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
 * Handler that injects AI Grader Pro fields into mod_assign's edit form
 * and persists them to local_aigrader_assign.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\form;

use local_aigrader\rubric\importer;
use stdClass;
/**
 * Class assign_form_handler.
 */
class assign_form_handler {
    /** @var string Field prefix to avoid collisions with assign's own fields. */
    private const FIELD_PREFIX = 'aigrader_';

    /**
     * Add our fields to the mod_form. Called from local_aigrader_coursemodule_standard_elements().
     *
     * @param \moodleform_mod $formwrapper The mod_form being built (assign or otherwise).
     * @param \MoodleQuickForm $mform Quickform reference to add elements to.
     */
    public static function add_elements($formwrapper, \MoodleQuickForm $mform): void {
        global $DB;

        // Only run on assign mod forms.
        if (!self::is_assign_form($formwrapper)) {
            return;
        }

        // Site-wide switch, course restriction and configure capability.
        if (!self::can_configure($formwrapper)) {
            return;
        }

        $current = $formwrapper->get_current();
        $assignid = isset($current->instance) ? (int) $current->instance : 0;
        $cmid     = isset($current->coursemodule) ? (int) $current->coursemodule : 0;

        $existing = $assignid
            ? $DB->get_record('local_aigrader_assign', ['assignid' => $assignid])
            : null;

        // Section header.
        $mform->addElement(
            'header',
            self::FIELD_PREFIX . 'header',
            get_string('pluginname', 'local_aigrader')
        );
        $mform->setExpanded(self::FIELD_PREFIX . 'header', false);

        // Enable toggle.
        $mform->addElement(
            'advcheckbox',
            self::FIELD_PREFIX . 'enabled',
            get_string('form_enabled', 'local_aigrader')
        );
        $mform->addHelpButton(self::FIELD_PREFIX . 'enabled', 'form_enabled', 'local_aigrader');
        $mform->setDefault(self::FIELD_PREFIX . 'enabled', $existing->enabled ?? 0);

        // Criteria textarea.
        $mform->addElement(
            'textarea',
            self::FIELD_PREFIX . 'criteria',
            get_string('form_criteria', 'local_aigrader'),
            ['rows' => 12, 'cols' => 70, 'style' => 'font-family: monospace;']
        );
        $mform->setType(self::FIELD_PREFIX . 'criteria', PARAM_RAW);
        $mform->addHelpButton(self::FIELD_PREFIX . 'criteria', 'form_criteria', 'local_aigrader');

        // Pre-fill criteria from existing config, OR from gradingform_rubric if auto-import enabled.
        $criteriadefault = $existing->criteria_text ?? '';
        $source = $existing->source ?? 'manual';
        if ($criteriadefault === '' && $cmid && get_config('local_aigrader', 'rubric_autoimport')) {
            $imported = importer::import_for_cmid($cmid);
            if ($imported !== null) {
                $criteriadefault = $imported;
                $source = 'rubric_imported';
                // Show a hint to the teacher.
                $mform->addElement(
                    'static',
                    self::FIELD_PREFIX . 'rubric_notice',
                    '',
                    \html_writer::div(
                        get_string('form_criteria_imported_notice', 'local_aigrader'),
                        'alert alert-info'
                    )
                );
            }
        }
        $mform->setDefault(self::FIELD_PREFIX . 'criteria', $criteriadefault);

        // Copy the criteria of another assignment the teacher can configure.
        // The button reloads the form with the copied text (see
        // definition_after_data()) so it can be reviewed before saving.
        $candidates = self::copy_candidates($formwrapper, $assignid);
        if ($candidates) {
            $mform->addGroup([
                $mform->createElement(
                    'select',
                    self::FIELD_PREFIX . 'copyfrom',
                    get_string('form_copyfrom', 'local_aigrader'),
                    ['' => get_string('form_copyfrom_choose', 'local_aigrader')] + $candidates
                ),
                $mform->createElement(
                    'submit',
                    self::FIELD_PREFIX . 'copybutton',
                    get_string('form_copyfrom_button', 'local_aigrader')
                ),
            ], self::FIELD_PREFIX . 'copygroup', get_string('form_copyfrom', 'local_aigrader'), ' ', false);
            $mform->setType(self::FIELD_PREFIX . 'copyfrom', PARAM_INT);
            $mform->registerNoSubmitButton(self::FIELD_PREFIX . 'copybutton');
            $mform->addHelpButton(self::FIELD_PREFIX . 'copygroup', 'form_copyfrom', 'local_aigrader');
            $mform->hideIf(self::FIELD_PREFIX . 'copygroup', self::FIELD_PREFIX . 'enabled', 'notchecked');
        }

        // Track source as hidden field (UI users don't set it, code does).
        $mform->addElement('hidden', self::FIELD_PREFIX . 'source', $source);
        $mform->setType(self::FIELD_PREFIX . 'source', PARAM_ALPHANUMEXT);

        // Language override (optional).
        $langoptions = ['' => get_string('form_lang_auto', 'local_aigrader')];
        foreach (get_string_manager()->get_list_of_translations() as $code => $name) {
            $langoptions[$code] = $name;
        }
        $mform->addElement(
            'select',
            self::FIELD_PREFIX . 'language_override',
            get_string('form_language_override', 'local_aigrader'),
            $langoptions
        );
        $mform->addHelpButton(self::FIELD_PREFIX . 'language_override', 'form_language_override', 'local_aigrader');
        $mform->setDefault(self::FIELD_PREFIX . 'language_override', $existing->language_override ?? '');

        // Grade automatically when a student submits (off by default: every
        // submission then costs an AI call).
        $mform->addElement(
            'advcheckbox',
            self::FIELD_PREFIX . 'autograde',
            get_string('form_autograde', 'local_aigrader')
        );
        $mform->addHelpButton(self::FIELD_PREFIX . 'autograde', 'form_autograde', 'local_aigrader');
        $mform->setDefault(self::FIELD_PREFIX . 'autograde', $existing->autograde ?? 0);

        // Only show the rest of fields if enabled. Cosmetic hideIf.
        $mform->hideIf(self::FIELD_PREFIX . 'criteria', self::FIELD_PREFIX . 'enabled', 'notchecked');
        $mform->hideIf(self::FIELD_PREFIX . 'language_override', self::FIELD_PREFIX . 'enabled', 'notchecked');
        $mform->hideIf(self::FIELD_PREFIX . 'autograde', self::FIELD_PREFIX . 'enabled', 'notchecked');
    }

