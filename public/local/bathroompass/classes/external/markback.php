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

namespace local_bathroompass\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_bathroompass\breaks;

/**
 * A teacher marks a student who is out of the room as back.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class markback extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'breakid' => new external_value(PARAM_INT, 'Break id'),
        ]);
    }

    /**
     * Mark the student as back.
     *
     * @param int $breakid
     * @return array
     */
    public static function execute(int $breakid): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['breakid' => $breakid]);
        $record = $DB->get_record('local_bpass_breaks', ['id' => $params['breakid']], '*', MUST_EXIST);
        $context = \context_course::instance($record->courseid);
        self::validate_context($context);
        require_capability('local/bathroompass:viewbreaks', $context);
        $ended = breaks::markback((int) $record->id, (int) $USER->id);
        return [
            'breakid' => (int) $record->id,
            'ended' => (bool) $ended,
            'duration' => $ended ? (int) ($ended->timein - $ended->timeout) : 0,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'breakid' => new external_value(PARAM_INT, 'Break id'),
            'ended' => new external_value(PARAM_BOOL, 'Whether this call ended the break'),
            'duration' => new external_value(PARAM_INT, 'Break length in seconds'),
        ]);
    }
}
