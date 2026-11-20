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
 * Meetups index: upcoming, planning, past and my meetups.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_meetups\meetup;

$tab = optional_param('tab', 'upcoming', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$tabs = ['upcoming', 'planning', 'past', 'mine'];
if (!in_array($tab, $tabs, true)) {
    $tab = 'upcoming';
}

if ($courseid && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
    $PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
} else {
    $courseid = 0;
    require_login(null, false);
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_heading(get_string('meetups', 'local_meetups'));
}
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use meetups.');
}
if (!meetup::enabled()) {
    throw new moodle_exception('errordisabled', 'local_meetups');
}
require_capability('local/meetups:view', $context);

$PAGE->set_url(new moodle_url('/local/meetups/index.php', ['tab' => $tab, 'courseid' => $courseid ?: null]));
$PAGE->set_title(get_string('meetups', 'local_meetups'));
$PAGE->set_pagelayout($courseid ? 'incourse' : 'standard');
$PAGE->add_body_class('local-meetups-page');

$cards = [];
foreach (meetup::list_for_tab($tab, $courseid) as $record) {
    $cards[] = meetup::export_card($record);
}
foreach ($cards as $i => &$card) {
    $card['index'] = min($i, 12);
}
unset($card);

$tabdata = [];
foreach ($tabs as $key) {
    $tabdata[] = [
        'key' => $key,
        'label' => get_string('tab_' . $key, 'local_meetups'),
        'url' => (new moodle_url('/local/meetups/index.php', ['tab' => $key, 'courseid' => $courseid ?: null]))->out(false),
        'active' => $key === $tab,
    ];
}

$data = [
    'tabs' => $tabdata,
    'cards' => $cards,
    'hascards' => !empty($cards),
    'empty' => get_string('empty_' . $tab, 'local_meetups'),
    'cancreate' => meetup::can_create($context),
    'createurl' => (new moodle_url('/local/meetups/edit.php', ['courseid' => $courseid ?: null]))->out(false),
    'coursename' => $courseid ? format_string($course->shortname, true, ['context' => $context]) : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_meetups/index', $data);
echo $OUTPUT->footer();
