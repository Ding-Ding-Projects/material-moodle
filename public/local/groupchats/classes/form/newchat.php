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

namespace local_groupchats\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Start a group chat.
 *
 * Custom data: id is the conversation id for the rename and add subclasses.
 *
 * @package    local_groupchats
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class newchat extends \moodleform {
    /** @var string What the form does: new, rename or add. Subclasses change it so each form has its own identifier. */
    const MODE = 'new';

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = 'local_groupchats';
        $mode = static::MODE;

        $mform->addElement('hidden', 'id', (int) ($this->_customdata['id'] ?? 0));
        $mform->setType('id', PARAM_INT);

        if ($mode !== 'add') {
            $mform->addElement('text', 'name', get_string('chatname', $c), ['maxlength' => 255, 'size' => 40,
                'placeholder' => get_string('chatname_placeholder', $c)]);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', null, 'required', null, 'client');
        }
        if ($mode !== 'rename') {
            $mform->addElement('autocomplete', 'members', get_string($mode === 'add' ? 'addmembers' : 'members', $c), [], [
                'multiple' => true,
                'ajax' => 'local_groupchats/user_selector',
                'noselectionstring' => get_string('nomembersselected', $c),
                'valuehtmlcallback' => [self::class, 'user_name_html'],
            ]);
            $mform->addRule('members', null, 'required', null, 'client');
            $mform->addHelpButton('members', 'members', $c);
        }
        $labels = ['new' => 'createchat', 'rename' => 'rename', 'add' => 'addmembers'];
        if ($mode === 'new') {
            $this->add_action_buttons(true, get_string($labels[$mode], $c));
        } else {
            $mform->addElement('submit', 'submitbutton', get_string($labels[$mode], $c));
        }
    }

    /**
     * Show a selected person by name.
     *
     * @param mixed $value User id.
     * @return string|false
     */
    public static function user_name_html($value) {
        $user = \core_user::get_user((int) $value);
        return $user && !$user->deleted ? s(fullname($user)) : false;
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
        if (array_key_exists('name', $data) && trim((string) $data['name']) === '') {
            $errors['name'] = get_string('errorname', 'local_groupchats');
        }
        if (static::MODE !== 'rename' && empty($data['members'])) {
            $errors['members'] = get_string('errornomembers', 'local_groupchats');
        }
        return $errors;
    }
}
