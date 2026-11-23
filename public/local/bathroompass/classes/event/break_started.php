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

namespace local_bathroompass\event;

/**
 * A student left the room for a bathroom break.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class break_started extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_bpass_breaks';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventbreakstarted', 'local_bathroompass');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $over = empty($this->other['overallowance']) ? '' : ' beyond the allowance';
        return "The user with id '{$this->relateduserid}' left the room for a bathroom break{$over} in the course with id " .
            "'{$this->courseid}'.";
    }

    /**
     * Object id mapping for backup.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_bpass_breaks', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
