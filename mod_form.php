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

        $mform->addElement(
            'editor',
            'question_editor',
            get_string('question', 'mod_reflect'),
            ['rows' => 5],
            ['maxfiles' => 0, 'context' => $this->context]
        );
        $mform->setType('question_editor', PARAM_RAW);
        $mform->addRule('question_editor', null, 'required', null, 'client');
        $mform->addHelpButton('question_editor', 'question', 'mod_reflect');

        $responsetypes = [
            'numeric' => get_string('responsetype_numeric', 'mod_reflect'),
            'text'    => get_string('responsetype_text', 'mod_reflect'),
        ];
        $mform->addElement('select', 'responsetype', get_string('responsetype', 'mod_reflect'), $responsetypes);
        $mform->setDefault('responsetype', 'numeric');

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
     * Pre-process data before loading into the form.
     *
     * @param array $defaultvalues Default form values passed by reference.
     * @return void
     */
    public function data_preprocessing(array &$defaultvalues): void {
        if (!empty($defaultvalues['question']) && !empty($defaultvalues['questionformat'])) {
            $defaultvalues['question_editor'] = [
                'text'   => $defaultvalues['question'],
                'format' => $defaultvalues['questionformat'],
            ];
        }
    }
}
