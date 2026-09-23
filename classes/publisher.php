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
 * Publishing of teacher-approved grades and feedback, shared by the review
 * page (one submission) and the bulk dispatcher (many submissions).
 *
 * Until v1.0.26 these helpers were plain functions at the bottom of
 * review.php and the bulk dispatcher pulled them in with require_once. That
 * re-ran review.php's top-level code, including require(config.php), which
 * reset $CFG mid-request: every bulk "Publish proposed grade" failed with
 * "Failed opening required '/mod/assign/locallib.php'". Living in an
 * autoloaded class, they can now be used from anywhere.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

use html_writer;

/**
 * Writes approved grades to mod_assign and records the review in the audit log.
 */
final class publisher {
    /**
     * Publish a teacher-approved grade and feedback for one submission.
     *
     * Order of operations, inside one DB transaction so the plugin's own row
     * and the gradebook never disagree:
     *   1. Push grade and feedback through \assign::save_grade().
     *   2. Mark the local_aigrader_submission row as published.
     *   3. Append the review action to local_aigrader_log.
     *
     * @param \stdClass $proposalrow Row from local_aigrader_submission.
     * @param array $proposed Original AI proposal (normalised 0-10 grade).
     * @param array $final Values approved by the teacher (normalised 0-10 grade).
     * @param grading_scale $scale Grading configuration of the assignment.
     * @param \stdClass $course Course record.
     * @param \cm_info|\stdClass $cm Course module of the assignment.
     * @param \context_module $context Module context of the assignment.
     * @throws \moodle_exception When the assignment uses a rubric or marking guide.
     */
    public static function publish(
        \stdClass $proposalrow,
        array $proposed,
        array $final,
        grading_scale $scale,
        \stdClass $course,
        $cm,
        \context_module $context
    ): void {
        global $DB, $USER;

        if (!$scale->can_publish()) {
            throw new \moodle_exception('erroradvancedgrading', 'local_aigrader');
        }

        $normalized = round((float) ($final['final_grade'] ?? 0), 4);
        $assigngrade = $scale->to_assign_grade($normalized);
        $final['final_grade'] = $normalized;
        // Recorded for the audit trail: the value actually written to the
        // gradebook, on the assignment's own scale (null = feedback only).
        $final['gradebook_grade'] = $assigngrade;

        $feedbackhtml = self::format_feedback_html(
            (array) ($final['strengths'] ?? []),
            (array) ($final['improvements'] ?? []),
            (string) ($final['justification'] ?? '')
        );

        $transaction = $DB->start_delegated_transaction();

        self::push_to_assign($course, $cm, $context, (int) $proposalrow->studentid, $assigngrade, $feedbackhtml);

        $now = time();
        $DB->update_record('local_aigrader_submission', (object) [
            'id'             => (int) $proposalrow->id,
            'status'         => 'published',
            'final_grade'    => $normalized,
            'final_feedback' => json_encode($final, JSON_UNESCAPED_UNICODE),
            'final_grader'   => (int) $USER->id,
            'timemodified'   => $now,
            'timepublished'  => $now,
        ]);

        self::log_review(self::diff_action($proposed, $final), $proposalrow, $proposed, $final);

        $transaction->allow_commit();
    }

    /**
     * Write grade and feedback through mod_assign's public save_grade() API.
     *
     * Using save_grade() rather than direct DML means the {assign_grades} row
     * gets grader = current user, the submission_graded event fires
     * (completion, notifications and observers react), feedback reaches every
     * enabled feedback plugin, and the gradebook is updated through the
     * standard path.
     *
     * @param \stdClass $course Course record.
     * @param \cm_info|\stdClass $cm Course module of the assignment.
     * @param \context_module $context Module context of the assignment.
     * @param int $studentid Id of the student being graded.
     * @param float|null $assigngrade Grade on the assignment's scale, or null for feedback only.
     * @param string $feedbackhtml HTML feedback shown to the student.
     * @return int Id of the {assign_grades} row, 0 if it cannot be found.
     */
    public static function push_to_assign(
        \stdClass $course,
        $cm,
        \context_module $context,
        int $studentid,
        ?float $assigngrade,
        string $feedbackhtml
    ): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign = new \assign($context, $cm, $course);

        $data = new \stdClass();
        // Attempt number -1 means "current attempt of the student", the same
        // convention mod_assign's own grading UI uses.
        $data->attemptnumber = -1;
        if ($assigngrade !== null) {
            $data->grade = $assigngrade;
        }
        // The plugin does not send its own notifications; don't trigger
        // mod_assign's either.
        $data->sendstudentnotifications = false;

        // Attach feedback when the comments feedback plugin is enabled. If it
        // isn't, save_grade() ignores the field.
        $comments = $assign->get_feedback_plugin_by_type('comments');
        if ($comments && $comments->is_enabled() && $comments->is_visible()) {
            $data->assignfeedbackcomments_editor = [
                'text'   => $feedbackhtml,
                'format' => FORMAT_HTML,
            ];
        }

        $assign->save_grade($studentid, $data);

