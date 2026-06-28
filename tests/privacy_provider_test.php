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
     * @covers \mod_reflect\privacy\provider::get_metadata
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        // Also test get_metadata.
        $collection = new \core_privacy\local\metadata\collection('mod_reflect');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * Test getting users in context.
     * @covers \mod_reflect\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $userlist = new approved_userlist($context, 'mod_reflect', [$user->id]);
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_reflect');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
    }

    /**
     * Test exporting user data.
     * @covers \mod_reflect\privacy\provider::export_user_data
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $approvedcontextlist = new approved_contextlist($user, 'mod_reflect', $contextlist->get_contextids());

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        provider::export_user_data($approvedcontextlist);

        $data = $writer->get_data([get_string('pluginname', 'mod_reflect'), get_string('responses', 'mod_reflect')]);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data->responses);
        $this->assertEquals(50, $data->responses[0]->value);
    }

    /**
     * Test deleting data for all users in a context.
     * @covers \mod_reflect\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        provider::delete_data_for_all_users_in_context($context);
        $this->assertFalse($DB->record_exists('reflect_responses', ['reflectid' => $reflect->id]));
    }

    /**
     * Test deleting data for users in a context.
     * @covers \mod_reflect\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $context = \context_module::instance($cm->id);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $approveduserlist = new approved_userlist($context, 'mod_reflect', [$user->id]);
        provider::delete_data_for_users($approveduserlist);
        $this->assertFalse($DB->record_exists('reflect_responses', ['reflectid' => $reflect->id]));
    }

    /**
     * Test deleting data for a user.
     * @covers \mod_reflect\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $reflect = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $q = $generator->create_question($reflect->id);

        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $q->id, 'userid' => $user->id,
            'value' => 50, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $approvedcontextlist = new approved_contextlist($user, 'mod_reflect', $contextlist->get_contextids());

        provider::delete_data_for_user($approvedcontextlist);
        $this->assertFalse($DB->record_exists('reflect_responses', ['reflectid' => $reflect->id]));
    }
}
