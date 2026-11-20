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


namespace local_materialtools;

use core\hook\output\before_footer_html_generation;

/**
 * Starts the vocabulary pack for chosen people and the drop-to-upload area for managers.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Add the scripts.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $config = ['url' => '', 'drop' => ''];
        if (vocabulary::eligible()) {
            $config['url'] = (new \moodle_url('/local/materialtools/vocab.php',
                ['v' => get_config('local_materialtools', 'vocabhash')]))->out(false);
        }
        if (has_capability('local/materialtools:managevocabulary', \context_system::instance())) {
            $config['drop'] = (new \moodle_url('/local/materialtools/vocabulary.php', ['action' => 'uploadjson',
                'sesskey' => sesskey()]))->out(false);
            $config['strings'] = [
                'dropinvalid' => get_string('dropinvalid', 'local_materialtools'),
                'vocabapplied' => get_string('vocabapplied', 'local_materialtools'),
            ];
        }
        if ($config['url'] || $config['drop']) {
            $PAGE->requires->js_call_amd('local_materialtools/vocabulary', 'init', [$config]);
        }
        self::labels();
    }

    /**
     * Activity type names, personal titles and quick post buttons for the current course.
     */
    private static function labels(): void {
        global $PAGE;
        $courseid = (int) ($PAGE->course->id ?? 0);
        if (!$courseid) {
            return;
        }
        $data = [
            'labels' => (object) labels::course_labels($courseid),
            'titles' => (object) labels::titles_for_course($courseid),
            'cmid' => 0,
            'modname' => '',
            'quickposts' => [],
            'strings' => ['postnew' => get_string('postnew', 'local_materialtools'),
                'newposttype' => get_string('newposttype', 'local_materialtools')],
        ];
        if ($PAGE->cm) {
            $data['cmid'] = (int) $PAGE->cm->id;
            $data['modname'] = get_string('modulename', $PAGE->cm->modname);
        }
        $context = \context_course::instance($courseid);
        if (strpos($PAGE->pagetype, 'course-view') === 0 && $PAGE->user_is_editing()
                && has_capability('moodle/course:manageactivities', $context)) {
            foreach (labels::quick_posts() as $post) {
                $data['quickposts'][] = ['label' => $post['label'], 'url' => (new \moodle_url('/course/modedit.php', [
                    'add' => $post['modname'], 'course' => $courseid, 'section' => 0, 'return' => 0,
                    'materiallabel' => $post['label']]))->out(false)];
            }
            if (has_capability('moodle/site:config', \context_system::instance())) {
                $data['newposttypeurl'] = (new \moodle_url('/admin/settings.php',
                    ['section' => 'local_materialtools_settings']))->out(false);
            }
        }
        if ((array) $data['labels'] || (array) $data['titles'] || $data['quickposts']) {
            $PAGE->requires->js_call_amd('local_materialtools/labels', 'init', [$data]);
        }
    }
}
