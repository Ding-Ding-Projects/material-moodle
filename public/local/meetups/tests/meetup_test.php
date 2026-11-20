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

namespace local_meetups;

/**
 * Tests for the meetup planner.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_meetups\meetup
 */
final class meetup_test extends \advanced_testcase {
    /**
     * Create an invite-only meetup with two time options.
     *
     * @param \stdClass $organiser
     * @param int[] $invitees
     * @param array $extra Extra fields.
     * @return \stdClass
     */
    protected function make_meetup(\stdClass $organiser, array $invitees, array $extra = []): \stdClass {
        $this->setUser($organiser);
        $start = time() + 2 * DAYSECS;
        return meetup::create((object) ($extra + [
            'title' => 'Study group',
            'description' => 'Bring notes',
            'descriptionformat' => FORMAT_PLAIN,
            'type' => 'hybrid',
            'location' => 'Library',
            'onlineurl' => 'https://example.com/room',
            'audience' => 'invite',
        ]), [
            ['timestart' => $start, 'timeend' => $start + HOURSECS],
            ['timestart' => $start + DAYSECS, 'timeend' => $start + DAYSECS + HOURSECS],
        ], $invitees);
    }

    public function test_create_sends_invitations_and_controls_access(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $guest = $gen->create_user();
        $stranger = $gen->create_user();
        $sink = $this->redirectMessages();

        $record = $this->make_meetup($organiser, [$guest->id]);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($guest->id, $messages[0]->useridto);
        $this->assertEquals('invitation', $messages[0]->eventtype);
        $this->assertSame(meetup::STATUS_PLANNING, $record->status);
        $this->assertCount(2, meetup::options($record));
        $this->assertTrue(meetup::can_view($record, $organiser->id));
        $this->assertTrue(meetup::can_view($record, $guest->id));
        $this->assertFalse(meetup::can_view($record, $stranger->id));

        // The invite link lets the stranger in.
        $this->assertFalse(meetup::join_with_token($record, 'wrong', $stranger->id));
        $this->assertTrue(meetup::join_with_token($record, $record->token, $stranger->id));
        $this->assertTrue(meetup::can_view($record, $stranger->id));
    }

