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
 * Replaces the student's name and identifiers with a placeholder in the text
 * that is sent to the AI provider.
 *
 * Students put their name on cover pages, in headers and in file names. The
 * AI does not need it to grade, and not sending it keeps personal data away
 * from the external provider. What is replaced:
 *
 *   - every part of the first name, last name, middle name and alternate
 *     name (3+ letters, not particles such as "de" or "van"), matched as a
 *     whole word, ignoring accents: "María" also matches "Maria" and "MARÍA";
 *   - the email address, username and ID number (5+ characters), anywhere.
 *
 * A name part is replaced when it is written as a proper noun (capitalised or
 * in capitals) or as part of an identifier such as a file name
 * ("practica_maria_garcia.docx", "garcia2024"). A lower-case common word that
 * happens to be a surname ("blanco", "rosa", "campos") is left alone.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class name_redactor {
    /** Placeholder written instead of the student's identifiers. */
    public const PLACEHOLDER = '[STUDENT]';

    /** @var int Shortest name part that is replaced. */
    private const MIN_NAME_LENGTH = 3;

    /** @var int Shortest username / ID number that is replaced. */
    private const MIN_IDENTIFIER_LENGTH = 5;

    /** Name particles that are never replaced on their own. */
    private const PARTICLES = [
        'al', 'bin', 'da', 'das', 'de', 'del', 'della', 'den', 'der', 'di', 'do', 'dos', 'du',
        'e', 'el', 'i', 'la', 'las', 'le', 'les', 'los', 'van', 'von', 'y',
    ];

    /** Letters that match their accented forms. */
    private const ACCENTED = [
        'a' => 'aáàâäãåą', 'c' => 'cçćč', 'e' => 'eéèêëę', 'i' => 'iíìîï', 'l' => 'lł',
        'n' => 'nñń', 'o' => 'oóòôöõø', 's' => 'sśš', 'u' => 'uúùûü', 'y' => 'yýÿ', 'z' => 'zźżž',
    ];

    /**
     * Whether the site administrator enabled the replacement.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_aigrader', 'redactnames');
    }

    /**
     * Names and identifiers of the author(s) of a submission: the student, or
     * every member of the group for a group submission.
     *
     * @param \stdClass $submission Row of {assign_submission}.
     * @return array{0: string[], 1: string[]} Name parts, and exact identifiers.
     */
    public static function terms_for_submission(\stdClass $submission): array {
        global $DB;

        if (!empty($submission->userid)) {
            $userids = [(int) $submission->userid];
        } else if (!empty($submission->groupid)) {
            $userids = array_keys(groups_get_members((int) $submission->groupid, 'u.id'));
        } else {
            $userids = [];
        }
        if (!$userids) {
            return [[], []];
        }

        $users = $DB->get_records_list(
            'user',
            'id',
            $userids,
            '',
            'id, username, email, idnumber, firstname, lastname, middlename, alternatename'
        );
        $names = [];
        $identifiers = [];
        foreach ($users as $user) {
            foreach (['firstname', 'lastname', 'middlename', 'alternatename'] as $field) {
                foreach (preg_split('/[\s\-\'’.]+/u', (string) $user->$field, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (
                        \core_text::strlen($part) >= self::MIN_NAME_LENGTH
                        && !in_array(\core_text::strtolower($part), self::PARTICLES, true)
                    ) {
                        $names[] = $part;
                    }
                }
            }
            foreach (['email', 'username', 'idnumber'] as $field) {
                $value = trim((string) $user->$field);
                if (\core_text::strlen($value) >= self::MIN_IDENTIFIER_LENGTH && !(ctype_digit($value) && strlen($value) < 6)) {
                    $identifiers[] = $value;
                }
            }
        }
        return [array_values(array_unique($names)), array_values(array_unique($identifiers))];
    }

    /**
     * Replace names and identifiers in a text.
     *
     * @param string $text Text to clean.
     * @param string[] $names Name parts, from terms_for_submission().
     * @param string[] $identifiers Email, username, ID number, from terms_for_submission().
     * @return string The text with PLACEHOLDER instead of each match.
     */
    public static function redact(string $text, array $names, array $identifiers = []): string {
        if ($text === '' || (!$names && !$identifiers)) {
            return $text;
        }

        // Identifiers first: an email address contains the name parts too.
        usort($identifiers, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($identifiers as $identifier) {
            $text = preg_replace(
                '/(?<![\p{L}\p{N}])' . preg_quote($identifier, '/') . '(?![\p{L}\p{N}])/iu',
                self::PLACEHOLDER,
                $text
            );
        }

        if ($names) {
            usort($names, fn($a, $b) => \core_text::strlen($b) <=> \core_text::strlen($a));
            $alternatives = implode('|', array_map([self::class, 'accent_insensitive'], $names));
            // A name part is a whole word: no letter right before or after it
            // (digits are fine: "garcia2024"). "post" peeks at up to two
            // characters to tell ".docx" from the end of a sentence.
            $pattern = '/(?<pre>^|[^\p{L}])(?<name>' . $alternatives . ')(?=(?<post>[^\p{L}]\p{L}?|$))/iu';
            $text = preg_replace_callback($pattern, function (array $m): string {
                $first = \core_text::substr($m['name'], 0, 1);
                $propernoun = $first !== \core_text::strtolower($first);
                $post = $m['post'] ?? '';
                $inidentifier = preg_match('/[_@\d]/u', $m['pre']) === 1
                    || preg_match('/^(?:[_@\d]|\.\p{L})/u', $post) === 1;
                return ($propernoun || $inidentifier) ? $m['pre'] . self::PLACEHOLDER : $m[0];
            }, $text);
        }

        // "[STUDENT] [STUDENT]" (first name and surname) or "[STUDENT]_[STUDENT]"
        // reads as one person; ". " between them is a sentence break, kept.
        $placeholder = preg_quote(self::PLACEHOLDER, '/');
        return preg_replace(
            '/' . $placeholder . '(?:(?:[ \t_\-]+|,[ \t]*|\.(?!\s))' . $placeholder . ')+/u',
            self::PLACEHOLDER,
            $text
        );
    }

    /**
     * Regular expression for a name part that also matches it with or
     * without accents.
     *
     * @param string $name A name part, e.g. "María".
     * @return string Regex fragment, e.g. "m[aáàâäãåą]r[iíìîï][aáàâäãåą]".
     */
    private static function accent_insensitive(string $name): string {
        $plain = \core_text::specialtoascii(\core_text::strtolower($name));
        $regex = '';
        foreach (preg_split('//u', $plain, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $regex .= isset(self::ACCENTED[$char]) ? '[' . self::ACCENTED[$char] . ']' : preg_quote($char, '/');
        }
        return $regex;
    }
}
