<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
namespace local_materialtools\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Display preferences for the signed-in user. */
class display extends \moodleform {
    public function definition() {
        $form = $this->_form;
        foreach (\local_materialtools\display_preferences::CHOICES as $name => $choices) {
            $options = [];
            foreach ($choices as $value) {
                $options[$value] = $name === 'textsize' && $value !== 'site' ? $value . '%'
                    : get_string('displayvalue' . $value, 'local_materialtools');
            }
            $form->addElement('select', $name, get_string('display' . $name, 'local_materialtools'), $options);
        }
        $this->add_action_buttons();
    }
}
