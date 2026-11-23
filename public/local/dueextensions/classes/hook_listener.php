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

namespace local_dueextensions;

use core\hook\output\before_footer_html_generation;

/**
 * Adds the due date extension card to assignment pages.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Render the card for students on an assignment's main page.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE, $USER, $OUTPUT;
        try {
            if (during_initial_install() || !isloggedin() || isguestuser() || $PAGE->pagetype !== 'mod-assign-view'
                    || !$PAGE->cm || $PAGE->cm->modname !== 'assign' || optional_param('action', '', PARAM_ALPHA) !== '') {
                return;
            }
            $coursecontext = \context_course::instance($PAGE->course->id);
            if (!has_capability('local/dueextensions:use', $coursecontext)
                    || !has_capability('mod/assign:submit', $PAGE->context)) {
                return;
            }
            $rules = rules::get((int) $PAGE->course->id);
            if (!$rules->enabled) {
                return;
            }
            [$assign] = granter::load((int) $PAGE->cm->id);
            $hook->add_html($OUTPUT->render_from_template('local_dueextensions/card',
                card::context($assign, $rules, (int) $USER->id)));
        } catch (\Throwable $e) {
            // The card is optional; never break the assignment page.
            debugging('Due date extension card failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
