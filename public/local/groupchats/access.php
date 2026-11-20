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
 * Messaging access: explains who can message whom and offers a one-click fix.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_groupchats\chat;

admin_externalpage_setup('local_groupchats_access');
$c = 'local_groupchats';
$done = optional_param('done', 0, PARAM_BOOL);

if (optional_param('action', '', PARAM_ALPHA) === 'allowall' && confirm_sesskey()) {
    chat::allow_everyone();
    redirect(new moodle_url($PAGE->url, ['done' => 1]));
}

$report = chat::access_report();
$permissionnames = [CAP_ALLOW => get_string('allow', 'core_role'), CAP_PREVENT => get_string('prevent', 'core_role'),
    CAP_PROHIBIT => get_string('prohibit', 'core_role'), 0 => get_string('notset', 'core_role')];
$checks = [
    ['label' => get_string('check_messaging', $c), 'ok' => $report['messaging'],
        'value' => get_string($report['messaging'] ? 'on' : 'off', $c), 'help' => get_string('check_messaging_desc', $c)],
    ['label' => get_string('check_allusers', $c), 'ok' => $report['messagingallusers'],
        'value' => get_string($report['messagingallusers'] ? 'on' : 'off', $c), 'help' => get_string('check_allusers_desc', $c)],
    ['label' => get_string('check_sendmessage', $c, $report['rolename']), 'ok' => $report['cansend'],
        'value' => $permissionnames[$report['sendmessage']] ?? $report['sendmessage'],
        'help' => get_string('check_sendmessage_desc', $c), 'code' => 'moodle/site:sendmessage'],
    ['label' => get_string('check_create', $c, $report['rolename']), 'ok' => $report['cancreate'],
        'value' => $permissionnames[$report['create']] ?? $report['create'],
        'help' => get_string('check_create_desc', $c), 'code' => 'local/groupchats:create'],
];
$allok = $report['messaging'] && $report['messagingallusers'] && $report['cansend'] && $report['cancreate'];

$data = [
    'checks' => $checks,
    'allok' => $allok,
    'done' => $done,
    'sesskey' => sesskey(),
    'actionurl' => $PAGE->url->out(false),
    'permissionsurl' => (new moodle_url('/admin/roles/define.php', ['action' => 'view',
        'roleid' => $report['roleid']]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('messagingaccess', $c));
echo $OUTPUT->render_from_template('local_groupchats/access', $data);
echo $OUTPUT->footer();
