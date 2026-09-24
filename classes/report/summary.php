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
 * The AI-written class summary of an assignment.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary {
    /** @var string A few sentences on how the class did. */
    public string $overview = '';

    /** @var array<int, array{gap: string, submissions: int|null, advice: string}> Most common gaps, most frequent first. */
    public array $gaps = [];

    /** @var string[] What most of the class did well. */
    public array $strengths = [];

    /** @var string[] Topics to go over again in class. */
    public array $topics = [];

    /** @var string[] Concrete next steps for the teacher. */
    public array $nextsteps = [];

    /**
     * Parse the AI's answer. Tolerates code fences and text around the JSON.
     *
     * @param string $raw Text returned by the AI.
     * @return self
     * @throws \moodle_exception errorsummaryformat if no usable summary is found.
     */
    public static function parse(string $raw): self {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $data = ($start !== false && $end !== false && $end > $start)
            ? json_decode(substr($raw, $start, $end - $start + 1), true)
            : null;
        if (!is_array($data) || trim((string) ($data['overview'] ?? '')) === '') {
            throw new \moodle_exception('classreport_errorsummaryformat', 'local_aigrader');
        }

        $summary = new self();
        $summary->overview = trim((string) $data['overview']);
        foreach ((array) ($data['common_gaps'] ?? []) as $gap) {
            if (is_string($gap)) {
                $gap = ['gap' => $gap];
            }
            if (!is_array($gap) || trim((string) ($gap['gap'] ?? '')) === '') {
                continue;
            }
            $summary->gaps[] = [
                'gap'         => trim((string) $gap['gap']),
                'submissions' => is_numeric($gap['submissions'] ?? null) ? (int) $gap['submissions'] : null,
                'advice'      => trim((string) ($gap['advice'] ?? '')),
            ];
        }
        $summary->strengths = self::strings($data['class_strengths'] ?? []);
        $summary->topics = self::strings($data['topics_to_reinforce'] ?? []);
        $summary->nextsteps = self::strings($data['next_steps'] ?? []);
        return $summary;
    }

    /**
     * JSON for storing with a report.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode(get_object_vars($this), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rebuild from to_json() output.
     *
     * @param string $json Stored summary.
     * @return self
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, true);
        $summary = new self();
        if (is_array($data)) {
            $summary->overview = (string) ($data['overview'] ?? '');
            $summary->gaps = (array) ($data['gaps'] ?? []);
            $summary->strengths = self::strings($data['strengths'] ?? []);
            $summary->topics = self::strings($data['topics'] ?? []);
            $summary->nextsteps = self::strings($data['nextsteps'] ?? []);
        }
        return $summary;
    }

    /**
     * Non-empty trimmed strings of a list.
     *
     * @param mixed $list Decoded JSON value.
     * @return string[]
     */
    private static function strings($list): array {
        $out = [];
        foreach ((array) $list as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }
        return $out;
    }
}
