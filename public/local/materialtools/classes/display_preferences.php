<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
namespace local_materialtools;

/** Per-person display choices used by the Material theme. */
final class display_preferences {
    public const PREF = 'local_materialtools_display';
    public const CHOICES = [
        'motion' => ['site', 'calm', 'off'],
        'textsize' => ['site', '112', '125', '150'],
        'spacing' => ['site', 'relaxed'],
    ];

    public static function normalise(array $input): array {
        $selected = [];
        foreach (self::CHOICES as $name => $choices) {
            $value = (string)($input[$name] ?? 'site');
            $selected[$name] = in_array($value, $choices, true) ? $value : 'site';
        }
        return $selected;
    }

    public static function current(): array {
        $saved = isloggedin() && !isguestuser() ? get_user_preferences(self::PREF, '') : '';
        $values = json_decode((string)$saved, true);
        return self::normalise(is_array($values) ? $values : []);
    }
}
