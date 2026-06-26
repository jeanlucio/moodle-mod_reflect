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
 * Upgrade steps for mod_reflect.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the upgrade steps from the given old version.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_reflect_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061800) {
        // Step 1: Create the reflect_questions table.
        $table = new xmldb_table('reflect_questions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('reflectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('question', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('questionformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('responsetype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'numeric');
        $table->add_field('maxgrade', XMLDB_TYPE_NUMBER, '10', null, XMLDB_NOTNULL, null, '0', 5);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_reflect', XMLDB_KEY_FOREIGN, ['reflectid'], 'reflect', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Step 2: Add questionid field to reflect_responses before migration.
        $restable = new xmldb_table('reflect_responses');
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reflectid');
        if (!$dbman->field_exists($restable, $field)) {
            $dbman->add_field($restable, $field);
        }

        // Step 3: Migrate existing single-question data from {reflect} to {reflect_questions}.
        $instances = $DB->get_records('reflect', null, '', 'id, question, questionformat, responsetype, grade');
        foreach ($instances as $instance) {
            if (!empty($instance->question)) {
                $now = time();
                $questionrecord = (object) [
                    'reflectid'      => $instance->id,
                    'question'       => $instance->question,
                    'questionformat' => $instance->questionformat,
                    'responsetype'   => $instance->responsetype,
                    'maxgrade'       => $instance->grade,
                    'sortorder'      => 0,
                    'timecreated'    => $now,
                    'timemodified'   => $now,
                ];
                $questionid = $DB->insert_record('reflect_questions', $questionrecord);

                // Update existing responses to point to the migrated question.
                $DB->execute(
                    "UPDATE {reflect_responses} SET questionid = :qid WHERE reflectid = :rid",
                    ['qid' => $questionid, 'rid' => $instance->id]
                );
            }
        }

        // Step 4: Drop the old unique key and add the new one on reflect_responses.
        $key = new xmldb_key('uq_reflect_user', XMLDB_KEY_UNIQUE, ['reflectid', 'userid']);
        $dbman->drop_key($restable, $key);

        $fkquestion = new xmldb_key('fk_question', XMLDB_KEY_FOREIGN, ['questionid'], 'reflect_questions', ['id']);
        $dbman->add_key($restable, $fkquestion);

        $newkey = new xmldb_key('uq_question_user', XMLDB_KEY_UNIQUE, ['questionid', 'userid']);
        $dbman->add_key($restable, $newkey);

        // Step 6: Add grademethod field to reflect.
        $table = new xmldb_table('reflect');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'manual', 'allowcomment');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Step 7: Drop legacy fields from reflect.
        $table = new xmldb_table('reflect');
        $legacyfields = ['question', 'questionformat', 'responsetype'];
        foreach ($legacyfields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061800, 'reflect');
    }

    if ($oldversion < 2026062600) {
        $table = new xmldb_table('reflect');
        $field = new xmldb_field('completionsubmit', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grade');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026062600, 'reflect');
    }

    return true;
}
