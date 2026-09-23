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
 * AI Grader Pro · bulk action endpoint.
 *
 * Receives a POST from the manage page with a chosen action and a list of
 * selected submission ids, then either:
 *
 *   - For destructive actions (e.g. approve_publish): renders an intermediate
 *     confirmation page showing how many rows will be affected, how many
 *     will be skipped, and a [Confirm] / [Cancel] pair.
 *   - For non-destructive actions: dispatches immediately to the bulk
 *     dispatcher and redirects to the manage page with a summary toast.
 *
 * URL: /local/aigrader/bulk.php
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use local_aigrader\bulk\dispatcher;

$cmid    = required_param('cmid', PARAM_INT);
$action  = required_param('action', PARAM_ALPHANUMEXT);
$ids     = optional_param_array('ids', [], PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/aigrader:use', $context);
\local_aigrader\availability::require_available_in_course($course);
require_sesskey();

// Validate action.
if (!in_array($action, dispatcher::ALL_ACTIONS, true)) {
    throw new moodle_exception('errorinvalidaction', 'local_aigrader');
}

$manageurl = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cmid]);

if (empty($ids)) {
    redirect(
        $manageurl,
        get_string('bulk_no_selection', 'local_aigrader'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

// Load each selected row WITH its current AI grader status. Strict filter on
// assignment id so a tampered POST cannot trigger work on submissions that
// belong to a different assignment.
[$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'sid');
$params['assignid'] = $assign->id;
$rows = $DB->get_records_sql(
    "SELECT s.id            AS submissionid,
            s.userid        AS studentid,
            ag.id            AS aigrader_id,
            ag.status        AS ai_status,
            ag.proposed_grade
     FROM   {assign_submission} s
     LEFT JOIN {local_aigrader_submission} ag ON ag.submissionid = s.id
     WHERE  s.assignment = :assignid
       AND  s.id $insql",
    $params
);
// Re-key by submissionid for easy lookup downstream.
$rowsbyid = [];
foreach ($rows as $r) {
    $rowsbyid[(int) $r->submissionid] = $r;
}

// Classify each row up front so both the confirmation page and the executor
// see exactly the same eligibility verdicts.
// With a rubric / marking guide active, grades cannot be published from
// here; classify() turns every approve_publish row into a skip.
$advancedgrading = !\local_aigrader\grading_scale::for_assign($assign, $context)->can_publish();

$applicable = [];
foreach ($rowsbyid as $sid => $row) {
    $applicable[$sid] = dispatcher::classify($action, $row, $advancedgrading);
}

$okcount = 0;
$skipreasons = [];
foreach ($applicable as $sid => $verdict) {
    if ($verdict === dispatcher::RESULT_OK) {
        $okcount++;
    } else if (str_starts_with($verdict, dispatcher::RESULT_SKIP_PREFIX)) {
        $reason = substr($verdict, strlen(dispatcher::RESULT_SKIP_PREFIX));
        $skipreasons[$reason] = ($skipreasons[$reason] ?? 0) + 1;
    }
}

// -----------------------------------------------------------------------.
// Confirmation page for destructive actions.
// -----------------------------------------------------------------------.
$isdestructive = in_array($action, dispatcher::DESTRUCTIVE_ACTIONS, true);

if ($isdestructive && !$confirm) {
    $PAGE->set_url(new moodle_url('/local/aigrader/bulk.php'));
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('bulk_confirm_pagetitle', 'local_aigrader'));
    $PAGE->set_heading($course->fullname);

    // Build the list of skip lines for the template, mapped to localized text.
    $skiplines = [];
    foreach ($skipreasons as $key => $count) {
        $skiplines[] = [
            'count'  => $count,
            'reason' => get_string('bulk_skip_' . $key, 'local_aigrader'),
        ];
    }

    $hiddeninputs = [
        ['name' => 'sesskey', 'value' => sesskey()],
        ['name' => 'cmid', 'value' => $cmid],
        ['name' => 'action', 'value' => $action],
        ['name' => 'confirm', 'value' => 1],
    ];
    foreach ($ids as $sid) {
        $hiddeninputs[] = ['name' => 'ids[]', 'value' => (int) $sid];
    }

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_aigrader/bulk_confirm', [
        'action_label'   => get_string('bulk_action_' . $action, 'local_aigrader'),
        'action_warning' => get_string('bulk_warning_' . $action, 'local_aigrader'),
        'ok_count'       => $okcount,
        'skip_lines'     => $skiplines,
        'has_skips'      => !empty($skiplines),
        'submit_url'     => (new moodle_url('/local/aigrader/bulk.php'))->out(false),
        'cancel_url'     => $manageurl->out(false),
        'hidden_inputs'  => $hiddeninputs,
        'confirm_button' => get_string('bulk_confirm_button_' . $action, 'local_aigrader'),
        'cancel_button'  => get_string('cancel', 'core'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

// -----------------------------------------------------------------------.
// Dispatch.
// -----------------------------------------------------------------------.
$summary = dispatcher::execute($action, $rowsbyid, $applicable);

$messageparts = [];
if ($summary['ok'] > 0) {
    $messageparts[] = get_string('bulk_done_ok', 'local_aigrader', $summary['ok']);
}
if ($summary['queued'] > 0) {
    $messageparts[] = get_string('bulk_done_queued', 'local_aigrader', $summary['queued']);
}
if ($summary['skipped'] > 0) {
    $messageparts[] = get_string('bulk_done_skipped', 'local_aigrader', $summary['skipped']);
}
if (!empty($summary['errors'])) {
    $messageparts[] = get_string('bulk_done_errors', 'local_aigrader', count($summary['errors']));
}

$messagetype = !empty($summary['errors'])
    ? \core\output\notification::NOTIFY_WARNING
    : \core\output\notification::NOTIFY_SUCCESS;

redirect(
    $manageurl,
    implode(' · ', $messageparts),
    null,
    $messagetype
);
