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

/**
 * Tests for automatic AI grading when a student submits.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\autograde
 * @covers     \local_aigrader\observer
 */
final class autograde_test extends \advanced_testcase {
    /** @var \stdClass Course of the assignment. */
    private \stdClass $course;

    /** @var \stdClass Editing teacher who configured AI Grader Pro. */
    private \stdClass $teacher;

    /** @var \stdClass Student who submits. */
    private \stdClass $student;

    /** @var \stdClass The assignment module (with cmid). */
    private \stdClass $module;

    /** @var \stdClass The {assign} row. */
    private \stdClass $assign;

    /**
     * An assignment without a "submit" button (each save is a submission),
     * configured by a teacher with automatic grading on.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->module = $generator->create_module('assign', [
            'course' => $this->course->id,
            'submissiondrafts' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->assign = $DB->get_record('assign', ['id' => $this->module->id], '*', MUST_EXIST);
        $generator->get_plugin_generator('local_aigrader')->enable_for_assignment($this->assign, [
            'autograde'    => 1,
            'usermodified' => $this->teacher->id,
        ]);
    }

    /**
     * Submitting queues one grading task that runs as the teacher, and the
     * submission shows as pending in the panel.
     */
    public function test_submission_queues_grading_as_the_teacher(): void {
        global $DB;

        $this->submit();

        $tasks = $this->queued_tasks();
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $submission = $this->submission();
        $this->assertEquals($submission->id, $task->get_custom_data()->submissionid);
        $this->assertEquals($this->teacher->id, $task->get_userid());
        $this->assertSame('pending_ai', $DB->get_field('local_aigrader_submission', 'status', ['submissionid' => $submission->id]));
    }

    /**
     * Saving again while the task is still queued does not queue a second one.
     */
    public function test_repeated_submissions_queue_one_task(): void {
        $this->submit();
        $this->submit('Second version.');
        $this->assertTrue(autograde::queue_for_submission((int) $this->submission()->id));

        $this->assertCount(1, $this->queued_tasks());
    }

    /**
     * Nothing is queued when automatic grading is off, which is the default.
     */
    public function test_nothing_queued_when_autograde_off(): void {
        global $DB;
        $DB->set_field('local_aigrader_assign', 'autograde', 0, ['assignid' => $this->assign->id]);

        $this->submit();

        $this->assertCount(0, $this->queued_tasks());
        $this->assertFalse($DB->record_exists('local_aigrader_submission', ['assignid' => $this->assign->id]));
    }

    /**
     * A proposal the teacher has reviewed or published is never replaced by a
     * new automatic grading.
     */
    public function test_teacher_decision_is_not_replaced(): void {
        global $DB;
        $this->submit();
        $this->queued_tasks(true);
        $submission = $this->submission();
        $DB->set_field('local_aigrader_submission', 'status', 'teacher_reviewed', ['submissionid' => $submission->id]);

        $this->assertFalse(autograde::queue_for_submission((int) $submission->id));

        $this->assertCount(0, $this->queued_tasks());
        $this->assertSame('teacher_reviewed', $DB->get_field('local_aigrader_submission', 'status', ['submissionid' => $submission->id]));
    }

    /**
     * If the teacher who turned it on can no longer use AI Grader Pro in the
     * assignment, the task runs as the site administrator.
     */
    public function test_falls_back_to_admin_when_teacher_left(): void {
        $enrol = enrol_get_plugin('manual');
        $instance = current(enrol_get_instances($this->course->id, true));
        $enrol->unenrol_user($instance, $this->teacher->id);

        $this->submit();

        $task = current($this->queued_tasks());
        $this->assertEquals(get_admin()->id, $task->get_userid());
    }

    /**
     * Nothing is queued in a course where the administrator did not allow
     * the plugin.
     */
    public function test_not_queued_where_plugin_not_available(): void {
        set_config('allowedcourses', 'SOME-OTHER-COURSE', 'local_aigrader');

        $this->submit();

        $this->assertCount(0, $this->queued_tasks());
    }

    /**
     * Submit online text as the student.
     *
     * @param string $text Online text.
     */
    private function submit(string $text = 'My essay.'): void {
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
            'userid'     => $this->student->id,
            'cmid'       => $this->module->cmid,
            'onlinetext' => $text,
        ]);
    }

    /**
     * The student's submission row.
     *
     * @return \stdClass
     */
    private function submission(): \stdClass {
        global $DB;
        return $DB->get_record('assign_submission', ['assignment' => $this->assign->id, 'userid' => $this->student->id], '*', MUST_EXIST);
    }

    /**
     * Queued grading tasks, optionally clearing them.
     *
     * @param bool $clear Delete them after reading.
     * @return task\grade_submission[]
     */
    private function queued_tasks(bool $clear = false): array {
        global $DB;
        $tasks = \core\task\manager::get_adhoc_tasks(task\grade_submission::class);
        if ($clear) {
            $DB->delete_records('task_adhoc', ['classname' => '\\' . task\grade_submission::class]);
        }
        return $tasks;
    }
}
