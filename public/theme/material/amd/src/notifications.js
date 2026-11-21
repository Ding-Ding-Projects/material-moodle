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
 * Notification centre: every alert and toast is kept in a searchable history behind a bell in the top bar.
 *
 * @module     theme_material/notifications
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var KEY = 'theme_material_notifications';
    var LIMIT = 200;
    var items = [];
    var s = {};
    var bell, panel, list, count, search;

    var load = function() {
        try {
            items = JSON.parse(window.localStorage.getItem(KEY) || '[]');
            if (!Array.isArray(items)) {
                items = [];
            }
        } catch (e) {
            items = [];
        }
    };

    var save = function() {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(items.slice(0, LIMIT)));
        } catch (e) {
            // Storage can be full or blocked; the history then lasts for this page only.
        }
    };

    var kindOf = function(el) {
        var c = el.className || '';
        if (/danger|error/.test(c)) {
            return 'error';
        }
        if (/warning/.test(c)) {
            return 'warning';
        }
        if (/success/.test(c)) {
            return 'success';
        }
        return 'info';
    };

    var record = function(el) {
        if (el.dataset.mdRecorded || el.closest('.md-notify-panel')) {
            return;
        }
        el.dataset.mdRecorded = '1';
        // Read the message without its close button and screen-reader-only extras.
        var copy = el.cloneNode(true);
        copy.querySelectorAll('button, .close, .btn-close, .sr-only, .visually-hidden').forEach(function(n) {
            n.remove();
        });
        var text = (copy.textContent || '').replace(/\s+/g, ' ').trim();
        if (!text || text.length < 3) {
            return;
        }
        var now = Date.now();
        // Moodle repeats the same notice on reload; keep one copy per minute.
        if (items[0] && items[0].text === text && now - items[0].time < 60000) {
            return;
        }
        items.unshift({id: now + '-' + Math.random().toString(36).slice(2, 7), text: text, kind: kindOf(el), time: now,
            page: document.title.split(' | ')[0], url: window.location.pathname + window.location.search, read: false});
        items = items.slice(0, LIMIT);
        save();
        render();
    };

    var matcher = function() {
        var q = search ? search.value : '';
        if (!q) {
            return function() {
                return true;
            };
        }
        var field = search.closest('.md-regex-field');
        if (field && field.querySelector('.md-regex-toggle.is-on')) {
            try {
                var re = new RegExp(q, 'i');
                return function(t) {
                    return re.test(t);
                };
            } catch (e) {
                return function() {
                    return false;
                };
            }
        }
        q = q.toLowerCase();
        return function(t) {
            return t.toLowerCase().indexOf(q) !== -1;
        };
    };

    var when = function(t) {
        var mins = Math.round((Date.now() - t) / 60000);
        if (mins < 1) {
            return s.notifynow || 'Just now';
        }
        if (mins < 60) {
            return (s.notifyminutes || '{$a} min ago').replace('{$a}', mins);
        }
        return new Date(t).toLocaleString();
    };

    var render = function() {
        if (!bell) {
            return;
        }
        var unread = items.filter(function(i) {
            return !i.read;
        }).length;
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.hidden = unread === 0;
        bell.setAttribute('aria-label', (s.notifications || 'Notifications') + (unread ? ' (' + unread + ')' : ''));
        if (panel.hidden) {
            return;
        }
        var test = matcher();
        list.textContent = '';
        var shown = items.filter(function(i) {
            return test(i.text + ' ' + i.page);
        });
        if (!shown.length) {
            var empty = document.createElement('li');
            empty.className = 'md-notify-empty';
            empty.textContent = items.length ? (s.notifynomatch || 'No notifications match') : (s.notifyempty || 'No notifications yet');
            list.appendChild(empty);
            return;
        }
        shown.forEach(function(i) {
            var li = document.createElement('li');
            li.className = 'md-notify-item is-' + i.kind + (i.read ? '' : ' is-unread');
            var box = document.createElement('input');
            box.type = 'checkbox';
            box.className = 'form-check-input';
            box.value = i.id;
            box.setAttribute('aria-label', s.notifyselect || 'Select');
            var body = document.createElement('a');
            body.href = i.url;
            body.className = 'md-notify-body';
            var t = document.createElement('span');
            t.className = 'md-notify-text';
            t.textContent = i.text;
            var meta = document.createElement('span');
            meta.className = 'md-notify-meta';
            meta.textContent = when(i.time) + ' · ' + i.page;
            body.appendChild(t);
            body.appendChild(meta);
            li.appendChild(box);
            li.appendChild(body);
            list.appendChild(li);
        });
    };

    var selected = function() {
        return Array.prototype.map.call(list.querySelectorAll('input:checked'), function(b) {
            return b.value;
        });
    };

    var button = function(label, handler, cls) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn ' + (cls || 'btn-outline-secondary') + ' btn-sm';
        b.textContent = label;
        b.addEventListener('click', handler);
        return b;
    };

    var open = function(show) {
        panel.hidden = !show;
        bell.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) {
            render();
            search.focus();
        }
    };

    var build = function() {
        // Look up one by one: a combined selector returns the first match in page order, which is the main menu.
        var host = document.getElementById('usernavigation') || document.querySelector('.usernav');
        if (!host) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'md-notify';
        bell = document.createElement('button');
        bell.type = 'button';
        bell.className = 'md-notify-bell';
        bell.setAttribute('aria-haspopup', 'dialog');
        bell.setAttribute('aria-expanded', 'false');
        // A history icon, so it is not mistaken for Moodle's own notifications bell.
        bell.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>' +
            '<span class="md-notify-count" hidden>0</span>';
        count = bell.querySelector('.md-notify-count');
        panel = document.createElement('div');
        panel.className = 'md-notify-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', s.notifications || 'Notifications');
        panel.hidden = true;
        var head = document.createElement('div');
        head.className = 'md-notify-head';
        var h = document.createElement('h2');
        h.textContent = s.notifications || 'Notifications';
        head.appendChild(h);
        search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control';
        search.placeholder = s.notifysearch || 'Search notifications';
        search.setAttribute('aria-label', search.placeholder);
        search.addEventListener('input', render);
        var actions = document.createElement('div');
        actions.className = 'md-notify-actions';
        actions.appendChild(button(s.notifymarkread || 'Mark all read', function() {
            items.forEach(function(i) {
                i.read = true;
            });
            save();
            render();
        }));
        actions.appendChild(button(s.notifyremoveselected || 'Remove selected', function() {
            var ids = selected();
            items = items.filter(function(i) {
                return ids.indexOf(i.id) === -1;
            });
            save();
            render();
        }));
        actions.appendChild(button(s.notifyclear || 'Clear all', function() {
            items = [];
            save();
            render();
        }, 'btn-outline-danger'));
        list = document.createElement('ul');
        list.className = 'md-notify-list';
        panel.appendChild(head);
        panel.appendChild(search);
        panel.appendChild(actions);
        panel.appendChild(list);
        wrap.appendChild(bell);
        wrap.appendChild(panel);
        host.insertBefore(wrap, host.firstChild);
        bell.addEventListener('click', function() {
            open(panel.hidden);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !panel.hidden) {
                open(false);
                bell.focus();
            }
        });
        document.addEventListener('click', function(e) {
            if (!panel.hidden && !wrap.contains(e.target)) {
                open(false);
            }
        });
        // Search fields get the regex switch from the search bar module when it runs after this.
    };

    return {
        /**
         * Start recording notifications.
         *
         * @param {Object} strings Theme strings.
         */
        init: function(strings) {
            s = strings || {};
            load();
            build();
            var scan = function(root) {
                if (root.matches && root.matches('.alert, .toast')) {
                    record(root);
                }
                if (root.querySelectorAll) {
                    root.querySelectorAll('.alert, .toast').forEach(record);
                }
            };
            scan(document.body);
            new MutationObserver(function(muts) {
                muts.forEach(function(m) {
                    m.addedNodes.forEach(function(n) {
                        if (n.nodeType === 1) {
                            scan(n);
                        }
                    });
                });
            }).observe(document.body, {childList: true, subtree: true});
            render();
        }
    };
});
