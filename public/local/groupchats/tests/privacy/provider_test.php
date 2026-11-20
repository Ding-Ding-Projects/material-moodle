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

use core_privacy\local\request\approved_contextlist;
use core_privacy\tests\provider_testcase;
use local_groupchats\chat;

/**
 * Privacy provider tests.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_groupchats\privacy\provider
 */
final class provider_test extends provider_testcase {
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $creator = $gen->create_user();
        $member = $gen->create_user();
        $this->setUser($creator);
        chat::create('Team', [$member->id]);

        $context = \context_user::instance($creator->id);
        $this->assertEquals([$context->id], array_values(provider::get_contexts_for_userid($creator->id)->get_contextids()));
        $this->assertEmpty(provider::get_contexts_for_userid($member->id)->get_contextids());

        $this->export_context_data_for_user($creator->id, $context, 'local_groupchats');
        $this->assertTrue(\core_privacy\local\request\writer::with_context($context)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($creator, 'local_groupchats', [$context->id]));
        $this->assertFalse($DB->record_exists('local_groupchats', ['userid' => $creator->id]));
        // The chat itself stays for the other members.
        $this->assertSame(1, $DB->count_records('local_groupchats'));
    }
}
