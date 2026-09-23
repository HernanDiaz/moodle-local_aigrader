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
 * Orchestrates the end-to-end grading pipeline:
 *
 *   build_prompt → call AI Subsystem → parse JSON →
 *   persist proposal to local_aigrader_submission → log to local_aigrader_log
 *
 * Returns a grading_result that the caller (CLI, future adhoc task, future
 * AJAX endpoint) can present to the user.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

use local_aigrader\prompt\builder;
use local_aigrader\prompt\built_prompt;
/**
 * Class manager.
 */
class manager {
    /**
     * Run the end-to-end grading pipeline for a single submission.
     *
     * Steps: preflight extractor → build prompt → call AI Subsystem →
     * parse JSON → persist proposal → append audit log.
     *
     * @param int $submissionid `assign_submission.id` of the submission to grade.
     * @return grading_result Outcome value object (success / error / needs_review).
     */
    public function grade_submission(int $submissionid): grading_result {
        global $DB, $USER;

        $start  = microtime(true);
        $result = new grading_result($submissionid);
        $prompt = null;
        $submrecid = null;

        try {
            // 0. Pre-flight: short-circuit submissions where every uploaded
            // file is in an unsupported format (e.g. only a .pdf). Sending
            // those to the LLM produced false 0/10 grades in the v1.0.1
            // pilot — Suárez's research-grade PDF project was rated 0 even
            // though the actual work was excellent. The dispatcher flags
            // these via is_needs_review(); we route them straight to a
            // manual-review status without burning any tokens.
            $preflight = \local_aigrader\extractor\dispatcher::extract($submissionid);
            if ($preflight->is_needs_review()) {
                $submrecid = self::mark_submission_needs_review($submissionid, (string) $preflight->error);
                $result->submission_record_id = $submrecid;
                $result->duration_ms = (int) round((microtime(true) - $start) * 1000);
                $result->mark_needs_review((string) $preflight->error);
                return $result;
            }

            // 1. Build prompt (throws if config missing or extraction fails).
            $prompt = builder::build_for_submission($submissionid);
            $result->prompt_hash = $prompt->hash();

            // 2. Find/create the local_aigrader_submission row in pending state.
            $submrecid = self::upsert_submission_row($prompt, status: 'pending_ai');
            $result->submission_record_id = $submrecid;

            // 3. Resolve module context for the AI Subsystem call.
            $assignid = (int) $prompt->metadata['assignid'];
            [$course, $cm] = get_course_and_cm_from_instance($assignid, 'assign');
            $context = \context_module::instance($cm->id);

            // 4. Build and dispatch the action via Moodle's AI Subsystem.
            //
            // In Moodle 4.5, generate_text only accepts prompttext (no
            // systeminstruction parameter — added in 4.6+). We concatenate
            // Our system+user messages so the LLM sees the full instructions.
            // The provider's own systeminstruction config should be EMPTY so.
            // It does not interfere with our prompt.
            $combinedprompt = $prompt->system_message . "\n\n--- TASK ---\n\n" . $prompt->user_message;

            $action = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid: (int) $USER->id,
                prompttext: $combinedprompt,
            );

            /** @var \core_ai\manager $aimanager */
            $aimanager = \core\di::get(\core_ai\manager::class);
            // Moodle 4.5: process_action takes only the action (no component arg).
            $response  = $aimanager->process_action($action);

            $result->duration_ms = (int) round((microtime(true) - $start) * 1000);

            // Record which provider actually handled the request, and its
            // model when that can be known, for the audit log.
            $origin = self::resolve_ai_origin((int) $USER->id);
            $result->llm_provider = $origin['provider'] ?? '';
            $result->llm_model = $origin['model'] ?? '';

            // 5. Check AI Subsystem success.
            if (!$response->get_success()) {
                $err = trim(($response->get_errorcode() ?? '') . ': ' . ($response->get_errormessage() ?? ''));
                if ($err === ':') {
                    $err = 'AI Subsystem call failed (no error message provided)';
                }
                self::mark_submission_error($submrecid, $err);
                $result->log_record_id = self::log_action('grade', $prompt, null, $response, $err, $result);
                $result->mark_error($err);
                return $result;
            }

            $data = $response->get_response_data();
            $responsetext = (string) ($data['generatedcontent'] ?? '');
            $result->tokens_input  = (int) ($data['prompttokens'] ?? 0);
            $result->tokens_output = (int) ($data['completiontokens'] ?? 0);

            // 6. Parse the LLM response into a structured proposal.
            $proposal = output_parser::parse($responsetext);

            if (!$proposal->success) {
                $err = 'parse_error: ' . $proposal->error;
                self::mark_submission_error($submrecid, $err);
                $result->log_record_id = self::log_action('grade', $prompt, $proposal, $response, $err, $result);
                $result->proposal = $proposal;
                $result->mark_error($err);
                return $result;
            }

            // 7. Persist the proposal.
            $now = time();
            $DB->update_record('local_aigrader_submission', (object) [
                'id'                => $submrecid,
                'status'            => 'ai_proposed',
                'proposed_grade'    => $proposal->grade,
                'proposed_feedback' => $proposal->as_json(),
                'error_message'     => null,
                'timeprocessed'     => $now,
                'timemodified'      => $now,
            ]);

            // 8. Log the action.
            $result->log_record_id = self::log_action('grade', $prompt, $proposal, $response, null, $result);

            $result->proposal = $proposal;
            $result->mark_success();
            return $result;
        } catch (\Throwable $e) {
            $result->duration_ms = (int) round((microtime(true) - $start) * 1000);
            $msg = 'exception: ' . $e->getMessage();
            if ($submrecid !== null) {
                self::mark_submission_error($submrecid, $msg);
            }
            if ($prompt !== null) {
                $result->log_record_id = self::log_action('grade', $prompt, null, null, $msg, $result);
            }
            $result->mark_error($msg);
            return $result;
        }
    }

    /**
     * Insert or update the local_aigrader_submission row, returning its id.
     *
     * @param built_prompt $prompt The built prompt carrying submission metadata.
     * @param string $status One of the local_aigrader_submission status values.
     * @return int Persisted row id.
     */
    private static function upsert_submission_row(built_prompt $prompt, string $status): int {
        global $DB;

        $submissionid = (int) $prompt->metadata['submissionid'];
        $existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);

        $now = time();
        $rec = (object) [
            'submissionid' => $submissionid,
            'assignid'     => (int) $prompt->metadata['assignid'],
            'courseid'     => (int) $prompt->metadata['courseid'],
            'studentid'    => (int) $prompt->metadata['studentid'],
            'status'       => $status,
            'error_message' => null,
            'timemodified' => $now,
        ];

        if ($existing) {
            $rec->id = $existing->id;
            $DB->update_record('local_aigrader_submission', $rec);
            return (int) $existing->id;
        }
        $rec->timecreated = $now;
        return (int) $DB->insert_record('local_aigrader_submission', $rec);
    }

    /**
     * Move a local_aigrader_submission row to status=error with a reason.
     *
     * @param int $localid Row id from local_aigrader_submission.
     * @param string $message Human-readable error message stored in error_message.
     */
    private static function mark_submission_error(int $localid, string $message): void {
        global $DB;
        $DB->update_record('local_aigrader_submission', (object) [
            'id'            => $localid,
            'status'        => 'error',
            'error_message' => $message,
            'timeprocessed' => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Upsert a local_aigrader_submission row and tag it as needing manual
     * teacher review (status = 'unsupported_format'). Used when the
     * preflight extraction detects an entirely unprocessable submission
     * (e.g. only .pdf attachments) so the teacher can grade it by hand
     * without an AI proposal being generated.
     *
     * Returns the row id so the caller can store it on the grading_result
     * for downstream logging.
     *
     * @param int $submissionid The assign_submission.id
     * @param string $reason Human-readable explanation shown in the table.
     * @return int Local submission row id.
     */
    private static function mark_submission_needs_review(int $submissionid, string $reason): int {
        global $DB;

        $assignsub = $DB->get_record('assign_submission', ['id' => $submissionid]);
        $now       = time();
        $existing  = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);

        $rec = (object) [
            'submissionid'      => $submissionid,
            'assignid'          => (int) ($assignsub->assignment ?? 0),
            'courseid'          => (int) (get_course_and_cm_from_instance($assignsub->assignment, 'assign')[0]->id ?? 0),
            'studentid'         => (int) ($assignsub->userid ?? 0),
            'status'            => 'unsupported_format',
            'error_message'     => $reason,
            // Null out any stale proposal fields from a previous run on the
            // same row — otherwise the table keeps showing the old grade
            // alongside the new orange "unsupported" badge.
            'proposed_grade'    => null,
            'proposed_feedback' => null,
            'timeprocessed'     => $now,
            'timemodified'      => $now,
        ];

        if ($existing) {
            $rec->id = $existing->id;
            $DB->update_record('local_aigrader_submission', $rec);
            return (int) $existing->id;
        }
        $rec->timecreated = $now;
        return (int) $DB->insert_record('local_aigrader_submission', $rec);
    }

    /**
     * Write an entry to local_aigrader_log. Returns its id.
     *
     * @param string $action Audit action: grade | regrade | edit | approve | save_draft | reject.
     * @param built_prompt $prompt The built prompt (used for hash and submission metadata).
     * @param parsed_proposal|null $proposal Parsed result, or null if the call failed before parse.
     * @param object|null $response Raw `\core_ai\aiactions\response` (or null on early failure).
     * @param string|null $error Error message if the action failed, else null.
     * @param grading_result $result Result so far: duration, and provider / model when known.
     * @return int Inserted row id from local_aigrader_log.
     */
    private static function log_action(
        string $action,
        built_prompt $prompt,
        ?parsed_proposal $proposal,
        ?object $response,
        ?string $error,
        grading_result $result
    ): int {
        global $DB, $USER;

        $now = time();

        $data = $response ? $response->get_response_data() : [];

        $rec = (object) [
            'submissionid'      => (int) $prompt->metadata['submissionid'],
            'userid'            => (int) $USER->id,
            'studentid'         => (int) $prompt->metadata['studentid'],
            'courseid'          => (int) $prompt->metadata['courseid'],
            'action'            => $action,
            'llm_provider'      => $result->llm_provider !== '' ? \core_text::substr($result->llm_provider, 0, 64) : null,
            'llm_model'         => $result->llm_model !== '' ? \core_text::substr($result->llm_model, 0, 128) : null,
            'prompt_hash'       => $prompt->hash(),
            'prompt_text'       => $prompt->system_message . "\n\n" . $prompt->user_message,
            'response_json'     => $proposal ? $proposal->as_json() : ($response ? json_encode($data) : null),
            'tokens_input'      => (int) ($data['prompttokens'] ?? 0),
            'tokens_output'     => (int) ($data['completiontokens'] ?? 0),
            'cost_usd'          => 0.0,
            'duration_ms'       => $result->duration_ms,
            'proposed_grade'    => $proposal && $proposal->success ? $proposal->grade : null,
            'final_grade'       => null,
            'teacher_edits'     => null,
            'submission_format' => (string) ($prompt->metadata['submission_format'] ?? ''),
            'timecreated'       => $now,
        ];

        if ($error !== null) {
            // Append error to response_json so it's still queryable.
            $rec->response_json = json_encode([
                'error' => $error,
                'partial' => $rec->response_json,
            ]);
        }

        return (int) $DB->insert_record('local_aigrader_log', $rec);
    }

    /**
     * Find which AI provider processed the user's latest generate_text
     * action, and which model it used when that can be known.
     *
     * Moodle 4.5 records the provider in ai_action_register but not the
     * model, so the model is taken from that provider's configured default.
     * If a Moodle version records the model per action, that value wins.
     * When the model cannot be determined it is left empty rather than
     * guessed: the audit log must never name a model that was not used.
     *
     * @param int $userid User who made the request.
     * @return array Keys 'provider' and 'model', each a string or null.
     */
    private static function resolve_ai_origin(int $userid): array {
        global $DB;

        $records = $DB->get_records(
            'ai_action_register',
            ['actionname' => 'generate_text', 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        $record = reset($records);
        if (!$record) {
            return ['provider' => null, 'model' => null];
        }

        $provider = (string) $record->provider;
        $model = null;
        if (!empty($record->model)) {
            $model = (string) $record->model;
        } else {
            $configured = get_config($provider, 'action_generate_text_model');
            if (is_string($configured) && $configured !== '') {
                $model = $configured;
            }
        }
        return ['provider' => $provider, 'model' => $model];
    }
}
