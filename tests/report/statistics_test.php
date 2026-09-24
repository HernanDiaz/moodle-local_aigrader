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
 * Tests for the class statistics and for reading the AI's summary.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\report\statistics
 * @covers     \local_aigrader\report\summary
 */
final class statistics_test extends \advanced_testcase {
    /**
     * The teacher's grade and criteria count for reviewed rows; rows without a
     * proposal are ignored; bands, median and weakest criterion come out right.
     */
    public function test_statistics_use_teacher_version_when_reviewed(): void {
        $rows = [
            $this->row('ai_proposed', 9.0, null, ['thesis' => 9, 'evidence' => 8]),
            $this->row('teacher_reviewed', 8.0, 5.0, ['thesis' => 6, 'evidence' => 3]),
            $this->row('published', 3.0, 3.5, ['thesis' => 4, 'evidence' => 2, 'rare' => 1]),
            $this->row('ai_proposed', 10.0, null, ['thesis' => 10]),
            $this->row('error', null, null, []),
            $this->row('pending_ai', null, null, []),
        ];

        $stats = statistics::from_rows($rows);

        $this->assertSame(4, $stats->count);
        $this->assertSame(2, $stats->reviewed);
        $this->assertEqualsWithDelta(6.88, $stats->mean, 0.001);
        $this->assertEqualsWithDelta(7.0, $stats->median, 0.001);
        $this->assertEqualsWithDelta(3.5, $stats->min, 0.001);
        $this->assertEqualsWithDelta(10.0, $stats->max, 0.001);
        $this->assertSame([0, 1, 1, 0, 2], $stats->distribution);
        // The "rare" criterion appears once in four submissions and is left out; weakest first.
        $this->assertSame(['evidence', 'thesis'], array_keys($stats->criteria));
        $this->assertEqualsWithDelta(4.33, $stats->criteria['evidence']['average'], 0.01);
        $this->assertSame(4, $stats->criteria['thesis']['count']);

        $copy = statistics::from_array(json_decode(json_encode($stats->to_array()), true));
        $this->assertEquals($stats->criteria, $copy->criteria);
        $this->assertEqualsWithDelta($stats->mean, $copy->mean, 0.001);
    }

    /**
     * No graded rows: counts are zero and grades are null.
     */
    public function test_empty_statistics(): void {
        $stats = statistics::from_rows([$this->row('error', null, null, [])]);
        $this->assertSame(0, $stats->count);
        $this->assertNull($stats->mean);
        $this->assertSame([], $stats->criteria);
    }

    /**
     * The AI's answer is read even with code fences around it; gaps can be
     * plain strings; an answer without an overview is refused.
     */
    public function test_summary_parse(): void {
        $fence = str_repeat(chr(96), 3);
        $raw = $fence . "json\n" . json_encode([
            'overview'            => 'The class understood the brief.',
            'common_gaps'         => [
                ['gap' => 'Weak evidence', 'submissions' => 7, 'advice' => 'Model a citation.'],
                'Short conclusions',
                ['gap' => ''],
            ],
            'class_strengths'     => ['Clear structure', ''],
            'topics_to_reinforce' => ['Citing sources'],
            'next_steps'          => ['Run a workshop'],
        ]) . "\n" . $fence;

        $summary = summary::parse($raw);

        $this->assertSame('The class understood the brief.', $summary->overview);
        $this->assertCount(2, $summary->gaps);
        $this->assertSame(7, $summary->gaps[0]['submissions']);
        $this->assertNull($summary->gaps[1]['submissions']);
        $this->assertSame(['Clear structure'], $summary->strengths);
        $this->assertEquals($summary, summary::from_json($summary->to_json()));

        $this->expectException(\moodle_exception::class);
        summary::parse('{"common_gaps": []}');
    }

    /**
     * A proposal row as stored by AI Grader Pro.
     *
     * @param string $status Row status.
     * @param float|null $proposed AI grade.
     * @param float|null $final Teacher's grade.
     * @param array $scores Criterion scores (used for both versions).
     * @return \stdClass
     */
    private function row(string $status, ?float $proposed, ?float $final, array $scores): \stdClass {
        $feedback = json_encode(['criterion_scores' => $scores, 'strengths' => [], 'improvements' => []]);
        return (object) [
            'status'            => $status,
            'proposed_grade'    => $proposed,
            'final_grade'       => $final,
            'proposed_feedback' => $proposed === null ? null : $feedback,
            'final_feedback'    => $final === null ? null : $feedback,
        ];
    }
}
