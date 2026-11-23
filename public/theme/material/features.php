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
 * Every Moodle feature the signed-in person can reach, on one page.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = optional_param('course', 0, PARAM_INT);
$course = $courseid ? get_course($courseid) : null;

if ($course && $course->id != SITEID) {
    require_login($course);
    $context = context_course::instance($course->id);
} else {
    require_login();
    $course = null;
    $context = context_system::instance();
}
if (isguestuser()) {
    throw new require_login_exception(get_string('featuresguest', 'theme_material'));
}

$url = new moodle_url('/theme/material/features.php', $course ? ['course' => $course->id] : []);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout($course ? 'incourse' : 'standard');
$PAGE->set_title(get_string('featurestitle', 'theme_material'));
$PAGE->set_heading(get_string('featurestitle', 'theme_material'));
$PAGE->add_body_class('mm-features-page');

$groups = \theme_material\features::groups($course);
$data = [
    'groups' => $groups,
    'page' => true,
    'total' => \theme_material\features::count($groups),
    'offcount' => count(array_filter(array_merge(...array_column($groups, 'items') ?: [[]]),
        fn($item) => $item['off'])),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_material/features_page', $data);
echo $OUTPUT->footer();
