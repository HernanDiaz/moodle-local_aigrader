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
 * English language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Capability descriptions (shown in Site administration > Permissions).
$string['aigrader:use'] = 'Use AI-assisted grading on assignments';
$string['aigrader:configure'] = 'Configure AI Grader Pro on an assignment';
$string['aigrader:viewlog'] = 'View AI Grader Pro audit log';

// Admin settings page.
$string['setting_enabled'] = 'Enable plugin';
$string['setting_enabled_desc'] = 'Global on/off switch for AI Grader Pro. When disabled, teachers cannot trigger new AI grading on any assignment. Existing audit logs are preserved.';

$string['setting_rubric_autoimport'] = 'Auto-import from grading rubric';
$string['setting_rubric_autoimport_desc'] = 'When an assignment uses Moodle\'s rubric grading method, automatically pre-populate the AI Grader Pro evaluation criteria with the rubric content. Teachers can still edit the imported criteria before enabling AI grading.';

$string['setting_default_system_prompt'] = 'Default system prompt';
$string['setting_default_system_prompt_desc'] = 'Optional institution-wide instruction prepended to the system prompt of every grading request. Use this to enforce consistent tone or policy across all teachers. Example: "Provide constructive feedback in academic register, maximum 200 words." Leave empty to use only the plugin\'s default system prompt.';

// Assignment edit form (mod_assign).
$string['form_enabled'] = 'Enable AI-assisted grading for this assignment';
$string['form_enabled_help'] = 'When checked, teachers can trigger AI Grader Pro on this assignment\'s submissions. The AI proposes a grade and feedback; the teacher reviews and decides. Nothing is published to the student until the teacher approves.';

$string['form_criteria'] = 'Evaluation criteria';
$string['form_criteria_help'] = 'Plain-language description of how the AI should evaluate submissions to this assignment. Write the same instructions you would give to a teaching assistant. Mention specific criteria, their relative weight, and the tone of feedback you want. Example:

Evaluate this essay (800-1000 words) on digital education using these criteria:
- Thesis clarity (25%): is the position defensible?
- Quality of evidence (30%): are sources academic and well-cited?
- Structure (25%): introduction, development, conclusion
- Language (20%): academic register, spelling

Tone: constructive and specific, in Spanish.';

$string['form_criteria_imported_notice'] = 'Criteria pre-filled from the rubric configured under "Grade > Advanced grading". You may edit them before enabling AI grading.';


$string['form_language_override'] = 'Feedback language (optional)';
$string['form_language_override_help'] = 'If set, AI feedback for this assignment will be in this language instead of the course language. Leave on "Auto" to use the course\'s language.';

$string['form_lang_auto'] = 'Auto (use course language)';

// Form errors.
$string['error_criteria_required'] = 'Evaluation criteria are required when AI-assisted grading is enabled. Describe how the AI should evaluate the submissions.';

// Rubric importer.
$string['rubric_export_header'] = 'Criteria (auto-imported from the assignment\'s advanced grading rubric):';

// (privacy:metadata defined in the "Privacy provider strings" block below.)

// Adhoc tasks (shown in Site administration > Server > Tasks).
$string['task_grade_submission'] = 'AI Grader Pro: grade a submission';
$string['errortaskfailed'] = 'AI Grader Pro grading task failed: {$a}';

// Management page (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro is not enabled on this assignment. Edit the assignment settings to enable it.';
$string['manage_no_submissions']    = 'No submitted assignments yet for this task.';
$string['manage_polling']           = 'A grading task is in progress. This page will refresh automatically.';
$string['manage_back_to_assignment'] = '← Back to the assignment';
$string['msg_enqueued']             = 'AI grading task enqueued. It will run on the next cron tick.';
$string['msg_graded_now']           = 'AI grading completed. Open Revisar → to review the proposal.';
$string['msg_needs_manual_review']  = 'AI could not process this submission automatically. Open Revisar → to grade it manually.';

$string['th_student']   = 'Student';
$string['th_submitted'] = 'Submitted';
$string['th_status']    = 'AI status';
$string['th_grade']     = 'Proposed grade';
$string['th_action']    = 'Action';

