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
 * Unit tests for the custom completion rule.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\completion;

use advanced_testcase;

/**
 * Tests for \mod_reflect\completion\custom_completion.
 *
 * @covers \mod_reflect\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Creates a course and a Reflect activity that requires a response to complete.
     *
     * @return array{0: \stdClass, 1: \stdClass} [course, activity]
     */
    private function create_fixture(): array {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $reflect = $this->getDataGenerator()->create_module('reflect', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
        ]);

        return [$course, $reflect];
    }

    /**
     * Saves a response of the user to a new question of the activity.
     *
     * @param \stdClass $reflect The activity.
     * @param int $userid The user.
     * @return void
     */
    private function save_response(\stdClass $reflect, int $userid): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_reflect');
        $question = $generator->create_question($reflect->id, ['responsetype' => 'text']);
        $DB->insert_record('reflect_responses', [
            'reflectid' => $reflect->id, 'questionid' => $question->id, 'userid' => $userid,
            'value' => null, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The rule is incomplete until the student has saved a response, and only that student's.
     *
     * @return void
     */
    public function test_get_state_needs_a_response_from_the_student(): void {
        $this->resetAfterTest();

        [$course, $reflect] = $this->create_fixture();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cminfo = get_fast_modinfo($course)->get_cm($reflect->cmid);

        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            (new custom_completion($cminfo, (int) $student->id))->get_state('completionsubmit')
        );

        $this->save_response($reflect, (int) $other->id);
        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            (new custom_completion($cminfo, (int) $student->id))->get_state('completionsubmit')
        );

        $this->save_response($reflect, (int) $student->id);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            (new custom_completion($cminfo, (int) $student->id))->get_state('completionsubmit')
        );
    }

    /**
     * The core completion machinery really evaluates the rule: the activity is only marked complete once
     * the student has a response, so the rule is wired through get_coursemodule_info() and not just defined.
     *
     * @return void
     */
    public function test_the_core_marks_the_activity_complete_only_with_a_response(): void {
        $this->resetAfterTest();

        [$course, $reflect] = $this->create_fixture();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('reflect', $reflect->id);
        $completion = new \completion_info($course);

        $completion->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, (int) $student->id)->completionstate);

        $this->save_response($reflect, (int) $student->id);
        $completion->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, (int) $student->id)->completionstate);
    }

    /**
     * The module defines exactly one rule, described and sorted.
     *
     * @return void
     */
    public function test_the_rule_is_defined_described_and_sorted(): void {
        $this->resetAfterTest();

        [$course, $reflect] = $this->create_fixture();
        $user = $this->getDataGenerator()->create_user();
        $cminfo = get_fast_modinfo($course)->get_cm($reflect->cmid);
        $completion = new custom_completion($cminfo, (int) $user->id);

        $this->assertSame(['completionsubmit'], custom_completion::get_defined_custom_rules());
        $this->assertSame(
            ['completionsubmit' => get_string('completiondetail:submit', 'mod_reflect')],
            $completion->get_custom_rule_descriptions()
        );
        $this->assertContains('completionsubmit', $completion->get_sort_order());
    }
}
