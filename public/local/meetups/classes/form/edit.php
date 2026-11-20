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

namespace local_meetups\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_meetups\meetup;

/**
 * Create or edit a meetup.
 *
 * Custom data: meetup (record or null), courses (id => name), groups (id => name), audiences (key => label),
 * optioncount (number of option rows to show).
 *
 * @package    local_meetups
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = 'local_meetups';
        $meetup = $this->_customdata['meetup'] ?? null;
        $isnew = empty($meetup);
        $scheduled = !$isnew && $meetup->status === meetup::STATUS_SCHEDULED;

        $mform->addElement('hidden', 'id', $isnew ? 0 : $meetup->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'general', get_string('details', $c));
        $mform->addElement('text', 'title', get_string('title', $c), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('editor', 'description_editor', get_string('description', $c), ['rows' => 6],
            ['maxfiles' => 0, 'noclean' => false]);
        $mform->setType('description_editor', PARAM_RAW);

        $types = [];
        foreach (meetup::TYPES as $type) {
            $types[$type] = get_string('type_' . $type, $c);
        }
        $mform->addElement('select', 'type', get_string('type', $c), $types);
        $mform->addElement('text', 'location', get_string('location', $c), ['maxlength' => 255, 'size' => 50,
            'placeholder' => get_string('location_placeholder', $c)]);
        $mform->setType('location', PARAM_TEXT);
        $mform->hideIf('location', 'type', 'eq', 'online');
        $mform->addElement('text', 'onlineurl', get_string('onlineurl', $c), ['size' => 50, 'placeholder' => 'https://']);
        $mform->setType('onlineurl', PARAM_URL);
        $mform->hideIf('onlineurl', 'type', 'eq', 'inperson');

        $mform->addElement('header', 'whohdr', get_string('whoisitfor', $c));
        $mform->setExpanded('whohdr', true);
        $mform->addElement('select', 'audience', get_string('audience', $c), $this->_customdata['audiences']);
        $mform->addHelpButton('audience', 'audience', $c);
        $courses = $this->_customdata['courses'] ?? [];
        $mform->addElement('select', 'courseid', get_string('course'), [0 => get_string('choosedots')] + $courses);
        $mform->hideIf('courseid', 'audience', 'neq', 'course');
        $groups = $this->_customdata['groups'] ?? [];
        $mform->addElement('select', 'groupid', get_string('group'), [0 => get_string('choosedots')] + $groups);
        $mform->hideIf('groupid', 'audience', 'neq', 'group');
        $mform->addElement('autocomplete', 'invitees', get_string('invitepeople', $c), [], [
            'multiple' => true,
            'ajax' => 'local_meetups/user_selector',
            'noselectionstring' => get_string('noinvitees', $c),
            'valuehtmlcallback' => function($value) {
                $user = \core_user::get_user((int) $value);
                return $user && !$user->deleted ? s(fullname($user)) : false;
            },
        ]);
        $mform->hideIf('invitees', 'audience', 'neq', 'invite');

        $mform->addElement('header', 'whenhdr', get_string('when', $c));
        $mform->setExpanded('whenhdr', true);
        if ($scheduled) {
            $mform->addElement('date_time_selector', 'timestart', get_string('starts', $c));
            $mform->addElement('duration', 'duration', get_string('duration', $c), ['units' => [MINSECS, HOURSECS, DAYSECS]]);
        } else {
            $mform->addElement('static', 'pollintro', '', get_string('pollintro', $c));
            $repeat = [
                $mform->createElement('date_time_selector', 'optionstart', get_string('optionn', $c),
                    ['optional' => true, 'step' => 15]),
                $mform->createElement('duration', 'optionduration', get_string('duration', $c),
                    ['units' => [MINSECS, HOURSECS, DAYSECS]]),
            ];
            $this->repeat_elements($repeat, $this->_customdata['optioncount'] ?? 3, [
                'optionduration' => ['default' => HOURSECS],
                'optionstart' => ['default' => 0],
            ], 'optionrepeats', 'optionadd', 2, get_string('addoptions', $c), true);
            if ($isnew) {
                $mform->addElement('advcheckbox', 'finalisenow', get_string('finalisenow', $c), get_string('finalisenow_desc', $c));
            }
        }

        $mform->addElement('header', 'rsvphdr', get_string('replies', $c));
        $mform->addElement('text', 'capacity', get_string('capacity', $c), ['size' => 6, 'inputmode' => 'numeric']);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'capacity', $c);
        $mform->addElement('date_time_selector', 'rsvpdeadline', get_string('rsvpdeadline', $c), ['optional' => true]);

        $this->add_action_buttons(true, get_string($isnew ? 'createmeetup' : 'savechanges', $isnew ? $c : 'moodle'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $c = 'local_meetups';
        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = get_string('required');
        }
        $type = $data['type'] ?? 'inperson';
        $location = trim($data['location'] ?? '');
        $url = trim($data['onlineurl'] ?? '');
        if ($type === 'inperson' && $location === '') {
            $errors['location'] = get_string('errorlocation', $c);
        }
        if ($type === 'online' && $url === '') {
            $errors['onlineurl'] = get_string('errorurl', $c);
        }
        if ($type === 'hybrid' && $location === '' && $url === '') {
            $errors['location'] = get_string('errorlocationorurl', $c);
        }
        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            $errors['onlineurl'] = get_string('errorurl', $c);
        }
        $audience = $data['audience'] ?? 'invite';
        if (!isset($this->_customdata['audiences'][$audience])) {
            $errors['audience'] = get_string('invaliddata', 'error');
        }
        if ($audience === 'course' && empty($this->_customdata['courses'][(int) ($data['courseid'] ?? 0)])) {
            $errors['courseid'] = get_string('errorcourserequired', $c);
        }
        if ($audience === 'group' && empty($this->_customdata['groups'][(int) ($data['groupid'] ?? 0)])) {
            $errors['groupid'] = get_string('errorgrouprequired', $c);
        }
        if ((int) ($data['capacity'] ?? 0) < 0) {
            $errors['capacity'] = get_string('errorcapacity', $c);
        }
        if (isset($data['optionstart'])) {
            $count = 0;
            foreach ($data['optionstart'] as $start) {
                if (!empty($start)) {
                    $count++;
                }
            }
            if (!$count) {
                $errors['optionstart[0]'] = get_string('erroroptions', $c);
            }
        }
        if (isset($data['timestart']) && empty($data['duration'])) {
            $errors['duration'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Poll options entered in the form.
     *
     * @param \stdClass $data Submitted data.
     * @return array|null Null when the form has no option rows.
     */
    public static function options_from_data(\stdClass $data): ?array {
        if (!isset($data->optionstart)) {
            return null;
        }
        $options = [];
        foreach ($data->optionstart as $i => $start) {
            if (empty($start)) {
                continue;
            }
            $duration = (int) ($data->optionduration[$i] ?? HOURSECS);
            $options[] = ['timestart' => (int) $start, 'timeend' => (int) $start + max(0, $duration)];
        }
        return $options;
    }
}
