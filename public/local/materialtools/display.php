<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
use local_materialtools\display_preferences;

require(__DIR__ . '/../../config.php');
require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$url = new moodle_url('/local/materialtools/display.php');
$back = new moodle_url('/user/preferences.php');
$title = get_string('displaypreferences', 'local_materialtools');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$form = new \local_materialtools\form\display($url);
$form->set_data(display_preferences::current());
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    $selected = display_preferences::normalise((array)$data);
    if (count(array_unique($selected)) === 1 && $selected['motion'] === 'site') {
        unset_user_preference(display_preferences::PREF);
    } else {
        set_user_preference(display_preferences::PREF, json_encode($selected));
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('displaypreferenceshelp', 'local_materialtools'));
$form->display();
echo $OUTPUT->footer();
