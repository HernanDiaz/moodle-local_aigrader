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

use local_aigrader\name_redactor;

/**
 * Builds and stores the class report of an assignment: statistics from the
 * AI Grader Pro proposals plus one AI call for the written summary.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** Fewest graded submissions for a report: fewer would describe individuals, not a class. */
    public const MIN_SUBMISSIONS = 3;

    /**
     * Graded proposal rows of an assignment.
     *
     * @param int $assignid The {assign}.id.
     * @return \stdClass[] Rows of {local_aigrader_submission}.
     */
    public static function graded_rows(int $assignid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(statistics::GRADED_STATUSES, SQL_PARAMS_NAMED);
        $params['assignid'] = $assignid;
        return $DB->get_records_select('local_aigrader_submission', "assignid = :assignid AND status $insql", $params, 'id');
    }

    /**
     * Generate a new report with one AI call and store it.
     *
     * @param \stdClass $assign Row of {assign}.
     * @param \context_module $context The assignment's context.
     * @return \stdClass The stored row of {local_aigrader_report}.
     * @throws \moodle_exception When there are too few graded submissions or the AI call fails.
     */
    public static function generate(\stdClass $assign, \context_module $context): \stdClass {
        global $DB, $USER;

        $config = $DB->get_record('local_aigrader_assign', ['assignid' => $assign->id], '*', MUST_EXIST);
        $rows = self::graded_rows((int) $assign->id);
        $stats = statistics::from_rows($rows);
        if ($stats->count < self::MIN_SUBMISSIONS) {
            throw new \moodle_exception('classreport_errornotenough', 'local_aigrader', '', self::MIN_SUBMISSIONS);
        }

        $language = \local_aigrader\prompt\builder::resolve_language($config, $assign);
        $prompt = prompt::build($assign, (string) $config->criteria_text, $stats, self::entries($rows), $language);

        $action = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: (int) $USER->id,
            prompttext: $prompt,
        );
        try {
            $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        } catch (\Throwable $e) {
            // Moodle 4.5's OpenAI provider throws a coding_exception instead of
            // returning an error response when the request fails without an
            // HTTP answer (network or TLS error, e.g. a proxy or antivirus
            // intercepting HTTPS).
            throw new \moodle_exception('classreport_erroraicall', 'local_aigrader', '', s($e->getMessage()));
        }
        if (!$response->get_success()) {
            throw new \moodle_exception('classreport_erroraicall', 'local_aigrader', '', s((string) $response->get_errormessage()));
        }
        $data = $response->get_response_data();
        $summary = summary::parse((string) ($data['generatedcontent'] ?? ''));

        [$provider, $model] = self::ai_origin((int) $USER->id);
        $record = (object) [
            'assignid'    => (int) $assign->id,
            'courseid'    => (int) $assign->course,
            'userid'      => (int) $USER->id,
            'submissions' => $stats->count,
            'stats'       => json_encode($stats->to_array()),
            'summary'     => $summary->to_json(),
            'language'    => $language,
            'llmprovider' => $provider,
            'llmmodel'    => $model,
            'tokensin'    => isset($data['prompttokens']) ? (int) $data['prompttokens'] : null,
            'tokensout'   => isset($data['completiontokens']) ? (int) $data['completiontokens'] : null,
            'timecreated' => time(),
        ];
        $record->id = $DB->insert_record('local_aigrader_report', $record);
        return $record;
    }

    /**
     * Per-submission entries for the prompt, with the student's name and
     * identifiers replaced when AI Grader Pro's setting asks for it (a
     * teacher's edited feedback can name the student).
     *
     * @param \stdClass[] $rows Graded rows of {local_aigrader_submission}.
     * @return array[] ['grade' => float, 'strengths' => string[], 'improvements' => string[]]
     */
    private static function entries(array $rows): array {
        global $DB;
        $redact = name_redactor::is_enabled();
        $entries = [];
        foreach ($rows as $row) {
            $grade = statistics::grade_of($row);
            if ($grade === null) {
                continue;
            }
            $feedback = statistics::feedback_of($row);
            $strengths = array_map('strval', (array) ($feedback['strengths'] ?? []));
            $improvements = array_map('strval', (array) ($feedback['improvements'] ?? []));
            if ($redact) {
                $submission = $DB->get_record('assign_submission', ['id' => $row->submissionid]);
                [$names, $identifiers] = $submission ? name_redactor::terms_for_submission($submission) : [[], []];
                $clean = fn(string $text): string => name_redactor::redact($text, $names, $identifiers);
                $strengths = array_map($clean, $strengths);
                $improvements = array_map($clean, $improvements);
            }
            $entries[] = ['grade' => $grade, 'strengths' => $strengths, 'improvements' => $improvements];
        }
        return $entries;
    }

    /**
     * Provider and model of the user's latest AI call, for the record.
     *
     * @param int $userid User who made the call.
     * @return array{0: string|null, 1: string|null}
     */
    private static function ai_origin(int $userid): array {
        global $DB;
        $records = $DB->get_records(
            'ai_action_register',
            ['actionname' => 'generate_text', 'userid' => $userid],
            'id DESC',
            'id, provider',
            0,
            1
        );
        $record = reset($records);
        if (!$record) {
            return [null, null];
        }
        $model = get_config($record->provider, 'action_generate_text_model');
        return [substr((string) $record->provider, 0, 64), is_string($model) && $model !== '' ? substr($model, 0, 128) : null];
    }
}
