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
 * The first-visit form: the site administrator's username, password, name and email.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class firstadmin extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'local_materialtools';
        $m->addElement('text', 'username', get_string('username'), ['autocomplete' => 'username']);
        $m->setType('username', PARAM_RAW_TRIMMED);
        $m->addRule('username', null, 'required', null, 'client');
        $m->addElement('passwordunmask', 'password', get_string('password'), ['autocomplete' => 'new-password']);
        $m->setType('password', PARAM_RAW);
        $m->addRule('password', null, 'required', null, 'client');
        $m->addElement('password', 'password2', get_string('firstadmin_confirm', $c), ['autocomplete' => 'new-password']);
        $m->setType('password2', PARAM_RAW);
        $m->addRule('password2', null, 'required', null, 'client');
        $m->addElement('text', 'firstname', get_string('firstname'), ['autocomplete' => 'given-name']);
        $m->setType('firstname', PARAM_NOTAGS);
        $m->setDefault('firstname', 'Site');
        $m->addRule('firstname', null, 'required', null, 'client');
        $m->addElement('text', 'lastname', get_string('lastname'), ['autocomplete' => 'family-name']);
        $m->setType('lastname', PARAM_NOTAGS);
        $m->setDefault('lastname', 'Administrator');
        $m->addRule('lastname', null, 'required', null, 'client');
        $m->addElement('text', 'email', get_string('email'), ['autocomplete' => 'email']);
        $m->setType('email', PARAM_RAW_TRIMMED);
        $m->addRule('email', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('firstadmin_submit', $c));
    }

    /**
     * Check the username, password and email.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $username = \core_text::strtolower(trim($data['username']));
        if ($username === '' || $username !== clean_param($username, PARAM_USERNAME)) {
            $errors['username'] = get_string('invalidusername');
        }
        $error = '';
        if ($data['password'] !== $data['password2']) {
            $errors['password2'] = get_string('passwordsdiffer');
        } else if (!check_password_policy($data['password'], $error)) {
            $errors['password'] = $error;
        }
        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }
        return $errors;
    }
}
