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


namespace auth_passkey\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: passkeys are personal data of their owner.
 *
 * @package    auth_passkey
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
        $collection->add_database_table('auth_passkey_creds', [
            'userid' => 'privacy:metadata:auth_passkey_creds:userid',
            'name' => 'privacy:metadata:auth_passkey_creds:name',
            'publickey' => 'privacy:metadata:auth_passkey_creds:publickey',
            'timecreated' => 'privacy:metadata:auth_passkey_creds:timecreated',
            'timelastused' => 'privacy:metadata:auth_passkey_creds:timelastused',
        ], 'privacy:metadata:auth_passkey_creds');
        $collection->add_user_preference('auth_passkey_offer', 'privacy:metadata:preference:offer');
        return $collection;
    }

    /**
     * Contexts with data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT ctx.id FROM {context} ctx JOIN {auth_passkey_creds} p ON p.userid = ctx.instanceid
            WHERE ctx.contextlevel = :level AND p.userid = :userid', ['level' => CONTEXT_USER, 'userid' => $userid]);
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {auth_passkey_creds} WHERE userid = :userid',
                ['userid' => $context->instanceid]);
        }
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || $context->instanceid != $contextlist->get_user()->id) {
                continue;
            }
            $rows = $DB->get_records('auth_passkey_creds', ['userid' => $context->instanceid], '',
                'id, name, timecreated, timelastused');
            writer::with_context($context)->export_data([get_string('pluginname', 'auth_passkey')],
                (object) ['passkeys' => array_values($rows)]);
        }
    }

    /**
     * Delete all in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_user) {
            $DB->delete_records('auth_passkey_creds', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete for one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $DB->delete_records('auth_passkey_creds', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Delete for listed users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('auth_passkey_creds', ['userid' => $userid]);
        }
    }
}
