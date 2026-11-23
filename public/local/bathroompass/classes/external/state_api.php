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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared parameters and return structure of the student functions, which both return the card's new state.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class state_api extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'out' => new external_value(PARAM_BOOL, 'Whether the student is out of the room'),
            'breakid' => new external_value(PARAM_INT, 'The open break, 0 when none'),
            'timeout' => new external_value(PARAM_INT, 'When the open break started'),
            'now' => new external_value(PARAM_INT, 'Server time'),
            'left' => new external_value(PARAM_INT, 'Passes left, -1 for unlimited'),
            'total' => new external_value(PARAM_INT, 'Passes per period, -1 for unlimited'),
            'humanright' => new external_value(PARAM_BOOL, 'Whether the allowance is used up'),
            'lefttext' => new external_value(PARAM_TEXT, 'Passes left, as text'),
            'iconslabel' => new external_value(PARAM_TEXT, 'Accessible label for the pass icons'),
            'icons' => new external_multiple_structure(new external_single_structure([
                'full' => new external_value(PARAM_BOOL, 'Whether the pass is still available'),
            ])),
            'humanrighttext' => new external_value(PARAM_TEXT, 'The human right statement'),
            'extratext' => new external_value(PARAM_TEXT, 'How extra breaks change the coin flip'),
            'heads' => new external_value(PARAM_INT, 'Heads chance in percent'),
            'golden' => new external_value(PARAM_INT, 'Golden chance in percent'),
            'tails' => new external_value(PARAM_INT, 'Tails chance in percent'),
            'barlabel' => new external_value(PARAM_TEXT, 'Accessible label for the chance bar'),
            'legendheads' => new external_value(PARAM_TEXT, 'Legend text for heads'),
            'legendgolden' => new external_value(PARAM_TEXT, 'Legend text for golden'),
            'legendtails' => new external_value(PARAM_TEXT, 'Legend text for tails'),
            'outnote' => new external_value(PARAM_TEXT, 'Note shown while out of the room'),
            'durationtext' => new external_value(PARAM_TEXT, 'How long the ended break took'),
            'message' => new external_value(PARAM_TEXT, 'Welcome back message, empty unless a break just ended'),
        ]);
    }
}
