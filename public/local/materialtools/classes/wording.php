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


namespace local_materialtools;

/**
 * Site wording: rename the teacher roles, students and the assignment activity everywhere.
 *
 * Role names are stored on the roles themselves. Activity names are written as local language overrides
 * (the same place the Language customisation tool uses), so the chosen name stays on every assignment
 * that is posted, in menus, on course pages, in notifications and in the gradebook.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wording {
    /** @var array Role short names that can be renamed here. */
    public const ROLES = ['editingteacher', 'teacher', 'student'];

    /** @var array Language overrides for the assignment activity: string id => form field. */
    public const ASSIGN_STRINGS = [
        'modulename' => 'assignsingular',
        'pluginname' => 'assignsingular',
        'modulenameplural' => 'assignplural',
    ];

    /**
     * Current role names (empty when the default is used).
     *
     * @return array shortname => name
     */
    public static function role_names(): array {
        global $DB;
        $out = [];
        foreach (self::ROLES as $short) {
            $out[$short] = (string) $DB->get_field('role', 'name', ['shortname' => $short]);
        }
        return $out;
    }

    /**
     * Rename a role. Empty restores the standard name.
     *
     * @param string $shortname
     * @param string $name
     */
    public static function set_role_name(string $shortname, string $name): void {
        global $DB;
        if (!in_array($shortname, self::ROLES)) {
            return;
        }
        $DB->set_field('role', 'name', \core_text::substr(trim($name), 0, 255), ['shortname' => $shortname]);
        accesslib_clear_all_caches(true);
    }

    /**
     * Path of a local override file.
     *
     * @param string $lang
     * @param string $component
     * @return string
     */
    private static function override_file(string $lang, string $component): string {
        global $CFG;
        $root = $CFG->langlocalroot ?? ($CFG->dataroot . '/lang');
        return $root . '/' . $lang . '_local/' . $component . '.php';
    }

    /**
     * Read the overrides in a local file.
     *
     * @param string $file
     * @return array
     */
    private static function read(string $file): array {
        if (!is_readable($file)) {
            return [];
        }
        $string = [];
        include($file);
        return is_array($string) ? $string : [];
    }

    /**
     * Current assignment names (empty when the standard name is used).
     *
     * @param string $lang
     * @return array ['assignsingular' => ..., 'assignplural' => ...]
     */
    public static function assign_names(string $lang = 'en'): array {
        $strings = self::read(self::override_file($lang, 'assign'));
        return [
            'assignsingular' => (string) ($strings['modulename'] ?? ''),
            'assignplural' => (string) ($strings['modulenameplural'] ?? ''),
        ];
    }

    /**
     * Save the assignment names. Empty values remove the override.
     *
     * @param string $singular
     * @param string $plural
     * @param string $lang
     */
    public static function set_assign_names(string $singular, string $plural, string $lang = 'en'): void {
        $file = self::override_file($lang, 'assign');
        $strings = self::read($file);
        $values = ['assignsingular' => trim($singular), 'assignplural' => trim($plural)];
        foreach (self::ASSIGN_STRINGS as $id => $field) {
            if ($values[$field] === '') {
                unset($strings[$id]);
            } else {
                $strings[$id] = $values[$field];
            }
        }
        check_dir_exists(dirname($file));
        $php = "<?php\n// Local overrides written by local_materialtools (Site administration > Users > Material tools > " .
            "Site wording).\ndefined('MOODLE_INTERNAL') || die();\n\n";
        foreach ($strings as $id => $value) {
            $php .= '$string[' . var_export((string) $id, true) . '] = ' . var_export((string) $value, true) . ";\n";
        }
        if ($strings) {
            file_put_contents($file, $php, LOCK_EX);
        } else if (file_exists($file)) {
            unlink($file);
        }
        get_string_manager()->reset_caches();
        rebuild_course_cache(0, true);
    }
}
