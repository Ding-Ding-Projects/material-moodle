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
 * Group chats page helpers: live search over lists, copy buttons and confirmation of destructive forms.
 *
 * @module     local_groupchats/ui
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    var started = false;

    /**
     * Whether the item text matches the query, as plain text or as a regular expression.
     *
     * @param {string} text
     * @param {string} query
     * @return {boolean}
     */
    function matches(text, query) {
        if (!query) {
            return true;
        }
        if (text.toLowerCase().indexOf(query.toLowerCase()) !== -1) {
            return true;
        }
        try {
            return new RegExp(query, 'i').test(text);
        } catch (e) {
            return false;
        }
    }

    /**
     * Filter one list as the user types.
     *
     * @param {HTMLInputElement} input
     */
    function attachSearch(input) {
        var list = document.querySelector(input.getAttribute('data-local-groupchats-search'));
        if (!list) {
            return;
        }
        var status = input.parentNode.querySelector('[data-region="search-status"]');
        var nomatch = list.parentNode.querySelector('[data-region="no-match"]');
        var run = function() {
            var query = input.value.trim();
            var items = list.querySelectorAll('[data-search-item]');
            var shown = 0;
            items.forEach(function(item) {
                var hit = matches(item.textContent.replace(/\s+/g, ' '), query);
                item.classList.toggle('local-groupchats-filtered', !hit);
                if (hit) {
                    shown++;
                }
            });
            if (status) {
                status.textContent = query ? shown + ' / ' + items.length : '';
            }
            if (nomatch) {
                nomatch.hidden = shown > 0 || !query;
            }
        };
        input.addEventListener('input', run);
        input.addEventListener('search', run);
    }

    /**
     * Copy text to the clipboard, with a fallback for pages served without HTTPS.
     *
     * @param {string} text
     * @return {Promise}
     */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }
            document.body.removeChild(area);
            if (ok) {
                resolve();
            } else {
                reject(new Error('Copy failed'));
            }
        });
    }

    /**
     * Handle [data-md-copy] buttons when the Material theme does not already do it.
     */
    function copyButtons() {
        if (document.documentElement.hasAttribute('data-md-motion')) {
            return;
        }
        document.addEventListener('click', function(e) {
            var button = e.target.closest ? e.target.closest('[data-md-copy]') : null;
            if (!button) {
                return;
            }
            var node = document.getElementById(button.getAttribute('data-md-copy'));
            var text = node ? (node.value !== undefined && node.tagName !== 'A' ? node.value : node.textContent)
                : (button.getAttribute('data-md-copy-text') || '');
            var original = button.textContent;
            copyText(text.trim()).then(function() {
                button.textContent = button.getAttribute('data-md-copied-label') || original;
                button.classList.add('local-groupchats-copied');
                setTimeout(function() {
                    button.textContent = original;
                    button.classList.remove('local-groupchats-copied');
                }, 1600);
                return null;
            }).catch(function() {
                return null;
            });
        });
    }

    /**
     * Ask before submitting forms marked with data-local-groupchats-confirm.
     */
    function confirmations() {
        document.addEventListener('submit', function(e) {
            var form = e.target;
            var question = form.getAttribute ? form.getAttribute('data-local-groupchats-confirm') : null;
            if (question && !window.confirm(question)) {
                e.preventDefault();
            }
        });
    }

    return {
        /**
         * Start the helpers on the current page.
         */
        init: function() {
            document.querySelectorAll('[data-local-groupchats-search]').forEach(function(input) {
                if (!input.dataset.localGroupchatsReady) {
                    input.dataset.localGroupchatsReady = '1';
                    attachSearch(input);
                }
            });
            if (started) {
                return;
            }
            started = true;
            copyButtons();
            confirmations();
            // Preload a common string so later notifications render without a round trip.
            Str.get_string('copied', 'local_groupchats').catch(function() {
                return null;
            });
        },
        matches: matches
    };
});
