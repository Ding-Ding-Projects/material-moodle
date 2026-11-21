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


namespace local_materialtools\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider. The plugin keeps one user preference; accounts it creates are ordinary Moodle accounts.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\user_preference_provider {
    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(\local_materialtools\vocabulary::PREF, 'privacy:metadata:preference:vocab');
        $collection->add_user_preference('local_materialtools_personalvocab', 'privacy:metadata:preference:personalvocab');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:personalvocabfiles');
        return $collection;
    }

    /**
     * Export preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid) {
        $personal = get_user_preferences('local_materialtools_personalvocab', null, $userid);
        if ($personal !== null) {
            \core_privacy\local\request\writer::export_user_preference('local_materialtools', 'local_materialtools_personalvocab',
                $personal, get_string('privacy:metadata:preference:personalvocab', 'local_materialtools'));
        }
        $value = get_user_preferences(\local_materialtools\vocabulary::PREF, null, $userid);
        if ($value !== null) {
            \core_privacy\local\request\writer::export_user_preference('local_materialtools',
                \local_materialtools\vocabulary::PREF, $value, get_string('privacy:metadata:preference:vocab',
                'local_materialtools'));
        }
    }
}
