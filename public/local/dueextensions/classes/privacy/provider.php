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

namespace local_dueextensions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for due date extensions.
 *
 * @package    local_dueextensions
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
        $collection->add_database_table('local_dueext_requests', [
            'userid' => 'privacy:metadata:local_dueext_requests:userid',
            'cmid' => 'privacy:metadata:local_dueext_requests:cmid',
            'outcome' => 'privacy:metadata:local_dueext_requests:outcome',
            'days' => 'privacy:metadata:local_dueext_requests:days',
            'penalty' => 'privacy:metadata:local_dueext_requests:penalty',
            'timecreated' => 'privacy:metadata:local_dueext_requests:timecreated',
        ], 'privacy:metadata:local_dueext_requests');
        $collection->add_external_location_link('dimsum', [], 'privacy:metadata:dimsum');
        return $collection;
    }

    /**
     * Module contexts where the user used extensions.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql("SELECT ctx.id FROM {local_dueext_requests} r
                               JOIN {context} ctx ON ctx.instanceid = r.cmid AND ctx.contextlevel = :level
                              WHERE r.userid = :userid", ['level' => CONTEXT_MODULE, 'userid' => $userid]);
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_MODULE) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_dueext_requests} WHERE cmid = :cmid',
                ['cmid' => $context->instanceid]);
        }
    }

    /**
     * Export a user's extensions.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $rows = $DB->get_records('local_dueext_requests', ['cmid' => $context->instanceid, 'userid' => $userid]);
            $export = array_values(array_map(fn($r) => [
                'mode' => $r->mode, 'granted' => transform::yesno($r->granted), 'days' => $r->days,
                'penalty' => $r->penalty, 'outcome' => $r->outcome, 'revoked' => transform::yesno($r->revoked),
                'timecreated' => transform::datetime($r->timecreated),
            ], $rows));
            writer::with_context($context)->export_data([get_string('pluginname', 'local_dueextensions')],
                (object) ['extensions' => $export]);
        }
    }

    /**
     * Delete everything in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $DB->delete_records('local_dueext_requests', ['cmid' => $context->instanceid]);
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
            if ($context->contextlevel == CONTEXT_MODULE) {
                $DB->delete_records('local_dueext_requests', ['cmid' => $context->instanceid, 'userid' => $userid]);
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
        if ($context->contextlevel != CONTEXT_MODULE || !$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_dueext_requests', "cmid = :cmid AND userid $in",
            $params + ['cmid' => $context->instanceid]);
    }
}
