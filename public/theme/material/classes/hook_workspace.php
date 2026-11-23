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

/**
 * Starts the dockview workspace on course pages.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_workspace {
    /** @var string[] Language strings the workspace shows. */
    private const STRINGS = [
        'workspacetitle', 'workspacepanelindex', 'workspacepanelcourse', 'workspacepanelblocks', 'workspaceclose',
        'workspaceclosetab', 'workspaceclosed', 'workspacereopened', 'workspacesearchtabs', 'workspacesearchlabel',
        'workspacesearchnone', 'workspacesearchcount', 'workspacestateopen', 'workspacestateclosed', 'workspacelayout',
        'workspaceresetlayout', 'workspaceclassic', 'workspaceuse', 'workspacelayoutreset', 'workspacepin',
        'workspaceunpin', 'workspacepinned', 'workspacelock', 'workspaceunlock', 'workspacelocked', 'workspacemovenew',
        'workspacetabgroup', 'workspaceremovegroup', 'workspacegroupname', 'workspacegroupdefault',
        'workspacegroupcolour', 'workspacecolourprimary', 'workspacecoloursecondary', 'workspacecolourtertiary',
        'workspacecolourerror', 'workspacecoloursuccess', 'workspacehelp', 'workspacemenu', 'workspacepinnednoclose',
    ];

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
     * Start the workspace on course view pages for signed-in users.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer(before_footer_html_generation $hook): void {
        global $PAGE, $USER;
        if (!self::active() || during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        if (strpos((string) $PAGE->pagetype, 'course-view-') !== 0) {
            return;
        }
        $strings = [];
        foreach (self::STRINGS as $key) {
            // Strings that carry a placeholder keep a literal %s for the browser to fill in.
            $strings[$key] = get_string($key, 'theme_material', '%s');
        }
        $config = [
            'userid' => (int) $USER->id,
            'mode' => get_user_preferences('theme_material_workspace', 'workspace') === 'classic' ? 'classic' : 'workspace',
            'layout' => (string) get_user_preferences('theme_material_workspace_layout', ''),
            'strings' => $strings,
        ];
        $PAGE->requires->js_call_amd('theme_material/workspace', 'init', [$config]);
    }
}
