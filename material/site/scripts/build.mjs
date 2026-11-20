#!/usr/bin/env node
// Builds the static Material Moodle website from scripts/pages/**.html.
//
// Each source file starts with a JSON comment holding page metadata, followed by the page body:
//   <!--{"title": "Deploy", "key": "page.deploy", "description": "...", "tab": "deploy"}-->
// The build wraps every body in the shared layout (header, tab strip, search bar, footer), adds ids to
// headings, and writes the search index, the English string file, sitemap.xml and robots.txt.
// Usage: node scripts/build.mjs

import {readFileSync, writeFileSync, readdirSync, statSync, mkdirSync} from 'node:fs';
import {dirname, join, relative} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const site = join(here, '..');
const sources = join(here, 'pages');

export const SITE = {
    name: 'Material Moodle',
    version: '1.0.0',
    updated: '2026-11-20',
    updatedLabel: '20 November 2026',
    base: 'https://ding-ding-projects.github.io/material-moodle/',
    repo: 'https://github.com/Ding-Ding-Projects/material-moodle',
    releases: 'https://github.com/Ding-Ding-Projects/material-moodle/releases/latest',
};

// Tab strip order. Every page is a tab; feature articles live under the Features tab.
export const TABS = [
    {id: 'home', href: 'index.html', label: 'Home', key: 'tab.home'},
    {id: 'features', href: 'features.html', label: 'Features', key: 'tab.features'},
    {id: 'deploy', href: 'deploy.html', label: 'Deploy', key: 'tab.deploy'},
    {id: 'passkeys', href: 'passkeys.html', label: 'Passkeys', key: 'tab.passkeys'},
    {id: 'students', href: 'students.html', label: 'Students', key: 'tab.students'},
    {id: 'vocabulary', href: 'vocabulary.html', label: 'Vocabulary', key: 'tab.vocabulary'},
    {id: 'docs', href: 'docs.html', label: 'Docs', key: 'tab.docs'},
    {id: 'demo', href: 'demo.html', label: 'Demo', key: 'tab.demo'},
    {id: 'changelog', href: 'changelog.html', label: 'Changelog', key: 'tab.changelog'},
    {id: 'download', href: 'download.html', label: 'Download', key: 'tab.download'},
];

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const strip = (s) => s.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/\s+/g, ' ').trim();
const slug = (s) => strip(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';

function walk(dir) {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        return statSync(full).isDirectory() ? walk(full) : name.endsWith('.html') ? [full] : [];
    });
}

const ICON = {
    search: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    mode: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9Z"/></svg>',
    tune: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
    plus: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    close: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
    cap: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>',
};

