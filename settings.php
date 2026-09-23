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
 * Sitewide admin settings page for AI Grader Pro.
 *
 * Appears in: Site administration > Plugins > Local plugins > AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_aigrader_settings',
        get_string('pluginname', 'local_aigrader')
    );

    // 1. Global on/off switch.
    $settings->add(new admin_setting_configcheckbox(
        'local_aigrader/enabled',
        get_string('setting_enabled', 'local_aigrader'),
        get_string('setting_enabled_desc', 'local_aigrader'),
        1
    ));

    // 2. Auto-import criteria from gradingform_rubric when available.
    $settings->add(new admin_setting_configcheckbox(
        'local_aigrader/rubric_autoimport',
        get_string('setting_rubric_autoimport', 'local_aigrader'),
        get_string('setting_rubric_autoimport_desc', 'local_aigrader'),
        1
    ));

    // 3. Institution-wide system prompt prefix.
    $settings->add(new admin_setting_configtextarea(
        'local_aigrader/default_system_prompt',
        get_string('setting_default_system_prompt', 'local_aigrader'),
        get_string('setting_default_system_prompt_desc', 'local_aigrader'),
        '',
        PARAM_RAW,
        60,
        8
    ));

    // 4. Where AI Grader Pro is available. Both empty = every course.
    $settings->add(new admin_setting_heading(
        'local_aigrader/availabilityheading',
        get_string('setting_availability_heading', 'local_aigrader'),
        get_string('setting_availability_heading_desc', 'local_aigrader')
    ));
    $settings->add(new admin_setting_configmultiselect(
        'local_aigrader/allowedcategories',
        get_string('setting_allowedcategories', 'local_aigrader'),
        get_string('setting_allowedcategories_desc', 'local_aigrader'),
        [],
        [\local_aigrader\availability::class, 'get_category_choices']
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_aigrader/allowedcourses',
        get_string('setting_allowedcourses', 'local_aigrader'),
        get_string('setting_allowedcourses_desc', 'local_aigrader'),
        '',
        PARAM_RAW_TRIMMED,
        40,
        5
    ));

    $ADMIN->add('localplugins', $settings);
}
