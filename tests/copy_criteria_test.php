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
 * Tests for the list of assignments a teacher may copy criteria from.
 * The copy itself, through the form, is covered by Behat.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\form\assign_form_handler::copy_candidates
 */
final class copy_criteria_test extends \advanced_testcase {
    /**
     * Offered: assignments with criteria in this course (first) and in other
     * courses where the teacher can configure AI Grader Pro. Not offered: the
     * assignment being edited, assignments without criteria, courses the
     * teacher is not in or only a non-editing teacher of.
     */
    public function test_candidates_respect_courses_and_capability(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();

        $here = $generator->create_course(['shortname' => 'ZHERE']);
        $mine = $generator->create_course(['shortname' => 'AMINE']);
        $notmine = $generator->create_course(['shortname' => 'NOTMINE']);
        $viewonly = $generator->create_course(['shortname' => 'VIEWONLY']);
        $generator->enrol_user($teacher->id, $here->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $mine->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $viewonly->id, 'teacher');

        $current = $this->assignment($here, 'Current', 'Its own criteria');
        $sibling = $this->assignment($here, 'Sibling', 'Sibling criteria');
        $this->assignment($here, 'No criteria', '');
        $othercourse = $this->assignment($mine, 'Other course', 'Other criteria');
        $this->assignment($notmine, 'Not mine', 'Hidden criteria');
        $this->assignment($viewonly, 'View only', 'Hidden criteria');

        $this->setUser($teacher);
        $formwrapper = new class ($here) {
            /** @var \stdClass The course of the form. */
            private \stdClass $course;

            /**
             * Stub of the mod_form: only the course is needed.
             *
             * @param \stdClass $course The course of the form.
             */
            public function __construct(\stdClass $course) {
                $this->course = $course;
            }

            /**
             * The course of the form.
             *
             * @return \stdClass
             */
            public function get_course(): \stdClass {
                return $this->course;
            }
        };

        $candidates = assign_form_handler::copy_candidates($formwrapper, (int) $current->id);

        $this->assertSame([(int) $sibling->id, (int) $othercourse->id], array_keys($candidates));
        $this->assertSame('ZHERE: Sibling', $candidates[$sibling->id]);
        $this->assertSame('AMINE: Other course', $candidates[$othercourse->id]);
    }

    /**
     * Create an assignment with AI Grader Pro configured.
     *
     * @param \stdClass $course Course.
     * @param string $name Assignment name.
     * @param string $criteria Criteria ('' for none).
     * @return \stdClass The {assign} row.
     */
    private function assignment(\stdClass $course, string $name, string $criteria): \stdClass {
        global $DB;
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'name' => $name]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment(
            $assign,
            ['criteria_text' => $criteria, 'enabled' => $criteria === '' ? 0 : 1]
        );
        return $assign;
    }
}
