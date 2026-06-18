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
 * View a reflect instance.
 *
 * @package mod_reflect
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('reflect', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/reflect:view', $context);

// Trigger course_module_viewed event.
$event = \mod_reflect\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('reflect', $instance);
$event->trigger();

$PAGE->set_url('/mod/reflect/view.php', ['id' => $cm->id]);
$PAGE->set_title($instance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$canmanage = has_capability('mod/reflect:addinstance', $context);

// Load questions for this instance.
$questions = $DB->get_records('reflect_questions', ['reflectid' => $instance->id], 'sortorder ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading($instance->name);

if ($canmanage) {
    // Teacher view: manage questions inline.
    $templatedata = [
        'cmid'       => $cm->id,
        'instanceid' => $instance->id,
        'questions'  => [],
        'hasquestions' => !empty($questions),
        'canmanage'  => true,
        'noquestions' => get_string('noquestions', 'mod_reflect'),
        'str_addquestion'          => get_string('addquestion', 'mod_reflect'),
        'str_editquestion'         => get_string('editquestion', 'mod_reflect'),
        'str_deletequestion'       => get_string('deletequestion', 'mod_reflect'),
        'str_question'             => get_string('question', 'mod_reflect'),
        'str_responsetype'         => get_string('responsetype', 'mod_reflect'),
        'str_maxgrade'             => get_string('maxgrade', 'mod_reflect'),
        'str_responsetype_numeric' => get_string('responsetype_numeric', 'mod_reflect'),
        'str_responsetype_text'    => get_string('responsetype_text', 'mod_reflect'),
        'str_save'                 => get_string('savechanges'),
        'str_cancel'               => get_string('cancel'),
        'str_confirmdelete'        => get_string('confirmdelete', 'mod_reflect'),
    ];

    foreach ($questions as $q) {
        $responsetypekey = 'responsetype_' . $q->responsetype;
        $templatedata['questions'][] = [
            'id'                => $q->id,
            'question'          => format_text($q->question, $q->questionformat, ['context' => $context]),
            'responsetype'      => $q->responsetype,
            'responsetypelabel' => get_string($responsetypekey, 'mod_reflect'),
            'maxgrade'          => (float) $q->maxgrade,
            'sortorder'         => $q->sortorder,
        ];
    }

    $PAGE->requires->js_call_amd('mod_reflect/manage_questions', 'init', [$cm->id]);
    echo $OUTPUT->render_from_template('mod_reflect/view_teacher', $templatedata);
} else {
    // Student view.
    $templatedata = [
        'cmid'         => $cm->id,
        'instanceid'   => $instance->id,
        'intro'        => format_module_intro('reflect', $instance, $cm->id),
        'allowcomment' => (bool)$instance->allowcomment,
        'hasquestions' => !empty($questions),
        'questions'    => [],
        'str_noquestions' => get_string('noquestions', 'mod_reflect'),
        'str_comment'     => get_string('comment', 'mod_reflect') ?? 'Comentário',
    ];

    // Load user's existing responses.
    $responses = $DB->get_records(
        'reflect_responses',
        ['reflectid' => $instance->id, 'userid' => $USER->id],
        '',
        'questionid, value, responsetext, comment'
    );
    $globalcomment = '';

    foreach ($questions as $q) {
        $val = 0;
        $text = '';
        if (isset($responses[$q->id])) {
            $val = $responses[$q->id]->value !== null ? (float)$responses[$q->id]->value : 0;
            $text = $responses[$q->id]->responsetext ?? '';
            if (!empty($responses[$q->id]->comment)) {
                $globalcomment = $responses[$q->id]->comment;
            }
        }

        $templatedata['questions'][] = [
            'id'        => $q->id,
            'question'  => format_text($q->question, $q->questionformat, ['context' => $context]),
            'isnumeric' => $q->responsetype === 'numeric',
            'istext'    => $q->responsetype === 'text',
            'value'     => $val,
            'text'      => $text,
        ];
    }

    $templatedata['globalcomment'] = $globalcomment;

    // Load JS module for autosave (to be created next).
    $PAGE->requires->js_call_amd('mod_reflect/autosave', 'init', [$cm->id]);
    echo $OUTPUT->render_from_template('mod_reflect/view_student', $templatedata);
}

echo $OUTPUT->footer();
