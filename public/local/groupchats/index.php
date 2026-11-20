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
 * My group chats, and every group chat for people who manage them all.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_groupchats\chat;

$c = 'local_groupchats';
$tab = optional_param('tab', 'mine', PARAM_ALPHA);
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use group chats.');
}
$context = context_system::instance();
$PAGE->set_context($context);
$isadmin = chat::is_admin();
if ($tab !== 'all' || !$isadmin) {
    $tab = 'mine';
}
$PAGE->set_url(new moodle_url('/local/groupchats/index.php', ['tab' => $tab]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('groupchats', $c));
$PAGE->set_heading(get_string('groupchats', $c));
$PAGE->add_body_class('local-groupchats-page');

$chats = [];
$i = 0;
foreach (chat::list_chats(0, $tab === 'all') as $record) {
    $creator = $record->creatorid ? core_user::get_user($record->creatorid) : null;
    $chats[] = [
        'id' => (int) $record->id,
        'name' => format_string($record->name ?: get_string('unnamedchat', $c), true, ['context' => $context]),
        'initial' => core_text::strtoupper(core_text::substr(trim($record->name ?: '#'), 0, 1)),
        'membercount' => (int) $record->membercount,
        'creator' => $creator ? fullname($creator) : '',
        'last' => $record->lastmessage ? userdate($record->lastmessage, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('nomessages', $c),
        'openurl' => chat::open_url($record->id)->out(false),
        'manageurl' => (new moodle_url('/local/groupchats/manage.php', ['id' => $record->id]))->out(false),
        'ismember' => (bool) $record->ismember,
        'index' => min($i++, 12),
    ];
}

$data = [
    'chats' => $chats,
    'haschats' => !empty($chats),
    'cancreate' => chat::can_create(),
    'newurl' => (new moodle_url('/local/groupchats/new.php'))->out(false),
    'showtabs' => $isadmin,
    'tabs' => [
        ['label' => get_string('mygroupchats', $c), 'url' => (new moodle_url($PAGE->url, ['tab' => 'mine']))->out(false),
            'active' => $tab === 'mine'],
        ['label' => get_string('allgroupchats', $c), 'url' => (new moodle_url($PAGE->url, ['tab' => 'all']))->out(false),
            'active' => $tab === 'all'],
    ],
    'messagingoff' => empty($CFG->messaging),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_groupchats/index', $data);
echo $OUTPUT->footer();
