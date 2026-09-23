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
 * Event observers of AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A student submitted an assignment for grading (or saved it, when the
     * assignment has no "submit" button): queue automatic AI grading if the
     * assignment asks for it.
     *
     * Never throws: a problem here must not stop the student's submission.
     *
     * @param \mod_assign\event\assessable_submitted $event The event.
     */
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event): void {
        try {
            autograde::queue_for_submission((int) $event->objectid);
        } catch (\Throwable $e) {
            debugging('AI Grader Pro could not queue automatic grading: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
