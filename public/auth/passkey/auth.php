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
 * Passkey sign-in: people sign in with a WebAuthn passkey they registered on their own account.
 *
 * The plugin never owns accounts. It adds a sign-in route and a passkey manager to accounts of any authentication
 * method, so manual accounts keep their password as a fallback.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');

/**
 * Passkey authentication plugin.
 */
class auth_plugin_passkey extends auth_plugin_base {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'passkey';
        $this->config = get_config('auth_passkey');
    }

    /**
     * Passwords are never checked by this plugin.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Accounts are not owned here.
     *
     * @return bool
     */
    public function is_internal() {
        return false;
    }

    /**
     * Passwords cannot be changed through this plugin.
     *
     * @return bool
     */
    public function can_change_password() {
        return false;
    }

    /**
     * The fallback link on the sign-in page; the theme script upgrades it to an inline button.
     *
     * @param string $wantsurl
     * @return array
     */
    public function loginpage_idp_list($wantsurl) {
        return [[
            'url' => new moodle_url('/auth/passkey/login.php', ['wantsurl' => $wantsurl]),
            'icon' => new pix_icon('i/passkey', '', 'auth_passkey'),
            'name' => get_string('apasskey', 'auth_passkey'),
        ]];
    }
}