$string['btn_grade_with_ai']   = 'Grade with AI';
$string['btn_pending']         = 'Processing...';

$string['status_none']        = 'No AI grading yet';
$string['status_pending']     = 'Pending';
$string['status_proposed']    = 'AI proposed';
$string['status_reviewed']    = 'Teacher reviewed';
$string['status_published']   = 'Published';
$string['status_error']       = 'Error';
$string['status_unsupported'] = 'Unsupported format';

$string['errornotenabled']  = 'AI Grader Pro is not enabled on this assignment.';
$string['errornocriteria']  = 'No evaluation criteria are set for this assignment.';

// Review page (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Review AI proposal · {$a}';
$string['review_heading']         = 'Review AI proposal: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Student submission';
$string['review_proposed']        = 'Proposed grade and feedback (editable)';
$string['review_criterion_scores'] = 'Criterion scores (from AI, for context)';
$string['review_proposed_at']     = 'Proposed at {$a}';
$string['review_proposed_by']     = 'by {$a}';
$string['manualfallback_banner']  = 'AI grading was not available for this submission, so the form below is empty. Fill in the grade and feedback manually; "Approve and publish" will write them to the gradebook the same way as for AI proposals. Reason:';
$string['manualfallback_default'] = 'no AI proposal recorded for this submission.';
$string['review_submission_files']        = 'Attached files';
$string['review_submission_seen_by_ai']   = 'Submission as seen by the AI';
$string['review_seen_by_ai_help']         = 'This is the version of the student\'s file the AI read. If the AI proposal says something odd, check here what text it actually received. Some formats are not processed (very large PDFs, images).';
$string['review_seen_by_ai_size']         = '{$a} KB of text extracted.';
$string['review_seen_by_ai_warnings']     = 'Notes about the extraction:';

$string['field_finalgrade']         = 'Final grade';
$string['field_finalgrade_points']  = 'Final grade (out of {$a})';
$string['field_finalgrade_reference'] = 'Reference grade (0-10, not published)';
$string['review_advanced_grading']  = 'This assignment uses advanced grading ({$a}). AI Grader Pro cannot fill it in yet, so it cannot publish the grade from here. Use the proposal below as a reference and complete the grading in Moodle\'s grader. You can still save your edits here without publishing.';
$string['review_open_grader']       = 'Open Moodle\'s grader for this student';
$string['review_grade_none']        = 'This assignment is set to "No grade": only the feedback will be published to the student.';
$string['field_strengths']          = 'Strengths';
$string['field_strengths_hint']     = 'One per line. These will be shown to the student as positive feedback.';
$string['field_improvements']       = 'Improvements';
$string['field_improvements_hint']  = 'One per line. Constructive suggestions shown to the student.';
$string['field_justification']      = 'Justification (visible to the student)';

$string['btn_review']          = 'Review';
$string['btn_view_published']  = 'View ✓';
$string['btn_approve_publish'] = 'Approve and publish';
$string['btn_save_draft']      = 'Save without publishing';

$string['msg_published']      = 'Grade approved and published to the gradebook.';
$string['msg_saved_draft']    = 'Saved without publishing. The grade is not in the gradebook yet.';

$string['feedback_strengths']    = 'Strengths';
$string['feedback_improvements'] = 'Areas to improve';
$string['feedback_justification'] = 'Summary';

$string['errornoproposal']      = 'No AI proposal is available for this submission.';
$string['errorparseproposal']   = 'The stored AI proposal could not be parsed. Try re-grading.';
$string['errorgradeoutofrange'] = 'The grade must be between {$a->min} and {$a->max} (received: {$a->value}).';
$string['erroradvancedgrading'] = 'This assignment uses advanced grading (rubric or marking guide), so the grade cannot be published from AI Grader Pro. Grade it in Moodle\'s grader.';

// Privacy provider strings (replaces the v0.1.0 placeholder).
$string['privacy:metadata'] = 'AI Grader Pro stores AI-assisted grading proposals, audit logs of every grading action, and per-assignment configuration. Personal data is also sent to an external Large Language Model (LLM) provider via Moodle\'s AI Subsystem.';

