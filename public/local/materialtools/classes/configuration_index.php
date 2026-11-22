<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
namespace local_materialtools;

/** Connect documented core configuration names to this installation's native settings pages. */
final class configuration_index {
    /** Inspect only the distributed example. Never read the site's own config.php. */
    public static function names(string $example): array {
        preg_match_all('/\$CFG->([a-zA-Z][a-zA-Z0-9_]*)/', $example, $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names, SORT_STRING);
        return $names;
    }

    /** Record destinations and ownership only. Do not extract any setting values. */
    public static function destinations(\admin_category $category): array {
        $found = [];
        foreach ($category->get_children() as $child) {
            if ($child instanceof \admin_category) {
                $found += self::destinations($child);
            } else if ($child instanceof \admin_settingpage && $child->check_access() && !$child->is_hidden()) {
                foreach ($child->settings as $setting) {
                    if (!empty($setting->plugin) || $setting->nosave || isset($found[$setting->name])) {
                        continue;
                    }
                    $url = $child->get_settings_page_url();
                    $url->set_anchor($setting->get_id());
                    $found[$setting->name] = ['url' => $url, 'title' => (string)$setting->visiblename,
                        'locked' => $setting->is_readonly()];
                }
            }
        }
        return $found;
    }
}