        $graderow = $DB->get_record(
            'assign_grades',
            ['assignment' => $assign->get_instance()->id, 'userid' => $studentid],
            'id',
            IGNORE_MULTIPLE
        );
        return $graderow ? (int) $graderow->id : 0;
    }

    /**
     * Split a textarea value (one item per line) into a clean array.
     *
     * @param string $text Raw textarea content.
     * @return array Trimmed, non-empty lines as a re-indexed array of strings.
     */
    public static function split_lines(string $text): array {
        $parts = preg_split('/\r?\n/', $text);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, fn($s) => $s !== '');
        return array_values($parts);
    }

    /**
     * Turn a criterion slug from the LLM ("configuracion_y_justificacion")
     * into a readable label ("Configuracion y justificacion").
     *
     * The LLM is instructed to emit ASCII snake_case identifiers so the
     * parser does not trip on accents; this only replaces underscores and
     * capitalises the first letter.
     *
     * @param string $slug Snake_case key from the proposal's criterion_scores.
     * @return string Readable label, or '' for empty input.
     */
    public static function humanize_criterion_slug(string $slug): string {
        $clean = trim($slug);
        if ($clean === '') {
            return '';
        }
        return ucfirst(str_replace('_', ' ', $clean));
    }

    /**
     * Build the HTML feedback shown to the student.
     *
     * Per ADR-001 section 8.2 the student does not see AI branding by
     * default: the teacher takes pedagogical and legal ownership of it.
     *
     * @param array $strengths Bullet points shown under "Strengths".
     * @param array $improvements Bullet points shown under "Improvements".
     * @param string $justification Free-text justification appended at the end.
     * @return string HTML for the comments feedback plugin.
     */
    public static function format_feedback_html(array $strengths, array $improvements, string $justification): string {
        $html = '';
        if ($strengths) {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('feedback_strengths', 'local_aigrader')));
            $html .= html_writer::start_tag('ul');
            foreach ($strengths as $s) {
                $html .= html_writer::tag('li', s($s));
            }
            $html .= html_writer::end_tag('ul');
        }
        if ($improvements) {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('feedback_improvements', 'local_aigrader')));
            $html .= html_writer::start_tag('ul');
            foreach ($improvements as $i) {
                $html .= html_writer::tag('li', s($i));
            }
            $html .= html_writer::end_tag('ul');
        }
        if (trim($justification) !== '') {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('feedback_justification', 'local_aigrader')));
            $html .= html_writer::tag('p', nl2br(s($justification)));
        }
        return $html;
    }

    /**
     * Decide whether the teacher made meaningful changes to the AI proposal,
     * for the audit log (action 'edit' vs 'approve').
     *
     * @param array $proposed Original AI proposal.
     * @param array $final Values the teacher approved.
     * @return string 'edit' if anything material changed, 'approve' otherwise.
     */
    public static function diff_action(array $proposed, array $final): string {
        if (round((float) ($proposed['final_grade'] ?? 0), 2) !== round((float) ($final['final_grade'] ?? 0), 2)) {
            return 'edit';
        }
        foreach (['strengths', 'improvements'] as $k) {
            if (($proposed[$k] ?? []) !== ($final[$k] ?? [])) {
                return 'edit';
            }
        }
        if (trim((string) ($proposed['justification'] ?? '')) !== trim((string) ($final['justification'] ?? ''))) {
            return 'edit';
        }
        return 'approve';
    }

    /**
     * Append a teacher review action to local_aigrader_log.
     *
     * @param string $action Audit action: 'approve', 'edit' or 'save_draft'.
     * @param \stdClass $proposalrow Row from local_aigrader_submission.
     * @param array|null $proposed Original AI proposal (for the edit diff).
     * @param array|null $final Values the teacher published or saved.
     */
    public static function log_review(string $action, \stdClass $proposalrow, ?array $proposed, ?array $final): void {
        global $DB, $USER;

        $DB->insert_record('local_aigrader_log', (object) [
            'submissionid'      => (int) $proposalrow->submissionid,
            'userid'            => (int) $USER->id,
            'studentid'         => (int) $proposalrow->studentid,
            'courseid'          => (int) $proposalrow->courseid,
            'action'            => $action,
            'llm_provider'      => null,
            'llm_model'         => null,
            'prompt_hash'       => null,
            'prompt_text'       => null,
            'response_json'     => $final ? json_encode($final, JSON_UNESCAPED_UNICODE) : null,
            'tokens_input'      => null,
            'tokens_output'     => null,
            'cost_usd'          => null,
            'duration_ms'       => null,
            'proposed_grade'    => $proposed['final_grade'] ?? null,
            'final_grade'       => $final['final_grade'] ?? null,
            'teacher_edits'     => ($action === 'edit' && $proposed && $final)
                ? json_encode([
                    'grade'         => [$proposed['final_grade'] ?? null, $final['final_grade'] ?? null],
                    'strengths'     => [$proposed['strengths'] ?? [], $final['strengths'] ?? []],
                    'improvements'  => [$proposed['improvements'] ?? [], $final['improvements'] ?? []],
                    'justification' => [$proposed['justification'] ?? '', $final['justification'] ?? ''],
                ], JSON_UNESCAPED_UNICODE)
                : null,
            'submission_format' => null,
            'timecreated'       => time(),
        ]);
    }
}
