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
 * Extractor for .zip archives.
 *
 * Unzips to a temp directory, walks the tree, skips noise directories and
 * binary extensions, and dispatches each remaining file to the appropriate
 * extractor (code/.docx/.ipynb/.txt). Combines results with path-aware
 * headers so the LLM can see the project structure.
 *
 * Limits (from ADR-001 section 3.9):
 *   - Max 100 files inside the zip.
 *   - Max 1 MB per individual file.
 *   - Max 60 seconds total extraction time.
 *   - Tree depth ignored (we just check directory blacklist).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;
/**
 * Class zip_extractor.
 */
class zip_extractor {
    /** @var int Max files we will process from a zip. */
    private const MAX_FILES_IN_ZIP   = 100;
    /** @var int Max size in bytes per individual file inside the zip. */
    private const MAX_FILE_SIZE      = 1048576;
    /** @var int Total wall-clock seconds we will spend on one zip. */
    private const TIME_BUDGET_SECS   = 60;
    /** @var int Max size in bytes of an office document inside the zip (they carry images). */
    private const MAX_OFFICE_FILE_SIZE = 20971520;

    /** Office documents read through their own extractor, with their label. */
    private const OFFICE_EXTENSIONS = [
        'docx' => 'Word document',
        'pptx' => 'PowerPoint presentation',
        'odt'  => 'OpenDocument text',
        'odp'  => 'OpenDocument presentation',
    ];

    /** Directories whose contents we always skip. */
    private const SKIP_DIR_FRAGMENTS = [
        '__pycache__/', '.git/', '.svn/', 'node_modules/', 'venv/', '.venv/',
        'target/', 'build/', 'dist/', 'out/', 'bin/', 'obj/',
        '.idea/', '.vscode/', '.mypy_cache/', '.pytest_cache/', '.DS_Store',
    ];

    /** File extensions we always skip (binaries / images / PDFs). */
    private const SKIP_EXTENSIONS = [
        'pyc', 'class', 'o', 'so', 'exe', 'dll', 'jar', 'war',
        'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg',
        'pdf', 'doc', 'xls', 'ppt',
        'mp3', 'mp4', 'mov', 'wav',
        'zip', // No nested zips in v0.11 — handled by outer dispatcher.
    ];

    /**
     * Extract content from a zip archive.
     *
     * @param \stored_file $file The zip uploaded by the student.
     * @return array{text: string, warnings: string[]} Text combines all
     *         file contents with --- headers; warnings list what was skipped.
     */
    public static function extract_file(\stored_file $file): array {
        $tmppath = self::copy_to_temp($file);
        if ($tmppath === null) {
            return ['text' => '', 'warnings' => ['Could not copy zip to temp']];
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmppath) !== true) {
            @unlink($tmppath);
            return ['text' => '', 'warnings' => ['Could not open zip file']];
        }

