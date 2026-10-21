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
 * Called by Moodle when building cm_info. Publishes the completion rule so that
 * \mod_reflect\completion\custom_completion knows whether it is switched on, and so that the
 * course page can describe it.
 *
 * @param stdClass $coursemodule The raw course_modules row.
 * @return cached_cm_info|false A populated info object, or false on failure.
 */
function reflect_get_coursemodule_info(stdClass $coursemodule): cached_cm_info|false {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit';
    $instance = $DB->get_record('reflect', ['id' => $coursemodule->instance], $fields);
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    // Once a module defines this callback, core no longer builds the course page description
    // itself, so "Display description on course page" does nothing unless it is set here.
    // Filters are skipped on purpose: this is cached, and they run when it is displayed.
    if (!empty($coursemodule->showdescription)) {
        $info->content = format_module_intro('reflect', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = (int) $instance->completionsubmit;
    }

    return $info;
}

/**
 * Describes the active custom completion rules.
 *
 * @param stdClass|cm_info $cm The course module info.
 * @return array An array of active completion rule descriptions.
 */
function reflect_get_completion_active_rule_descriptions(stdClass|cm_info $cm): array {
    $descriptions = [];

    $rules = $cm->customdata['customcompletionrules'] ?? [];
    if (!empty($rules['completionsubmit'])) {
        $descriptions[] = get_string('completiondetail:submit', 'mod_reflect');
    }

    return $descriptions;
}

/**
 * Adds the reflect section to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 * @return void
 */
function reflect_reset_course_form_definition(MoodleQuickForm $mform): void {
    $mform->addElement('header', 'reflectheader', get_string('modulenameplural', 'mod_reflect'));
    $mform->addElement('advcheckbox', 'reset_reflect_userdata', get_string('resetreflectuserdata', 'mod_reflect'));
}

/**
 * Returns the default values for the reflect course reset form.
 *
 * @param stdClass $course The course being reset.
 * @return array
 */
function reflect_reset_course_form_defaults(stdClass $course): array {
    return ['reset_reflect_userdata' => 1];
}

/**
 * Removes the students' responses from the course's activities when it is reset.
 *
 * Core clears the completion and the grades by itself but only asks the activities that define this
 * function to clear their own data. Left behind, the rows would satisfy the completion rule again and
 * bring the old grade back for a student enrolled again. What the teacher wrote stays.
 *
 * @param stdClass $data Reset form data, must contain courseid.
 * @return array Status rows for the course reset report.
 */
function reflect_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];
    if (empty($data->reset_reflect_userdata)) {
        return $status;
    }

    $instances = $DB->get_records('reflect', ['course' => $data->courseid]);
    if (empty($instances)) {
        return $status;
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED, 'rfid');
    $DB->delete_records_select('reflect_responses', "reflectid $insql", $inparams);

    foreach ($instances as $instance) {
        reflect_grade_item_update($instance, 'reset');
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_reflect'),
        'item'      => get_string('resetreflectuserdata', 'mod_reflect'),
        'error'     => false,
    ];

    return $status;
}
