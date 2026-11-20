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
 * Meetups settings.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $c = 'local_meetups';
    $ADMIN->add('localplugins', new admin_category('local_meetups_category', get_string('pluginname', $c)));
    $page = new admin_settingpage('local_meetups_settings', get_string('settings', $c));
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_configcheckbox("$c/enabled", get_string('enabled', $c),
            get_string('enabled_desc', $c), 1));
        $page->add(new admin_setting_configselect("$c/whocancreate", get_string('whocancreate', $c),
            get_string('whocancreate_desc', $c), 'everyone', [
                'everyone' => get_string('whocancreate_everyone', $c),
                'staff' => get_string('whocancreate_staff', $c),
            ]));
        $page->add(new admin_setting_configtext("$c/reminderhours", get_string('reminderhours', $c),
            get_string('reminderhours_desc', $c), 24, PARAM_INT, 4));
        $page->add(new admin_setting_configcheckbox("$c/allowsite", get_string('allowsite', $c),
            get_string('allowsite_desc', $c), 1));
    }
    $ADMIN->add('local_meetups_category', $page);
    $ADMIN->add('local_meetups_category', new admin_externalpage('local_meetups_index', get_string('allmeetups', $c),
        new moodle_url('/local/meetups/index.php'), 'local/meetups:moderate'));
}
