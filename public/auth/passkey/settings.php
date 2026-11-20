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
 * Passkey sign-in settings.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('auth_passkey/intro', '', get_string('settings_intro', 'auth_passkey')));
    $settings->add(new admin_setting_configtext('auth_passkey/rpname', get_string('rpname', 'auth_passkey'),
        get_string('rpname_desc', 'auth_passkey'), '', PARAM_TEXT));
    $settings->add(new admin_setting_configselect('auth_passkey/userverification', get_string('userverification', 'auth_passkey'),
        get_string('userverification_desc', 'auth_passkey'), 'required', [
            'required' => get_string('uvrequired', 'auth_passkey'),
            'preferred' => get_string('uvpreferred', 'auth_passkey'),
        ]));
    $settings->add(new admin_setting_configselect('auth_passkey/timeout', get_string('timeout', 'auth_passkey'),
        get_string('timeout_desc', 'auth_passkey'), 120, [60 => '60 s', 120 => '120 s', 300 => '300 s']));
    $settings->add(new admin_setting_configcheckbox('auth_passkey/offeronfirstlogin', get_string('offeronfirstlogin', 'auth_passkey'),
        get_string('offeronfirstlogin_desc', 'auth_passkey'), 1));
}
