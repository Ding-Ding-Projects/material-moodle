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

namespace local_groupchats\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider. Chat members and messages live in core messaging, which exports and deletes them; this plugin
 * only records who started each chat, in the starter's user context.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_groupchats', [
            'conversationid' => 'privacy:metadata:local_groupchats:conversationid',
            'userid' => 'privacy:metadata:local_groupchats:userid',
            'timecreated' => 'privacy:metadata:local_groupchats:timecreated',
        ], 'privacy:metadata:local_groupchats');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * Contexts: the user context of a person who started chats.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql("SELECT ctx.id
                               FROM {context} ctx
                              WHERE ctx.contextlevel = :level AND ctx.instanceid = :userid
                                AND EXISTS (SELECT 1 FROM {local_groupchats} g WHERE g.userid = :userid2)",
            ['level' => CONTEXT_USER, 'userid' => $userid, 'userid2' => $userid]);
        return $list;
    }

    /**
     * Users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_groupchats} WHERE userid = :userid',
            ['userid' => $context->instanceid]);
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || (int) $context->instanceid !== $userid) {
                continue;
            }
            $rows = $DB->get_records_sql('SELECT g.id, g.timecreated, mc.name
                                            FROM {local_groupchats} g
                                       LEFT JOIN {message_conversations} mc ON mc.id = g.conversationid
                                           WHERE g.userid = ?', [$userid]);
            $chats = [];
            foreach ($rows as $row) {
                $chats[] = ['name' => (string) $row->name, 'started' => transform::datetime($row->timecreated)];
            }
            writer::with_context($context)->export_data([get_string('groupchats', 'local_groupchats')],
                (object) ['startedchats' => $chats]);
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_USER) {
            $DB->set_field('local_groupchats', 'userid', 0, ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                $DB->set_field('local_groupchats', 'userid', 0, ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete several users' data.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER && in_array((int) $context->instanceid, $userlist->get_userids())) {
            self::delete_data_for_all_users_in_context($context);
        }
    }
}
