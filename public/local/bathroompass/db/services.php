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
 * Bathroom pass web service functions.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_bathroompass_leave' => [
        'classname' => \local_bathroompass\external\leave::class,
        'description' => 'Leave the room for a bathroom break. A break is never refused.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/bathroompass:use',
    ],
    'local_bathroompass_back' => [
        'classname' => \local_bathroompass\external\back::class,
        'description' => 'Come back from a bathroom break.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/bathroompass:use',
    ],
    'local_bathroompass_markback' => [
        'classname' => \local_bathroompass\external\markback::class,
        'description' => 'Mark a student who is out of the room as back.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/bathroompass:viewbreaks',
    ],
];
