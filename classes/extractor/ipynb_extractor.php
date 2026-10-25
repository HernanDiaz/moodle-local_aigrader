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
 * Extractor for Jupyter notebooks (.ipynb).
 *
 * A notebook is JSON with a list of "cells" of type markdown or code,
 * each with optional outputs. We extract:
 *   - markdown cells → as text
 *   - code cells → as code with kernel language marker
 *   - text outputs (stream / execute_result) → included for context
 *   - error outputs → included (often pedagogically relevant)
 *   - image / display_data outputs → omitted with a placeholder
 *
 * Outputs are aggressively truncated. Real-world ML notebooks routinely
 * print one log line per training batch, producing tens of thousands of
 * tokens that:
 *   (a) blow past LLM provider rate limits (we saw a 14k-token Fashion-
 *       MNIST notebook hit Groq's 12k TPM cap on llama-3.3-70b-versatile);
 *   (b) cost real money on paid providers;
 *   (c) don't help the LLM evaluate the submission — page after page of
 *       "Epoch 17/50 — loss: 0.084" tells it nothing more than "Epoch 1/50"
 *       and "Epoch 50/50" already told it.
 *
 * The strategy is "head + tail with summary": for each cell output we keep
 * the first ~15 lines and the last ~15 lines with a marker stating how
 * much was dropped, so the LLM still sees the early dynamics and the
 * final converged values. A whole-notebook character cap acts as a safety
 * net for pathological cases (thousands of small-output cells).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;
/**
 * Class ipynb_extractor.
 */
class ipynb_extractor {
    /**
     * Maximum lines kept per individual cell output. If an output exceeds
     * this, we keep half from the head and half from the tail with a
     * "[... N lines truncated ...]" marker in between. Picked at 30 so a
     * typical Keras 20-50 epoch verbose=1 log still preserves both the
     * initial high-loss epochs and the converged ones.
     */
    private const MAX_OUTPUT_LINES = 30;

    /**
     * Hard character cap per individual cell output. Defends against the
     * line-count heuristic missing pathological single-line outputs (e.g.
     * a printed numpy array that fits on one line but is 50k chars wide).
     */
    private const MAX_OUTPUT_CHARS = 1500;

    /**
     * Whole-notebook character cap applied AFTER per-output trimming as a
     * last-line safety net. Roughly 10k tokens, leaving headroom for the
     * teacher's criteria text (~2k tokens), the system prompt (~500
     * tokens), and the LLM's response (~1k tokens) within a 30k TPM
     * budget (the Groq free-tier limit on llama-4-scout).
     */
    private const MAX_TOTAL_CHARS = 40000;

    /**
     * Note put before a notebook without saved outputs in the prompt. In
     * English, like the other markers the LLM reads; the teacher gets a
     * localised warning instead.
     */
    public const NO_OUTPUTS_NOTE = '[Note from AI Grader Pro: this notebook has no saved outputs. It was not run '
        . 'before saving, or its outputs were cleared, so the file does not show whether the code runs or what '
        . 'results it gives. Do not assume them: say so when a criterion depends on them.]';

    /**
     * Extract a flat text representation from a .ipynb file.
     *
     * Thin wrapper around {@see extract_text()} that reads the file content.
     * The split lets the unit tests exercise the parsing/truncation logic
     * without needing to materialise a real stored_file fixture.
     *
     * @param \stored_file $file Submitted Jupyter notebook.
     * @return string|null Extracted text, or null if the notebook could not be parsed.
     */
    public static function extract_file(\stored_file $file): ?string {
        $raw = $file->get_content();
        if ($raw === false || $raw === '') {
            return null;
        }
        return self::extract_text($raw);
    }

    /**
     * Extract a flat text representation from a raw .ipynb JSON string.
     *
     * @param string $raw Notebook JSON.
     * @return string|null Plain text, or null if the JSON was invalid or empty.
     */
    public static function extract_text(string $raw): ?string {
        if ($raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['cells']) || !is_array($data['cells'])) {
            return null;
        }

        // Detect kernel language; defaults to python.
        $language = strtolower((string) ($data['metadata']['kernelspec']['language'] ?? 'python'));

        $parts = [];
        foreach ($data['cells'] as $i => $cell) {
            $type   = (string) ($cell['cell_type'] ?? '');
            $source = self::join_source($cell['source'] ?? '');

            if ($source === '' && empty($cell['outputs'])) {
                continue;
            }

            $label = '--- Cell ' . ($i + 1);

            if ($type === 'markdown') {
                $parts[] = $label . ' (markdown) ---';
                $parts[] = $source;
            } else if ($type === 'code') {
                $parts[] = $label . " (code, {$language}) ---";
                if ($source !== '') {
                    $parts[] = $source;
                }
                self::append_outputs($parts, $cell['outputs'] ?? []);
            } else if ($type === 'raw') {
                $parts[] = $label . ' (raw) ---';
                if ($source !== '') {
                    $parts[] = $source;
                }
            }
        }

        $text = trim(implode("\n", $parts));
        $text = self::apply_total_cap($text);
        return $text === '' ? null : $text;
    }

    /**
     * Whether a notebook has code but no saved outputs at all: it was not
     * run before saving, or its outputs were cleared. Students' notebooks
     * often arrive like this, and the LLM then sees only the code and the
     * student's own description of the results.
     *
     * @param string $raw Notebook JSON.
     * @return bool True when at least one code cell has source and no code cell has outputs.
     */
    public static function lacks_outputs(string $raw): bool {
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['cells']) || !is_array($data['cells'])) {
            return false;
        }
        $codecells = 0;
        foreach ($data['cells'] as $cell) {
            if (($cell['cell_type'] ?? '') !== 'code') {
                continue;
            }
            if (!empty($cell['outputs'])) {
                return false;
            }
            if (trim(self::join_source($cell['source'] ?? '')) !== '') {
                $codecells++;
            }
        }
        return $codecells > 0;
    }

    /**
     * In .ipynb the source field is sometimes a single string, sometimes
     * an array of strings (one per line).
     *
     * @param mixed $source
     */
    private static function join_source($source): string {
        if (is_array($source)) {
            return rtrim(implode('', $source));
        }
        return rtrim((string) $source);
    }

    /**
     * Append outputs of a code cell, truncating long ones to head + tail.
     *
     * @param array $parts Output buffer, modified by reference.
     * @param array $outputs Cell outputs array from the .ipynb JSON.
     */
    private static function append_outputs(array &$parts, array $outputs): void {
        foreach ($outputs as $output) {
            $otype = (string) ($output['output_type'] ?? '');
            switch ($otype) {
                case 'stream':
                    $text = self::join_source($output['text'] ?? '');
                    if ($text !== '') {
                        $parts[] = '--- Output (' . ($output['name'] ?? 'stdout') . ') ---';
                        $parts[] = self::truncate_long($text);
                    }
                    break;
                case 'execute_result':
                    $text = self::join_source($output['data']['text/plain'] ?? '');
                    if ($text !== '') {
                        $parts[] = '--- Result ---';
                        $parts[] = self::truncate_long($text);
                    }
                    break;
                case 'error':
                    // Tracebacks are usually short AND pedagogically critical
                    // (the student wants to see *which* line errored). Skip
                    // truncation; if a traceback is somehow gigantic, the
                    // whole-notebook cap below will trim it.
                    $traceback = $output['traceback'] ?? [];
                    if (is_array($traceback) && $traceback) {
                        $parts[] = '--- Error ---';
                        // Strip ANSI escape sequences from traceback.
                        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', implode("\n", $traceback));
                        $parts[] = $clean;
                    }
                    break;
                case 'display_data':
                    if (isset($output['data']['text/plain'])) {
                        $text = self::join_source($output['data']['text/plain']);
                        if ($text !== '') {
                            $parts[] = '--- Display ---';
                            $parts[] = self::truncate_long($text);
                        }
                    } else {
                        $parts[] = '[Image / rich output omitted]';
                    }
                    break;
            }
        }
    }

    /**
     * Trim one cell-output text to MAX_OUTPUT_LINES / MAX_OUTPUT_CHARS,
     * preserving the first half and the last half with a marker.
     *
     * Returns the input unchanged when it is already within both limits.
     *
     * @param string $text Raw output text.
     * @return string Possibly truncated.
     */
    private static function truncate_long(string $text): string {
        $totalchars = strlen($text);
        $lines      = preg_split('/\r?\n/', $text);
        $totallines = count($lines);

        if ($totalchars <= self::MAX_OUTPUT_CHARS && $totallines <= self::MAX_OUTPUT_LINES) {
            return $text;
        }

        // Head + tail strategy when the offender is line count.
        $headlines    = (int) floor(self::MAX_OUTPUT_LINES / 2);
        $taillines    = self::MAX_OUTPUT_LINES - $headlines;
        $head         = array_slice($lines, 0, $headlines);
        $tail         = array_slice($lines, -$taillines);
        $omittedlines = max(0, $totallines - $headlines - $taillines);

        $marker = sprintf(
            '[... %d lines / %s chars truncated ...]',
            $omittedlines,
            number_format($totalchars)
        );

        $truncated = implode("\n", $head) . "\n" . $marker . "\n" . implode("\n", $tail);

        // If individual lines are pathologically long, the head+tail join
        // can still exceed the char cap. Hard-cut on chars in that case.
        $hardcap = self::MAX_OUTPUT_CHARS * 2;
        if (strlen($truncated) > $hardcap) {
            $truncated = substr($truncated, 0, $hardcap) . "\n[... output truncated ...]";
        }

        return $truncated;
    }

    /**
     * Whole-notebook cap, applied after all per-output trimming. Cuts on a
     * newline boundary when possible so we don't leave a half-token in
     * the prompt.
     *
     * @param string $text Full extracted text.
     * @return string Possibly truncated, with an explanatory marker at the end.
     */
    private static function apply_total_cap(string $text): string {
        if (strlen($text) <= self::MAX_TOTAL_CHARS) {
            return $text;
        }

        // Leave room for the marker so the cap is a hard ceiling.
        $marker = "\n\n[... notebook truncated for length: please trim outputs before submitting ...]";
        $room   = self::MAX_TOTAL_CHARS - strlen($marker);

        $cut = substr($text, 0, $room);
        // Prefer cutting at a newline so we don't slice a token in half.
        $lastnewline = strrpos($cut, "\n");
        if ($lastnewline !== false && $lastnewline > $room - 500) {
            $cut = substr($cut, 0, $lastnewline);
        }

        return $cut . $marker;
    }
}
