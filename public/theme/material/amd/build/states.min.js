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
 * The offline state: a banner while the browser has no connection, and a short note when it comes back.
 *
 * Moodle cannot save anything while offline, so the banner says that plainly instead of promising a sync.
 *
 * @module     theme_material/states
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('theme_material/states', [], function() {
    var banner = null;
    var str = function(key) {
        return (window.M && M.str && M.str.theme_material && M.str.theme_material[key]) || key;
    };

    var note = function(text) {
        var el = document.createElement('div');
        el.className = 'mm-state-note';
        el.setAttribute('role', 'status');
        el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function() {
            el.remove();
        }, 4000);
    };

    var hide = function() {
        if (banner) {
            banner.remove();
            banner = null;
            document.documentElement.classList.remove('mm-offline');
            note(str('stateonline'));
        }
    };

    // Ask the server for this page's headers; only a real answer counts as being back online.
    var check = function(button) {
        button.disabled = true;
        button.textContent = str('statechecking');
        fetch(window.location.href, {method: 'HEAD', cache: 'no-store', credentials: 'same-origin'})
            .then(function() {
                hide();
                return null;
            })
            .catch(function() {
                button.disabled = false;
                button.textContent = str('stateretry');
                return null;
            });
    };

    var show = function() {
        if (banner) {
            return;
        }
        banner = document.createElement('section');
        banner.className = 'mm-state mm-state-offline';
        banner.setAttribute('role', 'alert');
        var art = document.createElement('div');
        art.className = 'mm-state-art';
        art.setAttribute('aria-hidden', 'true');
        art.innerHTML = '<span></span><span></span><span></span>';
        var title = document.createElement('h2');
        title.className = 'mm-state-title';
        title.textContent = str('stateofflinetitle');
        var body = document.createElement('p');
        body.className = 'mm-state-body';
        body.textContent = str('stateofflinebody');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary mm-state-button';
        button.textContent = str('stateretry');
        button.addEventListener('click', function() {
            check(button);
        });
        banner.append(art, title, body, button);
        document.body.appendChild(banner);
        document.documentElement.classList.add('mm-offline');
    };

    return {
        init: function() {
            window.addEventListener('offline', show);
            window.addEventListener('online', hide);
            if (navigator.onLine === false) {
                show();
            }
        }
    };
});