function layout(meta, body, root, outPath) {
    const pageUrl = SITE.base + (outPath === 'index.html' ? '' : outPath);
    const title = meta.title === 'Home' ? 'Material Moodle: Moodle, redesigned in Material 3' : `${meta.title} · Material Moodle`;
    const tabs = TABS.map((t) => {
        const current = t.id === meta.tab;
        return `<li class="tab${current ? ' is-current' : ''}" data-tab="${t.id}">` +
            `<a href="${root}${t.href}"${current ? ' aria-current="page"' : ''}><span data-i18n="${t.key}">${t.label}</span></a>` +
            `<button type="button" class="tab-close" data-close-tab="${t.id}" aria-label="Close ${t.label} tab" data-i18n-attr="aria-label:tab.closeLabel" data-i18n-arg="${t.label}">${ICON.close}</button></li>`;
    }).join('');
    const seg = (group, label, key, options) => `<div class="setting" id="set-${group}" tabindex="-1"><span class="setting-label" data-i18n="${key}">${label}</span>` +
        `<div class="segmented" role="group" aria-label="${label}">` +
        options.map(([value, text, tkey]) => `<button type="button" data-set="${group}" data-value="${value}" aria-pressed="false"${tkey ? ` data-i18n="${tkey}"` : ''}>${text}</button>`).join('') +
        '</div></div>';
    return `<!doctype html>
<html lang="en" data-root="${root}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
<meta name="description" content="${esc(meta.description)}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#FFF8F5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#18120D" media="(prefers-color-scheme: dark)">
<link rel="canonical" href="${pageUrl}">
<link rel="icon" href="${root}assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="${root}assets/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="${root}assets/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Material Moodle">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(meta.description)}">
<meta property="og:url" content="${pageUrl}">
<meta property="og:image" content="${SITE.base}assets/og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Material Moodle: Moodle, redesigned in Material 3">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(title)}">
<meta name="twitter:description" content="${esc(meta.description)}">
<meta name="twitter:image" content="${SITE.base}assets/og.png">
<link rel="preload" href="${root}assets/fonts/roboto-flex-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="${root}assets/css/site.css">
<script>(function(){try{var p=JSON.parse(localStorage.getItem('mm.prefs')||'{}');var d=document.documentElement;if(p.theme==='light'||p.theme==='dark'){d.dataset.theme=p.theme;}if(p.motion){d.dataset.motion=p.motion;}}catch(e){}})();</script>
<script src="${root}assets/js/search-index.js" defer></script>
<script src="${root}assets/js/site.js" defer></script>
${meta.scripts ? meta.scripts.map((s) => `<script src="${root}${s}" defer></script>`).join('\n') : ''}
</head>
<body data-page="${esc(meta.key)}" data-tab="${meta.tab}">
<a class="skip" href="#main" data-i18n="ui.skip">Skip to content</a>
<header class="topbar">
  <a class="brand" href="${root}index.html"><span class="brand-mark" aria-hidden="true">${ICON.cap}</span><span class="brand-name">Material Moodle</span></a>
  <span class="build"><span class="chip-version" title="Current release">v${SITE.version}</span><span class="updated"><span data-i18n="ui.updated">Updated</span> <time datetime="${SITE.updated}">${SITE.updatedLabel}</time></span></span>
  <span class="spacer"></span>
  <button type="button" class="search-btn" data-action="palette" aria-keyshortcuts="Control+Shift+F">${ICON.search}<span class="search-btn-text" data-i18n="ui.search">Search</span><kbd>Ctrl Shift F</kbd></button>
  <button type="button" class="icon-btn" data-action="theme" aria-label="Switch colour mode" data-i18n-attr="aria-label:ui.switchMode" title="Switch colour mode">${ICON.mode}</button>
  <button type="button" class="icon-btn" data-action="settings" aria-expanded="false" aria-controls="settings-panel" aria-label="Display settings" data-i18n-attr="aria-label:ui.settings" title="Display settings">${ICON.tune}</button>
  <div class="panel" id="settings-panel" hidden role="dialog" aria-label="Display settings">
    <h2 class="panel-title" data-i18n="ui.settings">Display settings</h2>
    ${seg('theme', 'Colour mode', 'set.theme', [['auto', 'Automatic', 'set.auto'], ['light', 'Light', 'set.light'], ['dark', 'Dark', 'set.dark']])}
    ${seg('motion', 'Motion', 'set.motion', [['expressive', 'Expressive', 'set.expressive'], ['calm', 'Calm', 'set.calm'], ['off', 'Off', 'set.off']])}
    ${seg('lang', 'Language', 'set.lang', [['en', 'English'], ['yue', '廣東話'], ['bi', 'Bilingual', 'set.bi']])}
    <p class="panel-note" data-i18n="set.reducedNote">Devices that ask for reduced motion always get the calm version.</p>
  </div>
</header>
<nav class="tabstrip" aria-label="Pages" data-i18n-attr="aria-label:ui.pages">
  <ul class="tabs">${tabs}</ul>
  <button type="button" class="tab-new" data-action="catalog" aria-expanded="false" aria-controls="tab-catalog" aria-label="New tab: open a page" data-i18n-attr="aria-label:tab.catalog" title="New tab">${ICON.plus}</button>
  <div class="panel catalog" id="tab-catalog" hidden>
    <h2 class="panel-title" data-i18n="tab.catalogTitle">Open a page in a tab</h2>
    <ul class="catalog-list"></ul>
    <button type="button" class="btn-tonal" data-action="open-all" data-i18n="tab.openAll">Open every tab</button>
  </div>
  <button type="button" class="btn-text open-all" data-action="open-all" hidden data-i18n="tab.openAll">Open every tab</button>
</nav>
<div class="sitesearch" role="search" aria-label="Search this site" data-i18n-attr="aria-label:ui.searchSite"></div>
<main id="main" tabindex="-1">
${body}
</main>
<footer class="footer">
  <div class="footer-brand"><span class="brand-mark small" aria-hidden="true">${ICON.cap}</span><div><strong>Material Moodle</strong><br><span>v${SITE.version} · <span data-i18n="ui.updated">Updated</span> <time datetime="${SITE.updated}">${SITE.updatedLabel}</time></span></div></div>
  <ul class="footer-links">
    <li><a href="${SITE.repo}" data-i18n="ui.source">Source code on GitHub</a></li>
    <li><a href="${SITE.releases}" data-i18n="ui.releases">Latest release</a></li>
    <li><a href="${root}changelog.html" data-i18n="tab.changelog">Changelog</a></li>
    <li><a href="${SITE.repo}/blob/main/COPYING.txt" data-i18n="ui.license">Licence: GNU GPL v3 or later</a></li>
  </ul>
  <p class="footer-note" data-i18n="ui.trademark">Material Moodle is an independent, community-made distribution. Moodle is a registered trademark of Moodle Pty Ltd; this project is not affiliated with or endorsed by Moodle Pty Ltd.</p>
</footer>
<div class="toast" role="status" aria-live="polite"></div>
</body>
</html>
`;
}

