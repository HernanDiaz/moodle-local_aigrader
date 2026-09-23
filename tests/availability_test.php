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

use local_aigrader\form\assign_form_handler;

/**
 * Tests for restricting AI Grader Pro to some categories or courses.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\availability
 * @covers     \local_aigrader\form\assign_form_handler
 */
final class availability_test extends \advanced_testcase {
    /**
     * With no restriction configured, every course qualifies; the site-wide
     * switch still turns everything off.
     */
    public function test_unrestricted_and_globally_disabled(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(availability::is_available_in_course($course));

        set_config('enabled', 0, 'local_aigrader');
        $this->assertFalse(availability::is_available_in_course($course));
    }

    /**
     * Allowing a category also allows its subcategories, and nothing else.
     */
    public function test_category_restriction_includes_subcategories(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id]);
        $other = $generator->create_category();

        $inchild = $generator->create_course(['category' => $child->id]);
        $inother = $generator->create_course(['category' => $other->id]);

        set_config('allowedcategories', (string) $parent->id, 'local_aigrader');

        $this->assertTrue(availability::is_available_in_course($inchild));
        $this->assertFalse(availability::is_available_in_course($inother));
    }

    /**
     * Courses can be allowed by short name, case-insensitively, one per line.
     */
    public function test_course_restriction_by_shortname(): void {
        $this->resetAfterTest();
        $pilot = $this->getDataGenerator()->create_course(['shortname' => 'PILOT-101']);
        $notpilot = $this->getDataGenerator()->create_course(['shortname' => 'OTHER-202']);

        set_config('allowedcourses', "pilot-101\n  another-course  ", 'local_aigrader');

        $this->assertTrue(availability::is_available_in_course($pilot));
        $this->assertFalse(availability::is_available_in_course($notpilot));
        $this->assertSame(['pilot-101', 'another-course'], availability::get_allowed_course_shortnames());
    }

    /**
     * require_available_in_course() throws where the plugin is not available.
     */
    public function test_require_available_throws(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'NOT-ALLOWED']);
        set_config('allowedcourses', 'SOMETHING-ELSE', 'local_aigrader');

        $this->expectException(\moodle_exception::class);
        availability::require_available_in_course($course);
    }

    /**
     * Saving an assignment without AI Grader Pro fields in the form (plugin
     * hidden, module created programmatically, restore) must not overwrite
     * the existing configuration.
     */
    public function test_save_without_our_fields_keeps_configuration(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment(
            $assign,
            ['criteria_text' => 'Keep me']
        );

        $moduleinfo = (object) [
            'modulename'   => 'assign',
            'instance'     => $assign->id,
            'coursemodule' => $module->cmid,
        ];
        assign_form_handler::save($moduleinfo, $course);

        $config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id], '*', MUST_EXIST);
        $this->assertSame('1', (string) $config->enabled);
        $this->assertSame('Keep me', $config->criteria_text);
    }

    /**
     * A user without local/aigrader:configure cannot change the configuration
     * through the assignment form, even by posting our fields.
     */
    public function test_save_requires_configure_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment(
            $assign,
            ['criteria_text' => 'Keep me']
        );

        // A non-editing teacher can see the course but has no configure capability.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);

        $moduleinfo = (object) [
            'modulename'        => 'assign',
            'instance'          => $assign->id,
            'coursemodule'      => $module->cmid,
            'aigrader_enabled'  => 0,
            'aigrader_criteria' => '',
        ];
        assign_form_handler::save($moduleinfo, $course);

        $config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id], '*', MUST_EXIST);
        $this->assertSame('1', (string) $config->enabled);
        $this->assertSame('Keep me', $config->criteria_text);
    }
}
