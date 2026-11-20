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
 * Apply the Material defaults to a new site: theme, colour modes, seed colour and passkey sign-in.
 *
 * Run with --first-run once after installation. Every value stays editable in Site administration.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['first-run' => false, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Apply Material defaults.\n  --first-run  Set theme, colour modes, seed colour and passkeys.\n";
    exit(0);
}
if (!$options['first-run']) {
    exit(0);
}

set_config('theme', 'material');
set_config('enablecolourmodes', 1, 'theme_boost');
$seed = getenv('MATERIAL_SEED_COLOR');
if ($seed && preg_match('/^#[0-9a-fA-F]{6}$/', $seed)) {
    set_config('seedcolor', strtoupper($seed), 'theme_material');
}
if (getenv('MATERIAL_ENABLE_PASSKEYS') !== '0') {
    $auths = array_filter(explode(',', (string) get_config('core', 'auth')));
    if (!in_array('passkey', $auths)) {
        $auths[] = 'passkey';
        set_config('auth', implode(',', $auths));
    }
}
set_config('vocabaudience', 'selected', 'local_materialtools');
set_config('onboardingpending', 1, 'local_materialtools');
theme_reset_all_caches();
purge_all_caches();
cli_writeln('Material defaults applied.');
