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
 * Composes a full grading prompt from the 6 ingredients defined in ADR-001
 * section 3 (prompt anatomy):
 *
 *   1. Fixed plugin system instruction
 *   2. Optional institution-wide system prompt prefix (from plugin settings)
 *   3. Per-assignment evaluation criteria (from local_aigrader_assign)
 *   4. Assignment intro/brief (from m_assign.intro)
 *   5. Extracted student submission text (from extractor)
 *   6. Strict JSON output format instructions
 *
 * Produces a built_prompt that the manager will hand to the AI Subsystem.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\prompt;

use local_aigrader\extractor\dispatcher as extractor_dispatcher;
/**
 * Class builder.
 */
class builder {
    /**
     * Fixed system instruction. The "voice" of AI Grader Pro.
     */
    private const DEFAULT_SYSTEM_INSTRUCTION = <<<EOT
You are an expert academic grading assistant integrated into a Moodle plugin
called AI Grader Pro.

Your role is to propose a grade and structured feedback for a student
submission, strictly following the evaluation criteria provided by the teacher.

Principles you MUST follow:
1. You PROPOSE; the teacher DECIDES. Your output is a draft that the teacher
   will review, edit if needed, and approve before any grade is published.
2. Be rigorous but constructive: cite specific parts of the submission when
   praising or criticising.
3. Apply ONLY the criteria the teacher provided. Do not invent additional
   criteria or judge the submission on aspects the teacher did not ask about.
4. If the submission is empty, off-topic, or clearly fails to meet the brief,
   say so explicitly in the justification and assign a low grade.
5. Return a strict JSON object as specified in the OUTPUT FORMAT section.
   No markdown, no preamble, no code fences.
EOT;

