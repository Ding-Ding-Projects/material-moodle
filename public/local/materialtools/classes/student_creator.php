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
 * Creates student accounts and the ready-to-paste credentials message.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_creator {
    /** @var string[] Short, easy-to-type words for readable temporary passwords. */
    private const WORDS = ['Maple', 'Otter', 'River', 'Cedar', 'Comet', 'Harbor', 'Lantern', 'Meadow', 'Pebble', 'Sparrow',
        'Thistle', 'Willow', 'Amber', 'Breeze', 'Canyon', 'Delta', 'Ember', 'Fable', 'Glacier', 'Hazel', 'Island', 'Juniper',
        'Kestrel', 'Lagoon', 'Mosaic', 'Nectar', 'Orchid', 'Prairie', 'Quartz', 'Raven', 'Summit', 'Tundra', 'Velvet',
        'Walnut', 'Yarrow', 'Zephyr', 'Birch', 'Coral', 'Falcon', 'Garnet'];

    /** @var string Default Discord template. */
    public const DEFAULT_TEMPLATE = "**{sitename}** sign-in for {fullname}\n{siteurl}\nUsername:\n```\n{username}\n```\n" .
        "Password:\n```\n{password}\n```\nYou will choose a new password the first time you sign in. " .
        "After that you can add a passkey for faster sign-in.";

    /**
     * A temporary password that meets the site password policy.
     *
     * @return string
     */
    public static function generate_password(): string {
        global $CFG;
        require_once($CFG->libdir . '/moodlelib.php');
        $style = get_config('local_materialtools', 'passwordstyle') ?: 'words';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            if ($style === 'random') {
                $length = max(10, (int) (get_config('local_materialtools', 'passwordlength') ?: 14));
                $password = generate_password($length);
            } else {
                $password = self::WORDS[random_int(0, count(self::WORDS) - 1)] . '-' .
                    self::WORDS[random_int(0, count(self::WORDS) - 1)] . '-' . random_int(1000, 9999) . '!';
            }
            $error = '';
            if (check_password_policy($password, $error)) {
                return $password;
            }
        }
        return generate_password(16);
    }

    /**
     * A free username built from the names.
     *
     * @param string $first
     * @param string $last
     * @return string
     */
    public static function suggest_username(string $first, string $last): string {
        global $DB;
        $base = \core_text::strtolower(trim($first) . '.' . trim($last));
        $base = clean_param(str_replace(' ', '', $base), PARAM_USERNAME);
        $base = trim($base, '.') ?: 'student';
        $username = $base;
        $n = 2;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $GLOBALS['CFG']->mnet_localhost_id])) {
            $username = $base . $n++;
        }
        return $username;
    }

    /**
     * Create the account.
     *
     * @param \stdClass $data Form data: firstname, lastname, email, username, password, cohortid, courseid, vocabulary.
     * @return \stdClass The new user.
     */
    public static function create(\stdClass $data): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $user = (object) [
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => \core_text::strtolower(trim($data->username)),
            'firstname' => trim($data->firstname),
            'lastname' => trim($data->lastname),
            'email' => trim((string) ($data->email ?? '')),
            'password' => $data->password,
            'lang' => $CFG->lang,
        ];
        if ($user->email === '') {
            // Moodle asks people without an email address to complete their profile at every sign-in. A reserved
            // ".invalid" address keeps that out of the way and email is switched off for the account.
            $user->email = $user->username . '@students.invalid';
            $user->emailstop = 1;
        }
        $user->id = user_create_user($user, true, true);

        // A temporary password must be replaced at the first sign-in.
        set_user_preference('auth_forcepasswordchange', 1, $user->id);
        if (get_config('auth_passkey', 'version') && get_config('auth_passkey', 'offeronfirstlogin') !== '0') {
            set_user_preference('auth_passkey_offer', 1, $user->id);
        }
        if (!empty($data->title)) {
            labels::set_title($user->id, 0, (string) $data->title);
        }
        if (!empty($data->vocabulary)) {
            vocabulary::set_user($user->id, true);
        }
        if (!empty($data->cohortid)) {
            cohort_add_member((int) $data->cohortid, $user->id);
        }
        if (!empty($data->courseid)) {
            $instance = null;
            foreach (enrol_get_instances((int) $data->courseid, true) as $candidate) {
                if ($candidate->enrol === 'manual') {
                    $instance = $candidate;
                    break;
                }
            }
            $plugin = enrol_get_plugin('manual');
            if ($instance && $plugin) {
                $roleid = (int) (get_config('local_materialtools', 'studentrole') ?: $instance->roleid);
                $plugin->enrol_user($instance, $user->id, $roleid);
            }
        }
        return \core_user::get_user($user->id);
    }

    /**
     * The ready-to-paste message.
     *
     * @param \stdClass $user
     * @param string $password
     * @return string
     */
    public static function message(\stdClass $user, string $password): string {
        global $CFG, $SITE;
        $template = (string) get_config('local_materialtools', 'discordtemplate');
        if (trim($template) === '') {
            $template = self::DEFAULT_TEMPLATE;
        }
        return strtr($template, [
            '{sitename}' => format_string($SITE->fullname, true, ['escape' => false]),
            '{siteurl}' => $CFG->wwwroot . '/login/index.php',
            '{fullname}' => fullname($user),
            '{firstname}' => $user->firstname,
            '{username}' => $user->username,
            '{password}' => $password,
        ]);
    }
}
