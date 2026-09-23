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
 * Tests for the bulk dispatcher's classify() — the matrix of
 *
 *   action × current AI-grader status → eligible or skipped(reason).
 *
 * v1.0.6 simplified the action set: regrade_ai was merged into grade_ai
 * (the dispatcher figures out per row whether it's a first grade or a
 * re-grade) and mark_manual was removed entirely (its bulk semantics
 * were unclear; the single-row "Rechazar" button on review.php remains
 * for that decision). These tests reflect the reduced matrix.
 *
 * classify() is pure; execute() is a side-effecting orchestrator best
 * tested via higher-level Behat scenarios against a real fixture course.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\bulk;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for \local_aigrader\bulk\dispatcher::classify().
 *
 * @covers \local_aigrader\bulk\dispatcher
 */
final class bulk_dispatcher_test extends \basic_testcase {
    /**
     * Tiny helper: build the minimal row shape that classify() reads.
     *
     * @param string|null $status One of the local_aigrader_submission status values, or null.
     * @param mixed $proposedgrade Optional AI-proposed grade.
     * @return object Row stdClass with submissionid, ai_status, proposed_grade.
     */
    private function row(?string $status, $proposedgrade = null): object {
        return (object) [
            'submissionid'   => 42,
            'studentid'      => 7,
            'aigrader_id'    => 1,
            'ai_status'      => $status,
            'proposed_grade' => $proposedgrade,
        ];
    }

    // ===================================================================.
    // ACTION_APPROVE_PUBLISH — needs a usable proposal.
    // ===================================================================.

    public function test_approve_publish_runs_on_ai_proposed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('ai_proposed', 7.5))
        );
    }

    public function test_approve_publish_runs_on_teacher_reviewed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('teacher_reviewed', 7.5))
        );
    }

    /**
     * With a rubric or marking guide active, publishable rows are skipped.
     */
    public function test_approve_publish_skips_under_advanced_grading(): void {
        foreach (['ai_proposed', 'teacher_reviewed'] as $status) {
            $this->assertSame(
                'skip:advanced_grading',
                dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row($status, 7.5), true)
            );
        }
        // Grading with AI is unaffected: it never writes the gradebook.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('ai_proposed', 7.5), true)
        );
    }

    public function test_approve_publish_skips_already_published(): void {
        $this->assertSame(
            'skip:already_published',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('published', 8.0))
        );
    }

    public function test_approve_publish_skips_pending(): void {
        $this->assertSame(
            'skip:in_flight',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('pending_ai'))
        );
    }

    public function test_approve_publish_skips_unsupported_format(): void {
        $this->assertSame(
            'skip:unsupported',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('unsupported_format'))
        );
    }

    public function test_approve_publish_skips_error(): void {
        $this->assertSame(
            'skip:no_proposal',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('error'))
        );
    }

    public function test_approve_publish_skips_null_status(): void {
        $this->assertSame(
            'skip:no_proposal',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row(null))
        );
    }

    // ===================================================================.
    // ACTION_GRADE_AI — unified first-grade + re-grade in one action.
    //
    // The dispatcher accepts any state EXCEPT pending_ai (don't double-
    // queue a task that's still running) and unsupported_format (re-running
    // the LLM cannot recover from a file we couldn't parse — the teacher
    // has to upload a parseable version first). Everything else, including
    // published, is eligible: re-grading a published row produces a new
    // proposal sitting in ai_proposed; the gradebook value stays put until
    // the teacher explicitly publishes the new proposal.
    // ===================================================================.

    public function test_grade_ai_runs_on_null_status(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row(null))
        );
    }

    public function test_grade_ai_runs_on_error(): void {
        // Errors are recoverable: re-run is the natural retry path.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('error'))
        );
    }

    public function test_grade_ai_runs_on_ai_proposed(): void {
        // v1.0.6 — previously skipped as 'already_proposed' (with
        // regrade_ai being the separate action). Now eligible.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('ai_proposed', 7.0))
        );
    }

    public function test_grade_ai_runs_on_teacher_reviewed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('teacher_reviewed', 7.0))
        );
    }

    public function test_grade_ai_runs_on_published(): void {
        // Re-grading a published row builds a fresh proposal but DOES NOT
        // touch the gradebook. The teacher has to explicitly publish the
        // new proposal to overwrite the live grade.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('published', 8.0))
        );
    }

    public function test_grade_ai_skips_pending(): void {
        $this->assertSame(
            'skip:in_flight',
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('pending_ai'))
        );
    }

    public function test_grade_ai_skips_unsupported(): void {
        $this->assertSame(
            'skip:unsupported',
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('unsupported_format'))
        );
    }

    // ===================================================================.
    // Bad inputs.
    // ===================================================================.

    public function test_unknown_action_is_classified_as_unknown(): void {
        $this->assertSame(
            'skip:unknown_action',
            dispatcher::classify('not_a_real_action', $this->row('ai_proposed', 7.0))
        );
    }

    public function test_removed_actions_are_classified_as_unknown(): void {
        // v1.0.5 actions that were dropped in v1.0.6 should now be rejected
        // by classify() (and by the bulk.php validation layer, which is the
        // first line of defense). This is the regression test for that
        // removal — a stale browser tab or bookmarked URL submitting one of
        // these values must not silently take the grade_ai path.
        foreach (['regrade_ai', 'mark_manual'] as $removed) {
            $this->assertSame(
                'skip:unknown_action',
                dispatcher::classify($removed, $this->row('ai_proposed', 7.0)),
                "Removed action '$removed' must not be silently accepted."
            );
        }
    }

    // ===================================================================.
    // Action list / destructiveness contract — UI relies on this.
    // ===================================================================.

    public function test_action_list_is_minimal(): void {
        // Two actions in v1.0.6: publish + grade. Adding more here is a
        // deliberate UX decision; if a future change wants a third action,
        // it should pair with a test addition.
        $this->assertSame(['approve_publish', 'grade_ai'], dispatcher::ALL_ACTIONS);
    }

    public function test_approve_publish_is_destructive(): void {
        $this->assertContains(
            dispatcher::ACTION_APPROVE_PUBLISH,
            dispatcher::DESTRUCTIVE_ACTIONS,
            'approve_publish must require confirmation — it writes to the gradebook.'
        );
    }

    public function test_grade_ai_is_not_destructive(): void {
        // grade_ai never writes to the gradebook; it only proposes a value
        // in local_aigrader_submission. The confirmation step is reserved
        // for actions that change the live student grade.
        $this->assertNotContains(
            dispatcher::ACTION_GRADE_AI,
            dispatcher::DESTRUCTIVE_ACTIONS,
            'grade_ai does not write final grades to the gradebook (only proposes).'
        );
    }
}