// Per-assignment configuration table.
$string['privacy:metadata:assign']               = 'Per-assignment AI Grader Pro configuration (which assignments are enabled, the evaluation criteria, and per-assignment overrides). Stores the id of the teacher who last edited the configuration.';
$string['privacy:metadata:assign:assignid']      = 'Internal id of the assignment.';
$string['privacy:metadata:assign:criteria_text'] = 'Evaluation criteria written by the teacher in natural language.';
$string['privacy:metadata:assign:usermodified']  = 'Id of the teacher who last edited the configuration. Anonymised on user deletion.';
$string['privacy:metadata:assign:timecreated']   = 'Time when the configuration was first saved.';
$string['privacy:metadata:assign:timemodified']  = 'Time when the configuration was last modified.';

// Per-submission AI proposal state.
$string['privacy:metadata:submission']                   = 'Per-submission state of the AI grading process: the proposed grade and feedback, plus the final grade and feedback approved by the teacher.';
$string['privacy:metadata:submission:submissionid']      = 'Id of the assignment submission this proposal refers to.';
$string['privacy:metadata:submission:studentid']         = 'Id of the student whose submission was graded.';
$string['privacy:metadata:submission:status']            = 'Current state in the AI grading state machine (pending_ai / ai_proposed / teacher_reviewed / published / error).';
$string['privacy:metadata:submission:proposed_grade']    = 'Grade proposed by the LLM (0-10).';
$string['privacy:metadata:submission:proposed_feedback'] = 'Full LLM response: criterion scores, strengths, improvements, justification.';
$string['privacy:metadata:submission:final_grade']       = 'Grade approved by the teacher (may differ from proposed if the teacher edited).';
$string['privacy:metadata:submission:final_feedback']    = 'Feedback approved by the teacher and shown to the student.';
$string['privacy:metadata:submission:final_grader']      = 'Id of the teacher who approved the grade. Anonymised on user deletion.';
$string['privacy:metadata:submission:timecreated']       = 'Time when the AI grading was first queued.';
$string['privacy:metadata:submission:timemodified']      = 'Time of the last modification.';
$string['privacy:metadata:submission:timeprocessed']     = 'Time when the LLM call finished.';
$string['privacy:metadata:submission:timepublished']     = 'Time when the teacher approved and the grade was written to the gradebook.';

// Audit log table.
$string['privacy:metadata:log']                = 'Append-only audit log of every AI grading action. Required by the AI Act (Reg. 2024/1689 Annex III) for high-risk AI systems in education.';
$string['privacy:metadata:log:userid']         = 'Id of the teacher who triggered the action. Anonymised on user deletion.';
$string['privacy:metadata:log:studentid']      = 'Id of the student whose submission was processed.';
$string['privacy:metadata:log:action']         = 'Type of action recorded (grade, regrade, edit, approve, reject).';
$string['privacy:metadata:log:llm_provider']   = 'Name of the LLM provider used (e.g. openai, azureai).';
$string['privacy:metadata:log:llm_model']      = 'Identifier of the LLM model used (e.g. llama-3.3-70b-versatile).';
$string['privacy:metadata:log:prompt_text']    = 'Full prompt sent to the LLM (includes the student\'s submission text).';
$string['privacy:metadata:log:response_json']  = 'Raw response from the LLM as JSON (includes proposed grade and feedback).';
$string['privacy:metadata:log:tokens_input']   = 'Number of input tokens consumed by the LLM call.';
$string['privacy:metadata:log:tokens_output']  = 'Number of output tokens consumed by the LLM call.';
$string['privacy:metadata:log:proposed_grade'] = 'Grade proposed by the LLM at the time of the action.';
$string['privacy:metadata:log:final_grade']    = 'Final grade after teacher review (if applicable).';
$string['privacy:metadata:log:teacher_edits']  = 'JSON diff showing how the teacher modified the AI proposal.';
$string['privacy:metadata:log:timecreated']    = 'Time when the action was recorded.';

