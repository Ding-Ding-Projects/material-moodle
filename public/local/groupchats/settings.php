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
 * Group chats in Site administration.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $c = 'local_groupchats';
    $ADMIN->add('localplugins', new admin_category('local_groupchats_category', get_string('pluginname', $c)));
    $ADMIN->add('local_groupchats_category', new admin_externalpage('local_groupchats_access',
        get_string('messagingaccess', $c), new moodle_url('/local/groupchats/access.php'), 'moodle/site:config'));
    $ADMIN->add('local_groupchats_category', new admin_externalpage('local_groupchats_all',
        get_string('allgroupchats', $c), new moodle_url('/local/groupchats/index.php', ['tab' => 'all']),
        'local/groupchats:manageall'));
}
