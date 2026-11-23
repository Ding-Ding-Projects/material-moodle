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

use moodle_url;

/**
 * The navigation rail from the redesign: a rail on wide screens, a bottom bar on phones.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rail {
    /** @var string[] Page layouts that keep their own minimal chrome. */
    private const SKIP_LAYOUTS = ['login', 'embedded', 'popup', 'frametop', 'maintenance', 'print', 'redirect', 'secure'];

    /**
     * Whether this page shows the rail.
     *
     * @return bool
     */
    public static function shown(): bool {
        global $PAGE;
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return false;
        }
        try {
            $layout = $PAGE->pagelayout;
        } catch (\Throwable $e) {
            return false;
        }
        return !in_array($layout, self::SKIP_LAYOUTS, true);
    }

    /**
     * The rail's HTML.
     *
     * @return string
     */
    public static function render(): string {
        global $OUTPUT, $PAGE, $USER, $SITE, $CFG;
        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        $is = fn(string ...$prefixes) => (bool) array_filter($prefixes, fn($p) => str_contains($path, $p));
        $home = !empty($CFG->enabledashboard) ? new moodle_url('/my/') : new moodle_url('/');
        $items = [
            ['home', $home, 'M4 11l8-7 8 7v9h-5v-6H9v6H4z', $is('/my/index.php') || $path === '/my/', true],
            ['courses', new moodle_url('/my/courses.php'), 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 19V5',
                $is('/my/courses.php', '/course/view.php', '/course/index.php', '/course/search.php', '/mod/'), true],
            ['calendar', new moodle_url('/calendar/view.php', ['view' => 'month']), 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
                $is('/calendar/'), true],
            ['grades', new moodle_url('/grade/report/mygrades.php'), 'M4 20V10M10 20V4M16 20v-8M22 20H2', $is('/grade/'), false],
            ['messages', new moodle_url('/message/index.php'), 'M4 5h16v11H8l-4 4z', $is('/message/'), true],
            ['everything', new moodle_url('/theme/material/features.php'), 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
                $is('/theme/material/features.php'), true],
        ];
        $context = [
            'sitename' => format_string($SITE->fullname),
            'homeurl' => $home->out(false),
            'settingsurl' => (new moodle_url('/user/preferences.php'))->out(false),
            'settingsactive' => $is('/user/preferences.php', '/admin/'),
            'profileurl' => (new moodle_url('/user/profile.php'))->out(false),
            'profileactive' => $is('/user/profile.php'),
            'initials' => \core_text::strtoupper(\core_text::substr($USER->firstname ?? '', 0, 1) .
                \core_text::substr($USER->lastname ?? '', 0, 1)),
            'items' => [],
        ];
        if (empty(get_config('core', 'messaging'))) {
            $items = array_values(array_filter($items, fn($i) => $i[0] !== 'messages'));
        }
        foreach ($items as [$key, $url, $icon, $active, $phone]) {
            $context['items'][] = ['key' => $key, 'label' => get_string('rail_' . $key, 'theme_material'),
                'url' => $url->out(false), 'icon' => $icon, 'active' => $active, 'phone' => $phone];
        }
        if (\core_course_category::has_capability_on_any('moodle/course:create')) {
            $category = \core_course_category::get_default();
            $context['newurl'] = (new moodle_url('/course/edit.php', ['category' => $category->id]))->out(false);
        }
        return $OUTPUT->render_from_template('theme_material/rail', $context);
    }
}
