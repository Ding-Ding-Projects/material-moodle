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
 * Adds "New group chat" and "My group chats" to the messaging drawer and the messaging page.
 *
 * @module     local_groupchats/drawer
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var MARK = 'data-local-groupchats-actions';

    /**
     * Build the action row.
     *
     * @param {Object} config
     * @return {HTMLElement}
     */
    function buildActions(config) {
        var row = document.createElement('div');
        row.setAttribute(MARK, '1');
        row.className = 'local-groupchats-drawer-actions d-flex flex-wrap gap-2 mt-2';
        var create = document.createElement('a');
        create.href = config.newurl;
        create.className = 'btn btn-sm btn-primary local-groupchats-drawer-new';
        create.setAttribute('data-action', 'local-groupchats-new');
        create.textContent = '+ ' + config.newlabel;
        var list = document.createElement('a');
        list.href = config.listurl;
        list.className = 'btn btn-sm btn-outline-secondary';
        list.textContent = config.listlabel;
        row.appendChild(create);
        row.appendChild(list);
        return row;
    }

    /**
     * Add the actions to every messaging overview header that does not have them yet.
     *
     * @param {Object} config
     * @return {boolean} Whether at least one header was found.
     */
    function decorate(config) {
        // Only the overview header holds the search box; the footer shares the region name.
        var headers = [];
        document.querySelectorAll('[data-region="view-overview"]').forEach(function(region) {
            if (region.querySelector('[data-region="view-overview-search-input"]')) {
                headers.push(region);
            }
        });
        headers.forEach(function(header) {
            if (header.querySelector('[' + MARK + ']')) {
                return;
            }
            header.appendChild(buildActions(config));
        });
        return headers.length > 0;
    }

    return {
        /**
         * Start watching for the messaging drawer.
         *
         * @param {Object} config URLs and labels.
         */
        init: function(config) {
            decorate(config);
            var pending = false;
            new MutationObserver(function() {
                if (pending) {
                    return;
                }
                pending = true;
                window.requestAnimationFrame(function() {
                    pending = false;
                    decorate(config);
                });
            }).observe(document.body, {childList: true, subtree: true});
        }
    };
});
