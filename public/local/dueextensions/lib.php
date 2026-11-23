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
 * Due date extension navigation.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the rules page to the course's More menu for teachers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_dueextensions_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if (has_any_capability(['local/dueextensions:manage', 'local/dueextensions:viewlog'], $context)) {
        $navigation->add(get_string('managetitle', 'local_dueextensions'),
            new moodle_url('/local/dueextensions/manage.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING, null, 'local_dueextensions', new pix_icon('i/calendareventtime', ''));
    }
}
