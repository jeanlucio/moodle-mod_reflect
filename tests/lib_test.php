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
 * Library functions tests for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\tests;

use advanced_testcase;
use stdClass;

/**
 * Library functions tests for mod_reflect.
 *
 * @coversDefaultClass \mod_reflect\local\grade_manager
 * @covers \reflect_add_instance
 * @covers \reflect_update_instance
 * @covers \reflect_delete_instance
 * @covers \reflect_supports
 * @covers \reflect_grade_item_update
 * @covers \reflect_update_grades
 * @covers \reflect_get_completion_state
 * @covers \reflect_get_completion_active_rule_descriptions
 */
final class lib_test extends advanced_testcase {

    /**
     * Setup before each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test adding a reflect instance.
     * @covers \reflect_add_instance
     */
    public function test_reflect_add_instance(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        
        $data = new stdClass();
        $data->course = $course->id;
        $data->name = 'Reflect Test';
        $data->intro = 'Intro';
        $data->introformat = FORMAT_HTML;
        $data->grade = 100;
        $data->grademethod = 'distribute';
        
        $id = reflect_add_instance($data);
        
        $this->assertNotEmpty($id);
        $record = $DB->get_record('reflect', ['id' => $id]);
        $this->assertEquals('Reflect Test', $record->name);
        
        // Verify grade item was created.
        $gradeitem = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'reflect', 'iteminstance' => $id]);
        $this->assertNotEmpty($gradeitem);
        $this->assertEquals(100, $gradeitem->grademax);
    }

    /**
     * Test updating a reflect instance.
     * @covers \reflect_update_instance
     */
    public function test_reflect_update_instance(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id, 'grade' => 10]);
        
        $data = new stdClass();
        $data->instance = $reflect->id;
        $data->course = $course->id;
        $data->name = 'Updated Reflect';
        $data->grade = 50;
        $data->grademethod = 'manual';
        
        $result = reflect_update_instance($data);
        $this->assertTrue($result);
        
        $record = $DB->get_record('reflect', ['id' => $reflect->id]);
        $this->assertEquals('Updated Reflect', $record->name);
        $this->assertEquals(50, $record->grade);
        
        // Verify grade item was updated.
        $gradeitem = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'reflect', 'iteminstance' => $reflect->id]);
        $this->assertEquals(50, $gradeitem->grademax);
    }

    /**
     * Test deleting a reflect instance.
     * @covers \reflect_delete_instance
     */
    public function test_reflect_delete_instance(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        
        $generator->create_question($reflect->id, ['responsetype' => 'numeric']);
        
        $result = reflect_delete_instance($reflect->id);
        $this->assertTrue($result);
        
        $this->assertFalse($DB->record_exists('reflect', ['id' => $reflect->id]));
        $this->assertFalse($DB->record_exists('reflect_questions', ['reflectid' => $reflect->id]));
    }

    /**
     * Test reflect_supports function.
     * @covers \reflect_supports
     */
    public function test_reflect_supports(): void {
        $this->assertTrue(reflect_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(reflect_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(reflect_supports('invalid_feature'));
    }

    /**
     * Test updating grades.
     * @covers ::update_grades
     */
    public function test_reflect_update_grades(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        
        $reflect = $this->getDataGenerator()->create_module('reflect', [
            'course' => $course->id,
            'grade' => 10,
            'grademethod' => 'manual'
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q1 = $generator->create_question($reflect->id, ['responsetype' => 'numeric', 'maxgrade' => 5]);
        $q2 = $generator->create_question($reflect->id, ['responsetype' => 'numeric', 'maxgrade' => 5]);
        
        // Add responses for student 1.
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q1->id, 'userid' => $student1->id,
            'value' => 100, 'timecreated' => time(), 'timemodified' => time()
        ]);
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q2->id, 'userid' => $student1->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time()
        ]);
        
        reflect_update_grades($reflect);
        
        // Check gradebook.
        $gradeitem = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'reflect', 'iteminstance' => $reflect->id]);
        $grade1 = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student1->id]);
        
        // Q1 max 5 (100%), Q2 max 5 (50%) -> 5 + 2.5 = 7.5
        $this->assertEquals(7.5, $grade1->rawgrade);
        
        // Student 2 has no responses, so no grade entry (or null).
        $grade2 = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student2->id]);
        $this->assertFalse($grade2);
    }

    /**
     * Test completion rules.
     * @covers \reflect_get_completion_state
     * @covers \reflect_get_completion_active_rule_descriptions
     */
    public function test_completion(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        
        $reflect = $this->getDataGenerator()->create_module('reflect', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1
        ]);
        
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        
        $rules = reflect_get_completion_active_rule_descriptions($course, $cm);
        $this->assertCount(1, $rules);
        
        // Initially false.
        $state = reflect_get_completion_state($course, $cm, $student->id, false);
        $this->assertFalse($state);
        
        // Add response.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q1 = $generator->create_question($reflect->id, ['responsetype' => 'text']);
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q1->id, 'userid' => $student->id,
            'value' => null, 'timecreated' => time(), 'timemodified' => time()
        ]);
        
        // Now true.
        $state = reflect_get_completion_state($course, $cm, $student->id, false);
        $this->assertTrue($state);
    }
}
