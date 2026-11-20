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

/**
 * Create or edit a meetup.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_meetups\meetup;

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use meetups.');
}
if (!meetup::enabled()) {
    throw new moodle_exception('errordisabled', 'local_meetups');
}
$c = 'local_meetups';
$record = $id ? meetup::get($id) : null;
if ($record) {
    if (!meetup::can_manage($record)) {
        throw new required_capability_exception(meetup::context($record), 'local/meetups:moderate', 'nopermissions', '');
    }
    $courseid = (int) $record->courseid;
}

$context = $courseid && $courseid != SITEID ? context_course::instance($courseid) : context_system::instance();
$PAGE->set_context($context);
if ($context->contextlevel == CONTEXT_COURSE) {
    $PAGE->set_course(get_course($courseid));
}
$PAGE->set_url(new moodle_url('/local/meetups/edit.php', ['id' => $id ?: null, 'courseid' => $courseid ?: null]));
$PAGE->set_pagelayout($context->contextlevel == CONTEXT_COURSE ? 'incourse' : 'standard');
$PAGE->set_title(get_string($record ? 'editmeetup' : 'newmeetup', $c));
$PAGE->set_heading(get_string($record ? 'editmeetup' : 'newmeetup', $c));
$PAGE->add_body_class('local-meetups-page');
$PAGE->navbar->add(get_string('meetups', $c), new moodle_url('/local/meetups/index.php', ['courseid' => $courseid ?: null]));

// Courses and groups the user may plan meetups for.
$courses = [];
$groups = [];
$mycourses = enrol_get_my_courses(['id', 'shortname', 'fullname']);
if ($courseid && $courseid != SITEID && !isset($mycourses[$courseid])) {
    $mycourses[$courseid] = get_course($courseid);
}
foreach ($mycourses as $course) {
    $coursecontext = context_course::instance($course->id);
    if (!meetup::can_create($coursecontext) && !($record && (int) $record->courseid === (int) $course->id)) {
        continue;
    }
    $courses[$course->id] = format_string($course->fullname, true, ['context' => $coursecontext]);
    $all = has_capability('moodle/site:accessallgroups', $coursecontext);
    foreach (groups_get_all_groups($course->id, $all ? 0 : $USER->id) as $group) {
        $groups[$group->id] = format_string($course->shortname, true, ['context' => $coursecontext]) . ' / ' .
            format_string($group->name, true, ['context' => $coursecontext]);
    }
}
$cansite = meetup::can_create(context_system::instance());
$audiences = [];
if ($cansite || $courses) {
    $audiences['invite'] = get_string('audience_invite', $c);
}
if ($courses) {
    $audiences['course'] = get_string('audience_course', $c);
}
if ($groups) {
    $audiences['group'] = get_string('audience_group', $c);
}
if ($cansite && meetup::site_meetups_allowed()) {
    $audiences['site'] = get_string('audience_site', $c);
}
if ($record && !isset($audiences[$record->audience])) {
    $audiences[$record->audience] = get_string('audience_' . $record->audience, $c);
}
if (!$audiences) {
    throw new required_capability_exception($context, 'local/meetups:create', 'nopermissions', '');
}

$existingoptions = $record ? array_values(meetup::options($record)) : [];
$form = new \local_meetups\form\edit(null, [
    'meetup' => $record,
    'courses' => $courses,
    'groups' => $groups,
    'audiences' => $audiences,
    'optioncount' => max(3, count($existingoptions) + 1),
]);

$returnurl = $record ? new moodle_url('/local/meetups/view.php', ['id' => $record->id])
    : new moodle_url('/local/meetups/index.php', ['courseid' => $courseid ?: null]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $data->description = $data->description_editor['text'] ?? '';
    $data->descriptionformat = $data->description_editor['format'] ?? FORMAT_HTML;
    $data->rsvpdeadline = $data->rsvpdeadline ?? 0;
    if ($data->audience !== 'course') {
        $data->courseid = 0;
    }
    $invitees = array_map('intval', (array) ($data->invitees ?? []));
    $options = \local_meetups\form\edit::options_from_data($data);
    if (isset($data->timestart)) {
        $data->timeend = (int) $data->timestart + (int) $data->duration;
    }
    if ($record) {
        $updated = meetup::update($record, $data, $options, $invitees);
        redirect(new moodle_url('/local/meetups/view.php', ['id' => $updated->id, 'done' => 'saved']));
    }
    $created = meetup::create($data, $options ?? [], $invitees, !empty($data->finalisenow));
    redirect(new moodle_url('/local/meetups/view.php', ['id' => $created->id, 'done' => 'created']));
}

if ($record) {
    $defaults = clone $record;
    $defaults->description_editor = ['text' => $record->description, 'format' => $record->descriptionformat];
    $defaults->invitees = $DB->get_fieldset_select('local_meetups_invite', 'userid', 'meetupid = ?', [$record->id]);
    if ($record->status === meetup::STATUS_SCHEDULED) {
        $defaults->duration = max(0, $record->timeend - $record->timestart);
    } else {
        $defaults->optionstart = [];
        $defaults->optionduration = [];
        foreach ($existingoptions as $i => $option) {
            $defaults->optionstart[$i] = $option->timestart;
            $defaults->optionduration[$i] = max(0, $option->timeend - $option->timestart);
        }
    }
    $form->set_data($defaults);
} else {
    $start = (int) (ceil(time() / HOURSECS) * HOURSECS) + DAYSECS;
    $form->set_data([
        'audience' => $courseid && isset($courses[$courseid]) ? 'course' : 'invite',
        'courseid' => $courseid,
        'optionstart' => [$start, 0, 0],
        'optionduration' => [HOURSECS, HOURSECS, HOURSECS],
        'capacity' => 0,
    ]);
}

echo $OUTPUT->header();
echo html_writer::start_div('local-meetups local-meetups-edit');
echo html_writer::tag('p', get_string($record ? 'edit_intro' : 'new_intro', $c), ['class' => 'lead']);
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
