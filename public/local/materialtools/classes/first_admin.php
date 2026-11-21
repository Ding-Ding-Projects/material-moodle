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

namespace local_materialtools;

/**
 * First-visit setup: the first person to open a new site chooses the site administrator's username and password.
 *
 * The container installs Moodle with a random throwaway administrator password and marks the site as waiting for
 * its owner. Until someone claims it, every page sends visitors to the setup page and nobody can sign in.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class first_admin {
    /** @var string Config name of the waiting flag. */
    const FLAG = 'firstadminpending';

    /** @var string[] Script prefixes that keep working while the site waits, so the setup page can load. */
    const ALLOWED = ['/local/materialtools/firstrun.php', '/theme/', '/lib/', '/pluginfile.php', '/favicon.ico',
        '/webservice/', '/admin/cli/'];

    /**
     * Whether the site is still waiting for its administrator.
     *
     * @return bool
     */
    public static function pending(): bool {
        if (during_initial_install()) {
            return false;
        }
        return !empty(get_config('local_materialtools', self::FLAG));
    }

    /**
     * The setup page address.
     *
     * @return \moodle_url
     */
    public static function url(): \moodle_url {
        return new \moodle_url('/local/materialtools/firstrun.php');
    }

    /**
     * Send ordinary page requests to the setup page while the site waits.
     */
    public static function redirect_if_pending(): void {
        global $SCRIPT;
        if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER) || headers_sent()) {
            return;
        }
        if (!self::pending()) {
            return;
        }
        $script = (string) ($SCRIPT ?? '');
        if ($script === '') {
            return;
        }
        foreach (self::ALLOWED as $prefix) {
            if (strpos($script, $prefix) === 0) {
                return;
            }
        }
        header('Location: ' . self::url()->out(false), true, 303);
        exit;
    }

    /**
     * Turn the throwaway administrator into the owner's account. Only the first claim wins.
     *
     * @param \stdClass $data username, password, email, firstname, lastname
     * @return \stdClass The updated administrator record.
     */
    public static function claim(\stdClass $data): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $factory = \core\lock\lock_config::get_lock_factory('local_materialtools_firstadmin');
        $lock = $factory->get_lock('claim', 10);
        if (!$lock) {
            throw new \moodle_exception('firstadmin_busy', 'local_materialtools');
        }
        try {
            // Read the flag from the database, not the cache, so a second claim always loses.
            $waiting = $DB->get_field('config_plugins', 'value',
                ['plugin' => 'local_materialtools', 'name' => self::FLAG]);
            if (empty($waiting)) {
                throw new \moodle_exception('firstadmin_taken', 'local_materialtools');
            }
            $admin = get_admin();
            user_update_user((object) [
                'id' => $admin->id,
                'username' => $data->username,
                'email' => $data->email,
                'firstname' => $data->firstname,
                'lastname' => $data->lastname,
            ], false, false);
            $user = $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST);
            update_internal_user_password($user, $data->password);
            unset_user_preference('auth_forcepasswordchange', $user);
            unset_config(self::FLAG, 'local_materialtools');
            return $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST);
        } finally {
            $lock->release();
        }
    }
}
