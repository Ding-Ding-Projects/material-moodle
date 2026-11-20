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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_meetups\meetup;

/**
 * Live tally of a scheduling poll.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_tally extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'meetupid' => new external_value(PARAM_INT, 'Meetup id'),
        ]);
    }

    /**
     * Load a meetup the current user may see, after validating the context.
     *
     * @param int $meetupid
     * @return \stdClass
     */
    protected static function load(int $meetupid): \stdClass {
        $record = meetup::get($meetupid);
        self::validate_context(meetup::context($record));
        if (!meetup::can_view($record)) {
            throw new \moodle_exception('errornoaccess', 'local_meetups');
        }
        return $record;
    }

    /**
     * Return the tally.
     *
     * @param int $meetupid
     * @return array
     */
    public static function execute(int $meetupid): array {
        ['meetupid' => $meetupid] = self::validate_parameters(self::execute_parameters(), ['meetupid' => $meetupid]);
        $record = self::load($meetupid);
        return meetup::tally($record) + ['status' => $record->status];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Meetup status'),
            'voters' => new external_value(PARAM_INT, 'Number of people who voted'),
            'options' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Option id'),
                'yes' => new external_value(PARAM_INT, 'Yes answers'),
                'ifneeded' => new external_value(PARAM_INT, 'If needed answers'),
                'no' => new external_value(PARAM_INT, 'No answers'),
                'score' => new external_value(PARAM_INT, 'Score: yes counts two, if needed one'),
                'best' => new external_value(PARAM_BOOL, 'Whether this option has the best score'),
                'myvote' => new external_value(PARAM_INT, 'The current user\'s answer, -1 when none'),
            ])),
        ]);
    }
}
