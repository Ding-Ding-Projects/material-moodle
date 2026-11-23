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

/**
 * Upgrade steps for the Material theme.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_theme_material_upgrade($oldversion) {
    if ($oldversion < 2026112302) {
        // Sites still on the previous default seed move to the redesign's default.
        // A seed somebody chose on purpose is left alone.
        $seed = get_config('theme_material', 'seedcolor');
        if ($seed === false || $seed === '' || strcasecmp(trim($seed), '#8B5000') === 0) {
            set_config('seedcolor', \theme_material\palette::DEFAULT_SEED, 'theme_material');
            theme_reset_all_caches();
        }
        upgrade_plugin_savepoint(true, 2026112302, 'theme', 'material');
    }
    return true;
}
