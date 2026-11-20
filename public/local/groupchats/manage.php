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
 * Manage one group chat: rename, members and leaving.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_groupchats\chat;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$done = optional_param('done', '', PARAM_ALPHA);
$refusedparam = optional_param('refused', '', PARAM_SEQUENCE);
$c = 'local_groupchats';

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use group chats.');
}
$context = context_system::instance();
$PAGE->set_context($context);
$url = new moodle_url('/local/groupchats/manage.php', ['id' => $id]);
$PAGE->set_url($url);
$conversation = chat::get($id);
if (!chat::can_view($conversation)) {
    throw new moodle_exception('errornotmember', $c);
}
$canmanage = chat::can_manage($conversation);

if ($action !== '' && confirm_sesskey()) {
    if ($action === 'leave') {
        chat::leave($conversation);
        redirect(new moodle_url('/local/groupchats/index.php'), get_string('left', $c, format_string($conversation->name)),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'remove' && $canmanage) {
        chat::remove_member($conversation, required_param('userid', PARAM_INT));
        redirect(new moodle_url($url, ['done' => 'removed']));
    }
}

$renameform = new \local_groupchats\form\rename_chat($url, ['id' => $id]);
$addform = new \local_groupchats\form\add_members($url, ['id' => $id]);
if ($canmanage) {
    if ($data = $renameform->get_data()) {
        chat::rename($conversation, $data->name);
        redirect(new moodle_url($url, ['done' => 'renamed']));
    }
    if ($data = $addform->get_data()) {
        [$added, $refused] = chat::add_members($conversation, (array) $data->members);
        $params = ['done' => $added ? 'added' : 'nochange'];
        if ($refused) {
            $params['refused'] = implode(',', array_map(fn($user) => $user->id, $refused));
        }
        redirect(new moodle_url($url, $params));
    }
}
$renameform->set_data(['id' => $id, 'name' => $conversation->name]);

$name = format_string($conversation->name ?: get_string('unnamedchat', $c), true, ['context' => $context]);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($name);
$PAGE->set_heading($name);
$PAGE->add_body_class('local-groupchats-page');
$PAGE->navbar->add(get_string('groupchats', $c), new moodle_url('/local/groupchats/index.php'));
$PAGE->navbar->add($name);

$members = [];
foreach (chat::members($conversation->id) as $member) {
    $members[] = [
        'id' => (int) $member->id,
        'fullname' => fullname($member),
        'picture' => $OUTPUT->user_picture($member, ['size' => 35, 'link' => false, 'alttext' => false]),
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $member->id]))->out(false),
        'iscreator' => (int) $member->id === $conversation->creatorid,
        'isme' => (int) $member->id === (int) $USER->id,
        'canremove' => $canmanage && (int) $member->id !== (int) $USER->id,
        'confirmremove' => get_string('confirmremove', $c, fullname($member)),
    ];
}
$refusednames = [];
foreach (array_filter(explode(',', $refusedparam)) as $refusedid) {
    if ($user = core_user::get_user((int) $refusedid)) {
        $refusednames[] = fullname($user);
    }
}
$donemessages = ['created' => 'done_created', 'renamed' => 'done_renamed', 'added' => 'done_added',
    'removed' => 'done_removed', 'nochange' => 'done_nochange'];
$creator = $conversation->creatorid ? core_user::get_user($conversation->creatorid) : null;

$data = [
    'id' => (int) $conversation->id,
    'name' => $name,
    'openurl' => chat::open_url($conversation->id)->out(false),
    'chatlink' => chat::open_url($conversation->id)->out(false),
    'creator' => $creator ? fullname($creator) : '',
    'membercount' => count($members),
    'members' => $members,
    'canmanage' => $canmanage,
    'ismember' => \core_message\api::is_user_in_conversation($USER->id, $conversation->id),
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'renameform' => $canmanage ? $renameform->render() : '',
    'addform' => $canmanage ? $addform->render() : '',
    'done' => isset($donemessages[$done]) ? get_string($donemessages[$done], $c) : '',
    'donecelebrate' => in_array($done, ['created', 'renamed', 'added'], true),
    'refused' => $refusednames ? get_string('refusedblocked', $c, implode(', ', $refusednames)) : '',
    'confirmleave' => get_string('confirmleave', $c),
    'listurl' => (new moodle_url('/local/groupchats/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_groupchats/manage', $data);
echo $OUTPUT->footer();
