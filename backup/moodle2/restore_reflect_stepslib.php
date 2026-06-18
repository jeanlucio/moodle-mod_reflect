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
 * Restore steps for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete reflect structure for restore.
 */
class restore_reflect_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure for the reflect activity.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('reflect', '/activity/reflect');
        $paths[] = new restore_path_element('reflect_question', '/activity/reflect/questions/question');
        $paths[] = new restore_path_element('reflect_response', '/activity/reflect/responses/response');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the reflect element.
     *
     * @param array $data
     */
    protected function process_reflect($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the reflect record.
        $newitemid = $DB->insert_record('reflect', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the reflect_question element.
     *
     * @param array $data
     */
    protected function process_reflect_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->reflectid = $this->get_new_parentid('reflect');

        $newitemid = $DB->insert_record('reflect_questions', $data);
        $this->set_mapping('reflect_questions', $oldid, $newitemid);
    }

    /**
     * Process the reflect_response element.
     *
     * @param array $data
     */
    protected function process_reflect_response($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->reflectid = $this->get_new_parentid('reflect');
        $data->questionid = $this->get_mappingid('reflect_questions', $data->questionid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('reflect_responses', $data);
        // We don't need to map responses.
    }

    /**
     * Actions to run after restoring all elements.
     */
    protected function after_execute() {
        // Add reflect related files.
        $this->add_related_files('mod_reflect', 'intro', null);
    }
}
