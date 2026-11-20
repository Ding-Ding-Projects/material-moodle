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
 * Meetups web service functions used by the pages.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_meetups_search_users' => [
        'classname' => \local_meetups\external\search_users::class,
        'description' => 'Find people to invite to a meetup.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_meetups_save_vote' => [
        'classname' => \local_meetups\external\save_vote::class,
        'description' => 'Save one answer in a meetup scheduling poll and return the new tally.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_meetups_get_tally' => [
        'classname' => \local_meetups\external\get_tally::class,
        'description' => 'Return the live tally of a meetup scheduling poll.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
