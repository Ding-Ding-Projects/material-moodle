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
 * Add a student and get a ready-to-paste credentials message for Discord.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_materialtools_newstudent');

$form = new \local_materialtools\form\newstudent();
$result = null;

if ($data = $form->get_data()) {
    $password = ($data->passwordmode ?? 'generate') === 'custom'
        ? $data->custompassword : \local_materialtools\student_creator::generate_password();
    if (empty($data->username)) {
        $data->username = \local_materialtools\student_creator::suggest_username($data->firstname, $data->lastname);
    }
    $data->password = $password;
    $user = \local_materialtools\student_creator::create($data);
    $result = [
        'fullname' => fullname($user),
        'username' => $user->username,
        'password' => $password,
        'message' => \local_materialtools\student_creator::message($user, $password),
        'profileurl' => (new moodle_url('/user/view.php', ['id' => $user->id]))->out(false),
        'againurl' => $PAGE->url->out(false),
        'vocabulary' => !empty($data->vocabulary),
    ];
}

echo $OUTPUT->header();
if ($result) {
    echo $OUTPUT->render_from_template('local_materialtools/credentials', $result);
} else {
    echo html_writer::tag('p', get_string('newstudent_desc', 'local_materialtools'), ['class' => 'lead']);
    $form->display();
}
echo $OUTPUT->footer();