// External LLM provider (data transferred outside Moodle).
$string['privacy:metadata:ai_subsystem']             = 'AI Grader Pro sends the student\'s submission text together with the teacher\'s evaluation criteria to the LLM provider configured in Moodle\'s AI Subsystem. The provider may be hosted in or outside the EU depending on the institution\'s choice. The site administrator signs a Data Processing Agreement (DPA) with the chosen provider.';
$string['privacy:metadata:ai_subsystem:prompt_text'] = 'The student\'s submission text plus the teacher\'s criteria and grading instructions.';
$string['privacy:metadata:ai_subsystem:userid']      = 'A user identifier passed to the LLM provider for rate-limiting and abuse-prevention (the provider\'s privacy policy applies).';

// Classified error banner (shown only to teachers — never to students).
$string['err_banner_title']         = 'AI grading failed';
$string['err_banner_title_plural']  = 'AI grading failed on {$a} submissions';
$string['err_banner_affecting']     = 'Affecting: {$a}';
$string['err_banner_show_details']  = 'Show raw error';
$string['err_banner_retry']         = 'Retry now';

// Payload too large.
$string['err_payload_too_large_headline'] = 'Submission exceeds the model\'s size limit';
$string['err_payload_too_large_body']     = 'The submission was {$a->requested} tokens but the configured model "{$a->model}" only accepts {$a->limit} tokens per minute on the current plan.';
$string['err_payload_too_large_body_partial'] = 'The submission exceeded the configured model\'s tokens-per-minute limit.';
$string['err_payload_too_large_action']   = 'Switch to a model with a higher TPM limit in Site administration → AI → Providers, or ask the student to remove notebook outputs before submitting again.';

// Unauthorized.
$string['err_unauthorized_headline'] = 'Provider rejected the API key';
$string['err_unauthorized_body']     = 'The LLM provider returned an authentication error. The API key is missing, invalid, or has been revoked.';
$string['err_unauthorized_action']   = 'Go to Site administration → AI → Providers and check the API key for the active provider.';

// Rate limited.
$string['err_rate_limited_headline'] = 'Provider rate limit hit';
$string['err_rate_limited_body']     = 'Too many grading requests have been sent in a short period. Moodle will retry automatically with exponential backoff.';
$string['err_rate_limited_action']   = 'No action needed. The grading will resume once the rate limit window resets.';

// 5xx provider error.
$string['err_provider_error_headline'] = 'Provider service error';
$string['err_provider_error_body']     = 'The LLM provider returned a temporary server error. Moodle will retry automatically.';
$string['err_provider_error_action']   = 'No action needed. If the problem persists for more than 15 minutes, check the provider\'s status page.';

// Network error.
$string['err_network_error_headline'] = 'Could not reach the LLM provider';
$string['err_network_error_body']     = 'The connection to the LLM provider failed (timeout, DNS error, or connection refused).';
$string['err_network_error_action']   = 'Check the site\'s network connectivity and the provider endpoint URL. Moodle will retry automatically.';

// Parse error.
$string['err_parse_error_headline'] = 'LLM returned a malformed response';
$string['err_parse_error_body']     = 'The model produced output that could not be parsed into the expected JSON grading format.';
$string['err_parse_error_action']   = 'Use "Retry now" to call the model again. If the problem persists, the criteria text may be encouraging free-form prose — review the evaluation criteria.';

// Unknown / catch-all.
$string['err_unknown_headline'] = 'AI grading failed';
$string['err_unknown_body']     = 'The provider returned an error: {$a}';
$string['err_unknown_action']   = 'See the audit log for full details, then try again.';

// -----------------------------------------------------------------------.
// Bulk actions (manage.php "With selected..." selector + bulk.php).
// -----------------------------------------------------------------------.

// Action bar.
$string['bulk_label_with_selected'] = 'With selected:';
$string['bulk_apply']               = 'Apply';
$string['bulk_select_all']          = 'Select all rows';
$string['bulk_select_row']          = 'Select submission by {$a}';

// Dropdown options.
$string['bulk_action_choose']          = '-- Choose an action --';
$string['bulk_action_approve_publish'] = 'Publish proposed grade';
$string['bulk_action_grade_ai']        = 'Grade with AI';

