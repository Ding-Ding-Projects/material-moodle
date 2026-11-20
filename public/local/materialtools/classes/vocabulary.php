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
 * Custom vocabulary pack: validation, storage and who sees it.
 *
 * The pack is a JSON object {"schemaVersion": 1, "name": "optional", "entries": {"ordinary words": "replacement"}}.
 * It is validated in full before anything is stored, so a rejected file never applies partially. The words are
 * only sent to signed-in people the administrator chose.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vocabulary {
    /** @var int Largest accepted file in bytes. */
    public const MAX_BYTES = 262144;
    /** @var int Deepest accepted nesting. */
    public const MAX_DEPTH = 8;
    /** @var int Longest key in code points. */
    public const MAX_KEY = 160;
    /** @var int Longest value in code points. */
    public const MAX_VALUE = 1000;
    /** @var string Preference marking a chosen person. */
    public const PREF = 'local_materialtools_vocab';

    /** @var array|null Request cache. */
    private static ?array $entries = null;

    /**
     * Validate a pack. Returns ['entries' => array, 'name' => string, 'count' => int].
     *
     * @param string $json
     * @return array
     * @throws \moodle_exception with a reason string identifier.
     */
    public static function validate(string $json): array {
        if (strlen($json) === 0) {
            throw new \moodle_exception('vocaberrorempty', 'local_materialtools');
        }
        if (strlen($json) > self::MAX_BYTES) {
            throw new \moodle_exception('vocaberrorsize', 'local_materialtools');
        }
        if (!mb_check_encoding($json, 'UTF-8')) {
            throw new \moodle_exception('vocaberrorencoding', 'local_materialtools');
        }
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $parser = new strict_json($json, self::MAX_DEPTH);
        $data = $parser->parse();
        if (!is_array($data) || !$parser->is_object_root()) {
            throw new \moodle_exception('vocaberrorshape', 'local_materialtools');
        }
        if (($data['schemaVersion'] ?? null) !== 1) {
            throw new \moodle_exception('vocaberrorversion', 'local_materialtools');
        }
        if (!isset($data['entries']) || !is_array($data['entries'])) {
            throw new \moodle_exception('vocaberrorshape', 'local_materialtools');
        }
        $entries = [];
        foreach ($data['entries'] as $key => $value) {
            $key = (string) $key;
            if (!is_string($value)) {
                throw new \moodle_exception('vocaberrorvalue', 'local_materialtools');
            }
            $klen = \core_text::strlen($key);
            $vlen = \core_text::strlen($value);
            if ($klen < 1 || $klen > self::MAX_KEY || trim($key) === '') {
                throw new \moodle_exception('vocaberrorkey', 'local_materialtools');
            }
            if ($vlen < 1 || $vlen > self::MAX_VALUE) {
                throw new \moodle_exception('vocaberrorvalue', 'local_materialtools');
            }
            $entries[$key] = $value;
        }
        if (!$entries) {
            throw new \moodle_exception('vocaberrorempty', 'local_materialtools');
        }
        $name = isset($data['name']) && is_string($data['name']) ? \core_text::substr(trim($data['name']), 0, 80) : '';
        return ['entries' => $entries, 'name' => $name, 'count' => count($entries)];
    }

    /**
     * Store a validated pack, replacing any previous one.
     *
     * @param string $json
     * @return array The validation result.
     */
    public static function store(string $json): array {
        $result = self::validate($json);
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_materialtools', 'vocabulary');
        $clean = json_encode(['schemaVersion' => 1, 'entries' => $result['entries']], JSON_UNESCAPED_UNICODE);
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'local_materialtools', 'filearea' => 'vocabulary',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'vocabulary.json',
        ], $clean);
        set_config('vocabhash', sha1($clean), 'local_materialtools');
        set_config('vocabcount', $result['count'], 'local_materialtools');
        set_config('vocabtime', time(), 'local_materialtools');
        self::$entries = null;
        return $result;
    }

    /**
     * Remove the pack. Everyone sees the original wording again.
     */
    public static function clear(): void {
        get_file_storage()->delete_area_files(\context_system::instance()->id, 'local_materialtools', 'vocabulary');
        unset_config('vocabhash', 'local_materialtools');
        unset_config('vocabcount', 'local_materialtools');
        unset_config('vocabtime', 'local_materialtools');
        self::$entries = null;
    }

    /**
     * Is a pack loaded?
     *
     * @return bool
     */
    public static function present(): bool {
        return (bool) get_config('local_materialtools', 'vocabhash');
    }

    /**
     * Entries of the stored pack.
     *
     * @return array
     */
    public static function entries(): array {
        if (self::$entries !== null) {
            return self::$entries;
        }
        $file = get_file_storage()->get_file(\context_system::instance()->id, 'local_materialtools', 'vocabulary', 0, '/',
            'vocabulary.json');
        $data = $file ? json_decode($file->get_content(), true) : null;
        self::$entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        return self::$entries;
    }

    /**
     * Who sees the pack: nobody, everyone or selected.
     *
     * @return string
     */
    public static function audience(): string {
        $a = get_config('local_materialtools', 'vocabaudience');
        return in_array($a, ['nobody', 'everyone', 'selected']) ? $a : 'selected';
    }

    /**
     * Cohort ids whose members see the pack when the audience is "selected".
     *
     * @return int[]
     */
    public static function cohorts(): array {
        $raw = (string) get_config('local_materialtools', 'vocabcohorts');
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /**
     * Should this person get the pack? Only signed-in, non-guest people ever do.
     *
     * @param \stdClass|null $user
     * @return bool
     */
    public static function eligible(?\stdClass $user = null): bool {
        global $USER, $CFG;
        $user = $user ?? $USER;
        if (empty($user->id) || isguestuser($user) || !isloggedin() || !self::present()) {
            return false;
        }
        switch (self::audience()) {
            case 'everyone':
                return true;
            case 'nobody':
                return false;
        }
        if (get_user_preferences(self::PREF, 0, $user->id)) {
            return true;
        }
        require_once($CFG->dirroot . '/cohort/lib.php');
        foreach (self::cohorts() as $cohortid) {
            if (cohort_is_member($cohortid, $user->id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Choose or unchoose one person.
     *
     * @param int $userid
     * @param bool $on
     */
    public static function set_user(int $userid, bool $on): void {
        if ($on) {
            set_user_preference(self::PREF, 1, $userid);
        } else {
            unset_user_preference(self::PREF, $userid);
        }
    }

    /**
     * People chosen one by one.
     *
     * @return array
     */
    public static function chosen_users(): array {
        global $DB;
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return $DB->get_records_sql("SELECT u.id, u.username, u.email, $fields
                                       FROM {user} u
                                       JOIN {user_preferences} p ON p.userid = u.id
                                      WHERE p.name = :name AND p.value = '1' AND u.deleted = 0
                                   ORDER BY u.lastname, u.firstname", ['name' => self::PREF]);
    }
}
