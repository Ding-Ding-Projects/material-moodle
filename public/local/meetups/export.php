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
 * Download a scheduled meetup as an iCalendar (.ics) file.
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_meetups\meetup;

$id = required_param('id', PARAM_INT);
require_login(null, false);
$record = meetup::get($id);
$PAGE->set_context(meetup::context($record));
$PAGE->set_url(new moodle_url('/local/meetups/export.php', ['id' => $id]));
if (!meetup::can_view($record)) {
    throw new moodle_exception('errornoaccess', 'local_meetups');
}
if ($record->status !== meetup::STATUS_SCHEDULED) {
    throw new moodle_exception('errornotscheduled', 'local_meetups');
}

$filename = clean_filename(\core_text::substr($record->title, 0, 60)) ?: 'meetup';
\core\session\manager::write_close();
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.ics"');
header('Cache-Control: private, max-age=0');
echo meetup::ics($record);
