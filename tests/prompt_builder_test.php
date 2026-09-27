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

namespace local_aigrader;

/**
 * Tests for the criterion keys the grading prompt asks the AI for.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\prompt\builder
 */
final class prompt_builder_test extends \advanced_testcase {
    /**
     * An assignment with AI Grader Pro enabled and two online-text submissions.
     *
     * @param int $criteriasaved When the criteria were last saved.
     * @return array [config row, first submission, second submission]
     */
    private function assignment_with_two_submissions(int $criteriasaved): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $config = $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment($assign, [
            'criteria_text' => "- Claridad de la tesis (60%)\n- Uso de fuentes (40%)",
            'timemodified'  => $criteriasaved,
        ]);
        $submissions = [];
        foreach (['Primer ensayo.', 'Segundo ensayo.'] as $text) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
                'userid' => $student->id, 'cmid' => $module->cmid, 'onlinetext' => $text,
            ]);
            $submissions[] = $DB->get_record('assign_submission', ['assignment' => $assign->id, 'userid' => $student->id]);
        }
        return [$config, $submissions[0], $submissions[1]];
    }

    /**
     * The prompt asks for keys in the teacher's own words and language, not translated ones.
     */
    public function test_keys_keep_the_teachers_wording(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $first] = $this->assignment_with_two_submissions(time());

        $prompt = prompt\builder::build_for_submission((int) $first->id)->user_message;

        $this->assertStringContainsString('the same words in the same language (never translated)', $prompt);
        $this->assertStringContainsString('becomes "claridad_de_la_tesis"', $prompt);
        $this->assertStringNotContainsString('Use exactly these criterion_scores keys', $prompt);
    }

    /**
     * Once a submission has an AI proposal, the next prompts ask for exactly its keys.
     */
    public function test_later_prompts_reuse_the_keys_of_the_latest_proposal(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        [$config, $first, $second] = $this->assignment_with_two_submissions($now - 600);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->create_submission_proposal($first, [
            'proposed_feedback' => json_encode(['criterion_scores' => ['claridad_de_la_tesis' => 7, 'uso_de_fuentes' => 5]]),
            'timeprocessed' => $now,
        ]);

        $this->assertSame(['claridad_de_la_tesis', 'uso_de_fuentes'], prompt\builder::known_criterion_keys($config));
        $prompt = prompt\builder::build_for_submission((int) $second->id)->user_message;
        $this->assertStringContainsString('"claridad_de_la_tesis", "uso_de_fuentes".', $prompt);
    }

    /**
     * A failed regrade keeps its older proposal on an error row; that proposal does not dictate the keys.
     */
    public function test_error_rows_are_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        [$config, $first, $second] = $this->assignment_with_two_submissions($now - 600);
        $gen = $this->getDataGenerator()->get_plugin_generator('local_aigrader');
        $gen->create_submission_proposal($first, [
            'proposed_feedback' => json_encode(['criterion_scores' => ['claridad_de_la_tesis' => 7]]),
            'timeprocessed' => $now - 60,
        ]);
        $gen->create_submission_proposal($second, [
            'status' => 'error',
            'proposed_feedback' => json_encode(['criterion_scores' => ['thesis_clarity' => 5]]),
            'timeprocessed' => $now,
        ]);

        $this->assertSame(['claridad_de_la_tesis'], prompt\builder::known_criterion_keys($config));
    }

    /**
     * Proposals made before the criteria were last saved do not dictate the keys.
     */
    public function test_proposals_older_than_the_criteria_are_ignored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        [$config, $first, $second] = $this->assignment_with_two_submissions($now);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->create_submission_proposal($first, [
            'proposed_feedback' => json_encode(['criterion_scores' => ['thesis_clarity' => 7]]),
            'timeprocessed' => $now - 600,
        ]);

        $this->assertSame([], prompt\builder::known_criterion_keys($config));
        $prompt = prompt\builder::build_for_submission((int) $second->id)->user_message;
        $this->assertStringNotContainsString('thesis_clarity', $prompt);
    }
}
