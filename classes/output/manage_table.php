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
 * \table_sql subclass that renders the AI Grader Pro manage table with
 * pagination + sortable columns, matching the look-and-feel of mod_assign's
 * native grading view.
 *
 * Previously (v1.0.5 - v1.0.8) the manage page loaded every submission in a
 * single un-paginated `html_table`. That works for 12-30 student cohorts
 * (the typical microcredencial size) but degrades badly at 200+ rows and is
 * the wrong pattern for the Moodle Plugin Directory peer review — every
 * other Moodle grading screen uses table_sql.
 *
 * This refactor:
 *   - Inherits Moodle-native pagination, sorting and "items per page"
 *     selector from \flexible_table / \table_sql.
 *   - Keeps the bulk action checkbox column working: each row's checkbox
 *     uses the HTML5 `form="aigrader-bulk-form"` attribute to participate
 *     in the bulk form rendered outside the table.
 *   - Hosts the status-badge and info-icon renderers as static methods so
 *     manage.php does not have to redeclare them as global functions.
 *
 * Selection across pages is intentionally NOT implemented: that matches
 * mod_assign's behaviour (the standard "With selected..." dropdown on
 * Moodle's grading view also only acts on visible rows), and the few
 * teachers who need cohort-wide bulk action can bump the "Mostrar X por
 * página" selector to a high value first.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use html_writer;
use local_aigrader\error_classifier;
use local_aigrader\grading_scale;
use moodle_url;
use stdClass;

/**
 * Manage page table for AI Grader Pro.
 */
class manage_table extends \table_sql {
    /**
     * Course module id; needed for action URLs (Revisar, Calificar con IA).
     *
     * @var int
     */
    private int $cmid;

    /**
     * Grading configuration of the assignment, used to show grades on its own scale.
     *
     * @var grading_scale
     */
    private grading_scale $scale;

    /**
     * Build the manage page table bound to a specific assignment by its cmid.
     *
     * @param int $cmid Course module id of the assignment being managed.
     * @param grading_scale $scale Grading configuration of that assignment.
     */
    public function __construct(int $cmid, grading_scale $scale) {
        parent::__construct('local-aigrader-manage');
        $this->cmid = $cmid;
        $this->scale = $scale;

        $this->define_columns([
            'checkbox', 'student', 'submitted_at', 'status', 'grade', 'action',
        ]);
        $this->define_headers([
            // Header checkbox toggles all on-page checkboxes via inline JS.
            html_writer::empty_tag('input', [
                'type'       => 'checkbox',
                'id'         => 'aigrader-select-all',
                'aria-label' => get_string('bulk_select_all', 'local_aigrader'),
            ]),
            get_string('th_student', 'local_aigrader'),
            get_string('th_submitted', 'local_aigrader'),
            get_string('th_status', 'local_aigrader'),
            get_string('th_grade', 'local_aigrader'),
            get_string('th_action', 'local_aigrader'),
        ]);

        // Default sort: student last name ASC. Sortable columns map to SQL
        // expressions via get_sql_sort() below.
        $this->sortable(true, 'student', SORT_ASC);
        $this->no_sorting('checkbox');
        $this->no_sorting('action');

        $this->collapsible(false);
        $this->is_downloadable(false);
        $this->pageable(true);
        $this->initialbars(false);

        // Small visual polish: stripe rows, hover effect.
        $this->set_attribute('class', 'generaltable aigrader-manage-table');
    }

    /**
     * Translate display column names to SQL ORDER BY expressions.
     *
     * The display columns aren't direct SQL aliases (e.g. 'student' is a
     * computed fullname; 'grade' is `ag.proposed_grade`). Without this
     * override table_sql would emit `ORDER BY student` and fail.
     */
    public function get_sql_sort(): string {
        $sortcols = $this->get_sort_columns();
        if (empty($sortcols)) {
            return 'u.lastname ASC, u.firstname ASC';
        }
        $clauses = [];
        foreach ($sortcols as $colname => $order) {
            $dir = (int) $order === SORT_ASC ? 'ASC' : 'DESC';
            $clause = match ($colname) {
                'student'      => "u.lastname $dir, u.firstname $dir",
                'submitted_at' => "s.timemodified $dir",
                'status'       => "ag.status $dir",
                'grade'        => "ag.proposed_grade $dir",
                default        => '',
            };
            if ($clause !== '') {
                $clauses[] = $clause;
            }
        }
        return implode(', ', $clauses);
    }

    // ---------------------------------------------------------------.
    // Per-column renderers.
    // ---------------------------------------------------------------.

    /**
     * Render the row-selection checkbox bound to the external bulk form.
     *
     * @param stdClass $row Joined assign_submission + local_aigrader_submission row.
     * @return string HTML for the cell.
     */
    public function col_checkbox($row): string {
        $studentname = fullname($row);
        return html_writer::empty_tag('input', [
            'type'       => 'checkbox',
            'name'       => 'ids[]',
            'value'      => $row->submissionid,
            'form'       => 'aigrader-bulk-form',
            'class'      => 'aigrader-row-check',
            'aria-label' => get_string('bulk_select_row', 'local_aigrader', $studentname),
        ]);
    }

