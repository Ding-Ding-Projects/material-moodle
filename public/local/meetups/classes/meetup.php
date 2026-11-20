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
 * Meetup storage, access rules, scheduling poll, replies, calendar and notifications.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meetup {
    /** @var string Main table. */
    const TABLE = 'local_meetups';
    /** @var string[] Meetup types. */
    const TYPES = ['inperson', 'online', 'hybrid'];
    /** @var string[] Who a meetup is for. */
    const AUDIENCES = ['invite', 'course', 'group', 'site'];
    /** @var string[] Replies to a scheduled meetup. */
    const RESPONSES = ['going', 'maybe', 'notgoing'];
    /** @var int Poll answer: yes. */
    const VOTE_YES = 2;
    /** @var int Poll answer: if needed. */
    const VOTE_IFNEEDED = 1;
    /** @var int Poll answer: no. */
    const VOTE_NO = 0;
    /** @var string Planning: the scheduling poll is open. */
    const STATUS_PLANNING = 'planning';
    /** @var string Scheduled: the final time is set. */
    const STATUS_SCHEDULED = 'scheduled';
    /** @var string Cancelled by the organiser or a moderator. */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Whether the plugin is switched on.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return get_config('local_meetups', 'enabled') !== '0';
    }

    /**
     * Whether site-wide meetups are allowed.
     *
     * @return bool
     */
    public static function site_meetups_allowed(): bool {
        return get_config('local_meetups', 'allowsite') !== '0';
    }

    /**
     * Hours before the start when reminders go out.
     *
     * @return int
     */
    public static function reminder_hours(): int {
        $hours = get_config('local_meetups', 'reminderhours');
        return $hours === false || $hours === '' ? 24 : max(0, (int) $hours);
    }

    /**
     * Load a meetup.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The context a meetup lives in.
     *
     * @param \stdClass $meetup
     * @return \context
     */
    public static function context(\stdClass $meetup): \context {
        return $meetup->courseid ? \context_course::instance($meetup->courseid) : \context_system::instance();
    }

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
     * Whether the user moderates meetups in the meetup's context.
     *
     * @param \stdClass $meetup
     * @param int $userid
     * @return bool
     */
    public static function is_moderator(\stdClass $meetup, int $userid = 0): bool {
        return has_capability('local/meetups:moderate', self::context($meetup), self::uid($userid));
    }

    /**
     * Whether the user may edit, finalise, cancel or delete the meetup.
     *
     * @param \stdClass $meetup
     * @param int $userid
     * @return bool
     */
    public static function can_manage(\stdClass $meetup, int $userid = 0): bool {
        $userid = self::uid($userid);
        return (int) $meetup->userid === $userid || self::is_moderator($meetup, $userid);
    }

    /**
     * Whether the user may create meetups in a context.
     *
     * @param \context $context System or course context.
     * @param int $userid
     * @return bool
     */
    public static function can_create(\context $context, int $userid = 0): bool {
        $userid = self::uid($userid);
        if (!self::enabled() || isguestuser($userid) || !has_capability('local/meetups:create', $context, $userid)) {
            return false;
        }
        if (get_config('local_meetups', 'whocancreate') === 'staff') {
            if (has_capability('local/meetups:moderate', $context, $userid)) {
                return true;
            }
            return $context->contextlevel == CONTEXT_COURSE
                && has_capability('moodle/course:manageactivities', $context, $userid);
        }
        return true;
    }

    /**
     * Whether the user may see the meetup.
     *
     * @param \stdClass $meetup
     * @param int $userid
     * @return bool
     */
    public static function can_view(\stdClass $meetup, int $userid = 0): bool {
        global $DB;
        $userid = self::uid($userid);
        if (!self::enabled()) {
            return false;
        }
        if ((int) $meetup->userid === $userid || self::is_moderator($meetup, $userid)) {
            return true;
        }
        $context = self::context($meetup);
        if (!has_capability('local/meetups:view', $context, $userid)) {
            return false;
        }
        switch ($meetup->audience) {
            case 'site':
                return true;
            case 'course':
                return is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid);
            case 'group':
                return groups_is_member($meetup->groupid, $userid)
                    || has_capability('moodle/site:accessallgroups', $context, $userid);
            default:
                return $DB->record_exists('local_meetups_invite', ['meetupid' => $meetup->id, 'userid' => $userid]);
        }
    }

    /**
     * Join an invite-only meetup with its invite link.
     *
     * @param \stdClass $meetup
     * @param string $token
     * @param int $userid
     * @return bool True when the user can now see the meetup.
     */
    public static function join_with_token(\stdClass $meetup, string $token, int $userid = 0): bool {
        $userid = self::uid($userid);
        if ($token === '' || !hash_equals($meetup->token, $token) || $meetup->audience !== 'invite'
                || $meetup->status === self::STATUS_CANCELLED || isguestuser($userid)
                || !has_capability('local/meetups:view', self::context($meetup), $userid)) {
            return false;
        }
        self::add_invites($meetup, [$userid]);
        return true;
    }

    /**
     * Personally invite people. Returns the ids that were new.
     *
     * @param \stdClass $meetup
     * @param int[] $userids
     * @return int[]
     */
    public static function add_invites(\stdClass $meetup, array $userids): array {
        global $DB;
        $added = [];
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if ($userid <= 0 || $DB->record_exists('local_meetups_invite', ['meetupid' => $meetup->id, 'userid' => $userid])) {
                continue;
            }
            $DB->insert_record('local_meetups_invite', (object) ['meetupid' => $meetup->id, 'userid' => $userid,
                'timecreated' => time()]);
            $added[] = $userid;
        }
        return $added;
    }

    /**
     * Everyone the meetup is addressed to, including the organiser.
     *
     * @param \stdClass $meetup
     * @return int[]
     */
    public static function invitees(\stdClass $meetup): array {
        global $DB;
        $ids = [(int) $meetup->userid];
        switch ($meetup->audience) {
            case 'course':
                $users = get_enrolled_users(self::context($meetup), '', 0, 'u.id', null, 0, 0, true);
                $ids = array_merge($ids, array_keys($users));
                break;
            case 'group':
                $ids = array_merge($ids, array_keys(groups_get_members($meetup->groupid, 'u.id')));
                break;
            case 'invite':
                $ids = array_merge($ids, $DB->get_fieldset_select('local_meetups_invite', 'userid',
                    'meetupid = ?', [$meetup->id]));
                break;
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * People who should hear about changes: invitees, voters and everyone who replied, except those not going.
     *
     * @param \stdClass $meetup
     * @return int[]
     */
    public static function interested_users(\stdClass $meetup): array {
        global $DB;
        $ids = self::invitees($meetup);
        $ids = array_merge($ids, $DB->get_fieldset_select('local_meetups_vote', 'DISTINCT userid', 'meetupid = ?', [$meetup->id]));
        $ids = array_merge($ids, $DB->get_fieldset_select('local_meetups_rsvp', 'userid', 'meetupid = ?', [$meetup->id]));
        $notgoing = $DB->get_fieldset_select('local_meetups_rsvp', 'userid', 'meetupid = ? AND response = ?',
            [$meetup->id, 'notgoing']);
        return array_values(array_diff(array_unique(array_map('intval', $ids)), array_map('intval', $notgoing)));
    }

    /**
     * Create a meetup.
     *
     * @param \stdClass $data Fields of the meetup.
     * @param array $options Each item has timestart and timeend.
     * @param int[] $inviteids People to invite personally.
     * @param bool $finalisenow Skip the poll and use the first option.
     * @return \stdClass The new meetup.
     */
    public static function create(\stdClass $data, array $options, array $inviteids = [], bool $finalisenow = false): \stdClass {
        global $DB, $USER;
        $now = time();
        $record = self::clean($data);
        $record->userid = !empty($data->userid) ? (int) $data->userid : (int) $USER->id;
        $record->status = self::STATUS_PLANNING;
        $record->timestart = 0;
        $record->timeend = 0;
        $record->finaloptionid = 0;
        $record->remindersent = 0;
        $record->token = random_string(32);
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->id = $DB->insert_record(self::TABLE, $record);
        $meetup = self::get($record->id);

        self::set_options($meetup, $options);
        if ($meetup->audience === 'invite') {
            self::add_invites($meetup, $inviteids);
        }
        $recipients = array_diff(self::invitees($meetup), [(int) $meetup->userid]);
        self::notify($meetup, 'invitation', $recipients);

        if ($finalisenow && ($first = $DB->get_records('local_meetups_option', ['meetupid' => $meetup->id], 'timestart ASC', 'id', 0, 1))) {
            self::finalise($meetup, (int) reset($first)->id);
        }
        return self::get($meetup->id);
    }

    /**
     * Update a meetup's details, options or time.
     *
     * @param \stdClass $meetup Current record.
     * @param \stdClass $data New field values.
     * @param array|null $options New poll options while planning.
     * @param int[] $inviteids People to invite personally (added, never removed here).
     * @return \stdClass The updated meetup.
     */
    public static function update(\stdClass $meetup, \stdClass $data, ?array $options = null, array $inviteids = []): \stdClass {
        global $DB;
        $record = self::clean($data);
        $record->id = $meetup->id;
        $record->timemodified = time();
        $timechanged = false;
        if ($meetup->status === self::STATUS_SCHEDULED && !empty($data->timestart) && !empty($data->timeend)) {
            if ((int) $data->timestart !== (int) $meetup->timestart || (int) $data->timeend !== (int) $meetup->timeend) {
                $record->timestart = (int) $data->timestart;
                $record->timeend = (int) $data->timeend;
                $record->remindersent = 0;
                $timechanged = true;
            }
        }
        $DB->update_record(self::TABLE, $record);
        $updated = self::get($meetup->id);

        if ($updated->status === self::STATUS_PLANNING && $options !== null) {
            self::set_options($updated, $options);
        }
        $newinvites = $updated->audience === 'invite' ? self::add_invites($updated, $inviteids) : [];
        if ($newinvites) {
            self::notify($updated, 'invitation', $newinvites);
        }
        if ($updated->status === self::STATUS_SCHEDULED) {
            self::sync_calendar($updated);
            $significant = $timechanged || $updated->title !== $meetup->title || $updated->location !== $meetup->location
                || $updated->onlineurl !== $meetup->onlineurl || $updated->type !== $meetup->type;
            if ($significant) {
                self::notify($updated, 'changed', array_diff(self::interested_users($updated), $newinvites));
            }
        }
        return $updated;
    }

    /**
     * Normalise the editable fields.
     *
     * @param \stdClass $data
     * @return \stdClass
     */
    protected static function clean(\stdClass $data): \stdClass {
        global $DB;
        $record = new \stdClass();
        $record->title = trim(\core_text::substr((string) ($data->title ?? ''), 0, 255));
        $record->description = (string) ($data->description ?? '');
        $record->descriptionformat = (int) ($data->descriptionformat ?? FORMAT_HTML);
        $record->type = in_array($data->type ?? '', self::TYPES, true) ? $data->type : 'inperson';
        $record->location = trim(\core_text::substr((string) ($data->location ?? ''), 0, 255));
        $record->onlineurl = trim((string) ($data->onlineurl ?? ''));
        $record->audience = in_array($data->audience ?? '', self::AUDIENCES, true) ? $data->audience : 'invite';
        $record->courseid = (int) ($data->courseid ?? 0);
        $record->groupid = 0;
        if ($record->audience === 'group') {
            $group = $DB->get_record('groups', ['id' => (int) ($data->groupid ?? 0)], 'id, courseid', MUST_EXIST);
            $record->groupid = (int) $group->id;
            $record->courseid = (int) $group->courseid;
        } else if ($record->audience === 'site') {
            $record->courseid = 0;
        } else if ($record->audience === 'course' && !$record->courseid) {
            throw new \moodle_exception('errorcourserequired', 'local_meetups');
        }
        $record->capacity = max(0, (int) ($data->capacity ?? 0));
        $record->rsvpdeadline = max(0, (int) ($data->rsvpdeadline ?? 0));
        return $record;
    }

    /**
     * Replace the poll options, keeping the votes of options that did not change.
     *
     * @param \stdClass $meetup
     * @param array $options Each item has timestart and timeend.
     */
    public static function set_options(\stdClass $meetup, array $options): void {
        global $DB;
        $wanted = [];
        foreach ($options as $option) {
            $option = (object) $option;
            $start = (int) $option->timestart;
            $end = max($start, (int) $option->timeend);
            if ($start > 0) {
                $wanted[$start . '-' . $end] = [$start, $end];
            }
        }
        foreach ($DB->get_records('local_meetups_option', ['meetupid' => $meetup->id]) as $existing) {
            $key = $existing->timestart . '-' . $existing->timeend;
            if (isset($wanted[$key])) {
                unset($wanted[$key]);
                continue;
            }
            $DB->delete_records('local_meetups_vote', ['optionid' => $existing->id]);
            $DB->delete_records('local_meetups_option', ['id' => $existing->id]);
        }
        foreach ($wanted as [$start, $end]) {
            $DB->insert_record('local_meetups_option', (object) ['meetupid' => $meetup->id, 'timestart' => $start,
                'timeend' => $end, 'timecreated' => time()]);
        }
    }

    /**
     * The poll options in time order.
     *
     * @param \stdClass $meetup
     * @return \stdClass[]
     */
    public static function options(\stdClass $meetup): array {
        global $DB;
        return $DB->get_records('local_meetups_option', ['meetupid' => $meetup->id], 'timestart ASC, id ASC');
    }

    /**
     * Save one poll answer.
     *
     * @param \stdClass $meetup
     * @param int $optionid
     * @param int $response One of the VOTE_ constants.
     * @param int $userid
     */
    public static function vote(\stdClass $meetup, int $optionid, int $response, int $userid = 0): void {
        global $DB;
        $userid = self::uid($userid);
        if ($meetup->status !== self::STATUS_PLANNING) {
            throw new \moodle_exception('errorpollclosed', 'local_meetups');
        }
        if (!in_array($response, [self::VOTE_YES, self::VOTE_IFNEEDED, self::VOTE_NO], true)) {
            throw new \invalid_parameter_exception('Unknown poll answer.');
        }
        $DB->get_record('local_meetups_option', ['id' => $optionid, 'meetupid' => $meetup->id], 'id', MUST_EXIST);
        $existing = $DB->get_record('local_meetups_vote', ['optionid' => $optionid, 'userid' => $userid]);
        if ($existing) {
            $existing->response = $response;
            $existing->timemodified = time();
            $DB->update_record('local_meetups_vote', $existing);
        } else {
            $DB->insert_record('local_meetups_vote', (object) ['meetupid' => $meetup->id, 'optionid' => $optionid,
                'userid' => $userid, 'response' => $response, 'timemodified' => time()]);
        }
    }

    /**
     * Poll tally. Score counts yes as two points and if needed as one.
     *
     * @param \stdClass $meetup
     * @param int $userid Whose own answers to include.
     * @return array Options with counts, plus the number of voters.
     */
    public static function tally(\stdClass $meetup, int $userid = 0): array {
        global $DB;
        $userid = self::uid($userid);
        $options = self::options($meetup);
        $votes = $DB->get_records('local_meetups_vote', ['meetupid' => $meetup->id]);
        $result = [];
        foreach ($options as $option) {
            $result[$option->id] = ['id' => (int) $option->id, 'yes' => 0, 'ifneeded' => 0, 'no' => 0, 'score' => 0,
                'best' => false, 'myvote' => -1];
        }
        $voters = [];
        foreach ($votes as $vote) {
            if (!isset($result[$vote->optionid])) {
                continue;
            }
            $voters[$vote->userid] = true;
            $key = [self::VOTE_YES => 'yes', self::VOTE_IFNEEDED => 'ifneeded', self::VOTE_NO => 'no'][(int) $vote->response];
            $result[$vote->optionid][$key]++;
            if ((int) $vote->userid === $userid) {
                $result[$vote->optionid]['myvote'] = (int) $vote->response;
            }
        }
        $best = 0;
        foreach ($result as &$row) {
            $row['score'] = $row['yes'] * 2 + $row['ifneeded'];
            $best = max($best, $row['score']);
        }
        unset($row);
        foreach ($result as &$row) {
            $row['best'] = $best > 0 && $row['score'] === $best;
        }
        unset($row);
        return ['options' => array_values($result), 'voters' => count($voters)];
    }

    /**
     * Pick the final time from a poll option.
     *
     * Voters who answered yes are marked as going and those who answered if needed as maybe.
     *
     * @param \stdClass $meetup
     * @param int $optionid
     * @return \stdClass The updated meetup.
     */
    public static function finalise(\stdClass $meetup, int $optionid): \stdClass {
        global $DB;
        if ($meetup->status === self::STATUS_CANCELLED) {
            throw new \moodle_exception('errorcancelled', 'local_meetups');
        }
        $option = $DB->get_record('local_meetups_option', ['id' => $optionid, 'meetupid' => $meetup->id], '*', MUST_EXIST);
        $wasscheduled = $meetup->status === self::STATUS_SCHEDULED;
        $DB->update_record(self::TABLE, (object) ['id' => $meetup->id, 'status' => self::STATUS_SCHEDULED,
            'timestart' => $option->timestart, 'timeend' => $option->timeend, 'finaloptionid' => $option->id,
            'remindersent' => 0, 'timemodified' => time()]);
        $meetup = self::get($meetup->id);

        self::rsvp($meetup, 'going', '', (int) $meetup->userid, true);
        $votes = $DB->get_records('local_meetups_vote', ['optionid' => $option->id], 'timemodified ASC, id ASC');
        foreach ($votes as $vote) {
            if ((int) $vote->userid === (int) $meetup->userid || (int) $vote->response === self::VOTE_NO
                    || $DB->record_exists('local_meetups_rsvp', ['meetupid' => $meetup->id, 'userid' => $vote->userid])) {
                continue;
            }
            self::rsvp($meetup, (int) $vote->response === self::VOTE_YES ? 'going' : 'maybe', '', (int) $vote->userid, true);
        }
        self::sync_calendar($meetup);
        $recipients = array_diff(self::interested_users($meetup), [(int) $meetup->userid]);
        self::notify($meetup, $wasscheduled ? 'changed' : 'finalised', $recipients);
        return $meetup;
    }

    /**
     * Cancel a meetup.
     *
     * @param \stdClass $meetup
     * @return \stdClass
     */
    public static function cancel(\stdClass $meetup): \stdClass {
        global $DB, $USER;
        $recipients = array_diff(self::interested_users($meetup), [(int) $USER->id]);
        $DB->update_record(self::TABLE, (object) ['id' => $meetup->id, 'status' => self::STATUS_CANCELLED,
            'timemodified' => time()]);
        $meetup = self::get($meetup->id);
        self::sync_calendar($meetup);
        self::notify($meetup, 'cancelled', $recipients);
        return $meetup;
    }

    /**
     * Delete a meetup and everything attached to it.
     *
     * @param \stdClass $meetup
     */
    public static function delete(\stdClass $meetup): void {
        global $DB;
        $meetup->status = self::STATUS_CANCELLED;
        self::sync_calendar($meetup);
        foreach (['local_meetups_vote', 'local_meetups_option', 'local_meetups_rsvp', 'local_meetups_comment',
                'local_meetups_invite'] as $table) {
            $DB->delete_records($table, ['meetupid' => $meetup->id]);
        }
        $DB->delete_records(self::TABLE, ['id' => $meetup->id]);
    }

    /**
     * Whether replies are closed.
     *
     * @param \stdClass $meetup
     * @return bool
     */
    public static function rsvp_closed(\stdClass $meetup): bool {
        return $meetup->status !== self::STATUS_SCHEDULED
            || ($meetup->rsvpdeadline && time() > $meetup->rsvpdeadline)
            || ($meetup->timeend && time() > $meetup->timeend);
    }

    /**
     * Reply to a scheduled meetup. People who say going when it is full join the waitlist.
     *
     * @param \stdClass $meetup
     * @param string $response going, maybe or notgoing.
     * @param string $note Optional note.
     * @param int $userid
     * @param bool $force Ignore the deadline (used when the organiser finalises).
     * @return \stdClass The reply.
     */
    public static function rsvp(\stdClass $meetup, string $response, string $note = '', int $userid = 0,
            bool $force = false): \stdClass {
        global $DB;
        $userid = self::uid($userid);
        if (!in_array($response, self::RESPONSES, true)) {
            throw new \invalid_parameter_exception('Unknown reply.');
        }
        if (!$force && self::rsvp_closed($meetup)) {
            throw new \moodle_exception('errorrsvpclosed', 'local_meetups');
        }
        $now = time();
        $existing = $DB->get_record('local_meetups_rsvp', ['meetupid' => $meetup->id, 'userid' => $userid]);
        $hadplace = $existing && $existing->response === 'going' && !$existing->waitlisted;
        $record = $existing ?: (object) ['meetupid' => $meetup->id, 'userid' => $userid, 'timecreated' => $now];
        $record->note = trim(\core_text::substr($note, 0, 255));
        $record->timemodified = $now;
        if ($response === 'going') {
            if (!$hadplace) {
                $record->waitlisted = self::is_full($meetup, $userid) ? 1 : 0;
                if ($existing && $existing->response !== 'going') {
                    // Joining the queue now, so the queue position starts now.
                    $record->timecreated = $now;
                }
            }
        } else {
            $record->waitlisted = 0;
        }
        $record->response = $response;
        if ($existing) {
            $DB->update_record('local_meetups_rsvp', $record);
        } else {
            $record->id = $DB->insert_record('local_meetups_rsvp', $record);
        }
        if ($hadplace && $response !== 'going') {
            self::promote_waitlist($meetup);
        }
        if ($meetup->status === self::STATUS_SCHEDULED && !in_array($meetup->audience, ['course', 'group'], true)) {
            self::sync_calendar($meetup);
        }
        return $DB->get_record('local_meetups_rsvp', ['id' => $record->id]);
    }

    /**
     * Whether all places are taken, not counting the given user.
     *
     * @param \stdClass $meetup
     * @param int $exceptuserid
     * @return bool
     */
    public static function is_full(\stdClass $meetup, int $exceptuserid = 0): bool {
        global $DB;
        if (empty($meetup->capacity)) {
            return false;
        }
        $taken = $DB->count_records_select('local_meetups_rsvp',
            'meetupid = ? AND response = ? AND waitlisted = 0 AND userid <> ?', [$meetup->id, 'going', $exceptuserid]);
        return $taken >= $meetup->capacity;
    }

    /**
     * Give free places to the people waiting longest and tell them.
     *
     * @param \stdClass $meetup
     */
    public static function promote_waitlist(\stdClass $meetup): void {
        global $DB;
        while (!self::is_full($meetup)) {
            $next = $DB->get_records('local_meetups_rsvp', ['meetupid' => $meetup->id, 'response' => 'going',
                'waitlisted' => 1], 'timecreated ASC, id ASC', '*', 0, 1);
            if (!$next) {
                return;
            }
            $next = reset($next);
            $DB->set_field('local_meetups_rsvp', 'waitlisted', 0, ['id' => $next->id]);
            self::notify($meetup, 'changed', [(int) $next->userid], 'notify_promoted');
        }
    }

    /**
     * All replies with names, grouped by response.
     *
     * @param \stdClass $meetup
     * @return array Keys going, waitlist, maybe, notgoing.
     */
    public static function attendees(\stdClass $meetup): array {
        global $DB;
        // Picture fields for the author, with the user id renamed so it does not replace the record id.
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', 'authorid', false)->selects;
        $rows = $DB->get_records_sql("SELECT r.*, $fields
                                        FROM {local_meetups_rsvp} r
                                        JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                                       WHERE r.meetupid = ?
                                    ORDER BY r.timecreated ASC, r.id ASC", [$meetup->id]);
        $groups = ['going' => [], 'waitlist' => [], 'maybe' => [], 'notgoing' => []];
        foreach ($rows as $row) {
            $key = $row->response === 'going' && $row->waitlisted ? 'waitlist' : $row->response;
            $groups[$key][] = $row;
        }
        return $groups;
    }

    /**
     * Add a comment.
     *
     * @param \stdClass $meetup
     * @param string $content Plain text.
     * @param int $userid
     * @return int Comment id.
     */
    public static function add_comment(\stdClass $meetup, string $content, int $userid = 0): int {
        global $DB;
        $content = trim($content);
        if ($content === '') {
            throw new \moodle_exception('errorcommentempty', 'local_meetups');
        }
        return $DB->insert_record('local_meetups_comment', (object) ['meetupid' => $meetup->id,
            'userid' => self::uid($userid), 'content' => \core_text::substr($content, 0, 4000), 'timecreated' => time()]);
    }

    /**
     * Delete a comment written by the user, or any comment when the user can manage the meetup.
     *
     * @param \stdClass $meetup
     * @param int $commentid
     * @param int $userid
     */
    public static function delete_comment(\stdClass $meetup, int $commentid, int $userid = 0): void {
        global $DB;
        $userid = self::uid($userid);
        $comment = $DB->get_record('local_meetups_comment', ['id' => $commentid, 'meetupid' => $meetup->id], '*', MUST_EXIST);
        if ((int) $comment->userid !== $userid && !self::can_manage($meetup, $userid)) {
            throw new \required_capability_exception(self::context($meetup), 'local/meetups:moderate', 'nopermissions', '');
        }
        $DB->delete_records('local_meetups_comment', ['id' => $commentid]);
    }

    /**
     * Comments in time order with author names.
     *
     * @param \stdClass $meetup
     * @return \stdClass[]
     */
    public static function comments(\stdClass $meetup): array {
        global $DB;
        // Picture fields for the author, with the user id renamed so it does not replace the record id.
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', 'authorid', false)->selects;
        return $DB->get_records_sql("SELECT c.*, $fields
                                       FROM {local_meetups_comment} c
                                       JOIN {user} u ON u.id = c.userid
                                      WHERE c.meetupid = ?
                                   ORDER BY c.timecreated ASC, c.id ASC", [$meetup->id]);
    }

    /**
     * Make the calendar match the meetup: one course or group event when scoped, otherwise one user event per
     * person taking part. Cancelled or unscheduled meetups have no events.
     *
     * @param \stdClass $meetup
     */
    public static function sync_calendar(\stdClass $meetup): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $existing = [];
        foreach ($DB->get_records('event', ['component' => 'local_meetups', 'instance' => $meetup->id]) as $event) {
            $key = $event->eventtype === 'user' ? 'user:' . $event->userid : $event->eventtype;
            if (isset($existing[$key])) {
                \calendar_event::load($event)->delete();
                continue;
            }
            $existing[$key] = $event;
        }

        $wanted = [];
        if ($meetup->status === self::STATUS_SCHEDULED) {
            $base = [
                'name' => $meetup->title,
                'description' => self::calendar_description($meetup),
                'format' => FORMAT_HTML,
                'timestart' => (int) $meetup->timestart,
                'timeduration' => max(0, (int) $meetup->timeend - (int) $meetup->timestart),
                'location' => self::where($meetup),
                'component' => 'local_meetups',
                'modulename' => '',
                'instance' => (int) $meetup->id,
                'visible' => 1,
            ];
            if ($meetup->audience === 'course') {
                $wanted['course'] = $base + ['eventtype' => 'course', 'courseid' => (int) $meetup->courseid,
                    'groupid' => 0, 'userid' => (int) $meetup->userid];
            } else if ($meetup->audience === 'group') {
                $wanted['group'] = $base + ['eventtype' => 'group', 'courseid' => (int) $meetup->courseid,
                    'groupid' => (int) $meetup->groupid, 'userid' => (int) $meetup->userid];
            } else {
                $declined = array_map('intval', $DB->get_fieldset_select('local_meetups_rsvp', 'userid',
                    'meetupid = ? AND response = ?', [$meetup->id, 'notgoing']));
                $people = $meetup->audience === 'invite' ? self::invitees($meetup) : [(int) $meetup->userid];
                $people = array_merge($people, array_map('intval', $DB->get_fieldset_select('local_meetups_rsvp', 'userid',
                    'meetupid = ? AND response <> ?', [$meetup->id, 'notgoing'])));
                foreach (array_diff(array_unique($people), $declined) as $userid) {
                    $wanted['user:' . $userid] = $base + ['eventtype' => 'user', 'courseid' => 0, 'groupid' => 0,
                        'userid' => $userid];
                }
            }
        }

        foreach ($existing as $key => $event) {
            if (!isset($wanted[$key])) {
                \calendar_event::load($event)->delete();
                continue;
            }
            $changed = false;
            foreach ($wanted[$key] as $field => $value) {
                if ((string) $event->$field !== (string) $value) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                \calendar_event::load($event)->update((object) $wanted[$key], false);
            }
            unset($wanted[$key]);
        }
        foreach ($wanted as $data) {
            \calendar_event::create((object) $data, false);
        }
    }

    /**
     * Calendar event text with a link back to the meetup.
     *
     * @param \stdClass $meetup
     * @return string
     */
    protected static function calendar_description(\stdClass $meetup): string {
        $url = new \moodle_url('/local/meetups/view.php', ['id' => $meetup->id]);
        $text = format_text($meetup->description, $meetup->descriptionformat, ['context' => self::context($meetup)]);
        if ($meetup->onlineurl) {
            $text .= \html_writer::tag('p', \html_writer::link($meetup->onlineurl, s($meetup->onlineurl)));
        }
        return $text . \html_writer::tag('p', \html_writer::link($url, get_string('openmeetup', 'local_meetups')));
    }

    /**
     * Where the meetup happens, as one line.
     *
     * @param \stdClass $meetup
     * @return string
     */
    public static function where(\stdClass $meetup): string {
        $parts = [];
        if ($meetup->type !== 'online' && $meetup->location !== '' && $meetup->location !== null) {
            $parts[] = $meetup->location;
        }
        if ($meetup->type !== 'inperson' && $meetup->onlineurl) {
            $parts[] = get_string('type_online', 'local_meetups');
        }
        return implode(' + ', $parts);
    }

    /**
     * When the meetup happens, as readable text.
     *
     * @param \stdClass $meetup
     * @return string
     */
    public static function when(\stdClass $meetup): string {
        if ($meetup->status === self::STATUS_PLANNING || !$meetup->timestart) {
            return get_string('timetbd', 'local_meetups');
        }
        return self::format_range((int) $meetup->timestart, (int) $meetup->timeend);
    }

    /**
     * Format a start and end time.
     *
     * @param int $start
     * @param int $end
     * @return string
     */
    public static function format_range(int $start, int $end): string {
        $text = userdate($start, get_string('strftimedaydatetime', 'langconfig'));
        if ($end > $start) {
            $sameday = userdate($start, '%Y%m%d') === userdate($end, '%Y%m%d');
            $text .= ' – ' . userdate($end, get_string($sameday ? 'strftimetime' : 'strftimedaydatetime', 'langconfig'));
        }
        return $text;
    }

    /**
     * Send a notification about the meetup.
     *
     * @param \stdClass $meetup
     * @param string $provider invitation, finalised, changed, cancelled or reminder.
     * @param int[] $userids Recipients.
     * @param string|null $stringkey Body string, defaults to notify_{provider}.
     * @return int Number of messages sent.
     */
    public static function notify(\stdClass $meetup, string $provider, array $userids, ?string $stringkey = null): int {
        global $USER;
        $url = new \moodle_url('/local/meetups/view.php', ['id' => $meetup->id]);
        $organiser = \core_user::get_user($meetup->userid);
        $a = (object) [
            'title' => format_string($meetup->title, true, ['context' => self::context($meetup)]),
            'organiser' => $organiser ? fullname($organiser) : '',
            'when' => self::when($meetup),
            'where' => self::where($meetup) ?: '-',
            'url' => $url->out(false),
            'hours' => self::reminder_hours(),
        ];
        $stringkey = $stringkey ?? 'notify_' . $provider;
        $from = isloggedin() && !isguestuser() ? \core_user::get_user($USER->id) : \core_user::get_noreply_user();
        $sent = 0;
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $user = \core_user::get_user($userid);
            if (!$user || $user->deleted || $user->suspended || isguestuser($user)) {
                continue;
            }
            $message = new \core\message\message();
            $message->component = 'local_meetups';
            $message->name = $provider;
            $message->userfrom = $from;
            $message->userto = $user;
            $message->subject = get_string($stringkey . '_subject', 'local_meetups', $a);
            $message->fullmessage = get_string($stringkey, 'local_meetups', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = $a->title;
            $message->courseid = $meetup->courseid ?: SITEID;
            if (message_send($message)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Send due reminders.
     *
     * @param int|null $now
     * @return int Number of meetups reminded.
     */
    public static function send_due_reminders(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $hours = self::reminder_hours();
        if (!self::enabled() || $hours <= 0) {
            return 0;
        }
        $meetups = $DB->get_records_select(self::TABLE, 'status = ? AND remindersent = 0 AND timestart > ? AND timestart <= ?',
            [self::STATUS_SCHEDULED, $now, $now + $hours * HOURSECS]);
        foreach ($meetups as $meetup) {
            $going = $DB->get_fieldset_select('local_meetups_rsvp', 'userid',
                "meetupid = ? AND (response = 'maybe' OR (response = 'going' AND waitlisted = 0))", [$meetup->id]);
            $going[] = (int) $meetup->userid;
            $DB->set_field(self::TABLE, 'remindersent', 1, ['id' => $meetup->id]);
            self::notify($meetup, 'reminder', $going);
        }
        return count($meetups);
    }

    /**
     * The meetup as an iCalendar file.
     *
     * @param \stdClass $meetup
     * @return string
     */
    public static function ics(\stdClass $meetup): string {
        global $CFG;
        $escape = function(string $value): string {
            $value = str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], $value);
            return $value;
        };
        $fold = function(string $line): string {
            $out = '';
            while (strlen($line) > 74) {
                $cut = 74;
                while ($cut > 1 && (ord($line[$cut]) & 0xC0) === 0x80) {
                    $cut--;
                }
                $out .= substr($line, 0, $cut) . "\r\n ";
                $line = substr($line, $cut);
            }
            return $out . $line . "\r\n";
        };
        $url = (new \moodle_url('/local/meetups/view.php', ['id' => $meetup->id]))->out(false);
        $description = trim(html_to_text(format_text($meetup->description, $meetup->descriptionformat,
            ['context' => self::context($meetup)]), 0, false));
        $description = trim($description . "\n" . ($meetup->onlineurl ?? '') . "\n" . $url);
        $description = preg_replace("/\n{2,}/", "\n", $description);
        $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'moodle';
        $end = max((int) $meetup->timeend, (int) $meetup->timestart);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Moodle//local_meetups//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:meetup-' . $meetup->id . '@' . $host,
            'DTSTAMP:' . gmdate('Ymd\THis\Z', (int) $meetup->timemodified ?: time()),
            'DTSTART:' . gmdate('Ymd\THis\Z', (int) $meetup->timestart),
            'DTEND:' . gmdate('Ymd\THis\Z', $end),
            'SUMMARY:' . $escape(format_string($meetup->title, true, ['context' => self::context($meetup)])),
            'DESCRIPTION:' . $escape($description),
            'LOCATION:' . $escape(self::where($meetup) === get_string('type_online', 'local_meetups')
                ? (string) $meetup->onlineurl : (string) $meetup->location),
            'URL:' . $url,
            'STATUS:' . ($meetup->status === self::STATUS_CANCELLED ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode('', array_map($fold, $lines));
    }

    /**
     * Meetups for one index tab that the user may see.
     *
     * @param string $tab upcoming, planning, past or mine.
     * @param int $courseid Limit to one course, 0 for all.
     * @param int $userid
     * @return \stdClass[]
     */
    public static function list_for_tab(string $tab, int $courseid = 0, int $userid = 0): array {
        global $DB;
        $userid = self::uid($userid);
        $now = time();
        $where = [];
        $params = [];
        switch ($tab) {
            case 'planning':
                $where[] = 'm.status = :planning';
                $params['planning'] = self::STATUS_PLANNING;
                $order = 'm.timecreated DESC';
                break;
            case 'past':
                $where[] = '((m.status = :scheduled AND m.timeend < :now) OR m.status = :cancelled)';
                $params += ['scheduled' => self::STATUS_SCHEDULED, 'now' => $now, 'cancelled' => self::STATUS_CANCELLED];
                $order = 'm.timestart DESC, m.timemodified DESC';
                break;
            case 'mine':
                $where[] = '(m.userid = :u1 OR EXISTS (SELECT 1 FROM {local_meetups_rsvp} r WHERE r.meetupid = m.id AND r.userid = :u2)
                    OR EXISTS (SELECT 1 FROM {local_meetups_vote} v WHERE v.meetupid = m.id AND v.userid = :u3)
                    OR EXISTS (SELECT 1 FROM {local_meetups_invite} i WHERE i.meetupid = m.id AND i.userid = :u4))';
                $params += ['u1' => $userid, 'u2' => $userid, 'u3' => $userid, 'u4' => $userid];
                $order = 'm.timemodified DESC';
                break;
            default:
                $where[] = 'm.status = :scheduled AND m.timeend >= :now';
                $params += ['scheduled' => self::STATUS_SCHEDULED, 'now' => $now];
                $order = 'm.timestart ASC';
        }
        if ($courseid) {
            $where[] = 'm.courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        $records = $DB->get_records_sql('SELECT m.* FROM {' . self::TABLE . '} m WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . $order, $params, 0, 500);
        return array_filter($records, function($meetup) use ($userid) {
            return self::can_view($meetup, $userid);
        });
    }

    /**
     * Template data for a meetup card.
     *
     * @param \stdClass $meetup
     * @param int $userid
     * @return array
     */
    public static function export_card(\stdClass $meetup, int $userid = 0): array {
        global $DB;
        $userid = self::uid($userid);
        $context = self::context($meetup);
        $organiser = \core_user::get_user($meetup->userid);
        $rsvp = $DB->get_record('local_meetups_rsvp', ['meetupid' => $meetup->id, 'userid' => $userid]);
        $going = $DB->count_records('local_meetups_rsvp', ['meetupid' => $meetup->id, 'response' => 'going', 'waitlisted' => 0]);
        $myreply = '';
        if ($rsvp) {
            $myreply = get_string($rsvp->response === 'going' && $rsvp->waitlisted ? 'waitlisted' : 'rsvp_' . $rsvp->response,
                'local_meetups');
        }
        $audience = get_string('audience_' . $meetup->audience, 'local_meetups');
        if ($meetup->courseid && ($course = $DB->get_record('course', ['id' => $meetup->courseid], 'id, shortname'))) {
            $audience .= ': ' . format_string($course->shortname, true, ['context' => $context]);
            if ($meetup->groupid && ($group = $DB->get_record('groups', ['id' => $meetup->groupid], 'id, name'))) {
                $audience .= ' / ' . format_string($group->name, true, ['context' => $context]);
            }
        }
        return [
            'id' => (int) $meetup->id,
            'title' => format_string($meetup->title, true, ['context' => $context]),
            'url' => (new \moodle_url('/local/meetups/view.php', ['id' => $meetup->id]))->out(false),
            'type' => $meetup->type,
            'typelabel' => get_string('type_' . $meetup->type, 'local_meetups'),
            'status' => $meetup->status,
            'statuslabel' => get_string('status_' . $meetup->status, 'local_meetups'),
            'when' => self::when($meetup),
            'where' => self::where($meetup),
            'organiser' => $organiser ? fullname($organiser) : '',
            'audience' => $audience,
            'going' => $going,
            'capacity' => (int) $meetup->capacity,
            'myreply' => $myreply,
            'isplanning' => $meetup->status === self::STATUS_PLANNING,
            'iscancelled' => $meetup->status === self::STATUS_CANCELLED,
            'day' => $meetup->timestart ? userdate($meetup->timestart, '%d') : '?',
            'month' => $meetup->timestart ? userdate($meetup->timestart, '%b') : '',
        ];
    }
}
