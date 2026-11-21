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
 * Manage your passkeys: add, rename and remove.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$own = ((int) $userid === (int) $USER->id);

if ($own) {
    $target = $USER;
    $url = new moodle_url('/auth/passkey/manage.php');
    $PAGE->set_context(context_user::instance($USER->id));
    $heading = get_string('managepasskeys', 'auth_passkey');
} else {
    // Administrators can see and remove another person's passkeys, for example after a lost device.
    require_capability('moodle/user:update', context_system::instance());
    $target = core_user::get_user($userid, '*', MUST_EXIST);
    $url = new moodle_url('/auth/passkey/manage.php', ['userid' => $target->id]);
    $PAGE->set_context(context_user::instance($target->id));
    $heading = get_string('managepasskeysfor', 'auth_passkey', fullname($target));
}

$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

if ($action === 'rename' && confirm_sesskey()) {
    \auth_passkey\manager::rename($target->id, $id, required_param('name', PARAM_TEXT));
    redirect($url, get_string('renamed', 'auth_passkey'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'delete' && $id) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        \auth_passkey\manager::delete($target->id, $id);
        redirect($url, get_string('removed', 'auth_passkey'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('removeconfirm', 'auth_passkey'),
        new moodle_url($url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]), $url);
    echo $OUTPUT->footer();
    die();
}
if ($action === 'deleteselected') {
    require_sesskey();
    $ids = optional_param_array('ids', [], PARAM_INT);
    if (!$ids) {
        redirect($url, get_string('noneselected', 'auth_passkey'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        foreach ($ids as $one) {
            \auth_passkey\manager::delete($target->id, $one);
        }
        redirect($url, get_string('removedcount', 'auth_passkey', count($ids)), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $params = ['action' => 'deleteselected', 'confirm' => 1, 'sesskey' => sesskey()];
    foreach (array_values($ids) as $i => $one) {
        $params["ids[$i]"] = $one;
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('removeselectedconfirm', 'auth_passkey', count($ids)), new moodle_url($url, $params), $url);
    echo $OUTPUT->footer();
    die();
}

if ($own) {
    $PAGE->requires->js_call_amd('auth_passkey/passkey', 'initManager', [[
        'sesskey' => sesskey(),
        'endpoint' => (new moodle_url('/auth/passkey/ajax.php'))->out(false),
        'returnurl' => $url->out(false),
        'strings' => \auth_passkey\hook_listener::strings(),
    ]]);
}

$keys = \auth_passkey\manager::list_for_user($target->id);
$items = [];
foreach ($keys as $key) {
    $items[] = [
        'id' => $key->id,
        'name' => format_string($key->name),
        'created' => userdate($key->timecreated, get_string('strftimedate', 'langconfig')),
        'lastused' => $key->timelastused ? userdate($key->timelastused, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('never'),
        'renameurl' => $url->out(false),
        'deleteurl' => (new moodle_url($url, ['action' => 'delete', 'id' => $key->id]))->out(false),
        'sesskey' => sesskey(),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('auth_passkey/manage', [
    'items' => $items,
    'haskeys' => !empty($items),
    'enabled' => is_enabled_auth('passkey'),
    'own' => $own,
    'bulkurl' => $url->out(false),
    'sesskey' => sesskey(),
    'multiple' => count($items) > 1,
]);
echo $OUTPUT->footer();
