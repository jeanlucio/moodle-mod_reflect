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
 * Event tests for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\tests;

use advanced_testcase;

/**
 * Event tests for mod_reflect.
 *
 * @covers \mod_reflect\event\course_module_viewed
 * @covers \mod_reflect\event\response_submitted
 */
final class event_test extends advanced_testcase {

    /**
     * Setup before each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test course_module_viewed event.
     */
    public function test_course_module_viewed(): void {
        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();

        $event = \mod_reflect\event\course_module_viewed::create([
            'objectid' => $reflect->id,
            'context' => $context,
            'courseid' => $course->id,
        ]);
        $event->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $triggered = reset($events);
        $this->assertInstanceOf('\mod_reflect\event\course_module_viewed', $triggered);
        $this->assertEquals($context->id, $triggered->contextid);
        $this->assertEquals($reflect->id, $triggered->objectid);
        $this->assertEventContextNotUsed($triggered);
    }

    /**
     * Test response_submitted event.
     */
    public function test_response_submitted(): void {
        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();

        $event = \mod_reflect\event\response_submitted::create([
            'objectid' => $reflect->id,
            'context' => $context,
            'courseid' => $course->id,
            'other' => ['questionid' => 123]
        ]);
        $event->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);

        $triggered = reset($events);
        $this->assertInstanceOf('\mod_reflect\event\response_submitted', $triggered);
        $this->assertEquals($context->id, $triggered->contextid);
        $this->assertEquals($reflect->id, $triggered->objectid);
        $this->assertEquals(123, $triggered->other['questionid']);
        $this->assertEventContextNotUsed($triggered);
    }
}
