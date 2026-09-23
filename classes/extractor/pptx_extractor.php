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
 * Extractor for .pptx files (PowerPoint 2007+).
 *
 * A .pptx is a ZIP archive with one XML part per slide. The slide order is
 * the one listed in ppt/presentation.xml, which is not necessarily the order
 * of the file names (slides keep their file name when moved), so we follow
 * the presentation's relationship list and fall back to the file names only
 * when it cannot be read. Speaker notes are included after each slide: they
 * often carry what the student would say, which matters for grading.
 *
 * Like docx_extractor this works on the raw XML without external libraries:
 * text only, no images, charts or SmartArt drawings.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Class pptx_extractor.
 */
class pptx_extractor {
    /** @var int Largest XML part read, uncompressed (guards against zip bombs). */
    private const MAX_PART_BYTES = 10485760;

    /** @var int Most slides read from one presentation. */
    private const MAX_SLIDES = 500;

    /**
     * Extract plain text from a .pptx file.
     *
     * @param \stored_file $file The presentation uploaded by the student.
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
     * Extract plain text from a .pptx file on disk.
     *
     * @param string $path Filesystem path of the presentation.
     * @return string|null Slides as "--- Slide N ---" blocks, or null on failure / empty.
     */
    public static function extract_path(string $path): ?string {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }

        $blocks = [];
        foreach (array_slice(self::slide_paths($zip), 0, self::MAX_SLIDES) as $index => $slidepath) {
            $xml = self::read_part($zip, $slidepath);
            $slidetext = $xml !== null ? self::xml_to_text($xml) : '';

            $notestext = '';
            $notespath = self::notes_path($zip, $slidepath);
            if ($notespath !== null) {
                $notesxml = self::read_part($zip, $notespath);
                $notestext = $notesxml !== null ? self::xml_to_text($notesxml) : '';
            }

            if ($slidetext === '' && $notestext === '') {
                continue;
            }
            $block = '--- Slide ' . ($index + 1) . " ---\n" . $slidetext;
            if ($notestext !== '') {
                $block .= "\nSpeaker notes:\n" . $notestext;
            }
            $blocks[] = trim($block);
        }
        $zip->close();

        $text = trim(implode("\n\n", $blocks));
        return $text === '' ? null : $text;
    }

    /**
     * Paths of the slide parts in presentation order.
     *
     * @param \ZipArchive $zip Open presentation.
     * @return string[] Zip entry names, e.g. "ppt/slides/slide3.xml".
     */
    private static function slide_paths(\ZipArchive $zip): array {
        $presentation = self::read_part($zip, 'ppt/presentation.xml');
        $rels = self::relationships($zip, 'ppt/presentation.xml');
        $paths = [];
        if ($presentation !== null && preg_match_all('#<p:sldId\b[^>]*\br:id="([^"]+)"#', $presentation, $matches)) {
            foreach ($matches[1] as $rid) {
                if (isset($rels[$rid]) && $zip->locateName($rels[$rid]['target']) !== false) {
                    $paths[] = $rels[$rid]['target'];
                }
            }
        }
        if ($paths) {
            return $paths;
        }

        // Fallback: every slide part, by the number in its file name.
        $numbered = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $numbered[(int) $m[1]] = $name;
            }
        }
        ksort($numbered);
        return array_values($numbered);
    }

    /**
     * Path of the speaker-notes part linked from a slide, if any.
     *
     * @param \ZipArchive $zip Open presentation.
     * @param string $slidepath Zip entry name of the slide.
     * @return string|null Zip entry name of its notes part.
     */
    private static function notes_path(\ZipArchive $zip, string $slidepath): ?string {
        foreach (self::relationships($zip, $slidepath) as $rel) {
            if (str_ends_with($rel['type'], '/notesSlide') && $zip->locateName($rel['target']) !== false) {
                return $rel['target'];
            }
        }
        return null;
    }

    /**
     * Relationships of a part, from its "_rels/<name>.rels" file.
     *
     * @param \ZipArchive $zip Open presentation.
     * @param string $partpath Zip entry name of the part that owns the relationships.
     * @return array<string, array{target: string, type: string}> Keyed by relationship id; targets resolved to entry names.
     */
    private static function relationships(\ZipArchive $zip, string $partpath): array {
        $dir = dirname($partpath);
        $xml = self::read_part($zip, $dir . '/_rels/' . basename($partpath) . '.rels');
        if ($xml === null || !preg_match_all('#<Relationship\b[^>]*>#', $xml, $matches)) {
            return [];
        }
        $rels = [];
        foreach ($matches[0] as $tag) {
            $id = preg_match('#\bId="([^"]+)"#', $tag, $m) ? $m[1] : '';
            $target = preg_match('#\bTarget="([^"]+)"#', $tag, $m) ? $m[1] : '';
            $type = preg_match('#\bType="([^"]+)"#', $tag, $m) ? $m[1] : '';
            if ($id === '' || $target === '' || str_contains($tag, 'TargetMode="External"')) {
                continue;
            }
            $rels[$id] = ['target' => self::resolve($dir, $target), 'type' => $type];
        }
        return $rels;
    }

    /**
     * Resolve a relationship target against the directory of its part.
     *
     * @param string $dir Directory of the part, e.g. "ppt/slides".
     * @param string $target Target as written, e.g. "../notesSlides/notesSlide1.xml".
     * @return string Zip entry name, e.g. "ppt/notesSlides/notesSlide1.xml".
     */
    private static function resolve(string $dir, string $target): string {
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : $dir . '/' . $target;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } else if ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        return implode('/', $segments);
    }

    /**
     * Read one XML part, refusing parts that expand beyond MAX_PART_BYTES.
     *
     * @param \ZipArchive $zip Open presentation.
     * @param string $name Zip entry name.
     * @return string|null The XML, or null when missing or too large.
     */
    private static function read_part(\ZipArchive $zip, string $name): ?string {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MAX_PART_BYTES) {
            return null;
        }
        $xml = $zip->getFromName($name);
        return $xml === false ? null : $xml;
    }

    /**
     * Convert DrawingML slide or notes XML to plain text.
     *
     * @param string $xml Raw XML of a slide or notes part.
     * @return string Plain text, possibly empty.
     */
    private static function xml_to_text(string $xml): string {
        // Fields hold slide numbers and dates, not the student's text.
        $xml = preg_replace('#<a:fld\b[^>]*>.*?</a:fld>#s', '', $xml);
        $xml = preg_replace('#</a:p>#', "\n", $xml);
        $xml = preg_replace('#<a:br\b[^>]*/>#', "\n", $xml);
        $xml = preg_replace('#<a:tab\b[^>]*/>#', "\t", $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8');
        $lines = array_filter(array_map('rtrim', explode("\n", $text)), fn($line) => trim($line) !== '');
        return trim(implode("\n", $lines));
    }

    /**
     * Copy a stored_file to a temp path. Returns null on failure.
     *
     * @param \stored_file $file Source stored file (the submitted pptx).
     * @return string|null Temp filesystem path, or null on copy failure.
     */
    private static function copy_to_temp(\stored_file $file): ?string {
        try {
            $tmppath = tempnam(sys_get_temp_dir(), 'aigrader_pptx_');
            $file->copy_content_to($tmppath);
            return $tmppath;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
