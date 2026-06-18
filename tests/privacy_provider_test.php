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
 * Privacy tests for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\tests;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use mod_reflect\privacy\provider;

/**
 * Privacy tests for mod_reflect.
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Test getting contexts for a user.
     * @covers \mod_reflect\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        // User has no data yet.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Add data.
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id,
            'questionid' => $q->id,
            'userid' => $user->id,
            'value' => 50,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
    }
}
