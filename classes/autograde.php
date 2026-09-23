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
 * Automatic AI grading when a student submits.
 *
 * When the teacher ticks "Grade automatically when a student submits" on an
 * assignment, each submission queues the same background grading task that
 * the manage page uses, so the proposal is waiting when the teacher opens
 * the panel. Only the proposal is automatic: the teacher still reviews and
 * publishes every grade.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autograde {
    /** Statuses holding a teacher's decision, which a new submission must not replace. */
    private const TEACHER_STATUSES = ['teacher_reviewed', 'published'];

    /**
     * Queue AI grading for a submission if its assignment asks for it.
     *
     * @param int $submissionid The {assign_submission}.id that was submitted.
     * @return bool True if a grading task was queued.
     */
    public static function queue_for_submission(int $submissionid): bool {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
        if (!$submission) {
            return false;
        }
        $config = $DB->get_record('local_aigrader_assign', ['assignid' => $submission->assignment]);
        if (!$config || empty($config->enabled) || empty($config->autograde) || trim((string) $config->criteria_text) === '') {
            return false;
        }
        [$course, $cm] = get_course_and_cm_from_instance((int) $submission->assignment, 'assign');
        if (!availability::is_available_in_course($course)) {
            return false;
        }

        $existing = $DB->get_record('local_aigrader_submission', ['submissionid' => $submissionid]);
        if ($existing && in_array($existing->status, self::TEACHER_STATUSES, true)) {
            return false;
        }

        // Show the submission as pending in the teacher's panel right away.
        $now = time();
        if ($existing) {
            $DB->update_record('local_aigrader_submission', (object) [
                'id'            => $existing->id,
                'status'        => 'pending_ai',
                'error_message' => null,
                'timemodified'  => $now,
            ]);
        } else {
            $DB->insert_record('local_aigrader_submission', (object) [
                'submissionid' => $submissionid,
                'assignid'     => (int) $submission->assignment,
                'courseid'     => (int) $course->id,
                'studentid'    => (int) $submission->userid,
                'status'       => 'pending_ai',
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        $task = new task\grade_submission();
        $task->set_custom_data((object) ['submissionid' => $submissionid]);
        $task->set_userid(self::grading_user_id($config, \context_module::instance($cm->id)));
        // One queued task per submission, even if the student saves repeatedly.
        \core\task\manager::queue_adhoc_task($task, true);
        return true;
    }

    /**
     * The user the automatic grading runs as: the teacher who turned it on
     * for the assignment, or the site administrator if that teacher can no
     * longer use AI Grader Pro there. The AI Subsystem records this user
     * and applies its rate limits to them.
     *
     * @param \stdClass $config Row of {local_aigrader_assign}.
     * @param \context_module $context The assignment's context.
     * @return int User id.
     */
    private static function grading_user_id(\stdClass $config, \context_module $context): int {
        $teacher = \core_user::get_user((int) $config->usermodified);
        if (
            $teacher && empty($teacher->deleted) && empty($teacher->suspended)
            && has_capability('local/aigrader:use', $context, $teacher)
        ) {
            return (int) $teacher->id;
        }
        return (int) get_admin()->id;
    }
}
