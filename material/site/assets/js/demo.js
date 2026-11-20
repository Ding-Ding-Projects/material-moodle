/* Demo page: sign-in with a simulated passkey prompt, an inline command palette over sample Moodle data,
 * the deploy success celebration and the error screen. Everything runs locally; nothing is sent anywhere. */
(function() {
    'use strict';

    function ready(fn) {
        if (window.MM) {
            fn(window.MM);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                fn(window.MM);
            });
        }
    }

    ready(function(MM) {
        var $ = function(id) {
            return document.getElementById(id);
        };
        var colours = ['var(--p)', 'var(--ok)', 'var(--tc)', 'var(--err)', 'var(--sc)', 'var(--onpc)'];

        function burst(host, count, spread) {
            host.textContent = '';
            if (MM.motion() !== 'expressive') {
                return;
            }
            ['', ' two'].forEach(function(extra) {
                var ring = document.createElement('span');
                ring.className = 'ring' + extra;
                host.appendChild(ring);
            });
            for (var i = 0; i < count; i++) {
                var ang = (i / count) * Math.PI * 2 + (i % 3) * 0.2;
                var dist = spread + (i * 37) % spread;
                var c = document.createElement('span');
                c.className = 'conf';
                c.style.width = (8 + (i % 4) * 3) + 'px';
                c.style.height = (i % 2 ? 18 : 10) + 'px';
                c.style.borderRadius = i % 3 === 0 ? '50%' : '3px';
                c.style.background = colours[i % colours.length];
                c.style.setProperty('--dx', Math.round(Math.cos(ang) * dist) + 'px');
                c.style.setProperty('--dy', Math.round(Math.sin(ang) * dist + 160) + 'px');
                c.style.setProperty('--r', (i * 97 % 720) + 'deg');
                c.style.animationDelay = (i % 6) * 0.05 + 's';
                host.appendChild(c);
            }
        }
        function replay(node, cls) {
            node.classList.remove(cls);
            void node.offsetWidth;
            node.classList.add(cls);
        }
        function message(host, kind, text) {
            host.textContent = '';
            var div = document.createElement('div');
            div.className = 'msg ' + kind + (MM.motion() === 'off' ? '' : ' pop');
            div.textContent = text;
            host.appendChild(div);
        }

        // ---------- Sign-in ----------
        var pk = $('demo-passkey');
        var pkLabel = $('demo-passkey-label');
        var signin = $('demo-signin');
        var msg = $('demo-msg');
        var timer = null;
        pk.addEventListener('click', function() {
            clearTimeout(timer);
            msg.textContent = '';
            pk.classList.add('is-waiting');
            pk.setAttribute('aria-busy', 'true');
            pkLabel.textContent = MM.t('demo.pkWait', 'Follow the prompt on your device…');
            timer = setTimeout(function() {
                pk.classList.remove('is-waiting');
                pk.removeAttribute('aria-busy');
                pkLabel.textContent = MM.t('demo.pkDone', 'Signed in');
                message(msg, 'ok', MM.t('demo.pkOk', 'Signed in. Taking you there…'));
                timer = setTimeout(function() {
                    pkLabel.textContent = MM.t('demo.pk', 'Sign in with a passkey');
                }, 4000);
            }, 1400);
        });
        $('demo-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = e.target;
            if (!form.username.value.trim() || !form.password.value) {
                message(msg, 'err', MM.t('demo.badLogin', 'Invalid login, please try again. Type a username and any password.'));
                if (MM.motion() !== 'off') {
                    msg.firstChild.animate([
                        {transform: 'none'}, {transform: 'translateX(-14px)'}, {transform: 'translateX(12px)'},
                        {transform: 'translateX(-8px)'}, {transform: 'translateX(4px)'}, {transform: 'none'}
                    ], {duration: MM.motion() === 'expressive' ? 600 : 1, easing: 'cubic-bezier(.36,.07,.19,.97)'});
                }
                (form.username.value.trim() ? form.password : form.username).focus();
                return;
            }
            message(msg, 'ok', MM.t('demo.pkOk', 'Signed in. Taking you there…'));
        });

        // ---------- Inline command palette ----------
        var sample = [
            {kind: 'Course', label: 'Introduction to Biology', where: 'Science'},
            {kind: 'Activity', label: 'Intro quiz: cells', where: 'Introduction to Biology'},
            {kind: 'Course', label: 'Statistics 101', where: 'Mathematics'},
            {kind: 'Activity', label: 'Biology quiz 2', where: 'Introduction to Biology'},
            {kind: 'Course', label: 'World History 1900-2000', where: 'Humanities'},
            {kind: 'Activity', label: 'Essay 3: The 1960s', where: 'World History 1900-2000'},
            {kind: 'Page', label: 'Passkeys', where: 'Preferences'},
            {kind: 'Page', label: 'Dashboard', where: 'Navigation'},
            {kind: 'Setting', label: 'Theme seed colour', where: 'Site administration'},
            {kind: 'Setting', label: 'Celebration level', where: 'Site administration'},
            {kind: 'Setting', label: 'Custom vocabulary', where: 'Material tools'},
            {kind: 'Search', label: 'Search all courses', where: 'Course search'}
        ];
        var b = MM.createBuilder($('demo-palette'), {
            items: function() {
                return sample;
            },
            onPick: function(item) {
                MM.toast(MM.t('demo.wouldOpen', 'In Moodle this opens:') + ' ' + item.label);
            },
            placeholder: 'Search courses, pages and settings', placeholderKey: 'demo.paletteSearch',
            label: 'Search sample courses, pages and settings', storeKey: 'mm.demo', headingTag: 'h3', emptyLimit: 12, kbd: true
        });
        b.setQuery('^Intro');

        // ---------- Celebration ----------
        var stage = $('demo-celebrate');
        var fx = stage.querySelector('.fx');
        function celebrate() {
            burst(fx, 40, 260);
            replay(stage, 'is-celebrating');
        }
        $('demo-replay').addEventListener('click', celebrate);
        var seen = false;
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function(entries, obs) {
                if (entries[0].isIntersecting && !seen) {
                    seen = true;
                    celebrate();
                    obs.disconnect();
                }
            }, {threshold: 0.4}).observe(stage);
        }

        // ---------- Error screen ----------
        var err = $('demo-error');
        var errMsg = $('demo-error-msg');
        function shake() {
            errMsg.textContent = '';
            replay(err, 'is-shaking');
        }
        $('demo-shake').addEventListener('click', shake);
        $('demo-retry').addEventListener('click', function() {
            shake();
            message(errMsg, 'err', MM.t('demo.retryFail', 'Step 4 failed again: the tunnel certificate is still missing. Run the guided login first.'));
        });
        $('demo-quick').addEventListener('click', function() {
            message(errMsg, 'ok', MM.t('demo.quickOk', 'Quick tunnel started: https://bright-otter-lake.trycloudflare.com'));
            stage.scrollIntoView({behavior: MM.prefersMotion() ? 'smooth' : 'auto', block: 'center'});
            setTimeout(celebrate, MM.prefersMotion() ? 500 : 0);
        });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function(entries, obs) {
                if (entries[0].isIntersecting) {
                    shake();
                    obs.disconnect();
                }
            }, {threshold: 0.4}).observe(err);
        }
        document.addEventListener('mm:language', function() {
            if (!pk.classList.contains('is-waiting')) {
                pkLabel.textContent = MM.t('demo.pk', 'Sign in with a passkey');
            }
        });
    });
})();
