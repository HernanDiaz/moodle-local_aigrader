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

namespace local_aigrader\output;

use local_aigrader\grading_scale;
use local_aigrader\report\generator;
use local_aigrader\report\statistics;
use local_aigrader\report\summary;

/**
 * Data for the class report page (templates/report.mustache).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_page implements \renderable, \templatable {
    /** @var \cm_info|\stdClass The assignment's course module. */
    private $cm;

    /** @var grading_scale The assignment's grading scale, to format grades. */
    private grading_scale $scale;

    /** @var statistics Current class statistics. */
    private statistics $stats;

    /** @var \stdClass|null Report to show (row of {local_aigrader_report}). */
    private ?\stdClass $report;

    /** @var \stdClass[] All reports of the assignment, newest first. */
    private array $history;

    /**
     * Constructor.
     *
     * @param \cm_info|\stdClass $cm The assignment's course module.
     * @param grading_scale $scale The assignment's grading scale, to format grades.
     * @param statistics $stats Current class statistics.
     * @param \stdClass|null $report Report to show, or null.
     * @param \stdClass[] $history All reports of the assignment, newest first.
     */
    public function __construct($cm, grading_scale $scale, statistics $stats, ?\stdClass $report, array $history) {
        $this->cm = $cm;
        $this->scale = $scale;
        $this->stats = $stats;
        $this->report = $report;
        $this->history = $history;
    }

    /**
     * Context for the template.
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;
        $stats = $this->stats;
        $url = new \moodle_url('/local/aigrader/report.php', ['cmid' => $this->cm->id]);
        $data = [
            'cmid'           => (int) $this->cm->id,
            'count'          => $stats->count,
            'reviewed'       => $stats->reviewed,
            'hasgrades'      => $stats->count > 0,
            'mean'           => $stats->mean !== null ? $this->scale->format($stats->mean) : '',
            'median'         => $stats->median !== null ? $this->scale->format($stats->median) : '',
            'min'            => $stats->min !== null ? $this->scale->format($stats->min) : '',
            'max'            => $stats->max !== null ? $this->scale->format($stats->max) : '',
            'bands'          => $this->bands(),
            'criteria'       => [],
            'notenough'      => $stats->count < generator::MIN_SUBMISSIONS,
            'minsubmissions' => generator::MIN_SUBMISSIONS,
            'canGenerate'    => $stats->count >= generator::MIN_SUBMISSIONS,
            'generateurl'    => $url->out(false),
            'sesskey'        => sesskey(),
            'generatelabel'  => get_string($this->history ? 'classreport_generate_update' : 'classreport_generate', 'local_aigrader'),
            'hasreport'      => $this->report !== null,
            'history'        => [],
        ];
        foreach ($stats->criteria as $slug => $criterion) {
            $data['criteria'][] = [
                'name'    => \local_aigrader\publisher::humanize_criterion_slug((string) $slug),
                'average' => format_float($criterion['average'], 1, true, true),
                'percent' => (int) round($criterion['average'] * 10),
                'count'   => $criterion['count'],
            ];
        }
        $data['hascriteria'] = !empty($data['criteria']);

        if ($this->report) {
            $summary = summary::from_json((string) $this->report->summary);
            $author = $this->report->userid ? \core_user::get_user($this->report->userid) : null;
            $gaps = array_map(fn(array $gap) => [
                'gap'            => $gap['gap'],
                'hassubmissions' => $gap['submissions'] !== null,
                'submissions'    => $gap['submissions'],
                'advice'         => $gap['advice'],
            ], $summary->gaps);
            $data['report'] = [
                'overview'      => $summary->overview,
                'gaps'          => $gaps,
                'hasgaps'       => !empty($gaps),
                'strengths'     => $summary->strengths,
                'hasstrengths'  => !empty($summary->strengths),
                'topics'        => $summary->topics,
                'hastopics'     => !empty($summary->topics),
                'nextsteps'     => $summary->nextsteps,
                'hasnextsteps'  => !empty($summary->nextsteps),
                'generatedinfo' => get_string('classreport_summary_generatedinfo', 'local_aigrader', (object) [
                    'date'        => userdate($this->report->timecreated),
                    'user'        => $author ? fullname($author) : get_string('unknownuser'),
                    'submissions' => (int) $this->report->submissions,
                ]),
            ];
        }

        foreach ($this->history as $row) {
            $data['history'][] = [
                'url'     => (new \moodle_url($url, ['reportid' => $row->id]))->out(false),
                'label'   => userdate($row->timecreated, get_string('strftimedatetime', 'langconfig')),
                'current' => $this->report && (int) $row->id === (int) $this->report->id,
            ];
        }
        $data['hashistory'] = count($data['history']) > 1;
        return $data;
    }

    /**
     * Grade bands with labels on the assignment's scale when it is points.
     *
     * @return array[] ['label' => string, 'count' => int, 'percent' => int]
     */
    private function bands(): array {
        $max = $this->scale->get_type() === grading_scale::TYPE_POINT ? $this->scale->get_grademax() : 10.0;
        $bands = [];
        foreach ($this->stats->distribution as $i => $count) {
            $low = format_float($i * $max / 5, $max % 5 === 0 ? 0 : 1, true, true);
            $high = format_float(($i + 1) * $max / 5, $max % 5 === 0 ? 0 : 1, true, true);
            $bands[] = [
                'label'   => $low . '–' . $high,
                'count'   => $count,
                'percent' => $this->stats->count ? (int) round($count * 100 / $this->stats->count) : 0,
            ];
        }
        return $bands;
    }
}
