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

namespace mod_reflect\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Test cross-instance security for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \mod_reflect\external\save_response
 */
final class cross_instance_security_test extends advanced_testcase {
    /**
     * Setup before each test.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/reflect/tests/generator/lib.php');
    }

    /**
     * Test that a student cannot save a response to a question belonging to another instance.
     *
     * @covers ::execute
     */
    public function test_save_response_cross_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        // Create Instance 1.
        $instance1 = $generator->create_module('reflect', ['course' => $course->id]);
        $cm1 = get_coursemodule_from_instance('reflect', $instance1->id);

        $q1 = $DB->insert_record('reflect_questions', [
            'reflectid' => $instance1->id,
            'question' => 'Q1 from Instance 1',
            'responsetype' => 'numeric',
            'maxgrade' => 10,
        ]);

        // Create Instance 2.
        $instance2 = $generator->create_module('reflect', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('reflect', $instance2->id);

        $q2 = $DB->insert_record('reflect_questions', [
            'reflectid' => $instance2->id,
            'question' => 'Q1 from Instance 2',
            'responsetype' => 'numeric',
            'maxgrade' => 10,
        ]);

        $this->setUser($student);

        // Attempting to save a response for $cm1 using a question from $instance2.
        // This should throw a dml_missing_record_exception because the question doesn't belong to the instance.
        $this->expectException(\dml_missing_record_exception::class);

        save_response::execute(
            $cm1->id,
            $q2,
            50,
            null,
            null
        );
    }
}
