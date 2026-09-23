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

/**
 * Test data generator for local_aigrader.
 *
 * Lets PHPUnit and Behat suites create the plugin's records directly,
 * skipping the LLM round-trip:
 *
 *   $gen = $this->getDataGenerator()->get_plugin_generator('local_aigrader');
 *   $gen->enable_for_assignment($assign, ['criteria_text' => '...']);
 *   $gen->create_submission_proposal($studentsub, [
 *       'status' => 'ai_proposed',
 *       'proposed_grade' => 8.5,
 *   ]);
 *
 * The Behat layer uses this generator via tests/behat/behat_local_aigrader.php,
 * which exposes the same operations as Gherkin Givens. The point is that the
 * test never has to talk to a real (or mocked) AI Subsystem provider — it
 * just plants the row in the state it wants and drives the UI from there.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator for local_aigrader.
 *
 * Inherits component_generator_base so Moodle's test bootstrap picks it up
 * automatically. The class name `local_aigrader_generator` is fixed by the
 * generator-loader convention — don't rename it.
 */
class local_aigrader_generator extends component_generator_base {
    /** Default evaluation criteria used when the caller doesn't pass any. */
    private const DEFAULT_CRITERIA = "Evaluate clarity of thesis, structure and academic language.";

    /** Default proposed_feedback JSON when the caller doesn't pass any. */
    private const DEFAULT_PROPOSED_FEEDBACK = [
        'final_grade'      => 8.0,
        'criterion_scores' => [
            'thesis_clarity'  => 8,
            'structure'       => 7,
            'language'        => 9,
        ],
        'strengths'        => ['Clear thesis statement', 'Good use of evidence'],
        'improvements'     => ['Tighten the conclusion', 'Cite one more academic source'],
        'justification'    => 'The essay holds together and argues a defensible position. Minor structural issues.',
    ];

