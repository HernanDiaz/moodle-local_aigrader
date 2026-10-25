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
 * Tests for the .ipynb extractor, with emphasis on output truncation.
 *
 * The bug that motivated these tests: a Fashion-MNIST notebook with 50
 * epochs of verbose=1 training output produced 14k tokens, busting the
 * 12k TPM cap of Groq's free-tier llama-3.3-70b-versatile model. The
 * truncation logic added in this commit caps each stream output at
 * MAX_OUTPUT_LINES / MAX_OUTPUT_CHARS and the whole notebook at
 * MAX_TOTAL_CHARS, so the same notebook would now fit comfortably.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Tests for the .ipynb extractor and its output-truncation behaviour.
 *
 * @covers \local_aigrader\extractor\ipynb_extractor
 */
final class ipynb_extractor_test extends \advanced_testcase {
    /**
     * Build a minimal valid notebook JSON given a list of cells.
     *
     * @param array $cells Cell dicts as the ipynb spec defines them.
     * @param string $language Kernel language (default python).
     * @return string Notebook JSON.
     */
    private function notebook(array $cells, string $language = 'python'): string {
        return json_encode([
            'cells' => $cells,
            'metadata' => [
                'kernelspec' => ['language' => $language],
            ],
            'nbformat' => 4,
            'nbformat_minor' => 5,
        ]);
    }

