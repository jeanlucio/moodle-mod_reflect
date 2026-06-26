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
 * Activity configuration form for mod_reflect.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for creating or editing a reflect activity instance.
 */
class mod_reflect_mod_form extends moodleform_mod {
    /**
     * Define the form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_reflect'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'reflectsettings', get_string('reflectsettings', 'mod_reflect'));

        $grademethods = [
            'manual'     => get_string('grademethod_manual', 'mod_reflect'),
            'distribute' => get_string('grademethod_distribute', 'mod_reflect'),
        ];
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'mod_reflect'), $grademethods);
        $mform->setDefault('grademethod', 'manual');
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_reflect');

        $mform->addElement(
            'advcheckbox',
            'allowcomment',
            get_string('allowcomment', 'mod_reflect'),
            get_string('allowcomment_help', 'mod_reflect')
        );
        $mform->setDefault('allowcomment', 0);

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', get_string('completionsubmit', 'mod_reflect'));
        return ['completionsubmit'];
    }

    /**
     * Checks if completion rule is enabled.
     *
     * @param array $data Form data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return (!empty($data['completionsubmit']) && $data['completionsubmit'] != 0);
    }
}
