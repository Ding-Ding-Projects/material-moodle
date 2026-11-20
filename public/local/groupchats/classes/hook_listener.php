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

namespace local_groupchats;

use core\hook\output\before_footer_html_generation;
use core_user\hook\extend_user_menu;

/**
 * Adds the "New group chat" button to the messaging drawer and the group chats link to the user menu.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Whether the current user gets the links.
     *
     * @return bool
     */
    protected static function eligible(): bool {
        global $CFG;
        return !during_initial_install() && isloggedin() && !isguestuser() && !empty($CFG->messaging);
    }

    /**
     * Drawer button.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::eligible() || !chat::can_create()) {
            return;
        }
        $PAGE->requires->js_call_amd('local_groupchats/drawer', 'init', [[
            'newurl' => (new \moodle_url('/local/groupchats/new.php'))->out(false),
            'listurl' => (new \moodle_url('/local/groupchats/index.php'))->out(false),
            'newlabel' => get_string('newgroupchat', 'local_groupchats'),
            'listlabel' => get_string('mygroupchats', 'local_groupchats'),
        ]]);
    }

    /**
     * User menu link.
     *
     * @param extend_user_menu $hook
     */
    public static function extend_user_menu(extend_user_menu $hook): void {
        if (!self::eligible()) {
            return;
        }
        $hook->add_menu_item(new \core_user\output\user_action_menu\link(
            new \core\url('/local/groupchats/index.php'),
            get_string('groupchats', 'local_groupchats'),
            null,
            new \core\output\pix_icon('t/message', ''),
        ));
    }
}
