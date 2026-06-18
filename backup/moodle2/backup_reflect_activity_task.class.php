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
 * Backup task for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/reflect/backup/moodle2/backup_reflect_stepslib.php');

/**
 * Backup task for mod_reflect.
 */
class backup_reflect_activity_task extends backup_activity_task {
    /**
     * Define the specific steps for backup.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_reflect_activity_structure_step('reflect_structure', 'reflect.xml'));
    }

    /**
     * Define the specific rules for the backup.
     */
    protected function define_my_settings() {
        // No specific settings.
    }

    /**
     * Encode the content links.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of reflect activities.
        $search = "/(" . $base . "\/mod\/reflect\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@REFLECTINDEX*$2@$', $content);

        // Link to a specific reflect activity by module id.
        $search = "/(" . $base . "\/mod\/reflect\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@REFLECTVIEWBYID*$2@$', $content);

        return $content;
    }
}
