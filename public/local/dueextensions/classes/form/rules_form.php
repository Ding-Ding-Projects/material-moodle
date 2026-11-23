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

namespace local_dueextensions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_dueextensions\rules;

/**
 * A course's due date extension rules.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules_form extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'local_dueextensions';
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);

        $m->addElement('advcheckbox', 'enabled', get_string('enabled', $c));

        $group = [
            $m->createElement('text', 'allowance', get_string('allowance', $c), ['size' => 4, 'inputmode' => 'numeric']),
            $m->createElement('advcheckbox', 'unlimited', '', get_string('unlimited', $c)),
        ];
        $m->addGroup($group, 'allowancegroup', get_string('allowance', $c), ' ', false);
        $m->setType('allowance', PARAM_INT);
        $m->addHelpButton('allowancegroup', 'allowance', $c);
        $m->disabledIf('allowance', 'unlimited', 'checked');

        $m->addElement('select', 'period', get_string('period', $c), [
            'week' => get_string('periodweek', $c), 'month' => get_string('periodmonth', $c),
            'course' => get_string('periodcourse', $c),
        ]);

        $modes = [];
        foreach (rules::MODES as $mode) {
            $modes[] = $m->createElement('radio', 'mode', '', get_string('mode' . $mode, $c), $mode);
        }
        $m->addGroup($modes, 'modegroup', get_string('mode', $c), '<br>', false);

        $m->addElement('text', 'instantdays', get_string('instantdays', $c), ['size' => 3, 'inputmode' => 'numeric']);
        $m->setType('instantdays', PARAM_INT);
        $m->hideIf('instantdays', 'mode', 'neq', 'instant');

        $dice = [];
        foreach (rules::DICE as $sides) {
            $dice[$sides] = get_string('dicesidesn', $c, $sides);
        }
        $m->addElement('select', 'dicesides', get_string('dicesides', $c), $dice);
        $m->hideIf('dicesides', 'mode', 'neq', 'dice');

        $m->addElement('text', 'coindays', get_string('coindays', $c), ['size' => 3, 'inputmode' => 'numeric']);
        $m->setType('coindays', PARAM_INT);
        $m->hideIf('coindays', 'mode', 'neq', 'coin');

        $m->addElement('textarea', 'wheeltext', get_string('wheel', $c), ['rows' => 6, 'cols' => 20]);
        $m->setType('wheeltext', PARAM_TEXT);
        $m->addHelpButton('wheeltext', 'wheel', $c);
        $m->hideIf('wheeltext', 'mode', 'neq', 'wheel');

        $m->addElement('advcheckbox', 'deniedcounts', get_string('deniedcounts', $c));
        $m->addHelpButton('deniedcounts', 'deniedcounts', $c);
        $m->hideIf('deniedcounts', 'mode', 'in', ['instant', 'dice']);

        $this->add_action_buttons(false);
    }

    /**
     * Turn the wheel text into segments.
     *
     * @param string $text
     * @return array[]
     */
    public static function parse_wheel(string $text): array {
        $segments = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (!preg_match('/^\s*(\d{1,2})\s*(?:[,;]\s*(\d{1,3}(?:\.\d{1,2})?))?\s*%?\s*$/', $line, $m)) {
                return [];
            }
            $segments[] = ['days' => (int) $m[1], 'penalty' => isset($m[2]) ? (float) $m[2] : 0];
        }
        return rules::clean_wheel($segments);
    }

    /**
     * Wheel segments as editable text.
     *
     * @param array[] $wheel
     * @return string
     */
    public static function wheel_text(array $wheel): string {
        return implode("\n", array_map(fn($s) => $s['days'] . ', ' . $s['penalty'], $wheel));
    }

    /**
     * Validate.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $c = 'local_dueextensions';
        if (empty($data['unlimited']) && ((int) $data['allowance'] < 0 || (int) $data['allowance'] > 100)) {
            $errors['allowancegroup'] = get_string('allowance_help', $c);
        }
        if (($data['mode'] ?? '') === 'wheel' && !self::parse_wheel((string) $data['wheeltext'])) {
            $errors['wheeltext'] = get_string('wheelinvalid', $c);
        }
        foreach (['instantdays', 'coindays'] as $field) {
            if ((int) $data[$field] < 1 || (int) $data[$field] > rules::MAX_DAYS) {
                $errors[$field] = get_string('error', 'moodle');
            }
        }
        return $errors;
    }
}
