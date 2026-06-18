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

namespace mod_reflect\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;

/**
 * External function to create or update a question in a reflect activity.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_question extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'           => new external_value(PARAM_INT, 'Course module ID'),
            'questionid'     => new external_value(PARAM_INT, 'Question ID (0 for new)', VALUE_DEFAULT, 0),
            'question'       => new external_value(PARAM_RAW, 'Question HTML content'),
            'questionformat' => new external_value(PARAM_INT, 'Text format', VALUE_DEFAULT, FORMAT_HTML),
            'responsetype'   => new external_value(PARAM_ALPHA, 'Response type: numeric or text'),
            'maxgrade'       => new external_value(PARAM_FLOAT, 'Maximum grade for this question', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create or update a question.
     *
     * @param int $cmid Course module ID.
     * @param int $questionid Question ID (0 for new).
     * @param string $question Question HTML content.
     * @param int $questionformat Text format.
     * @param string $responsetype Response type.
     * @param float $maxgrade Maximum grade.
     * @return array Result with questionid and success status.
     */
    public static function execute(
        int $cmid,
        int $questionid,
        string $question,
        int $questionformat,
        string $responsetype,
        float $maxgrade
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'           => $cmid,
            'questionid'     => $questionid,
            'question'       => $question,
            'questionformat' => $questionformat,
            'responsetype'   => $responsetype,
            'maxgrade'       => $maxgrade,
        ]);

        $cm = get_coursemodule_from_id('reflect', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/reflect:addinstance', $context);

        $now = time();

        if ($params['questionid'] > 0) {
            // Update existing question — verify it belongs to this instance.
            $record = $DB->get_record_sql(
                "SELECT q.*
                   FROM {reflect_questions} q
                  WHERE q.id = :id AND q.reflectid = :rid",
                ['id' => $params['questionid'], 'rid' => $cm->instance],
                MUST_EXIST
            );
            $record->question       = $params['question'];
            $record->questionformat = $params['questionformat'];
            $record->responsetype   = $params['responsetype'];
            $record->maxgrade       = $params['maxgrade'];
            $record->timemodified   = $now;
            $DB->update_record('reflect_questions', $record);
            $resultid = $record->id;
        } else {
            // New question — determine next sortorder.
            $maxsort = $DB->get_field_sql(
                "SELECT COALESCE(MAX(sortorder), -1)
                   FROM {reflect_questions}
                  WHERE reflectid = :rid",
                ['rid' => $cm->instance]
            );
            $record = (object) [
                'reflectid'      => $cm->instance,
                'question'       => $params['question'],
                'questionformat' => $params['questionformat'],
                'responsetype'   => $params['responsetype'],
                'maxgrade'       => $params['maxgrade'],
                'sortorder'      => $maxsort + 1,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ];
            $resultid = $DB->insert_record('reflect_questions', $record);
        }

        return [
            'questionid' => $resultid,
            'success'    => true,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'ID of the saved question'),
            'success'    => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
        ]);
    }
}
