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
 * Palette generator tests: every generated pair stays readable for any seed colour.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_material\palette
 */
final class palette_test extends \basic_testcase {
    /**
     * Seeds across the colour wheel, including very bright and very dull ones.
     *
     * @return array
     */
    public static function seed_provider(): array {
        return [['#8B5000'], ['#006A6A'], ['#4F5B92'], ['#9C4146'], ['#FFEB3B'], ['#00FF00'], ['#808080'], ['#000000'],
            ['#FFFFFF'], ['#6750A4']];
    }

    /**
     * Text on every surface meets WCAG AA 4.5:1 in light and dark mode.
     *
     * @dataProvider seed_provider
     * @param string $seed
     */
    public function test_pairs_are_readable(string $seed): void {
        $pairs = [['primary', 'on-primary'], ['primary-container', 'on-primary-container'], ['surface', 'on-surface'],
            ['surface', 'on-surface-variant'], ['error-container', 'on-error-container'],
            ['secondary-container', 'on-secondary-container'], ['tertiary-container', 'on-tertiary-container'],
            ['success-container', 'on-success-container'], ['surface', 'primary'], ['error', 'on-error']];
        foreach ([false, true] as $dark) {
            $scheme = palette::scheme($seed, $dark);
            foreach ($pairs as [$bg, $fg]) {
                $this->assertGreaterThanOrEqual(4.5, palette::contrast($scheme[$bg], $scheme[$fg]),
                    "$fg on $bg for $seed " . ($dark ? 'dark' : 'light'));
            }
        }
    }

    /**
     * Bad input falls back to the default seed.
     */
    public function test_normalise(): void {
        $this->assertSame('#AABBCC', palette::normalise('abc'));
        $this->assertSame(palette::DEFAULT_SEED, palette::normalise('red; } body { display:none'));
    }

    /**
     * The generated CSS defines both modes and the Bootstrap variables.
     */
    public function test_css(): void {
        $css = palette::css('#8B5000');
        $this->assertStringContainsString('[data-bs-theme=dark]{', $css);
        $this->assertStringContainsString('--bs-primary:', $css);
        $this->assertStringContainsString('--md-sys-color-on-surface:', $css);
    }
}