        $startts  = microtime(true);
        $parts    = [];
        $warnings = [];
        $processed = 0;
        $skipped   = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ((microtime(true) - $startts) > self::TIME_BUDGET_SECS) {
                $warnings[] = 'Time budget exceeded after ' . $processed . ' files; stopping.';
                break;
            }
            if ($processed >= self::MAX_FILES_IN_ZIP) {
                $warnings[] = 'File budget reached (' . self::MAX_FILES_IN_ZIP . '); '
                            . ($zip->numFiles - $i) . ' more files skipped.';
                break;
            }

            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];

            // Skip directories themselves.
            if (substr($name, -1) === '/') {
                continue;
            }
            // Skip files inside blacklisted directories.
            foreach (self::SKIP_DIR_FRAGMENTS as $frag) {
                if (str_contains($name, $frag)) {
                    $skipped++;
                    continue 2;
                }
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Office documents are zips themselves: read them with their extractor
            // instead of discarding them as binary.
            if (isset(self::OFFICE_EXTENSIONS[$ext])) {
                if ($stat['size'] > self::MAX_OFFICE_FILE_SIZE) {
                    $warnings[] = $name . ' skipped (size ' . $stat['size'] . ' > 20 MB)';
                    continue;
                }
                $text = self::extract_office_entry($zip, $i, $ext);
                if ($text === null) {
                    $warnings[] = $name . ' skipped (could not read the document)';
                    continue;
                }
                $parts[] = '--- ' . $name . ' (' . self::OFFICE_EXTENSIONS[$ext] . ') ---';
                $parts[] = $text;
                $parts[] = '';
                $processed++;
                continue;
            }

            if (in_array($ext, self::SKIP_EXTENSIONS, true)) {
                $skipped++;
                continue;
            }

            if ($stat['size'] > self::MAX_FILE_SIZE) {
                $warnings[] = $name . ' skipped (size ' . $stat['size'] . ' > 1 MB)';
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            // Heuristic: if the content has lots of NUL bytes, it's binary; skip.
            if (substr_count(substr($content, 0, 1024), "\0") > 4) {
                $skipped++;
                continue;
            }

            $label = self::label_for($name, $ext);
            $parts[] = '--- ' . $name . ' (' . $label . ') ---';
            $parts[] = self::normalise_encoding($content);
            $parts[] = '';
            $processed++;
        }

        $zip->close();
        @unlink($tmppath);

        if ($skipped > 0) {
            $warnings[] = $skipped . ' files skipped (noise directories or binary types)';
        }
        if ($processed === 0) {
            $warnings[] = 'No processable files found in the zip';
        }

        return [
            'text'     => trim(implode("\n", $parts)),
            'warnings' => $warnings,
        ];
    }

    /**
     * Extract the text of an office document stored inside the zip.
     *
     * @param \ZipArchive $zip The open outer zip.
     * @param int $index Index of the entry in the zip.
     * @param string $ext Lower-case extension, a key of OFFICE_EXTENSIONS.
     * @return string|null Extracted text, or null if the document could not be read.
     */
    private static function extract_office_entry(\ZipArchive $zip, int $index, string $ext): ?string {
        $content = $zip->getFromIndex($index);
        if ($content === false) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'aigrader_zipdoc_');
        if ($tmp === false || file_put_contents($tmp, $content) === false) {
            return null;
        }
        try {
            return match ($ext) {
                'docx' => docx_extractor::extract_path($tmp),
                'pptx' => pptx_extractor::extract_path($tmp),
                default => odf_extractor::extract_path($tmp),
            };
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Friendly label for a file inside the zip, based on its name and extension.
     *
     * @param string $name Entry name (filename inside the zip).
     * @param string $ext Lower-case extension without the dot (e.g. "py").
     * @return string Human label used as section header in the extracted text.
     */
    private static function label_for(string $name, string $ext): string {
        $codemap = [
            'py'   => 'Python', 'java' => 'Java', 'cpp' => 'C++', 'c' => 'C',
            'h'    => 'C header', 'hpp' => 'C++ header', 'cs' => 'C#',
            'js'   => 'JavaScript', 'ts' => 'TypeScript', 'sql' => 'SQL',
            'html' => 'HTML', 'css' => 'CSS', 'php' => 'PHP', 'rb' => 'Ruby',
            'go'   => 'Go', 'rs' => 'Rust', 'kt' => 'Kotlin', 'swift' => 'Swift',
            'json' => 'JSON', 'xml' => 'XML', 'yaml' => 'YAML', 'yml' => 'YAML',
            'sh'   => 'Shell script', 'bat' => 'Batch script',
            'md'   => 'Markdown', 'txt' => 'plain text',
            'ipynb' => 'Jupyter notebook (raw)',
        ];
        if (isset($codemap[$ext])) {
            return $codemap[$ext];
        }
        if (str_starts_with($name, 'README') || str_starts_with($name, 'readme')) {
            return 'README';
        }
        return 'text';
    }

    /**
     * Normalise file content to UTF-8 (best-effort).
     *
     * @param string $content Raw bytes read from a zip entry.
     * @return string UTF-8 string suitable for inclusion in the prompt.
     */
    private static function normalise_encoding(string $content): string {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        return $content;
    }

    /**
     * Copy a stored_file to a temp filesystem path so ZipArchive can open it.
     *
     * @param \stored_file $file Source stored file (the submitted zip).
     * @return string|null Temp path, or null on copy failure.
     */
    private static function copy_to_temp(\stored_file $file): ?string {
        try {
            $tmp = tempnam(sys_get_temp_dir(), 'aigrader_zip_');
            $file->copy_content_to($tmp);
            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
