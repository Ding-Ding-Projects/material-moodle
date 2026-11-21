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

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_html_attributes;

/**
 * Hook callbacks for the Material theme.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Is the page being rendered with this theme?
     *
     * @return bool
     */
    private static function active(): bool {
        global $PAGE;
        try {
            // The page object exposes the theme through a magic getter, so isset() cannot be used here.
            $theme = $PAGE->theme;
        } catch (\Throwable $e) {
            return false;
        }
        return $theme && $theme->name === 'material';
    }

    /**
     * Mark the motion level on the html tag so CSS can follow it from the first paint.
     *
     * @param before_html_attributes $hook
     */
    public static function before_html_attributes(before_html_attributes $hook): void {
        if (!self::active()) {
            return;
        }
        $config = settings_values::js_config();
        $hook->add_attribute('data-md-motion', $config['motion']);
    }

    /**
     * Start the Material behaviours on every page.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::active() || during_initial_install()) {
            return;
        }
        $config = settings_values::js_config();
        $config['adminsearch'] = has_capability('moodle/site:config', \context_system::instance())
            ? (new \moodle_url('/admin/search.php'))->out(false) : '';
        $config['coursesearch'] = (new \moodle_url('/course/search.php'))->out(false);
        $config['loggedin'] = isloggedin() && !isguestuser();
        $keys = ['search', 'searchplaceholder', 'copy', 'copied', 'regexbuilder', 'useregex', 'ignorecase', 'clear',
            'nomatch', 'matches', 'patternincomplete', 'removeblock', 'searchcourses', 'searchsettings', 'kindpage',
            'kindcourse', 'kindsearch', 'kindsetting', 'blockstartswith', 'blockendswith', 'blockanything', 'blockdigit',
            'blockletter', 'blockwordedge', 'blockspace', 'blockoneormore', 'blockoptional', 'blockeither',
            'blockgroupopen', 'blockgroupclose', 'ctxmenu', 'ctxcopy', 'ctxsearchfor', 'ctxopen', 'ctxopentab', 'ctxcopylink',
            'ctxopenimage', 'ctxcopyimage', 'ctxcopycode', 'ctxback', 'ctxreload', 'ctxcopypage', 'ctxprint', 'ctxtop'];
        // Strings travel through M.str so the init call stays small.
        $PAGE->requires->strings_for_js($keys, 'theme_material');
        $PAGE->requires->js_call_amd('theme_material/main', 'init', [$config]);
    }
}
