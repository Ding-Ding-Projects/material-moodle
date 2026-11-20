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

/**
 * Find people to invite. When the site lets everyone message everyone, all active users can be found;
 * otherwise only people who share a course with the searcher.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_users extends external_api {
    /** @var int Maximum number of results. */
    const LIMIT = 20;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Part of a name'),
        ]);
    }

    /**
     * Search.
     *
     * @param string $query
     * @return array
     */
    public static function execute(string $query): array {
        global $CFG, $DB, $USER;
        ['query' => $query] = self::validate_parameters(self::execute_parameters(), ['query' => $query]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/meetups:view', $context);

        $query = trim($query);
        if (\core_text::strlen($query) < 1) {
            return [];
        }
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $params = [
            'q1' => '%' . $DB->sql_like_escape($query) . '%',
            'q2' => '%' . $DB->sql_like_escape($query) . '%',
            'me' => $USER->id,
            'guest' => $CFG->siteguest,
        ];
        $scope = '';
        if (empty($CFG->messagingallusers) && !has_capability('moodle/user:viewalldetails', $context)) {
            $scope = 'AND EXISTS (SELECT 1
                                    FROM {user_enrolments} ue1
                                    JOIN {enrol} e1 ON e1.id = ue1.enrolid
                                    JOIN {enrol} e2 ON e2.courseid = e1.courseid
                                    JOIN {user_enrolments} ue2 ON ue2.enrolid = e2.id
                                   WHERE ue1.userid = u.id AND ue2.userid = :me2)';
            $params['me2'] = $USER->id;
        }
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, $namefields
                                         FROM {user} u
                                        WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                                          AND u.id <> :me AND u.id <> :guest
                                          AND (" . $DB->sql_like($fullname, ':q1', false, false) . "
                                               OR " . $DB->sql_like('u.username', ':q2', false, false) . ")
                                          $scope
                                     ORDER BY u.lastname, u.firstname", $params, 0, self::LIMIT);
        $result = [];
        foreach ($users as $user) {
            $result[] = ['id' => (int) $user->id, 'fullname' => fullname($user)];
        }
        return $result;
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User id'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name'),
        ]));
    }
}
