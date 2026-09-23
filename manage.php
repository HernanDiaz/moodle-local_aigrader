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
 * AI Grader Pro management page.
 *
 * Lists all submissions for a given assignment (cmid) and lets the teacher
 * trigger AI grading on each one. Shows current AI Grader Pro status per
 * submission and auto-refreshes while any are pending.
 *
 * v1.0.9 switched the table from a single un-paginated `html_table` to
 * `\local_aigrader\output\manage_table` (a `\table_sql` subclass) which
 * gives free pagination, sortable columns and "items per page" — matching
 * the look-and-feel of mod_assign's native grading view. The status
 * counter is still computed over the full cohort (separate cheap GROUP BY
 * query) so the chip totals stay correct regardless of the visible page.
 *
 * URL: /local/aigrader/manage.php?cmid=<course module id>[&filter=...&page=...&perpage=...]
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use local_aigrader\output\manage_table;

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/aigrader:use', $context);
\local_aigrader\availability::require_available_in_course($course);

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
$config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id]);

// -------------------------------------------------------------------.
// Handle POST: enqueue grading for one submission. Same code path as
// v1.0.8 — kept identical because the per-row "Calificar con IA" button
// in manage_table::col_action() posts here.
// -------------------------------------------------------------------.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'enqueue' && data_submitted()) {
    require_sesskey();

    if (!$config || empty($config->enabled)) {
        throw new moodle_exception('errornotenabled', 'local_aigrader');
    }
    if (trim((string) $config->criteria_text) === '') {
        throw new moodle_exception('errornocriteria', 'local_aigrader');
    }

    $submissionid = required_param('submissionid', PARAM_INT);
    $submission   = $DB->get_record(
        'assign_submission',
        ['id' => $submissionid, 'assignment' => $assign->id],
        '*',
        MUST_EXIST
    );

    // Pre-insert pending row for immediate UI feedback.
    $existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);
    $now = time();
    if (!$existing) {
        $DB->insert_record('local_aigrader_submission', (object) [
            'submissionid' => $submissionid,
            'assignid'     => (int) $assign->id,
            'courseid'     => (int) $assign->course,
            'studentid'    => (int) $submission->userid,
            'status'       => 'pending_ai',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    } else {
        $DB->update_record('local_aigrader_submission', (object) [
            'id'            => $existing->id,
            'status'        => 'pending_ai',
            'error_message' => null,
            'timemodified'  => $now,
        ]);
    }

    // Grade synchronously in this request.
    \core\session\manager::write_close();
    @set_time_limit(120);

    $mgr    = new \local_aigrader\manager();
    $result = $mgr->grade_submission((int) $submissionid);

    if ($result->success) {
        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
            get_string('msg_graded_now', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if (!empty($result->needs_review)) {
        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
            get_string('msg_needs_manual_review', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $classified = \local_aigrader\error_classifier::classify((string) $result->error);
    redirect(
        new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]),
        get_string($classified->headline_string_key(), 'local_aigrader'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// -------------------------------------------------------------------.
// Read filter / pagination params.
//
// PARAM_ALPHAEXT (not PARAM_ALPHA) on `filter`: the bucket keys contain
// underscores (e.g. 'ai_proposed') and PARAM_ALPHA would silently strip
// them, making the filter look broken to the teacher.
// -------------------------------------------------------------------.
$filter  = optional_param('filter', '', PARAM_ALPHAEXT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$page    = optional_param('page', 0, PARAM_INT);

$validfilters = ['ai_proposed', 'teacher_reviewed', 'published', 'problems', 'none'];
if ($filter !== '' && !in_array($filter, $validfilters, true)) {
    $filter = '';
}

// Clamp perpage to a safe set; "0" means "all" (used when the teacher
// wants to bulk-act on the whole cohort without paginating).
$allowedperpage = [10, 25, 50, 100, 0];
if (!in_array($perpage, $allowedperpage, true)) {
    $perpage = 25;
}

// -------------------------------------------------------------------.
// Render the page.
// -------------------------------------------------------------------.
$pageurl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);
if ($filter !== '') {
    $pageurl->param('filter', $filter);
}
if ($perpage !== 25) {
    $pageurl->param('perpage', $perpage);
}

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manage_pagetitle', 'local_aigrader', format_string($assign->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs(['hidecompletion' => true]);
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'local_aigrader', format_string($assign->name)));

if (!$config || empty($config->enabled)) {
    echo $OUTPUT->notification(
        get_string('manage_disabled', 'local_aigrader'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    exit;
}

// -------------------------------------------------------------------.
// Status counter (separate query, no pagination/filter applied).
//
// This is the source of truth for the chip totals and for the auto-refresh
// trigger. Runs as a single GROUP BY so it stays cheap (~10ms) even on
// large cohorts.
// -------------------------------------------------------------------.
$rawcounts = $DB->get_records_sql(
    "SELECT COALESCE(ag.status, '__none__') AS aistatus,
            COUNT(*) AS n
       FROM {assign_submission} s
       LEFT JOIN {local_aigrader_submission} ag ON ag.submissionid = s.id
      WHERE s.assignment = :assignid
        AND s.latest = 1
        AND s.status  = :submitted
   GROUP BY ag.status",
    [
        'assignid'  => $assign->id,
        'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
    ]
);

$totalrows = 0;
$counts = ['ai_proposed' => 0, 'teacher_reviewed' => 0, 'published' => 0,
    'problems' => 0, 'none' => 0];
$pendingcount = 0;
foreach ($rawcounts as $rc) {
    $n = (int) $rc->n;
    $totalrows += $n;
    $raw = $rc->aistatus;
    if ($raw === '__none__' || $raw === 'pending_ai') {
        $counts['none'] += $n;
    } else if ($raw === 'error' || $raw === 'unsupported_format') {
        $counts['problems'] += $n;
    } else if (isset($counts[$raw])) {
        $counts[$raw] = $n;
    }
    if ($raw === 'pending_ai') {
        $pendingcount += $n;
    }
}

if ($totalrows === 0) {
    echo $OUTPUT->notification(
        get_string('manage_no_submissions', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

// Auto-refresh while any grading is in flight. With pagination the
// teacher might not see the pending row on the current page, but we
// still want to keep the counter chip live — hence: trigger off the
// global pending count, not just visible rows.
if ($pendingcount > 0) {
    $PAGE->requires->js_init_code("setTimeout(function() { window.location.reload(); }, 4000);");
    echo $OUTPUT->notification(
        get_string('manage_polling', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
}

// -------------------------------------------------------------------.
// Error banner — operates on ALL error rows across the assign, not
// just the page currently visible. Separate query for fidelity at the
// cost of one extra ~ms.
// -------------------------------------------------------------------.
if ($counts['problems'] > 0) {
    $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
    $errorrows = $DB->get_records_sql(
        "SELECT s.id AS submissionid, s.userid AS studentid,
                {$namefields->selects},
                ag.status AS ai_status, ag.error_message
           FROM {assign_submission} s
           JOIN {user} u ON u.id = s.userid
           JOIN {local_aigrader_submission} ag ON ag.submissionid = s.id
          WHERE s.assignment = :assignid AND s.latest = 1
            AND s.status = :submitted
            AND ag.status = :errstatus",
        array_merge([
            'assignid'  => $assign->id,
            'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'errstatus' => 'error',
        ], $namefields->params)
    );
    echo \local_aigrader\output\error_banner::render($errorrows, $cmid);
}

// -------------------------------------------------------------------.
// Counter chips (clickable filter).
// -------------------------------------------------------------------.
// Chip colours map 1:1 to the badge colours in manage_table::render_status
// so the teacher gets the same visual cue in the filter and in the table.
// v1.0.15 split ai_proposed off bg-success (which was shared with
// published) — having "waiting for your review" and "already in the
// gradebook" both in green was confusing. ai_proposed now uses bg-info
// (cyan = "there is information for you to act on").
$chipdefs = [
    'ai_proposed'      => ['label' => 'count_ai_proposed', 'class' => 'bg-info text-white'],
    'teacher_reviewed' => ['label' => 'count_teacher_reviewed', 'class' => 'bg-primary text-white'],
    'published'        => ['label' => 'count_published', 'class' => 'bg-success text-white'],
    'problems'         => ['label' => 'count_problems', 'class' => 'bg-warning text-dark'],
    'none'             => ['label' => 'count_none', 'class' => 'bg-secondary text-white'],
];

echo html_writer::start_div('aigrader-counter mb-4 d-flex flex-wrap align-items-center gap-3 row-gap-2');
echo html_writer::tag(
    'strong',
    get_string('count_total', 'local_aigrader', $totalrows),
    ['class' => 'me-1']
);

foreach ($chipdefs as $key => $def) {
    $count = $counts[$key];
    $isactive = ($filter === $key);
    $ismuted = ($count === 0 && !$isactive);

    $targeturl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);
    if (!$isactive) {
        $targeturl->param('filter', $key);
    }
    if ($perpage !== 25) {
        $targeturl->param('perpage', $perpage);
    }

    $chipclass = 'badge ' . $def['class'];
    if ($isactive) {
        $chipclass .= ' border border-dark';
    }
    if ($ismuted) {
        $chipclass .= ' opacity-50';
    }
    $chipclass .= ' text-decoration-none';

    echo html_writer::link(
        $targeturl,
        s(get_string($def['label'], 'local_aigrader', $count)),
        [
            'class'      => $chipclass,
            'style'      => 'cursor: pointer;',
            'aria-label' => $isactive
                ? get_string('count_clear_filter', 'local_aigrader')
                : get_string('count_filter_to', 'local_aigrader', get_string($def['label'], 'local_aigrader', $count)),
        ]
    );
}

if ($filter !== '') {
    $clearurl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);
    if ($perpage !== 25) {
        $clearurl->param('perpage', $perpage);
    }
    echo html_writer::link(
        $clearurl,
        get_string('count_clear_filter', 'local_aigrader'),
        ['class' => 'ms-2 small']
    );
}
echo html_writer::end_div();

// -------------------------------------------------------------------.
// Bulk actions form.
// -------------------------------------------------------------------.
$bulkactions = [
    ''                                                       => get_string('bulk_action_choose', 'local_aigrader'),
    \local_aigrader\bulk\dispatcher::ACTION_APPROVE_PUBLISH => get_string('bulk_action_approve_publish', 'local_aigrader'),
    \local_aigrader\bulk\dispatcher::ACTION_GRADE_AI       => get_string('bulk_action_grade_ai', 'local_aigrader'),
];

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/aigrader/bulk.php'))->out(false),
    'id'     => 'aigrader-bulk-form',
    'class'  => 'mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);

echo html_writer::start_div('aigrader-bulk-bar d-flex flex-wrap align-items-center gap-3 mb-3');
echo html_writer::tag(
    'label',
    get_string('bulk_label_with_selected', 'local_aigrader'),
    ['for' => 'aigrader-bulk-action', 'class' => 'form-label mb-0']
);

$selecthtml = html_writer::start_tag('select', [
    'name'  => 'action',
    'id'    => 'aigrader-bulk-action',
    'class' => 'form-select',
    'style' => 'max-width: 280px;',
]);
foreach ($bulkactions as $value => $label) {
    $selecthtml .= html_writer::tag('option', s($label), ['value' => $value]);
}
$selecthtml .= html_writer::end_tag('select');
echo $selecthtml;

echo html_writer::tag('button', get_string('bulk_apply', 'local_aigrader'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// -------------------------------------------------------------------.
// Items-per-page selector (mirrors mod_assign grader). Plain GET form;
// submitting it just sets ?perpage=X and the page reloads.
// -------------------------------------------------------------------.
$perpageoptions = [
    10  => '10',
    25  => '25',
    50  => '50',
    100 => '100',
    0   => get_string('count_perpage_all', 'local_aigrader'),
];
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/aigrader/manage.php'))->out(false),
    'class'  => 'aigrader-perpage-form mb-2 d-flex align-items-center gap-2',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
if ($filter !== '') {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'filter', 'value' => $filter]);
}
echo html_writer::tag(
    'label',
    get_string('count_perpage_label', 'local_aigrader'),
    ['for' => 'aigrader-perpage', 'class' => 'form-label mb-0 small text-muted']
);
$ppsel = html_writer::start_tag('select', [
    'name'    => 'perpage',
    'id'      => 'aigrader-perpage',
    'class'   => 'form-select form-select-sm',
    'style'   => 'max-width: 110px;',
    'onchange' => 'this.form.submit();',
]);
foreach ($perpageoptions as $value => $label) {
    $attrs = ['value' => $value];
    if ((int) $value === (int) $perpage) {
        $attrs['selected'] = 'selected';
    }
    $ppsel .= html_writer::tag('option', s($label), $attrs);
}
$ppsel .= html_writer::end_tag('select');
echo $ppsel;
echo html_writer::end_tag('form');

// -------------------------------------------------------------------.
// The table itself.
// -------------------------------------------------------------------.
$table = new manage_table($cmid, \local_aigrader\grading_scale::for_assign($assign, $context));
$table->define_baseurl($pageurl);

// SQL pieces. Note: table_sql appends its own ORDER BY (via the
// get_sql_sort() override on the class) and its own LIMIT/OFFSET.
$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
$fields = "s.id            AS submissionid,
           s.userid        AS studentid,
           s.timemodified  AS submitted_at,
           {$namefields->selects},
           ag.id            AS aigrader_id,
           ag.status        AS ai_status,
           ag.proposed_grade,
           ag.timeprocessed,
           ag.error_message";
$from = "{assign_submission} s
         JOIN {user} u ON u.id = s.userid
         LEFT JOIN {local_aigrader_submission} ag ON ag.submissionid = s.id";
$where = 's.assignment = :assignid AND s.latest = 1 AND s.status = :submitted';
$params = array_merge([
    'assignid'  => $assign->id,
    'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
], $namefields->params);

// Apply filter at the SQL level — far cheaper than fetching everything
// and filtering in PHP, and works correctly with pagination.
switch ($filter) {
    case 'ai_proposed':
        $where .= ' AND ag.status = :fstatus';
        $params['fstatus'] = 'ai_proposed';
        break;
    case 'teacher_reviewed':
        $where .= ' AND ag.status = :fstatus';
        $params['fstatus'] = 'teacher_reviewed';
        break;
    case 'published':
        $where .= ' AND ag.status = :fstatus';
        $params['fstatus'] = 'published';
        break;
    case 'problems':
        $where .= " AND ag.status IN ('error', 'unsupported_format')";
        break;
    case 'none':
        $where .= " AND (ag.status IS NULL OR ag.status = 'pending_ai')";
        break;
}

$table->set_sql($fields, $from, $where, $params);

// perpage=0 means "show all" — pass a sensible upper bound that
// flexible_table accepts (it interprets 0 as "no pagination").
$pagesizeforout = $perpage === 0 ? 10000 : $perpage;
$table->out($pagesizeforout, false);

// Tiny inline JS for the "select all" header checkbox.
$PAGE->requires->js_init_code(<<<'JS'
    document.getElementById('aigrader-select-all')?.addEventListener('change', function(e) {
        document.querySelectorAll('.aigrader-row-check').forEach(function(c) {
            c.checked = e.target.checked;
        });
    });
JS);

// Quick-link footer.
echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('manage_back_to_assignment', 'local_aigrader')
    ),
    'mt-3'
);

echo $OUTPUT->footer();
