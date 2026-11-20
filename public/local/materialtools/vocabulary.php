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
 * Custom vocabulary pack: upload, who sees it, and the chosen students.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_materialtools\vocabulary;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

$action = optional_param('action', '', PARAM_ALPHA);

// JSON upload used by the drop zone, here and anywhere on the site.
if ($action === 'uploadjson') {
    define('NO_DEBUG_DISPLAY', true);
    require_login(null, false);
    require_capability('local/materialtools:managevocabulary', context_system::instance());
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        require_sesskey();
        $result = vocabulary::store(file_get_contents('php://input') ?: '');
        echo json_encode(['success' => true, 'count' => $result['count'],
            'headline' => $result['name'] !== '' ? $result['name'] : get_string('vocabapplied', 'local_materialtools')]);
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e instanceof moodle_exception ? $e->getMessage()
            : get_string('vocaberrorsyntax', 'local_materialtools')]);
    }
    die();
}

admin_externalpage_setup('local_materialtools_vocabulary');
$url = new moodle_url('/local/materialtools/vocabulary.php');
$c = 'local_materialtools';

if ($action === 'upload' && confirm_sesskey()) {
    $content = '';
    if (!empty($_FILES['vocabfile']['tmp_name']) && is_uploaded_file($_FILES['vocabfile']['tmp_name'])) {
        $content = (string) file_get_contents($_FILES['vocabfile']['tmp_name']);
    }
    try {
        $result = vocabulary::store($content);
        redirect($url, get_string('vocabloaded', $c, $result['count']), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
if ($action === 'clear' && confirm_sesskey()) {
    vocabulary::clear();
    redirect($url, get_string('vocabcleared', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'audience' && confirm_sesskey()) {
    $audience = required_param('audience', PARAM_ALPHA);
    if (in_array($audience, ['nobody', 'everyone', 'selected'])) {
        set_config('vocabaudience', $audience, $c);
    }
    $cohortids = optional_param_array('cohorts', [], PARAM_INT);
    set_config('vocabcohorts', implode(',', array_filter($cohortids)), $c);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'removeuser' && confirm_sesskey()) {
    vocabulary::set_user(required_param('userid', PARAM_INT), false);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$usersform = new \local_materialtools\form\vocabusers($url);
if ($data = $usersform->get_data()) {
    foreach ((array) $data->userids as $userid) {
        vocabulary::set_user((int) $userid, true);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$audience = vocabulary::audience();
$chosencohorts = vocabulary::cohorts();
$cohorts = [];
foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
    $cohorts[] = ['id' => $cohort->id, 'name' => format_string($cohort->name),
        'selected' => in_array((int) $cohort->id, $chosencohorts)];
}
$users = [];
foreach (vocabulary::chosen_users() as $user) {
    $users[] = ['id' => $user->id, 'fullname' => fullname($user), 'username' => $user->username];
}

$PAGE->requires->js_call_amd('local_materialtools/vocabadmin', 'init', [[
    'endpoint' => (new moodle_url('/local/materialtools/vocabulary.php', ['action' => 'uploadjson',
        'sesskey' => sesskey()]))->out(false),
    'reload' => $url->out(false),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_materialtools/vocabulary', [
    'present' => vocabulary::present(),
    'count' => (int) get_config($c, 'vocabcount'),
    'updated' => vocabulary::present() ? userdate((int) get_config($c, 'vocabtime')) : '',
    'sesskey' => sesskey(),
    'actionurl' => $url->out(false),
    'audiences' => array_map(fn($a) => ['value' => $a, 'label' => get_string('audience' . $a, $c),
        'checked' => $audience === $a], ['nobody', 'everyone', 'selected']),
    'cohorts' => $cohorts,
    'hascohorts' => !empty($cohorts),
    'users' => $users,
    'hasusers' => !empty($users),
    'usersform' => $usersform->render(),
]);
echo $OUTPUT->footer();
