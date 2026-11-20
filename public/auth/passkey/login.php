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
 * Passkey sign-in page, used when the sign-in page could not show the inline button.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$wantsurl = optional_param('wantsurl', '', PARAM_LOCALURL);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/auth/passkey/login.php'));
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('signinwithpasskey', 'auth_passkey'));

if (!is_enabled_auth('passkey')) {
    redirect(get_login_url());
}
if (isloggedin() && !isguestuser()) {
    redirect($wantsurl ? new moodle_url($wantsurl) : new moodle_url('/my/'));
}

$PAGE->requires->js_call_amd('auth_passkey/passkey', 'initLoginPage', [[
    'sesskey' => sesskey(),
    'endpoint' => (new moodle_url('/auth/passkey/ajax.php'))->out(false),
    'wantsurl' => $wantsurl,
    'strings' => \auth_passkey\hook_listener::strings(),
]]);

echo $OUTPUT->header();
echo html_writer::start_div('loginform');
echo html_writer::tag('h1', get_string('signinwithpasskey', 'auth_passkey'), ['class' => 'h2']);
echo html_writer::tag('p', get_string('signinwithpasskey_desc', 'auth_passkey'), ['class' => 'text-muted mb-4']);
echo html_writer::div('', '', ['data-region' => 'auth-passkey-slot']);
echo html_writer::tag('noscript', html_writer::div(get_string('errornojs', 'auth_passkey'), 'alert alert-danger'));
echo html_writer::div(html_writer::link(get_login_url(), get_string('usepassword', 'auth_passkey')), 'text-center mt-4');
echo html_writer::end_div();
echo $OUTPUT->footer();
