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
 * A Material right-click menu on every element, with working shortcuts shown beside each action.
 * Shift + right-click opens the browser's own menu.
 *
 * @module     theme_material/contextmenu
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['theme_material/copy', 'theme_material/palette'], function(Copy, Palette) {
    var menu = null;
    var s = {};
    var opener = null;

    /**
     * Close the menu.
     */
    function close() {
        if (menu) {
            menu.remove();
            menu = null;
            if (opener && opener.focus) {
                opener.focus();
            }
        }
    }

    /**
     * Actions for what was clicked.
     *
     * @param {HTMLElement} target
     * @returns {Array}
     */
    function actionsFor(target) {
        var items = [];
        var selection = String(window.getSelection ? window.getSelection() : '').trim();
        var link = target.closest('a[href]');
        var img = target.closest('img[src]');
        var pre = target.closest('pre, code');
        if (selection) {
            items.push({label: s.ctxcopy, keys: 'Ctrl+C', run: function() {
                Copy.write(selection);
            }});
            items.push({label: (s.ctxsearchfor || 'Search for') + ' "' + selection.slice(0, 24) + (selection.length > 24 ? '…' : '') + '"',
                keys: 'Ctrl+Shift+F', run: function() {
                    Palette.open(selection);
                }});
        }
        if (link && !/^javascript:/i.test(link.getAttribute('href'))) {
            items.push('-');
            items.push({label: s.ctxopen, keys: 'Enter', run: function() {
                window.location.href = link.href;
            }});
            items.push({label: s.ctxopentab, keys: 'Ctrl+Click', run: function() {
                window.open(link.href, '_blank', 'noopener');
            }});
            items.push({label: s.ctxcopylink, run: function() {
                Copy.write(link.href);
            }});
        }
        if (img) {
            items.push('-');
            items.push({label: s.ctxopenimage, run: function() {
                window.open(img.src, '_blank', 'noopener');
            }});
            items.push({label: s.ctxcopyimage, run: function() {
                Copy.write(img.src);
            }});
        }
        if (pre) {
            items.push('-');
            items.push({label: s.ctxcopycode, run: function() {
                Copy.write(pre.innerText);
            }});
        }
        items.push('-');
        items.push({label: s.search, keys: 'Ctrl+Shift+F', run: function() {
            Palette.open('');
        }});
        items.push({label: s.ctxback, keys: 'Alt+←', disabled: history.length < 2, run: function() {
            history.back();
        }});
        items.push({label: s.ctxreload, keys: 'F5', run: function() {
            window.location.reload();
        }});
        items.push({label: s.ctxcopypage, run: function() {
            Copy.write(window.location.href);
        }});
        items.push({label: s.ctxprint, keys: 'Ctrl+P', run: function() {
            window.print();
        }});
        items.push({label: s.ctxtop, keys: 'Home', run: function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        }});
        return items.filter(function(item, i, all) {
            return item !== '-' || (i > 0 && all[i - 1] !== '-');
        });
    }

    /**
     * Show the menu.
     *
     * @param {MouseEvent} e
     */
    function show(e) {
        close();
        opener = document.activeElement;
        menu = document.createElement('div');
        menu.className = 'md-context-menu';
        menu.setAttribute('role', 'menu');
        menu.setAttribute('aria-label', s.ctxmenu || 'Menu');
        actionsFor(e.target).forEach(function(item) {
            if (item === '-') {
                var hr = document.createElement('div');
                hr.className = 'md-context-sep';
                hr.setAttribute('role', 'separator');
                menu.appendChild(hr);
                return;
            }
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'menuitem');
            b.disabled = !!item.disabled;
            var label = document.createElement('span');
            label.textContent = item.label;
            b.appendChild(label);
            if (item.keys) {
                var k = document.createElement('kbd');
                k.textContent = item.keys;
                b.appendChild(k);
            }
            b.addEventListener('click', function() {
                close();
                item.run();
            });
            menu.appendChild(b);
        });
        document.body.appendChild(menu);
        var w = menu.offsetWidth;
        var h = menu.offsetHeight;
        menu.style.left = Math.min(e.clientX, window.innerWidth - w - 8) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - h - 8) + 'px';
        var first = menu.querySelector('button:not([disabled])');
        if (first) {
            first.focus();
        }
        menu.addEventListener('keydown', function(ev) {
            var buttons = Array.prototype.slice.call(menu.querySelectorAll('button:not([disabled])'));
            var i = buttons.indexOf(document.activeElement);
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                var next = buttons[(i + (ev.key === 'ArrowDown' ? 1 : buttons.length - 1)) % buttons.length];
                next.focus();
            } else if (ev.key === 'Escape' || ev.key === 'Tab') {
                ev.preventDefault();
                close();
            }
        });
    }

    return {
        /**
         * Start.
         *
         * @param {Object} strings
         */
        init: function(strings) {
            s = strings || {};
            document.addEventListener('contextmenu', function(e) {
                if (e.shiftKey || e.target.closest('input, textarea, select, [contenteditable="true"], .editor_atto, .tox, ' +
                        '.md-context-menu, [data-md-native-menu]')) {
                    return;
                }
                e.preventDefault();
                show(e);
            });
            document.addEventListener('pointerdown', function(e) {
                if (menu && !menu.contains(e.target)) {
                    close();
                }
            });
            window.addEventListener('blur', close);
            window.addEventListener('scroll', close, {passive: true});
        }
    };
});
