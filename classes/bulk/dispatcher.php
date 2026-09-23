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
 * Bulk action dispatcher for the AI Grader Pro manage screen.
 *
 * Lets the teacher select N rows in the manage table and apply a single
 * action to all of them in one click — the standard Moodle "With selected..."
 * pattern, adapted to the four states (no proposal / ai_proposed / published /
 * unsupported / error) that local_aigrader rows can be in.
 *
 * Two-phase API:
 *   1. classify($action, $row) — pure, no side effects. Returns 'ok' if the
 *      row is eligible for the action, or 'skip:<reason>' if not. Used both
 *      by the confirmation page (to show the teacher "you'll apply X to 22
 *      rows; 3 will be skipped because Y") and by execute() to guard each
 *      row before touching it.
 *   2. execute($action, $rows, $applicable) — runs the action over the
 *      eligible rows. For LLM-heavy actions (grade_ai / regrade_ai) it
 *      either runs synchronously in the request (when count <= SYNC_LIMIT)
 *      or enqueues adhoc tasks (when count > SYNC_LIMIT) so the request
 *      returns quickly and the cron picks them up.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\bulk;

use local_aigrader\grading_scale;
use local_aigrader\manager;
use local_aigrader\publisher;
/**
 * Stateless bulk dispatcher.
 */
class dispatcher {
    /** Approve the AI's proposal as-is and publish it to the gradebook. */
    public const ACTION_APPROVE_PUBLISH = 'approve_publish';

    /**
     * Ask the LLM to grade (or re-grade) the selected submissions.
     *
     * Single action that handles both the "this row has never been graded"
     * and the "this row already has a proposal, do it again" cases. In v1.0.5
     * these were two separate actions (grade_ai / regrade_ai) that confused
     * the teacher when picking from the dropdown without giving any extra
     * power; in v1.0.6 they were merged. The per-row button is always
     * labelled "Calificar con IA" regardless of the current status.
     *
     * Skip rules:
     *   - pending_ai          — already in flight, don't double-queue
     *   - unsupported_format  — file is unprocessable, re-running won't help
     */
    public const ACTION_GRADE_AI = 'grade_ai';

    /** All recognized actions, in display order for the dropdown. */
    public const ALL_ACTIONS = [
        self::ACTION_APPROVE_PUBLISH,
        self::ACTION_GRADE_AI,
    ];

    /** Actions that write to the gradebook and need a confirmation step. */
    public const DESTRUCTIVE_ACTIONS = [
        self::ACTION_APPROVE_PUBLISH,
    ];

    /**
     * Up to this many submissions, run the LLM calls in the current request
     * (write_close + set_time_limit, ~3-5 s per row). Above this threshold
     * we enqueue adhoc tasks so the request returns immediately. The cutoff
     * is conservative — most demos sit at 1-3 rows; a teacher pushing 30 at
     * once should not lock the page tab open for a minute.
     */
    public const SYNC_LIMIT = 5;

    /** Classification result: row will be processed. */
    public const RESULT_OK = 'ok';

    /** Classification result prefix. Full value is e.g. 'skip:already_published'. */
    public const RESULT_SKIP_PREFIX = 'skip:';

    /**
     * Decide whether an action applies to a given submission row, without
     * touching anything. Pure function (modulo $DB reads via the row).
     *
     * Returns either self::RESULT_OK or 'skip:<reason_key>'. Reason keys are
     * stable so the calling UI can map them to localized strings:
     *
     *   - skip:no_proposal       — approve_publish needs a proposal but the row has none
     *   - skip:already_published — approve_publish on a row that's already in gradebook
     *   - skip:in_flight         — task is pending; don't touch
     *   - skip:unsupported       — file format prevents AI grading
     *   - skip:advanced_grading  — approve_publish on an assignment graded with a
     *                              rubric / marking guide (grade it in Moodle's grader)
     *   - skip:unknown_state     — defensive catch-all for an unrecognised status
     *
     * @param string $action One of self::ALL_ACTIONS.
     * @param object $row A row from the manage.php SQL with at least:
     *                    submissionid, ai_status, proposed_grade.
     * @param bool $advancedgrading True when the assignment uses a rubric or
     *                              marking guide, so grades cannot be published from here.
     * @return string self::RESULT_OK or "skip:<reason>".
     */
    public static function classify(string $action, object $row, bool $advancedgrading = false): string {
        $status = $row->ai_status ?? null;

        switch ($action) {
            case self::ACTION_APPROVE_PUBLISH:
                // Needs a usable proposal to publish.
                if ($status === 'ai_proposed' || $status === 'teacher_reviewed') {
                    return $advancedgrading ? self::RESULT_SKIP_PREFIX . 'advanced_grading' : self::RESULT_OK;
                }
                if ($status === 'published') {
                    return self::RESULT_SKIP_PREFIX . 'already_published';
                }
                if ($status === 'pending_ai') {
                    return self::RESULT_SKIP_PREFIX . 'in_flight';
                }
                if ($status === 'unsupported_format') {
                    return self::RESULT_SKIP_PREFIX . 'unsupported';
                }
                if ($status === 'error') {
                    return self::RESULT_SKIP_PREFIX . 'no_proposal';
                }
                return self::RESULT_SKIP_PREFIX . 'no_proposal'; // status null.

            case self::ACTION_GRADE_AI:
                // Unified "Calificar con IA": covers both the never-graded
                // case (status NULL) and the re-grade case (any prior state
                // with a proposal or a failed attempt). Re-grading a
                // published row re-runs the LLM but does NOT touch the
                // gradebook — the new proposal sits in ai_proposed waiting
                // for the teacher to publish (or re-publish) it.
                if (
                    $status === null
                    || in_array($status, ['ai_proposed', 'teacher_reviewed', 'published', 'error'], true)
                ) {
                    return self::RESULT_OK;
                }
                if ($status === 'pending_ai') {
                    return self::RESULT_SKIP_PREFIX . 'in_flight';
                }
                if ($status === 'unsupported_format') {
                    // Re-running won't help — the file format is the blocker. The
                    // teacher needs to upload a parseable version first.
                    return self::RESULT_SKIP_PREFIX . 'unsupported';
                }
                return self::RESULT_SKIP_PREFIX . 'unknown_state';

            default:
                return self::RESULT_SKIP_PREFIX . 'unknown_action';
        }
    }

    /**
     * Run the chosen action against the eligible rows.
     *
     * @param string $action One of self::ALL_ACTIONS.
     * @param array $rows Rows from the manage.php SQL keyed by submissionid.
     * @param array $applicable [submissionid => self::classify() return]. The
     *                          caller has already filtered; we just trust the
     *                          map and re-validate inside the per-row branch.
     * @param int|null $synclimit Override SYNC_LIMIT for tests. Null = default.
     * @return array Result tally with keys: ok (int rows the action ran on),
     *               queued (int rows enqueued as adhoc tasks, >= 0),
     *               skipped (int rows the action did NOT touch),
     *               errors (array<int, string> submissionid => error message),
     *               skip_reasons (array<string, int> reason_key => count for the summary banner).
     */
    public static function execute(string $action, array $rows, array $applicable, ?int $synclimit = null): array {
        global $USER;

        $synclimit = $synclimit ?? self::SYNC_LIMIT;

        $eligible = [];
        $skipreasons = [];
        foreach ($applicable as $sid => $verdict) {
            if ($verdict === self::RESULT_OK) {
                $eligible[$sid] = $rows[$sid] ?? null;
            } else if (str_starts_with($verdict, self::RESULT_SKIP_PREFIX)) {
                $reason = substr($verdict, strlen(self::RESULT_SKIP_PREFIX));
                $skipreasons[$reason] = ($skipreasons[$reason] ?? 0) + 1;
            }
        }
        // Drop any null rows (defensive — caller should have filtered already).
        $eligible = array_filter($eligible);

        $result = [
            'ok'           => 0,
            'queued'       => 0,
            'skipped'      => count($applicable) - count($eligible),
            'errors'       => [],
            'skip_reasons' => $skipreasons,
        ];

        if (empty($eligible)) {
            return $result;
        }

        switch ($action) {
            case self::ACTION_APPROVE_PUBLISH:
                foreach ($eligible as $sid => $row) {
                    try {
                        self::publish_existing_proposal((int) $sid);
                        $result['ok']++;
                    } catch (\Throwable $e) {
                        $result['errors'][(int) $sid] = $e->getMessage();
                    }
                }
                return $result;

            case self::ACTION_GRADE_AI:
                // Below threshold: run inline (best UX for small cohorts).
                // Above threshold: enqueue tasks (avoid 60s+ request hangs).
                if (count($eligible) <= $synclimit) {
                    // Release the session lock so other requests by the same
                    // user are not serialised behind us, and bump the wall-clock
                    // limit so a slow LLM doesn't 30s-out mid-loop.
                    \core\session\manager::write_close();
                    @set_time_limit(60 + count($eligible) * 30);

                    $mgr = new manager();
                    foreach ($eligible as $sid => $row) {
                        $gr = $mgr->grade_submission((int) $sid);
                        if ($gr->success || $gr->needs_review) {
                            $result['ok']++;
                        } else {
                            $result['errors'][(int) $sid] = (string) $gr->error;
                        }
                    }
                } else {
                    foreach ($eligible as $sid => $row) {
                        $task = new \local_aigrader\task\grade_submission();
                        $task->set_custom_data((object) ['submissionid' => (int) $sid]);
                        $task->set_userid((int) $USER->id);
                        \core\task\manager::queue_adhoc_task($task);
                        $result['queued']++;
                    }
                }
                return $result;
        }

        // Unreachable for ALL_ACTIONS; defensive default.
        $result['errors']['_'] = 'Unknown action: ' . $action;
        return $result;
    }

    /**
     * Publish an existing proposal as the official grade. Equivalent to
     * opening review.php and clicking "Approve and publish" without
     * changing any field.
     *
     * For a teacher_reviewed row the teacher's saved draft (final_grade /
     * final_feedback) is what gets published, not the raw AI proposal —
     * publishing the proposal would silently discard the teacher's edits.
     *
     * @param int $submissionid The {assign_submission}.id.
     */
    private static function publish_existing_proposal(int $submissionid): void {
        global $DB;

        $proposalrow = $DB->get_record(
            'local_aigrader_submission',
            ['submissionid' => $submissionid],
            '*',
            MUST_EXIST
        );
        if (!in_array($proposalrow->status, ['ai_proposed', 'teacher_reviewed'], true)) {
            throw new \moodle_exception('errornoproposal', 'local_aigrader');
        }

        $proposed = json_decode((string) $proposalrow->proposed_feedback, true);
        if (!is_array($proposed)) {
            throw new \moodle_exception('errorparseproposal', 'local_aigrader');
        }

        $final = $proposed;
        if ($proposalrow->status === 'teacher_reviewed') {
            $draft = json_decode((string) $proposalrow->final_feedback, true);
            if (is_array($draft)) {
                $final = $draft;
            }
            if ($proposalrow->final_grade !== null) {
                $final['final_grade'] = (float) $proposalrow->final_grade;
            }
        }

        $assign = $DB->get_record('assign', ['id' => $proposalrow->assignid], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($assign->id, 'assign');
        $context = \context_module::instance($cm->id);

        publisher::publish(
            $proposalrow,
            $proposed,
            $final,
            grading_scale::for_assign($assign, $context),
            $course,
            $cm,
            $context
        );
    }
}
