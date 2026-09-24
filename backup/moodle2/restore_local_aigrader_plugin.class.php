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
 * Restore of AI Grader Pro data written by backup_local_aigrader_plugin.
 *
 * module.xml is restored before the activity itself, so while its elements
 * are processed the new assignment id and the new submission ids do not
 * exist yet. The rows are therefore collected during processing and written
 * in after_restore_module(), when the activity and every id mapping
 * (assignment, submissions, users) are available.
 *
 * Historical timestamps (when a proposal was made, when a grade was
 * published, audit entries) are kept as they were: they record events,
 * they are not course dates to shift.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the restore structure for local_aigrader in module.xml.
 */
class restore_local_aigrader_plugin extends restore_local_plugin {
    /** @var stdClass|null Configuration row waiting for the new assignment id. */
    private ?stdClass $pendingconfig = null;

    /** @var stdClass[] Proposal rows waiting for id mappings. */
    private array $pendingsubmissions = [];

    /** @var stdClass[] Audit log rows waiting for id mappings. */
    private array $pendinglogs = [];

    /** @var stdClass[] Class report rows waiting for the new assignment id. */
    private array $pendingreports = [];

    /**
     * Paths of AI Grader Pro elements inside module.xml.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        $paths = [
            new restore_path_element('aigrader_config', $this->get_pathfor('/aigrader_config')),
        ];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element(
                'aigrader_submission',
                $this->get_pathfor('/aigrader_submissions/aigrader_submission')
            );
            $paths[] = new restore_path_element('aigrader_log', $this->get_pathfor('/aigrader_logs/aigrader_log'));
            $paths[] = new restore_path_element('aigrader_report', $this->get_pathfor('/aigrader_reports/aigrader_report'));
        }
        return $paths;
    }

    /**
     * Collect the configuration row.
     *
     * @param array $data Decoded element data.
     */
    public function process_aigrader_config($data) {
        $this->pendingconfig = (object) $data;
    }

    /**
     * Collect one proposal row.
     *
     * @param array $data Decoded element data.
     */
    public function process_aigrader_submission($data) {
        $this->pendingsubmissions[] = (object) $data;
    }

    /**
     * Collect one audit log row.
     *
     * @param array $data Decoded element data.
     */
    public function process_aigrader_log($data) {
        $this->pendinglogs[] = (object) $data;
    }

    /**
     * Collect one class report row.
     *
     * @param array $data Decoded element data.
     */
    public function process_aigrader_report($data) {
        $this->pendingreports[] = (object) $data;
    }

    /**
     * Write the collected rows, now that the assignment and all mappings exist.
     */
    public function after_restore_module() {
        $assignid = (int) $this->task->get_activityid();
        if (!$assignid) {
            return;
        }
        if ($this->pendingconfig) {
            $this->restore_config($assignid);
        }
        $courseid = (int) $this->task->get_courseid();
        foreach ($this->pendingsubmissions as $row) {
            $this->restore_submission($row, $assignid, $courseid);
        }
        foreach ($this->pendinglogs as $row) {
            $this->restore_log($row, $courseid);
        }
        foreach ($this->pendingreports as $row) {
            $this->restore_report($row, $assignid, $courseid);
        }
    }

    /**
     * Insert one class report, attached to the restored assignment. A teacher
     * who does not exist on this site is recorded as user 0.
     *
     * @param stdClass $row Row as read from the backup.
     * @param int $assignid Id of the restored assignment.
     * @param int $courseid Id of the course restored into.
     */
    private function restore_report(stdClass $row, int $assignid, int $courseid): void {
        global $DB;

        $record = clone $row;
        unset($record->id);
        $record->assignid = $assignid;
        $record->courseid = $courseid;
        $record->userid = (int) ($this->get_mappingid('user', $row->userid) ?: 0);
        $DB->insert_record('local_aigrader_report', $record);
    }

    /**
     * Insert (or update) the configuration of the restored assignment.
     *
     * @param int $assignid Id of the restored assignment.
     */
    private function restore_config(int $assignid): void {
        global $DB, $USER;

        $record = clone $this->pendingconfig;
        unset($record->id);
        $record->assignid = $assignid;
        // The teacher who last edited the criteria may not exist on this site.
        $record->usermodified = (int) ($this->get_mappingid('user', $record->usermodified ?? 0) ?: $USER->id);

        $existing = $DB->get_record('local_aigrader_assign', ['assignid' => $assignid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aigrader_assign', $record);
        } else {
            $DB->insert_record('local_aigrader_assign', $record);
        }
    }

    /**
     * Insert one proposal row, re-pointed at the restored submission and student.
     *
     * Rows whose submission or student were not restored are skipped.
     *
     * @param stdClass $row Row as read from the backup.
     * @param int $assignid Id of the restored assignment.
     * @param int $courseid Id of the course restored into.
     */
    private function restore_submission(stdClass $row, int $assignid, int $courseid): void {
        global $DB;

        $submissionid = $this->get_mappingid('submission', $row->submissionid);
        $studentid = $this->get_mappingid('user', $row->studentid);
        if (!$submissionid || !$studentid) {
            return;
        }
        if ($DB->record_exists('local_aigrader_submission', ['submissionid' => $submissionid])) {
            return;
        }

        $record = clone $row;
        unset($record->id);
        $record->submissionid = $submissionid;
        $record->assignid = $assignid;
        $record->courseid = $courseid;
        $record->studentid = $studentid;
        $record->final_grader = !empty($row->final_grader)
            ? ($this->get_mappingid('user', $row->final_grader) ?: null)
            : null;
        $DB->insert_record('local_aigrader_submission', $record);
    }

    /**
     * Insert one audit log row, re-pointed at the restored submission and users.
     *
     * Entries whose submission or student were not restored are skipped. A
     * teacher who does not exist on this site is recorded as user 0, the same
     * anonymisation the privacy provider applies.
     *
     * @param stdClass $row Row as read from the backup.
     * @param int $courseid Id of the course restored into.
     */
    private function restore_log(stdClass $row, int $courseid): void {
        global $DB;

        $submissionid = $this->get_mappingid('submission', $row->submissionid);
        $studentid = $this->get_mappingid('user', $row->studentid);
        if (!$submissionid || !$studentid) {
            return;
        }

        $record = clone $row;
        unset($record->id);
        $record->submissionid = $submissionid;
        $record->studentid = $studentid;
        $record->userid = (int) ($this->get_mappingid('user', $row->userid) ?: 0);
        $record->courseid = $courseid;
        $DB->insert_record('local_aigrader_log', $record);
    }
}
