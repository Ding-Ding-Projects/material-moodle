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
 * Bathroom pass navigation and the golden chance callback.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the bathroom pass page to the course's More menu for teachers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_bathroompass_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if (has_any_capability(['local/bathroompass:manage', 'local/bathroompass:viewbreaks',
            'local/bathroompass:configurewebhooks'], $context)) {
        $navigation->add(get_string('managetitle', 'local_bathroompass'),
            new moodle_url('/local/bathroompass/manage.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_bathroompass', new pix_icon('i/withsubcat', ''));
    }
}

/**
 * The golden chance a student has earned on due date extension coin flips.
 *
 * Every break taken beyond the allowance in the current pass period turns the course's golden step
 * (in percent) of the heads chance into a golden chance, up to 25 percent. The due date extension
 * plugin calls this through component_callback('local_bathroompass', 'golden_chance', [$courseid, $userid], 0).
 *
 * @param int $courseid
 * @param int $userid
 * @return int Percent between 0 and 25.
 */
function local_bathroompass_golden_chance(int $courseid, int $userid): int {
    return \local_bathroompass\ledger::golden_chance($courseid, $userid);
}