    public function test_vote_tally_and_finalise_creates_calendar_events(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id, $b->id]);
        $options = array_values(meetup::options($record));

        meetup::vote($record, $options[0]->id, meetup::VOTE_YES, $a->id);
        meetup::vote($record, $options[0]->id, meetup::VOTE_IFNEEDED, $b->id);
        meetup::vote($record, $options[1]->id, meetup::VOTE_NO, $a->id);
        // Changing an answer replaces it.
        meetup::vote($record, $options[1]->id, meetup::VOTE_YES, $a->id);

        $tally = meetup::tally($record, $a->id);
        $this->assertSame(2, $tally['voters']);
        $this->assertSame(1, $tally['options'][0]['yes']);
        $this->assertSame(1, $tally['options'][0]['ifneeded']);
        $this->assertSame(3, $tally['options'][0]['score']);
        $this->assertTrue($tally['options'][0]['best']);
        $this->assertSame(meetup::VOTE_YES, $tally['options'][1]['myvote']);

        $sink = $this->redirectMessages();
        $record = meetup::finalise($record, $options[0]->id);
        $this->assertSame(meetup::STATUS_SCHEDULED, $record->status);
        $this->assertEquals($options[0]->timestart, $record->timestart);
        $this->assertCount(2, $sink->get_messages());

        // Invite-only meetups get one user event per person taking part.
        $events = $DB->get_records('event', ['component' => 'local_meetups', 'instance' => $record->id]);
        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertSame('user', $event->eventtype);
            $this->assertEquals($record->timestart, $event->timestart);
        }
        // Yes became going, if needed became maybe.
        $this->assertSame('going', $DB->get_field('local_meetups_rsvp', 'response', ['meetupid' => $record->id,
            'userid' => $a->id]));
        $this->assertSame('maybe', $DB->get_field('local_meetups_rsvp', 'response', ['meetupid' => $record->id,
            'userid' => $b->id]));

        // Declining removes the person's calendar event.
        meetup::rsvp($record, 'notgoing', 'Sorry', $b->id);
        $this->assertFalse($DB->record_exists('event', ['component' => 'local_meetups', 'instance' => $record->id,
            'userid' => $b->id]));

        // Cancelling removes every event.
        $this->setUser($organiser);
        meetup::cancel($record);
        $this->assertEquals(0, $DB->count_records('event', ['component' => 'local_meetups', 'instance' => $record->id]));
    }

    public function test_course_meetup_creates_one_course_event(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $outsider = $gen->create_user();
        $record = $this->make_meetup($teacher, [], ['audience' => 'course', 'courseid' => $course->id]);

        $this->assertTrue(meetup::can_view($record, $student->id));
        $this->assertFalse(meetup::can_view($record, $outsider->id));

        $options = array_values(meetup::options($record));
        $record = meetup::finalise($record, $options[1]->id);
        $events = $DB->get_records('event', ['component' => 'local_meetups', 'instance' => $record->id]);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame('course', $event->eventtype);
        $this->assertEquals($course->id, $event->courseid);
    }

    public function test_waitlist_promotes_when_a_place_opens(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id, $b->id], ['capacity' => 2]);
        $options = array_values(meetup::options($record));
        $record = meetup::finalise($record, $options[0]->id);

        // The organiser takes one place.
        $reply = meetup::rsvp($record, 'going', '', $a->id);
        $this->assertEquals(0, $reply->waitlisted);
        $reply = meetup::rsvp($record, 'going', 'Count me in', $b->id);
        $this->assertEquals(1, $reply->waitlisted);
        $this->assertTrue(meetup::is_full($record));

        $sink = $this->redirectMessages();
        meetup::rsvp($record, 'notgoing', '', $a->id);
        $this->assertEquals(0, $DB->get_field('local_meetups_rsvp', 'waitlisted', ['meetupid' => $record->id,
            'userid' => $b->id]));
        $this->assertCount(1, $sink->get_messages());

        $groups = meetup::attendees($record);
        $this->assertCount(2, $groups['going']);
        $this->assertCount(1, $groups['notgoing']);
        $this->assertCount(0, $groups['waitlist']);
    }

    public function test_rsvp_deadline_and_comments(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id], ['rsvpdeadline' => time() - 10]);
        $options = array_values(meetup::options($record));
        $record = meetup::finalise($record, $options[0]->id);

        $this->assertTrue(meetup::rsvp_closed($record));
        try {
            meetup::rsvp($record, 'going', '', $a->id);
            $this->fail('A reply after the deadline must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorrsvpclosed', $e->errorcode);
        }

        $id = meetup::add_comment($record, 'See you there', $a->id);
        $this->assertCount(1, meetup::comments($record));
        meetup::delete_comment($record, $id, $organiser->id);
        $this->assertCount(0, meetup::comments($record));
    }

    public function test_reminders_and_ics(): void {
        $this->resetAfterTest();
        set_config('reminderhours', 72, 'local_meetups');
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id]);
        $options = array_values(meetup::options($record));
        $record = meetup::finalise($record, $options[0]->id);
        meetup::rsvp($record, 'going', '', $a->id);

        $sink = $this->redirectMessages();
        $this->assertSame(1, meetup::send_due_reminders());
        $this->assertCount(2, $sink->get_messages());
        // Reminders go out once.
        $this->assertSame(0, meetup::send_due_reminders());

        $ics = meetup::ics(meetup::get($record->id));
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Study group', $ics);
        $this->assertStringContainsString('DTSTART:' . gmdate('Ymd\THis\Z', $record->timestart), $ics);
        $this->assertStringContainsString('LOCATION:Library', $ics);
    }

    public function test_edit_options_keeps_unchanged_votes(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id]);
        $options = array_values(meetup::options($record));
        meetup::vote($record, $options[0]->id, meetup::VOTE_YES, $a->id);
        meetup::vote($record, $options[1]->id, meetup::VOTE_YES, $a->id);

        meetup::set_options($record, [
            ['timestart' => $options[0]->timestart, 'timeend' => $options[0]->timeend],
            ['timestart' => $options[0]->timestart + 3 * DAYSECS, 'timeend' => $options[0]->timeend + 3 * DAYSECS],
        ]);
        $this->assertCount(2, meetup::options($record));
        $this->assertSame(1, $DB->count_records('local_meetups_vote', ['meetupid' => $record->id]));
        $this->assertTrue($DB->record_exists('local_meetups_vote', ['optionid' => $options[0]->id]));
    }

    public function test_delete_removes_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $organiser = $gen->create_user();
        $a = $gen->create_user();
        $record = $this->make_meetup($organiser, [$a->id]);
        $options = array_values(meetup::options($record));
        meetup::vote($record, $options[0]->id, meetup::VOTE_YES, $a->id);
        $record = meetup::finalise($record, $options[0]->id);
        meetup::add_comment($record, 'Hello', $a->id);

        meetup::delete($record);
        foreach (['local_meetups', 'local_meetups_option', 'local_meetups_vote', 'local_meetups_rsvp',
                'local_meetups_comment', 'local_meetups_invite'] as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
        $this->assertSame(0, $DB->count_records('event', ['component' => 'local_meetups']));
    }
}
