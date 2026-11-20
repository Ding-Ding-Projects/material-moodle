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


namespace local_materialtools\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Site wording form.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wording extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'local_materialtools';
        $m->addElement('header', 'roles', get_string('wordingroles', $c));
        foreach (\local_materialtools\wording::ROLES as $short) {
            $m->addElement('text', 'role_' . $short, get_string('wordingrole_' . $short, $c),
                ['placeholder' => get_string('wordingdefault', $c)]);
            $m->setType('role_' . $short, PARAM_TEXT);
        }
        $m->addElement('header', 'activities', get_string('wordingactivities', $c));
        $m->addElement('text', 'assignsingular', get_string('wordingassignsingular', $c),
            ['placeholder' => get_string('modulename', 'assign')]);
        $m->setType('assignsingular', PARAM_TEXT);
        $m->addElement('text', 'assignplural', get_string('wordingassignplural', $c),
            ['placeholder' => get_string('modulenameplural', 'assign')]);
        $m->setType('assignplural', PARAM_TEXT);
        $m->addElement('static', 'wordinghelp', '', get_string('wording_help', $c));
        $this->add_action_buttons(false);
    }
}
