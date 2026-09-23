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
 * AI Grader Pro · review page.
 *
 * The teacher sees the LLM's proposal in editable form. They can:
 *   - Approve & Publish: edits (if any) get saved and the grade is written
 *     to m_assign_grades with grader=USER. This is the physical guarantee
 *     of human-in-the-loop (per ADR-001 section 3.6): nothing reaches the
 *     gradebook without a teacher's click.
 *   - Save without publishing: edits are kept as a draft
 *     (status teacher_reviewed); nothing reaches the gradebook.
 *
 * Grades are stored on the AI's 0-10 scale and shown / entered on the
 * assignment's own scale (points, Moodle scale); see grading_scale.
 *
 * URL: /local/aigrader/review.php?submissionid=N
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');

use local_aigrader\grading_scale;
use local_aigrader\publisher;

$submissionid = required_param('submissionid', PARAM_INT);

$assignsub = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$assign    = $DB->get_record('assign', ['id' => $assignsub->assignment], '*', MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_instance($assign->id, 'assign');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/aigrader:use', $context);
\local_aigrader\availability::require_available_in_course($course);

$scale = grading_scale::for_assign($assign, $context);

$proposalrow = $DB->get_record(
    'local_aigrader_submission',
    ['submissionid' => $submissionid],
    '*',
    MUST_EXIST
);

// Allowed statuses:
// ai_proposed / teacher_reviewed / published — there is a proposal to review.
// unsupported_format / error                — there is NO proposal because the
// AI could not process the submission, but the teacher should still be
// able to grade manually here (same UI, same save_grade path) without
// having to go to Moodle's native grader. The form renders with empty
// defaults; nothing is pre-filled.
$manualfallbackstatuses = ['unsupported_format', 'error'];
$allowedstatuses = array_merge(
    ['ai_proposed', 'teacher_reviewed', 'published'],
    $manualfallbackstatuses
);
if (!in_array($proposalrow->status, $allowedstatuses, true)) {
    throw new \moodle_exception('errornoproposal', 'local_aigrader');
}

$ismanualfallback = in_array($proposalrow->status, $manualfallbackstatuses, true);

if ($ismanualfallback) {
    // No AI proposal exists — render the form with empty defaults so the
    // teacher can fill in a grade and feedback by hand.
    $proposed = [
        'final_grade'      => 0,
        'criterion_scores' => [],
        'strengths'        => [],
        'improvements'     => [],
        'justification'    => '',
    ];
} else {
    $proposed = json_decode((string) $proposalrow->proposed_feedback, true);
    if (!is_array($proposed)) {
        throw new \moodle_exception('errorparseproposal', 'local_aigrader');
    }
}

// If we already published before, prefer the final (edited) feedback for the form defaults.
$current = $proposalrow->final_feedback
    ? (json_decode($proposalrow->final_feedback, true) ?: $proposed)
    : $proposed;

$currentgrade = $proposalrow->final_grade ?? ($proposed['final_grade'] ?? 0);

// ---------------------------------------------------------------------.
// Handle POST.
// ---------------------------------------------------------------------.
// PARAM_ALPHAEXT (not PARAM_ALPHA) — the action values contain
// underscores ('save_draft') and PARAM_ALPHA would silently strip
// them, leaving $action === 'savedraft' which matches no handler. The
// form would re-render with no save, indistinguishable from "the
// teacher pressed nothing".
$action = optional_param('action', '', PARAM_ALPHAEXT);
if ($action && data_submitted()) {
    require_sesskey();

    if ($action === 'approve' && !$scale->can_publish()) {
        throw new \moodle_exception('erroradvancedgrading', 'local_aigrader');
    }

    // The grade arrives on the assignment's own scale (points or scale item)
    // and is stored on the AI's 0-10 scale. Out-of-range values are
    // rejected for drafts too, so a half-typed grade never gets saved.
    if ($scale->get_type() === grading_scale::TYPE_NONE) {
        // "No grade" assignments have no grade field; keep the proposal's
        // value so the audit trail doesn't record a spurious edit.
        $finalgrade = (float) ($current['final_grade'] ?? $proposed['final_grade'] ?? 0);
    } else {
        $enteredgrade = required_param('finalgrade', PARAM_FLOAT);
        if (!$scale->is_valid_input($enteredgrade)) {
            throw new \moodle_exception('errorgradeoutofrange', 'local_aigrader', '', (object) [
                'min'   => format_float($scale->get_input_min(), 2, true, true),
                'max'   => format_float($scale->get_input_max(), 2, true, true),
                'value' => s((string) $enteredgrade),
            ]);
        }
        $finalgrade = $scale->to_normalized($enteredgrade);
    }

    // Keeps criterion_scores etc. from the original proposal.
    $finalfeedback = array_merge(is_array($current) ? $current : [], [
        'final_grade'   => $finalgrade,
        'strengths'     => publisher::split_lines(required_param('finalstrengths', PARAM_RAW_TRIMMED)),
        'improvements'  => publisher::split_lines(required_param('finalimprovements', PARAM_RAW_TRIMMED)),
        'justification' => required_param('finaljustification', PARAM_RAW_TRIMMED),
    ]);

    if ($action === 'save_draft') {
        // "Save without publishing": persist the teacher's edits as a draft
        // (teacher_reviewed) and stop — nothing is pushed to the gradebook.
        // The teacher can come back, edit further, and save again or publish.
        $DB->update_record('local_aigrader_submission', (object) [
            'id'             => $proposalrow->id,
            'status'         => 'teacher_reviewed',
            'final_grade'    => $finalgrade,
            'final_feedback' => json_encode($finalfeedback, JSON_UNESCAPED_UNICODE),
            'final_grader'   => (int) $USER->id,
            'timemodified'   => time(),
        ]);

        // Distinct audit action so the trail shows the human review is still
        // in progress rather than concluded (matters for AI Act record-keeping).
        publisher::log_review('save_draft', $proposalrow, $proposed, $finalfeedback);

        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]),
            get_string('msg_saved_draft', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    if ($action === 'approve') {
        // Writes the gradebook (grader = this teacher, never a system id),
        // marks the row published and logs the review, in one transaction.
        publisher::publish($proposalrow, $proposed, $finalfeedback, $scale, $course, $cm, $context);

        redirect(
            new moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]),
            get_string('msg_published', 'local_aigrader'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// ---------------------------------------------------------------------.
// Render page.
// ---------------------------------------------------------------------.
$PAGE->set_url(new moodle_url('/local/aigrader/review.php', ['submissionid' => $submissionid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('review_pagetitle', 'local_aigrader', format_string($assign->name)));
$PAGE->set_heading($course->fullname);

// Same reasoning as on manage.php: hide the activity header so the AI
// proposal review form is not pushed below the fold by the assignment intro.
$PAGE->activityheader->disable();

$student = $DB->get_record(
    'user',
    ['id' => $proposalrow->studentid],
    \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects . ', id'
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review_heading', 'local_aigrader', [
    'assign'  => format_string($assign->name),
    'student' => fullname($student),
]));

// When the AI could not process the submission (PDF too large, image-only,
// all-unsupported-formats, transient LLM failure, parse error) the row is
// still reviewable — the teacher just fills the form by hand. Surface why
// the AI didn't run so the teacher understands what they're looking at.
if ($ismanualfallback) {
    $reason = $proposalrow->error_message
        ? s(\local_aigrader\error_classifier::summarize_raw($proposalrow->error_message))
        : get_string('manualfallback_default', 'local_aigrader');
    echo $OUTPUT->notification(
        get_string('manualfallback_banner', 'local_aigrader') . ' ' . $reason,
        \core\output\notification::NOTIFY_WARNING
    );
}

// ---------------------------------------------------------------------.
// Student submission (read-only): list of attached files + extracted text
// the AI actually saw. The earlier text_extractor-only path reported
// "Online text submission is empty" whenever the student attached a file
// instead of typing online text, hiding the .ipynb / .docx / .zip that
// was actually graded. Using the dispatcher here shows the teacher the
// same text the LLM consumed, which is required to fairly review the
// proposal — especially now that ipynb_extractor head+tail-truncates
// long training logs.
// ---------------------------------------------------------------------.
echo html_writer::tag('h3', get_string('review_submission_text', 'local_aigrader'));

// File attachments list. Built from the standard mod_assign file area so
// it works regardless of which submission plugin (file / onlinetext /
// both) the assignment is configured with.
$fs    = get_file_storage();
$files = $fs->get_area_files(
    $context->id,
    'assignsubmission_file',
    'submission_files',
    $submissionid,
    'filename',
    false // Skip directory entries.
);

if ($files) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag(
        'h6',
        get_string('review_submission_files', 'local_aigrader'),
        ['class' => 'card-subtitle text-muted mb-2']
    );
    echo html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
    foreach ($files as $file) {
        $downloadurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true // Force download.
        );
        $sizekb = round($file->get_filesize() / 1024, 1);
        echo html_writer::tag(
            'li',
            html_writer::link(
                $downloadurl,
                s($file->get_filename()),
                ['class' => 'me-2']
            )
            . html_writer::span("({$sizekb} KB)", 'text-muted small'),
            ['class' => 'mb-1']
        );
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Plain-text view as the AI saw it. Uses dispatcher::extract (the same
// extractor the grading manager uses to build the LLM prompt), so this
// box reproduces exactly what was sent — including ipynb head+tail
// truncation. Uses native <details>/<summary> for the toggle so it
// works without depending on Bootstrap collapse JS (which is loaded
// selectively by Moodle 4.5 via AMD and is not active on this page).
$extraction = \local_aigrader\extractor\dispatcher::extract($submissionid);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('details');
echo html_writer::tag(
    'summary',
    html_writer::span(
        get_string('review_submission_seen_by_ai', 'local_aigrader'),
        'fw-semibold'
    ),
    ['style' => 'cursor: pointer; user-select: none;']
);
echo html_writer::start_div('mt-2');

// Microcopy explaining what this section is for. Pilot feedback in
// v1.0.12 review noted that opening the section without context felt
// like "wall of text without explanation". The paragraph is plain
// language, no jargon ("head + tail" was rejected as too technical;
// "se conservan sólo el principio y el final" is the safer phrasing).
echo html_writer::tag(
    'p',
    get_string('review_seen_by_ai_help', 'local_aigrader'),
    ['class' => 'small text-muted mb-2']
);

if ($extraction->is_ok()) {
    // Tiny metadata line: size of extracted text. Helps the teacher
    // sanity-check "did the AI receive a meaningful chunk of work?".
    $sizekb = number_format(mb_strlen($extraction->text) / 1024, 1);
    echo html_writer::tag(
        'p',
        get_string('review_seen_by_ai_size', 'local_aigrader', $sizekb),
        ['class' => 'small text-muted mb-2']
    );

    echo html_writer::tag(
        'pre',
        s($extraction->text),
        ['style' => 'white-space: pre-wrap; max-height: 400px; overflow-y: auto; '
                  . 'font-family: monospace; font-size: 0.85rem; '
                  . 'background: #f6f8fa; padding: 0.75rem; border-radius: 4px;']
    );

    // Warnings (skipped files, truncations) used to render as anonymous
    // muted divs after the pre — easy to miss. Group them under a
    // labelled sub-heading so the teacher knows what they mean.
    if (!empty($extraction->warnings)) {
        echo html_writer::tag(
            'p',
            get_string('review_seen_by_ai_warnings', 'local_aigrader'),
            ['class' => 'small fw-semibold text-muted mt-3 mb-1']
        );
        echo html_writer::start_tag('ul', ['class' => 'small text-muted mb-0']);
        foreach ($extraction->warnings as $w) {
            echo html_writer::tag('li', s($w));
        }
        echo html_writer::end_tag('ul');
    }
} else {
    echo html_writer::div(s($extraction->error ?? '(no text)'), 'text-muted');
}
echo html_writer::end_div();
echo html_writer::end_tag('details');
echo html_writer::end_div();
echo html_writer::end_div();

// Criterion scores summary (read-only, info only).
//
// The slugs come from the LLM's structured JSON output. We instruct the
// model to emit machine-readable identifiers (snake_case, ASCII only)
// so the PHP parser doesn't trip on accents or whitespace. That's good
// for the parser but ugly for the teacher, who otherwise sees lines
// like "configuracion_y_justificacion_explicita_de_hiperparametros".
// Convert snake_case -> "First word capitalised, rest lowercase, with
// spaces". Accents are lost in this transform (the underlying slug has
// none), which is an acceptable trade-off — full pretty labels would
// require either a per-criterion dictionary or a separate "label" field
// from the LLM, both of which complicate the prompt for marginal gain.
if (!empty($proposed['criterion_scores']) && is_array($proposed['criterion_scores'])) {
    echo html_writer::tag('h3', get_string('review_criterion_scores', 'local_aigrader'));
    echo html_writer::start_tag('ul');
    foreach ($proposed['criterion_scores'] as $slug => $score) {
        $label = publisher::humanize_criterion_slug((string) $slug);
        echo html_writer::tag(
            'li',
            s($label) . ': <strong>' . format_float((float) $score, 2) . '</strong> / 10'
        );
    }
    echo html_writer::end_tag('ul');
}

// Editable form.
echo html_writer::tag('h3', get_string('review_proposed', 'local_aigrader'));

$scaletype = $scale->get_type();
if ($scaletype === grading_scale::TYPE_ADVANCED) {
    // mod_assign computes the grade from the rubric / marking guide, which
    // this plugin cannot fill in yet. Send the teacher to the native grader.
    $graderurl = new moodle_url('/mod/assign/view.php', [
        'id'     => $cm->id,
        'action' => 'grader',
        'userid' => (int) $proposalrow->studentid,
    ]);
    echo $OUTPUT->notification(
        get_string(
            'review_advanced_grading',
            'local_aigrader',
            get_string('pluginname', 'gradingform_' . $scale->get_advanced_method())
        ) . ' ' . html_writer::link($graderurl, get_string('review_open_grader', 'local_aigrader')),
        \core\output\notification::NOTIFY_WARNING
    );
} else if ($scaletype === grading_scale::TYPE_NONE) {
    echo $OUTPUT->notification(
        get_string('review_grade_none', 'local_aigrader'),
        \core\output\notification::NOTIFY_INFO
    );
}

$formurl = $PAGE->url->out(false);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl, 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Final grade, shown and entered on the assignment's own scale.
if ($scaletype === grading_scale::TYPE_SCALE) {
    echo html_writer::start_div('mb-3');
    echo html_writer::label(get_string('field_finalgrade', 'local_aigrader'), 'finalgrade', false, ['class' => 'form-label']);
    echo html_writer::select(
        $scale->get_scale_items(),
        'finalgrade',
        (int) $scale->to_assign_grade((float) $currentgrade),
        false,
        ['id' => 'finalgrade', 'class' => 'form-select', 'style' => 'max-width: 280px;']
    );
    echo html_writer::end_div();
} else if ($scaletype !== grading_scale::TYPE_NONE) {
    if ($scaletype === grading_scale::TYPE_POINT) {
        $gradelabel = get_string(
            'field_finalgrade_points',
            'local_aigrader',
            format_float($scale->get_grademax(), 2, true, true)
        );
        $gradevalue = (float) $scale->to_assign_grade((float) $currentgrade);
        $gradestep = 0.01;
    } else {
        // Advanced grading: a 0-10 reference value the teacher can keep in the draft.
        $gradelabel = get_string('field_finalgrade_reference', 'local_aigrader');
        $gradevalue = (float) $currentgrade;
        $gradestep = 0.1;
    }
    echo html_writer::start_div('mb-3');
    echo html_writer::label($gradelabel, 'finalgrade', false, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type'  => 'number',
        'name'  => 'finalgrade',
        'id'    => 'finalgrade',
        // Use number_format with explicit "." decimal, NOT format_float:
        // format_float respects the user's locale (comma in es/fr/de/...) but
        // HTML <input type="number"> only accepts ASCII dot. With a comma the
        // browser refuses the value and leaves the field empty, hiding the AI
        // proposal from the teacher.
        'value' => number_format($gradevalue, 2, '.', ''),
        'min'   => number_format($scale->get_input_min(), 2, '.', ''),
        'max'   => number_format($scale->get_input_max(), 2, '.', ''),
        'step'  => $gradestep,
        'class' => 'form-control',
        'style' => 'max-width: 120px;',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
}

// Strengths.
echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('field_strengths', 'local_aigrader'), 'finalstrengths', false, ['class' => 'form-label']);
echo html_writer::tag(
    'textarea',
    s(implode("\n", $current['strengths'] ?? [])),
    ['name' => 'finalstrengths', 'id' => 'finalstrengths', 'rows' => 5, 'class' => 'form-control']
);
echo html_writer::tag('small', get_string('field_strengths_hint', 'local_aigrader'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

// Improvements.
echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('field_improvements', 'local_aigrader'), 'finalimprovements', false, ['class' => 'form-label']);
echo html_writer::tag(
    'textarea',
    s(implode("\n", $current['improvements'] ?? [])),
    ['name' => 'finalimprovements', 'id' => 'finalimprovements', 'rows' => 5, 'class' => 'form-control']
);
echo html_writer::tag('small', get_string('field_improvements_hint', 'local_aigrader'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

// Justification.
echo html_writer::start_div('mb-3');
echo html_writer::label(
    get_string('field_justification', 'local_aigrader'),
    'finaljustification',
    false,
    ['class' => 'form-label']
);
echo html_writer::tag(
    'textarea',
    s($current['justification'] ?? ''),
    ['name' => 'finaljustification', 'id' => 'finaljustification', 'rows' => 3, 'class' => 'form-control']
);
echo html_writer::end_div();

// Action buttons. Using <button> so we can have nice display text while
// posting value="approve"/"reject" for the handler.
echo html_writer::start_div('d-flex gap-2');
if ($scale->can_publish()) {
    echo html_writer::tag(
        'button',
        get_string('btn_approve_publish', 'local_aigrader'),
        ['type' => 'submit', 'name' => 'action', 'value' => 'approve', 'class' => 'btn btn-success']
    );
}
// "Save without publishing": neutral secondary style (not red/danger as
// in v1.0.x — saving a draft is non-destructive) and no JS confirm()
// since pressing the wrong button is recoverable (the teacher can
// re-open Revisar and either save another draft or publish).
echo html_writer::tag(
    'button',
    get_string('btn_save_draft', 'local_aigrader'),
    [
        'type'  => 'submit',
        'name'  => 'action',
        'value' => 'save_draft',
        'class' => 'btn btn-outline-secondary ms-2',
    ]
);
echo html_writer::link(
    new moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]),
    get_string('back', 'core'),
    ['class' => 'btn btn-link ms-2']
);
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Footer note showing meta info (model, when proposed).
//
// We deliberately omit the provider name from the user-facing string. In
// practice every row was logged with llm_provider = 'openai' because the
// plugin uses Moodle 4.5's `aiprovider_openai` to speak to whatever LLM
// endpoint the site has configured — typically Groq via its
// openai-compatible API, sometimes the real OpenAI, sometimes a local
// runtime. Showing "openai" was misleading without adding value; the
// model name itself (e.g. "meta-llama/llama-4-scout-17b-16e-instruct")
// is the actually informative bit. The provider field stays in the
// audit log for traceability and forensic queries — only the UI string
// changed.
$meta = [];
if ($proposalrow->timeprocessed) {
    $meta[] = get_string(
        'review_proposed_at',
        'local_aigrader',
        userdate($proposalrow->timeprocessed, get_string('strftimedatetimeshort'))
    );
}
$lastlog = $DB->get_record_sql(
    'SELECT llm_model FROM {local_aigrader_log} ' .
    'WHERE submissionid = ? AND action = ? ORDER BY id DESC LIMIT 1',
    [$submissionid, 'grade']
);
if ($lastlog && !empty($lastlog->llm_model)) {
    $meta[] = get_string('review_proposed_by', 'local_aigrader', s($lastlog->llm_model));
}
if ($meta) {
    echo html_writer::div(implode(' · ', $meta), 'text-muted small');
}

echo $OUTPUT->footer();