    /**
     * Enable AI Grader Pro on an assignment by inserting a local_aigrader_assign row.
     *
     * Usage:
     *   $gen->enable_for_assignment($assign);                       // defaults
     *   $gen->enable_for_assignment($assign, [                      // overrides
     *       'criteria_text'     => 'Custom criteria here',
     *       'model_override'    => 'gpt-4o-mini',
     *       'language_override' => 'es',
     *       'enabled'           => 1,
     *   ]);
     *
     * If a row already exists for $assign->id, it is updated rather than
     * duplicated. The unique key on assignid makes the duplicate insert
     * fatal otherwise.
     *
     * @param stdClass $assign The assign record (must have an ->id field).
     * @param array $overrides Optional per-field overrides.
     * @return stdClass The persisted local_aigrader_assign row.
     */
    public function enable_for_assignment(stdClass $assign, array $overrides = []): stdClass {
        global $DB, $USER;

        $now = time();
        $base = [
            'assignid'          => (int) $assign->id,
            'enabled'           => 1,
            'criteria_text'     => self::DEFAULT_CRITERIA,
            'source'            => 'manual',
            'model_override'    => null,
            'language_override' => null,
            'autograde'         => 0,
            'usermodified'      => (int) ($USER->id ?? 2),
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        $record = (object) array_merge($base, $overrides);

        $existing = $DB->get_record('local_aigrader_assign', ['assignid' => $record->assignid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aigrader_assign', $record);
            return $DB->get_record('local_aigrader_assign', ['id' => $existing->id], '*', MUST_EXIST);
        }

        $id = $DB->insert_record('local_aigrader_assign', $record);
        return $DB->get_record('local_aigrader_assign', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Plant a local_aigrader_submission row for an existing assign_submission.
     *
     * Designed so the test can decide what state the row should be in
     * without having to drive the real grading pipeline (which would call
     * an LLM). Status values:
     *
     *   - 'pending_ai'        : job queued / in flight (no proposal yet)
     *   - 'ai_proposed'       : LLM returned a proposal, awaiting teacher review
     *   - 'teacher_reviewed'  : teacher saved a draft (edits made, not published)
     *   - 'published'         : teacher approved & grade is in the gradebook
     *   - 'error'             : LLM call failed
     *   - 'unsupported_format': preflight refused (e.g. nothing extractable)
     *
     * @param stdClass $assignsub The assign_submission record this proposal is for.
     * @param array $overrides Per-field overrides.
     * @return stdClass The persisted local_aigrader_submission row.
     */
    public function create_submission_proposal(stdClass $assignsub, array $overrides = []): stdClass {
        global $DB;

        $assign = $DB->get_record('assign', ['id' => $assignsub->assignment], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course, false, MUST_EXIST);

        $now = time();
        $status = $overrides['status'] ?? 'ai_proposed';

        $defaults = [
            'submissionid'      => (int) $assignsub->id,
            'assignid'          => (int) $assign->id,
            'courseid'          => (int) $assign->course,
            'studentid'         => (int) $assignsub->userid,
            'status'            => $status,
            'proposed_grade'    => null,
            'proposed_feedback' => null,
            'final_grade'       => null,
            'final_feedback'    => null,
            'final_grader'      => null,
            'error_message'     => null,
            'timecreated'       => $now,
            'timemodified'      => $now,
            'timeprocessed'     => null,
            'timepublished'     => null,
        ];

        // Sensible defaults per status. The caller can still override anything
        // via $overrides, but these defaults make the common cases zero-config.
        switch ($status) {
            case 'ai_proposed':
                $defaults['proposed_grade']    = 8.0;
                $defaults['proposed_feedback'] = json_encode(self::DEFAULT_PROPOSED_FEEDBACK);
                $defaults['timeprocessed']     = $now;
                break;
            case 'teacher_reviewed':
                $defaults['proposed_grade']    = 8.0;
                $defaults['proposed_feedback'] = json_encode(self::DEFAULT_PROPOSED_FEEDBACK);
                $defaults['final_grade']       = 7.5;
                $defaults['final_feedback']    = json_encode(array_merge(
                    self::DEFAULT_PROPOSED_FEEDBACK,
                    ['final_grade' => 7.5]
                ));
                $defaults['timeprocessed']     = $now;
                break;
            case 'published':
                $defaults['proposed_grade']    = 8.0;
                $defaults['proposed_feedback'] = json_encode(self::DEFAULT_PROPOSED_FEEDBACK);
                $defaults['final_grade']       = 8.0;
                $defaults['final_feedback']    = json_encode(self::DEFAULT_PROPOSED_FEEDBACK);
                $defaults['timeprocessed']     = $now;
                $defaults['timepublished']     = $now;
                break;
            case 'error':
                $defaults['error_message']     = 'Simulated provider error for testing.';
                $defaults['timeprocessed']     = $now;
                break;
            case 'unsupported_format':
                $defaults['error_message']     = 'Submission contained no extractable text.';
                $defaults['timeprocessed']     = $now;
                break;
            case 'pending_ai':
                // No proposal / no error — task is in flight.
                break;
        }

        $record = (object) array_merge($defaults, $overrides);

        // Final_grader defaults to a teacher of the course (or admin) when
        // status implies teacher action; lets the privacy export queries
        // find a userid to attribute the action to.
        if (
            in_array($record->status, ['teacher_reviewed', 'published'], true)
            && empty($record->final_grader)
        ) {
            $record->final_grader = $this->guess_grader_userid($cm);
        }

        // local_aigrader_submission has a unique key on submissionid — upsert.
        $existing = $DB->get_record('local_aigrader_submission', [
            'submissionid' => $record->submissionid,
        ]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_aigrader_submission', $record);
            return $DB->get_record('local_aigrader_submission', ['id' => $existing->id], '*', MUST_EXIST);
        }
        $id = $DB->insert_record('local_aigrader_submission', $record);
        return $DB->get_record('local_aigrader_submission', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Append a row to the audit log (local_aigrader_log).
     *
     * Used by privacy tests and by Behat scenarios that need pre-existing
     * audit history to be present (e.g. "the teacher already proposed a
     * grade yesterday").
     *
     * @param stdClass $assignsub
     * @param array $overrides
     * @return stdClass
     */
    public function create_log_entry(stdClass $assignsub, array $overrides = []): stdClass {
        global $DB, $USER;

        $assign = $DB->get_record('assign', ['id' => $assignsub->assignment], '*', MUST_EXIST);

        $defaults = [
            'submissionid'      => (int) $assignsub->id,
            'userid'            => (int) ($USER->id ?? 2),
            'studentid'         => (int) $assignsub->userid,
            'courseid'          => (int) $assign->course,
            'action'            => 'grade',
            'llm_provider'      => 'openai',
            'llm_model'         => 'gpt-4o-mini',
            'prompt_hash'       => hash('sha256', 'test-prompt'),
            'prompt_text'       => 'Test prompt content (truncated for fixture).',
            'response_json'     => json_encode(['ok' => true, 'grade' => 8.0]),
            'tokens_input'      => 1200,
            'tokens_output'     => 350,
            'cost_usd'          => 0.0023,
            'duration_ms'       => 1850,
            'proposed_grade'    => 8.0,
            'final_grade'       => null,
            'teacher_edits'     => null,
            'submission_format' => 'onlinetext',
            'timecreated'       => time(),
        ];
        $record = (object) array_merge($defaults, $overrides);
        $id = $DB->insert_record('local_aigrader_log', $record);
        return $DB->get_record('local_aigrader_log', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Plant several proposal rows in one call, varying the status field.
     *
     * Convenience helper for Behat scenarios that need a manage.php page
     * with a mix of states. Returns the array of created rows keyed by
     * status for assertions:
     *
     *   $rows = $gen->seed_cohort_with_mixed_statuses([
     *       'ai_proposed'    => 3,
     *       'published'      => 2,
     *       'teacher_reviewed' => 1,
     *   ], $assign);
     *
     * Each status counts is satisfied by creating that many *new* students
     * and assign_submissions under the same assignment, then creating the
     * matching local_aigrader_submission row.
     *
     * @param array $counts Map of status => integer count.
     * @param stdClass $assign Existing assign record.
     * @return array<int, stdClass> Created local_aigrader_submission rows.
     */
    public function seed_cohort_with_mixed_statuses(array $counts, stdClass $assign): array {
        global $DB;
        $created = [];
        $generator = testing_util::get_data_generator();
        $coursecat = $DB->get_record('course', ['id' => $assign->course], 'id', MUST_EXIST);

        foreach ($counts as $status => $n) {
            for ($i = 0; $i < $n; $i++) {
                $student = $generator->create_user();
                $generator->enrol_user($student->id, $coursecat->id, 'student');

                // mod_assign's own generator creates the submission row
                // properly (status, attemptnumber, etc.). Cheaper than DML.
                $assignsubid = $DB->insert_record('assign_submission', (object) [
                    'assignment'    => (int) $assign->id,
                    'userid'        => (int) $student->id,
                    'timecreated'   => time(),
                    'timemodified'  => time(),
                    'status'        => 'submitted',
                    'groupid'       => 0,
                    'attemptnumber' => 0,
                    'latest'        => 1,
                ]);
                $assignsub = $DB->get_record('assign_submission', ['id' => $assignsubid], '*', MUST_EXIST);

                $created[] = $this->create_submission_proposal($assignsub, ['status' => $status]);
            }
        }
        return $created;
    }

    /**
     * Best-effort lookup of a teacher userid in the course of a given cm,
     * for filling `final_grader` when not explicitly provided.
     *
     * @param stdClass $cm
     * @return int Falls back to admin uid (2) when no teacher is enrolled.
     */
    private function guess_grader_userid(stdClass $cm): int {
        global $DB;
        $context = context_course::instance($cm->course);
        // Try editing teachers first; then any user with the use capability.
        $teachers = get_users_by_capability(
            $context,
            'local/aigrader:use',
            'u.id',
            'u.id ASC',
            '0',
            '1'
        );
        if (!empty($teachers)) {
            $teacher = reset($teachers);
            return (int) $teacher->id;
        }
        // Fall back to admin (uid 2 in fresh installs).
        return 2;
    }
}
