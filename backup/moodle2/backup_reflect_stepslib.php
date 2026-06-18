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
 * Backup steps for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete reflect structure for backup, with file and id annotations.
 */
class backup_reflect_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure for the reflect activity.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Define each element separated.
        $reflect = new backup_nested_element('reflect', ['id'], [
            'name', 'intro', 'introformat', 'grademethod', 'allowcomment',
            'grade', 'timecreated', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'question', 'questionformat', 'responsetype', 'maxgrade', 'sortorder',
        ]);

        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'questionid', 'userid', 'value', 'responsetext', 'comment',
            'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $reflect->add_child($questions);
        $questions->add_child($question);

        $reflect->add_child($responses);
        $responses->add_child($response);

        // Define sources.
        $reflect->set_source_table('reflect', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('reflect_questions', ['reflectid' => backup::VAR_PARENTID]);

        // Only include responses if user info is requested.
        if ($this->get_setting_value('userinfo')) {
            $response->set_source_table('reflect_responses', ['reflectid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $response->annotate_ids('user', 'userid');

        // Define file annotations.
        $reflect->annotate_files('mod_reflect', 'intro', null); // This file area hasn't itemid.

        // Return the root element (reflect), wrapped into standard activity structure.
        return $this->prepare_activity_structure($reflect);
    }
}
