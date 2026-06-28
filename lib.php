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
    $id = $DB->insert_record('reflect', $data);
    $data->id = $id;
    \mod_reflect\local\grade_manager::update_grade_item($data);
    return $id;
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
    $result = $DB->update_record('reflect', $data);
    \mod_reflect\local\grade_manager::update_grade_item($data);
    // Recalculate grades if the settings changed.
    \mod_reflect\local\grade_manager::update_grades($data);
    return $result;
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
 * @return mixed True if supported, false if not, null if unknown. String for purpose.
 */
function reflect_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Create or update the grade item for given reflect instance.
 *
 * @param stdClass $instance
 * @param mixed $grades
 * @return int 0 if ok, error code otherwise
 */
function reflect_grade_item_update(stdClass $instance, mixed $grades = null): int {
    return \mod_reflect\local\grade_manager::update_grade_item($instance, $grades);
}

/**
 * Update grades in the gradebook.
 *
 * @param stdClass $instance
 * @param int $userid Update grade of specific user only.
 * @param bool $nullifnone If true and no responses found, set grade to null.
 * @return void
 */
function reflect_update_grades(stdClass $instance, int $userid = 0, bool $nullifnone = true): void {
    \mod_reflect\local\grade_manager::update_grades($instance, $userid, $nullifnone);
}

/**
 * Obtains the automatic completion state for this reflect based on any conditions
 * in reflect settings.
 *
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function reflect_get_completion_state(stdClass $course, cm_info|stdClass $cm, int $userid, bool $type): bool {
    global $DB;

    $reflect = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);

    // If the rule is active.
    if (!empty($reflect->completionsubmit)) {
        // Has the user submitted at least one response?
        $hasresponses = $DB->record_exists('reflect_responses', ['reflectid' => $reflect->id, 'userid' => $userid]);
        if (!$hasresponses) {
            return false;
        }
        return true;
    }

    return $type;
}

/**
 * Returns the custom completion rules for this module.
 *
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm Course-module
 * @return array
 */
function reflect_get_completion_active_rule_descriptions(stdClass $course, cm_info|stdClass $cm): array {
    global $DB;

    $reflect = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);
    $rules = [];

    if (!empty($reflect->completionsubmit)) {
        $rules[] = get_string('completionsubmit', 'mod_reflect');
    }

    return $rules;
}
