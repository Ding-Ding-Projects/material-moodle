<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_login(null, false);
require_sesskey();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isguestuser()) {
    throw new moodle_exception('nopermissions', 'error', '', 'change personal motion');
}
$choice = required_param('motion', PARAM_ALPHA);
if (!in_array($choice, ['expressive', 'calm', 'off'], true)) {
    throw new invalid_parameter_exception('Unrecognised motion choice');
}
$prefs = \local_materialtools\display_preferences::current();
$prefs['motion'] = $choice === 'expressive' ? 'site' : $choice;
set_user_preference(\local_materialtools\display_preferences::PREF, json_encode($prefs));
header('Content-Type: application/json');
echo json_encode(['motion' => \theme_material\settings_values::js_config()['motion']]);
