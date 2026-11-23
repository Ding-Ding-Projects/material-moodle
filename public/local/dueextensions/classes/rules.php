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
 * A course's due date extension rules, with defaults when the teacher has not set any.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {
    /** @var string[] How an extension is decided. */
    public const MODES = ['instant', 'dice', 'coin', 'wheel'];
    /** @var string[] Allowance periods. */
    public const PERIODS = ['week', 'month', 'course'];
    /** @var int[] Dice the teacher may choose. */
    public const DICE = [4, 6, 8, 12, 20];
    /** @var int Allowance value meaning unlimited. */
    public const UNLIMITED = -1;
    /** @var int Most days one extension may add. */
    public const MAX_DAYS = 60;
    /** @var int Most wheel segments. */
    public const MAX_SEGMENTS = 12;

    /**
     * Default wheel: days and grade deduction in percent. Zero days means denied.
     *
     * @return array[]
     */
    public static function default_wheel(): array {
        return [
            ['days' => 1, 'penalty' => 0], ['days' => 2, 'penalty' => 0], ['days' => 3, 'penalty' => 5],
            ['days' => 5, 'penalty' => 10], ['days' => 7, 'penalty' => 15], ['days' => 0, 'penalty' => 0],
        ];
    }

    /**
     * The rules for a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get(int $courseid): \stdClass {
        global $DB;
        $record = $DB->get_record('local_dueext_rules', ['courseid' => $courseid]);
        $rules = (object) [
            'id' => 0, 'courseid' => $courseid, 'enabled' => 1, 'allowance' => 3, 'period' => 'month',
            'mode' => 'instant', 'instantdays' => 2, 'dicesides' => 6, 'coindays' => 3,
            'wheel' => self::default_wheel(), 'deniedcounts' => 1,
        ];
        if ($record) {
            foreach (['id', 'enabled', 'allowance', 'instantdays', 'dicesides', 'coindays', 'deniedcounts'] as $field) {
                $rules->$field = (int) $record->$field;
            }
            $rules->period = $record->period;
            $rules->mode = $record->mode;
            $rules->wheel = self::clean_wheel(json_decode((string) $record->wheel, true) ?: []) ?: self::default_wheel();
        }
        return self::clean($rules);
    }

    /**
     * Keep every value inside its allowed range.
     *
     * @param \stdClass $rules
     * @return \stdClass
     */
    public static function clean(\stdClass $rules): \stdClass {
        $rules->enabled = empty($rules->enabled) ? 0 : 1;
        $rules->allowance = (int) $rules->allowance < 0 ? self::UNLIMITED : min(100, (int) $rules->allowance);
        $rules->period = in_array($rules->period, self::PERIODS, true) ? $rules->period : 'month';
        $rules->mode = in_array($rules->mode, self::MODES, true) ? $rules->mode : 'instant';
        $rules->instantdays = max(1, min(self::MAX_DAYS, (int) $rules->instantdays));
        $rules->dicesides = in_array((int) $rules->dicesides, self::DICE, true) ? (int) $rules->dicesides : 6;
        $rules->coindays = max(1, min(self::MAX_DAYS, (int) $rules->coindays));
        $rules->wheel = self::clean_wheel($rules->wheel) ?: self::default_wheel();
        $rules->deniedcounts = empty($rules->deniedcounts) ? 0 : 1;
        return $rules;
    }

    /**
     * Normalise wheel segments.
     *
     * @param mixed $wheel
     * @return array[]
     */
    public static function clean_wheel($wheel): array {
        $out = [];
        foreach (is_array($wheel) ? $wheel : [] as $segment) {
            if (!is_array($segment) || count($out) >= self::MAX_SEGMENTS) {
                continue;
            }
            $out[] = [
                'days' => max(0, min(self::MAX_DAYS, (int) ($segment['days'] ?? 0))),
                'penalty' => round(max(0, min(100, (float) ($segment['penalty'] ?? 0))), 2),
            ];
        }
        return count($out) >= 2 ? $out : [];
    }

    /**
     * Save a course's rules.
     *
     * @param \stdClass $rules
     */
    public static function save(\stdClass $rules): void {
        global $DB;
        $rules = self::clean($rules);
        $record = (object) [
            'courseid' => $rules->courseid, 'enabled' => $rules->enabled, 'allowance' => $rules->allowance,
            'period' => $rules->period, 'mode' => $rules->mode, 'instantdays' => $rules->instantdays,
            'dicesides' => $rules->dicesides, 'coindays' => $rules->coindays, 'wheel' => json_encode($rules->wheel),
            'deniedcounts' => $rules->deniedcounts, 'timemodified' => time(),
        ];
        if ($existing = $DB->get_field('local_dueext_rules', 'id', ['courseid' => $rules->courseid])) {
            $record->id = $existing;
            $DB->update_record('local_dueext_rules', $record);
        } else {
            $DB->insert_record('local_dueext_rules', $record);
        }
    }

    /**
     * One line describing how extensions are decided, for students.
     *
     * @param \stdClass $rules
     * @return string
     */
    public static function describe(\stdClass $rules): string {
        $c = 'local_dueextensions';
        switch ($rules->mode) {
            case 'dice':
                return get_string('ruledice', $c, $rules->dicesides);
            case 'coin':
                return get_string('rulecoin', $c, $rules->coindays);
            case 'wheel':
                return get_string('rulewheel', $c);
            default:
                return get_string('ruleinstant', $c, $rules->instantdays);
        }
    }
}
