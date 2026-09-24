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
 * Class report of an assignment: statistics and an AI-written summary of
 * the class's strengths and gaps.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_aigrader\report\generator;
use local_aigrader\report\statistics;

$cmid = required_param('cmid', PARAM_INT);
$reportid = optional_param('reportid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/aigrader:use', $context);
\local_aigrader\availability::require_available_in_course($course);

$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
$config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id]);
if (!$config || empty($config->enabled)) {
    throw new moodle_exception('errornotenabled', 'local_aigrader');
}

$url = new moodle_url('/local/aigrader/report.php', ['cmid' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('classreport_title', 'local_aigrader', format_string($assign->name)));
$PAGE->set_heading(format_string($course->fullname));
// The report is not the assignment page: skip its description and completion header.
$PAGE->activityheader->disable();

if (optional_param('action', '', PARAM_ALPHA) === 'generate' && data_submitted()) {
    require_sesskey();
    @set_time_limit(180);
    try {
        generator::generate($assign, $context);
        redirect($url, get_string('classreport_msg_generated', 'local_aigrader'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$history = $DB->get_records('local_aigrader_report', ['assignid' => $assign->id], 'timecreated DESC, id DESC');
$report = $reportid ? ($history[$reportid] ?? null) : (reset($history) ?: null);

$page = new \local_aigrader\output\report_page(
    $cm,
    \local_aigrader\grading_scale::for_assign($assign, $context),
    statistics::from_rows(generator::graded_rows((int) $assign->id)),
    $report,
    array_values($history)
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('classreport_title', 'local_aigrader', format_string($assign->name)));
echo html_writer::link(
    new moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]),
    get_string('classreport_backtopanel', 'local_aigrader'),
    ['class' => 'd-inline-block mb-3']
);
echo $OUTPUT->render_from_template('local_aigrader/class_report', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
