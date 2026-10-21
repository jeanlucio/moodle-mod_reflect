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
 * Tests for the course reset hooks in lib.php.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect;

/**
 * Tests for the reflect part of "Reset course", run through the real reset_course_userdata().
 *
 * @covers ::reflect_reset_userdata
 * @covers ::reflect_reset_course_form_definition
 * @covers ::reflect_reset_course_form_defaults
 */
final class lib_reset_userdata_test extends \advanced_testcase {
    /** @var string[] The tables that hold the students' rows. */
    private const STUDENT_TABLES = ['reflect_responses'];

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/moodlelib.php');
        require_once($CFG->dirroot . '/mod/reflect/lib.php');
    }

    /**
     * Inserts a row, filling every required column the test does not care about.
     *
     * @param string $table The table, without prefix.
     * @param array $values The columns the test sets.
     * @return int The new id.
     */
    private function insert_row(string $table, array $values): int {
        global $DB;

        $row = [];
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($column->auto_increment) {
                continue;
            }
            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];
            } else if ($column->not_null && !$column->has_default) {
                $row[$name] = in_array($column->meta_type, ['C', 'X'], true) ? 'x' : 0;
            }
        }

        return $DB->insert_record($table, (object) $row);
    }

    /**
     * Creates an activity in a new course with a student's rows in every student table, plus the
     * teacher's own content.
     *
     * @return array [course, activity]
     */
    private function create_course_with_progress(): array {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $this->insert_row('reflect_responses', ['reflectid' => $instance->id, 'userid' => $student->id]);
        $this->insert_row('reflect_questions', ['reflectid' => $instance->id]);

        return [$course, $instance];
    }

    /**
     * The number of rows in the student tables, all courses together.
     *
     * @return int
     */
    private function count_student_rows(): int {
        global $DB;

        $total = 0;
        foreach (self::STUDENT_TABLES as $table) {
            $total += $DB->count_records($table);
        }

        return $total;
    }

    /**
     * Runs the core course reset with the given option.
     *
     * @param \stdClass $course The course to reset.
     * @param int $option Whether the reflect option is ticked.
     * @return array The status rows the core reports.
     */
    private function run_reset(\stdClass $course, int $option): array {
        $this->setAdminUser();
        return reset_course_userdata((object) ['id' => $course->id, 'reset_reflect_userdata' => $option]);
    }

    /**
     * Ticking the option clears the students' rows of that course only, and keeps the teacher's content.
     *
     * @return void
     */
    public function test_reset_clears_the_students_rows_of_the_course_only(): void {
        global $DB;

        [$coursea] = $this->create_course_with_progress();
        $this->create_course_with_progress();
        $percourse = count(self::STUDENT_TABLES);
        $this->assertSame(2 * $percourse, $this->count_student_rows());

        $status = $this->run_reset($coursea, 1);

        $this->assertSame($percourse, $this->count_student_rows(), 'Only the other course keeps its rows.');
        $this->assertSame(
            2,
            $DB->count_records('reflect_questions'),
            'The teacher\'s own content must stay.'
        );
        $this->assertContains(get_string('resetreflectuserdata', 'mod_reflect'), array_column($status, 'item'));
    }

    /**
     * Without the option nothing is touched.
     *
     * @return void
     */
    public function test_reset_keeps_everything_when_the_option_is_off(): void {
        [$course] = $this->create_course_with_progress();
        $before = $this->count_student_rows();

        $status = $this->run_reset($course, 0);

        $this->assertSame($before, $this->count_student_rows());
        $this->assertNotContains(get_string('resetreflectuserdata', 'mod_reflect'), array_column($status, 'item'));
    }

    /**
     * The reset form offers the option, ticked by default.
     *
     * @return void
     */
    public function test_the_form_offers_the_option_ticked_by_default(): void {
        $mform = new \MoodleQuickForm('reset', 'post', '');

        reflect_reset_course_form_definition($mform);

        $this->assertTrue($mform->elementExists('reset_reflect_userdata'));
        $this->assertSame(
            ['reset_reflect_userdata' => 1],
            reflect_reset_course_form_defaults($this->getDataGenerator()->create_course())
        );
    }

    /**
     * The recorded grade goes with the responses it came from, so the next recompute cannot bring it back.
     *
     * @return void
     */
    public function test_reset_clears_the_recorded_grade(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id, 'grade' => 100]);
        $this->insert_row('reflect_responses', ['reflectid' => $instance->id, 'userid' => $student->id]);
        grade_update('mod/reflect', $course->id, 'mod', 'reflect', $instance->id, 0, [
            $student->id => ['userid' => $student->id, 'rawgrade' => 70],
        ], ['gradetype' => GRADE_TYPE_VALUE, 'grademax' => 100, 'grademin' => 0]);
        $grades = grade_get_grades($course->id, 'mod', 'reflect', $instance->id, $student->id);
        $this->assertEquals(70.0, $grades->items[0]->grades[$student->id]->grade);

        $this->run_reset($course, 1);

        $grades = grade_get_grades($course->id, 'mod', 'reflect', $instance->id, $student->id);
        $this->assertNull($grades->items[0]->grades[$student->id]->grade);
    }
}
