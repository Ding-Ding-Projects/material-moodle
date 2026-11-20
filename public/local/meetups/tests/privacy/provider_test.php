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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use local_meetups\meetup;

/**
 * Privacy provider tests.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_meetups\privacy\provider
 */
final class provider_test extends provider_testcase {
    public function test_contexts_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $guest = $gen->create_user();
        $this->setUser($organiser);
        $start = time() + DAYSECS;
        $record = meetup::create((object) ['title' => 'Picnic', 'type' => 'inperson', 'location' => 'Park',
            'audience' => 'invite'], [['timestart' => $start, 'timeend' => $start + HOURSECS]], [$guest->id]);
        $option = array_values(meetup::options($record))[0];
        meetup::vote($record, $option->id, meetup::VOTE_YES, $guest->id);
        meetup::add_comment($record, 'Bringing snacks', $guest->id);

        $system = \context_system::instance();
        $contexts = provider::get_contexts_for_userid($guest->id)->get_contextids();
        $this->assertEquals([$system->id], array_values($contexts));

        $userlist = new userlist($system, 'local_meetups');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$organiser->id, $guest->id], array_values(array_unique($userlist->get_userids())));

        $this->export_context_data_for_user($guest->id, $system, 'local_meetups');
        $this->assertTrue(\core_privacy\local\request\writer::with_context($system)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($guest, 'local_meetups', [$system->id]));
        $this->assertSame(0, $DB->count_records('local_meetups_vote', ['userid' => $guest->id]));
        $this->assertSame(0, $DB->count_records('local_meetups_comment', ['userid' => $guest->id]));
        $this->assertSame(1, $DB->count_records('local_meetups'));

        provider::delete_data_for_all_users_in_context($system);
        $this->assertSame(0, $DB->count_records('local_meetups'));
    }
}
