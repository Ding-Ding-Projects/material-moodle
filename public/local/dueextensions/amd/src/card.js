// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
/**
 * Student due date extension card: dialog, server-decided outcome and its dice, coin or wheel animation.
 *
 * @module     local_dueextensions/card
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {
    var NS = 'http://www.w3.org/2000/svg';
    var PIPS = {1: [[50, 50]], 2: [[28, 28], [72, 72]], 3: [[28, 28], [50, 50], [72, 72]],
        4: [[28, 28], [72, 28], [28, 72], [72, 72]], 5: [[28, 28], [72, 28], [50, 50], [28, 72], [72, 72]],
        6: [[28, 25], [72, 25], [28, 50], [72, 50], [28, 75], [72, 75]]};
    var SEGMENT_TONES = ['--md-sys-color-primary-container', '--md-sys-color-secondary-container',
        '--md-sys-color-tertiary-container', '--md-sys-color-surface-container-highest'];

    function still() {
        return document.documentElement.dataset.mdMotion === 'off' ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function el(name, attrs, svg) {
        var node = svg ? document.createElementNS(NS, name) : document.createElement(name);
        Object.keys(attrs || {}).forEach(function(k) {
            node.setAttribute(k, attrs[k]);
        });
        return node;
    }

    function wait(ms) {
        return new Promise(function(resolve) {
            setTimeout(resolve, still() ? 0 : ms);
        });
    }

    /** Draw a die face: pips up to 6, a number above that. */
    function drawDie(stage, face, sides) {
        stage.textContent = '';
        var svg = el('svg', {viewBox: '0 0 100 100', width: '120', height: '120', 'class': 'mm-ext-die'}, true);
        svg.appendChild(el('rect', {x: 6, y: 6, width: 88, height: 88, rx: 20, 'class': 'mm-ext-die-face'}, true));
        if (sides <= 6 && PIPS[face]) {
            PIPS[face].forEach(function(p) {
                svg.appendChild(el('circle', {cx: p[0], cy: p[1], r: 8, 'class': 'mm-ext-pip'}, true));
            });
        } else {
            var t = el('text', {x: 50, y: 62, 'text-anchor': 'middle', 'class': 'mm-ext-die-number'}, true);
            t.textContent = String(face);
            svg.appendChild(t);
        }
        stage.appendChild(svg);
        return svg;
    }

    function drawCoin(stage, side) {
        stage.textContent = '';
        var coin = el('div', {'class': 'mm-ext-coin', 'data-side': side});
        coin.appendChild(el('span', {'class': 'mm-ext-coin-face'}));
        stage.appendChild(coin);
        return coin;
    }

    function drawWheel(stage, segments) {
        stage.textContent = '';
        var wrap = el('div', {'class': 'mm-ext-wheel-wrap'});
        var svg = el('svg', {viewBox: '-110 -110 220 220', width: '220', height: '220', 'class': 'mm-ext-wheel'}, true);
        var n = segments.length;
        var css = getComputedStyle(document.documentElement);
        segments.forEach(function(s, i) {
            var a0 = (i / n) * 2 * Math.PI - Math.PI / 2;
            var a1 = ((i + 1) / n) * 2 * Math.PI - Math.PI / 2;
            var large = a1 - a0 > Math.PI ? 1 : 0;
            var d = 'M0 0 L' + (100 * Math.cos(a0)) + ' ' + (100 * Math.sin(a0)) +
                ' A100 100 0 ' + large + ' 1 ' + (100 * Math.cos(a1)) + ' ' + (100 * Math.sin(a1)) + ' Z';
            var fill = css.getPropertyValue(SEGMENT_TONES[i % SEGMENT_TONES.length]).trim() || '#DDE1FF';
            svg.appendChild(el('path', {d: d, fill: fill, 'class': 'mm-ext-seg'}, true));
            var mid = (a0 + a1) / 2;
            var label = el('text', {x: 66 * Math.cos(mid), y: 66 * Math.sin(mid) + 5, 'text-anchor': 'middle',
                'class': 'mm-ext-seg-label'}, true);
            label.textContent = s.days > 0 ? s.days + 'd' + (s.penalty > 0 ? ' -' + s.penalty + '%' : '') : '✕';
            svg.appendChild(label);
        });
        svg.appendChild(el('circle', {r: 14, 'class': 'mm-ext-hub'}, true));
        wrap.appendChild(svg);
        wrap.appendChild(el('span', {'class': 'mm-ext-pointer', 'aria-hidden': 'true'}));
        stage.appendChild(wrap);
        return svg;
    }

    function animate(mode, stage, data, outcome) {
        if (mode === 'dice') {
            var sides = outcome.sides || 6;
            var ticks = still() ? 0 : 9;
            var roll = function(i) {
                if (i >= ticks) {
                    drawDie(stage, outcome.face, sides).classList.add('is-landed');
                    return Promise.resolve();
                }
                drawDie(stage, 1 + Math.floor(Math.random() * sides), sides).classList.add('is-tumbling');
                return wait(90 + i * 18).then(function() {
                    return roll(i + 1);
                });
            };
            return roll(0);
        }
        if (mode === 'coin') {
            var coin = drawCoin(stage, outcome.side);
            if (still()) {
                coin.classList.add('is-' + outcome.side);
                return Promise.resolve();
            }
            void coin.offsetWidth;
            coin.classList.add('is-flipping', 'is-' + outcome.side);
            return wait(1600);
        }
        if (mode === 'wheel') {
            var segments = outcome.segments || [];
            var wheel = drawWheel(stage, segments);
            var per = 360 / segments.length;
            // Land with the chosen segment's centre under the pointer at the top.
            var target = 360 * (still() ? 0 : 5) + (360 - (outcome.segment + 0.5) * per);
            wheel.style.transform = 'rotate(0deg)';
            void wheel.getBoundingClientRect();
            wheel.style.transition = still() ? 'none' : 'transform 1.8s cubic-bezier(.12,.6,.1,1)';
            wheel.style.transform = 'rotate(' + target + 'deg)';
            return wait(1900);
        }
        return Promise.resolve();
    }

    function resultText(data, outcome) {
        var c = 'local_dueextensions';
        var parts = [];
        if (data.mode === 'dice') {
            parts.push(Str.get_string('rolled', c, outcome.face));
        } else if (data.mode === 'coin') {
            parts.push(Str.get_string(outcome.side === 'golden' ? 'golden' : (outcome.side === 'heads' ? 'heads' : 'tails'), c));
        }
        if (data.granted) {
            parts.push(Str.get_string('resultgranted', c, {days: data.days, due: data.newduetext}));
            if (data.penalty > 0) {
                parts.push(Str.get_string('resultpenalty', c, data.penalty));
            }
        } else {
            parts.push(Str.get_string(outcome.zeroed ? 'resultdeniedzero' : (data.counted ? 'resultdeniedcounted' : 'resultdenied'), c));
        }
        return Promise.all(parts).then(function(texts) {
            return texts.join(' ');
        });
    }

    /** The golden coin's prize: a dim sum from the public catalog, with both of its names. */
    function showDish(card, dish) {
        var figure = card.querySelector('[data-region="dish"]');
        if (!figure || !dish) {
            return;
        }
        var img = figure.querySelector('img');
        if (dish.image) {
            img.src = dish.image;
            img.alt = dish.alt || dish.en;
            img.hidden = false;
        } else {
            img.hidden = true;
        }
        figure.querySelector('figcaption').textContent = dish.yue ? dish.en + ' · ' + dish.yue : dish.en;
        figure.hidden = false;
    }

    return {init: function(card) {
        if (!card || card.dataset.ready) {
            return;
        }
        card.dataset.ready = 'true';
        // Put the card above the submission status so it sits with the dates it changes.
        var anchor = document.querySelector('.submissionstatustable, .activity-dates, #region-main [role="main"]');
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(card, anchor.matches('[role="main"]') ? anchor.firstChild : anchor);
        }
        card.hidden = false;
        var dialog = card.querySelector('[data-region="dialog"]');
        var stage = card.querySelector('[data-region="stage"]');
        var result = card.querySelector('[data-region="result"]');
        var go = dialog.querySelector('[data-action="go"]');
        var close = dialog.querySelector('[data-action="close"]');
        var mode = card.dataset.mode;
        var changed = false;
        var used = false;

        var preview = function() {
            if (mode === 'dice') {
                drawDie(stage, 1, parseInt(card.dataset.sides, 10) || 6);
            } else if (mode === 'coin') {
                drawCoin(stage, 'heads').classList.add('is-heads');
            } else if (mode === 'wheel') {
                drawWheel(stage, JSON.parse(card.dataset.wheel || '[]'));
            }
        };

        var open = card.querySelector('[data-action="open"]');
        if (open) {
            open.addEventListener('click', function() {
                if (!used) {
                    preview();
                    result.textContent = '';
                }
                dialog.showModal();
                go.focus();
            });
        }
        close.addEventListener('click', function() {
            dialog.close();
        });
        dialog.addEventListener('close', function() {
            if (changed) {
                window.location.reload();
            }
        });
        go.addEventListener('click', function() {
            if (used) {
                dialog.close();
                return;
            }
            go.disabled = true;
            close.disabled = true;
            Ajax.call([{methodname: 'local_dueextensions_request', args: {cmid: parseInt(card.dataset.cmid, 10)}}])[0]
                .then(function(data) {
                    used = true;
                    var outcome = JSON.parse(data.outcome || '{}');
                    return animate(data.mode, stage, data, outcome).then(function() {
                        return resultText(data, outcome);
                    }).then(function(text) {
                        result.textContent = text;
                        result.dataset.granted = data.granted ? 'true' : 'false';
                        showDish(card, outcome.dish);
                        changed = data.granted || data.counted;
                        return Str.get_string('done', 'local_dueextensions');
                    }).then(function(done) {
                        go.textContent = done;
                        go.disabled = false;
                        close.hidden = true;
                        go.focus();
                        return done;
                    });
                })
                .catch(function(err) {
                    go.disabled = false;
                    close.disabled = false;
                    Notification.exception(err);
                });
        });
    }};
});
