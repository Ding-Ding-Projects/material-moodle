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

namespace local_dueextensions\event;

/**
 * A student used a due date extension.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extension_used extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_dueext_requests';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventextensionused', 'local_dueextensions');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $result = empty($this->other['granted']) ? 'was denied an extension' : "gained {$this->other['days']} days";
        return "The user with id '{$this->userid}' used a due date extension on the assignment with course module id " .
            "'{$this->contextinstanceid}' and {$result}.";
    }

    /**
     * Object id mapping for backup.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_dueext_requests', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
