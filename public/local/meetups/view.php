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
 * One meetup: details, replies, attendees and comments.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_meetups\meetup;

$id = required_param('id', PARAM_INT);
$token = optional_param('token', '', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$done = optional_param('done', '', PARAM_ALPHA);
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
$url = new moodle_url('/local/meetups/view.php', ['id' => $record->id]);
$PAGE->set_url($url);

if (!meetup::can_view($record)) {
    if (!meetup::join_with_token($record, $token)) {
        throw new moodle_exception('errornoaccess', $c);
    }
    redirect(new moodle_url($url, ['done' => 'joined']));
}
$canmanage = meetup::can_manage($record);

if ($action !== '' && confirm_sesskey()) {
    switch ($action) {
        case 'rsvp':
            $reply = meetup::rsvp($record, required_param('response', PARAM_ALPHA), optional_param('note', '', PARAM_TEXT));
            redirect(new moodle_url($url, ['done' => $reply->waitlisted ? 'waitlisted' : 'rsvp']));
            break;
        case 'comment':
            meetup::add_comment($record, required_param('content', PARAM_TEXT));
            redirect(new moodle_url($url, ['done' => 'commented']) . '#local-meetups-comments');
            break;
        case 'deletecomment':
            meetup::delete_comment($record, required_param('commentid', PARAM_INT));
            redirect(new moodle_url($url) . '#local-meetups-comments');
            break;
        case 'cancel':
            if (!$canmanage) {
                throw new moodle_exception('errornoaccess', $c);
            }
            meetup::cancel($record);
            redirect(new moodle_url($url, ['done' => 'cancelled']));
            break;
        case 'delete':
            if (!$canmanage) {
                throw new moodle_exception('errornoaccess', $c);
            }
            meetup::delete($record);
            redirect(new moodle_url('/local/meetups/index.php', ['courseid' => $record->courseid ?: null]),
                get_string('deleted', $c), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

$title = format_string($record->title, true, ['context' => $context]);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout($record->courseid ? 'incourse' : 'standard');
$PAGE->add_body_class('local-meetups-page');
$PAGE->navbar->add(get_string('meetups', $c), new moodle_url('/local/meetups/index.php',
    ['courseid' => $record->courseid ?: null]));
$PAGE->navbar->add($title);

$card = meetup::export_card($record);
$myreply = $DB->get_record('local_meetups_rsvp', ['meetupid' => $record->id, 'userid' => $USER->id]);
$responses = [];
foreach (meetup::RESPONSES as $response) {
    $responses[] = [
        'value' => $response,
        'label' => get_string('rsvp_' . $response, $c),
        'checked' => $myreply && $myreply->response === $response,
    ];
}
$groups = [];
$attendees = meetup::attendees($record);
foreach (['going', 'waitlist', 'maybe', 'notgoing'] as $key) {
    $people = [];
    foreach ($attendees[$key] as $row) {
        $people[] = [
            'fullname' => fullname($row),
            'note' => $row->note,
            'profileurl' => (new moodle_url('/user/profile.php', ['id' => $row->userid]))->out(false),
            'picture' => $OUTPUT->user_picture($row, ['size' => 32, 'link' => false, 'alttext' => false]),
        ];
    }
    $groups[] = ['key' => $key, 'label' => get_string($key === 'waitlist' ? 'waitlist' : 'rsvp_' . $key, $c),
        'count' => count($people), 'people' => $people, 'haspeople' => !empty($people)];
}
$comments = [];
foreach (meetup::comments($record) as $comment) {
    $comments[] = [
        'id' => (int) $comment->id,
        'fullname' => fullname($comment),
        'picture' => $OUTPUT->user_picture($comment, ['size' => 35, 'link' => false, 'alttext' => false]),
        'time' => userdate($comment->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'content' => format_text($comment->content, FORMAT_PLAIN, ['context' => $context]),
        'candelete' => (int) $comment->userid === (int) $USER->id || $canmanage,
    ];
}

$scheduled = $record->status === meetup::STATUS_SCHEDULED;
$invitelink = new moodle_url('/local/meetups/view.php', ['id' => $record->id]);
if ($record->audience === 'invite') {
    $invitelink->param('token', $record->token);
}
$donemessages = ['created' => 'done_created', 'saved' => 'done_saved', 'rsvp' => 'done_rsvp',
    'waitlisted' => 'done_waitlisted', 'commented' => 'done_commented', 'finalised' => 'done_finalised',
    'joined' => 'done_joined', 'cancelled' => 'done_cancelled'];

$data = $card + [
    'description' => format_text($record->description, $record->descriptionformat, ['context' => $context]),
    'hasdescription' => trim(strip_tags($record->description)) !== '',
    'location' => $record->type !== 'online' ? $record->location : '',
    'onlineurl' => $record->type !== 'inperson' ? $record->onlineurl : '',
    'invitelink' => $invitelink->out(false),
    'isinviteonly' => $record->audience === 'invite',
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'canmanage' => $canmanage,
    'isscheduled' => $scheduled,
    'editurl' => (new moodle_url('/local/meetups/edit.php', ['id' => $record->id]))->out(false),
    'pollurl' => (new moodle_url('/local/meetups/poll.php', ['id' => $record->id]))->out(false),
    'exporturl' => (new moodle_url('/local/meetups/export.php', ['id' => $record->id]))->out(false),
    'canrsvp' => $scheduled && !meetup::rsvp_closed($record),
    'rsvpclosed' => $scheduled && meetup::rsvp_closed($record),
    'responses' => $responses,
    'mynote' => $myreply ? $myreply->note : '',
    'iswaitlisted' => $myreply && $myreply->response === 'going' && $myreply->waitlisted,
    'isfull' => $scheduled && meetup::is_full($record, (int) $USER->id),
    'deadline' => $record->rsvpdeadline ? userdate($record->rsvpdeadline, get_string('strftimedaydatetime', 'langconfig')) : '',
    'attendeegroups' => $groups,
    'comments' => $comments,
    'hascomments' => !empty($comments),
    'done' => isset($donemessages[$done]) ? get_string($donemessages[$done], $c) : '',
    'donecelebrate' => in_array($done, ['created', 'saved', 'rsvp', 'commented', 'finalised', 'joined'], true),
    'confirmcancel' => get_string('confirmcancel', $c),
    'confirmdelete' => get_string('confirmdelete', $c),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_meetups/view', $data);
echo $OUTPUT->footer();
