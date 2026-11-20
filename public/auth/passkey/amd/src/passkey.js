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
 * Passkey sign-in and registration in the browser.
 *
 * @module     auth_passkey/passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var ICON = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 11a2 2 0 0 0-2 2c0 3-1 5-2 6"/>' +
        '<path d="M14 13c0 3-.5 5.5-1.5 7.5"/><path d="M6.5 16.5C7 15 7 14 7 13a5 5 0 0 1 10 0c0 1 0 2.5-.3 4"/>' +
        '<path d="M4.5 9.5A8.5 8.5 0 0 1 19.5 9.5"/></svg>';

    /**
     * Turn the server's "=?BINARY?B?...?=" strings into ArrayBuffers, in place.
     *
     * @param {Object} obj
     * @returns {Object}
     */
    function toBuffers(obj) {
        Object.keys(obj).forEach(function(key) {
            var v = obj[key];
            if (typeof v === 'string' && v.indexOf('=?BINARY?B?') === 0 && v.slice(-2) === '?=') {
                var bin = atob(v.substring(11, v.length - 2));
                var bytes = new Uint8Array(bin.length);
                for (var i = 0; i < bin.length; i++) {
                    bytes[i] = bin.charCodeAt(i);
                }
                obj[key] = bytes.buffer;
            } else if (v && typeof v === 'object') {
                toBuffers(v);
            }
        });
        return obj;
    }

    /**
     * ArrayBuffer to base64.
     *
     * @param {ArrayBuffer} buf
     * @returns {string}
     */
    function b64(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i++) {
            bin += String.fromCharCode(bytes[i]);
        }
        return btoa(bin);
    }

    /**
     * Call the JSON endpoint.
     *
     * @param {Object} cfg
     * @param {string} action
     * @param {Object} [body]
     * @returns {Promise<Object>}
     */
    function call(cfg, action, body) {
        return fetch(cfg.endpoint + '?action=' + action + '&sesskey=' + encodeURIComponent(cfg.sesskey), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body || {})
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            if (data && data.success === false) {
                throw new Error(data.error || '');
            }
            return data;
        });
    }

    /**
     * Is the browser able to use passkeys?
     *
     * @returns {boolean}
     */
    function supported() {
        return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.get);
    }

    /**
     * Celebrate with the Material theme when it is the active theme.
     *
     * @param {string} kind success|error
     * @param {HTMLElement} el
     */
    function celebrate(kind, el) {
        require(['theme_material/celebrate'], function(C) {
            if (kind === 'success') {
                C.burst(el);
            } else {
                C.failure(el);
            }
        }, function() {
            // Another theme is active: no celebration.
        });
    }

    /**
     * Show a message in a status region.
     *
     * @param {HTMLElement} region
     * @param {string} kind success|danger|info
     * @param {string} text
     */
    function status(region, kind, text) {
        if (!region) {
            return;
        }
        region.textContent = '';
        if (!text) {
            return;
        }
        var div = document.createElement('div');
        div.className = 'alert alert-' + kind + ' mb-3';
        div.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
        div.dataset.mdCelebrated = '1';
        div.textContent = text;
        region.appendChild(div);
    }

    /**
     * Error text for a failed ceremony.
     *
     * @param {Error} e
     * @param {Object} s
     * @returns {string}
     */
    function errorText(e, s) {
        if (e && (e.name === 'NotAllowedError' || e.name === 'AbortError')) {
            return s.cancelled;
        }
        return (e && e.message) || s.errorgeneric;
    }

    /**
     * Run the sign-in ceremony.
     *
     * @param {Object} cfg
     * @param {HTMLButtonElement} button
     * @param {HTMLElement} region
     */
    function login(cfg, button, region) {
        var s = cfg.strings || {};
        if (!supported()) {
            status(region, 'danger', s.errorunsupported);
            return;
        }
        var label = button.querySelector('span').textContent;
        button.disabled = true;
        button.classList.add('is-waiting');
        button.querySelector('span').textContent = s.waiting;
        status(region, 'info', '');
        call(cfg, 'loginoptions').then(function(options) {
            return navigator.credentials.get(toBuffers(options));
        }).then(function(cred) {
            return call(cfg, 'login', {
                id: b64(cred.rawId),
                clientDataJSON: b64(cred.response.clientDataJSON),
                authenticatorData: b64(cred.response.authenticatorData),
                signature: b64(cred.response.signature),
                wantsurl: cfg.wantsurl || ''
            });
        }).then(function(result) {
            button.classList.remove('is-waiting');
            button.querySelector('span').textContent = s.signedin;
            status(region, 'success', s.signedin);
            celebrate('success', button);
            setTimeout(function() {
                window.location.href = result.redirect;
            }, 900);
            return null;
        }).catch(function(e) {
            button.disabled = false;
            button.classList.remove('is-waiting');
            button.querySelector('span').textContent = label;
            status(region, 'danger', errorText(e, s));
            celebrate('error', button);
        });
    }

    /**
     * Run the registration ceremony.
     *
     * @param {Object} cfg
     * @param {string} name
     * @returns {Promise<Object>}
     */
    function register(cfg, name) {
        var s = cfg.strings || {};
        if (!supported() || !navigator.credentials.create) {
            return Promise.reject(new Error(s.errorunsupported));
        }
        return call(cfg, 'registeroptions').then(function(options) {
            return navigator.credentials.create(toBuffers(options));
        }).then(function(cred) {
            return call(cfg, 'register', {
                clientDataJSON: b64(cred.response.clientDataJSON),
                attestationObject: b64(cred.response.attestationObject),
                transports: cred.response.getTransports ? cred.response.getTransports() : [],
                name: name || ''
            });
        });
    }

    /**
     * Build the big passkey button.
     *
     * @param {string} label
     * @returns {HTMLButtonElement}
     */
    function bigButton(label) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'md-passkey-btn btn btn-primary';
        b.innerHTML = ICON + '<span></span>';
        b.querySelector('span').textContent = label;
        return b;
    }

    /**
     * Wire an add button with a status region and optional name field.
     *
     * @param {Object} cfg
     * @param {HTMLElement} scope
     * @param {Function} done
     */
    function wireAdd(cfg, scope, done) {
        var s = cfg.strings || {};
        var button = scope.querySelector('[data-action="auth-passkey-add"]');
        var region = scope.querySelector('[data-region="auth-passkey-status"]');
        var nameField = scope.querySelector('#auth-passkey-name');
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            var label = button.textContent;
            button.disabled = true;
            button.classList.add('is-waiting');
            button.textContent = s.adding;
            register(cfg, nameField ? nameField.value : '').then(function(result) {
                status(region, 'success', s.added);
                celebrate('success', button);
                done(result);
                return null;
            }).catch(function(e) {
                status(region, 'danger', errorText(e, s));
                celebrate('error', button);
            }).then(function() {
                button.disabled = false;
                button.classList.remove('is-waiting');
                button.textContent = label;
                return null;
            });
        });
    }

    return {
        /**
         * Inline button on the standard sign-in page.
         *
         * @param {Object} cfg
         */
        initLoginForm: function(cfg) {
            var form = document.querySelector('.loginform');
            if (!form || form.querySelector('.md-passkey-btn') || !supported()) {
                return;
            }
            var s = cfg.strings || {};
            var wrap = document.createElement('div');
            wrap.className = 'auth-passkey-inline mb-3';
            var region = document.createElement('div');
            region.setAttribute('aria-live', 'polite');
            var button = bigButton(s.signinwithpasskey);
            var sep = document.createElement('div');
            sep.className = 'login-separator my-4';
            sep.textContent = s.orusername;
            wrap.appendChild(button);
            wrap.appendChild(region);
            wrap.appendChild(sep);
            var anchor = form.querySelector('form.login-form') || form.querySelector('.login-identityproviders');
            if (anchor) {
                anchor.parentNode.insertBefore(wrap, anchor);
            } else {
                form.appendChild(wrap);
            }
            form.querySelectorAll('a.login-identityprovider-btn[href*="/auth/passkey/"]').forEach(function(a) {
                a.hidden = true;
            });
            var params = new URLSearchParams(window.location.search);
            cfg.wantsurl = cfg.wantsurl || '';
            if (params.get('wantsurl')) {
                cfg.wantsurl = params.get('wantsurl');
            }
            button.addEventListener('click', function() {
                login(cfg, button, region);
            });
        },

        /**
         * The standalone passkey sign-in page.
         *
         * @param {Object} cfg
         */
        initLoginPage: function(cfg) {
            var slot = document.querySelector('[data-region="auth-passkey-slot"]');
            if (!slot) {
                return;
            }
            var region = document.createElement('div');
            region.setAttribute('aria-live', 'polite');
            var button = bigButton((cfg.strings || {}).signinwithpasskey);
            slot.appendChild(region);
            slot.appendChild(button);
            button.addEventListener('click', function() {
                login(cfg, button, region);
            });
            if (!supported()) {
                status(region, 'danger', (cfg.strings || {}).errorunsupported);
                button.disabled = true;
            }
        },

        /**
         * The passkey manager page.
         *
         * @param {Object} cfg
         */
        initManager: function(cfg) {
            var scope = document.querySelector('[data-region="auth-passkey-manage"]');
            if (scope) {
                wireAdd(cfg, scope, function() {
                    setTimeout(function() {
                        window.location.href = cfg.returnurl;
                    }, 1400);
                });
            }
        },

        /**
         * The optional step after the first sign-in.
         *
         * @param {Object} cfg
         */
        initWelcome: function(cfg) {
            var scope = document.querySelector('[data-region="auth-passkey-welcome"]');
            if (!scope) {
                return;
            }
            wireAdd(cfg, scope, function() {
                var step = scope.querySelector('[data-region="auth-passkey-final-step"]');
                if (step) {
                    step.classList.add('is-done');
                }
                setTimeout(function() {
                    window.location.href = cfg.returnurl;
                }, 1600);
            });
        }
    };
});
