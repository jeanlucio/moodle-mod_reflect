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
 * Restore task for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/reflect/backup/moodle2/restore_reflect_stepslib.php');

/**
 * Restore task for mod_reflect.
 */
class restore_reflect_activity_task extends restore_activity_task {
    /**
     * Define the specific steps for restore.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_reflect_activity_structure_step('reflect_structure', 'reflect.xml'));
    }

    /**
     * Define the specific rules for the restore.
     */
    protected function define_my_settings() {
        // No specific settings.
    }

    // The invalid define_decode_contents was removed.

    /**
     * Define the rules for decoding links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('REFLECTINDEX', '/mod/reflect/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('REFLECTVIEWBYID', '/mod/reflect/view.php?id=$1', 'course_module');

        return $rules;
    }

    /**
     * Define the content that needs to be decoded.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('reflect', ['intro'], 'reflect');
        $contents[] = new restore_decode_content('reflect_questions', ['question'], 'reflect_questions');

        return $contents;
    }
}
