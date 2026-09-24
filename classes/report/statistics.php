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
 * Class statistics of the AI Grader Pro proposals of one assignment.
 *
 * Each graded submission counts once, with the teacher's version when the
 * teacher reviewed or published it and the AI proposal otherwise. Grades
 * are on AI Grader Pro's internal 0-10 scale; pages format them on the
 * assignment's own scale.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics {
    /** Statuses of {local_aigrader_submission} rows that carry a grade and feedback. */
    public const GRADED_STATUSES = ['ai_proposed', 'teacher_reviewed', 'published'];

    /** Statuses where the teacher's version replaces the AI proposal. */
    public const REVIEWED_STATUSES = ['teacher_reviewed', 'published'];

    /** @var int Graded submissions. */
    public int $count = 0;

    /** @var int Of those, reviewed or published by the teacher. */
    public int $reviewed = 0;

    /** @var float|null Average grade (0-10). */
    public ?float $mean = null;

    /** @var float|null Median grade (0-10). */
    public ?float $median = null;

    /** @var float|null Lowest grade (0-10). */
    public ?float $min = null;

    /** @var float|null Highest grade (0-10). */
    public ?float $max = null;

    /** @var int[] Submissions per band: [0,2), [2,4), [4,6), [6,8), [8,10]. */
    public array $distribution = [0, 0, 0, 0, 0];

    /** @var array<string, array{average: float, count: int}> Criteria scored in most submissions, weakest first. */
    public array $criteria = [];

    /**
     * Compute the statistics.
     *
     * @param \stdClass[] $rows Rows of {local_aigrader_submission}; rows without a grade are ignored.
     * @return self
     */
    public static function from_rows(array $rows): self {
        $stats = new self();
        $grades = [];
        $scores = [];
        foreach ($rows as $row) {
            if (!in_array($row->status, self::GRADED_STATUSES, true)) {
                continue;
            }
            $grade = self::grade_of($row);
            if ($grade === null) {
                continue;
            }
            $stats->count++;
            if (in_array($row->status, self::REVIEWED_STATUSES, true)) {
                $stats->reviewed++;
            }
            $grades[] = $grade;
            $stats->distribution[min(4, (int) floor($grade / 2))]++;
            foreach ((array) (self::feedback_of($row)['criterion_scores'] ?? []) as $slug => $score) {
                if (is_numeric($score)) {
                    $scores[(string) $slug][] = (float) $score;
                }
            }
        }
        if (!$grades) {
            return $stats;
        }

        sort($grades);
        $n = count($grades);
        $stats->mean = round(array_sum($grades) / $n, 2);
        $stats->median = round($n % 2 ? $grades[intdiv($n, 2)] : ($grades[$n / 2 - 1] + $grades[$n / 2]) / 2, 2);
        $stats->min = $grades[0];
        $stats->max = $grades[$n - 1];

        // The AI names criteria per submission, so a slug seen in only a
        // few submissions is noise; keep those scored in at least half.
        $threshold = max(2, (int) ceil($n / 2));
        foreach ($scores as $slug => $values) {
            if (count($values) >= min($threshold, $n)) {
                $stats->criteria[$slug] = [
                    'average' => round(array_sum($values) / count($values), 2),
                    'count'   => count($values),
                ];
            }
        }
        uasort($stats->criteria, fn($a, $b) => $a['average'] <=> $b['average']);
        return $stats;
    }

    /**
     * The grade that counts for a row: the teacher's when reviewed, else the AI's.
     *
     * @param \stdClass $row Row of {local_aigrader_submission}.
     * @return float|null Grade on 0-10, or null when there is none.
     */
    public static function grade_of(\stdClass $row): ?float {
        if (in_array($row->status, self::REVIEWED_STATUSES, true) && $row->final_grade !== null) {
            return (float) $row->final_grade;
        }
        return $row->proposed_grade !== null ? (float) $row->proposed_grade : null;
    }

    /**
     * The feedback that counts for a row: the teacher's when reviewed, else the AI's.
     *
     * @param \stdClass $row Row of {local_aigrader_submission}.
     * @return array Decoded feedback (criterion_scores, strengths, improvements, justification).
     */
    public static function feedback_of(\stdClass $row): array {
        $json = in_array($row->status, self::REVIEWED_STATUSES, true) && !empty($row->final_feedback)
            ? $row->final_feedback
            : $row->proposed_feedback;
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Plain array for storing with a report.
     *
     * @return array
     */
    public function to_array(): array {
        return get_object_vars($this);
    }

    /**
     * Rebuild from to_array() output.
     *
     * @param array $data Stored statistics.
     * @return self
     */
    public static function from_array(array $data): self {
        $stats = new self();
        foreach (get_object_vars($stats) as $name => $unused) {
            if (array_key_exists($name, $data)) {
                $stats->$name = $data[$name];
            }
        }
        return $stats;
    }
}
