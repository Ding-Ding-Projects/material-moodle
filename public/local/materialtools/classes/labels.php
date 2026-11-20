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
 * Display names that do not change permissions: a type name per activity ("Dropbox" for one assignment) and a
 * personal title per teacher or student ("Coach", "Intern"). Roles and module types stay exactly as they are.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class labels {
    /** @var string Activity label table. */
    public const CM = 'local_materialtools_cmlabel';
    /** @var string Personal title table. */
    public const TITLE = 'local_materialtools_title';

    /**
     * Set or clear the type name of one activity.
     *
     * @param int $cmid
     * @param int $courseid
     * @param string $label
     */
    public static function set_cm_label(int $cmid, int $courseid, string $label): void {
        global $DB;
        $label = \core_text::substr(trim($label), 0, 100);
        $existing = $DB->get_record(self::CM, ['cmid' => $cmid]);
        if ($label === '') {
            if ($existing) {
                $DB->delete_records(self::CM, ['id' => $existing->id]);
            }
            return;
        }
        if ($existing) {
            $existing->label = $label;
            $existing->timemodified = time();
            $DB->update_record(self::CM, $existing);
        } else {
            $DB->insert_record(self::CM, (object) ['cmid' => $cmid, 'courseid' => $courseid, 'label' => $label,
                'timemodified' => time()]);
        }
    }

    /**
     * Type name of one activity, or ''.
     *
     * @param int $cmid
     * @return string
     */
    public static function cm_label(int $cmid): string {
        global $DB;
        return (string) $DB->get_field(self::CM, 'label', ['cmid' => $cmid]);
    }

    /**
     * Type names in a course.
     *
     * @param int $courseid
     * @return array cmid => label
     */
    public static function course_labels(int $courseid): array {
        global $DB;
        return $DB->get_records_menu(self::CM, ['courseid' => $courseid], '', 'cmid, label');
    }

    /**
     * Set or clear a personal title. Course 0 means the whole site.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $title
     */
    public static function set_title(int $userid, int $courseid, string $title): void {
        global $DB;
        $title = \core_text::substr(trim($title), 0, 100);
        $existing = $DB->get_record(self::TITLE, ['userid' => $userid, 'courseid' => $courseid]);
        if ($title === '') {
            if ($existing) {
                $DB->delete_records(self::TITLE, ['id' => $existing->id]);
            }
            return;
        }
        if ($existing) {
            $existing->title = $title;
            $existing->timemodified = time();
            $DB->update_record(self::TITLE, $existing);
        } else {
            $DB->insert_record(self::TITLE, (object) ['userid' => $userid, 'courseid' => $courseid, 'title' => $title,
                'timemodified' => time()]);
        }
    }

    /**
     * Titles that apply in a course: course titles win over site titles.
     *
     * @param int $courseid
     * @return array userid => title
     */
    public static function titles_for_course(int $courseid): array {
        global $DB;
        $out = $DB->get_records_menu(self::TITLE, ['courseid' => 0], '', 'userid, title');
        if ($courseid && $courseid != SITEID) {
            foreach ($DB->get_records_menu(self::TITLE, ['courseid' => $courseid], '', 'userid, title') as $u => $t) {
                $out[$u] = $t;
            }
        }
        return $out;
    }

    /**
     * Every title, for the management page.
     *
     * @return array
     */
    public static function all_titles(): array {
        global $DB;
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql("SELECT t.id, t.userid, t.courseid, t.title, c.shortname AS coursename, u.username, $fields
                                       FROM {" . self::TITLE . "} t
                                       JOIN {user} u ON u.id = t.userid
                                  LEFT JOIN {course} c ON c.id = t.courseid
                                   ORDER BY u.lastname, u.firstname, t.courseid");
    }

    /**
     * Quick post buttons from the setting, "Label|modname" per line.
     *
     * @return array [['label' => 'Dropbox', 'modname' => 'assign'], ...]
     */
    public static function quick_posts(): array {
        $raw = (string) get_config('local_materialtools', 'quickposts');
        if (trim($raw) === '') {
            $raw = "Dropbox|assign";
        }
        $out = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) === 2 && $parts[0] !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $parts[1])) {
                $out[] = ['label' => \core_text::substr($parts[0], 0, 100), 'modname' => $parts[1]];
            }
        }
        return $out;
    }
}
