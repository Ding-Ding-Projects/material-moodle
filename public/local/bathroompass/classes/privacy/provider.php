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

namespace local_bathroompass\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the bathroom pass.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_bpass_breaks', [
            'userid' => 'privacy:metadata:local_bpass_breaks:userid',
            'timeout' => 'privacy:metadata:local_bpass_breaks:timeout',
            'timein' => 'privacy:metadata:local_bpass_breaks:timein',
            'overallowance' => 'privacy:metadata:local_bpass_breaks:overallowance',
            'endedby' => 'privacy:metadata:local_bpass_breaks:endedby',
            'outstatus' => 'privacy:metadata:local_bpass_breaks:outstatus',
            'instatus' => 'privacy:metadata:local_bpass_breaks:instatus',
        ], 'privacy:metadata:local_bpass_breaks');
        $collection->add_external_location_link('webhook', [
            'course' => 'privacy:metadata:webhook:course',
            'time' => 'privacy:metadata:webhook:time',
            'student' => 'privacy:metadata:webhook:student',
        ], 'privacy:metadata:webhook');
        return $collection;
    }

    /**
     * Course contexts where the user took breaks.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql("SELECT ctx.id FROM {local_bpass_breaks} b
                               JOIN {context} ctx ON ctx.instanceid = b.courseid AND ctx.contextlevel = :level
                              WHERE b.userid = :userid", ['level' => CONTEXT_COURSE, 'userid' => $userid]);
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_COURSE) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_bpass_breaks} WHERE courseid = :courseid',
                ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Export a user's breaks.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $rows = $DB->get_records('local_bpass_breaks', ['courseid' => $context->instanceid, 'userid' => $userid],
                'timeout ASC');
            $export = array_values(array_map(fn($r) => [
                'left' => transform::datetime($r->timeout),
                'back' => $r->timein ? transform::datetime($r->timein) : '',
                'overallowance' => transform::yesno($r->overallowance),
                'markedbackbyteacher' => transform::yesno($r->endedby),
                'leavewebhookstatus' => $r->outstatus,
                'backwebhookstatus' => $r->instatus,
            ], $rows));
            writer::with_context($context)->export_data([get_string('pluginname', 'local_bathroompass')],
                (object) ['breaks' => $export]);
        }
    }

    /**
     * Delete everything in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('local_bpass_breaks', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->delete_records('local_bpass_breaks', ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete several users' data in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE || !$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_bpass_breaks', "courseid = :courseid AND userid $in",
            $params + ['courseid' => $context->instanceid]);
    }
}
