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


namespace auth_passkey;

use core\hook\output\before_footer_html_generation;

/**
 * Adds the inline passkey button to the standard sign-in page.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Strings the browser needs.
     *
     * @return array
     */
    public static function strings(): array {
        $out = [];
        foreach (['signinwithpasskey', 'waiting', 'signedin', 'errorgeneric', 'errorunsupported', 'cancelled', 'added',
                'addpasskey', 'adding', 'orusername', 'stepdone'] as $key) {
            $out[$key] = get_string($key, 'auth_passkey');
        }
        return $out;
    }

    /**
     * On the sign-in page, put the passkey button above the username field.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !is_enabled_auth('passkey')) {
            return;
        }
        if ($PAGE->pagetype !== 'login-index' || (isloggedin() && !isguestuser())) {
            return;
        }
        $PAGE->requires->js_call_amd('auth_passkey/passkey', 'initLoginForm', [[
            'sesskey' => sesskey(),
            'endpoint' => (new \moodle_url('/auth/passkey/ajax.php'))->out(false),
            'wantsurl' => '',
            'strings' => self::strings(),
        ]]);
    }
}
