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
 * The vocabulary pack for the signed-in person, if they were chosen to get it.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

require_login(null, false, null, false, true);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
if (!\local_materialtools\vocabulary::eligible()) {
    header('Cache-Control: no-store');
    echo json_encode(['entries' => new stdClass()]);
    die();
}
// The URL carries the pack hash, so a private cache can keep it until the pack changes.
header('Cache-Control: private, max-age=86400');
echo json_encode(['entries' => (object) \local_materialtools\vocabulary::entries()], JSON_UNESCAPED_UNICODE);
