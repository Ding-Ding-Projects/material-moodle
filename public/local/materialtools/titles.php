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
 * Personal titles: show "Coach" or "Intern" instead of a role name for one person. Permissions are unchanged.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_materialtools\labels;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_materialtools_titles');
$url = new moodle_url('/local/materialtools/titles.php');

if (optional_param('action', '', PARAM_ALPHA) === 'remove' && confirm_sesskey()) {
    labels::set_title(required_param('userid', PARAM_INT), required_param('courseid', PARAM_INT), '');
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = new \local_materialtools\form\title($url);
if ($data = $form->get_data()) {
    labels::set_title((int) $data->userid, (int) ($data->courseid ?: 0), (string) $data->title);
    redirect($url, get_string('titlesaved', 'local_materialtools'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$rows = [];
foreach (labels::all_titles() as $t) {
    $rows[] = [
        fullname($t) . ' (' . s($t->username) . ')',
        $t->courseid ? s($t->coursename) : get_string('wholesite', 'local_materialtools'),
        s($t->title),
        html_writer::link(new moodle_url($url, ['action' => 'remove', 'userid' => $t->userid, 'courseid' => $t->courseid,
            'sesskey' => sesskey()]), get_string('remove'), ['class' => 'btn btn-sm btn-outline-secondary']),
    ];
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('titles_desc', 'local_materialtools'), ['class' => 'lead']);
$form->display();
if ($rows) {
    $table = new html_table();
    $table->head = [get_string('person', 'local_materialtools'), get_string('titlecourse', 'local_materialtools'),
        get_string('titletext', 'local_materialtools'), ''];
    $table->data = $rows;
    $table->attributes['class'] = 'generaltable';
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
