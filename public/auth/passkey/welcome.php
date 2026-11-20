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
 * The optional "add a passkey" step shown once after a new account's first sign-in.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/'));
}

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$skip = optional_param('skip', 0, PARAM_BOOL);
$target = $returnurl ? new moodle_url($returnurl) : new moodle_url('/my/');

if ($skip && confirm_sesskey()) {
    set_user_preference('auth_passkey_offer', 0);
    redirect($target);
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url(new moodle_url('/auth/passkey/welcome.php', ['returnurl' => $returnurl]));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('welcometitle', 'auth_passkey'));

$PAGE->requires->js_call_amd('auth_passkey/passkey', 'initWelcome', [[
    'sesskey' => sesskey(),
    'endpoint' => (new moodle_url('/auth/passkey/ajax.php'))->out(false),
    'returnurl' => $target->out(false),
    'strings' => \auth_passkey\hook_listener::strings(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('auth_passkey/welcome', [
    'skipurl' => (new moodle_url('/auth/passkey/welcome.php', ['skip' => 1, 'sesskey' => sesskey(),
        'returnurl' => $returnurl]))->out(false),
    'firstname' => format_string($USER->firstname),
]);
echo $OUTPUT->footer();
