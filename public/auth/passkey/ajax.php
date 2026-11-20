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
 * JSON endpoint for passkey ceremonies.
 *
 * Actions: loginoptions, login (anyone), registeroptions, register (signed-in users). Every action needs the sesskey.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/authlib.php');

$action = required_param('action', PARAM_ALPHA);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/auth/passkey/ajax.php'));

/**
 * Send JSON and stop.
 *
 * @param array|stdClass $data
 * @param int $status
 */
function auth_passkey_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    die();
}

try {
    if (!confirm_sesskey()) {
        throw new moodle_exception('invalidsesskey');
    }
    $body = json_decode(file_get_contents('php://input') ?: '{}');
    $body = is_object($body) ? $body : new stdClass();

    switch ($action) {
        case 'loginoptions':
            if (!is_enabled_auth('passkey')) {
                throw new moodle_exception('errordisabled', 'auth_passkey');
            }
            auth_passkey_json(\auth_passkey\manager::login_options());
            break;

        case 'login':
            if (!is_enabled_auth('passkey')) {
                throw new moodle_exception('errordisabled', 'auth_passkey');
            }
            $user = \auth_passkey\manager::verify_login(
                clean_param($body->id ?? '', PARAM_RAW_TRIMMED),
                clean_param($body->clientDataJSON ?? '', PARAM_RAW_TRIMMED),
                clean_param($body->authenticatorData ?? '', PARAM_RAW_TRIMMED),
                clean_param($body->signature ?? '', PARAM_RAW_TRIMMED)
            );
            $wantsurl = clean_param($body->wantsurl ?? '', PARAM_LOCALURL);
            if ($wantsurl) {
                $SESSION->wantsurl = $wantsurl;
            }
            complete_user_login($user);
            \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
            require_once($CFG->dirroot . '/login/lib.php');
            auth_passkey_json(['success' => true, 'redirect' => core_login_get_return_url()]);
            break;

        case 'registeroptions':
            require_login(null, false, null, false, true);
            if (isguestuser()) {
                throw new moodle_exception('noguest');
            }
            auth_passkey_json(\auth_passkey\manager::registration_options($USER));
            break;

        case 'register':
            require_login(null, false, null, false, true);
            if (isguestuser()) {
                throw new moodle_exception('noguest');
            }
            $record = \auth_passkey\manager::register($USER,
                clean_param($body->clientDataJSON ?? '', PARAM_RAW_TRIMMED),
                clean_param($body->attestationObject ?? '', PARAM_RAW_TRIMMED),
                clean_param($body->name ?? '', PARAM_TEXT),
                preg_replace('/[^a-z,-]/', '', strtolower(implode(',', (array) ($body->transports ?? []))))
            );
            auth_passkey_json(['success' => true, 'id' => $record->id, 'name' => $record->name]);
            break;

        default:
            throw new moodle_exception('invalidparameter', 'debug');
    }
} catch (\Throwable $e) {
    $message = $e instanceof moodle_exception ? $e->getMessage() : get_string('errorgeneric', 'auth_passkey');
    auth_passkey_json(['success' => false, 'error' => $message], 400);
}
