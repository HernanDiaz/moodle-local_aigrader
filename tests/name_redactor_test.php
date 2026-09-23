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
 * Tests for keeping the student's name out of the text sent to the AI.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\name_redactor
 * @covers     \local_aigrader\extractor\dispatcher::extract
 * @covers     \local_aigrader\prompt\builder::build_for_submission
 */
final class name_redactor_test extends \advanced_testcase {
    /**
     * Name parts are replaced as whole words, with or without accents and in
     * capitals, and a full name becomes a single placeholder.
     */
    public function test_names_replaced_regardless_of_accents_and_case(): void {
        $names = ['María', 'García', 'López'];

        $this->assertSame(
            'My name is [STUDENT]. [STUDENT] wrote this.',
            name_redactor::redact('My name is María García López. Maria wrote this.', $names)
        );
        $this->assertSame('AUTHOR: [STUDENT]', name_redactor::redact('AUTHOR: MARIA GARCIA', $names));
        // Longer words that contain a name are left alone.
        $this->assertSame('Marianne and Garcíaz', name_redactor::redact('Marianne and Garcíaz', $names));
    }

    /**
     * A surname that is also a common word is only replaced when written as
     * a proper noun or inside an identifier.
     */
    public function test_common_words_kept_unless_proper_noun_or_identifier(): void {
        $names = ['Luis', 'Blanco'];

        $this->assertSame(
            'El fondo es blanco. Firmado: [STUDENT].',
            name_redactor::redact('El fondo es blanco. Firmado: Luis Blanco.', $names)
        );
        $this->assertSame(
            '=== practica_[STUDENT].docx ===',
            name_redactor::redact('=== practica_luis_blanco.docx ===', $names)
        );
    }

    /**
     * Email address, username and ID number are replaced anywhere.
     */
    public function test_identifiers_replaced(): void {
        $text = 'Contact: luis.blanco@example.com (user lblanco, ID 20231234).';
        $this->assertSame(
            'Contact: [STUDENT] (user [STUDENT], ID [STUDENT]).',
            name_redactor::redact($text, ['Luis', 'Blanco'], ['luis.blanco@example.com', 'lblanco', '20231234'])
        );
    }

    /**
     * Names are split into parts; particles and very short parts are not
     * used; short numeric ID numbers are not used.
     */
    public function test_terms_for_submission(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'María José',
            'lastname'  => 'de la Fuente',
            'middlename'    => '',
            'alternatename' => '',
            'username'  => 'mjfuente',
            'email'     => 'mj.fuente@example.com',
            'idnumber'  => '123',
        ]);

        [$names, $identifiers] = name_redactor::terms_for_submission((object) ['userid' => $user->id, 'groupid' => 0]);

        $this->assertEqualsCanonicalizing(['María', 'José', 'Fuente'], $names);
        $this->assertEqualsCanonicalizing(['mj.fuente@example.com', 'mjfuente'], $identifiers);
    }

    /**
     * The extracted submission and the prompt carry the placeholder instead of
     * the student's name, and the prompt tells the AI why. With the setting
     * off, the text is left as written.
     */
    public function test_submission_text_and_prompt_are_redacted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Lucía',
            'lastname'  => 'Pérez',
            'email'     => 'lucia.perez@example.com',
        ]);
        $module = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
            'userid'     => $student->id,
            'cmid'       => $module->cmid,
            'onlinetext' => 'Soy Lucía Pérez (lucia.perez@example.com). Mi ensayo trata del color blanco.',
        ]);
        $assign = $DB->get_record('assign', ['id' => $module->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('local_aigrader')->enable_for_assignment($assign);
        $submission = $DB->get_record('assign_submission', ['assignment' => $assign->id, 'userid' => $student->id]);

        set_config('redactnames', 1, 'local_aigrader');
        $text = extractor\dispatcher::extract((int) $submission->id)->text;
        $this->assertStringContainsString('Soy [STUDENT] ([STUDENT]).', $text);
        $this->assertStringContainsString('color blanco', $text);
        $this->assertStringNotContainsString('Lucía', $text);

        $prompt = prompt\builder::build_for_submission((int) $submission->id);
        $this->assertStringContainsString('replaced with [STUDENT] for privacy', $prompt->user_message);
        $this->assertStringNotContainsString('Pérez', $prompt->user_message);

        set_config('redactnames', 0, 'local_aigrader');
        $this->assertStringContainsString('Soy Lucía Pérez', extractor\dispatcher::extract((int) $submission->id)->text);
        $this->assertStringNotContainsString('[STUDENT]', prompt\builder::build_for_submission((int) $submission->id)->user_message);
    }
}