    /**
     * Build the full prompt for a given submission.
     *
     * @param int $submissionid {assign_submission}.id
     * @return built_prompt
     * @throws \moodle_exception If the submission, assignment or per-assignment
     *                           AI Grader config cannot be loaded.
     */
    public static function build_for_submission(int $submissionid): built_prompt {
        global $DB;

        if ($submissionid <= 0) {
            throw new \moodle_exception('invalidparameter', 'debug', '', 'submissionid');
        }

        $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
        $assign     = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
        $config     = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id]);

        if (!$config || !$config->enabled || trim((string) $config->criteria_text) === '') {
            throw new \moodle_exception(
                'errorconfigmissing',
                'local_aigrader',
                '',
                'No active AI Grader Pro config for assignid=' . $assign->id .
                ' (plugin not enabled on this assignment, or criteria empty)'
            );
        }

        // Also stops tasks queued before the administrator restricted the
        // plugin away from this course, so no data is sent to the LLM.
        \local_aigrader\availability::require_available_in_course(get_course((int) $assign->course));

        $language = self::resolve_language($config, $assign);

        $extraction = extractor_dispatcher::extract($submissionid);
        if (!$extraction->is_ok()) {
            throw new \moodle_exception(
                'errorextraction',
                'local_aigrader',
                '',
                $extraction->error
            );
        }

        // -------- SYSTEM MESSAGE (ingredients 1 + 2) --------
        $system = self::DEFAULT_SYSTEM_INSTRUCTION;
        $institutional = trim((string) get_config('local_aigrader', 'default_system_prompt'));
        if ($institutional !== '') {
            $system .= "\n\nAdditional institution-wide instruction:\n" . $institutional;
        }

        // -------- USER MESSAGE (ingredients 3 + 4 + 5 + 6) --------
        $criteria = trim($config->criteria_text);
        $intro    = self::strip_html((string) ($assign->intro ?? ''));
        $student  = $extraction->text;

        $user  = "You will grade the following student submission.\n\n";

        $user .= "=== EVALUATION CRITERIA (from the teacher; respect weights) ===\n";
        $user .= $criteria . "\n\n";

        if ($intro !== '') {
            $user .= "=== ASSIGNMENT BRIEF (instructions shown to the student) ===\n";
            $user .= $intro . "\n\n";
        }

        $user .= "=== STUDENT SUBMISSION (format: " . $extraction->format . ") ===\n";
        if (str_contains($student, \local_aigrader\name_redactor::PLACEHOLDER)) {
            $user .= "(The student's name and identifiers were replaced with "
                . \local_aigrader\name_redactor::PLACEHOLDER
                . " for privacy. This is not a mistake by the student: do not comment on it or penalise it.)\n";
        }
        $user .= $student . "\n\n";

        if (!empty($extraction->warnings)) {
            $user .= "=== EXTRACTION WARNINGS ===\n";
            foreach ($extraction->warnings as $w) {
                $user .= "- " . $w . "\n";
            }
            $user .= "\n";
        }

        $user .= "=== OUTPUT FORMAT ===\n";
        $user .= self::output_format_instructions($language);

        $metadata = [
            'submissionid'      => (int) $submissionid,
            'assignid'          => (int) $assign->id,
            'courseid'          => (int) $assign->course,
            'studentid'         => (int) $submission->userid,
            'language'          => $language,
            'submission_format' => $extraction->format,
            'submission_chars'  => $extraction->chars,
            'extraction_warnings' => $extraction->warnings,
        ];

        return new built_prompt($system, $user, $metadata);
    }

    /**
     * Decide which language the AI feedback should be in.
     *
     * Order of precedence (per ADR-001 section 8.3):
     *   1. config.language_override (per-assignment, set by teacher)
     *   2. course.lang
     *   3. site default lang
     *   4. 'en' fallback
     *
     * @param \stdClass $config Per-assignment config row (local_aigrader_assign).
     * @param \stdClass $assign Assignment row (assign).
     * @return string ISO language code (e.g. 'es', 'en').
     */
    public static function resolve_language(\stdClass $config, \stdClass $assign): string {
        if (!empty($config->language_override)) {
            return $config->language_override;
        }
        global $DB;
        $courselang = $DB->get_field('course', 'lang', ['id' => $assign->course]);
        if (!empty($courselang)) {
            return $courselang;
        }
        $sitelang = get_config('core', 'lang');
        if (!empty($sitelang)) {
            return $sitelang;
        }
        return 'en';
    }

    /**
     * Strip HTML from an assignment intro without losing paragraph breaks.
     *
     * @param string $html Raw HTML from `assign.intro`.
     * @return string Plain text suitable for the prompt body.
     */
    private static function strip_html(string $html): string {
        $html = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * JSON output format that the LLM must return.
     *
     * @param string $language ISO code that the LLM should write the textual fields in.
     * @return string Multi-line instruction block to append to the prompt.
     */
    private static function output_format_instructions(string $language): string {
        return <<<EOT
Return EXCLUSIVELY a valid JSON object with this exact structure. Do not include
any text before or after the JSON, no markdown code fences, no preamble.

{
  "criterion_scores": {
    "<criterion_slug>": <number 0-10>,
    "<criterion_slug>": <number 0-10>
  },
  "final_grade": <number 0-10, weighted average per criteria weights>,
  "strengths": ["<specific point>", "<specific point>", "<specific point>"],
  "improvements": ["<specific point>", "<specific point>", "<specific point>"],
  "justification": "<2-3 sentences in {$language} explaining the final grade>",
  "feedback_language": "{$language}"
}

Rules:
- final_grade is on a 0-10 scale.
- Compute final_grade as the weighted average of criterion_scores using the
  weights mentioned by the teacher in the criteria. If no weights are
  specified, use the simple average.
- criterion_scores keys MUST be short slugs derived from the criteria labels
  (lowercase, underscores, no spaces, no accents). For example "thesis_clarity"
  instead of "Claridad de la tesis".
- strengths and improvements MUST be specific and actionable, written in
  {$language}, referring to concrete parts of the submission when possible.
- justification MUST be in {$language}.
- Do NOT output anything outside the JSON object.
EOT;
    }
}