export function build() {
    const files = walk(sources).sort();
    const index = [];
    const strings = {};
    const pages = [];
    for (const file of files) {
        const rel = relative(sources, file).split('\\').join('/');
        const raw = readFileSync(file, 'utf8');
        const m = raw.match(/^<!--(\{[\s\S]*?\})-->\s*/);
        if (!m) {
            throw new Error(`${rel}: missing metadata comment`);
        }
        const meta = JSON.parse(m[1]);
        const depth = rel.split('/').length - 1;
        const root = meta.root || '../'.repeat(depth);
        const used = new Set();
        let body = raw.slice(m[0].length).replace(/\{\{root\}\}/g, root);
        // Give every h2 and h3 a stable id so the palette can teleport to it.
        body = body.replace(/<h([23])((?:\s[^>]*)?)>([\s\S]*?)<\/h\1>/g, (all, level, attrs, inner) => {
            let id = (attrs.match(/\sid="([^"]+)"/) || [])[1];
            if (!id) {
                id = slug(inner);
                let n = 2;
                while (used.has(id)) {
                    id = `${slug(inner)}-${n++}`;
                }
                attrs += ` id="${id}"`;
            }
            used.add(id);
            const key = (attrs.match(/\sdata-i18n="([^"]+)"/) || [])[1] || '';
            if (rel !== '404.html') index.push({t: strip(inner), u: `${rel}#${id}`, k: 'Section', p: meta.title, i: key});
            return `<h${level}${attrs}>${inner}</h${level}>`;
        });
        // <!--toc--> becomes an "On this page" list of the page's h2 headings.
        const h2s = [...body.matchAll(/<h2[^>]*\sid="([^"]+)"[^>]*>([\s\S]*?)<\/h2>/g)];
        body = body.replace('<!--toc-->', `<p class="aside-title" data-i18n="ui.onThisPage">On this page</p><ul>${
            h2s.map((h) => `<li><a href="#${h[1]}">${strip(h[2])}</a></li>`).join('')}</ul>`);
        if (meta.doc) {
            body = body.replace('<!--doc-->', `<p class="aside-title" data-i18n="ui.fullDocs">Full documentation</p><ul><li><a href="${SITE.repo}/blob/main/material/docs/${meta.doc}">material/docs/${meta.doc}</a></li></ul>`);
        }
        if (rel !== '404.html') index.unshift({t: meta.title, u: rel, k: rel.startsWith('features/') ? 'Article' : 'Page', p: meta.description, i: meta.key + '.title'});
        const html = layout(meta, body, root, rel);
        // Collect English strings from leaf elements marked data-i18n.
        for (const sm of html.matchAll(/<([a-z0-9]+)\b[^>]*\sdata-i18n="([^"]+)"[^>]*>([^<]*)<\/\1>/g)) {
            const text = strip(sm[3]);
            if (text && !(sm[2] in strings)) {
                strings[sm[2]] = text;
            }
        }
        strings[meta.key + '.title'] = meta.title;
        const out = join(site, rel);
        mkdirSync(dirname(out), {recursive: true});
        writeFileSync(out, html);
        pages.push(rel);
    }
    // Strings used only by scripts.
    Object.assign(strings, JSON.parse(readFileSync(join(here, 'ui-strings.json'), 'utf8')));
    writeFileSync(join(site, 'i18n/en.json'), JSON.stringify(strings, null, 2) + '\n');
    const tabsForIndex = TABS.map(({id, href, label, key}) => ({id, href, label, key}));
    writeFileSync(join(site, 'assets/js/search-index.js'),
        '// Generated by scripts/build.mjs. Do not edit by hand.\n' +
        `window.MM_SITE = ${JSON.stringify({version: SITE.version, updated: SITE.updated, tabs: tabsForIndex})};\n` +
        `window.MM_INDEX = ${JSON.stringify(index.sort((a, b) => (a.k === 'Section') - (b.k === 'Section')))};\n`);
    const urls = pages.filter((p) => p !== '404.html').map((p) => `  <url><loc>${SITE.base}${p === 'index.html' ? '' : p}</loc><lastmod>${SITE.updated}</lastmod></url>`);
    writeFileSync(join(site, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}\n</urlset>\n`);
    writeFileSync(join(site, 'robots.txt'), `User-agent: *\nAllow: /\n\nSitemap: ${SITE.base}sitemap.xml\n`);
    writeFileSync(join(site, '.nojekyll'), '');
    // Report strings missing from the Cantonese file.
    const yue = JSON.parse(readFileSync(join(site, 'i18n/yue.json'), 'utf8'));
    const missing = Object.keys(strings).filter((k) => !(k in yue));
    console.log(`Built ${pages.length} pages, ${index.length} index entries, ${Object.keys(strings).length} strings.`);
    if (missing.length) {
        console.log(`Cantonese strings missing (English is shown instead): ${missing.length}\n  ${missing.join('\n  ')}`);
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    build();
}
