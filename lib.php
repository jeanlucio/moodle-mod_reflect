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
    reflect_grade_item_update($data);
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
    reflect_grade_item_update($data);
    // Recalculate grades if the settings changed.
    reflect_update_grades($data);
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
function reflect_grade_item_update($instance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $instance->name, 'idnumber' => $instance->idnumber ?? ''];

    if ($instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $instance->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update('mod/reflect', $instance->course, 'mod', 'reflect', $instance->id, 0, $grades, $params);
}

/**
 * Update grades in the gradebook.
 *
 * @param stdClass $instance
 * @param int $userid Update grade of specific user only.
 * @param bool $nullifnone If true and no responses found, set grade to null.
 */
function reflect_update_grades($instance, int $userid = 0, bool $nullifnone = true) {
    global $DB;

    if ($instance->grade == 0) {
        reflect_grade_item_update($instance);
        return;
    }

    $sql = "SELECT userid FROM {reflect_responses} WHERE reflectid = :rid GROUP BY userid";
    $params = ['rid' => $instance->id];
    if ($userid) {
        $sql .= " HAVING userid = :uid";
        $params['uid'] = $userid;
    }

    $users = $DB->get_records_sql($sql, $params);
    $grades = [];

    // We must fetch questions to know their maxgrade and count.
    $questions = $DB->get_records('reflect_questions', ['reflectid' => $instance->id]);
    $totalquestions = count($questions);

    foreach ($users as $u) {
        $responses = $DB->get_records('reflect_responses', ['reflectid' => $instance->id, 'userid' => $u->userid]);
        $totalscore = 0.0;

        foreach ($responses as $resp) {
            if ($resp->value === null) {
                continue;
            }
            if (isset($questions[$resp->questionid])) {
                $q = $questions[$resp->questionid];
                if ($q->responsetype === 'numeric') {
                    if ($instance->grademethod === 'manual') {
                        $totalscore += ($resp->value / 100) * $q->maxgrade;
                    } else if ($instance->grademethod === 'distribute' && $totalquestions > 0) {
                        $weight = $instance->grade / $totalquestions;
                        $totalscore += ($resp->value / 100) * $weight;
                    }
                }
            }
        }

        $grades[$u->userid] = new stdClass();
        $grades[$u->userid]->userid = $u->userid;
        $grades[$u->userid]->rawgrade = $totalscore;
    }

    if (empty($grades) && $nullifnone && $userid) {
        // Force update to null if specific user requested but has no responses.
        $grades[$userid] = new stdClass();
        $grades[$userid]->userid = $userid;
        $grades[$userid]->rawgrade = null;
    }

    reflect_grade_item_update($instance, $grades);
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
function reflect_get_completion_state(\stdClass $course, \cm_info|\stdClass $cm, int $userid, bool $type): bool {
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
function reflect_get_completion_active_rule_descriptions(\stdClass $course, \cm_info|\stdClass $cm): array {
    global $DB;

    $reflect = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);
    $rules = [];

    if (!empty($reflect->completionsubmit)) {
        $rules[] = get_string('completionsubmit', 'mod_reflect');
    }

    return $rules;
}
