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
 * Tests for the activity settings form.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect;

/**
 * The activity name is stored in a 255-character column, so the form must refuse a longer one.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_reflect_mod_form
 */
final class mod_form_test extends \advanced_testcase {
    /**
     * The name field carries a maxlength rule that fits its column.
     */
    public function test_the_name_field_has_a_maxlength_rule_that_fits_the_column(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/reflect/mod_form.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('reflect', $instance->id);
        $PAGE->set_course($course);
        $form = new \mod_reflect_mod_form((object) [
            'instance' => $instance->id, 'id' => $cm->id, 'course' => $course->id,
        ], 0, $cm, $course);

        $property = new \ReflectionProperty(\mod_reflect_mod_form::class, '_form');
        $quickform = $property->getValue($form);
        $rules = (new \ReflectionProperty(\HTML_QuickForm::class, '_rules'))->getValue($quickform);
        $limits = [];
        foreach ($rules['name'] ?? [] as $rule) {
            if ($rule['type'] === 'maxlength') {
                $limits[] = (int) $rule['format'];
            }
        }

        $this->assertNotEmpty($limits, 'The name field has no maxlength rule.');
        $this->assertLessThanOrEqual(255, min($limits));
    }

    /**
     * Builds the form for an existing activity.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $instance The activity record returned by the generator.
     * @return \mod_reflect_mod_form
     */
    private function build_form(\stdClass $course, \stdClass $instance): \mod_reflect_mod_form {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/reflect/mod_form.php');

        $PAGE->set_course($course);
        $cm = get_coursemodule_from_instance('reflect', $instance->id);

        return new \mod_reflect_mod_form((object) [
            'instance' => $instance->id, 'id' => $cm->id, 'course' => $course->id,
        ], 0, $cm, $course);
    }

    /**
     * An unticked completion rule is stored as 0.
     *
     * A browser does not send an unticked checkbox, so the rule is simply missing from the submitted data
     * and, unless the form fills it in, the update leaves the old 1 in the database.
     */
    public function test_an_unticked_completion_rule_is_submitted_as_zero(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $form = $this->build_form($course, $instance);

        $unticked = (object) ['completionunlocked' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC];
        $form->data_postprocessing($unticked);
        $this->assertSame(0, (int) $unticked->completionsubmit);

        $ticked = (object) [
            'completionunlocked' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
        ];
        $form->data_postprocessing($ticked);
        $this->assertSame(1, (int) $ticked->completionsubmit);

        $manual = (object) [
            'completionunlocked' => 1, 'completion' => COMPLETION_TRACKING_MANUAL, 'completionsubmit' => 1,
        ];
        $form->data_postprocessing($manual);
        $this->assertSame(0, (int) $manual->completionsubmit);

        $locked = (object) ['completion' => COMPLETION_TRACKING_MANUAL, 'completionsubmit' => 1];
        $form->data_postprocessing($locked);
        $this->assertSame(1, (int) $locked->completionsubmit);
    }

    /**
     * The whole save: the stored rule really goes to 0 in the database when the teacher unticks it.
     */
    public function test_saving_with_the_rule_unticked_clears_it_in_the_database(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('reflect', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
        ]);
        $form = $this->build_form($course, $instance);

        // What the form returns when the box was unticked: the key is absent.
        $data = $DB->get_record('reflect', ['id' => $instance->id], '*', MUST_EXIST);
        unset($data->completionsubmit);
        $data->instance = $instance->id;
        $data->completionunlocked = 1;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;

        $form->data_postprocessing($data);
        reflect_update_instance($data);

        $this->assertSame(0, (int) $DB->get_field('reflect', 'completionsubmit', ['id' => $instance->id]));
    }

    /**
     * The rule element carries the form's suffix.
     *
     * The core shows the form on the "Default activity completion" page with a suffix and removes any
     * custom rule whose name lacks it, which would erase the rule from the defaults of every new activity.
     */
    public function test_the_completion_rule_element_carries_the_form_suffix(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('reflect', ['course' => $course->id]);
        $form = $this->build_form($course, $instance);
        $quickform = (new \ReflectionProperty(\mod_reflect_mod_form::class, '_form'))->getValue($form);

        $form->set_suffix('_reflect');
        $names = $form->add_completion_rules();

        $this->assertSame(['completionsubmit_reflect'], $names);
        $this->assertTrue($quickform->elementExists('completionsubmit_reflect'));
        $this->assertTrue($form->completion_rule_enabled(['completionsubmit_reflect' => 1]));
        $this->assertFalse($form->completion_rule_enabled(['completionsubmit' => 1]));
    }
}
