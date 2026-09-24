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

namespace local_aigrader\report;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_aigrader\privacy\provider;

/**
 * Privacy handling of class reports: only the teacher who generated a
 * report is personal data.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\privacy\provider
 */
final class report_privacy_test extends provider_testcase {
    /**
     * The teacher who generated a report is found, exported and anonymised;
     * deleting the assignment's context removes its reports.
     */
    public function test_report_author_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);
        $DB->insert_record('local_aigrader_report', (object) [
            'assignid' => $module->id, 'courseid' => $course->id, 'userid' => $teacher->id, 'submissions' => 5,
            'stats' => '{}', 'summary' => json_encode(['overview' => 'Good class']), 'timecreated' => time(),
        ]);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid($teacher->id)->get_contextids());
        $userlist = new userlist($context, 'local_aigrader');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$teacher->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($teacher, 'local_aigrader', [$context->id]));
        $report = $DB->get_record('local_aigrader_report', ['assignid' => $module->id], '*', MUST_EXIST);
        $exported = writer::with_context($context)->get_data(
            [get_string('pluginname', 'local_aigrader'), 'class_reports', (string) $report->id]
        );
        $this->assertSame('Good class', $exported->summary['overview']);

        provider::delete_data_for_users(new approved_userlist($context, 'local_aigrader', [$teacher->id]));
        $this->assertSame('0', (string) $DB->get_field('local_aigrader_report', 'userid', ['id' => $report->id]));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('local_aigrader_report'));
    }
}
