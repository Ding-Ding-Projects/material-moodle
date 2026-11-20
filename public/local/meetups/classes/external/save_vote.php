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

namespace local_meetups\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_meetups\meetup;

/**
 * Save one poll answer and return the new tally.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_vote extends get_tally {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'meetupid' => new external_value(PARAM_INT, 'Meetup id'),
            'optionid' => new external_value(PARAM_INT, 'Option id'),
            'response' => new external_value(PARAM_INT, '2 yes, 1 if needed, 0 no'),
        ]);
    }

    /**
     * Save the answer.
     *
     * @param int $meetupid
     * @param int $optionid
     * @param int $response
     * @return array
     */
    public static function execute(int $meetupid, int $optionid = 0, int $response = 0): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['meetupid' => $meetupid, 'optionid' => $optionid, 'response' => $response]);
        $record = self::load($params['meetupid']);
        meetup::vote($record, $params['optionid'], $params['response']);
        return meetup::tally($record) + ['status' => $record->status];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return parent::execute_returns();
    }
}
