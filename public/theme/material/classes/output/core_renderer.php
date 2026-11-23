<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
namespace theme_material\output;

use theme_boost\colour_mode;

/** Material display controls in Boost's existing toolbar slot. */
class core_renderer extends \theme_boost\output\core_renderer {
    public function colour_mode_menu(): string {
        if (!isloggedin() || isguestuser() || !class_exists(\local_materialtools\display_preferences::class)) {
            return parent::colour_mode_menu();
        }
        $strings = get_string_manager();
        // These panel translations ship with the theme, including on English-only sites.
        $string = [];
        require(__DIR__ . '/../../lang/zh_hk/theme_material.php');
        $label = static function(string $key) use ($strings, $string): array {
            return [
                'en' => $strings->get_string('panel' . $key, 'theme_material', null, 'en'),
                'yue' => $string['panel' . $key],
            ];
        };
        $current = colour_mode::get_current_mode();
        $config = \theme_material\settings_values::js_config();
        $choices = [
            'colour' => ['auto', 'light', 'dark'],
            'motion' => ['expressive', 'calm', 'off'],
            'language' => ['en', 'yue', 'bi'],
        ];
        $active = ['colour' => $current, 'motion' => $config['motion'], 'language' => 'en'];
        $icons = ['auto' => 'fa-circle-half-stroke', 'light' => 'fa-sun', 'dark' => 'fa-moon'];
        $groups = [];
        foreach ($choices as $kind => $values) {
            $buttons = [];
            foreach ($values as $value) {
                $disabled = $kind === 'colour' && !colour_mode::can_choose_mode();
                if ($kind === 'motion') {
                    $disabled = array_search($value, $values) < array_search($config['sitemotion'], $values);
                }
                $buttons[] = ['value' => $value, 'label' => $label($value), 'active' => $active[$kind] === $value,
                    'disabled' => $disabled, 'colour' => $kind === 'colour', 'icon' => $icons[$value] ?? ''];
            }
            $groups[] = ['kind' => $kind, 'iscolour' => $kind === 'colour', 'label' => $label($kind), 'buttons' => $buttons];
        }
        $context = [
            'title' => $label('title'), 'note' => $label('note'), 'prefs' => $label('preferences'),
            'groups' => $groups, 'cookieattributes' => colour_mode::get_cookie_attributes(),
            'preferencesurl' => (new \moodle_url('/user/preferences.php'))->out(false),
            'motionurl' => (new \moodle_url('/local/materialtools/motion.php'))->out(false),
            'failure' => get_string('panelfailure', 'theme_material'),
        ];
        $admin = $this->page->primarynav->find('siteadminnode', \navigation_node::TYPE_SITE_ADMIN);
        if ($admin && $admin->action() instanceof \moodle_url) {
            $context['adminurl'] = $admin->action()->out(false);
            $context['adminlabel'] = $label('administration');
        }
        return $this->render_from_template('theme_material/display_menu', $context);
    }

    /**
     * Put the features launcher in front of the other toolbar items.
     *
     * @return string
     */
    public function navbar_plugin_output() {
        $output = parent::navbar_plugin_output();
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return $output;
        }
        $course = $this->page->course ?? null;
        $groups = \theme_material\features::groups($course);
        if (!$groups) {
            return $output;
        }
        $context = [
            'groups' => $groups,
            'chips' => array_map(fn($g) => ['key' => $g['key'], 'label' => $g['label']], $groups),
            'pageurl' => (new \moodle_url('/theme/material/features.php',
                $course && $course->id != SITEID ? ['course' => $course->id] : []))->out(false),
            'page' => false,
        ];
        return $this->render_from_template('theme_material/features_launcher', $context) . $output;
    }
}
