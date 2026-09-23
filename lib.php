<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Global functions for AI Grader Pro.
 *
 * Hooks into mod_assign's edit form via Moodle's coursemodule_* callbacks.
 * Each callback delegates to a static method on assign_form_handler.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_aigrader\form\assign_form_handler;

/**
 * Add AI Grader Pro fields to the assignment edit form.
 *
 * @param moodleform $formwrapper The mod_form being built.
 * @param MoodleQuickForm $mform Quickform reference.
 */
function local_aigrader_coursemodule_standard_elements($formwrapper, $mform): void {
    assign_form_handler::add_elements($formwrapper, $mform);
}

/**
 * Fill the criteria copied from another assignment when the teacher pressed "Copy".
 *
 * @param moodleform $formwrapper The mod_form being displayed.
 * @param MoodleQuickForm $mform Quickform reference.
 */
function local_aigrader_coursemodule_definition_after_data($formwrapper, $mform): void {
    assign_form_handler::definition_after_data($formwrapper, $mform);
}

/**
 * Validate AI Grader Pro fields submitted via the assignment edit form.
 *
 * @param moodleform $formwrapper
 * @param array $data Submitted form data.
 * @return array Map of field => error message.
 */
function local_aigrader_coursemodule_validation($formwrapper, $data): array {
    return assign_form_handler::validate($formwrapper, $data);
}

/**
 * Persist AI Grader Pro config after an assignment is saved.
 *
 * @param stdClass $moduleinfo Saved module info (includes instance id).
 * @param stdClass $course
 * @return stdClass
 */
function local_aigrader_coursemodule_edit_post_actions($moduleinfo, $course) {
    return assign_form_handler::save($moduleinfo, $course);
}

/**
 * Add a link to AI Grader Pro's management page in the assignment's
 * settings menu (the gear icon when viewing an assignment).
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_aigrader_extend_settings_navigation(\settings_navigation $settingsnav, \context $context): void {
    // Only act on module contexts (assignment pages).
    if ($context->contextlevel !== CONTEXT_MODULE) {
        return;
    }
    $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
    if (!$cm || $cm->modname !== 'assign') {
        return;
    }

    // Site-wide switch and course restriction.
    if (!\local_aigrader\availability::is_available_in_course(get_course($cm->course))) {
        return;
    }

    // Capability check.
    if (!has_capability('local/aigrader:use', $context)) {
        return;
    }

    // Only show the link if AI Grader Pro is enabled on THIS assignment.
    global $DB;
    $config = $DB->get_record('local_aigrader_assign', ['assignid' => $cm->instance]);
    if (!$config || empty($config->enabled)) {
        return;
    }

    // Find the assignment's settings node (modulesettings).
    $modulenode = $settingsnav->find('modulesettings', \settings_navigation::TYPE_SETTING);
    if (!$modulenode) {
        return;
    }

    $url  = new \moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]);
    $node = \navigation_node::create(
        get_string('pluginname', 'local_aigrader'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_aigrader_manage',
        new \pix_icon('i/scales', '')
    );
    $modulenode->add_node($node);
}
