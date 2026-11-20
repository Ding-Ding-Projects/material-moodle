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
 * Passkey sign-in callbacks.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * After a new account has changed its first password, offer the optional passkey step once.
 *
 * @param stdClass|int|null $courseorid
 * @param bool $autologinguest
 * @param stdClass|null $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 */
function auth_passkey_after_require_login($courseorid = null, $autologinguest = true, $cm = null,
        $setwantsurltome = true, $preventredirect = false) {
    global $FULLME, $SCRIPT;
    if ($preventredirect || AJAX_SCRIPT || CLI_SCRIPT || !isloggedin() || isguestuser()
            || \core\session\manager::is_loggedinas()) {
        return;
    }
    if (!get_user_preferences('auth_passkey_offer', 0) || get_user_preferences('auth_forcepasswordchange', 0)) {
        return;
    }
    if (strpos((string) $SCRIPT, '/auth/passkey/') !== false) {
        return;
    }
    $return = (new moodle_url($FULLME))->out_as_local_url(false);
    redirect(new moodle_url('/auth/passkey/welcome.php', ['returnurl' => $return]));
}

/**
 * Link to the passkey manager from the preferences page.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function auth_passkey_extend_navigation_user_settings(navigation_node $navigation, $user, $usercontext, $course,
        $coursecontext) {
    global $USER;
    if ($user->id != $USER->id || isguestuser()) {
        return;
    }
    $navigation->add(get_string('managepasskeys', 'auth_passkey'), new moodle_url('/auth/passkey/manage.php'),
        navigation_node::TYPE_SETTING, null, 'auth_passkey_manage', new pix_icon('i/passkey', '', 'auth_passkey'));
}
