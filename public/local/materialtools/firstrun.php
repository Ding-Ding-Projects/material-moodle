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
 * First visit to a new site: choose the site administrator's username and password.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_materialtools\first_admin;

if (!first_admin::pending()) {
    redirect(new moodle_url('/login/index.php'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(first_admin::url());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('firstadmin_title', 'local_materialtools'));

$form = new \local_materialtools\form\firstadmin();
if ($data = $form->get_data()) {
    $data->username = core_text::strtolower(trim($data->username));
    try {
        $user = first_admin::claim($data);
    } catch (moodle_exception $e) {
        redirect(new moodle_url('/login/index.php'), $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    complete_user_login($user);
    redirect(new moodle_url('/admin/index.php'), get_string('firstadmin_done', 'local_materialtools'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('firstadmin_title', 'local_materialtools'));
echo html_writer::tag('p', get_string('firstadmin_desc', 'local_materialtools'), ['class' => 'lead']);
$form->display();
echo $OUTPUT->footer();
