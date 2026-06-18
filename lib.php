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
 * Library functions for mod_reflect.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a new reflect instance.
 *
 * @param stdClass $data Form data submitted by the teacher.
 * @return int The id of the newly inserted record.
 */
function reflect_add_instance(stdClass $data): int {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('reflect', $data);
}

/**
 * Update an existing reflect instance.
 *
 * @param stdClass $data Form data submitted by the teacher.
 * @return bool True on success.
 */
function reflect_update_instance(stdClass $data): bool {
    global $DB;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('reflect', $data);
}

/**
 * Delete a reflect instance and all associated data.
 *
 * @param int $id Instance id.
 * @return bool True on success.
 */
function reflect_delete_instance(int $id): bool {
    global $DB;
    $DB->delete_records('reflect_responses', ['reflectid' => $id]);
    $DB->delete_records('reflect_questions', ['reflectid' => $id]);
    $DB->delete_records('reflect', ['id' => $id]);
    return true;
}

/**
 * Return the features supported by this module.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return bool|null True if supported, false if not, null if unknown.
 */
function reflect_supports(string $feature): bool|null {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
