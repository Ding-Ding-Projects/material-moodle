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

namespace local_meetups;

use core\hook\navigation\primary_extend;
use core_user\hook\extend_user_menu;

/**
 * Adds Meetups to the user menu and the primary navigation.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Whether the current user should see the Meetups links.
     *
     * @return bool
     */
    protected static function visible(): bool {
        if (during_initial_install() || !isloggedin() || isguestuser() || !meetup::enabled()) {
            return false;
        }
        return has_capability('local/meetups:view', \context_system::instance());
    }

    /**
     * User menu link.
     *
     * @param extend_user_menu $hook
     */
    public static function extend_user_menu(extend_user_menu $hook): void {
        if (!self::visible()) {
            return;
        }
        $hook->add_menu_item(new \core_user\output\user_action_menu\link(
            new \core\url('/local/meetups/index.php'),
            get_string('meetups', 'local_meetups'),
            null,
            new \core\output\pix_icon('i/calendar', ''),
        ));
    }

    /**
     * Primary navigation link.
     *
     * @param primary_extend $hook
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        if (!self::visible()) {
            return;
        }
        $hook->get_primaryview()->add(get_string('meetups', 'local_meetups'), new \moodle_url('/local/meetups/index.php'),
            \navigation_node::TYPE_CUSTOM, null, 'local_meetups', new \pix_icon('i/calendar', ''));
    }
}
