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
 * First-run setup for administrators: colour, sign-in, passkeys, custom vocabulary and wording, with Back/Next,
 * skipping of optional steps, and resume from the last step.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_materialtools_onboarding');

$c = 'local_materialtools';
$steps = ['colour', 'signin', 'passkeys', 'vocabulary', 'wording', 'done'];
$saved = (string) get_config($c, 'onboardingstep');
$step = optional_param('step', in_array($saved, $steps) ? $saved : 'colour', PARAM_ALPHA);
if (!in_array($step, $steps)) {
    $step = 'colour';
}
$index = array_search($step, $steps);
$url = new moodle_url('/local/materialtools/onboarding.php');

if (data_submitted() && confirm_sesskey()) {
    $go = optional_param('go', 'next', PARAM_ALPHA);
    if ($go === 'next') {
        switch ($step) {
            case 'colour':
                $seed = \theme_material\palette::normalise(optional_param('seedcolor', '', PARAM_RAW_TRIMMED));
                set_config('seedcolor', $seed, 'theme_material');
                set_config('motion', optional_param('motion', 'expressive', PARAM_ALPHA), 'theme_material');
                theme_reset_all_caches();
                break;
            case 'signin':
                set_config('loginheading', optional_param('loginheading', '', PARAM_TEXT), 'theme_material');
                set_config('logintext', optional_param('logintext', '', PARAM_TEXT), 'theme_material');
                break;
            case 'passkeys':
                $auths = array_filter(explode(',', (string) get_config('core', 'auth')));
                $on = optional_param('passkeys', 0, PARAM_BOOL);
                $auths = array_values(array_diff($auths, ['passkey']));
                if ($on) {
                    $auths[] = 'passkey';
                }
                set_config('auth', implode(',', $auths));
                set_config('offeronfirstlogin', optional_param('offer', 0, PARAM_BOOL), 'auth_passkey');
                \core\session\manager::gc();
                break;
            case 'wording':
                \local_materialtools\wording::set_role_name('editingteacher', optional_param('teacher', '', PARAM_TEXT));
                \local_materialtools\wording::set_role_name('student', optional_param('student', '', PARAM_TEXT));
                \local_materialtools\wording::set_assign_names(optional_param('assignsingular', '', PARAM_TEXT),
                    optional_param('assignplural', '', PARAM_TEXT), current_language());
                break;
        }
    }
    $target = $go === 'back' ? max(0, $index - 1) : min(count($steps) - 1, $index + 1);
    set_config('onboardingstep', $steps[$target], $c);
    redirect(new moodle_url($url, ['step' => $steps[$target]]));
}

$config = get_config('theme_material');
$names = \local_materialtools\wording::role_names();
$assign = \local_materialtools\wording::assign_names(current_language());
$progress = [];
foreach ($steps as $i => $name) {
    $progress[] = ['label' => get_string('onboard_' . $name, $c), 'done' => $i <= $index, 'current' => $i === $index,
        'url' => (new moodle_url($url, ['step' => $name]))->out(false)];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_materialtools/onboarding', [
    'actionurl' => $url->out(false),
    'step' => $step,
    'is' . $step => true,
    'sesskey' => sesskey(),
    'progress' => $progress,
    'percent' => (int) round($index / (count($steps) - 1) * 100),
    'first' => $index === 0,
    'optional' => in_array($step, ['signin', 'vocabulary', 'wording']),
    'seedcolor' => $config->seedcolor ?? \theme_material\palette::DEFAULT_SEED,
    'swatches' => array_map(fn($h) => ['hex' => $h], ['#8B5000', '#006A6A', '#4F5B92', '#9C4146', '#3B6939', '#6750A4']),
    'motions' => array_map(fn($m) => ['value' => $m, 'label' => get_string('motion' . $m, 'theme_material'),
        'checked' => ($config->motion ?? 'expressive') === $m], ['expressive', 'calm', 'off']),
    'loginheading' => $config->loginheading ?? get_string('loginheadingdefault', 'theme_material'),
    'logintext' => $config->logintext ?? get_string('logintextdefault', 'theme_material'),
    'passkeyson' => is_enabled_auth('passkey'),
    'offeron' => get_config('auth_passkey', 'offeronfirstlogin') !== '0',
    'vocabularyurl' => (new moodle_url('/local/materialtools/vocabulary.php'))->out(false),
    'vocabpresent' => \local_materialtools\vocabulary::present(),
    'teacher' => $names['editingteacher'], 'student' => $names['student'],
    'assignsingular' => $assign['assignsingular'], 'assignplural' => $assign['assignplural'],
    'newstudenturl' => (new moodle_url('/local/materialtools/newstudent.php'))->out(false),
    'dashboardurl' => (new moodle_url('/my/'))->out(false),
]);
echo $OUTPUT->footer();
