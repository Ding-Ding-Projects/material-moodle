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
 * Material sign-in layout: Boost's split layout with the configurable Material panel.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes(['material-login']);
$leftinstructions = !empty($CFG->auth_instructions)
    ? format_text($CFG->auth_instructions, FORMAT_MOODLE, ['context' => context_system::instance()])
    : null;

$config = get_config('theme_material');
$heading = isset($config->loginheading) && $config->loginheading !== ''
    ? $config->loginheading : get_string('loginheadingdefault', 'theme_material');
$text = isset($config->logintext) ? $config->logintext : get_string('logintextdefault', 'theme_material');
$chipsource = isset($config->loginchips) ? $config->loginchips : get_string('loginchipsdefault', 'theme_material');
$chips = array_values(array_filter(array_map('trim', explode(',', (string) $chipsource)), fn($v) => $v !== ''));

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'leftinstructions' => $leftinstructions,
    'materiallogin' => [
        'heading' => format_string($heading),
        'text' => format_string($text),
        'chips' => array_map('format_string', $chips),
        'haschips' => !empty($chips),
    ],
];

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);
