/* Material Moodle website: preferences, language, tab strip, command palette with regex builder, site search,
 * right-click menu, copy buttons and teleport highlighting. Plain browser JavaScript, no dependencies. */
(function() {
    'use strict';

    var doc = document;
    var html = doc.documentElement;
    var root = html.getAttribute('data-root') || '';
    var site = window.MM_SITE || {tabs: []};
    var reducedQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : {matches: false};
    var darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : {matches: false};

    // ---------- Storage (every access guarded: private modes and blocked storage must not break the page) ----------
    function load(key, fallback) {
        try {
            var raw = window.localStorage.getItem(key);
            return raw === null ? fallback : JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }
    function save(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Storage unavailable: the choice lasts for this page only.
        }
    }

    // ---------- Strings ----------
    var strings = {en: {}, yue: {}};
    var prefs = Object.assign({theme: 'auto', motion: 'expressive', lang: 'en'}, load('mm.prefs', {}));

    function t(key, fallback) {
        var en = strings.en[key] || fallback || key;
        if (prefs.lang === 'yue') {
            return strings.yue[key] || en;
        }
        return en;
    }
    function fmt(s, arg) {
        return String(s).replace('{0}', arg);
    }

    function applyLanguage() {
        html.lang = prefs.lang === 'yue' ? 'zh-HK' : 'en';
        doc.querySelectorAll('[data-i18n]').forEach(function(el) {
            var key = el.getAttribute('data-i18n');
            if (!el.hasAttribute('data-en')) {
                el.setAttribute('data-en', el.textContent);
            }
            var en = strings.en[key] || el.getAttribute('data-en');
            var yue = strings.yue[key];
            if (prefs.lang === 'yue' && yue) {
                el.textContent = yue;
                el.lang = 'zh-HK';
            } else if (prefs.lang === 'bi' && yue && yue !== en) {
                el.textContent = en;
                var span = doc.createElement('span');
                span.className = 'bi';
                span.lang = 'zh-HK';
                span.textContent = yue;
                el.appendChild(span);
                el.removeAttribute('lang');
            } else {
                el.textContent = en;
                el.removeAttribute('lang');
            }
        });
        doc.querySelectorAll('[data-i18n-attr]').forEach(function(el) {
            var parts = el.getAttribute('data-i18n-attr').split(':');
            var attr = parts[0];
            var key = parts[1];
            var store = 'data-en-' + attr;
            if (!el.hasAttribute(store)) {
                el.setAttribute(store, el.getAttribute(attr) || '');
            }
            var arg = el.getAttribute('data-i18n-arg');
            var en = strings.en[key] ? fmt(strings.en[key], arg && tabLabel(arg, 'en')) : el.getAttribute(store);
            var yue = strings.yue[key] ? fmt(strings.yue[key], arg && tabLabel(arg, 'yue')) : '';
            el.setAttribute(attr, prefs.lang === 'yue' && yue ? yue : prefs.lang === 'bi' && yue ? en + ' / ' + yue : en);
        });
        rebuildCatalog();
        doc.dispatchEvent(new CustomEvent('mm:language'));
    }
    function tabLabel(label, lang) {
        var tab = site.tabs.filter(function(x) {
            return x.label === label;
        })[0];
        if (!tab) {
            return label;
        }
        return lang === 'yue' ? (strings.yue[tab.key] || label) : label;
    }
    function fetchStrings(lang) {
        if (strings[lang] && strings[lang].__loaded) {
            return Promise.resolve();
        }
        if (!window.fetch) {
            return Promise.resolve();
        }
        return fetch(root + 'i18n/' + lang + '.json').then(function(r) {
            return r.ok ? r.json() : {};
        }).then(function(data) {
            strings[lang] = Object.assign(data, {__loaded: true});
        }).catch(function() {
            strings[lang] = {__loaded: true};
        });
    }

    // ---------- Preferences ----------
    function effectiveMotion() {
        if (prefs.motion === 'off') {
            return 'off';
        }
        return reducedQuery.matches ? 'calm' : prefs.motion;
    }
    function applyPrefs() {
        if (prefs.theme === 'light' || prefs.theme === 'dark') {
            html.dataset.theme = prefs.theme;
        } else {
            delete html.dataset.theme;
        }
        html.dataset.motion = effectiveMotion();
        doc.querySelectorAll('[data-set]').forEach(function(b) {
            b.setAttribute('aria-pressed', String(prefs[b.getAttribute('data-set')] === b.getAttribute('data-value')));
        });
        doc.dispatchEvent(new CustomEvent('mm:prefs'));
    }
    function setPref(name, value, quiet) {
        prefs[name] = value;
        save('mm.prefs', prefs);
        applyPrefs();
        if (name === 'lang') {
            Promise.all([fetchStrings('en'), value === 'en' ? null : fetchStrings('yue')]).then(applyLanguage);
        }
        if (!quiet) {
            toast(t('ui.saved', 'Saved') + ': ' + labelFor(name, value));
        }
    }
    function labelFor(name, value) {
        var map = {auto: 'set.auto', light: 'set.light', dark: 'set.dark', expressive: 'set.expressive', calm: 'set.calm', off: 'set.off', bi: 'set.bi'};
        if (name === 'lang') {
            return value === 'en' ? 'English' : value === 'yue' ? '廣東話' : t('set.bi', 'Bilingual');
        }
        return t(map[value], value);
    }
    function isDark() {
        return prefs.theme === 'dark' || (prefs.theme === 'auto' && darkQuery.matches);
    }
    function toggleTheme() {
        setPref('theme', isDark() ? 'light' : 'dark');
    }
    function prefersMotion() {
        return effectiveMotion() === 'expressive';
    }

    // ---------- Toast ----------
    var toastTimer = null;
    function toast(text) {
        var el = doc.querySelector('.toast');
        if (!el) {
            return;
        }
        el.textContent = text;
        el.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            el.classList.remove('is-on');
        }, 2200);
    }

    // ---------- Clipboard ----------
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).catch(function() {
                return legacyCopy(text);
            });
        }
        return Promise.resolve(legacyCopy(text));
    }
    function legacyCopy(text) {
        var area = doc.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        doc.body.appendChild(area);
        area.select();
        try {
            doc.execCommand('copy');
        } catch (e) {
            // Nothing else to try.
        }
        area.remove();
    }

    // ---------- Panels (settings and tab catalog) ----------
    function closePanels(except) {
        doc.querySelectorAll('[aria-controls]').forEach(function(btn) {
            var panel = doc.getElementById(btn.getAttribute('aria-controls'));
            if (panel && panel.classList.contains('panel') && panel !== except) {
                panel.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    function togglePanel(btn, force) {
        var panel = doc.getElementById(btn.getAttribute('aria-controls'));
        var open = force === undefined ? panel.hidden : force;
        closePanels(open ? panel : null);
        panel.hidden = !open;
        btn.setAttribute('aria-expanded', String(open));
        if (open) {
            var first = panel.querySelector('[aria-pressed="true"], button, a');
            if (first) {
                first.focus();
            }
        }
        return panel;
    }

    // ---------- Tab strip ----------
    // State: "seen" lists every tab the strip has offered; "closed" lists tabs the visitor closed. A tab that was
    // never seen opens automatically (new pages after an update), a closed tab stays closed until reopened.
    var tabState = {seen: load('mm.tabs.seen', []), closed: load('mm.tabs.closed', [])};
    function persistTabs() {
        save('mm.tabs.seen', tabState.seen);
        save('mm.tabs.closed', tabState.closed);
    }
    function initTabs() {
        var current = doc.body.getAttribute('data-tab');
        site.tabs.forEach(function(tab) {
            if (tabState.seen.indexOf(tab.id) < 0) {
                tabState.seen.push(tab.id);
                tabState.closed = tabState.closed.filter(function(id) {
                    return id !== tab.id;
                });
            }
        });
        // Visiting a page opens its tab, like following a link in a browser.
        tabState.closed = tabState.closed.filter(function(id) {
            return id !== current;
        });
        persistTabs();
        renderTabs();
        doc.querySelectorAll('[data-close-tab]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                closeTab(btn.getAttribute('data-close-tab'));
            });
        });
        var cur = doc.querySelector('.tab.is-current');
        if (cur && cur.scrollIntoView) {
            cur.scrollIntoView({block: 'nearest', inline: 'nearest'});
        }
    }
    function renderTabs() {
        doc.querySelectorAll('.tab').forEach(function(li) {
            li.hidden = tabState.closed.indexOf(li.getAttribute('data-tab')) >= 0;
        });
        var anyClosed = tabState.closed.length > 0;
        doc.querySelectorAll('.open-all').forEach(function(b) {
            b.hidden = !anyClosed;
        });
        rebuildCatalog();
    }
    function closeTab(id) {
        var li = doc.querySelector('.tab[data-tab="' + id + '"]');
        var open = site.tabs.filter(function(x) {
            return tabState.closed.indexOf(x.id) < 0 && x.id !== id;
        });
        if (!open.length) {
            toast(t('tab.lastTab', 'The last tab stays open.'));
            return;
        }
        var isCurrent = id === doc.body.getAttribute('data-tab');
        var idx = site.tabs.map(function(x) {
            return x.id;
        }).indexOf(id);
        tabState.closed.push(id);
        persistTabs();
        var finish = function() {
            renderTabs();
            if (isCurrent) {
                // Like a browser: move to the neighbouring open tab.
                var next = site.tabs.slice(idx + 1).concat(site.tabs.slice(0, idx).reverse()).filter(function(x) {
                    return tabState.closed.indexOf(x.id) < 0;
                })[0];
                window.location.href = root + next.href;
            } else {
                var focusTarget = doc.querySelector('.tab.is-current a') || doc.querySelector('.tab-new');
                focusTarget.focus();
                toast(fmt(t('tab.closed', 'Closed {0}. Reopen it from the + button.'), tabLabel(labelOf(id), prefs.lang === 'yue' ? 'yue' : 'en')));
            }
        };
        if (li && prefersMotion()) {
            li.classList.add('is-closing');
            setTimeout(finish, 220);
        } else {
            finish();
        }
    }
    function labelOf(id) {
        var tab = site.tabs.filter(function(x) {
            return x.id === id;
        })[0];
        return tab ? tab.label : id;
    }
    function openTab(id, go) {
        tabState.closed = tabState.closed.filter(function(x) {
            return x !== id;
        });
        persistTabs();
        renderTabs();
        var li = doc.querySelector('.tab[data-tab="' + id + '"]');
        if (li && prefersMotion()) {
            li.classList.add('is-opening');
            setTimeout(function() {
                li.classList.remove('is-opening');
            }, 400);
        }
        if (go) {
            var tab = site.tabs.filter(function(x) {
                return x.id === id;
            })[0];
            window.location.href = root + tab.href;
        }
    }
    function openAllTabs() {
        var reopened = tabState.closed.slice();
        tabState.closed = [];
        persistTabs();
        renderTabs();
        reopened.forEach(function(id) {
            var li = doc.querySelector('.tab[data-tab="' + id + '"]');
            if (li && prefersMotion()) {
                li.classList.add('is-opening');
            }
        });
        toast(t('tab.allOpen', 'Every tab is open.'));
    }
    function rebuildCatalog() {
        var list = doc.querySelector('.catalog-list');
        if (!list) {
            return;
        }
        list.textContent = '';
        site.tabs.forEach(function(tab) {
            var closed = tabState.closed.indexOf(tab.id) >= 0;
            var li = doc.createElement('li');
            var b = doc.createElement('button');
            b.type = 'button';
            var name = doc.createElement('span');
            name.textContent = prefs.lang === 'yue' && strings.yue[tab.key] ? strings.yue[tab.key] : tab.label;
            var state = doc.createElement('span');
            state.className = 'state' + (closed ? ' closed' : '');
            state.textContent = closed ? t('tab.reopen', 'Reopen') : t('tab.open', 'Open');
            b.appendChild(name);
            b.appendChild(state);
            b.addEventListener('click', function() {
                openTab(tab.id, true);
            });
            li.appendChild(b);
            list.appendChild(li);
        });
    }

    // ---------- Regex builder (shared by the palette, the site search bar and the demo) ----------
    var BLOCKS = [
        ['^', 'blk.start', 'Starts with'], ['$', 'blk.end', 'Ends with'], ['.*', 'blk.any', 'Anything'],
        ['\\d', 'blk.digit', 'Digit'], ['[A-Za-z]', 'blk.letter', 'Letter'], ['\\b', 'blk.edge', 'Word edge'],
        ['\\s', 'blk.space', 'Space'], ['+', 'blk.more', 'One or more'], ['?', 'blk.optional', 'Optional'],
        ['|', 'blk.either', 'Either']
    ];
    var builderCount = 0;

    function el(tag, attrs, text) {
        var node = doc.createElement(tag);
        Object.keys(attrs || {}).forEach(function(k) {
            if (k === 'className') {
                node.className = attrs[k];
            } else {
                node.setAttribute(k, attrs[k]);
            }
        });
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    /**
     * Build a search field with a regex builder.
     * opts: items() -> [{label, where, kind, url?, action?, live?}], onPick(item), placeholder, collapsible,
     *       heading level, storeKey, popup (results hidden until typing), autofocus.
     */
    function createBuilder(host, opts) {
        var id = 'mmb' + (++builderCount);
        var settings = Object.assign({regex: true, ignoreCase: true}, load(opts.storeKey || 'mm.search', {}));
        var state = {active: 0, results: []};

        var wrap = el('div', {className: 'mm-search'});
        var field = el('div', {className: 'mm-field'});
        field.insertAdjacentHTML('beforeend', '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>');
        var label = el('label', {className: 'sr-only', for: id + '-q'}, opts.label || 'Search');
        var input = el('input', {
            id: id + '-q', type: 'search', role: 'combobox', autocomplete: 'off', spellcheck: 'false',
            'aria-autocomplete': 'list', 'aria-controls': id + '-list', 'aria-expanded': 'false',
            'aria-describedby': id + '-count', placeholder: opts.placeholder || 'Search'
        });
        field.appendChild(label);
        field.appendChild(input);
        var toggle = null;
        if (opts.collapsible) {
            toggle = el('button', {type: 'button', className: 'builder-toggle', 'aria-expanded': 'false', 'aria-controls': id + '-builder'});
            field.appendChild(toggle);
        } else if (opts.kbd) {
            field.appendChild(el('kbd', {}, 'Ctrl Shift F'));
        }
        wrap.appendChild(field);

        var builder = el('section', {className: 'mm-builder', id: id + '-builder', 'aria-labelledby': id + '-bh'});
        var head = el('div', {className: 'mm-builder-head'});
        var bh = el(opts.headingTag || 'h2', {id: id + '-bh'});
        head.appendChild(bh);
        var toggles = el('div', {className: 'mm-toggles'});
        var regexBtn = el('button', {type: 'button', className: 'mm-toggle'});
        var caseBtn = el('button', {type: 'button', className: 'mm-toggle'});
        var clearBtn = el('button', {type: 'button', className: 'btn-text'});
        toggles.appendChild(regexBtn);
        toggles.appendChild(caseBtn);
        toggles.appendChild(clearBtn);
        head.appendChild(toggles);
        builder.appendChild(head);
        var blocks = el('div', {className: 'mm-blocks', role: 'group'});
        var blockButtons = BLOCKS.map(function(b) {
            var btn = el('button', {type: 'button', className: 'mm-block'});
            btn.appendChild(el('code', {}, b[0]));
            btn.appendChild(el('span', {}, b[2]));
            btn.addEventListener('click', function() {
                insert(b[0]);
            });
            blocks.appendChild(btn);
            return btn;
        });
        builder.appendChild(blocks);
        var pattern = el('div', {className: 'mm-pattern'});
        builder.appendChild(pattern);
        if (opts.collapsible) {
            builder.hidden = true;
        }
        wrap.appendChild(builder);

        var list = el('ul', {className: 'mm-results', id: id + '-list', role: 'listbox'});
        var count = el('p', {className: 'sr-only', id: id + '-count', 'aria-live': 'polite'});
        if (opts.popup) {
            list.hidden = true;
        }
        wrap.appendChild(count);
        wrap.appendChild(list);
        host.appendChild(wrap);

        function texts() {
            input.placeholder = t(opts.placeholderKey, opts.placeholder);
            label.textContent = t(opts.placeholderKey, opts.placeholder);
            bh.textContent = t('rx.title', 'Regex builder');
            regexBtn.textContent = t('rx.regex', 'Regex');
            caseBtn.textContent = t('rx.case', 'Ignore case');
            clearBtn.textContent = t('rx.clear', 'Clear');
            blocks.setAttribute('aria-label', t('rx.blocks', 'Pattern blocks'));
            if (toggle) {
                toggle.textContent = t('rx.title', 'Regex builder');
            }
            BLOCKS.forEach(function(b, i) {
                blockButtons[i].lastChild.textContent = t(b[1], b[2]);
                blockButtons[i].setAttribute('aria-label', t('rx.add', 'Add') + ' ' + t(b[1], b[2]) + ' (' + b[0] + ')');
            });
            regexBtn.setAttribute('aria-pressed', String(settings.regex));
            caseBtn.setAttribute('aria-pressed', String(settings.ignoreCase));
            update();
        }

        function insert(code) {
            if (!settings.regex) {
                settings.regex = true;
                regexBtn.setAttribute('aria-pressed', 'true');
                persist();
            }
            var start = input.selectionStart === null ? input.value.length : input.selectionStart;
            var end = input.selectionEnd === null ? input.value.length : input.selectionEnd;
            input.value = input.value.slice(0, start) + code + input.value.slice(end);
            input.focus();
            input.setSelectionRange(start + code.length, start + code.length);
            update();
        }
        function persist() {
            save(opts.storeKey || 'mm.search', {regex: settings.regex, ignoreCase: settings.ignoreCase});
        }
        function compile() {
            var q = input.value;
            if (!q) {
                return {re: null, valid: true};
            }
            var source = settings.regex ? q : q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            try {
                return {re: new RegExp(source, settings.ignoreCase ? 'i' : ''), valid: true};
            } catch (e) {
                return {re: null, valid: false};
            }
        }
        function tokens(q) {
            var out = [];
            var codes = BLOCKS.map(function(b) {
                return b[0];
            }).sort(function(a, b) {
                return b.length - a.length;
            });
            var i = 0;
            var text = '';
            while (i < q.length) {
                var hit = settings.regex && codes.filter(function(c) {
                    return q.substr(i, c.length) === c;
                })[0];
                if (hit) {
                    if (text) {
                        out.push(text);
                        text = '';
                    }
                    out.push(hit);
                    i += hit.length;
                } else {
                    text += q[i++];
                }
            }
            if (text) {
                out.push(text);
            }
            return out;
        }
        function highlight(node, text, re) {
            node.textContent = '';
            var m = re && re.exec(text);
            if (!m || !m[0]) {
                node.textContent = text;
                return;
            }
            node.appendChild(doc.createTextNode(text.slice(0, m.index)));
            node.appendChild(el('mark', {}, m[0]));
            node.appendChild(doc.createTextNode(text.slice(m.index + m[0].length)));
        }
        function update() {
            var c = compile();
            field.classList.toggle('is-invalid', !c.valid);
            input.setAttribute('aria-invalid', String(!c.valid));
            pattern.textContent = '';
            pattern.appendChild(el('strong', {}, t('rx.pattern', 'Pattern')));
            tokens(input.value).forEach(function(tok) {
                pattern.appendChild(el('span', {className: 'mm-tok'}, tok));
            });
            var all = opts.items();
            var results;
            if (!c.valid) {
                results = [];
            } else if (!c.re) {
                results = opts.popup ? [] : all.slice(0, opts.emptyLimit || 8);
            } else {
                results = all.filter(function(item) {
                    return c.re.test(item.label) || (item.alt && c.re.test(item.alt)) || (item.where && c.re.test(item.where));
                });
            }
            state.results = results.slice(0, 40);
            state.active = Math.min(state.active, Math.max(0, state.results.length - 1));
            var countText = !c.valid ? t('rx.invalid', 'Pattern is incomplete') : state.results.length + ' ' + t('rx.matches', 'matches');
            var countSpan = el('span', {className: 'mm-count' + (c.valid ? '' : ' is-invalid')}, countText);
            pattern.appendChild(countSpan);
            count.textContent = countText;
            render(c.re);
        }
        function render(re) {
            list.textContent = '';
            var show = !opts.popup || input.value !== '';
            list.hidden = !show;
            input.setAttribute('aria-expanded', String(show && state.results.length > 0));
            if (!show) {
                input.removeAttribute('aria-activedescendant');
                return;
            }
            if (!state.results.length) {
                var empty = el('li', {className: 'mm-empty', role: 'presentation'},
                    field.classList.contains('is-invalid') ? t('rx.invalidHelp', 'Pattern is incomplete. Finish the group or remove the last block.') : t('rx.none', 'No match. Remove a block or turn on Ignore case.'));
                list.appendChild(empty);
                input.removeAttribute('aria-activedescendant');
                return;
            }
            state.results.forEach(function(item, i) {
                var li = el('li', {role: 'option', id: id + '-o' + i, 'aria-selected': String(i === state.active)});
                li.appendChild(el('span', {className: 'mm-kind'}, t('kind.' + item.kind, item.kind)));
                var lab = el('span', {className: 'mm-label'});
                highlight(lab, item.label, re);
                li.appendChild(lab);
                if (item.live) {
                    var live = el('span', {className: 'mm-live', role: 'group', 'aria-label': item.label});
                    item.live.forEach(function(opt) {
                        var b = el('button', {type: 'button', tabindex: '-1', 'aria-pressed': String(prefs[item.live.name || item.setting] === opt.value)}, opt.label);
                        b.addEventListener('click', function(e) {
                            e.stopPropagation();
                            setPref(item.setting, opt.value);
                            update();
                        });
                        live.appendChild(b);
                    });
                    li.appendChild(live);
                } else if (item.where) {
                    li.appendChild(el('span', {className: 'mm-where'}, item.where));
                }
                li.addEventListener('mousemove', function() {
                    if (state.active !== i) {
                        state.active = i;
                        mark();
                    }
                });
                li.addEventListener('click', function() {
                    opts.onPick(item);
                });
                list.appendChild(li);
            });
            mark();
        }
        function mark() {
            Array.prototype.forEach.call(list.querySelectorAll('[role="option"]'), function(li, i) {
                li.setAttribute('aria-selected', String(i === state.active));
                if (i === state.active) {
                    input.setAttribute('aria-activedescendant', li.id);
                    // Keep the active option visible inside the list without scrolling the page.
                    var lr = li.getBoundingClientRect();
                    var cr = list.getBoundingClientRect();
                    if (lr.top < cr.top) {
                        list.scrollTop -= cr.top - lr.top;
                    } else if (lr.bottom > cr.bottom) {
                        list.scrollTop += lr.bottom - cr.bottom;
                    }
                }
            });
        }

        input.addEventListener('input', function() {
            state.active = 0;
            update();
        });
        input.addEventListener('keydown', function(e) {
            var item = state.results[state.active];
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (state.results.length) {
                    state.active = (state.active + (e.key === 'ArrowDown' ? 1 : state.results.length - 1)) % state.results.length;
                    mark();
                }
            } else if (e.key === 'Enter') {
                if (item) {
                    e.preventDefault();
                    opts.onPick(item);
                }
            } else if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && item && item.live && e.altKey) {
                // Alt+Left/Right changes a setting straight from the results.
                e.preventDefault();
                var values = item.live.map(function(o) {
                    return o.value;
                });
                var at = values.indexOf(prefs[item.setting]);
                setPref(item.setting, values[(at + (e.key === 'ArrowRight' ? 1 : values.length - 1)) % values.length]);
                update();
            } else if (e.key === 'Escape' && opts.popup && input.value) {
                e.preventDefault();
                input.value = '';
                update();
            }
        });
        regexBtn.addEventListener('click', function() {
            settings.regex = !settings.regex;
            regexBtn.setAttribute('aria-pressed', String(settings.regex));
            persist();
            update();
        });
        caseBtn.addEventListener('click', function() {
            settings.ignoreCase = !settings.ignoreCase;
            caseBtn.setAttribute('aria-pressed', String(settings.ignoreCase));
            persist();
            update();
        });
        clearBtn.addEventListener('click', function() {
            input.value = '';
            input.focus();
            update();
        });
        if (toggle) {
            toggle.addEventListener('click', function() {
                builder.hidden = !builder.hidden;
                toggle.setAttribute('aria-expanded', String(!builder.hidden));
            });
        }
        doc.addEventListener('mm:language', texts);
        doc.addEventListener('mm:prefs', function() {
            if (input.value || !opts.popup) {
                update();
            }
        });
        texts();
        return {input: input, update: update, wrap: wrap, list: list, setQuery: function(q) {
            input.value = q;
            update();
        }};
    }

    // ---------- Search index ----------
    function siteItems() {
        var items = (window.MM_INDEX || []).map(function(e) {
            var alt = e.i && strings.yue[e.i] ? strings.yue[e.i] : '';
            return {
                label: prefs.lang === 'yue' && alt ? alt : e.t,
                alt: prefs.lang === 'yue' ? e.t : alt,
                kind: e.k,
                where: e.k === 'Section' ? e.p : '',
                url: root + e.u
            };
        });
        var settingItems = [
            {setting: 'theme', label: t('set.theme', 'Colour mode'), kind: 'Setting', live: [
                {value: 'auto', label: t('set.auto', 'Automatic')}, {value: 'light', label: t('set.light', 'Light')}, {value: 'dark', label: t('set.dark', 'Dark')}]},
            {setting: 'motion', label: t('set.motion', 'Motion'), kind: 'Setting', live: [
                {value: 'expressive', label: t('set.expressive', 'Expressive')}, {value: 'calm', label: t('set.calm', 'Calm')}, {value: 'off', label: t('set.off', 'Off')}]},
            {setting: 'lang', label: t('set.lang', 'Language'), alt: 'Language English Cantonese bilingual 廣東話', kind: 'Setting', live: [
                {value: 'en', label: 'English'}, {value: 'yue', label: '廣東話'}, {value: 'bi', label: t('set.bi', 'Bilingual')}]},
            {label: t('tab.openAll', 'Open every tab'), kind: 'Action', action: openAllTabs},
            {label: t('ui.switchMode', 'Switch colour mode'), alt: 'dark light theme', kind: 'Action', action: toggleTheme}
        ];
        settingItems.forEach(function(s) {
            s.where = t('ui.settings', 'Display settings');
        });
        // Pages first, then settings, then sections.
        var pages = items.filter(function(i) {
            return i.kind !== 'Section';
        });
        var sections = items.filter(function(i) {
            return i.kind === 'Section';
        });
        return pages.concat(settingItems, sections);
    }

    // ---------- Teleport ----------
    function teleport(item) {
        if (item.action) {
            closePalette();
            item.action();
            return;
        }
        if (item.setting) {
            closePalette();
            var btn = doc.querySelector('[data-action="settings"]');
            var panel = togglePanel(btn, true);
            var target = panel.querySelector('#set-' + item.setting);
            flash(target);
            var pressed = target.querySelector('[aria-pressed="true"]') || target.querySelector('button');
            pressed.focus();
            return;
        }
        var url = new URL(item.url, window.location.href);
        if (url.pathname === window.location.pathname) {
            closePalette();
            var node = url.hash ? doc.getElementById(decodeURIComponent(url.hash.slice(1))) : doc.querySelector('main h1');
            if (node) {
                history.replaceState(null, '', url.hash || window.location.pathname);
                land(node);
            }
            return;
        }
        window.location.href = url.href;
    }
    function land(node) {
        node.scrollIntoView({behavior: prefersMotion() ? 'smooth' : 'auto', block: 'start'});
        if (!node.hasAttribute('tabindex')) {
            node.setAttribute('tabindex', '-1');
        }
        node.focus({preventScroll: true});
        flash(node);
    }
    function flash(node) {
        node.classList.remove('is-teleported');
        void node.offsetWidth;
        node.classList.add('is-teleported');
        setTimeout(function() {
            node.classList.remove('is-teleported');
        }, 1900);
    }

    // ---------- Command palette ----------
    var palette = null;
    var paletteBuilder = null;
    var paletteOpener = null;
    function buildPalette() {
        palette = el('dialog', {className: 'palette', 'aria-labelledby': 'palette-title'});
        var sheet = el('div', {className: 'palette-sheet'});
        var title = el('h2', {id: 'palette-title', className: 'sr-only'}, 'Command palette');
        sheet.appendChild(title);
        palette.appendChild(sheet);
        paletteBuilder = createBuilder(sheet, {
            items: siteItems, onPick: teleport, placeholder: 'Search pages, sections and settings',
            placeholderKey: 'ui.paletteSearch', label: 'Search pages, sections and settings', kbd: true, storeKey: 'mm.palette', emptyLimit: 12
        });
        var foot = el('p', {className: 'palette-foot'});
        sheet.appendChild(foot);
        var paint = function() {
            title.textContent = t('ui.palette', 'Command palette');
            foot.textContent = t('ui.paletteHelp', 'Up and Down move · Enter teleports · Alt+Left/Right changes a setting · Esc closes');
        };
        paint();
        doc.addEventListener('mm:language', paint);
        palette.addEventListener('click', function(e) {
            if (e.target === palette) {
                closePalette();
            }
        });
        palette.addEventListener('close', function() {
            if (paletteOpener && paletteOpener.focus) {
                paletteOpener.focus();
            }
        });
        doc.body.appendChild(palette);
    }
    function openPalette(query) {
        if (!palette) {
            buildPalette();
        }
        paletteOpener = doc.activeElement;
        closePanels();
        if (!palette.open) {
            if (palette.showModal) {
                palette.showModal();
            } else {
                palette.setAttribute('open', '');
            }
        }
        paletteBuilder.setQuery(query === undefined ? paletteBuilder.input.value : query);
        paletteBuilder.input.focus();
        paletteBuilder.input.select();
    }
    function closePalette() {
        if (palette && palette.open) {
            if (palette.close) {
                palette.close();
            } else {
                palette.removeAttribute('open');
            }
        }
    }

    // ---------- Site search bar ----------
    function initSiteSearch() {
        var host = doc.querySelector('.sitesearch');
        if (!host) {
            return;
        }
        var b = createBuilder(host, {
            items: siteItems, onPick: teleport, placeholder: 'Search this site', placeholderKey: 'ui.searchSite', label: 'Search this site',
            collapsible: true, popup: true, storeKey: 'mm.sitesearch', headingTag: 'h2'
        });
        doc.addEventListener('click', function(e) {
            if (!host.contains(e.target)) {
                b.list.hidden = true;
                b.input.setAttribute('aria-expanded', 'false');
            }
        });
        b.input.addEventListener('focus', function() {
            if (b.input.value) {
                b.update();
            }
        });
    }

    // ---------- Copy buttons on every code block ----------
    function initCopyButtons(scope) {
        (scope || doc).querySelectorAll('pre').forEach(function(pre) {
            if (pre.querySelector('.copy-btn') || pre.hasAttribute('data-nocopy')) {
                return;
            }
            var btn = el('button', {type: 'button', className: 'copy-btn'});
            var paint = function() {
                btn.textContent = btn.classList.contains('is-copied') ? t('ui.copied', 'Copied') : t('ui.copy', 'Copy');
                btn.setAttribute('aria-label', btn.textContent + ': ' + (pre.getAttribute('aria-label') || t('ui.codeBlock', 'code block')));
            };
            btn.addEventListener('click', function() {
                var code = pre.querySelector('code') || pre;
                copyText(code.textContent.replace(/\n$/, '')).then(function() {
                    btn.classList.add('is-copied');
                    paint();
                    toast(t('ui.copiedToast', 'Copied to the clipboard'));
                    setTimeout(function() {
                        btn.classList.remove('is-copied');
                        paint();
                    }, 1500);
                });
            });
            paint();
            doc.addEventListener('mm:language', paint);
            pre.appendChild(btn);
        });
    }

    // ---------- Right-click menu ----------
    var menu = null;
    var menuReturn = null;
    var menuScroll = 0;
    function closeMenu() {
        if (menu) {
            menu.remove();
            menu = null;
            if (menuReturn && menuReturn.focus) {
                menuReturn.focus({preventScroll: true});
            }
        }
    }
    function openMenu(e) {
        var target = e.target;
        // Text fields keep the browser menu (cut, paste, spelling); Shift+right-click always gives the browser menu.
        if (e.shiftKey || target.closest('input, textarea, select, [contenteditable="true"]')) {
            return;
        }
        e.preventDefault();
        closeMenu();
        menuReturn = doc.activeElement;
        var items = [];
        var selection = String(window.getSelection ? window.getSelection() : '').trim();
        var link = target.closest('a[href]');
        var pre = target.closest('pre');
        var section = target.closest('h1, h2, h3, section, article, li, p');
        if (selection) {
            items.push({label: t('ctx.copy', 'Copy'), keys: 'Ctrl+C', run: function() {
                copyText(selection).then(function() {
                    toast(t('ui.copiedToast', 'Copied to the clipboard'));
                });
            }});
            items.push({label: fmt(t('ctx.searchFor', 'Search for "{0}"'), selection.slice(0, 24) + (selection.length > 24 ? '…' : '')), keys: '', run: function() {
                openPalette(selection);
            }});
        }
        if (link) {
            items.push({label: t('ctx.open', 'Open link'), keys: 'Enter', run: function() {
                link.click();
            }});
            items.push({label: t('ctx.openTab', 'Open link in new tab'), keys: 'Ctrl+Click', run: function() {
                window.open(link.href, '_blank', 'noopener');
            }});
            items.push({label: t('ctx.copyLink', 'Copy link address'), run: function() {
                copyText(link.href).then(function() {
                    toast(t('ui.copiedToast', 'Copied to the clipboard'));
                });
            }});
        }
        if (pre) {
            items.push({label: t('ctx.copyCode', 'Copy code'), run: function() {
                var code = pre.querySelector('code') || pre;
                copyText(code.textContent).then(function() {
                    toast(t('ui.copiedToast', 'Copied to the clipboard'));
                });
            }});
        }
        var heading = section && (section.matches('h1, h2, h3') ? section : null);
        if (!heading && section) {
            var probe = section;
            while (probe && probe !== doc.body && !heading) {
                var prev = probe.previousElementSibling;
                while (prev && !heading) {
                    heading = prev.matches('h2, h3') && prev.id ? prev : prev.querySelector && prev.querySelector('h2[id], h3[id]');
                    prev = prev.previousElementSibling;
                }
                probe = probe.parentElement;
            }
        }
        if (heading && heading.id) {
            items.push({label: t('ctx.copySection', 'Copy link to this section'), run: function() {
                copyText(window.location.href.split('#')[0] + '#' + heading.id).then(function() {
                    toast(t('ui.copiedToast', 'Copied to the clipboard'));
                });
            }});
        }
        if (items.length) {
            items.push('sep');
        }
        items.push({label: t('ui.palette', 'Command palette'), keys: 'Ctrl+Shift+F', run: function() {
            openPalette();
        }});
        items.push({label: t('ctx.back', 'Back'), keys: 'Alt+←', disabled: history.length < 2, run: function() {
            history.back();
        }});
        items.push({label: t('ctx.reload', 'Reload'), keys: 'F5', run: function() {
            window.location.reload();
        }});
        items.push({label: isDark() ? t('ctx.light', 'Switch to light mode') : t('ctx.dark', 'Switch to dark mode'), run: toggleTheme});
        items.push({label: t('ctx.copyPage', 'Copy page address'), run: function() {
            copyText(window.location.href).then(function() {
                toast(t('ui.copiedToast', 'Copied to the clipboard'));
            });
        }});
        items.push({label: t('ctx.print', 'Print'), keys: 'Ctrl+P', run: function() {
            window.print();
        }});
        items.push({label: t('ctx.top', 'Back to top'), keys: 'Home', run: function() {
            window.scrollTo({top: 0, behavior: prefersMotion() ? 'smooth' : 'auto'});
            doc.getElementById('main').focus({preventScroll: true});
        }});

        menu = el('div', {className: 'ctxmenu', role: 'menu', 'aria-label': t('ctx.menu', 'Page menu')});
        items.forEach(function(item) {
            if (item === 'sep') {
                menu.appendChild(el('hr', {role: 'separator'}));
                return;
            }
            var b = el('button', {type: 'button', role: 'menuitem'});
            b.appendChild(el('span', {}, item.label));
            if (item.keys) {
                b.appendChild(el('span', {className: 'keys'}, item.keys));
            }
            if (item.disabled) {
                b.disabled = true;
            }
            b.addEventListener('click', function() {
                menuReturn = null;
                closeMenu();
                item.run();
            });
            menu.appendChild(b);
        });
        doc.body.appendChild(menu);
        menuScroll = window.scrollY;
        var x = e.clientX;
        var y = e.clientY;
        if (!x && !y) {
            var r = target.getBoundingClientRect();
            x = r.left + 12;
            y = r.top + Math.min(r.height, 24);
        }
        var w = menu.offsetWidth;
        var h = menu.offsetHeight;
        menu.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
        var first = menu.querySelector('button:not(:disabled)');
        if (first) {
            first.focus();
        }
        menu.addEventListener('keydown', function(ev) {
            var buttons = Array.prototype.slice.call(menu.querySelectorAll('button:not(:disabled)'));
            var i = buttons.indexOf(doc.activeElement);
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                buttons[(i + (ev.key === 'ArrowDown' ? 1 : buttons.length - 1)) % buttons.length].focus();
            } else if (ev.key === 'Home' || ev.key === 'End') {
                ev.preventDefault();
                buttons[ev.key === 'Home' ? 0 : buttons.length - 1].focus();
            } else if (ev.key === 'Escape' || ev.key === 'Tab') {
                ev.preventDefault();
                closeMenu();
            }
        });
    }

    // ---------- Wiring ----------
    function onKey(e) {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'F' || e.key === 'f')) {
            e.preventDefault();
            if (palette && palette.open) {
                closePalette();
            } else {
                openPalette();
            }
            return;
        }
        if (e.key === 'Escape') {
            if (menu) {
                closeMenu();
                return;
            }
            var open = doc.querySelector('.panel:not([hidden])');
            if (open) {
                var opener = doc.querySelector('[aria-controls="' + open.id + '"]');
                closePanels();
                if (opener) {
                    opener.focus();
                }
            }
        }
    }

    function init() {
        applyPrefs();
        initTabs();
        initSiteSearch();
        initCopyButtons();
        doc.addEventListener('keydown', onKey);
        doc.addEventListener('contextmenu', openMenu);
        doc.addEventListener('click', function(e) {
            if (menu && !menu.contains(e.target)) {
                closeMenu();
            }
            var actionEl = e.target.closest('[data-action]');
            var setEl = e.target.closest('[data-set]');
            if (setEl) {
                setPref(setEl.getAttribute('data-set'), setEl.getAttribute('data-value'));
                return;
            }
            if (actionEl) {
                var action = actionEl.getAttribute('data-action');
                if (action === 'palette') {
                    openPalette();
                } else if (action === 'theme') {
                    toggleTheme();
                } else if (action === 'settings' || action === 'catalog') {
                    togglePanel(actionEl);
                } else if (action === 'open-all') {
                    openAllTabs();
                    closePanels();
                }
                return;
            }
            if (!e.target.closest('.panel')) {
                closePanels();
            }
        });
        window.addEventListener('scroll', function() {
            // Ignore the small scroll that can follow opening the menu; close on a real scroll.
            if (menu && Math.abs(window.scrollY - menuScroll) > 40) {
                closeMenu();
            }
        }, {passive: true});
        window.addEventListener('resize', closeMenu);
        var onSystem = function() {
            applyPrefs();
        };
        if (reducedQuery.addEventListener) {
            reducedQuery.addEventListener('change', onSystem);
            darkQuery.addEventListener('change', onSystem);
        }
        Promise.all([fetchStrings('en'), prefs.lang === 'en' ? null : fetchStrings('yue')]).then(function() {
            applyLanguage();
            if (paletteBuilder) {
                paletteBuilder.update();
            }
        });
        if (window.location.hash) {
            var node = doc.getElementById(decodeURIComponent(window.location.hash.slice(1)));
            if (node) {
                setTimeout(function() {
                    land(node);
                }, 60);
            }
        }
    }

    // Public helpers for page scripts such as the demo.
    window.MM = {
        createBuilder: createBuilder, t: t, toast: toast, copyText: copyText, prefersMotion: prefersMotion,
        motion: effectiveMotion, openPalette: openPalette, initCopyButtons: initCopyButtons, flash: flash
    };

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
