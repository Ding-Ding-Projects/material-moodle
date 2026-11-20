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
 * Give one person a title shown instead of their role name.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class title extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'local_materialtools';
        $m->addElement('autocomplete', 'userid', get_string('person', $c), [], [
            'ajax' => 'core_user/form_user_selector',
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user((int) $userid);
                return $user ? fullname($user) . ' (' . s($user->username) . ')' : false;
            },
        ]);
        $m->addRule('userid', null, 'required', null, 'client');
        $m->addElement('course', 'courseid', get_string('titlecourse', $c), ['multiple' => false,
            'includefrontpage' => false, 'noselectionstring' => get_string('wholesite', $c)]);
        $m->addElement('text', 'title', get_string('titletext', $c), ['maxlength' => 100,
            'placeholder' => get_string('titletext_placeholder', $c)]);
        $m->setType('title', PARAM_TEXT);
        $m->addRule('title', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('savetitle', $c));
    }
}
