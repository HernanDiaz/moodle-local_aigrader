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
 * Maps AI Grader Pro's internal 0-10 grades onto the grading setup of one
 * assignment: points out of a maximum, a Moodle scale, no grade, or an
 * advanced grading method (rubric / marking guide).
 *
 * The LLM always proposes on a fixed 0-10 scale, and the plugin stores both
 * the proposal and the teacher's decision on that same normalised scale in
 * local_aigrader_submission. This class converts to and from the scale the
 * assignment actually uses, both when showing grades to the teacher and when
 * publishing to the gradebook. Before v1.0.27 the 0-10 value was written to
 * the gradebook as-is, so an 8/10 proposal became 8/100 on a default
 * assignment.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * Grading configuration of one assignment, as seen by AI Grader Pro.
 */
final class grading_scale {
    /** Numeric grade out of a maximum (assign.grade > 0). */
    public const TYPE_POINT = 'point';

    /** Moodle scale such as "Fail, Pass, Merit" (assign.grade < 0). */
    public const TYPE_SCALE = 'scale';

    /** No grade, feedback only (assign.grade == 0). */
    public const TYPE_NONE = 'none';

    /** A rubric or marking guide is active; mod_assign computes the grade from it. */
    public const TYPE_ADVANCED = 'advanced';

    /** Upper bound of the scale the AI proposes on. */
    public const AI_MAX = 10.0;

    /** @var string One of the TYPE_* constants. */
    private string $type;

    /** @var float Maximum points (TYPE_POINT only). */
    private float $grademax;

    /** @var array Scale item labels keyed 1..n (TYPE_SCALE only). */
    private array $scaleitems;

    /** @var string Active advanced grading method, e.g. 'rubric' (TYPE_ADVANCED only). */
    private string $advancedmethod;

    /**
     * Use {@see self::for_assign()} or {@see self::from_values()}.
     *
     * @param string $type One of the TYPE_* constants.
     * @param float $grademax Maximum points (TYPE_POINT only).
     * @param array $scaleitems Scale item labels keyed 1..n (TYPE_SCALE only).
     * @param string $advancedmethod Active grading method (TYPE_ADVANCED only).
     */
    private function __construct(string $type, float $grademax, array $scaleitems, string $advancedmethod) {
        $this->type = $type;
        $this->grademax = $grademax;
        $this->scaleitems = $scaleitems;
        $this->advancedmethod = $advancedmethod;
    }

    /**
     * Read the grading configuration of an assignment.
     *
     * Mirrors the decision mod_assign itself takes in
     * assign::get_grading_instance(): an advanced grading method only counts
     * when its form is defined and available; otherwise mod_assign falls back
     * to the simple grade, and so do we.
     *
     * @param \stdClass $assign Row from the assign table.
     * @param \context_module $context Module context of the assignment.
     * @return self
     */
    public static function for_assign(\stdClass $assign, \context_module $context): self {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $manager = get_grading_manager($context, 'mod_assign', 'submissions');
        $method = $manager->get_active_method();
        if ($method) {
            $controller = $manager->get_controller($method);
            if ($controller->is_form_available()) {
                return new self(self::TYPE_ADVANCED, 0.0, [], $method);
            }
        }

        $grade = (int) $assign->grade;
        if ($grade > 0) {
            return new self(self::TYPE_POINT, (float) $grade, [], '');
        }
        if ($grade < 0) {
            $scale = $DB->get_record('scale', ['id' => -$grade], 'id, scale');
            $items = $scale ? make_menu_from_list($scale->scale) : [];
            if ($items) {
                return new self(self::TYPE_SCALE, 0.0, $items, '');
            }
        }
        // Grade type "None", or a scale that no longer exists.
        return new self(self::TYPE_NONE, 0.0, [], '');
    }

    /**
     * Build an instance from explicit values. Intended for unit tests.
     *
     * @param string $type One of the TYPE_* constants.
     * @param float $grademax Maximum points (TYPE_POINT only).
     * @param array $scaleitems Scale item labels (TYPE_SCALE only); re-keyed 1..n.
     * @param string $advancedmethod Active grading method (TYPE_ADVANCED only).
     * @return self
     */
    public static function from_values(
        string $type,
        float $grademax = 0.0,
        array $scaleitems = [],
        string $advancedmethod = ''
    ): self {
        $items = [];
        $i = 1;
        foreach ($scaleitems as $label) {
            $items[$i++] = (string) $label;
        }
        return new self($type, $grademax, $items, $advancedmethod);
    }

    /**
     * Type of grading used by the assignment.
     *
     * @return string One of the TYPE_* constants.
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Whether "Approve and publish" can run for this assignment.
     *
     * With a rubric or marking guide active, mod_assign expects the filled-in
     * grading form and cannot accept a plain grade, so publishing from AI
     * Grader Pro is not possible (yet).
     *
     * @return bool
     */
    public function can_publish(): bool {
        return $this->type !== self::TYPE_ADVANCED;
    }

