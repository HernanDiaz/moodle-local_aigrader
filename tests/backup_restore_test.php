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
 * Backup and restore of AI Grader Pro data, through real Moodle backups.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_aigrader_plugin
 * @covers     \restore_local_aigrader_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Duplicating an assignment (a one-activity backup and restore, without
     * user data) keeps its AI Grader Pro configuration.
     */
    public function test_duplicate_assignment_keeps_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment($assign, [
            'criteria_text'     => 'TEST-MARKER evaluate thesis clarity',
            'language_override' => 'es',
            'autograde'         => 1,
        ]);

        $cm = get_coursemodule_from_id('assign', $module->cmid, 0, false, MUST_EXIST);
        $newcm = duplicate_module($course, $cm);

        $newconfig = $DB->get_record('local_aigrader_assign', ['assignid' => $newcm->instance]);
        $this->assertNotFalse($newconfig, 'The duplicated assignment has no AI Grader Pro configuration.');
        $this->assertSame('1', (string) $newconfig->enabled);
        $this->assertSame('TEST-MARKER evaluate thesis clarity', $newconfig->criteria_text);
        $this->assertSame('es', $newconfig->language_override);
        $this->assertSame('1', (string) $newconfig->autograde);
        // The original keeps its own row.
        $this->assertSame(2, $DB->count_records('local_aigrader_assign'));
    }

    /**
     * Backing up any other activity works and adds nothing.
     *
     * Regression test: v1.0.26 crashed the backup of every activity.
     */
    public function test_duplicate_other_activity_is_unaffected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('forum', $forum->cmid, 0, false, MUST_EXIST);

        $newcm = duplicate_module($course, $cm);

        $this->assertNotEmpty($newcm->id);
        $this->assertSame(0, $DB->count_records('local_aigrader_assign'));
    }

    /**
     * A course backup with user data carries proposals and the audit log,
     * re-pointed at the restored submissions.
     */
    public function test_course_backup_with_user_data_restores_proposals_and_log(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['shortname' => 'SRC']);
        $student = $generator->create_and_enrol($course, 'student');
        $module = $generator->create_module('assign', [
            'course'                              => $course->id,
            'name'                                => 'Essay to back up',
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $generator->get_plugin_generator('mod_assign')->create_submission([
            'userid'     => $student->id,
            'cmid'       => $module->cmid,
            'onlinetext' => 'My essay.',
        ]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $submission = $DB->get_record('assign_submission', ['assignment' => $assign->id, 'userid' => $student->id]);

        $plugingenerator = $generator->get_plugin_generator('local_aigrader');
        $plugingenerator->enable_for_assignment($assign);
        $plugingenerator->create_submission_proposal($submission, ['status' => 'teacher_reviewed']);
        $plugingenerator->create_log_entry($submission, ['action' => 'grade']);
        $DB->insert_record('local_aigrader_report', (object) [
            'assignid' => $assign->id, 'courseid' => $course->id, 'userid' => get_admin()->id, 'submissions' => 1,
            'stats' => '{}', 'summary' => json_encode(['overview' => 'REPORT-MARKER']), 'timecreated' => time(),
        ]);

        $newcourseid = $this->backup_and_restore_course($course);

        $newassign = $DB->get_record('assign', ['course' => $newcourseid, 'name' => 'Essay to back up'], '*', MUST_EXIST);
        $newsubmission = $DB->get_record(
            'assign_submission',
            ['assignment' => $newassign->id, 'userid' => $student->id],
            '*',
            MUST_EXIST
        );

        $this->assertTrue($DB->record_exists('local_aigrader_assign', ['assignid' => $newassign->id]));

        $proposal = $DB->get_record('local_aigrader_submission', ['submissionid' => $newsubmission->id]);
        $this->assertNotFalse($proposal, 'The proposal was not restored.');
        $this->assertSame('teacher_reviewed', $proposal->status);
        $this->assertEquals($newassign->id, $proposal->assignid);
        $this->assertEquals($newcourseid, $proposal->courseid);
        $this->assertEqualsWithDelta(7.5, (float) $proposal->final_grade, 0.001);

        $log = $DB->get_record('local_aigrader_log', ['submissionid' => $newsubmission->id]);
        $this->assertNotFalse($log, 'The audit log entry was not restored.');
        $this->assertEquals($newcourseid, $log->courseid);
        $this->assertSame('grade', $log->action);

        $report = $DB->get_record('local_aigrader_report', ['assignid' => $newassign->id]);
        $this->assertNotFalse($report, 'The class report was not restored.');
        $this->assertEquals($newcourseid, $report->courseid);
        $this->assertStringContainsString('REPORT-MARKER', $report->summary);
    }

    /**
     * Back up a course with user data and restore it as a new course.
     *
     * @param \stdClass $course Course to back up.
     * @return int Id of the restored course.
     */
    private function backup_and_restore_course(\stdClass $course): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $folder = 'aigrader_restore_' . $course->id;
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $folder);

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new \restore_controller(
            $folder,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return (int) $newcourseid;
    }
}
