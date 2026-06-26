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
 * Report view showing all student responses.
 *
 * @package    mod_reflect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('reflect', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('reflect', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/reflect:viewreports', $context);

$PAGE->set_url('/mod/reflect/report.php', ['id' => $cm->id]);
$PAGE->set_title($instance->name . ': ' . get_string('responses', 'mod_reflect'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('responses', 'mod_reflect'));

// Fetch all questions for table headers.
$questions = $DB->get_records('reflect_questions', ['reflectid' => $instance->id], 'sortorder ASC');

// Get all enrolled users who can submit. Use the user fields API so fullname()
// and user_picture() receive every name and picture field they require.
$userfieldsapi = \core_user\fields::for_userpic();
$userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
$enrolledusers = get_enrolled_users($context, 'mod/reflect:submit', 0, $userfields);

$templatedata = [
    'questions' => [],
    'users' => [],
    'hasdata' => !empty($enrolledusers) && !empty($questions),
    'str_user' => get_string('user'),
    'str_comment' => get_string('comment', 'mod_reflect'),
    'str_nodatafound' => get_string('nodatafound', 'mod_reflect'),
];

foreach ($questions as $q) {
    $templatedata['questions'][] = [
        'id' => $q->id,
        'title' => strip_tags(format_text($q->question, $q->questionformat, ['context' => $context])),
        'isnumeric' => $q->responsetype === 'numeric',
    ];
}

if (!empty($enrolledusers)) {
    // Fetch all responses for this instance.
    $sql = "SELECT r.*
              FROM {reflect_responses} r
             WHERE r.reflectid = :rid";
    $allresponses = $DB->get_records_sql($sql, ['rid' => $instance->id]);

    // Group responses by userid.
    $responsesbyuser = [];
    foreach ($allresponses as $resp) {
        if (!isset($responsesbyuser[$resp->userid])) {
            $responsesbyuser[$resp->userid] = [];
        }
        $responsesbyuser[$resp->userid][$resp->questionid] = $resp;
    }

    foreach ($enrolledusers as $user) {
        $userrow = [
            'fullname' => fullname($user),
            'picture' => $OUTPUT->user_picture($user, ['courseid' => $course->id]),
            'responses' => [],
            'hasglobalcomment' => false,
            'globalcomment' => '',
        ];

        foreach ($questions as $q) {
            $respdata = [
                'hasresponse' => false,
                'value' => '-',
                'text' => '-',
                'isnumeric' => $q->responsetype === 'numeric',
            ];
            if (isset($responsesbyuser[$user->id][$q->id])) {
                $resp = $responsesbyuser[$user->id][$q->id];
                $respdata['hasresponse'] = true;
                if ($q->responsetype === 'numeric' && $resp->value !== null) {
                    $respdata['value'] = (float)$resp->value;
                } else if ($q->responsetype === 'text' && $resp->responsetext !== null) {
                    $respdata['text'] = format_text($resp->responsetext, FORMAT_HTML);
                    $respdata['value'] = null; // Don't output the hyphen.
                }

                if (!empty($resp->comment)) {
                    $userrow['hasglobalcomment'] = true;
                    $userrow['globalcomment'] = format_text($resp->comment, FORMAT_HTML);
                }
            }
            $userrow['responses'][] = $respdata;
        }

        $templatedata['users'][] = $userrow;
    }
}

echo $OUTPUT->render_from_template('mod_reflect/report', $templatedata);

echo $OUTPUT->footer();