    /**
     * After the form has its data: when the teacher pressed "Copy", fill the
     * criteria (and feedback language) with those of the chosen assignment.
     * Called from local_aigrader_coursemodule_definition_after_data().
     *
     * @param \moodleform_mod $formwrapper The mod_form being displayed.
     * @param \MoodleQuickForm $mform Quickform reference.
     */
    public static function definition_after_data($formwrapper, \MoodleQuickForm $mform): void {
        global $DB;

        if (!self::is_assign_form($formwrapper) || !$mform->elementExists(self::FIELD_PREFIX . 'copygroup')) {
            return;
        }
        if (!$mform->isSubmitted() || $mform->getSubmitValue(self::FIELD_PREFIX . 'copybutton') === null) {
            return;
        }

        // Only an assignment that was offered to this teacher can be copied.
        $sourceid = (int) $mform->getSubmitValue(self::FIELD_PREFIX . 'copyfrom');
        $currentid = (int) ($formwrapper->get_current()->instance ?? 0);
        $candidates = self::copy_candidates($formwrapper, $currentid);
        if (!isset($candidates[$sourceid])) {
            return;
        }
        $source = $DB->get_record('local_aigrader_assign', ['assignid' => $sourceid], 'criteria_text, language_override');
        if (!$source) {
            return;
        }

        // The page reloads at the top: open our section so the teacher sees the result.
        $mform->setExpanded(self::FIELD_PREFIX . 'header', true);
        $mform->getElement(self::FIELD_PREFIX . 'enabled')->setValue(1);
        $mform->getElement(self::FIELD_PREFIX . 'criteria')->setValue((string) $source->criteria_text);
        $mform->getElement(self::FIELD_PREFIX . 'language_override')->setValue((string) ($source->language_override ?? ''));
        $mform->insertElementBefore(
            $mform->createElement(
                'static',
                self::FIELD_PREFIX . 'copied_notice',
                '',
                \html_writer::div(get_string('form_copyfrom_done', 'local_aigrader', $candidates[$sourceid]), 'alert alert-success')
            ),
            self::FIELD_PREFIX . 'criteria'
        );
    }

    /**
     * Other assignments whose AI Grader Pro criteria the current user may
     * copy: those with criteria, in this course or in the user's other
     * courses, where the user has local/aigrader:configure.
     *
     * @param \moodleform_mod $formwrapper The mod_form being built.
     * @param int $currentassignid Id of the assignment being edited (0 when new).
     * @return array<int, string> Assignment id => "COURSE: Assignment name", this course first.
     */
    public static function copy_candidates($formwrapper, int $currentassignid): array {
        global $DB, $USER;

        $courseid = (int) $formwrapper->get_course()->id;
        $courseids = array_keys(enrol_get_users_courses($USER->id, true, 'id'));
        $courseids[] = $courseid;
        [$insql, $params] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);
        $params['modname'] = 'assign';
        $params['current'] = $currentassignid;

        $rows = $DB->get_records_sql(
            "SELECT la.assignid, a.name, a.course, c.shortname, cm.id AS cmid
               FROM {local_aigrader_assign} la
               JOIN {assign} a ON a.id = la.assignid
               JOIN {course} c ON c.id = a.course
               JOIN {modules} m ON m.name = :modname
               JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = a.id
              WHERE a.course $insql
                AND la.assignid <> :current
                AND " . $DB->sql_isnotempty('local_aigrader_assign', 'la.criteria_text', true, true) . "
           ORDER BY c.shortname, a.name",
            $params,
            0,
            200
        );

