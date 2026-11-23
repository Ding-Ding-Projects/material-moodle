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
 * A course's bathroom pass settings, with defaults when the teacher has not set any.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {
    /** @var string[] Pass periods. */
    public const PERIODS = ['week', 'month', 'quarter', 'halfyear', 'course'];
    /** @var int Passes value meaning unlimited. */
    public const UNLIMITED = -1;
    /** @var int Most passes per period. */
    public const MAX_PASSES = 100;
    /** @var int Largest golden step, which is also the largest golden chance. */
    public const MAX_STEP = 25;

    /**
     * The settings for a course.
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get(int $courseid): \stdClass {
        global $DB;
        $settings = (object) [
            'id' => 0, 'courseid' => $courseid, 'enabled' => 1, 'passes' => 5, 'period' => 'quarter',
            'goldenstep' => 5, 'webhookout' => '', 'webhookin' => '', 'includename' => 0,
        ];
        if ($record = $DB->get_record('local_bpass_settings', ['courseid' => $courseid])) {
            foreach (['id', 'enabled', 'passes', 'goldenstep', 'includename'] as $field) {
                $settings->$field = (int) $record->$field;
            }
            $settings->period = $record->period;
            $settings->webhookout = (string) $record->webhookout;
            $settings->webhookin = (string) $record->webhookin;
        }
        return self::clean($settings);
    }

    /**
     * Keep every value inside its allowed range.
     *
     * @param \stdClass $settings
     * @return \stdClass
     */
    public static function clean(\stdClass $settings): \stdClass {
        $settings->enabled = empty($settings->enabled) ? 0 : 1;
        $settings->passes = (int) $settings->passes < 0 ? self::UNLIMITED : min(self::MAX_PASSES, (int) $settings->passes);
        $settings->period = in_array($settings->period, self::PERIODS, true) ? $settings->period : 'quarter';
        $settings->goldenstep = max(0, min(self::MAX_STEP, (int) $settings->goldenstep));
        $settings->webhookout = trim((string) $settings->webhookout);
        $settings->webhookin = trim((string) $settings->webhookin);
        $settings->includename = empty($settings->includename) ? 0 : 1;
        return $settings;
    }

    /**
     * Save a course's settings.
     *
     * @param \stdClass $settings
     */
    public static function save(\stdClass $settings): void {
        global $DB;
        $settings = self::clean($settings);
        $record = (object) [
            'courseid' => $settings->courseid, 'enabled' => $settings->enabled, 'passes' => $settings->passes,
            'period' => $settings->period, 'goldenstep' => $settings->goldenstep,
            'webhookout' => $settings->webhookout, 'webhookin' => $settings->webhookin,
            'includename' => $settings->includename, 'timemodified' => time(),
        ];
        if ($existing = $DB->get_field('local_bpass_settings', 'id', ['courseid' => $settings->courseid])) {
            $record->id = $existing;
            $DB->update_record('local_bpass_settings', $record);
        } else {
            $DB->insert_record('local_bpass_settings', $record);
        }
    }
}
