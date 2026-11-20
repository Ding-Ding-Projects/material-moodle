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
 * Choose people who get the vocabulary pack.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vocabusers extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'action', 'addusers');
        $m->setType('action', PARAM_ALPHA);
        $m->addElement('autocomplete', 'userids', get_string('addstudents', 'local_materialtools'), [], [
            'multiple' => true,
            'ajax' => 'core_user/form_user_selector',
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user((int) $userid);
                return $user ? fullname($user) . ' (' . s($user->username) . ')' : false;
            },
        ]);
        $this->add_action_buttons(false, get_string('addstudents', 'local_materialtools'));
    }
}
