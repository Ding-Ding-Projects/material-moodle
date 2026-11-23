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

namespace local_dueextensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dueextensions\granter;

/**
 * Use one due date extension on an assignment.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Assignment course module id'),
        ]);
    }

    /**
     * Use the extension.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [, $cm] = granter::load($params['cmid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/assign:view', $context);
        [$record, $rules, $remaining, $outcome] = granter::request($params['cmid']);
        $format = get_string('strftimedue', 'local_dueextensions');
        return [
            'mode' => $record->mode,
            'granted' => (bool) $record->granted,
            'days' => (int) $record->days,
            'penalty' => (float) $record->penalty,
            'outcome' => json_encode($outcome),
            'newdue' => (int) $record->newduedate,
            'newduetext' => userdate($record->newduedate, $format),
            'remaining' => $remaining === null ? -1 : $remaining,
            'counted' => (bool) $record->counted,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mode' => new external_value(PARAM_ALPHA, 'instant, dice, coin or wheel'),
            'granted' => new external_value(PARAM_BOOL, 'Whether the extension was granted'),
            'days' => new external_value(PARAM_INT, 'Days added'),
            'penalty' => new external_value(PARAM_FLOAT, 'Grade deduction in percent'),
            'outcome' => new external_value(PARAM_RAW, 'JSON: what the animation should land on'),
            'newdue' => new external_value(PARAM_INT, 'New due date'),
            'newduetext' => new external_value(PARAM_TEXT, 'New due date, formatted for the user'),
            'remaining' => new external_value(PARAM_INT, 'Extensions left, -1 for unlimited'),
            'counted' => new external_value(PARAM_BOOL, 'Whether this attempt used an extension'),
        ]);
    }
}