// Confirmation warnings.
$string['bulk_warning_approve_publish'] = 'You are about to publish the AI proposed grades as-is, without editing. Grades will be written to the gradebook and students will be notified according to the assignment settings. This action cannot be undone in bulk.';
$string['bulk_warning_grade_ai']        = 'You are about to run the AI on the selected submissions. Any existing proposals will be overwritten. Each submission consumes tokens from the configured provider.';

// Confirm buttons.
$string['bulk_confirm_button_approve_publish'] = 'Yes, publish';
$string['bulk_confirm_button_grade_ai']        = 'Yes, grade';

// Confirmation page.
$string['bulk_confirm_pagetitle']       = 'AI Grader Pro · Confirm action';
$string['bulk_confirm_count']           = 'submissions will be processed.';
$string['bulk_confirm_skipped_header']  = 'Will be skipped:';

// Errors / validation.
$string['bulk_no_selection']            = 'No submissions selected.';
$string['errorinvalidaction']           = 'Invalid bulk action: {$a}';

// Post-execution summary (redirect toast).
$string['bulk_done_ok']                 = '{$a} submissions processed';
$string['bulk_done_queued']             = '{$a} submissions queued (cron will finish them)';
$string['bulk_done_skipped']            = '{$a} skipped';
$string['bulk_done_errors']             = '{$a} with errors';

// Skip reasons mapped to skip:<reason>.
$string['bulk_skip_already_published'] = 'Already published';
$string['bulk_skip_in_flight']         = 'AI grading is in progress';
$string['bulk_skip_unsupported']       = 'Unsupported file format (upload a valid file first)';
$string['bulk_skip_no_proposal']       = 'No AI proposal (use Grade with AI first)';
$string['bulk_skip_unknown_state']     = 'Unknown row state';
$string['bulk_skip_unknown_action']    = 'Unknown action';
$string['bulk_skip_advanced_grading']  = 'The assignment uses a rubric or marking guide (grade it in Moodle\'s grader)';

// -----------------------------------------------------------------------.
// Status counter + filter chips (manage page banner).
// -----------------------------------------------------------------------.

$string['count_total']             = '{$a} submissions';
$string['count_ai_proposed']       = '{$a} with AI proposal';
$string['count_teacher_reviewed']  = '{$a} reviewed';
$string['count_published']         = '{$a} published';
$string['count_problems']          = '{$a} with problems';
$string['count_none']              = '{$a} not yet graded';
$string['count_filter_to']         = 'Filter: {$a}';
$string['count_clear_filter']      = 'Show all';
$string['count_no_rows_match_filter'] = 'No submissions in this state. Clear the filter to see the rest.';
$string['count_perpage_label']        = 'Show per page:';
$string['count_perpage_all']          = 'All';

// -----------------------------------------------------------------------.
// Extraction (dispatcher.php) — reasons a file or submission was skipped.
// -----------------------------------------------------------------------.

$string['extract_skip_marker']            = 'unsupported';
$string['extract_needs_review_preamble']  = 'All submitted files are unparseable. Supported formats: .txt, .md, .docx, .ipynb, .pdf (≤5 MB, text-based), .zip and code files.';
$string['extract_skipped_list']           = 'Skipped: {$a}.';

$string['extract_reason_docx_malformed']     = 'docx (could not extract — file may be malformed)';
$string['extract_reason_ipynb_parse']        = 'ipynb (could not parse JSON)';
$string['extract_reason_pdf_too_large']      = 'pdf too large ({$a->actual} MB; max {$a->max} MB — see plugin README)';
$string['extract_reason_pdf_no_text']        = 'pdf has no extractable text (image-only scan or corrupt content)';
$string['extract_reason_zip_empty']          = 'zip (empty or only contained skipped files)';
$string['extract_reason_no_extension']       = 'no extension';
$string['extract_reason_unknown_extension']  = 'unsupported extension: {$a}';
$string['extract_truncation_warning']        = '{$a->filename} truncated to {$a->chars} characters';

// Inline confirmation when re-grading an already-published row.
$string['confirm_regrade_published'] = 'This submission is already published. Re-grade with AI? The current gradebook value will stay untouched, but the status will revert to "AI proposal" until you approve again.';
