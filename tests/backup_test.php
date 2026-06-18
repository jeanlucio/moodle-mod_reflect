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
 * Backup and restore tests for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\tests;

use advanced_testcase;

/**
 * Backup and restore tests for mod_reflect.
 */
final class backup_test extends advanced_testcase {
    /**
     * Test backup and restore of reflect activity.
     * @covers \backup_reflect_activity_task
     */
    public function test_backup_and_restore(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');

        $q1 = $generator->create_question($reflect->id, ['responsetype' => 'numeric', 'maxgrade' => 10]);
        $q2 = $generator->create_question($reflect->id, ['responsetype' => 'text']);

        // Insert a dummy response.
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id,
            'questionid' => $q1->id,
            'userid' => $USER->id,
            'value' => 50,
            'responsetext' => null,
            'comment' => 'Test comment',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $this->assertNotEquals($course->id, $newcourseid);

        $newreflect = $DB->get_record('reflect', ['course' => $newcourseid]);
        $this->assertNotEmpty($newreflect);
        $this->assertEquals($reflect->name, $newreflect->name);

        $newquestions = $DB->get_records('reflect_questions', ['reflectid' => $newreflect->id], 'id ASC');
        $this->assertCount(2, $newquestions);

        $newresponses = $DB->get_records('reflect_responses', ['reflectid' => $newreflect->id]);
        $this->assertCount(1, $newresponses);
        $response = reset($newresponses);
        $this->assertEquals(50, $response->value);
        $this->assertEquals('Test comment', $response->comment);
    }

    /**
     * Backs a course up and restores it.
     *
     * @param \stdClass $srccourse Course object to backup
     * @return int ID of newly restored course
     */
    private function backup_and_restore(\stdClass $srccourse): int {
        global $USER, $CFG;

        // Turn off file logging.
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // Do backup with MODE_IMPORT to skip zipping.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $srccourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Do restore to new course.
        $newcourseid = \restore_dbops::create_new_course(
            $srccourse->fullname . ' restored',
            $srccourse->shortname . '_2',
            $srccourse->category
        );
        
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );

        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
