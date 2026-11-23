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

use local_bathroompass\event\break_ended;
use local_bathroompass\event\break_started;

/**
 * Starting and ending bathroom breaks. A break is never refused: with no passes left it is recorded as over the allowance.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class breaks {
    /**
     * A student's open break in a course, if any.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null
     */
    public static function open_break(int $courseid, int $userid): ?\stdClass {
        global $DB;
        $record = $DB->get_record_select('local_bpass_breaks', 'courseid = :courseid AND userid = :userid AND timein = 0',
            ['courseid' => $courseid, 'userid' => $userid], '*', IGNORE_MULTIPLE);
        return $record ?: null;
    }

    /**
     * Take a lock so that a double click cannot open two breaks.
     *
     * @param int $courseid
     * @param int $userid
     * @return \core\lock\lock
     */
    protected static function lock(int $courseid, int $userid): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('local_bathroompass')->get_lock("break_{$courseid}_{$userid}", 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_bathroompass');
        }
        return $lock;
    }

    /**
     * Leave the room. Returns the open break when the student is already out.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass The open break.
     */
    public static function leave(int $courseid, int $userid): \stdClass {
        global $DB;
        $settings = settings::get($courseid);
        if (!$settings->enabled) {
            throw new \moodle_exception('disabled', 'local_bathroompass');
        }
        $lock = self::lock($courseid, $userid);
        try {
            if ($open = self::open_break($courseid, $userid)) {
                return $open;
            }
            $remaining = ledger::remaining($settings, $userid);
            $now = time();
            $record = (object) [
                'courseid' => $courseid, 'userid' => $userid, 'timeout' => $now, 'timein' => 0,
                'overallowance' => ($remaining !== null && $remaining <= 0) ? 1 : 0, 'endedby' => 0,
                'outstatus' => 0, 'instatus' => 0, 'timecreated' => $now,
            ];
            $record->id = $DB->insert_record('local_bpass_breaks', $record);
        } finally {
            $lock->release();
        }
        break_started::create([
            'context' => \context_course::instance($courseid), 'objectid' => $record->id, 'relateduserid' => $userid,
            'other' => ['overallowance' => (bool) $record->overallowance],
        ])->trigger();
        // A webhook failure never blocks the break; its status is recorded for the teacher.
        $record->outstatus = self::notify($settings, $record, 'left');
        $DB->set_field('local_bpass_breaks', 'outstatus', $record->outstatus, ['id' => $record->id]);
        return $record;
    }

    /**
     * Come back from a break.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $endedby 0 when the student ends it, else the teacher's user id.
     * @return \stdClass|null The ended break, or null when the student was not out.
     */
    public static function back(int $courseid, int $userid, int $endedby = 0): ?\stdClass {
        global $DB;
        $settings = settings::get($courseid);
        $lock = self::lock($courseid, $userid);
        try {
            $record = self::open_break($courseid, $userid);
            if (!$record) {
                return null;
            }
            $record->timein = max(time(), (int) $record->timeout);
            $record->endedby = $endedby;
            $DB->update_record('local_bpass_breaks', $record);
        } finally {
            $lock->release();
        }
        break_ended::create([
            'context' => \context_course::instance($courseid), 'objectid' => $record->id, 'relateduserid' => $userid,
            'other' => ['duration' => $record->timein - $record->timeout, 'endedby' => $endedby],
        ])->trigger();
        $record->instatus = self::notify($settings, $record, 'back');
        $DB->set_field('local_bpass_breaks', 'instatus', $record->instatus, ['id' => $record->id]);
        return $record;
    }

    /**
     * A teacher marks a student as back.
     *
     * @param int $breakid
     * @param int $teacherid
     * @return \stdClass|null The ended break, or null when it had already ended.
     */
    public static function markback(int $breakid, int $teacherid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_bpass_breaks', ['id' => $breakid], '*', MUST_EXIST);
        if ($record->timein) {
            return null;
        }
        return self::back((int) $record->courseid, (int) $record->userid, $teacherid);
    }

    /**
     * A duration as minutes and seconds.
     *
     * @param int $seconds
     * @return string
     */
    public static function duration_text(int $seconds): string {
        $seconds = max(0, $seconds);
        return get_string('durationfmt', 'local_bathroompass', (object) ['min' => intdiv($seconds, 60), 'sec' => $seconds % 60]);
    }

    /**
     * Send the webhook for an event.
     *
     * @param \stdClass $settings
     * @param \stdClass $break
     * @param string $event left or back
     * @return int The HTTP status, or 0 when nothing was sent or it failed.
     */
    protected static function notify(\stdClass $settings, \stdClass $break, string $event): int {
        global $DB;
        $url = $event === 'left' ? $settings->webhookout : $settings->webhookin;
        if ($url === '') {
            return 0;
        }
        $payload = [
            'event' => $event,
            'course' => $DB->get_field('course', 'shortname', ['id' => $break->courseid]),
            'courseid' => (int) $break->courseid,
            'time' => $event === 'left' ? (int) $break->timeout : (int) $break->timein,
            'over_allowance' => (bool) $break->overallowance,
        ];
        if ($settings->includename) {
            $payload['student'] = (string) $DB->get_field('user', 'firstname', ['id' => $break->userid]);
        }
        if ($event === 'back') {
            $payload['duration_seconds'] = (int) $break->timein - (int) $break->timeout;
        }
        return webhook::post($url, $payload);
    }
}
