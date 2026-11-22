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


namespace theme_material;

/**
 * Typed access to the Material theme settings, with defaults for every value.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_values {
    /** @var string[] Font stacks keyed by setting value. */
    public const FONTS = [
        'robotoflex' => "'Roboto Flex', 'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'inter' => "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'atkinson' => "'Atkinson Hyperlegible Next', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'system' => "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    ];

    /** @var string Seed colour. */
    public string $seed = palette::DEFAULT_SEED;
    /** @var string Font key. */
    public string $font = 'robotoflex';
    /** @var string Font stack. */
    public string $fontstack = '';
    /** @var int Large corner radius in px. */
    public int $radius = 24;
    /** @var string comfortable|compact */
    public string $density = 'comfortable';
    /** @var string expressive|calm|off */
    public string $motion = 'expressive';

    /**
     * Build from a theme_config.
     *
     * @param \theme_config|null $theme
     * @return self
     */
    public static function from_theme(?\theme_config $theme = null): self {
        $s = $theme ? $theme->settings : get_config('theme_material');
        $v = new self();
        $v->seed = palette::normalise($s->seedcolor ?? palette::DEFAULT_SEED);
        $v->font = array_key_exists($s->font ?? '', self::FONTS) ? $s->font : 'robotoflex';
        $v->fontstack = self::FONTS[$v->font];
        $v->radius = max(0, min(48, (int) ($s->radius ?? 24)));
        $v->density = ($s->density ?? '') === 'compact' ? 'compact' : 'comfortable';
        $v->motion = in_array($s->motion ?? '', ['expressive', 'calm', 'off']) ? $s->motion : 'expressive';
        return $v;
    }

    /**
     * Font faces for the vendored fonts.
     *
     * @return string
     */
    public static function font_faces(): string {
        $faces = [
            ['Roboto Flex', 'roboto-flex-latin-wght-normal.woff2', '100 1000'],
            ['Inter', 'inter-latin-wght-normal.woff2', '100 900'],
            ['Roboto Mono', 'roboto-mono-latin-wght-normal.woff2', '100 700'],
            ['Atkinson Hyperlegible Next', 'atkinson-hyperlegible-next-latin-400-normal.woff2', '400'],
            ['Atkinson Hyperlegible Next', 'atkinson-hyperlegible-next-latin-700-normal.woff2', '700'],
        ];
        $css = '';
        foreach ($faces as [$family, $file, $weight]) {
            $css .= "@font-face{font-family:'{$family}';font-style:normal;font-display:swap;font-weight:{$weight};"
                . "src:url([[font:theme|{$file}]]) format('woff2');}\n";
        }
        return $css;
    }

    /**
     * Feature switches and motion settings passed to the browser.
     *
     * @return array
     */
    public static function js_config(): array {
        $c = get_config('theme_material');
        $flag = fn(string $name, bool $default = true) => isset($c->$name) ? (bool) $c->$name : $default;
        $motion = in_array($c->motion ?? '', ['expressive', 'calm', 'off']) ? $c->motion : 'expressive';
        $sitemotion = $motion;
        $display = class_exists(\local_materialtools\display_preferences::class)
            ? \local_materialtools\display_preferences::current() : [];
        if (($display['motion'] ?? '') === 'off' || (($display['motion'] ?? '') === 'calm' && $motion === 'expressive')) {
            $motion = $display['motion'];
        }
        return [
            'motion' => $motion,
            'sitemotion' => $sitemotion,
            'textsize' => $display['textsize'] ?? 'site',
            'spacing' => $display['spacing'] ?? 'site',
            'commandpalette' => $flag('commandpalette'),
            'regexbuilder' => $flag('regexbuilder'),
            'copybuttons' => $flag('copybuttons'),
            'celebrations' => $flag('celebrations'),
            'celebrationlevel' => in_array($c->celebrationlevel ?? '', ['subtle', 'normal', 'dramatic'])
                ? $c->celebrationlevel : 'dramatic',
            'ripples' => $flag('ripples'),
            'contextmenu' => $flag('contextmenu'),
            'notifications' => $flag('notifications'),
            'provenance' => $flag('provenance'),
            'pagetransitions' => $flag('pagetransitions'),
            'shortcut' => 'ctrl+shift+f',
        ];
    }
}
