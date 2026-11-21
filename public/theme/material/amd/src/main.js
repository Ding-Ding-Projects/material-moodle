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
 * Material theme behaviours: ripples, page transitions, copy buttons, celebrations and the command palette.
 *
 * @module     theme_material/main
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['theme_material/celebrate', 'theme_material/palette', 'theme_material/copy', 'theme_material/contextmenu',
    'theme_material/searchbars', 'theme_material/notifications', 'theme_material/provenance'],
    function(Celebrate, Palette, Copy, ContextMenu, SearchBars, Notifications, Provenance) {
    var started = false;

    /**
     * Should motion be reduced right now?
     *
     * @param {Object} config
     * @returns {boolean}
     */
    function reduced(config) {
        return config.motion === 'off' ||
            (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    /**
     * Ripple from the pointer position on buttons.
     */
    function ripples() {
        document.addEventListener('pointerdown', function(e) {
            var target = e.target.closest ? e.target.closest('.btn, .md-chip, .md-passkey-btn') : null;
            if (!target || target.disabled) {
                return;
            }
            var rect = target.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height) * 2;
            var dot = document.createElement('span');
            dot.className = 'md-ripple';
            dot.style.width = dot.style.height = size + 'px';
            dot.style.left = (e.clientX - rect.left - size / 2) + 'px';
            dot.style.top = (e.clientY - rect.top - size / 2) + 'px';
            if (getComputedStyle(target).position === 'static') {
                target.style.position = 'relative';
            }
            target.appendChild(dot);
            setTimeout(function() {
                dot.remove();
            }, 700);
        }, {passive: true});
    }

    return {
        /**
         * Start everything once per page.
         *
         * @param {Object} config Settings from theme_material\settings_values::js_config().
         */
        init: function(config) {
            if (started) {
                return;
            }
            started = true;
            config = config || {};
            config.strings = (window.M && M.str && M.str.theme_material) ? M.str.theme_material : {};
            var calm = reduced(config);
            if (config.pagetransitions && !calm) {
                document.documentElement.classList.add('md-transitions');
            }
            if (config.ripples && !calm && config.motion !== 'calm') {
                ripples();
            }
            if (config.copybuttons) {
                Copy.init(config.strings || {});
            }
            if (config.celebrations) {
                Celebrate.watch(config);
            }
            if (config.commandpalette) {
                Palette.init(config);
            }
            if (config.notifications) {
                Notifications.init(config.strings || {});
            }
            if (config.provenance) {
                Provenance.init(config.strings || {});
            }
            if (config.regexbuilder) {
                SearchBars.init(config.strings || {});
            }
            if (config.contextmenu) {
                ContextMenu.init(config.strings || {});
            }
        }
    };
});
