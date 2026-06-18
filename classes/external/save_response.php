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
 * External function to save a student response or comment.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_response extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module ID'),
            'questionid'   => new external_value(PARAM_INT, 'Question ID'),
            'value'        => new external_value(PARAM_FLOAT, 'Numeric value', VALUE_DEFAULT, null),
            'responsetext' => new external_value(PARAM_RAW, 'Text response', VALUE_DEFAULT, null),
            'comment'      => new external_value(PARAM_RAW, 'Optional comment', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Save the student's response.
     *
     * @param int $cmid Course module ID.
     * @param int $questionid Question ID.
     * @param float|null $value Numeric response value.
     * @param string|null $responsetext Text response.
     * @param string|null $comment Optional comment.
     * @return array Result with success status and timestamp.
     */
    public static function execute(
        int $cmid,
        int $questionid,
        ?float $value = null,
        ?string $responsetext = null,
        ?string $comment = null
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'questionid'   => $questionid,
            'value'        => $value,
            'responsetext' => $responsetext,
            'comment'      => $comment,
        ]);

        $cm = get_coursemodule_from_id('reflect', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/reflect:submit', $context);

        $instance = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);

        // Verify the question belongs to this instance.
        $question = $DB->get_record(
            'reflect_questions',
            ['id' => $params['questionid'], 'reflectid' => $instance->id],
            '*',
            MUST_EXIST
        );

        $now = time();

        // Check if a response already exists for this user and question.
        $record = $DB->get_record(
            'reflect_responses',
            ['questionid' => $question->id, 'userid' => $USER->id]
        );

        if ($record) {
            // Update only provided fields.
            if ($params['value'] !== null) {
                $record->value = $params['value'];
            }
            if ($params['responsetext'] !== null) {
                $record->responsetext = $params['responsetext'];
            }
            if ($params['comment'] !== null && $instance->allowcomment) {
                $record->comment = $params['comment'];
            }
            $record->timemodified = $now;
            $DB->update_record('reflect_responses', $record);
        } else {
            // Insert new response.
            $record = (object) [
                'reflectid'    => $instance->id,
                'questionid'   => $question->id,
                'userid'       => $USER->id,
                'value'        => $params['value'],
                'responsetext' => $params['responsetext'],
                'comment'      => ($instance->allowcomment ? $params['comment'] : null),
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('reflect_responses', $record);
        }

        // Trigger grade update for the whole activity.
        require_once(__DIR__ . '/../../lib.php');
        reflect_update_grades($instance, $USER->id);

        return [
            'success'  => true,
            'saved_at' => $now,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'saved_at' => new external_value(PARAM_INT, 'Timestamp when saved'),
        ]);
    }
}