        $here = [];
        $elsewhere = [];
        foreach ($rows as $row) {
            if (!has_capability('local/aigrader:configure', \context_module::instance($row->cmid))) {
                continue;
            }
            $label = format_string($row->shortname) . ': ' . format_string($row->name);
            if ((int) $row->course === $courseid) {
                $here[(int) $row->assignid] = $label;
            } else {
                $elsewhere[(int) $row->assignid] = $label;
            }
        }
        return $here + $elsewhere;
    }

    /**
     * Validate the submitted form data.
     *
     * @param \moodleform_mod $formwrapper The mod_form being validated.
     * @param array $data Submitted form data.
     * @return array Map of field name => error message string.
     */
    public static function validate($formwrapper, array $data): array {
        $errors = [];
        if (!self::can_configure($formwrapper)) {
            return $errors;
        }

        $enabled = !empty($data[self::FIELD_PREFIX . 'enabled']);
        $criteria = trim($data[self::FIELD_PREFIX . 'criteria'] ?? '');

        if ($enabled && $criteria === '') {
            $errors[self::FIELD_PREFIX . 'criteria'] = get_string('error_criteria_required', 'local_aigrader');
        }

        return $errors;
    }

    /**
     * Persist the form data to local_aigrader_assign after the assignment is saved.
     *
     * @param \stdClass $moduleinfo Saved module info from mod_form (includes ->instance).
     * @param \stdClass $course Course the module belongs to.
     * @return \stdClass The same $moduleinfo (Moodle convention for post-actions callbacks).
     */
    public static function save($moduleinfo, $course) {
        global $DB, $USER;

        if (($moduleinfo->modulename ?? '') !== 'assign') {
            return $moduleinfo;
        }
        // Only persist when our fields were part of the submitted form. They
        // are absent when add_elements() did not show them (plugin disabled,
        // course not allowed, no configure capability) and when the module is
        // created programmatically or restored; saving anyway would overwrite
        // the existing configuration with empty values.
        if (!property_exists($moduleinfo, self::FIELD_PREFIX . 'enabled')) {
            return $moduleinfo;
        }
        if (!\local_aigrader\availability::is_available_in_course($course)) {
            return $moduleinfo;
        }

        $assignid = isset($moduleinfo->instance) ? (int) $moduleinfo->instance : 0;
        if (!$assignid) {
            return $moduleinfo;
        }
        if (!has_capability('local/aigrader:configure', \context_module::instance((int) $moduleinfo->coursemodule))) {
            return $moduleinfo;
        }

        $now = time();
        $enabled = !empty($moduleinfo->{self::FIELD_PREFIX . 'enabled'}) ? 1 : 0;
        $criteria = trim($moduleinfo->{self::FIELD_PREFIX . 'criteria'} ?? '');
        $source = $moduleinfo->{self::FIELD_PREFIX . 'source'} ?? 'manual';
        $languageoverride = $moduleinfo->{self::FIELD_PREFIX . 'language_override'} ?? '';

        $existing = $DB->get_record('local_aigrader_assign', ['assignid' => $assignid]);

        // If the teacher edited the auto-imported criteria, flag the source as rubric_edited.
        if ($existing && $existing->source === 'rubric_imported' && $existing->criteria_text !== $criteria) {
            $source = 'rubric_edited';
        }

        $record = new stdClass();
        $record->assignid = $assignid;
        $record->enabled = $enabled;
        $record->criteria_text = $criteria;
        $record->source = $source ?: 'manual';
        // The per-assignment model override was removed in v1.0.27: Moodle's AI
        // subsystem cannot pick a model per request, so the value was never
        // used. Clear any value left over from earlier versions.
        $record->model_override = null;
        $record->language_override = $languageoverride !== '' ? $languageoverride : null;
        $record->autograde = !empty($moduleinfo->{self::FIELD_PREFIX . 'autograde'}) ? 1 : 0;
        $record->usermodified = $USER->id;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aigrader_assign', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_aigrader_assign', $record);
        }

        return $moduleinfo;
    }

    /**
     * Whether the current user may see and change AI Grader Pro settings in
     * this form: it is the assignment form, the plugin is available in the
     * course, and the user has local/aigrader:configure.
     *
     * @param \moodleform_mod $formwrapper The mod_form being built or validated.
     * @return bool
     */
    private static function can_configure($formwrapper): bool {
        if (!self::is_assign_form($formwrapper)) {
            return false;
        }
        if (!\local_aigrader\availability::is_available_in_course($formwrapper->get_course())) {
            return false;
        }
        return has_capability('local/aigrader:configure', $formwrapper->get_context());
    }

    /**
     * Returns true if the form being built is the mod_assign edit form.
     *
     * @param \moodleform_mod $formwrapper The mod_form being introspected.
     * @return bool True for mod_assign, false for everything else.
     */
    private static function is_assign_form($formwrapper): bool {
        $current = $formwrapper->get_current();
        return isset($current->modulename) && $current->modulename === 'assign';
    }
}
