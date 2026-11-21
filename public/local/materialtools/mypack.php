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
 * Your own wording pack: a vocabulary JSON that applies only to you.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_materialtools\vocabulary;

if (($_GET['action'] ?? '') === 'uploadjson') {
    define('AJAX_SCRIPT', true);
    define('NO_DEBUG_DISPLAY', true);
}

require(__DIR__ . '/../../config.php');

$action = optional_param('action', '', PARAM_ALPHA);

$c = 'local_materialtools';

if ($action === 'uploadjson') {
    require_login(null, false);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        require_sesskey();
        if (isguestuser() || !vocabulary::personal_allowed()) {
            throw new moodle_exception('personalvocaboff', $c);
        }
        $result = vocabulary::store_personal((int) $USER->id, file_get_contents('php://input') ?: '');
        echo json_encode(['success' => true, 'count' => $result['count'],
            'headline' => $result['name'] !== '' ? $result['name'] : get_string('personalvocabapplied', $c)]);
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e instanceof moodle_exception ? $e->getMessage()
            : get_string('vocaberrorsyntax', $c)]);
    }
    die();
}

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$url = new moodle_url('/local/materialtools/mypack.php');
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('personalvocab', $c));
$PAGE->set_heading(get_string('personalvocab', $c));

if (!vocabulary::personal_allowed()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('personalvocaboff', $c), 'info');
    echo $OUTPUT->footer();
    die();
}

if ($action === 'upload' && confirm_sesskey()) {
    $content = '';
    if (!empty($_FILES['vocabfile']['tmp_name']) && is_uploaded_file($_FILES['vocabfile']['tmp_name'])) {
        $content = (string) file_get_contents($_FILES['vocabfile']['tmp_name']);
    }
    if ($content === '') {
        $content = optional_param('vocabtext', '', PARAM_RAW);
    }
    try {
        $result = vocabulary::store_personal((int) $USER->id, $content);
        redirect($url, get_string('personalvocabsaved', $c, $result['count']), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
if ($action === 'clear' && confirm_sesskey()) {
    vocabulary::clear_personal((int) $USER->id);
    redirect($url, get_string('personalvocabcleared', $c), null, \core\output\notification::NOTIFY_SUCCESS);
}

$info = vocabulary::personal_info((int) $USER->id);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_materialtools/mypack', [
    'present' => (bool) $info,
    'count' => $info['count'] ?? 0,
    'updated' => $info ? userdate($info['time']) : '',
    'sitepack' => vocabulary::eligible(),
    'actionurl' => $url->out(false),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
