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
 * A course's due date extension rules and log.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_dueextensions\form\rules_form;
use local_dueextensions\granter;
use local_dueextensions\rules;

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'rules', PARAM_ALPHA);
$revoke = optional_param('revoke', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
if (!has_any_capability(['local/dueextensions:manage', 'local/dueextensions:viewlog'], $context)) {
    throw new required_capability_exception($context, 'local/dueextensions:viewlog', 'nopermissions', '');
}
$canmanage = has_capability('local/dueextensions:manage', $context);
if (!$canmanage) {
    $tab = 'log';
}

$c = 'local_dueextensions';
$url = new moodle_url('/local/dueextensions/manage.php', ['id' => $course->id, 'tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managetitle', $c));
$PAGE->set_heading(format_string($course->fullname));

if ($revoke && confirm_sesskey()) {
    require_capability('local/dueextensions:manage', $context);
    $request = $DB->get_record('local_dueext_requests', ['id' => $revoke, 'courseid' => $course->id], '*', MUST_EXIST);
    granter::revoke((int) $request->id);
    redirect($url, get_string('revoked', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = null;
if ($tab === 'rules') {
    $form = new rules_form($url);
    $current = rules::get($course->id);
    if ($form->is_submitted() && ($data = $form->get_data())) {
        $current->enabled = $data->enabled;
        $current->allowance = !empty($data->unlimited) ? rules::UNLIMITED : (int) $data->allowance;
        $current->period = $data->period;
        $current->mode = $data->mode;
        $current->instantdays = $data->instantdays;
        $current->dicesides = $data->dicesides;
        $current->coindays = $data->coindays;
        if ($data->mode === 'wheel') {
            $current->wheel = rules_form::parse_wheel((string) $data->wheeltext);
        }
        $current->deniedcounts = $data->deniedcounts;
        rules::save($current);
        redirect($url, get_string('saved', $c), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $form->set_data([
        'id' => $course->id, 'enabled' => $current->enabled,
        'allowance' => $current->allowance === rules::UNLIMITED ? 3 : $current->allowance,
        'unlimited' => $current->allowance === rules::UNLIMITED ? 1 : 0, 'period' => $current->period,
        'mode' => $current->mode, 'instantdays' => $current->instantdays, 'dicesides' => $current->dicesides,
        'coindays' => $current->coindays, 'wheeltext' => rules_form::wheel_text($current->wheel),
        'deniedcounts' => $current->deniedcounts,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetitle', $c));
$tabs = [];
if ($canmanage) {
    $tabs[] = new tabobject('rules', new moodle_url($url, ['tab' => 'rules']), get_string('settingstab', $c));
}
$tabs[] = new tabobject('log', new moodle_url($url, ['tab' => 'log']), get_string('logtab', $c));
echo $OUTPUT->tabtree($tabs, $tab);

if ($form) {
    $form->display();
} else {
    $records = $DB->get_records('local_dueext_requests', ['courseid' => $course->id], 'timecreated DESC', '*', 0, 500);
    if (!$records) {
        echo $OUTPUT->notification(get_string('logempty', $c), 'info');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable mm-ext-log';
        $table->head = [get_string('logstudent', $c), get_string('logassignment', $c), get_string('logmode', $c),
            get_string('logresult', $c), get_string('logdays', $c), get_string('logpenalty', $c), get_string('logtime', $c), ''];
        $modinfo = get_fast_modinfo($course);
        $users = \core\user::get_users_by_id(array_unique(array_column($records, 'userid')));
        foreach ($records as $r) {
            $outcome = json_decode((string) $r->outcome, true) ?: [];
            if ($r->revoked) {
                $result = get_string('logrevoked', $c);
            } else if (($outcome['side'] ?? '') === 'golden') {
                $result = get_string('loggolden', $c);
            } else {
                $result = get_string($r->granted ? 'loggranted' : 'logdenied', $c);
            }
            $cmname = isset($modinfo->cms[$r->cmid]) ? $modinfo->cms[$r->cmid]->get_formatted_name() : '-';
            $action = '';
            if ($canmanage && !$r->revoked) {
                $button = new single_button(new moodle_url($url, ['revoke' => $r->id, 'sesskey' => sesskey()]),
                    get_string('revoke', $c), 'post', single_button::BUTTON_SECONDARY);
                $button->add_confirm_action(get_string('revokeconfirm', $c));
                $action = $OUTPUT->render($button);
            }
            $table->data[] = [
                isset($users[$r->userid]) ? fullname($users[$r->userid]) : '-', $cmname,
                get_string('mode' . $r->mode, $c), $result, (int) $r->days,
                ((float) $r->penalty > 0 ? format_float($r->penalty, 2, true, true) . '%' : '-'),
                userdate($r->timecreated, get_string('strftimedatetimeshort', 'langconfig')), $action,
            ];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }
}
echo $OUTPUT->footer();
