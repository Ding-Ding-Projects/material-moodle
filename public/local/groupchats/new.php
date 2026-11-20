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
 * Start a new group chat.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_groupchats\chat;

$c = 'local_groupchats';
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot start group chats.');
}
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/groupchats/new.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('newgroupchat', $c));
$PAGE->set_heading(get_string('newgroupchat', $c));
$PAGE->add_body_class('local-groupchats-page');
$PAGE->navbar->add(get_string('groupchats', $c), new moodle_url('/local/groupchats/index.php'));
$PAGE->navbar->add(get_string('newgroupchat', $c));

if (!chat::can_create()) {
    if (empty($CFG->messaging)) {
        throw new moodle_exception('errormessagingoff', $c);
    }
    require_capability('local/groupchats:create', $context);
    require_capability('moodle/site:sendmessage', $context);
}

$form = new \local_groupchats\form\newchat();
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/groupchats/index.php'));
}
if ($data = $form->get_data()) {
    try {
        [$conversation, $refused] = chat::create($data->name, (array) $data->members);
    } catch (moodle_exception $e) {
        if ($e->errorcode !== 'errornomembers') {
            throw $e;
        }
        redirect($PAGE->url, get_string('errornomembersblocked', $c), null, \core\output\notification::NOTIFY_ERROR);
    }
    $params = ['id' => $conversation->id, 'done' => 'created'];
    if ($refused) {
        $params['refused'] = implode(',', array_map(fn($user) => $user->id, $refused));
    }
    redirect(new moodle_url('/local/groupchats/manage.php', $params));
}

echo $OUTPUT->header();
echo html_writer::start_div('local-groupchats local-groupchats-new');
echo html_writer::tag('p', get_string('new_intro', $c), ['class' => 'lead']);
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
