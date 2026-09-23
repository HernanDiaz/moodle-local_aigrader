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
 * Extractor for .docx files (Microsoft Word 2007+).
 *
 * A .docx is a ZIP archive whose body lives in word/document.xml. We unzip,
 * read that XML, convert paragraph closings to newlines and strip the rest
 * of the tags. This works without external dependencies (no PhpWord needed)
 * and is sufficient for grading purposes (body text only — we do not
 * extract images, tables structure, comments or tracked changes).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;
/**
 * Class docx_extractor.
 */
class docx_extractor {
    /**
     * Extract plain text from a .docx file.
     *
     * @param \stored_file $file The Word document uploaded by the student.
     * @return string|null Extracted plain text or null on failure / empty.
     */
    public static function extract_file(\stored_file $file): ?string {
        $tmppath = self::copy_to_temp($file);
        if ($tmppath === null) {
            return null;
        }
        $text = self::extract_path($tmppath);
        @unlink($tmppath);
        return $text;
    }

    /**
     * Extract plain text from a .docx file on disk (e.g. one found inside a
     * .zip submission).
     *
     * @param string $path Filesystem path of the Word document.
     * @return string|null Extracted plain text or null on failure / empty.
     */
    public static function extract_path(string $path): ?string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return null;
        }

        return self::xml_to_text($xml);
    }

    /**
     * Convert WordprocessingML XML to plain text.
     *
     * @param string $xml Raw `word/document.xml` content from the docx zip.
     * @return string|null Plain text, or null if nothing extractable.
     */
    private static function xml_to_text(string $xml): ?string {
        // Replace paragraph endings, line breaks and tabs with their text equivalents.
        // BEFORE stripping tags — otherwise we lose paragraph separation.
        $xml = preg_replace('#</w:p>#i', "\n", $xml);
        $xml = preg_replace('#<w:br\s*/?>#i', "\n", $xml);
        $xml = preg_replace('#<w:tab\s*/?>#i', "\t", $xml);
        $xml = preg_replace('#</w:tr>#i', "\n", $xml);

        // Strip all remaining XML tags.
        $text = strip_tags($xml);

        // Decode XML entities (&amp; &lt; &#x...).
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');

        // Normalise whitespace: collapse 3+ newlines to 2, strip trailing spaces.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $lines = preg_split('/\n/', $text);
        $lines = array_map('rtrim', $lines);
        $text  = implode("\n", $lines);
        $text  = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Copy a stored_file to a temp path. Returns null on failure.
     *
     * @param \stored_file $file Source stored file (the submitted docx).
     * @return string|null Temp filesystem path, or null on copy failure.
     */
    private static function copy_to_temp(\stored_file $file): ?string {
        try {
            $tmppath = tempnam(sys_get_temp_dir(), 'aigrader_docx_');
            $file->copy_content_to($tmppath);
            return $tmppath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
