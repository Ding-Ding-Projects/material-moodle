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

namespace gradepenalty_dueextension;

use core_grades\penalty_container;

/**
 * Applies the grade deduction a student accepted on the extension wheel for this assignment.
 *
 * @package    gradepenalty_dueextension
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class penalty_calculator extends \core_grades\penalty_calculator {
    /**
     * Add the recorded wheel deductions, in percent of the maximum grade.
     *
     * @param penalty_container $container
     */
    public static function calculate_penalty(penalty_container $container): void {
        global $DB;
        $item = $container->get_grade_item();
        if ($item->itemtype !== 'mod' || $item->itemmodule !== 'assign') {
            return;
        }
        $modinfo = get_fast_modinfo($item->courseid);
        $cm = $modinfo->instances['assign'][$item->iteminstance] ?? null;
        if (!$cm) {
            return;
        }
        $percent = (float) $DB->get_field_sql(
            'SELECT COALESCE(SUM(penalty), 0) FROM {local_dueext_requests}
              WHERE cmid = :cmid AND userid = :userid AND granted = 1 AND revoked = 0',
            ['cmid' => $cm->id, 'userid' => $container->get_userid()]);
        $percent = min(100, max(0, $percent));
        if ($percent > 0) {
            $container->aggregate_penalty($container->get_max_grade() * $percent / 100);
        }
    }
}