    /**
     * Render the student's full name.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_student($row): string {
        return fullname($row);
    }

    /**
     * Render the submission time in the user's locale or a dash if unsubmitted.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_submitted_at($row): string {
        return $row->submitted_at
            ? userdate($row->submitted_at, get_string('strftimedatetimeshort'))
            : '-';
    }

    /**
     * Render the AI grading status badge + error context if any.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_status($row): string {
        return self::render_status($row->ai_status, $row->error_message);
    }

    /**
     * Render the AI's proposed grade on the assignment's own scale.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_grade($row): string {
        return $row->proposed_grade !== null
            ? s($this->scale->format((float) $row->proposed_grade))
            : '-';
    }

    /**
     * Render the action cell — Revisar / Calificar con IA buttons depending on state.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_action($row): string {
        global $PAGE;

        $action = '';

        // Primary CTA: a "Revisar →" link to review.php for any row that
        // has a proposal or a fallback-grade-by-hand reason. Pending and
        // never-graded rows don't show Revisar.
        $reviewablestatuses = ['ai_proposed', 'teacher_reviewed', 'published',
            'unsupported_format', 'error'];
        if (in_array($row->ai_status, $reviewablestatuses, true)) {
            $reviewlabel = $row->ai_status === 'published'
                ? get_string('btn_view_published', 'local_aigrader')
                : get_string('btn_review', 'local_aigrader');
            $action .= html_writer::link(
                new moodle_url('/local/aigrader/review.php', ['submissionid' => $row->submissionid]),
                $reviewlabel,
                ['class' => 'btn btn-success btn-sm me-1']
            );
        }

        // Secondary: "Calificar con IA" submit (or "Procesando..." if in flight).
        if ($row->ai_status === 'pending_ai') {
            $action .= html_writer::span(get_string('btn_pending', 'local_aigrader'), 'text-muted');
            return $action;
        }

        // Per-row form posts to manage.php?cmid=N with action=enqueue. This
        // is the existing single-row enqueue path (not the bulk dispatcher),
        // which is what we want — same code path as v1.0.8.
        $formurl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $this->cmid]);
        $action .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $formurl->out(false),
            'style'  => 'display:inline;',
        ]);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'enqueue']);
        $action .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submissionid', 'value' => $row->submissionid]);

        $isprimaryforrow = ($row->ai_status === null);
        $btnattrs = [
            'type'  => 'submit',
            'value' => get_string('btn_grade_with_ai', 'local_aigrader'),
            'class' => $isprimaryforrow ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm',
        ];
        // Native browser confirm() before re-grading an already-published row.
        if ($row->ai_status === 'published') {
            $btnattrs['onclick'] = 'return confirm('
                . json_encode(get_string('confirm_regrade_published', 'local_aigrader'))
                . ');';
        }
        $action .= html_writer::empty_tag('input', $btnattrs);
        $action .= html_writer::end_tag('form');

        return $action;
    }

    // ---------------------------------------------------------------.
    // Static helpers (also called from the global error_banner path).
    // ---------------------------------------------------------------.

    /**
     * Render the AI grading status as a Bootstrap badge plus an optional
     * info icon carrying the long detail. The text-white classes are spelled
     * out because some themes (notably Moove) don't apply Bootstrap 5's
     * "white text on dark badge" rule automatically.
     *
     * @param string|null $status One of the local_aigrader_submission status values, or null.
     * @param string|null $errormsg Error context when status is `error` or `unsupported_format`.
     * @return string HTML snippet ready to drop into a table cell.
     */
    public static function render_status(?string $status, ?string $errormsg): string {
        if ($status === null) {
            return html_writer::span(
                get_string('status_none', 'local_aigrader'),
                'badge bg-secondary text-white'
            );
        }
        switch ($status) {
            case 'pending_ai':
                // Transient state (a few seconds) while the LLM call is in
                // flight. Keep it subtle so the teacher doesn't read it as
                // "another item to act on" — the auto-refresh polling notice
                // above the table is the right place to surface the activity.
                return html_writer::span(
                    get_string('status_pending', 'local_aigrader'),
                    'badge bg-light text-dark'
                );
            case 'ai_proposed':
                // bg-info (cyan) is "informational: there is a proposal
                // waiting for you to review". Distinct from bg-success which
                // is reserved for 'published' (final, in gradebook). v1.0.14
                // had both ai_proposed and published in green, which the
                // pilot teacher correctly flagged as confusing.
                return html_writer::span(
                    get_string('status_proposed', 'local_aigrader'),
                    'badge bg-info text-white'
                );
            case 'teacher_reviewed':
                return html_writer::span(
                    get_string('status_reviewed', 'local_aigrader'),
                    'badge bg-primary text-white'
                );
            case 'published':
                return html_writer::span(
                    get_string('status_published', 'local_aigrader'),
                    'badge bg-success text-white'
                );
            case 'error':
                $badge = html_writer::span(
                    get_string('status_error', 'local_aigrader'),
                    'badge bg-danger text-white'
                );
                if ($errormsg) {
                    $badge .= self::info_icon(error_classifier::summarize_raw($errormsg));
                }
                return $badge;
            case 'unsupported_format':
                $badge = html_writer::span(
                    get_string('status_unsupported', 'local_aigrader'),
                    'badge bg-warning text-dark'
                );
                if ($errormsg) {
                    $badge .= self::info_icon($errormsg);
                }
                return $badge;
            default:
                return html_writer::span(s($status), 'badge bg-secondary text-white');
        }
    }

    /**
     * Small "info" icon (Unicode ⓘ) whose HTML `title` attribute carries a
     * longer detail string. Used to collapse multi-line skip/error reasons
     * that would otherwise break the grid layout to multiple lines.
     *
     * @param string $detail Free-text detail surfaced on hover.
     * @return string HTML span with the icon.
     */
    public static function info_icon(string $detail): string {
        return ' ' . html_writer::tag('span', 'ⓘ', [
            'class'      => 'aigrader-info-icon ms-1 text-muted',
            'title'      => $detail,
            'tabindex'   => 0,
            'role'       => 'button',
            'aria-label' => $detail,
            'style'      => 'cursor: help; font-size: 1.1em;',
        ]);
    }
}
