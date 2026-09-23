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
 * Backup of AI Grader Pro data inside mod_assign activity backups.
 *
 * Local plugins attach to the "module" connection point, i.e. module.xml,
 * which Moodle writes for EVERY activity in the backup. This class:
 *
 *   - skips every activity that is not an assignment;
 *   - always includes the per-assignment configuration
 *     (local_aigrader_assign: criteria, feedback language...);
 *   - when the backup includes user data, also includes the AI proposals and
 *     teacher decisions (local_aigrader_submission) and the audit log
 *     (local_aigrader_log) of that assignment.
 *
 * v1.0.26 treated the connection point as an object and crashed every
 * activity backup with "Call to a member function get_element() on string";
 * it also keyed the query on the course module id instead of the assignment
 * id. Both are fixed here.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the backup structure for local_aigrader in module.xml.
 */
class backup_local_aigrader_plugin extends backup_local_plugin {
    /**
     * Add AI Grader Pro data to the module.xml of assignment activities.
     */
    protected function define_module_plugin_structure() {
        if ($this->task->get_modulename() !== 'assign') {
            return;
        }

        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // Per-assignment configuration. The id and assignid are regenerated
        // on restore, so assignid is not stored.
        $config = new backup_nested_element('aigrader_config', ['id'], [
            'enabled',
            'criteria_text',
            'source',
            'model_override',
            'language_override',
            'usermodified',
            'timecreated',
            'timemodified',
        ]);
        $wrapper->add_child($config);
        // In module.xml the parent element is the course module, so key on
        // the activity (assignment) id rather than VAR_PARENTID.
        $config->set_source_table('local_aigrader_assign', ['assignid' => backup::VAR_ACTIVITYID]);
        $config->annotate_ids('user', 'usermodified');

        if (!$this->get_setting_value('userinfo')) {
            return;
        }

        // AI proposals and teacher decisions, one row per student submission.
        $submissions = new backup_nested_element('aigrader_submissions');
        $submission = new backup_nested_element('aigrader_submission', ['id'], [
            'submissionid',
            'studentid',
            'status',
            'proposed_grade',
            'proposed_feedback',
            'final_grade',
            'final_feedback',
            'final_grader',
            'error_message',
            'timecreated',
            'timemodified',
            'timeprocessed',
            'timepublished',
        ]);
        $wrapper->add_child($submissions);
        $submissions->add_child($submission);
        $submission->set_source_table('local_aigrader_submission', ['assignid' => backup::VAR_ACTIVITYID]);
        $submission->annotate_ids('user', 'studentid');
        $submission->annotate_ids('user', 'final_grader');

        // Audit log entries for the submissions of this assignment.
        $logs = new backup_nested_element('aigrader_logs');
        $log = new backup_nested_element('aigrader_log', ['id'], [
            'submissionid',
            'userid',
            'studentid',
            'action',
            'llm_provider',
            'llm_model',
            'prompt_hash',
            'prompt_text',
            'response_json',
            'tokens_input',
            'tokens_output',
            'cost_usd',
            'duration_ms',
            'proposed_grade',
            'final_grade',
            'teacher_edits',
            'submission_format',
            'timecreated',
        ]);
        $wrapper->add_child($logs);
        $logs->add_child($log);
        $log->set_source_sql(
            'SELECT l.*
               FROM {local_aigrader_log} l
               JOIN {assign_submission} s ON s.id = l.submissionid
              WHERE s.assignment = ?
           ORDER BY l.id',
            [backup::VAR_ACTIVITYID]
        );
        $log->annotate_ids('user', 'userid');
        $log->annotate_ids('user', 'studentid');
    }
}
