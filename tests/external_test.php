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
 * External functions tests for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\tests;

use advanced_testcase;
use core_external\external_api;

/**
 * External functions tests for mod_reflect.
 */
final class external_test extends advanced_testcase {
    /**
     * Test saving a response and gradebook update.
     * @covers \mod_reflect\external\save_response::execute
     */
    public function test_save_response(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup course and users.
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // Setup module (grade method: distribute, max grade 100).
        $reflect = $this->getDataGenerator()->create_module('reflect', [
            'course' => $course->id,
            'grademethod' => 'distribute',
            'grade' => 100,
            'allowcomment' => 1,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q1 = $generator->create_question($reflect->id, ['responsetype' => 'numeric']);
        $q2 = $generator->create_question($reflect->id, ['responsetype' => 'numeric']);
        $this->setUser($student);

        // Call the external function.
        $result = \mod_reflect\external\save_response::execute(
            $reflect->cmid,
            $q1->id,
            50,
            '',
            'Global comment'
        );
        $result = external_api::clean_returnvalue(\mod_reflect\external\save_response::execute_returns(), $result);

        $this->assertTrue($result['success']);

        // Check database.
        $response = $DB->get_record(
            'reflect_responses',
            ['reflectid' => $reflect->id, 'userid' => $student->id, 'questionid' => $q1->id]
        );
        $this->assertEquals(50, $response->value);
        $this->assertEquals('Global comment', $response->comment);

        // Check gradebook.
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'reflect',
            'iteminstance' => $reflect->id,
        ]);
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $this->assertEquals(25.0, $gradegrade->rawgrade);
    }
}
