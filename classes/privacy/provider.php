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

namespace mod_reflect\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_reflect.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('reflect_responses', [
            'reflectid' => 'privacy:metadata:reflect_responses:reflectid',
            'questionid' => 'privacy:metadata:reflect_responses:questionid',
            'userid' => 'privacy:metadata:reflect_responses:userid',
            'value' => 'privacy:metadata:reflect_responses:value',
            'responsetext' => 'privacy:metadata:reflect_responses:responsetext',
            'comment' => 'privacy:metadata:reflect_responses:comment',
            'timecreated' => 'privacy:metadata:reflect_responses:timecreated',
            'timemodified' => 'privacy:metadata:reflect_responses:timemodified',
        ], 'privacy:metadata:reflect_responses');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {reflect} r ON r.id = cm.instance
                  JOIN {reflect_responses} rr ON rr.reflectid = r.id
                 WHERE rr.userid = :userid";

        $params = [
            'modname'      => 'reflect',
            'contextlevel' => CONTEXT_MODULE,
            'userid'       => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT rr.userid
                  FROM {reflect_responses} rr
                  JOIN {course_modules} cm ON cm.instance = rr.reflectid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $params = [
            'modname' => 'reflect',
            'cmid'    => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('reflect', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $responses = $DB->get_records('reflect_responses', [
                'reflectid' => $cm->instance,
                'userid'    => $user->id,
            ]);

            if (empty($responses)) {
                continue;
            }

            $exportdata = [];
            foreach ($responses as $response) {
                $exportdata[] = (object)[
                    'questionid'   => $response->questionid,
                    'value'        => $response->value,
                    'responsetext' => $response->responsetext,
                    'comment'      => $response->comment,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($response->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($response->timemodified),
                ];
            }

            writer::with_context($context)->export_data([
                get_string('pluginname', 'mod_reflect'),
                get_string('responses', 'mod_reflect'),
            ], (object)['responses' => $exportdata]);
        }
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if ($cm = get_coursemodule_from_id('reflect', $context->instanceid)) {
            $DB->delete_records('reflect_responses', ['reflectid' => $cm->instance]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('reflect', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['reflectid' => $cm->instance], $inparams);

        $DB->delete_records_select('reflect_responses', "reflectid = :reflectid AND userid $insql", $params);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            if ($cm = get_coursemodule_from_id('reflect', $context->instanceid)) {
                $DB->delete_records('reflect_responses', [
                    'reflectid' => $cm->instance,
                    'userid'    => $userid,
                ]);
            }
        }
    }
}
