<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduling poll: vote on the proposed times, and the organiser picks the final one.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_meetups\meetup;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$c = 'local_meetups';

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use meetups.');
}
$record = meetup::get($id);
$context = meetup::context($record);
$PAGE->set_context($context);
if ($record->courseid) {
    $PAGE->set_course(get_course($record->courseid));
}
$url = new moodle_url('/local/meetups/poll.php', ['id' => $record->id]);
$PAGE->set_url($url);
if (!meetup::can_view($record)) {
    throw new moodle_exception('errornoaccess', $c);
}
$canmanage = meetup::can_manage($record);

if ($action !== '' && confirm_sesskey()) {
    if ($action === 'finalise' && $canmanage) {
        meetup::finalise($record, required_param('optionid', PARAM_INT));
        redirect(new moodle_url('/local/meetups/view.php', ['id' => $record->id, 'done' => 'finalised']));
    }
    if ($action === 'reopen' && $canmanage && $record->status === meetup::STATUS_SCHEDULED) {
        $record->status = meetup::STATUS_PLANNING;
        $DB->update_record('local_meetups', (object) ['id' => $record->id, 'status' => meetup::STATUS_PLANNING,
            'timemodified' => time()]);
        meetup::sync_calendar($record);
        redirect($url);
    }
    if ($action === 'vote') {
        // Fallback for browsers without JavaScript: one form with an answer per option.
        $votes = optional_param_array('vote', [], PARAM_INT);
        foreach ($votes as $optionid => $response) {
            meetup::vote($record, (int) $optionid, (int) $response);
        }
        redirect(new moodle_url($url, ['done' => 1]));
    }
}

$title = format_string($record->title, true, ['context' => $context]);
$PAGE->set_title(get_string('pollfor', $c, $title));
$PAGE->set_heading($title);
$PAGE->set_pagelayout($record->courseid ? 'incourse' : 'standard');
$PAGE->add_body_class('local-meetups-page');
$PAGE->navbar->add(get_string('meetups', $c), new moodle_url('/local/meetups/index.php',
    ['courseid' => $record->courseid ?: null]));
$PAGE->navbar->add($title, new moodle_url('/local/meetups/view.php', ['id' => $record->id]));
$PAGE->navbar->add(get_string('poll', $c));

$tally = meetup::tally($record);
$bytally = [];
foreach ($tally['options'] as $row) {
    $bytally[$row['id']] = $row;
}
$choices = [
    meetup::VOTE_YES => ['key' => 'yes', 'label' => get_string('vote_yes', $c)],
    meetup::VOTE_IFNEEDED => ['key' => 'ifneeded', 'label' => get_string('vote_ifneeded', $c)],
    meetup::VOTE_NO => ['key' => 'no', 'label' => get_string('vote_no', $c)],
];
$options = [];
$open = $record->status === meetup::STATUS_PLANNING;
foreach (meetup::options($record) as $option) {
    $row = $bytally[$option->id];
    $buttons = [];
    foreach ($choices as $value => $choice) {
        $buttons[] = $choice + ['value' => $value, 'optionid' => (int) $option->id, 'checked' => $row['myvote'] === $value];
    }
    $options[] = $row + [
        'date' => userdate($option->timestart, get_string('strftimedaydate', 'langconfig')),
        'time' => meetup::format_range((int) $option->timestart, (int) $option->timeend),
        'buttons' => $buttons,
        'isfinal' => (int) $record->finaloptionid === (int) $option->id && $record->status === meetup::STATUS_SCHEDULED,
        'percent' => $tally['voters'] ? round(100 * $row['score'] / (2 * $tally['voters'])) : 0,
    ];
}

// Who answered what, for the matrix.
$voters = [];
$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$votes = $DB->get_records_sql("SELECT v.id, v.optionid, v.response, v.userid, $namefields
                                 FROM {local_meetups_vote} v
                                 JOIN {user} u ON u.id = v.userid
                                WHERE v.meetupid = ?
                             ORDER BY u.lastname, u.firstname, v.id", [$record->id]);
foreach ($votes as $vote) {
    if (!isset($voters[$vote->userid])) {
        $voters[$vote->userid] = ['fullname' => fullname($vote), 'answers' => []];
    }
    $voters[$vote->userid]['answers'][$vote->optionid] = (int) $vote->response;
}
$matrix = [];
foreach ($voters as $voter) {
    $cells = [];
    foreach ($options as $option) {
        $answer = $voter['answers'][$option['id']] ?? null;
        $cells[] = $answer === null ? ['key' => 'none', 'label' => '–']
            : ['key' => $choices[$answer]['key'], 'label' => $choices[$answer]['label']];
    }
    $matrix[] = ['fullname' => $voter['fullname'], 'cells' => $cells];
}

$data = [
    'id' => (int) $record->id,
    'title' => $title,
    'isopen' => $open,
    'canmanage' => $canmanage,
    'options' => $options,
    'hasoptions' => !empty($options),
    'voters' => $tally['voters'],
    'matrix' => $matrix,
    'hasmatrix' => !empty($matrix),
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'viewurl' => (new moodle_url('/local/meetups/view.php', ['id' => $record->id]))->out(false),
    'editurl' => (new moodle_url('/local/meetups/edit.php', ['id' => $record->id]))->out(false),
    'isscheduled' => $record->status === meetup::STATUS_SCHEDULED,
    'when' => meetup::when($record),
    'saved' => optional_param('done', 0, PARAM_BOOL),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_meetups/poll', $data);
echo $OUTPUT->footer();
