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

namespace mod_reflect\external;

use advanced_testcase;
use core_external\external_api;

/**
 * External functions tests for mod_reflect.
 *
 * @covers \mod_reflect\external\delete_question
 * @covers \mod_reflect\external\save_question
 * @covers \mod_reflect\external\save_response
 */
final class external_test extends advanced_testcase {
    /**
     * Test saving a response and gradebook update.
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

    /**
     * Test saving a question.
     */
    public function test_save_question(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);

        $result = \mod_reflect\external\save_question::execute(
            $reflect->cmid,
            0, // New question.
            'How are you?',
            FORMAT_HTML,
            'numeric',
            5.0
        );
        $result = external_api::clean_returnvalue(\mod_reflect\external\save_question::execute_returns(), $result);
        $this->assertTrue($result['success']);

        $question = $DB->get_record('reflect_questions', ['reflectid' => $reflect->id]);
        $this->assertEquals('How are you?', $question->question);
        $this->assertEquals(5, $question->maxgrade);
    }

    /**
     * Test deleting a question.
     */
    public function test_delete_question(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q1 = $generator->create_question($reflect->id, ['responsetype' => 'numeric']);

        $result = \mod_reflect\external\delete_question::execute(
            $reflect->cmid,
            $q1->id
        );
        $result = external_api::clean_returnvalue(\mod_reflect\external\delete_question::execute_returns(), $result);
        $this->assertTrue($result['success']);

        $this->assertFalse($DB->record_exists('reflect_questions', ['id' => $q1->id]));
    }

    /**
     * Creates an activity with one numeric question and a student who is logged in.
     *
     * @return array [activity, question, student]
     */
    private function create_activity_for_a_student(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/reflect/tests/fixtures/recording_lock_factory.php');

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $question = $generator->create_question($reflect->id, ['responsetype' => 'numeric']);
        $this->setUser($student);

        return [$reflect, $question, $student];
    }

    /**
     * The lookup and the write of a response sit between the acquire and the release of the student's lock
     * for that question. PHPUnit cannot race two saves; what it shows is the order of the locks, which is
     * what makes a second simultaneous save wait for the first row before it looks for an earlier one.
     */
    public function test_a_response_is_saved_under_the_students_lock(): void {
        [$reflect, $question, $student] = $this->create_activity_for_a_student();
        \mod_reflect_recording_lock_factory::install();

        \mod_reflect\external\save_response::execute($reflect->cmid, $question->id, 40, '', '');

        $key = 'response_' . $question->id . '_' . $student->id;
        $this->assertSame(
            ["acquire {$key}", "release {$key}"],
            \mod_reflect_recording_lock_factory::events_for('mod_reflect')
        );
    }

    /**
     * When the lock cannot be had the save is refused and nothing is stored; the same save works afterwards.
     */
    public function test_a_busy_lock_stores_nothing_and_the_save_can_be_retried(): void {
        global $DB;

        [$reflect, $question, $student] = $this->create_activity_for_a_student();
        \mod_reflect_recording_lock_factory::install(['mod_reflect/response_' . $question->id . '_' . $student->id]);

        try {
            \mod_reflect\external\save_response::execute($reflect->cmid, $question->id, 40, '', '');
            $this->fail('A save that cannot take the lock must be refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('responsebusy', $exception->errorcode);
        }
        $this->assertSame(0, $DB->count_records('reflect_responses'));

        \mod_reflect_recording_lock_factory::install();
        \mod_reflect\external\save_response::execute($reflect->cmid, $question->id, 40, '', '');
        $this->assertSame(1, $DB->count_records('reflect_responses'));
    }

    /**
     * Two saves that reach the server one after the other, the case the lock turns the simultaneous one
     * into: the second finds the first and updates it, so the question keeps a single row.
     */
    public function test_saving_twice_keeps_a_single_row_per_question(): void {
        global $DB;

        [$reflect, $question, $student] = $this->create_activity_for_a_student();

        \mod_reflect\external\save_response::execute($reflect->cmid, $question->id, 40, '', '');
        \mod_reflect\external\save_response::execute($reflect->cmid, $question->id, 70, '', '');

        $rows = $DB->get_records('reflect_responses', ['questionid' => $question->id, 'userid' => $student->id]);
        $this->assertCount(1, $rows);
        $this->assertEquals(70, reset($rows)->value);
    }
}