    /**
     * Whether publishing writes a grade (as opposed to feedback only).
     *
     * @return bool
     */
    public function publishes_grade(): bool {
        return $this->type === self::TYPE_POINT || $this->type === self::TYPE_SCALE;
    }

    /**
     * Maximum points of a TYPE_POINT assignment.
     *
     * @return float
     */
    public function get_grademax(): float {
        return $this->grademax;
    }

    /**
     * Scale item labels of a TYPE_SCALE assignment, keyed 1..n.
     *
     * @return array
     */
    public function get_scale_items(): array {
        return $this->scaleitems;
    }

    /**
     * Frankenstyle-less name of the active advanced grading method, e.g. 'rubric'.
     *
     * @return string Empty unless the type is TYPE_ADVANCED.
     */
    public function get_advanced_method(): string {
        return $this->advancedmethod;
    }

    /**
     * Convert a normalised 0-10 grade into the value mod_assign stores.
     *
     * @param float $normalized Grade on the 0-10 scale (clamped).
     * @return float|null Points, scale item index, or null when no grade applies.
     */
    public function to_assign_grade(float $normalized): ?float {
        $normalized = max(0.0, min(self::AI_MAX, $normalized));
        switch ($this->type) {
            case self::TYPE_POINT:
                return round($normalized / self::AI_MAX * $this->grademax, 2);
            case self::TYPE_SCALE:
                $count = count($this->scaleitems);
                if ($count <= 1) {
                    return 1.0;
                }
                return (float) (1 + (int) round($normalized / self::AI_MAX * ($count - 1)));
            default:
                return null;
        }
    }

    /**
     * Convert a value on the assignment's scale back to the normalised 0-10 scale.
     *
     * For TYPE_NONE and TYPE_ADVANCED the input is already on the 0-10 scale.
     *
     * @param float $assigngrade Points, scale item index, or 0-10 value.
     * @return float Grade on the 0-10 scale, rounded to 4 decimals.
     */
    public function to_normalized(float $assigngrade): float {
        switch ($this->type) {
            case self::TYPE_POINT:
                if ($this->grademax <= 0) {
                    return 0.0;
                }
                $points = max(0.0, min($this->grademax, $assigngrade));
                return round($points / $this->grademax * self::AI_MAX, 4);
            case self::TYPE_SCALE:
                $count = count($this->scaleitems);
                if ($count <= 1) {
                    return self::AI_MAX;
                }
                $index = max(1, min($count, (int) round($assigngrade)));
                return round(($index - 1) / ($count - 1) * self::AI_MAX, 4);
            default:
                return round(max(0.0, min(self::AI_MAX, $assigngrade)), 4);
        }
    }

    /**
     * Lowest value the teacher may enter in the review form.
     *
     * @return float
     */
    public function get_input_min(): float {
        return $this->type === self::TYPE_SCALE ? 1.0 : 0.0;
    }

    /**
     * Highest value the teacher may enter in the review form.
     *
     * @return float
     */
    public function get_input_max(): float {
        switch ($this->type) {
            case self::TYPE_POINT:
                return $this->grademax;
            case self::TYPE_SCALE:
                return (float) count($this->scaleitems);
            default:
                return self::AI_MAX;
        }
    }

    /**
     * Whether a value entered by the teacher is acceptable for this assignment.
     *
     * @param float $value Points, scale item index, or 0-10 value.
     * @return bool
     */
    public function is_valid_input(float $value): bool {
        if ($value < $this->get_input_min() || $value > $this->get_input_max()) {
            return false;
        }
        if ($this->type === self::TYPE_SCALE) {
            return isset($this->scaleitems[(int) $value]) && (float) (int) $value === $value;
        }
        return true;
    }

    /**
     * Human-readable rendering of a normalised grade on the assignment's scale.
     *
     * Returns plain text; callers must escape it before output.
     *
     * @param float $normalized Grade on the 0-10 scale.
     * @return string E.g. "75 / 100", "Merit", or "7.5 / 10".
     */
    public function format(float $normalized): string {
        switch ($this->type) {
            case self::TYPE_POINT:
                return format_float((float) $this->to_assign_grade($normalized), 2, true, true)
                    . ' / ' . format_float($this->grademax, 2, true, true);
            case self::TYPE_SCALE:
                return (string) ($this->scaleitems[(int) $this->to_assign_grade($normalized)] ?? '');
            default:
                return format_float(max(0.0, min(self::AI_MAX, $normalized)), 2, true, true)
                    . ' / ' . format_float(self::AI_MAX, 0);
        }
    }
}
