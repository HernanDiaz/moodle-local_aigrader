<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Value object representing a fully composed LLM prompt for grading.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\prompt;

// Properties intentionally use the same casing as OpenAI/Anthropic chat.
// API fields (system_message, user_message) for clear round-tripping.
// phpcs:disable moodle.NamingConventions.ValidVariableName.MemberNameUnderscore

/**
 * Chat-style prompt (system + user messages) plus metadata.
 */
class built_prompt {
    /** @var string Top-level system instruction. */
    public string $system_message;

    /** @var string User-side message (criteria + brief + submission + output spec). */
    public string $user_message;

    /** @var array<string,mixed> Metadata (submissionid, assignid, language, submission_format...). */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param string $system System message content.
     * @param string $user User message content.
     * @param array $metadata Optional metadata map (string key => mixed value).
     */
    public function __construct(string $system, string $user, array $metadata = []) {
        $this->system_message = $system;
        $this->user_message   = $user;
        $this->metadata       = $metadata;
    }

    /**
     * Total characters across both messages.
     *
     * @return int
     */
    public function total_chars(): int {
        return mb_strlen($this->system_message) + mb_strlen($this->user_message);
    }

    /**
     * Return the prompt as an OpenAI/Anthropic-compatible chat messages array.
     *
     * @return array<int, array{role:string,content:string}>
     */
    public function as_chat_messages(): array {
        return [
            ['role' => 'system', 'content' => $this->system_message],
            ['role' => 'user', 'content' => $this->user_message],
        ];
    }

    /**
     * SHA-256 hash of the full prompt. Used for dedup and the audit log
     * column local_aigrader_log.prompt_hash.
     *
     * @return string Hex-encoded SHA-256 digest.
     */
    public function hash(): string {
        return hash('sha256', $this->system_message . "\n" . $this->user_message);
    }
}
