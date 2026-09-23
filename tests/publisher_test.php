<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aigrader;

use local_aigrader\bulk\dispatcher;

/**
 * End-to-end tests for publishing approved grades to mod_assign.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\publisher
 * @covers     \local_aigrader\bulk\dispatcher
 */
final class publisher_test extends \advanced_testcase {
    /**
     * Create a course, teacher, student, assignment, submission and AI proposal.
     *
     * The current user is switched to the teacher.
     *
     * @param array $assignoptions Extra settings for the assignment (e.g. grade).
     * @param array $proposaloverrides Overrides for the local_aigrader_submission row.
     * @return array Keys: assign (assign row), cm, course, context, student, row.
     */
    private function create_proposal(array $assignoptions, array $proposaloverrides = []): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $module = $generator->create_module('assign', array_merge([
            'course'                              => $course->id,
            'grade'                               => 100,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_comments_enabled'     => 1,
        ], $assignoptions));
        $generator->get_plugin_generator('mod_assign')->create_submission([
            'userid'     => $student->id,
            'cmid'       => $module->cmid,
            'onlinetext' => 'My essay about digital education.',
        ]);

        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $submission = $DB->get_record(
            'assign_submission',
            ['assignment' => $assign->id, 'userid' => $student->id],
            '*',
            MUST_EXIST
        );
        $plugingenerator = $generator->get_plugin_generator('local_aigrader');
        $plugingenerator->enable_for_assignment($assign);
        $row = $plugingenerator->create_submission_proposal($submission, $proposaloverrides);

        [$course, $cm] = get_course_and_cm_from_instance($assign->id, 'assign');
        $this->setUser($teacher);

        return [
            'assign'  => $assign,
            'cm'      => $cm,
            'course'  => $course,
            'context' => \context_module::instance($cm->id),
            'student' => $student,
            'row'     => $row,
        ];
    }

    /**
     * Publish the row's AI proposal unchanged through publisher::publish().
     *
     * @param array $fixture Result of create_proposal().
     */
    private function publish_unchanged(array $fixture): void {
        $proposed = json_decode($fixture['row']->proposed_feedback, true);
        publisher::publish(
            $fixture['row'],
            $proposed,
            $proposed,
            grading_scale::for_assign($fixture['assign'], $fixture['context']),
            $fixture['course'],
            $fixture['cm'],
            $fixture['context']
        );
    }

    /**
     * Read the student's grade from mod_assign.
     *
     * @param array $fixture Result of create_proposal().
     * @return \stdClass assign_grades row.
     */
    private function get_assign_grade(array $fixture): \stdClass {
        global $DB;
        return $DB->get_record(
            'assign_grades',
            ['assignment' => $fixture['assign']->id, 'userid' => $fixture['student']->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * An 8/10 proposal on a 100-point assignment lands in the gradebook as 80.
     */
    public function test_publish_converts_to_assignment_points(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_proposal(['grade' => 100]);

        $this->publish_unchanged($fixture);

        $this->assertEqualsWithDelta(80.0, (float) $this->get_assign_grade($fixture)->grade, 0.001);

        $row = $DB->get_record('local_aigrader_submission', ['id' => $fixture['row']->id]);
        $this->assertSame('published', $row->status);
        $this->assertEqualsWithDelta(8.0, (float) $row->final_grade, 0.001);

        $log = $DB->get_record('local_aigrader_log', ['submissionid' => $row->submissionid, 'action' => 'approve']);
        $this->assertNotFalse($log);
        $this->assertEqualsWithDelta(80.0, json_decode($log->response_json, true)['gradebook_grade'], 0.001);
    }

    /**
     * On a Moodle scale the proposal is mapped to the nearest scale item.
     */
    public function test_publish_maps_to_scale_item(): void {
        $this->resetAfterTest();
        $moodlescale = $this->getDataGenerator()->create_scale(['scale' => 'Fail,Pass,Merit']);
        $fixture = $this->create_proposal(['grade' => -$moodlescale->id]);

        $this->publish_unchanged($fixture);

        // The default proposal is 8/10, closest to "Merit" (item 3).
        $this->assertEqualsWithDelta(3.0, (float) $this->get_assign_grade($fixture)->grade, 0.001);
    }

    /**
     * On a "No grade" assignment only the feedback is published.
     */
    public function test_publish_without_grade_saves_feedback_only(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_proposal(['grade' => 0]);

        $this->publish_unchanged($fixture);

        $grade = $this->get_assign_grade($fixture);
        $this->assertEqualsWithDelta(-1.0, (float) $grade->grade, 0.001);
        $comment = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id], '*', MUST_EXIST);
        $this->assertStringContainsString('Clear thesis statement', $comment->commenttext);
    }

    /**
     * With a rubric active, publishing is refused and nothing changes.
     */
    public function test_publish_refuses_advanced_grading(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_proposal(['grade' => 100]);

        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')->create_instance(
            $fixture['context'],
            'mod_assign',
            'submissions',
            'Essay rubric',
            'Rubric for the test',
            ['Argument' => ['Weak' => 0, 'Solid' => 5, 'Excellent' => 10]]
        );

        try {
            $this->publish_unchanged($fixture);
            $this->fail('Publishing should be refused when a rubric is active.');
        } catch (\moodle_exception $e) {
            $this->assertSame('erroradvancedgrading', $e->errorcode);
        }
        $row = $DB->get_record('local_aigrader_submission', ['id' => $fixture['row']->id]);
        $this->assertSame('ai_proposed', $row->status);
    }

    /**
     * Bulk "Publish proposed grade" works and publishes the teacher's saved
     * draft rather than the raw AI proposal.
     *
     * Regression test: until v1.0.27 the bulk path pulled review.php in with
     * require_once, which re-ran config.php and failed every row.
     */
    public function test_bulk_publish_uses_teacher_draft(): void {
        $this->resetAfterTest();
        // AI proposed 8/10; the teacher saved a draft with 6.5/10.
        $fixture = $this->create_proposal(['grade' => 100], ['status' => 'teacher_reviewed', 'final_grade' => 6.5]);
        $sid = (int) $fixture['row']->submissionid;
        $row = (object) ['submissionid' => $sid, 'ai_status' => 'teacher_reviewed'];

        $result = dispatcher::execute(
            dispatcher::ACTION_APPROVE_PUBLISH,
            [$sid => $row],
            [$sid => dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $row)]
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['ok']);
        $this->assertEqualsWithDelta(65.0, (float) $this->get_assign_grade($fixture)->grade, 0.001);
    }
}
