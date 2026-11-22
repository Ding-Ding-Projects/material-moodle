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


namespace local_materialtools\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: personal titles, the person's own wording pack and two preferences. All of it belongs to the
 * person's user context; accounts the plugin creates are ordinary Moodle accounts.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\user_preference_provider {

    /** Title table. */
    private const TITLE = 'local_materialtools_title';

    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TITLE, [
            'userid' => 'privacy:metadata:title:userid',
            'courseid' => 'privacy:metadata:title:courseid',
            'title' => 'privacy:metadata:title:title',
            'timemodified' => 'privacy:metadata:title:timemodified',
        ], 'privacy:metadata:title');
        $collection->add_user_preference(\local_materialtools\vocabulary::PREF, 'privacy:metadata:preference:vocab');
        $collection->add_user_preference(\local_materialtools\display_preferences::PREF, 'privacy:metadata:preference:display');
        $collection->add_user_preference('local_materialtools_personalvocab', 'privacy:metadata:preference:personalvocab');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:personalvocabfiles');
        return $collection;
    }

    /**
     * Does the person have anything stored here?
     *
     * @param int $userid
     * @return bool
     */
    private static function has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists(self::TITLE, ['userid' => $userid])
            || get_user_preferences('local_materialtools_personalvocab', null, $userid) !== null;
    }

    /**
     * Contexts with data for a person.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        if (self::has_data($userid)) {
            $list->add_user_context($userid);
        }
        return $list;
    }

    /**
     * People with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && self::has_data((int) $context->instanceid)) {
            $userlist->add_user((int) $context->instanceid);
        }
    }

    /**
     * Export titles and the person's own pack.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || (int) $context->instanceid !== $userid) {
                continue;
            }
            $titles = [];
            foreach ($DB->get_records(self::TITLE, ['userid' => $userid], 'courseid') as $t) {
                $titles[] = ['course' => $t->courseid ? format_string((string) $DB->get_field('course', 'fullname',
                    ['id' => $t->courseid])) : get_string('site'), 'title' => $t->title,
                    'timemodified' => \core_privacy\local\request\transform::datetime($t->timemodified)];
            }
            $path = [get_string('pluginname', 'local_materialtools')];
            if ($titles) {
                writer::with_context($context)->export_data($path, (object) ['titles' => $titles]);
            }
            writer::with_context($context)->export_area_files($path, 'local_materialtools', 'personalvocab', 0);
        }
    }

    /**
     * Remove everything for one person.
     *
     * @param int $userid
     */
    private static function delete_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TITLE, ['userid' => $userid]);
        \local_materialtools\vocabulary::clear_personal($userid);
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context instanceof \context_user) {
            self::delete_user((int) $context->instanceid);
        }
    }

    /**
     * Delete one person's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === $userid) {
                self::delete_user($userid);
            }
        }
    }

    /**
     * Delete data for several people in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && in_array((int) $context->instanceid, $userlist->get_userids())) {
            self::delete_user((int) $context->instanceid);
        }
    }

    /**
     * Export preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        foreach ([\local_materialtools\vocabulary::PREF => 'privacy:metadata:preference:vocab',
                \local_materialtools\display_preferences::PREF => 'privacy:metadata:preference:display',
                'local_materialtools_personalvocab' => 'privacy:metadata:preference:personalvocab'] as $name => $desc) {
            $value = get_user_preferences($name, null, $userid);
            if ($value !== null) {
                writer::export_user_preference('local_materialtools', $name, $value, get_string($desc, 'local_materialtools'));
            }
        }
    }
}
