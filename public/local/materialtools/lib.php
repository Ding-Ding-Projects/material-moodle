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
 * Material tools callbacks.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add "Type name" to every activity settings form, so one assignment can be a "Dropbox".
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_materialtools_coursemodule_standard_elements($formwrapper, $mform) {
    $c = 'local_materialtools';
    $cm = $formwrapper->get_coursemodule();
    $current = $cm ? \local_materialtools\labels::cm_label((int) $cm->id) : optional_param('materiallabel', '', PARAM_TEXT);
    $mform->addElement('header', 'materiallabelhdr', get_string('typename', $c));
    $mform->addElement('text', 'materiallabel', get_string('typename', $c), ['maxlength' => 100,
        'placeholder' => get_string('typename_placeholder', $c)]);
    $mform->setType('materiallabel', PARAM_TEXT);
    $mform->setDefault('materiallabel', $current);
    $mform->addHelpButton('materiallabel', 'typename', $c);
    if ($current !== '') {
        $mform->setExpanded('materiallabelhdr', true);
    }
}

/**
 * Save the type name after the activity is saved.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_materialtools_coursemodule_edit_post_actions($data, $course) {
    if (isset($data->materiallabel) && !empty($data->coursemodule)) {
        \local_materialtools\labels::set_cm_label((int) $data->coursemodule, (int) $course->id, (string) $data->materiallabel);
    }
    return $data;
}

/**
 * Remove a type name with its activity.
 *
 * @param stdClass $cm
 */
function local_materialtools_pre_course_module_delete($cm) {
    global $DB;
    $DB->delete_records(\local_materialtools\labels::CM, ['cmid' => $cm->id]);
}

/**
 * Send a site administrator to the setup wizard once, right after installation.
 *
 * @param stdClass|int|null $courseorid
 * @param bool $autologinguest
 * @param stdClass|null $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 */
function local_materialtools_after_require_login($courseorid = null, $autologinguest = true, $cm = null,
        $setwantsurltome = true, $preventredirect = false) {
    global $SCRIPT;
    if ($preventredirect || AJAX_SCRIPT || CLI_SCRIPT || !is_siteadmin() || \core\session\manager::is_loggedinas()) {
        return;
    }
    if (!get_config('local_materialtools', 'onboardingpending') || strpos((string) $SCRIPT, '/local/materialtools/') !== false
            || strpos((string) $SCRIPT, '/admin/') !== false) {
        return;
    }
    unset_config('onboardingpending', 'local_materialtools');
    redirect(new moodle_url('/local/materialtools/onboarding.php'));
}

/**
 * Link to your own wording pack from the preferences page.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_materialtools_extend_navigation_user_settings(navigation_node $navigation, $user, $usercontext, $course,
        $coursecontext) {
    global $USER;
    if ($user->id != $USER->id || isguestuser()) {
        return;
    }
    $navigation->add(get_string('displaypreferences', 'local_materialtools'), new moodle_url('/local/materialtools/display.php'),
        navigation_node::TYPE_SETTING, null, 'local_materialtools_display');
    if (!\local_materialtools\vocabulary::personal_allowed()) {
        return;
    }
    $navigation->add(get_string('personalvocab', 'local_materialtools'), new moodle_url('/local/materialtools/mypack.php'),
        navigation_node::TYPE_SETTING, null, 'local_materialtools_mypack');
}
