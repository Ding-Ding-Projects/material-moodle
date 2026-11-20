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
 * Meetups callbacks.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add "Meetups" to the course secondary navigation.
 *
 * @param navigation_node $navigation The course administration node.
 * @param stdClass $course
 * @param context_course $context
 */
function local_meetups_extend_navigation_course($navigation, $course, $context) {
    if (!\local_meetups\meetup::enabled() || $course->id == SITEID || !has_capability('local/meetups:view', $context)) {
        return;
    }
    $navigation->add(get_string('meetups', 'local_meetups'),
        new moodle_url('/local/meetups/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM, null, 'local_meetups', new pix_icon('i/calendar', ''));
}

/**
 * Meetup calendar events are visible to the people who can see the meetup.
 *
 * @param calendar_event $event
 * @param int $userid The user the calendar is shown for.
 * @return bool
 */
function local_meetups_core_calendar_is_event_visible($event, $userid = 0) {
    global $DB;
    $meetup = $DB->get_record('local_meetups', ['id' => $event->instance]);
    return $meetup && \local_meetups\meetup::can_view($meetup, (int) $userid);
}
