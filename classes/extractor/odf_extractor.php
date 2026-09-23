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
 * Extractor for OpenDocument text (.odt) and presentation (.odp) files, as
 * written by LibreOffice, OpenOffice and Google Docs exports.
 *
 * An OpenDocument file is a ZIP archive whose body lives in content.xml.
 * We keep the text of the document body only: tracked deletions, comments
 * and image descriptions are dropped. Presentations are split into
 * "--- Slide N ---" blocks with their speaker notes.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Class odf_extractor.
 */
class odf_extractor {
    /** @var int Largest content.xml read, uncompressed (guards against zip bombs). */
    private const MAX_CONTENT_BYTES = 20971520;

    /**
     * Extract plain text from an .odt or .odp file.
     *
     * @param \stored_file $file The document uploaded by the student.
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
     * Extract plain text from an .odt or .odp file on disk.
     *
     * @param string $path Filesystem path of the document.
     * @return string|null Extracted plain text or null on failure / empty.
     */
    public static function extract_path(string $path): ?string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $stat = $zip->statName('content.xml');
        $xml = ($stat !== false && $stat['size'] <= self::MAX_CONTENT_BYTES) ? $zip->getFromName('content.xml') : false;
        $zip->close();

        if (!$xml) {
            return null;
        }
        return self::xml_to_text($xml);
    }

    /**
     * Convert OpenDocument content.xml to plain text.
     *
     * @param string $xml Raw content.xml.
     * @return string|null Plain text, or null if nothing extractable.
     */
    private static function xml_to_text(string $xml): ?string {
        if (preg_match('#<office:body\b[^>]*>(.*)</office:body>#s', $xml, $m)) {
            $xml = $m[1];
        }

        // Text the reader of the document does not see.
        $xml = preg_replace('#<text:tracked-changes\b.*?</text:tracked-changes>#s', '', $xml);
        $xml = preg_replace('#<office:annotation\b.*?</office:annotation>#s', '', $xml);
        $xml = preg_replace('#<svg:(title|desc)\b.*?</svg:\1>#s', '', $xml);

        // Presentations: one block per slide, speaker notes after it.
        $slide = 0;
        $xml = preg_replace_callback('#<draw:page\b[^>]*>#', function () use (&$slide) {
            $slide++;
            return "\n--- Slide " . $slide . " ---\n";
        }, $xml);
        $xml = preg_replace('#<presentation:notes\b[^>]*>#', "\nSpeaker notes:\n", $xml);

        // Spacing elements, then paragraph and heading ends.
        $xml = preg_replace_callback('#<text:s\b([^>]*)/>#', function ($m) {
            $count = preg_match('#text:c="(\d+)"#', $m[1], $c) ? (int) $c[1] : 1;
            return str_repeat(' ', max(1, min($count, 100)));
        }, $xml);
        $xml = preg_replace('#<text:tab\b[^>]*/>#', "\t", $xml);
        $xml = preg_replace('#<text:line-break\b[^>]*/>#', "\n", $xml);
        $xml = preg_replace('#</text:(p|h)>#', "\n", $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", array_map('rtrim', explode("\n", $text))));
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Copy a stored_file to a temp path. Returns null on failure.
     *
     * @param \stored_file $file Source stored file (the submitted document).
     * @return string|null Temp filesystem path, or null on copy failure.
     */
    private static function copy_to_temp(\stored_file $file): ?string {
        try {
            $tmppath = tempnam(sys_get_temp_dir(), 'aigrader_odf_');
            $file->copy_content_to($tmppath);
            return $tmppath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
