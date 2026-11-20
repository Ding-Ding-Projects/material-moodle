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
 * Form for adding one student.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class newstudent extends \moodleform {
    /**
     * Fields.
     */
    protected function definition() {
        global $CFG;
        $m = $this->_form;
        $c = 'local_materialtools';

        $m->addElement('text', 'firstname', get_string('firstname'), ['autocomplete' => 'off']);
        $m->setType('firstname', PARAM_NOTAGS);
        $m->addRule('firstname', null, 'required', null, 'client');
        $m->addElement('text', 'lastname', get_string('lastname'), ['autocomplete' => 'off']);
        $m->setType('lastname', PARAM_NOTAGS);
        $m->addRule('lastname', null, 'required', null, 'client');
        $m->addElement('text', 'username', get_string('username'), ['autocomplete' => 'off',
            'placeholder' => get_string('usernameauto', $c)]);
        $m->setType('username', PARAM_RAW_TRIMMED);
        $m->addHelpButton('username', 'usernameauto', $c);
        $m->addElement('text', 'email', get_string('emailoptional', $c), ['autocomplete' => 'off']);
        $m->setType('email', PARAM_RAW_TRIMMED);

        $m->addElement('text', 'title', get_string('studenttitle', $c), ['maxlength' => 100]);
        $m->setType('title', PARAM_TEXT);
        $m->addHelpButton('title', 'studenttitle', $c);

        $m->addElement('select', 'passwordmode', get_string('passwordmode', $c), [
            'generate' => get_string('passwordgenerate', $c),
            'custom' => get_string('passwordcustom', $c),
        ]);
        $m->addElement('passwordunmask', 'custompassword', get_string('password'));
        $m->setType('custompassword', PARAM_RAW);
        $m->hideIf('custompassword', 'passwordmode', 'eq', 'generate');

        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohorts = ['' => get_string('none')];
        foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
            $cohorts[$cohort->id] = format_string($cohort->name);
        }
        $m->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $cohorts);
        $m->addElement('course', 'courseid', get_string('enrolincourse', $c), ['multiple' => false, 'includefrontpage' => false]);

        if (\local_materialtools\vocabulary::present()) {
            $m->addElement('advcheckbox', 'vocabulary', get_string('vocabforstudent', $c),
                get_string('vocabforstudent_desc', $c));
        }
        $m->addElement('static', 'forcechange', get_string('firstsignin', $c), get_string('firstsignin_desc', $c));

        $this->add_action_buttons(false, get_string('createstudent', $c));
    }

    /**
     * Checks.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);
        if (!empty($data['username'])) {
            $clean = \core_text::strtolower($data['username']);
            if ($clean !== clean_param($clean, PARAM_USERNAME)) {
                $errors['username'] = get_string('invalidusername');
            } else if ($DB->record_exists('user', ['username' => $clean, 'mnethostid' => $CFG->mnet_localhost_id])) {
                $errors['username'] = get_string('usernameexists');
            }
        }
        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }
        if (($data['passwordmode'] ?? '') === 'custom') {
            $error = '';
            if (empty($data['custompassword']) || !check_password_policy($data['custompassword'], $error)) {
                $errors['custompassword'] = $error ?: get_string('required');
            }
        }
        return $errors;
    }
}
