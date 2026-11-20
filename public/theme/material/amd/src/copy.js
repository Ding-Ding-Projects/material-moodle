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
 * Copy buttons on every code block, and on any element marked with data-md-copy.
 *
 * @module     theme_material/copy
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var labels = {copy: 'Copy', copied: 'Copied'};

    /**
     * Put text on the clipboard.
     *
     * @param {string} text
     * @returns {Promise}
     */
    function write(text) {
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
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            }
            area.remove();
        });
    }

    /**
     * Copy, then show the confirmation on the button for a moment.
     *
     * @param {HTMLElement} button
     * @param {string} text
     */
    function copyFrom(button, text) {
        var original = button.dataset.mdLabel || button.textContent;
        button.dataset.mdLabel = original;
        write(text).then(function() {
            button.textContent = button.dataset.mdCopiedLabel || labels.copied;
            button.classList.add('is-copied');
            setTimeout(function() {
                button.textContent = original;
                button.classList.remove('is-copied');
            }, 1500);
            return null;
        }).catch(function() {
            button.textContent = original;
        });
    }

    /**
     * Add a copy button to each pre block that has none.
     *
     * @param {ParentNode} root
     */
    function decorate(root) {
        root.querySelectorAll('pre').forEach(function(pre) {
            if (pre.closest('.md-code-wrap') || pre.closest('.md-palette') || pre.dataset.mdNoCopy !== undefined) {
                return;
            }
            var wrap = document.createElement('div');
            wrap.className = 'md-code-wrap';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'md-copy-btn';
            button.textContent = labels.copy;
            button.setAttribute('aria-label', labels.copy);
            button.addEventListener('click', function() {
                copyFrom(button, pre.innerText.replace(/\n$/, ''));
            });
            wrap.insertBefore(button, pre);
        });
    }

    return {
        /**
         * Start watching the page.
         *
         * @param {Object} strings
         */
        init: function(strings) {
            labels.copy = strings.copy || labels.copy;
            labels.copied = strings.copied || labels.copied;
            decorate(document);
            document.addEventListener('click', function(e) {
                var button = e.target.closest ? e.target.closest('[data-md-copy]') : null;
                if (!button) {
                    return;
                }
                var source = button.getAttribute('data-md-copy');
                var node = source ? document.getElementById(source) : null;
                var text = node ? (node.value !== undefined && node.tagName === 'TEXTAREA' ? node.value : node.textContent)
                    : (button.getAttribute('data-md-copy-text') || '');
                copyFrom(button, text);
            });
            new MutationObserver(function(records) {
                records.forEach(function(r) {
                    r.addedNodes.forEach(function(n) {
                        if (n.nodeType === 1) {
                            decorate(n.tagName === 'PRE' ? n.parentNode : n);
                        }
                    });
                });
            }).observe(document.body, {childList: true, subtree: true});
        },
        write: write
    };
});
