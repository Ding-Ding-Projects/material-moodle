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
 * Builds a Material 3 style tonal colour scheme from one seed colour.
 *
 * Tones follow the Material 3 role table (primary at tone 40 in light mode, 80 in dark mode and so on). Tones are
 * approximated in HSL, which keeps the generator dependency free while staying close to the reference scheme.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette {
    /** @var string Fallback seed when the setting is empty or invalid. */
    public const DEFAULT_SEED = '#8B5000';

    /**
     * Normalise a colour to #RRGGBB.
     *
     * @param string|null $hex
     * @return string
     */
    public static function normalise(?string $hex): string {
        $hex = trim((string) $hex);
        if (preg_match('/^#?([0-9a-f]{3})$/i', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        if (!preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) {
            return self::DEFAULT_SEED;
        }
        return '#' . strtoupper($m[1]);
    }

    /**
     * Hex to hue (0-360), saturation and lightness (0-1).
     *
     * @param string $hex
     * @return float[]
     */
    public static function hex_to_hsl(string $hex): array {
        $hex = ltrim(self::normalise($hex), '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            return [0.0, 0.0, $l];
        }
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max == $r) {
            $h = (($g - $b) / $d) + ($g < $b ? 6 : 0);
        } else if ($max == $g) {
            $h = (($b - $r) / $d) + 2;
        } else {
            $h = (($r - $g) / $d) + 4;
        }
        return [$h * 60, $s, $l];
    }

    /**
     * HSL to hex.
     *
     * @param float $h
     * @param float $s
     * @param float $l
     * @return string
     */
    public static function hsl_to_hex(float $h, float $s, float $l): string {
        $h = fmod(($h + 360), 360) / 360;
        $s = max(0, min(1, $s));
        $l = max(0, min(1, $l));
        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue($p, $q, $h + 1 / 3);
            $g = self::hue($p, $q, $h);
            $b = self::hue($p, $q, $h - 1 / 3);
        }
        return sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    /**
     * HSL helper.
     *
     * @param float $p
     * @param float $q
     * @param float $t
     * @return float
     */
    private static function hue(float $p, float $q, float $t): float {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    /**
     * A tone (0 black, 100 white) of a hue and saturation.
     *
     * @param float $h
     * @param float $s
     * @param float $tone
     * @return string
     */
    public static function tone(float $h, float $s, float $tone): string {
        // Very light and very dark tones need less saturation to stay readable and calm.
        $edge = min($tone, 100 - $tone) / 50;
        $sat = $s * (0.55 + 0.45 * $edge);
        if ($tone >= 100) {
            return '#FFFFFF';
        }
        if ($tone <= 0) {
            return '#000000';
        }
        // Material tones are perceptual lightness (CIELAB L*), so search the HSL lightness that reaches it.
        $lo = 0.0;
        $hi = 1.0;
        for ($i = 0; $i < 24; $i++) {
            $mid = ($lo + $hi) / 2;
            if (self::lstar(self::hsl_to_hex($h, $sat, $mid)) < $tone) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        return self::hsl_to_hex($h, $sat, ($lo + $hi) / 2);
    }

    /**
     * CIELAB lightness of a colour.
     *
     * @param string $hex
     * @return float
     */
    public static function lstar(string $hex): float {
        $y = self::luminance($hex);
        return $y > 216 / 24389 ? 116 * pow($y, 1 / 3) - 16 : $y * 24389 / 27;
    }

    /**
     * Relative luminance (WCAG).
     *
     * @param string $hex
     * @return float
     */
    public static function luminance(string $hex): float {
        $hex = ltrim($hex, '#');
        $c = [];
        foreach ([0, 2, 4] as $i) {
            $v = hexdec(substr($hex, $i, 2)) / 255;
            $c[] = $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /**
     * WCAG contrast ratio.
     *
     * @param string $a
     * @param string $b
     * @return float
     */
    public static function contrast(string $a, string $b): float {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * The full role table for one mode.
     *
     * @param string $seed
     * @param bool $dark
     * @return array role => #RRGGBB
     */
    public static function scheme(string $seed, bool $dark): array {
        [$h, $s] = self::hex_to_hsl($seed);
        $ps = max(0.38, min(0.9, $s));
        $roles = [
            // Role => [hue, saturation, light tone, dark tone].
            'primary' => [$h, $ps, 40, 80],
            'on-primary' => [$h, $ps, 100, 20],
            'primary-container' => [$h, $ps, 90, 30],
            'on-primary-container' => [$h, $ps, 10, 90],
            'secondary' => [$h, $ps * 0.32, 40, 80],
            'on-secondary' => [$h, $ps * 0.32, 100, 20],
            'secondary-container' => [$h, $ps * 0.45, 90, 30],
            'on-secondary-container' => [$h, $ps * 0.45, 10, 90],
            'tertiary' => [$h + 60, $ps * 0.5, 40, 80],
            'on-tertiary' => [$h + 60, $ps * 0.5, 100, 20],
            'tertiary-container' => [$h + 60, $ps * 0.55, 90, 30],
            'on-tertiary-container' => [$h + 60, $ps * 0.55, 10, 90],
            'error' => [2, 0.75, 40, 80],
            'on-error' => [2, 0.75, 100, 20],
            'error-container' => [5, 0.9, 92, 30],
            'on-error-container' => [5, 0.9, 12, 90],
            'success' => [130, 0.45, 32, 78],
            'on-success' => [130, 0.45, 100, 15],
            'success-container' => [125, 0.6, 88, 25],
            'on-success-container' => [125, 0.6, 8, 88],
            'surface' => [$h, 0.25, 98, 6],
            'surface-dim' => [$h, 0.15, 87, 6],
            'surface-bright' => [$h, 0.25, 98, 24],
            'surface-container-lowest' => [$h, 0.2, 100, 4],
            'surface-container-low' => [$h, 0.28, 96, 10],
            'surface-container' => [$h, 0.25, 94, 12],
            'surface-container-high' => [$h, 0.2, 92, 17],
            'surface-container-highest' => [$h, 0.16, 90, 22],
            'on-surface' => [$h, 0.12, 10, 90],
            'surface-variant' => [$h, 0.2, 90, 30],
            'on-surface-variant' => [$h, 0.14, 30, 80],
            'outline' => [$h, 0.08, 50, 60],
            'outline-variant' => [$h, 0.14, 80, 30],
            'inverse-surface' => [$h, 0.1, 20, 90],
            'inverse-on-surface' => [$h, 0.3, 95, 20],
            'inverse-primary' => [$h, $ps, 80, 40],
            'scrim' => [0, 0, 0, 0],
        ];
        $out = [];
        foreach ($roles as $role => [$rh, $rs, $light, $darktone]) {
            $out[$role] = self::tone($rh, $rs, $dark ? $darktone : $light);
        }
        return $out;
    }

    /**
     * #RRGGBB to "r, g, b".
     *
     * @param string $hex
     * @return string
     */
    public static function rgb(string $hex): string {
        $hex = ltrim($hex, '#');
        return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
    }

    /**
     * CSS custom properties for both modes, including the Bootstrap variables Moodle reads.
     *
     * @param string $seed
     * @return string
     */
    public static function css(string $seed): string {
        $seed = self::normalise($seed);
        $css = '';
        foreach ([false => ':root,[data-bs-theme=light]', true => '[data-bs-theme=dark]'] as $dark => $selector) {
            $scheme = self::scheme($seed, (bool) $dark);
            $vars = ['--md-seed:' . $seed];
            foreach ($scheme as $role => $hex) {
                $vars[] = '--md-sys-color-' . $role . ':' . $hex;
                $vars[] = '--md-sys-color-' . $role . '-rgb:' . self::rgb($hex);
            }
            $map = [
                'body-bg' => 'surface',
                'body-color' => 'on-surface',
                'emphasis-color' => 'on-surface',
                'secondary-color' => 'on-surface-variant',
                'secondary-bg' => 'surface-container',
                'tertiary-bg' => 'surface-container-low',
                'tertiary-color' => 'on-surface-variant',
                'border-color' => 'outline-variant',
                'border-color-translucent' => 'outline-variant',
                'primary' => 'primary',
                'link-color' => 'primary',
                'link-hover-color' => 'on-primary-container',
                'primary-bg-subtle' => 'primary-container',
                'primary-text-emphasis' => 'on-primary-container',
                'primary-border-subtle' => 'primary-container',
                'secondary-bg-subtle' => 'secondary-container',
                'secondary-text-emphasis' => 'on-secondary-container',
                'success' => 'success',
                'success-bg-subtle' => 'success-container',
                'success-text-emphasis' => 'on-success-container',
                'danger' => 'error',
                'danger-bg-subtle' => 'error-container',
                'danger-text-emphasis' => 'on-error-container',
                'info-bg-subtle' => 'tertiary-container',
                'info-text-emphasis' => 'on-tertiary-container',
                'focus-ring-color' => 'primary',
            ];
            foreach ($map as $bs => $role) {
                $vars[] = '--bs-' . $bs . ':' . $scheme[$role];
            }
            foreach (['primary' => 'primary', 'body-bg' => 'surface', 'body-color' => 'on-surface',
                    'link-color' => 'primary', 'emphasis-color' => 'on-surface', 'secondary-bg' => 'surface-container',
                    'tertiary-bg' => 'surface-container-low', 'success' => 'success', 'danger' => 'error'] as $bs => $role) {
                $vars[] = '--bs-' . $bs . '-rgb:' . self::rgb($scheme[$role]);
            }
            $css .= $selector . '{' . implode(';', $vars) . "}\n";
        }
        return $css;
    }
}
