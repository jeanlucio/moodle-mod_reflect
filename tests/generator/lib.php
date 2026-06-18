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
 * Data generator for mod_reflect.
 *
 * @package    mod_reflect
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Reflect module data generator class.
 */
class mod_reflect_generator extends testing_module_generator {
    /**
     * Create a new reflect instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array)$record;

        $defaultsettings = [
            'allowcomment' => 1,
            'grademethod'  => 'manual',
            'grade'        => 100,
        ];

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record[$name])) {
                $record[$name] = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create a reflect question.
     *
     * @param int $reflectid
     * @param array|stdClass|null $record
     * @return stdClass
     */
    public function create_question($reflectid, $record = null) {
        global $DB;
        $record = (array)$record;

        $question = new stdClass();
        $question->reflectid = $reflectid;
        $question->question = $record['question'] ?? 'Test question';
        $question->questionformat = FORMAT_HTML;
        $question->responsetype = $record['responsetype'] ?? 'numeric';
        $question->maxgrade = $record['maxgrade'] ?? 10.0;
        $question->sortorder = $record['sortorder'] ?? 1;

        $question->id = $DB->insert_record('reflect_questions', $question);
        return $question;
    }
}
