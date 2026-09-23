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
 * Tests for the mapping between the AI's 0-10 grades and the assignment's scale.
 *
 * @package    local_aigrader
 * @category   test
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aigrader\grading_scale
 */
final class grading_scale_test extends \advanced_testcase {
    /**
     * Points: 8/10 becomes 80/100, and teacher input round-trips exactly.
     */
    public function test_point_scale_converts_both_ways(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_POINT, 100);

        $this->assertEqualsWithDelta(80.0, $scale->to_assign_grade(8.0), 0.0001);
        $this->assertEqualsWithDelta(55.0, $scale->to_assign_grade(5.5), 0.0001);
        $this->assertEqualsWithDelta(7.333, $scale->to_normalized(73.33), 0.0001);
        $this->assertEqualsWithDelta(73.33, $scale->to_assign_grade($scale->to_normalized(73.33)), 0.0001);
        $this->assertTrue($scale->publishes_grade());
        $this->assertTrue($scale->can_publish());
    }

    /**
     * Points: other maximums and out-of-range values are handled.
     */
    public function test_point_scale_other_maximum_and_clamping(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_POINT, 20);

        $this->assertEqualsWithDelta(16.0, $scale->to_assign_grade(8.0), 0.0001);
        $this->assertEqualsWithDelta(20.0, $scale->to_assign_grade(12.0), 0.0001);
        $this->assertEqualsWithDelta(0.0, $scale->to_assign_grade(-3.0), 0.0001);

        $this->assertTrue($scale->is_valid_input(20));
        $this->assertTrue($scale->is_valid_input(0));
        $this->assertFalse($scale->is_valid_input(20.5));
        $this->assertFalse($scale->is_valid_input(-1));
        $this->assertSame('16 / 20', $scale->format(8.0));
    }

    /**
     * Moodle scale: the 0-10 grade is spread evenly across the scale items.
     */
    public function test_scale_maps_to_nearest_item(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_SCALE, 0, ['Fail', 'Pass', 'Merit']);

        $this->assertSame(1.0, $scale->to_assign_grade(0.0));
        $this->assertSame(2.0, $scale->to_assign_grade(4.0));
        $this->assertSame(2.0, $scale->to_assign_grade(5.0));
        $this->assertSame(3.0, $scale->to_assign_grade(8.0));
        $this->assertSame(3.0, $scale->to_assign_grade(10.0));
        $this->assertSame('Merit', $scale->format(8.0));

        // Choosing an item and converting back lands on the same item.
        foreach ([1, 2, 3] as $item) {
            $this->assertSame((float) $item, $scale->to_assign_grade($scale->to_normalized($item)));
        }

        $this->assertTrue($scale->is_valid_input(2));
        $this->assertFalse($scale->is_valid_input(2.5));
        $this->assertFalse($scale->is_valid_input(0));
        $this->assertFalse($scale->is_valid_input(4));
    }

    /**
     * Moodle scale with a single item: always that item.
     */
    public function test_single_item_scale(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_SCALE, 0, ['Completed']);

        $this->assertSame(1.0, $scale->to_assign_grade(0.0));
        $this->assertSame(1.0, $scale->to_assign_grade(10.0));
        $this->assertSame(10.0, $scale->to_normalized(1));
    }

    /**
     * "No grade": feedback is published, no grade value is produced.
     */
    public function test_no_grade(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_NONE);

        $this->assertNull($scale->to_assign_grade(7.5));
        $this->assertFalse($scale->publishes_grade());
        $this->assertTrue($scale->can_publish());
        $this->assertSame('7.5 / 10', $scale->format(7.5));
    }

    /**
     * Advanced grading: nothing can be published from AI Grader Pro.
     */
    public function test_advanced_grading_cannot_publish(): void {
        $scale = grading_scale::from_values(grading_scale::TYPE_ADVANCED, 0, [], 'rubric');

        $this->assertFalse($scale->can_publish());
        $this->assertFalse($scale->publishes_grade());
        $this->assertNull($scale->to_assign_grade(7.5));
        $this->assertSame('rubric', $scale->get_advanced_method());
    }

    /**
     * for_assign() reads points, scales and "no grade" from real assignments.
     */
    public function test_for_assign_reads_simple_grading_types(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $points = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $scale = grading_scale::for_assign($points, \context_module::instance($points->cmid));
        $this->assertSame(grading_scale::TYPE_POINT, $scale->get_type());
        $this->assertEqualsWithDelta(100.0, $scale->get_grademax(), 0.0001);

        $moodlescale = $this->getDataGenerator()->create_scale(['scale' => 'Fail,Pass,Merit']);
        $scaled = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade'  => -$moodlescale->id,
        ]);
        $scale = grading_scale::for_assign($scaled, \context_module::instance($scaled->cmid));
        $this->assertSame(grading_scale::TYPE_SCALE, $scale->get_type());
        // Moodle lists scale items highest first; keys still run 1..n.
        $this->assertEquals([1 => 'Fail', 2 => 'Pass', 3 => 'Merit'], $scale->get_scale_items());

        $nograde = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 0]);
        $scale = grading_scale::for_assign($nograde, \context_module::instance($nograde->cmid));
        $this->assertSame(grading_scale::TYPE_NONE, $scale->get_type());
    }

    /**
     * for_assign() detects an active rubric, which blocks publishing.
     */
    public function test_for_assign_detects_rubric(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $context = \context_module::instance($assign->cmid);

        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')->create_instance(
            $context,
            'mod_assign',
            'submissions',
            'Essay rubric',
            'Rubric for the test',
            ['Argument' => ['Weak' => 0, 'Solid' => 5, 'Excellent' => 10]]
        );

        $scale = grading_scale::for_assign($assign, $context);
        $this->assertSame(grading_scale::TYPE_ADVANCED, $scale->get_type());
        $this->assertSame('rubric', $scale->get_advanced_method());
        $this->assertFalse($scale->can_publish());
    }
}
