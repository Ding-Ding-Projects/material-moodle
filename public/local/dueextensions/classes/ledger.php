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

/**
 * How many extensions a student has used and has left in the current period.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ledger {
    /**
     * Start and end of the current period, in the student's own time zone.
     *
     * @param \stdClass $rules
     * @param int $userid
     * @param int|null $now
     * @return int[] [start, end]; end is 0 for the whole course.
     */
    public static function period(\stdClass $rules, int $userid, ?int $now = null): array {
        $now = $now ?? time();
        $user = \core_user::get_user($userid);
        $tz = \core_date::get_user_timezone_object($user);
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
        if ($rules->period === 'course') {
            return [0, 0];
        }
        if ($rules->period === 'week') {
            $start = $date->setTime(0, 0)->modify('monday this week');
            return [$start->getTimestamp(), $start->modify('+1 week')->getTimestamp()];
        }
        $start = $date->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)->setTime(0, 0);
        return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
    }

    /**
     * Extensions used in the current period.
     *
     * @param \stdClass $rules
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function used(\stdClass $rules, int $userid, ?int $now = null): int {
        global $DB;
        [$start, $end] = self::period($rules, $userid, $now);
        $where = 'courseid = :courseid AND userid = :userid AND counted = 1 AND revoked = 0';
        $params = ['courseid' => $rules->courseid, 'userid' => $userid];
        if ($end) {
            $where .= ' AND timecreated >= :start AND timecreated < :end';
            $params += ['start' => $start, 'end' => $end];
        }
        return $DB->count_records_select('local_dueext_requests', $where, $params);
    }

    /**
     * Extensions left, or null when unlimited.
     *
     * @param \stdClass $rules
     * @param int $userid
     * @param int|null $now
     * @return int|null
     */
    public static function remaining(\stdClass $rules, int $userid, ?int $now = null): ?int {
        if ($rules->allowance === rules::UNLIMITED) {
            return null;
        }
        return max(0, $rules->allowance - self::used($rules, $userid, $now));
    }
}
