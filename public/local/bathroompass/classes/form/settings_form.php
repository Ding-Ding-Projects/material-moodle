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

namespace local_bathroompass\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_bathroompass\settings;
use local_bathroompass\webhook;

/**
 * A course's bathroom pass settings.
 *
 * Custom data: canmanage (the general settings) and canwebhooks (the webhook addresses and their test buttons).
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'local_bathroompass';
        $canmanage = !empty($this->_customdata['canmanage']);
        $canwebhooks = !empty($this->_customdata['canwebhooks']);
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);

        if ($canmanage) {
            $m->addElement('advcheckbox', 'enabled', get_string('enabled', $c));

            $group = [
                $m->createElement('text', 'passes', get_string('passes', $c), ['size' => 4, 'inputmode' => 'numeric']),
                $m->createElement('advcheckbox', 'unlimited', '', get_string('unlimited', $c)),
            ];
            $m->addGroup($group, 'passesgroup', get_string('passes', $c), ' ', false);
            $m->setType('passes', PARAM_INT);
            $m->addHelpButton('passesgroup', 'passes', $c);
            $m->disabledIf('passes', 'unlimited', 'checked');

            $periods = [];
            foreach (settings::PERIODS as $period) {
                $periods[$period] = get_string('periodname_' . $period, $c);
            }
            $m->addElement('select', 'period', get_string('period', $c), $periods);
            $m->addHelpButton('period', 'period', $c);

            $m->addElement('text', 'goldenstep', get_string('goldenstep', $c), ['size' => 3, 'inputmode' => 'numeric']);
            $m->setType('goldenstep', PARAM_INT);
            $m->addHelpButton('goldenstep', 'goldenstep', $c);
        }

        if ($canwebhooks) {
            $m->addElement('header', 'webhooksheader', get_string('webhooks', $c));
            $m->addElement('static', 'webhooksintro', '', get_string('webhooksintro', $c));
            foreach (['out', 'in'] as $direction) {
                $group = [
                    $m->createElement('text', 'webhook' . $direction, '', ['size' => 60, 'placeholder' => 'https://']),
                    $m->createElement('submit', 'test' . $direction, get_string('sendtest', $c)),
                ];
                $m->addGroup($group, 'webhook' . $direction . 'group', get_string('webhook' . $direction, $c), ' ', false);
                $m->setType('webhook' . $direction, PARAM_RAW_TRIMMED);
                $m->addHelpButton('webhook' . $direction . 'group', 'webhook' . $direction, $c);
                $m->registerNoSubmitButton('test' . $direction);
            }
            $m->addElement('advcheckbox', 'includename', get_string('includename', $c));
            $m->addHelpButton('includename', 'includename', $c);
        }

        $this->add_action_buttons(false);
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
        $c = 'local_bathroompass';
        if (!empty($this->_customdata['canmanage'])) {
            if (empty($data['unlimited']) && ((int) $data['passes'] < 0 || (int) $data['passes'] > settings::MAX_PASSES)) {
                $errors['passesgroup'] = get_string('passes_help', $c);
            }
            if ((int) $data['goldenstep'] < 0 || (int) $data['goldenstep'] > settings::MAX_STEP) {
                $errors['goldenstep'] = get_string('goldenstepinvalid', $c);
            }
        }
        if (!empty($this->_customdata['canwebhooks'])) {
            foreach (['out', 'in'] as $direction) {
                $url = trim((string) ($data['webhook' . $direction] ?? ''));
                if ($url !== '' && !webhook::valid_url($url)) {
                    $errors['webhook' . $direction . 'group'] = get_string('webhookinvalid', $c);
                }
            }
        }
        return $errors;
    }
}
