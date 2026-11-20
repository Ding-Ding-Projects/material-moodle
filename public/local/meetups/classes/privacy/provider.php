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

namespace local_meetups\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider. Meetups live in the system context or in a course context.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** @var string[] Tables with a userid column and a meetupid column. */
    const CHILD_TABLES = ['local_meetups_invite', 'local_meetups_vote', 'local_meetups_rsvp', 'local_meetups_comment'];

    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_meetups', [
            'userid' => 'privacy:metadata:local_meetups:userid',
            'title' => 'privacy:metadata:local_meetups:title',
            'description' => 'privacy:metadata:local_meetups:description',
            'location' => 'privacy:metadata:local_meetups:location',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_meetups');
        $collection->add_database_table('local_meetups_invite', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_meetups_invite');
        $collection->add_database_table('local_meetups_vote', [
            'userid' => 'privacy:metadata:userid',
            'response' => 'privacy:metadata:local_meetups_vote:response',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:local_meetups_vote');
        $collection->add_database_table('local_meetups_rsvp', [
            'userid' => 'privacy:metadata:userid',
            'response' => 'privacy:metadata:local_meetups_rsvp:response',
            'note' => 'privacy:metadata:local_meetups_rsvp:note',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:local_meetups_rsvp');
        $collection->add_database_table('local_meetups_comment', [
            'userid' => 'privacy:metadata:userid',
            'content' => 'privacy:metadata:local_meetups_comment:content',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:local_meetups_comment');
        $collection->add_subsystem_link('core_calendar', [], 'privacy:metadata:core_calendar');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * SQL selecting the meetup ids a user is involved in.
     *
     * @return string
     */
    protected static function involved_sql(): string {
        $parts = ['SELECT id AS meetupid FROM {local_meetups} WHERE userid = :u0'];
        foreach (self::CHILD_TABLES as $i => $table) {
            $parts[] = "SELECT meetupid FROM {{$table}} WHERE userid = :u" . ($i + 1);
        }
        return implode(' UNION ', $parts);
    }

    /**
     * Parameters for involved_sql().
     *
     * @param int $userid
     * @return array
     */
    protected static function involved_params(int $userid): array {
        $params = [];
        for ($i = 0; $i <= count(self::CHILD_TABLES); $i++) {
            $params['u' . $i] = $userid;
        }
        return $params;
    }

    /**
     * Contexts holding the user's data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_meetups} m
                  JOIN {context} ctx ON (ctx.instanceid = m.courseid AND ctx.contextlevel = :course AND m.courseid > 0)
                                     OR (ctx.contextlevel = :system AND m.courseid = 0)
                 WHERE m.id IN (" . self::involved_sql() . ")";
        $list->add_from_sql($sql, ['course' => CONTEXT_COURSE, 'system' => CONTEXT_SYSTEM] + self::involved_params($userid));
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        [$where, $params] = self::meetups_in_context($context);
        if ($where === null) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_meetups} m WHERE $where", $params);
        foreach (self::CHILD_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT t.userid FROM {{$table}} t JOIN {local_meetups} m ON m.id = t.meetupid
                WHERE $where", $params);
        }
    }

    /**
     * WHERE clause selecting the meetups of a context.
     *
     * @param \context $context
     * @return array [where or null, params]
     */
    protected static function meetups_in_context(\context $context): array {
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return ['m.courseid = 0', []];
        }
        if ($context->contextlevel == CONTEXT_COURSE) {
            return ['m.courseid = :courseid', ['courseid' => $context->instanceid]];
        }
        return [null, []];
    }

    /**
     * Export the user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            [$where, $params] = self::meetups_in_context($context);
            if ($where === null) {
                continue;
            }
            $meetups = $DB->get_records_sql("SELECT m.* FROM {local_meetups} m
                WHERE $where AND m.id IN (" . self::involved_sql() . ")", $params + self::involved_params($userid));
            foreach ($meetups as $meetup) {
                $data = (object) [
                    'title' => format_string($meetup->title),
                    'organiser' => transform::yesno((int) $meetup->userid === (int) $userid),
                    'status' => $meetup->status,
                    'timestart' => $meetup->timestart ? transform::datetime($meetup->timestart) : '',
                    'location' => (int) $meetup->userid === (int) $userid ? $meetup->location : '',
                    'description' => (int) $meetup->userid === (int) $userid
                        ? format_text($meetup->description, $meetup->descriptionformat) : '',
                    'invited' => transform::yesno($DB->record_exists('local_meetups_invite',
                        ['meetupid' => $meetup->id, 'userid' => $userid])),
                    'votes' => array_values(array_map(function($vote) {
                        return ['option' => transform::datetime($vote->timestart), 'response' => (int) $vote->response];
                    }, $DB->get_records_sql('SELECT v.id, v.response, o.timestart FROM {local_meetups_vote} v
                        JOIN {local_meetups_option} o ON o.id = v.optionid WHERE v.meetupid = ? AND v.userid = ?',
                        [$meetup->id, $userid]))),
                    'reply' => $DB->get_record('local_meetups_rsvp', ['meetupid' => $meetup->id, 'userid' => $userid],
                        'response, waitlisted, note') ?: null,
                    'comments' => array_values(array_map(function($comment) {
                        return ['content' => $comment->content, 'time' => transform::datetime($comment->timecreated)];
                    }, $DB->get_records('local_meetups_comment', ['meetupid' => $meetup->id, 'userid' => $userid]))),
                ];
                writer::with_context($context)->export_data([get_string('meetups', 'local_meetups'),
                    $meetup->id . ' ' . clean_filename($meetup->title)], $data);
            }
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        [$where, $params] = self::meetups_in_context($context);
        if ($where === null) {
            return;
        }
        foreach ($DB->get_records_sql("SELECT m.* FROM {local_meetups} m WHERE $where", $params) as $meetup) {
            \local_meetups\meetup::delete($meetup);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Delete several users' data in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove users from meetups in a context. Meetups they organised are deleted with everything attached.
     *
     * @param \context $context
     * @param int[] $userids
     */
    protected static function delete_users_in_context(\context $context, array $userids) {
        global $DB;
        [$where, $params] = self::meetups_in_context($context);
        if ($where === null || !$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'pu');
        $owned = $DB->get_records_sql("SELECT m.* FROM {local_meetups} m WHERE $where AND m.userid $insql",
            $params + $inparams);
        foreach ($owned as $meetup) {
            \local_meetups\meetup::delete($meetup);
        }
        foreach (self::CHILD_TABLES as $table) {
            $DB->delete_records_select($table, "userid $insql AND meetupid IN (SELECT m.id FROM {local_meetups} m WHERE $where)",
                $params + $inparams);
        }
    }
}
