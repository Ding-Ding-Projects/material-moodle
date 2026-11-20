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
 * Tests for group chats.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupchats\chat
 */
final class chat_test extends \advanced_testcase {
    public function test_install_enables_messaging_between_everyone(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('messagingallusers', 0);
        require_once($CFG->dirroot . '/local/groupchats/db/install.php');
        xmldb_local_groupchats_install();
        $this->assertEquals(1, get_config('core', 'messagingallusers'));
        $this->assertEquals(1, get_config('core', 'messaging'));
    }

    public function test_create_group_chat_appears_in_core_messaging(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $creator = $gen->create_user();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $this->setUser($creator);
        $this->assertTrue(chat::can_create());

        [$conversation, $refused] = chat::create('Project team', [$a->id, $b->id]);
        $this->assertSame([], $refused);
        $this->assertEquals(api::MESSAGE_CONVERSATION_TYPE_GROUP, $conversation->type);
        $this->assertNull($conversation->itemtype);
        $this->assertSame((int) $creator->id, $conversation->creatorid);
        $this->assertCount(3, chat::member_ids($conversation->id));
        $this->assertTrue(chat::can_manage($conversation, $creator->id));
        $this->assertFalse(chat::can_manage($conversation, $a->id));

        // The first message is posted by the creator, and the chat is listed in the drawer data.
        $this->assertSame(1, $DB->count_records('messages', ['conversationid' => $conversation->id]));
        $conversations = api::get_conversations($a->id, 0, 20, api::MESSAGE_CONVERSATION_TYPE_GROUP);
        $this->assertArrayHasKey($conversation->id, array_column($conversations, null, 'id'));

        // Members can talk.
        api::send_message_to_conversation($a->id, $conversation->id, 'Hello all', FORMAT_PLAIN);
        $this->assertSame(2, $DB->count_records('messages', ['conversationid' => $conversation->id]));
        $this->assertCount(1, chat::list_chats($b->id));
    }

    public function test_blocked_people_are_not_added(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $creator = $gen->create_user();
        $friend = $gen->create_user();
        $blocker = $gen->create_user();
        $blocked = $gen->create_user();
        api::block_user($blocker->id, $creator->id);
        api::block_user($creator->id, $blocked->id);
        $this->setUser($creator);

        [$conversation, $refused] = chat::create('Team', [$friend->id, $blocker->id, $blocked->id]);
        $this->assertEqualsCanonicalizing([$blocker->id, $blocked->id], array_map(fn($u) => (int) $u->id, $refused));
        $this->assertEqualsCanonicalizing([$creator->id, $friend->id], array_values(chat::member_ids($conversation->id)));

        // Someone who blocked an existing member cannot be added either.
        $picky = $gen->create_user();
        api::block_user($picky->id, $friend->id);
        [$added, $refused] = chat::add_members($conversation, [$picky->id]);
        $this->assertSame([], $added);
        $this->assertCount(1, $refused);

        $this->expectException(\moodle_exception::class);
        chat::create('Nobody', [$blocker->id]);
    }

    public function test_rename_add_remove_and_leave(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $creator = $gen->create_user();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $this->setUser($creator);
        [$conversation] = chat::create('Old name', [$a->id]);

        chat::rename($conversation, 'New name');
        $this->assertSame('New name', $DB->get_field('message_conversations', 'name', ['id' => $conversation->id]));

        [$added] = chat::add_members($conversation, [$b->id]);
        $this->assertSame([(int) $b->id], $added);
        $this->assertTrue(api::is_user_in_conversation($b->id, $conversation->id));

        chat::remove_member($conversation, $b->id);
        $this->assertFalse(api::is_user_in_conversation($b->id, $conversation->id));

        chat::leave($conversation, $a->id);
        $this->assertFalse(api::is_user_in_conversation($a->id, $conversation->id));
        $this->assertCount(1, chat::member_ids($conversation->id));
    }

    public function test_managers_manage_every_chat(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $creator = $gen->create_user();
        $a = $gen->create_user();
        $manager = $gen->create_user();
        $gen->role_assign($this->get_role_id('manager'), $manager->id, \context_system::instance()->id);
        $this->setUser($creator);
        [$conversation] = chat::create('Team', [$a->id]);

        $this->assertTrue(chat::can_manage($conversation, $manager->id));
        $this->assertTrue(chat::can_view($conversation, $manager->id));
        $this->setUser($manager);
        $this->assertCount(1, chat::list_chats($manager->id, true));
        $this->assertCount(0, chat::list_chats($manager->id, false));
    }

    public function test_allow_everyone_and_report(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('messagingallusers', 0);
        chat::allow_everyone();
        $report = chat::access_report();
        $this->assertTrue($report['messaging']);
        $this->assertTrue($report['messagingallusers']);
        $this->assertTrue($report['cansend']);
        $this->assertTrue($report['cancreate']);
        $this->assertEquals($CFG->defaultuserroleid, $report['roleid']);
    }

    /**
     * Role id by short name.
     *
     * @param string $shortname
     * @return int
     */
    protected function get_role_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
