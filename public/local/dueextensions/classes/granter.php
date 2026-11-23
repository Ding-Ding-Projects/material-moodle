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
 * Uses one extension: checks eligibility, decides, records and writes the assignment's extension date.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class granter {
    /**
     * Load the assignment for a course module.
     *
     * @param int $cmid
     * @return array [\assign, \cm_info, \stdClass course]
     */
    public static function load(int $cmid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        $context = \context_module::instance($cm->id);
        return [new \assign($context, $cm, $course), $cm, $course];
    }

    /**
     * The student's current effective due date: their extension if later than the assignment's due date.
     *
     * @param \assign $assign
     * @param int $userid
     * @return int 0 when the assignment has no due date
     */
    public static function current_due(\assign $assign, int $userid): int {
        $instance = $assign->get_instance($userid);
        $due = (int) $instance->duedate;
        $flags = $assign->get_user_flags($userid, false);
        if ($flags && !empty($flags->extensionduedate)) {
            $due = max($due, (int) $flags->extensionduedate);
        }
        return $due;
    }

    /**
     * Why the student cannot use an extension here, or null when they can.
     *
     * @param \assign $assign
     * @param \stdClass $rules
     * @param int $userid
     * @return string|null a string identifier
     */
    public static function blocker(\assign $assign, \stdClass $rules, int $userid): ?string {
        $instance = $assign->get_instance($userid);
        if (!$rules->enabled) {
            return 'blockoff';
        }
        if (empty($instance->duedate)) {
            return 'blocknodue';
        }
        if (!has_capability('mod/assign:submit', $assign->get_context(), $userid)) {
            return 'blocknosubmit';
        }
        $submission = $assign->get_user_submission($userid, false);
        if ($submission && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return 'blocksubmitted';
        }
        // Extensions are asked for before the deadline, not after it.
        if (time() >= self::current_due($assign, $userid)) {
            return 'blockpastdue';
        }
        if (ledger::remaining($rules, $userid) === 0) {
            return 'blocknoneleft';
        }
        return null;
    }

    /**
     * Use one extension for the current user.
     *
     * @param int $cmid
     * @return array the request record fields plus remaining
     */
    public static function request(int $cmid): array {
        global $DB, $USER;
        [$assign, $cm, $course] = self::load($cmid);
        $context = \context_course::instance($course->id);
        require_capability('local/dueextensions:use', $context);
        $userid = (int) $USER->id;

        $lock = \core\lock\lock_config::get_lock_factory('local_dueextensions')
            ->get_lock('request_' . $course->id . '_' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('busy', 'local_dueextensions');
        }
        try {
            $rules = rules::get($course->id);
            if ($reason = self::blocker($assign, $rules, $userid)) {
                throw new \moodle_exception($reason, 'local_dueextensions');
            }
            $decision = decider::decide($rules, $userid);
            $olddue = self::current_due($assign, $userid);
            $newdue = $decision['granted'] ? $olddue + $decision['days'] * DAYSECS : $olddue;
            $record = (object) [
                'courseid' => $course->id, 'cmid' => $cm->id, 'userid' => $userid, 'mode' => $rules->mode,
                'outcome' => json_encode($decision['outcome']), 'granted' => $decision['granted'] ? 1 : 0,
                'days' => $decision['granted'] ? $decision['days'] : 0, 'penalty' => $decision['penalty'],
                'counted' => ($decision['granted'] || $rules->deniedcounts) ? 1 : 0,
                'oldduedate' => $olddue, 'newduedate' => $newdue, 'timecreated' => time(),
            ];
            $transaction = $DB->start_delegated_transaction();
            if (!$decision['granted'] && !$record->counted) {
                // The teacher chose that a denied attempt keeps the extension for a later assignment;
                // in exchange this assignment is marked 0. The student is told before they try.
                $decision['outcome']['zeroed'] = true;
                $decision['outcome']['previousgrade'] = self::zero_grade($assign, $userid);
                $record->outcome = json_encode($decision['outcome']);
            }
            $record->id = $DB->insert_record('local_dueext_requests', $record);
            if ($decision['granted']) {
                self::write_extension($assign, $userid, $newdue);
            }
            $transaction->allow_commit();
            \local_dueextensions\event\extension_used::create([
                'context' => \context_module::instance($cm->id), 'objectid' => $record->id, 'relateduserid' => $userid,
                'other' => ['granted' => $record->granted, 'days' => $record->days],
            ])->trigger();
        } finally {
            $lock->release();
        }
        return [$record, $rules, ledger::remaining($rules, $userid), $decision['outcome']];
    }

    /**
     * Write the student's extension date the same way the assignment does, without the teacher-only check.
     * Eligibility has already been checked by the caller.
     *
     * @param \assign $assign
     * @param int $userid
     * @param int $due 0 removes the extension
     */
    public static function write_extension(\assign $assign, int $userid, int $due): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $flags = $assign->get_user_flags($userid, true);
        $instance = $assign->get_instance();
        $flags->extensionduedate = ($due && $due > (int) $instance->duedate) ? $due : 0;
        $assign->update_user_flags($flags);
        \mod_assign\event\extension_granted::create_from_assign($assign, $userid)->trigger();

        $event = $DB->get_record('event', ['userid' => $userid, 'eventtype' => ASSIGN_EVENT_TYPE_EXTENSION,
            'modulename' => 'assign', 'instance' => $instance->id]);
        if (!$flags->extensionduedate) {
            if ($event) {
                \calendar_event::load($event)->delete();
            }
            return;
        }
        if ($event) {
            $event->timestart = $flags->extensionduedate;
            $event->timesort = $flags->extensionduedate;
            $DB->update_record('event', $event);
            return;
        }
        $cm = $assign->get_course_module();
        $new = (object) [
            'type' => CALENDAR_EVENT_TYPE_ACTION,
            'name' => get_string('calendarextension', 'assign', $instance->name),
            'description' => format_module_intro('assign', $instance, $cm->id),
            'format' => FORMAT_HTML, 'courseid' => 0, 'groupid' => 0, 'userid' => $userid,
            'modulename' => 'assign', 'instance' => $instance->id, 'eventtype' => ASSIGN_EVENT_TYPE_EXTENSION,
            'timestart' => $flags->extensionduedate, 'timesort' => $flags->extensionduedate, 'timeduration' => 0,
            'visible' => instance_is_visible('assign', $instance),
        ];
        \calendar_event::create($new, false);
    }

    /**
     * Mark the assignment 0 for the student (the lowest item on a scale), returning the previous grade.
     *
     * @param \assign $assign
     * @param int $userid
     * @return float|null the previous grade, or null when there was none
     */
    public static function zero_grade(\assign $assign, int $userid): ?float {
        global $DB;
        $grade = $assign->get_user_grade($userid, true);
        $previous = ($grade->grade === null || (float) $grade->grade < 0) ? null : (float) $grade->grade;
        $max = (int) $assign->get_instance()->grade;
        if ($max < 0) {
            $scale = $DB->get_record('scale', ['id' => -$max]);
            $grade->grade = $scale ? 1 : 0;
        } else {
            $grade->grade = 0;
        }
        $grade->grader = 0;
        $assign->update_grade($grade);
        return $previous;
    }

    /**
     * Revoke a granted extension: the due date returns to what it was before it, and the attempt no longer counts.
     *
     * @param int $requestid
     */
    public static function revoke(int $requestid): void {
        global $DB, $USER;
        $request = $DB->get_record('local_dueext_requests', ['id' => $requestid], '*', MUST_EXIST);
        require_capability('local/dueextensions:manage', \context_course::instance($request->courseid));
        if ($request->revoked) {
            return;
        }
        [$assign] = self::load((int) $request->cmid);
        $transaction = $DB->start_delegated_transaction();
        $outcome = json_decode((string) $request->outcome, true) ?: [];
        if (!empty($outcome['zeroed'])) {
            // Put back the grade the assignment had before it was marked 0.
            $grade = $assign->get_user_grade((int) $request->userid, true);
            $grade->grade = $outcome['previousgrade'] === null ? -1 : $outcome['previousgrade'];
            $assign->update_grade($grade);
        }
        if ($request->granted) {
            $current = self::current_due($assign, (int) $request->userid);
            // Only roll the date back when no later extension has moved it since.
            if ($current === (int) $request->newduedate) {
                self::write_extension($assign, (int) $request->userid, (int) $request->oldduedate);
            }
        }
        $DB->update_record('local_dueext_requests', (object) ['id' => $request->id, 'revoked' => 1, 'revokedby' => $USER->id]);
        $transaction->allow_commit();
    }
}
