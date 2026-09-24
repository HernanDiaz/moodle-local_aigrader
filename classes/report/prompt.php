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

namespace local_aigrader\report;

/**
 * Prompt for the class summary.
 *
 * The AI receives the assignment, the teacher's criteria, the class
 * statistics and, for each graded submission, only its grade, strengths and
 * areas for improvement, numbered and without names. It never sees the
 * submissions themselves.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt {
    /** @var int Longest strength or improvement line sent, in characters. */
    private const MAX_ITEM_CHARS = 300;

    /** @var int Budget for the per-submission section, in characters. */
    private const MAX_FEEDBACK_CHARS = 60000;

    /** Instructions to the AI. */
    private const INSTRUCTIONS = <<<EOT
You are an assistant for a teacher. Below are the grading results of every
graded submission to one assignment: the grade and the strengths and areas
for improvement written for each student (proposed by an AI and, in some
cases, edited by the teacher).

Write an executive summary of how the CLASS did, for the teacher:
- what most of the class did well;
- the most common gaps, from most to least frequent, with how many
  submissions show each one (approximately) and one concrete piece of
  advice to address it;
- the topics worth going over again in class;
- concrete next steps for the teacher.

Rules:
- Base every statement on the data below. Do not invent problems.
- Talk about the class as a whole. Never single out or describe an
  individual student.
- Be specific to this assignment and its criteria; avoid generic advice.
EOT;

    /**
     * Build the prompt.
     *
     * @param \stdClass $assign Row of {assign}.
     * @param string $criteria The teacher's evaluation criteria.
     * @param statistics $stats Class statistics.
     * @param array $entries One per submission: ['grade' => float, 'strengths' => string[], 'improvements' => string[]].
     * @param string $language Language code the summary must be written in.
     * @return string
     */
    public static function build(\stdClass $assign, string $criteria, statistics $stats, array $entries, string $language): string {
        $text = self::INSTRUCTIONS . "\n\n";

        $text .= "=== ASSIGNMENT ===\n" . format_string($assign->name) . "\n";
        $brief = self::plain((string) ($assign->intro ?? ''));
        if ($brief !== '') {
            $text .= $brief . "\n";
        }
        $text .= "\n=== EVALUATION CRITERIA (from the teacher) ===\n" . trim($criteria) . "\n\n";

        $text .= "=== CLASS STATISTICS (grades on a 0-10 scale) ===\n";
        $text .= "Graded submissions: {$stats->count} ({$stats->reviewed} reviewed by the teacher)\n";
        $text .= "Average: {$stats->mean}; median: {$stats->median}; lowest: {$stats->min}; highest: {$stats->max}\n";
        $text .= 'Submissions per grade band 0-2 / 2-4 / 4-6 / 6-8 / 8-10: ' . implode(' / ', $stats->distribution) . "\n";
        if ($stats->criteria) {
            $text .= "Average score per criterion (weakest first):\n";
            foreach ($stats->criteria as $slug => $criterion) {
                $text .= "- {$slug}: {$criterion['average']} ({$criterion['count']} submissions)\n";
            }
        }

        $text .= "\n=== FEEDBACK PER SUBMISSION ===\n";
        $feedback = '';
        $included = 0;
        foreach (array_values($entries) as $i => $entry) {
            $block = '#' . ($i + 1) . ' grade ' . round((float) $entry['grade'], 2) . "/10\n";
            foreach (['strengths' => 'Strengths', 'improvements' => 'To improve'] as $key => $label) {
                $items = array_filter(array_map('trim', (array) ($entry[$key] ?? [])), 'strlen');
                if ($items) {
                    $block .= $label . ":\n";
                    foreach ($items as $item) {
                        $block .= '- ' . \core_text::substr($item, 0, self::MAX_ITEM_CHARS) . "\n";
                    }
                }
            }
            if (strlen($feedback) + strlen($block) > self::MAX_FEEDBACK_CHARS) {
                break;
            }
            $feedback .= $block . "\n";
            $included++;
        }
        $text .= $feedback;
        if ($included < count($entries)) {
            $text .= "(Only the first {$included} of " . count($entries) . " submissions fit; the statistics above cover all.)\n\n";
        }

        $text .= <<<EOT
=== OUTPUT FORMAT ===
Return EXCLUSIVELY a valid JSON object, no markdown, no code fences:
{
  "overview": "<3-5 sentences on how the class did>",
  "common_gaps": [
    {"gap": "<gap>", "submissions": <approximate number of submissions>, "advice": "<how to address it>"}
  ],
  "class_strengths": ["<what most of the class did well>"],
  "topics_to_reinforce": ["<topic>"],
  "next_steps": ["<concrete action for the teacher>"]
}
Write every text value in the language with code "{$language}". At most 5 items per list.
EOT;
        return $text;
    }

    /**
     * Plain text of an HTML intro.
     *
     * @param string $html HTML.
     * @return string
     */
    private static function plain(string $html): string {
        $html = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
