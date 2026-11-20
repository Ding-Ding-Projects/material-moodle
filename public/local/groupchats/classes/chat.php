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

namespace local_groupchats;

use core_message\api;

/**
 * Group chats built on core messaging group conversations that are not tied to a course group.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat {
    /** @var string Table recording who started each chat. */
    const TABLE = 'local_groupchats';
    /** @var int Longest chat name. */
    const NAME_LENGTH = 255;

    /**
     * Resolve the user id, defaulting to the current user.
     *
     * @param int $userid
     * @return int
     */
    protected static function uid(int $userid): int {
        global $USER;
        return $userid ?: (int) $USER->id;
    }

    /**
     * Whether the user may start group chats.
     *
     * @param int $userid
     * @return bool
     */
    public static function can_create(int $userid = 0): bool {
        global $CFG;
        $userid = self::uid($userid);
        return !empty($CFG->messaging) && !isguestuser($userid)
            && has_capability('local/groupchats:create', \context_system::instance(), $userid)
            && has_capability('moodle/site:sendmessage', \context_system::instance(), $userid);
    }

    /**
     * Whether the user manages every group chat.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_admin(int $userid = 0): bool {
        return has_capability('local/groupchats:manageall', \context_system::instance(), self::uid($userid));
    }

    /**
     * Load a group chat (a group conversation not linked to any component).
     *
     * @param int $conversationid
     * @return \stdClass Conversation with creatorid.
     */
    public static function get(int $conversationid): \stdClass {
        global $DB;
        $conversation = $DB->get_record_sql("SELECT mc.*, gc.userid AS creatorid
                                               FROM {message_conversations} mc
                                          LEFT JOIN {" . self::TABLE . "} gc ON gc.conversationid = mc.id
                                              WHERE mc.id = :id AND mc.type = :type AND mc.component IS NULL",
            ['id' => $conversationid, 'type' => api::MESSAGE_CONVERSATION_TYPE_GROUP], MUST_EXIST);
        $conversation->creatorid = (int) $conversation->creatorid;
        return $conversation;
    }

    /**
     * Whether the user may rename the chat and add or remove members.
     *
     * @param \stdClass $conversation
     * @param int $userid
     * @return bool
     */
    public static function can_manage(\stdClass $conversation, int $userid = 0): bool {
        $userid = self::uid($userid);
        return ($conversation->creatorid === $userid && api::is_user_in_conversation($userid, $conversation->id))
            || self::is_admin($userid);
    }

    /**
     * Whether the user may open the manage page.
     *
     * @param \stdClass $conversation
     * @param int $userid
     * @return bool
     */
    public static function can_view(\stdClass $conversation, int $userid = 0): bool {
        $userid = self::uid($userid);
        return api::is_user_in_conversation($userid, $conversation->id) || self::is_admin($userid);
    }

    /**
     * Split candidate members into people who can be added and people who cannot.
     *
     * Deleted, suspended and guest accounts are dropped. People who blocked the person adding them, or whom that
     * person blocked, are refused so nobody is put in a chat with someone they blocked.
     *
     * @param int[] $userids
     * @param int $actorid The person adding them.
     * @param int[] $existing Members already in the chat.
     * @return array [int[] allowed, \stdClass[] refused users]
     */
    public static function filter_members(array $userids, int $actorid, array $existing = []): array {
        $allowed = [];
        $refused = [];
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if ($userid === $actorid || in_array($userid, $existing, true)) {
                continue;
            }
            $user = \core_user::get_user($userid);
            if (!$user || $user->deleted || $user->suspended || isguestuser($user) || !\core_user::is_real_user($userid)) {
                continue;
            }
            $blocked = api::is_blocked($userid, $actorid) || api::is_blocked($actorid, $userid);
            if (!$blocked) {
                foreach ($existing as $memberid) {
                    if (api::is_blocked($userid, $memberid)) {
                        $blocked = true;
                        break;
                    }
                }
            }
            if ($blocked) {
                $refused[] = $user;
            } else {
                $allowed[] = $userid;
            }
        }
        return [$allowed, $refused];
    }

    /**
     * Start a group chat. The creator becomes a member and posts the first message.
     *
     * @param string $name
     * @param int[] $memberids
     * @param int $creatorid
     * @return array [\stdClass conversation, \stdClass[] refused users]
     */
    public static function create(string $name, array $memberids, int $creatorid = 0): array {
        global $DB;
        $creatorid = self::uid($creatorid);
        $name = self::clean_name($name);
        [$allowed, $refused] = self::filter_members($memberids, $creatorid);
        if (!$allowed) {
            throw new \moodle_exception('errornomembers', 'local_groupchats');
        }
        $conversation = api::create_conversation(api::MESSAGE_CONVERSATION_TYPE_GROUP,
            array_merge([$creatorid], $allowed), $name);
        $DB->insert_record(self::TABLE, (object) ['conversationid' => $conversation->id, 'userid' => $creatorid,
            'timecreated' => time()]);
        self::post($conversation->id, $creatorid, get_string('msg_created', 'local_groupchats', (object) [
            'name' => $name,
            'people' => self::names($allowed),
        ]));
        return [self::get($conversation->id), $refused];
    }

    /**
     * Add members.
     *
     * @param \stdClass $conversation
     * @param int[] $userids
     * @param int $actorid
     * @return array [int[] added, \stdClass[] refused]
     */
    public static function add_members(\stdClass $conversation, array $userids, int $actorid = 0): array {
        $actorid = self::uid($actorid);
        $existing = array_map('intval', array_keys(self::member_ids($conversation->id)));
        [$allowed, $refused] = self::filter_members($userids, $actorid, $existing);
        if ($allowed) {
            api::add_members_to_conversation($allowed, $conversation->id);
            self::post($conversation->id, $actorid, get_string('msg_added', 'local_groupchats', (object) [
                'actor' => self::names([$actorid]),
                'people' => self::names($allowed),
            ]));
        }
        return [$allowed, $refused];
    }

    /**
     * Remove a member.
     *
     * @param \stdClass $conversation
     * @param int $userid
     * @param int $actorid
     */
    public static function remove_member(\stdClass $conversation, int $userid, int $actorid = 0): void {
        $actorid = self::uid($actorid);
        if ($userid === $actorid) {
            self::leave($conversation, $userid);
            return;
        }
        api::remove_members_from_conversation([$userid], $conversation->id);
        self::post($conversation->id, $actorid, get_string('msg_removed', 'local_groupchats', (object) [
            'actor' => self::names([$actorid]),
            'people' => self::names([$userid]),
        ]));
    }

    /**
     * Leave a chat.
     *
     * @param \stdClass $conversation
     * @param int $userid
     */
    public static function leave(\stdClass $conversation, int $userid = 0): void {
        $userid = self::uid($userid);
        if (!api::is_user_in_conversation($userid, $conversation->id)) {
            return;
        }
        self::post($conversation->id, $userid, get_string('msg_left', 'local_groupchats', self::names([$userid])));
        api::remove_members_from_conversation([$userid], $conversation->id);
    }

    /**
     * Rename a chat.
     *
     * @param \stdClass $conversation
     * @param string $name
     * @param int $actorid
     */
    public static function rename(\stdClass $conversation, string $name, int $actorid = 0): void {
        $actorid = self::uid($actorid);
        $name = self::clean_name($name);
        if ($name === (string) $conversation->name) {
            return;
        }
        api::update_conversation_name($conversation->id, $name);
        self::post($conversation->id, $actorid, get_string('msg_renamed', 'local_groupchats', (object) [
            'actor' => self::names([$actorid]),
            'name' => $name,
        ]));
    }

    /**
     * Trim and shorten a chat name.
     *
     * @param string $name
     * @return string
     */
    protected static function clean_name(string $name): string {
        $name = trim(clean_param($name, PARAM_TEXT));
        if ($name === '') {
            throw new \moodle_exception('errorname', 'local_groupchats');
        }
        return \core_text::substr($name, 0, self::NAME_LENGTH);
    }

    /**
     * Post an announcement into the chat from a member. Does nothing when that person is not a member or cannot post.
     *
     * @param int $conversationid
     * @param int $userid
     * @param string $text
     * @return bool
     */
    protected static function post(int $conversationid, int $userid, string $text): bool {
        if (!api::can_send_message_to_conversation($userid, $conversationid)) {
            return false;
        }
        api::send_message_to_conversation($userid, $conversationid, $text, FORMAT_PLAIN);
        return true;
    }

    /**
     * Member user ids.
     *
     * @param int $conversationid
     * @return array userid => userid
     */
    public static function member_ids(int $conversationid): array {
        global $DB;
        $ids = $DB->get_fieldset_select('message_conversation_members', 'userid', 'conversationid = ?', [$conversationid]);
        return array_combine($ids, $ids) ?: [];
    }

    /**
     * Members with names, ordered by name.
     *
     * @param int $conversationid
     * @return \stdClass[]
     */
    public static function members(int $conversationid): array {
        global $DB;
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql("SELECT $fields
                                       FROM {message_conversation_members} m
                                       JOIN {user} u ON u.id = m.userid AND u.deleted = 0
                                      WHERE m.conversationid = ?
                                   ORDER BY u.firstname, u.lastname, u.id", [$conversationid]);
    }

    /**
     * Names joined into one line.
     *
     * @param int[] $userids
     * @return string
     */
    public static function names(array $userids): string {
        $names = [];
        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);
            if ($user) {
                $names[] = fullname($user);
            }
        }
        return implode(', ', $names);
    }

    /**
     * Group chats for the list page.
     *
     * @param int $userid
     * @param bool $all Every chat started with this plugin, for people who manage all chats.
     * @return \stdClass[]
     */
    public static function list_chats(int $userid = 0, bool $all = false): array {
        global $DB;
        $userid = self::uid($userid);
        $params = ['type' => api::MESSAGE_CONVERSATION_TYPE_GROUP, 'me' => $userid];
        if ($all) {
            $scope = 'EXISTS (SELECT 1 FROM {' . self::TABLE . '} x WHERE x.conversationid = mc.id)';
        } else {
            $scope = 'EXISTS (SELECT 1 FROM {message_conversation_members} me WHERE me.conversationid = mc.id AND me.userid = :me2)';
            $params['me2'] = $userid;
        }
        return $DB->get_records_sql("SELECT mc.id, mc.name, mc.timecreated, mc.enabled, gc.userid AS creatorid,
                                            (SELECT COUNT(1) FROM {message_conversation_members} c WHERE c.conversationid = mc.id)
                                                AS membercount,
                                            (SELECT MAX(m.timecreated) FROM {messages} m WHERE m.conversationid = mc.id)
                                                AS lastmessage,
                                            (SELECT COUNT(1) FROM {message_conversation_members} i
                                              WHERE i.conversationid = mc.id AND i.userid = :me) AS ismember
                                       FROM {message_conversations} mc
                                  LEFT JOIN {" . self::TABLE . "} gc ON gc.conversationid = mc.id
                                      WHERE mc.type = :type AND mc.component IS NULL AND $scope
                                   ORDER BY COALESCE((SELECT MAX(m2.timecreated) FROM {messages} m2
                                                      WHERE m2.conversationid = mc.id), mc.timecreated) DESC", $params);
    }

    /**
     * Link that opens the chat in the messaging page.
     *
     * @param int $conversationid
     * @return \moodle_url
     */
    public static function open_url(int $conversationid): \moodle_url {
        return new \moodle_url('/message/index.php', ['convid' => $conversationid]);
    }

    /**
     * Messaging access summary for the settings page.
     *
     * @return array
     */
    public static function access_report(): array {
        global $CFG, $DB;
        $system = \context_system::instance();
        $role = $DB->get_record('role', ['id' => $CFG->defaultuserroleid]);
        $permission = $role ? $DB->get_field('role_capabilities', 'permission', ['roleid' => $role->id,
            'capability' => 'moodle/site:sendmessage', 'contextid' => $system->id]) : false;
        $createpermission = $role ? $DB->get_field('role_capabilities', 'permission', ['roleid' => $role->id,
            'capability' => 'local/groupchats:create', 'contextid' => $system->id]) : false;
        return [
            'messaging' => !empty($CFG->messaging),
            'messagingallusers' => !empty($CFG->messagingallusers),
            'rolename' => $role ? role_get_name($role, $system) : '-',
            'roleid' => $role ? (int) $role->id : 0,
            'sendmessage' => (int) $permission,
            'cansend' => (int) $permission === CAP_ALLOW,
            'create' => (int) $createpermission,
            'cancreate' => (int) $createpermission === CAP_ALLOW,
        ];
    }

    /**
     * Let everyone message everyone and make sure signed-in users may send messages.
     */
    public static function allow_everyone(): void {
        global $CFG;
        set_config('messaging', 1);
        set_config('messagingallusers', 1);
        if (!empty($CFG->defaultuserroleid)) {
            $system = \context_system::instance();
            assign_capability('moodle/site:sendmessage', CAP_ALLOW, $CFG->defaultuserroleid, $system->id, true);
            assign_capability('local/groupchats:create', CAP_ALLOW, $CFG->defaultuserroleid, $system->id, true);
            $system->mark_dirty();
        }
    }
}
