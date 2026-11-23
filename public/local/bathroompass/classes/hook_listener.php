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

namespace local_bathroompass;

use core\hook\output\before_footer_html_generation;

/**
 * Adds the bathroom pass card to course pages.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Render the card for students on a course's main page.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE, $USER, $OUTPUT;
        try {
            if (during_initial_install() || !isloggedin() || isguestuser() || strpos((string) $PAGE->pagetype, 'course-view-') !== 0
                    || empty($PAGE->course->id) || (int) $PAGE->course->id === SITEID) {
                return;
            }
            $coursecontext = \context_course::instance($PAGE->course->id);
            if (!has_capability('local/bathroompass:use', $coursecontext)) {
                return;
            }
            if (!settings::get((int) $PAGE->course->id)->enabled) {
                return;
            }
            $hook->add_html($OUTPUT->render_from_template('local_bathroompass/card',
                card::context((int) $PAGE->course->id, (int) $USER->id)));
        } catch (\Throwable $e) {
            // The card is optional; never break the course page.
            debugging('Bathroom pass card failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
