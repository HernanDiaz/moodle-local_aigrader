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

use local_aigrader\output\report_page;

/**
 * Rendering of the class report page, with and without stored reports.
 *
 * Renders the real template, so a missing language string (which calls
 * debugging() and fails the test) or a template error shows up here.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\output\report_page
 */
final class report_page_test extends \advanced_testcase {
    /**
     * With two stored reports: the newest is shown, the older one is listed,
     * the button offers to update, grades use the assignment's scale.
     */
    public function test_renders_stored_report_and_history(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $context = \context_module::instance($module->cmid);
        $PAGE->set_url('/local/aigrader/report.php', ['cmid' => $module->cmid]);
        $PAGE->set_context($context);

        $summary = summary::parse(json_encode([
            'overview'            => 'OVERVIEW-MARKER',
            'common_gaps'         => [['gap' => 'GAP-MARKER', 'submissions' => 4, 'advice' => 'ADVICE-MARKER']],
            'class_strengths'     => ['STRENGTH-MARKER'],
            'topics_to_reinforce' => ['TOPIC-MARKER'],
            'next_steps'          => ['STEP-MARKER'],
        ]));
        $older = $this->store_report($assign, 'OLDER', time() - 3600);
        $newer = $this->store_report($assign, $summary->to_json(), time());

        $stats = statistics::from_rows([
            (object) ['status' => 'ai_proposed', 'proposed_grade' => 8.0, 'final_grade' => null,
                'proposed_feedback' => json_encode(['criterion_scores' => ['thesis_clarity' => 6]]), 'final_feedback' => null],
            (object) ['status' => 'published', 'proposed_grade' => 5.0, 'final_grade' => 6.0,
                'proposed_feedback' => null, 'final_feedback' => json_encode(['criterion_scores' => ['thesis_clarity' => 4]])],
            (object) ['status' => 'ai_proposed', 'proposed_grade' => 7.0, 'final_grade' => null,
                'proposed_feedback' => json_encode(['criterion_scores' => ['thesis_clarity' => 5]]), 'final_feedback' => null],
        ]);
        $page = new report_page(
            get_coursemodule_from_id('assign', $module->cmid),
            \local_aigrader\grading_scale::for_assign($assign, $context),
            $stats,
            $newer,
            [$newer, $older]
        );

        $output = $PAGE->get_renderer('core');
        $html = $output->render_from_template('local_aigrader/class_report', $page->export_for_template($output));

        foreach (['OVERVIEW-MARKER', 'GAP-MARKER', 'ADVICE-MARKER', 'STRENGTH-MARKER', 'TOPIC-MARKER', 'STEP-MARKER'] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
        $this->assertStringContainsString(get_string('classreport_generate_update', 'local_aigrader'), $html);
        $this->assertStringContainsString(get_string('classreport_history_heading', 'local_aigrader'), $html);
        $this->assertStringContainsString('70 / 100', $html);
        $this->assertStringContainsString('Thesis clarity', $html);
        $this->assertStringContainsString('reportid=' . $older->id, $html);
        $this->assertStringNotContainsString('[[', $html, 'A language string is missing.');
    }

    /**
     * Without reports and with too few graded submissions: the notice, no button.
     */
    public function test_renders_empty_state(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $context = \context_module::instance($module->cmid);
        $PAGE->set_url('/local/aigrader/report.php', ['cmid' => $module->cmid]);
        $PAGE->set_context($context);

        $page = new report_page(
            get_coursemodule_from_id('assign', $module->cmid),
            \local_aigrader\grading_scale::for_assign($assign, $context),
            statistics::from_rows([]),
            null,
            []
        );
        $output = $PAGE->get_renderer('core');
        $html = $output->render_from_template('local_aigrader/class_report', $page->export_for_template($output));

        $this->assertStringContainsString(get_string('classreport_stats_none', 'local_aigrader'), $html);
        $this->assertStringContainsString(get_string('classreport_errornotenough', 'local_aigrader', generator::MIN_SUBMISSIONS), $html);
        $this->assertStringNotContainsString('<button', $html);
        $this->assertStringNotContainsString('[[', $html, 'A language string is missing.');
    }

    /**
     * Store a report row.
     *
     * @param \stdClass $assign The assignment.
     * @param string $summary Summary JSON (or any text).
     * @param int $time Creation time.
     * @return \stdClass The stored row.
     */
    private function store_report(\stdClass $assign, string $summary, int $time): \stdClass {
        global $DB, $USER;
        $record = (object) [
            'assignid' => $assign->id, 'courseid' => $assign->course, 'userid' => $USER->id, 'submissions' => 3,
            'stats' => '{}', 'summary' => $summary, 'timecreated' => $time,
        ];
        $record->id = $DB->insert_record('local_aigrader_report', $record);
        return $record;
    }
}
