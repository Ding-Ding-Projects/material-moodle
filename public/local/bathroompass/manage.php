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
 * A course's bathroom pass settings, who is out now, and the break history.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_bathroompass\breaks;
use local_bathroompass\form\settings_form;
use local_bathroompass\settings;
use local_bathroompass\webhook;

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'settings', PARAM_ALPHA);
$markback = optional_param('markback', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
if (!has_any_capability(['local/bathroompass:manage', 'local/bathroompass:viewbreaks',
        'local/bathroompass:configurewebhooks'], $context)) {
    throw new required_capability_exception($context, 'local/bathroompass:viewbreaks', 'nopermissions', '');
}
$canmanage = has_capability('local/bathroompass:manage', $context);
$canwebhooks = has_capability('local/bathroompass:configurewebhooks', $context);
$canview = has_capability('local/bathroompass:viewbreaks', $context);
$cansettings = $canmanage || $canwebhooks;
if (!$cansettings) {
    $tab = 'now';
} else if (!$canview && $tab === 'now') {
    $tab = 'settings';
}

$c = 'local_bathroompass';
$url = new moodle_url('/local/bathroompass/manage.php', ['id' => $course->id, 'tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managetitle', $c));
$PAGE->set_heading(format_string($course->fullname));
$datetime = get_string('strftimebreak', $c);

if ($markback && confirm_sesskey()) {
    require_capability('local/bathroompass:viewbreaks', $context);
    $break = $DB->get_record('local_bpass_breaks', ['id' => $markback, 'courseid' => $course->id], '*', MUST_EXIST);
    breaks::markback((int) $break->id, (int) $USER->id);
    redirect($url, get_string('markedback', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = null;
$testresult = '';
if ($tab === 'settings' && $cansettings) {
    $form = new settings_form($url, ['canmanage' => $canmanage, 'canwebhooks' => $canwebhooks]);
    $current = settings::get($course->id);
    $testdirection = '';
    foreach (['out', 'in'] as $direction) {
        if ($canwebhooks && $form->no_submit_button_pressed() && optional_param('test' . $direction, null, PARAM_RAW) !== null) {
            $testdirection = $direction;
        }
    }
    if ($testdirection !== '') {
        // Send the test event to the address as typed, without saving the form.
        require_sesskey();
        $submitted = $form->get_submitted_data();
        $field = 'webhook' . $testdirection;
        $address = trim((string) ($submitted->$field ?? ''));
        if ($address === '' || !webhook::valid_url($address)) {
            $testresult = $OUTPUT->notification(get_string('webhookinvalid', $c), 'error');
        } else {
            $status = webhook::send_test($address);
            $testresult = webhook::ok($status)
                ? $OUTPUT->notification(get_string('testok', $c, $status), 'success')
                : $OUTPUT->notification($status ? get_string('testfailed', $c, $status) : get_string('testnoresponse', $c), 'error');
        }
    } else {
        if ($form->is_submitted() && ($data = $form->get_data())) {
            if ($canmanage) {
                $current->enabled = $data->enabled;
                $current->passes = !empty($data->unlimited) ? settings::UNLIMITED : (int) $data->passes;
                $current->period = $data->period;
                $current->goldenstep = (int) $data->goldenstep;
            }
            if ($canwebhooks) {
                $current->webhookout = (string) $data->webhookout;
                $current->webhookin = (string) $data->webhookin;
                $current->includename = $data->includename;
            }
            settings::save($current);
            redirect($url, get_string('saved', $c), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $form->set_data([
            'id' => $course->id, 'enabled' => $current->enabled,
            'passes' => $current->passes === settings::UNLIMITED ? 5 : $current->passes,
            'unlimited' => $current->passes === settings::UNLIMITED ? 1 : 0, 'period' => $current->period,
            'goldenstep' => $current->goldenstep, 'webhookout' => $current->webhookout,
            'webhookin' => $current->webhookin, 'includename' => $current->includename,
        ]);
    }
}

/**
 * How a webhook delivery went, as text.
 *
 * @param int $status
 * @return string
 */
function local_bathroompass_status_text(int $status): string {
    if ($status === 0) {
        return get_string('statusnone', 'local_bathroompass');
    }
    return get_string(webhook::ok($status) ? 'statusok' : 'statusfailed', 'local_bathroompass', $status);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetitle', $c));
$tabs = [];
if ($cansettings) {
    $tabs[] = new tabobject('settings', new moodle_url($url, ['tab' => 'settings']), get_string('settingstab', $c));
}
if ($canview) {
    $tabs[] = new tabobject('now', new moodle_url($url, ['tab' => 'now']), get_string('nowtab', $c));
}
echo $OUTPUT->tabtree($tabs, $tab);

if ($form) {
    echo $testresult;
    $form->display();
} else {
    $users = [];
    $names = static function (array $records) use (&$users) {
        $missing = array_diff(array_unique(array_column($records, 'userid')), array_keys($users));
        if ($missing) {
            $users += \core\user::get_users_by_id($missing);
        }
    };

    // Who is out of the room now.
    echo $OUTPUT->heading(get_string('outnow', $c), 3);
    $out = $DB->get_records('local_bpass_breaks', ['courseid' => $course->id, 'timein' => 0], 'timeout ASC');
    if (!$out) {
        echo $OUTPUT->notification(get_string('nooneout', $c), 'info');
    } else {
        $names($out);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable mm-bp-table';
        $table->head = [get_string('student', $c), get_string('left', $c), get_string('outfor', $c),
            get_string('overallowance', $c), get_string('leavewebhook', $c), ''];
        foreach ($out as $b) {
            $button = new single_button(new moodle_url($url, ['tab' => 'now', 'markback' => $b->id, 'sesskey' => sesskey()]),
                get_string('markasback', $c), 'post', single_button::BUTTON_PRIMARY);
            $table->data[] = [
                isset($users[$b->userid]) ? fullname($users[$b->userid]) : '-', userdate($b->timeout, $datetime),
                breaks::duration_text(time() - (int) $b->timeout),
                get_string($b->overallowance ? 'yes' : 'no', 'moodle'), local_bathroompass_status_text((int) $b->outstatus),
                $OUTPUT->render($button),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }

    // History.
    echo $OUTPUT->heading(get_string('history', $c), 3);
    $records = $DB->get_records_select('local_bpass_breaks', 'courseid = :courseid AND timein > 0', ['courseid' => $course->id],
        'timeout DESC', '*', 0, 500);
    if (!$records) {
        echo $OUTPUT->notification(get_string('historyempty', $c), 'info');
    } else {
        $names($records);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable mm-bp-table';
        $table->head = [get_string('student', $c), get_string('left', $c), get_string('back', $c), get_string('duration', $c),
            get_string('overallowance', $c), get_string('leavewebhook', $c), get_string('backwebhook', $c)];
        foreach ($records as $r) {
            $table->data[] = [
                isset($users[$r->userid]) ? fullname($users[$r->userid]) : '-', userdate($r->timeout, $datetime),
                userdate($r->timein, $datetime) . ($r->endedby ? ' ' . get_string('markedbyteacher', $c) : ''),
                breaks::duration_text((int) $r->timein - (int) $r->timeout),
                get_string($r->overallowance ? 'yes' : 'no', 'moodle'),
                local_bathroompass_status_text((int) $r->outstatus), local_bathroompass_status_text((int) $r->instatus),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }
}
echo $OUTPUT->footer();
