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
 * Grade management for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reflect\local;

use stdClass;

/**
 * Grade management class for mod_reflect.
 */
class grade_manager {
    /**
     * Create or update the grade item for given reflect instance.
     *
     * @param stdClass $instance The reflect instance object.
     * @param mixed $grades Optional grades to be updated.
     * @return int 0 if ok, error code otherwise.
     */
    public static function update_grade_item(stdClass $instance, mixed $grades = null): int {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $params = [
            'itemname' => $instance->name,
            'idnumber' => $instance->idnumber ?? '',
        ];

        if (property_exists($instance, 'grade') && (int)$instance->grade > 0) {
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
     * @param stdClass $instance The reflect instance object.
     * @param int $userid Update grade of specific user only.
     * @param bool $nullifnone If true and no responses found, set grade to null.
     * @return void
     */
    public static function update_grades(stdClass $instance, int $userid = 0, bool $nullifnone = true): void {
        global $DB;

        if (!property_exists($instance, 'grade') || (int)$instance->grade === 0) {
            self::update_grade_item($instance);
            return;
        }

        $questions = $DB->get_records('reflect_questions', ['reflectid' => $instance->id]);
        $totalquestions = count($questions);

        $params = ['rid' => $instance->id];
        $sql = "SELECT * FROM {reflect_responses} WHERE reflectid = :rid";
        if ($userid > 0) {
            $sql .= " AND userid = :uid";
            $params['uid'] = $userid;
        }

        // Bulk load all relevant responses to avoid N+1 query.
        $allresponses = $DB->get_records_sql($sql, $params);

        $userresponses = [];
        foreach ($allresponses as $resp) {
            if (!isset($userresponses[$resp->userid])) {
                $userresponses[$resp->userid] = [];
            }
            $userresponses[$resp->userid][] = $resp;
        }

        // Ensure we process the requested user even if they have no responses.
        if ($userid > 0 && empty($userresponses[$userid])) {
            $userresponses[$userid] = [];
        }

        $grades = [];
        foreach ($userresponses as $uid => $responses) {
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

            if (empty($responses) && $nullifnone && $userid > 0) {
                $totalscore = null;
            }

            $grades[$uid] = (object) [
                'userid' => $uid,
                'rawgrade' => $totalscore,
            ];
        }

        self::update_grade_item($instance, $grades);
    }
}
