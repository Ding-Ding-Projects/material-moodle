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

/**
 * How many passes a student has used and has left in the current period, and the golden chance earned.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ledger {
    /**
     * Start and end of the current period, in the student's own time zone.
     *
     * Quarters are Jan-Mar, Apr-Jun, Jul-Sep and Oct-Dec; half years are Jan-Jun and Jul-Dec.
     *
     * @param \stdClass $settings
     * @param int $userid
     * @param int|null $now
     * @return int[] [start, end]; both 0 for the whole course.
     */
    public static function period(\stdClass $settings, int $userid, ?int $now = null): array {
        $now = $now ?? time();
        if ($settings->period === 'course') {
            return [0, 0];
        }
        $user = \core_user::get_user($userid);
        $tz = \core_date::get_user_timezone_object($user);
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone($tz);
        if ($settings->period === 'week') {
            $start = $date->setTime(0, 0)->modify('monday this week');
            return [$start->getTimestamp(), $start->modify('+1 week')->getTimestamp()];
        }
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        if ($settings->period === 'quarter') {
            $start = $date->setDate($year, 3 * intdiv($month - 1, 3) + 1, 1)->setTime(0, 0);
            return [$start->getTimestamp(), $start->modify('+3 months')->getTimestamp()];
        }
        if ($settings->period === 'halfyear') {
            $start = $date->setDate($year, $month <= 6 ? 1 : 7, 1)->setTime(0, 0);
            return [$start->getTimestamp(), $start->modify('+6 months')->getTimestamp()];
        }
        $start = $date->setDate($year, $month, 1)->setTime(0, 0);
        return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
    }

    /**
     * Count a student's breaks in the current period.
     *
     * @param \stdClass $settings
     * @param int $userid
     * @param bool $over Count breaks beyond the allowance (true) or within it (false).
     * @param int|null $now
     * @return int
     */
    protected static function count(\stdClass $settings, int $userid, bool $over, ?int $now = null): int {
        global $DB;
        [$start, $end] = self::period($settings, $userid, $now);
        $where = 'courseid = :courseid AND userid = :userid AND overallowance = :over';
        $params = ['courseid' => $settings->courseid, 'userid' => $userid, 'over' => $over ? 1 : 0];
        if ($end) {
            $where .= ' AND timeout >= :start AND timeout < :end';
            $params += ['start' => $start, 'end' => $end];
        }
        return $DB->count_records_select('local_bpass_breaks', $where, $params);
    }

    /**
     * Passes used in the current period.
     *
     * @param \stdClass $settings
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function used(\stdClass $settings, int $userid, ?int $now = null): int {
        return self::count($settings, $userid, false, $now);
    }

    /**
     * Breaks taken beyond the allowance in the current period.
     *
     * @param \stdClass $settings
     * @param int $userid
     * @param int|null $now
     * @return int
     */
    public static function extra(\stdClass $settings, int $userid, ?int $now = null): int {
        return self::count($settings, $userid, true, $now);
    }

    /**
     * Passes left, or null when unlimited.
     *
     * @param \stdClass $settings
     * @param int $userid
     * @param int|null $now
     * @return int|null
     */
    public static function remaining(\stdClass $settings, int $userid, ?int $now = null): ?int {
        if ($settings->passes === settings::UNLIMITED) {
            return null;
        }
        return max(0, $settings->passes - self::used($settings, $userid, $now));
    }

    /**
     * The golden chance on extension coin flips: the golden step times the extra breaks, at most 25.
     *
     * @param int $courseid
     * @param int $userid
     * @return int Percent.
     */
    public static function golden_chance(int $courseid, int $userid): int {
        $settings = settings::get($courseid);
        return min(settings::MAX_STEP, $settings->goldenstep * self::extra($settings, $userid));
    }
}