    /**
     * Build a Keras-like verbose=1 training log with N epochs and B batches
     * per epoch. Each line is ~100 chars, so 50 epochs * 1875 batches
     * gives ~10MB of stream output — exactly the shape that hit the TPM
     * limit on the original pilot submission.
     *
     * @param int $epochs
     * @param int $batchesperepoch
     * @return string Multi-line stream text.
     */
    private function fake_keras_log(int $epochs, int $batchesperepoch): string {
        $lines = [];
        for ($e = 1; $e <= $epochs; $e++) {
            for ($b = 1; $b <= $batchesperepoch; $b++) {
                $lines[] = sprintf(
                    'Epoch %d/%d %d/%d [%s>] - loss: 0.%04d - accuracy: 0.%04d',
                    $e,
                    $epochs,
                    $b,
                    $batchesperepoch,
                    str_repeat('=', min(30, $b)),
                    (int) (1000 - ($e * 15 + $b * 0.001)),
                    (int) (800 + ($e * 4))
                );
            }
        }
        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------.
    // Basic extraction
    // -------------------------------------------------------------------.

    public function test_markdown_cell_is_extracted_as_text(): void {
        $raw = $this->notebook([
            [
                'cell_type' => 'markdown',
                'source'    => "# Title\n\nSome paragraph.",
                'metadata'  => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertNotNull($out);
        $this->assertStringContainsString('# Title', $out);
        $this->assertStringContainsString('Some paragraph.', $out);
        $this->assertStringContainsString('(markdown)', $out);
    }

    public function test_code_cell_is_extracted_with_language_marker(): void {
        $raw = $this->notebook([
            [
                'cell_type'      => 'code',
                'source'         => 'import numpy as np',
                'execution_count' => 1,
                'outputs'        => [],
                'metadata'       => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertStringContainsString('(code, python)', $out);
        $this->assertStringContainsString('import numpy as np', $out);
    }

    public function test_images_are_omitted_with_placeholder(): void {
        $raw = $this->notebook([
            [
                'cell_type' => 'code',
                'source'    => 'plt.imshow(x)',
                'execution_count' => 1,
                'outputs' => [
                    [
                        'output_type' => 'display_data',
                        'data' => [
                            'image/png' => 'aGVsbG8=', // Pretend base64 PNG.
                        ],
                        'metadata' => [],
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertStringContainsString('[Image / rich output omitted]', $out);
        $this->assertStringNotContainsString('aGVsbG8=', $out, 'image bytes must not leak through');
    }

    // -------------------------------------------------------------------.
    // Notebooks without saved outputs
    // -------------------------------------------------------------------.

    public function test_code_without_any_output_lacks_outputs(): void {
        $raw = $this->notebook([
            ['cell_type' => 'markdown', 'source' => '# Results', 'metadata' => []],
            ['cell_type' => 'code', 'source' => 'model.fit(X, y)', 'execution_count' => null, 'outputs' => [], 'metadata' => []],
            ['cell_type' => 'code', 'source' => 'print(acc)', 'execution_count' => null, 'outputs' => [], 'metadata' => []],
        ]);

        $this->assertTrue(ipynb_extractor::lacks_outputs($raw));
    }

    public function test_one_output_is_enough(): void {
        $raw = $this->notebook([
            ['cell_type' => 'code', 'source' => 'import numpy as np', 'execution_count' => 1, 'outputs' => [], 'metadata' => []],
            [
                'cell_type' => 'code',
                'source' => 'print(acc)',
                'execution_count' => 2,
                'outputs' => [['output_type' => 'stream', 'name' => 'stdout', 'text' => '0.91']],
                'metadata' => [],
            ],
        ]);

        $this->assertFalse(ipynb_extractor::lacks_outputs($raw));
    }

    public function test_notebooks_without_code_do_not_lack_outputs(): void {
        $markdownonly = $this->notebook([['cell_type' => 'markdown', 'source' => 'Only text.', 'metadata' => []]]);
        $emptycode = $this->notebook([['cell_type' => 'code', 'source' => "  \n", 'outputs' => [], 'metadata' => []]]);

        $this->assertFalse(ipynb_extractor::lacks_outputs($markdownonly));
        $this->assertFalse(ipynb_extractor::lacks_outputs($emptycode));
        $this->assertFalse(ipynb_extractor::lacks_outputs('{ this is not json'));
    }

    /**
     * Through the dispatcher, a notebook without outputs reaches the prompt with
     * a note for the AI and leaves a warning for the teacher; a notebook that was
     * run does not.
     *
     * @covers \local_aigrader\extractor\dispatcher::extract
     */
    public function test_dispatcher_flags_notebook_without_outputs(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);
        $submission = (new \assign($context, null, null))->get_user_submission($student->id, true);
        $code = ['cell_type' => 'code', 'source' => 'model.fit(X, y)', 'execution_count' => null, 'outputs' => [], 'metadata' => []];
        $file = [
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea'  => 'submission_files',
            'itemid'    => $submission->id,
            'filepath'  => '/',
            'filename'  => 'lab.ipynb',
        ];
        $fs = get_file_storage();
        $stored = $fs->create_file_from_string($file, $this->notebook([$code]));

        $result = dispatcher::extract((int) $submission->id);
        $this->assertTrue($result->is_ok());
        $this->assertStringContainsString(ipynb_extractor::NO_OUTPUTS_NOTE, $result->text);
        $this->assertContains(get_string('extract_ipynb_no_outputs', 'local_aigrader', 'lab.ipynb'), $result->warnings);

        $stored->delete();
        $code['outputs'] = [['output_type' => 'stream', 'name' => 'stdout', 'text' => 'Epoch 1/5']];
        $fs->create_file_from_string($file, $this->notebook([$code]));

        $result = dispatcher::extract((int) $submission->id);
        $this->assertStringNotContainsString(ipynb_extractor::NO_OUTPUTS_NOTE, $result->text);
        $this->assertSame([], $result->warnings);
    }

    public function test_invalid_json_returns_null(): void {
        $this->assertNull(ipynb_extractor::extract_text('{ this is not json'));
    }

    public function test_empty_string_returns_null(): void {
        $this->assertNull(ipynb_extractor::extract_text(''));
    }

    public function test_json_without_cells_returns_null(): void {
        $this->assertNull(ipynb_extractor::extract_text('{"metadata":{}}'));
    }

    // -------------------------------------------------------------------.
    // The headline behaviour: output truncation
    // -------------------------------------------------------------------.

    /**
     * The whole reason this test class exists. A long stream output must
     * be replaced with head + marker + tail so the LLM still sees the
     * first and last epochs but not 50 * 1875 batch lines.
     */
    public function test_long_stream_output_is_truncated_with_head_and_tail(): void {
        $log = $this->fake_keras_log(50, 100); // 5000 lines, well over the cap.
        $raw = $this->notebook([
            [
                'cell_type' => 'code',
                'source'    => 'model.fit(X, y, epochs=50)',
                'execution_count' => 1,
                'outputs' => [
                    [
                        'output_type' => 'stream',
                        'name'        => 'stdout',
                        'text'        => $log,
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertNotNull($out);
        $this->assertStringContainsString('lines /', $out, 'should include truncation marker');
        $this->assertStringContainsString('truncated', $out);

        // First epoch should still be visible (head preserved).
        $this->assertStringContainsString('Epoch 1/50', $out);

        // Last epoch should still be visible (tail preserved).
        $this->assertStringContainsString('Epoch 50/50', $out);

        // The middle epochs should be gone.
        $this->assertStringNotContainsString('Epoch 25/50', $out, 'mid-range epochs should be dropped');
    }

    /**
     * Short outputs (below the cap) must pass through unchanged so we do
     * not accidentally degrade quality for well-behaved notebooks.
     */
    public function test_short_output_passes_through_unchanged(): void {
        $short = "Loaded 60000 samples.\nValidation accuracy: 0.9123";
        $raw = $this->notebook([
            [
                'cell_type'       => 'code',
                'source'          => 'print(model.evaluate(X_test, y_test))',
                'execution_count' => 1,
                'outputs' => [
                    [
                        'output_type' => 'stream',
                        'name'        => 'stdout',
                        'text'        => $short,
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertStringContainsString('Loaded 60000 samples.', $out);
        $this->assertStringContainsString('Validation accuracy: 0.9123', $out);
        $this->assertStringNotContainsString('truncated', $out);
    }

    /**
     * A single pathologically wide line (e.g. a printed numpy array with
     * no newlines) must still be capped on chars, not lines.
     */
    public function test_single_wide_line_is_capped_on_chars(): void {
        $widearray = str_repeat('1, 2, 3, 4, 5, ', 1000); // ~15k chars on one line.
        $raw = $this->notebook([
            [
                'cell_type'       => 'code',
                'source'          => 'print(np.arange(5000))',
                'execution_count' => 1,
                'outputs' => [
                    [
                        'output_type' => 'execute_result',
                        'data'        => ['text/plain' => $widearray],
                        'metadata'    => [],
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        // Should be capped well below the input length.
        $this->assertLessThan(15000, strlen($out));
        $this->assertStringContainsString('truncated', $out);
    }

    /**
     * Error tracebacks are intentionally NOT truncated by the per-output
     * cap because they are short and pedagogically important. They are
     * still subject to the whole-notebook cap if everything else is huge.
     */
    public function test_error_traceback_is_preserved_with_ansi_stripped(): void {
        $raw = $this->notebook([
            [
                'cell_type'       => 'code',
                'source'          => 'x = 1 / 0',
                'execution_count' => 1,
                'outputs' => [
                    [
                        'output_type' => 'error',
                        'ename'       => 'ZeroDivisionError',
                        'evalue'      => 'division by zero',
                        'traceback' => [
                            "\x1b[0;31m---------------------------------------------------------------------------\x1b[0m",
                            "\x1b[0;31mZeroDivisionError\x1b[0m: division by zero",
                        ],
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertStringContainsString('ZeroDivisionError', $out);
        $this->assertStringContainsString('division by zero', $out);
        // ANSI sequences must be stripped.
        $this->assertStringNotContainsString("\x1b[", $out);
    }

    // -------------------------------------------------------------------.
    // Whole-notebook cap
    // -------------------------------------------------------------------.

    /**
     * If a notebook has thousands of small-output cells, the per-output
     * cap alone would not save us — the safety net is the whole-notebook
     * char cap. Verify it fires and emits a clear marker.
     */
    public function test_pathological_many_cells_hits_total_cap(): void {
        // Build 1000 code cells each with a small stream output. Per-cell
        // cap leaves each output alone (small), but the sum is huge.
        $cells = [];
        for ($i = 0; $i < 1000; $i++) {
            $cells[] = [
                'cell_type'       => 'code',
                'source'          => 'x = ' . $i,
                'execution_count' => $i,
                'outputs' => [
                    [
                        'output_type' => 'stream',
                        'name'        => 'stdout',
                        'text'        => 'A line of about fifty characters each, repeated many times.',
                    ],
                ],
                'metadata' => [],
            ];
        }
        $raw = $this->notebook($cells);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertNotNull($out);
        $this->assertLessThanOrEqual(40000, strlen($out), 'must respect the whole-notebook cap');
        $this->assertStringContainsString('notebook truncated for length', $out);
    }

    /**
     * End-to-end regression: a notebook shaped like a pilot Fashion-MNIST
     * submission (50 epochs * 1875 batches verbose=1) used to extract to
     * ~14k tokens. With truncation it must drop well below that, in fact
     * well within a 10k-token budget for the prompt body.
     */
    public function test_fashion_mnist_shaped_notebook_fits_under_budget(): void {
        $raw = $this->notebook([
            [
                'cell_type' => 'markdown',
                'source'    => '# Práctica Fashion-MNIST',
                'metadata'  => [],
            ],
            [
                'cell_type'       => 'code',
                'source'          => "import tensorflow as tf\nfrom tensorflow.keras import layers",
                'execution_count' => 1,
                'outputs'         => [],
                'metadata'        => [],
            ],
            [
                'cell_type'       => 'code',
                'source'          => 'model.fit(X_train, y_train, epochs=50, validation_split=0.1)',
                'execution_count' => 2,
                'outputs' => [
                    [
                        'output_type' => 'stream',
                        'name'        => 'stdout',
                        'text'        => $this->fake_keras_log(50, 1875),
                    ],
                ],
                'metadata' => [],
            ],
            [
                'cell_type'       => 'code',
                'source'          => 'print(model.evaluate(X_test, y_test))',
                'execution_count' => 3,
                'outputs' => [
                    [
                        'output_type' => 'stream',
                        'name'        => 'stdout',
                        'text'        => "loss: 0.245, accuracy: 0.912\n",
                    ],
                ],
                'metadata' => [],
            ],
        ]);

        $out = ipynb_extractor::extract_text($raw);

        $this->assertNotNull($out);
        // 1 char ≈ 0.25 tokens for typical text → 40k chars ≈ 10k tokens.
        // A realistic notebook of this shape should be well under that.
        $this->assertLessThanOrEqual(40000, strlen($out));

        // The conclusion line (final accuracy on test set) must survive —
        // it is the most pedagogically important line in the whole log.
        $this->assertStringContainsString('accuracy: 0.912', $out);

        // The student's code is still visible.
        $this->assertStringContainsString('model.fit', $out);
        $this->assertStringContainsString('Fashion-MNIST', $out);
    }
}
