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

/**
 * Decides in which courses AI Grader Pro can be used.
 *
 * Site administrators can restrict the plugin to some course categories
 * (subcategories included) and/or to individual courses listed by short
 * name. With no restriction configured, every course qualifies — the
 * behaviour of earlier versions.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * Course-level availability of AI Grader Pro.
 */
final class availability {
    /**
     * Whether AI Grader Pro can be used in a course.
     *
     * True when the plugin is enabled site-wide and, if the administrator
     * restricted it, the course is inside one of the allowed categories or
     * is listed explicitly.
     *
     * @param \stdClass $course Course record (id, shortname and category are used).
     * @return bool
     */
    public static function is_available_in_course(\stdClass $course): bool {
        if (!get_config('local_aigrader', 'enabled')) {
            return false;
        }

        $categoryids = self::get_allowed_category_ids();
        $shortnames = self::get_allowed_course_shortnames();
        if (!$categoryids && !$shortnames) {
            return true;
        }

        if (in_array(\core_text::strtolower(trim((string) ($course->shortname ?? ''))), $shortnames, true)) {
            return true;
        }

        if ($categoryids && !empty($course->category)) {
            $category = \core_course_category::get((int) $course->category, IGNORE_MISSING, true);
            if ($category) {
                // Path is "/<top>/<child>/<this>": the course's category and all its parents.
                $path = array_map('intval', explode('/', trim($category->path, '/')));
                if (array_intersect($path, $categoryids)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Throw unless AI Grader Pro can be used in the course.
     *
     * @param \stdClass $course Course record.
     * @throws \moodle_exception When the plugin is disabled or restricted away from the course.
     */
    public static function require_available_in_course(\stdClass $course): void {
        if (!self::is_available_in_course($course)) {
            throw new \moodle_exception('errornotavailable', 'local_aigrader');
        }
    }

    /**
     * Ids of the categories the administrator allowed.
     *
     * @return int[] Empty when not restricted by category.
     */
    public static function get_allowed_category_ids(): array {
        $raw = (string) get_config('local_aigrader', 'allowedcategories');
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /**
     * Short names (lower-cased) of the courses the administrator allowed.
     *
     * @return string[] Empty when not restricted by course.
     */
    public static function get_allowed_course_shortnames(): array {
        $raw = (string) get_config('local_aigrader', 'allowedcourses');
        $names = array_map(
            fn($name) => \core_text::strtolower(trim($name)),
            preg_split('/[\r\n,]+/', $raw)
        );
        return array_values(array_filter($names, fn($name) => $name !== ''));
    }

    /**
     * Choices for the "allowed categories" admin setting, loaded only when
     * the settings page is displayed.
     *
     * @return array Category id => category path name.
     */
    public static function get_category_choices(): array {
        return \core_course_category::make_categories_list();
    }
}
