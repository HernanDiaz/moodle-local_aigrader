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
 * Tests for generating a class report, with the AI provider replaced by a
 * stub that records the prompt.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\report\generator
 * @covers     \local_aigrader\report\prompt
 */
final class generator_test extends \advanced_testcase {
    /** @var string|null Prompt received by the stub AI. */
    private ?string $sentprompt = null;

    /**
     * A report is stored from the stub's answer; the prompt carries the
     * statistics and numbered feedback in the assignment's language, and no
     * student name even when the teacher wrote one in the feedback.
     */
    public function test_generate_stores_report_without_student_names(): void {
        global $DB;
        $this->resetAfterTest();
        [$assign, $context, $students] = $this->assignment_with_proposals(3, ['language_override' => 'es']);
        set_config('redactnames', 1, 'local_aigrader');

        // The teacher named the first student in the reviewed feedback.
        $row = $DB->get_record('local_aigrader_submission', ['studentid' => $students[0]->id], '*', MUST_EXIST);
        $feedback = json_decode($row->final_feedback, true);
        $feedback['strengths'] = ['Buen trabajo, Valentina, muy clara la tesis'];
        $DB->set_field('local_aigrader_submission', 'final_feedback', json_encode($feedback), ['id' => $row->id]);

        $this->stub_ai(true, json_encode([
            'overview'            => 'La clase entendió la tarea.',
            'common_gaps'         => [['gap' => 'Evidencias débiles', 'submissions' => 2, 'advice' => 'Modelar una cita.']],
            'class_strengths'     => ['Estructura clara'],
            'topics_to_reinforce' => ['Citar fuentes'],
            'next_steps'          => ['Taller de citas'],
        ]));

        $record = generator::generate($assign, $context);

        $stored = $DB->get_record('local_aigrader_report', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertSame(3, (int) $stored->submissions);
        $this->assertSame('es', $stored->language);
        $this->assertSame('La clase entendió la tarea.', summary::from_json($stored->summary)->overview);
        $this->assertSame(3, statistics::from_array(json_decode($stored->stats, true))->count);

        $this->assertStringContainsString('#3 grade', $this->sentprompt);
        $this->assertStringContainsString('language with code "es"', $this->sentprompt);
        $this->assertStringContainsString('Buen trabajo, [STUDENT], muy clara la tesis', $this->sentprompt);
        $this->assertStringNotContainsString('Valentina', $this->sentprompt);
    }

    /**
     * Fewer than MIN_SUBMISSIONS graded submissions: no AI call, no report.
     */
    public function test_needs_minimum_submissions(): void {
        global $DB;
        $this->resetAfterTest();
        [$assign, $context] = $this->assignment_with_proposals(generator::MIN_SUBMISSIONS - 1);
        $this->stub_ai(true, '{}');

        try {
            generator::generate($assign, $context);
            $this->fail('Expected an exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('classreport_errornotenough', $e->errorcode);
        }
        $this->assertNull($this->sentprompt);
        $this->assertSame(0, $DB->count_records('local_aigrader_report'));
    }

    /**
     * An AI error is reported and nothing is stored.
     */
    public function test_ai_failure_stores_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        [$assign, $context] = $this->assignment_with_proposals(3);
        $this->stub_ai(false, '');

        $this->expectException(\moodle_exception::class);
        try {
            generator::generate($assign, $context);
        } finally {
            $this->assertSame(0, $DB->count_records('local_aigrader_report'));
        }
    }

    /**
     * A provider that throws instead of answering (network or TLS failure)
     * gives the teacher a readable error, not a coding error.
     */
    public function test_provider_exception_becomes_readable_error(): void {
        $this->resetAfterTest();
        [$assign, $context] = $this->assignment_with_proposals(3);
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('process_action')->willThrowException(
            new \coding_exception('Error code and message must exist in an error response.')
        );
        \core\di::set(\core_ai\manager::class, $manager);

        try {
            generator::generate($assign, $context);
            $this->fail('Expected an exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('classreport_erroraicall', $e->errorcode);
            $this->assertStringContainsString('The AI provider could not write the report', $e->getMessage());
        }
    }

    /**
     * An assignment with AI Grader Pro and some graded submissions.
     *
     * @param int $count Number of graded submissions (the first one teacher-reviewed).
     * @param array $config Overrides of the AI Grader Pro configuration.
     * @return array [assign row, context, students]
     */
    private function assignment_with_proposals(int $count, array $config = []): array {
        global $DB;
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $module = $generator->create_module('assign', ['course' => $course->id, 'assignsubmission_onlinetext_enabled' => 1]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $aigrader = $generator->get_plugin_generator('local_aigrader');
        $aigrader->enable_for_assignment($assign, $config);

        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Valentina', 'lastname' => 'Test' . $i]);
            $generator->get_plugin_generator('mod_assign')->create_submission([
                'userid' => $student->id, 'cmid' => $module->cmid, 'onlinetext' => 'Essay ' . $i,
            ]);
            $submission = $DB->get_record('assign_submission', ['assignment' => $assign->id, 'userid' => $student->id]);
            $aigrader->create_submission_proposal($submission, ['status' => $i === 0 ? 'teacher_reviewed' : 'ai_proposed']);
            $students[] = $student;
        }
        return [$assign, \context_module::instance($module->cmid), $students];
    }

    /**
     * Replace the AI Subsystem manager with a stub that records the prompt.
     *
     * @param bool $success Whether the call succeeds.
     * @param string $content Generated text.
     */
    private function stub_ai(bool $success, string $content): void {
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('process_action')->willReturnCallback(function ($action) use ($success, $content) {
            $this->sentprompt = $action->get_configuration('prompttext');
            $response = new \core_ai\aiactions\responses\response_generate_text(
                success: $success,
                errorcode: $success ? 0 : 500,
                errormessage: $success ? '' : 'Provider down',
            );
            if ($success) {
                $response->set_response_data(['generatedcontent' => $content, 'prompttokens' => 1200, 'completiontokens' => 300]);
            }
            return $response;
        });
        \core\di::set(\core_ai\manager::class, $manager);
    }
}
