#!/usr/bin/env node
// Checks the built site: every internal link and asset resolves to a file, every #fragment exists on its target
// page, tags are balanced (a basic HTML structure check), ids are unique, and the JSON files parse.
// External http(s) links are listed but not fetched. Exit code 1 on any problem.
// Usage: node scripts/check-links.mjs
import {readFileSync, readdirSync, statSync, existsSync} from 'node:fs';
import {dirname, join, relative, resolve, posix} from 'node:path';
import {fileURLToPath} from 'node:url';

const site = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const BASE_PATH = '/material-moodle/';
const SKIP = new Set(['scripts', 'screenshots', 'node_modules']);
const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr']);
const SVG_SELF = /\/>$/;

function walk(dir) {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        if (statSync(full).isDirectory()) {
            return SKIP.has(name) ? [] : walk(full);
        }
        return [full];
    });
}

const files = walk(site);
const pages = files.filter((f) => f.endsWith('.html'));
const problems = [];
const external = new Set();
const ids = new Map();

for (const file of pages) {
    const html = readFileSync(file, 'utf8');
    const found = new Set();
    for (const m of html.matchAll(/\sid="([^"]+)"/g)) {
        if (found.has(m[1])) {
            problems.push(`${relative(site, file)}: duplicate id "${m[1]}"`);
        }
        found.add(m[1]);
    }
    ids.set(file, found);
    // Structure: balanced tags outside script, style, pre and code text.
    const body = html.replace(/<script\b[\s\S]*?<\/script>/g, '').replace(/<style\b[\s\S]*?<\/style>/g, '').replace(/<!--[\s\S]*?-->/g, '');
    const stack = [];
    for (const m of body.matchAll(/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?>/g)) {
        const name = m[2].toLowerCase();
        if (name === '!doctype' || VOID.has(name) || (!m[1] && SVG_SELF.test(m[0]))) {
            continue;
        }
        if (!m[1]) {
            stack.push(name);
        } else if (stack[stack.length - 1] === name) {
            stack.pop();
        } else {
            problems.push(`${relative(site, file)}: </${name}> closes <${stack[stack.length - 1] || 'nothing'}>`);
            const at = stack.lastIndexOf(name);
            if (at >= 0) {
                stack.length = at;
            }
        }
    }
    if (stack.length) {
        problems.push(`${relative(site, file)}: unclosed <${stack.join('>, <')}>`);
    }
}

let checked = 0;
for (const file of pages) {
    const html = readFileSync(file, 'utf8');
    const refs = [...html.matchAll(/\s(?:href|src)="([^"]+)"/g)].map((m) => m[1]);
    for (const raw of refs) {
        const ref = raw.replace(/&amp;/g, '&');
        if (/^(https?:)?\/\//.test(ref)) {
            external.add(ref);
            continue;
        }
        if (/^(mailto|tel|data|javascript):/.test(ref)) {
            continue;
        }
        checked++;
        const [pathPart, frag] = ref.split('#');
        let target;
        if (!pathPart) {
            target = file;
        } else if (pathPart.startsWith(BASE_PATH)) {
            target = join(site, pathPart.slice(BASE_PATH.length));
        } else if (pathPart.startsWith('/')) {
            problems.push(`${relative(site, file)}: absolute link outside the site base: ${ref}`);
            continue;
        } else {
            target = resolve(dirname(file), decodeURIComponent(pathPart));
        }
        if (existsSync(target) && statSync(target).isDirectory()) {
            target = join(target, 'index.html');
        }
        if (!existsSync(target)) {
            problems.push(`${relative(site, file)}: missing ${ref}`);
            continue;
        }
        if (frag && target.endsWith('.html') && !(ids.get(target) || new Set()).has(decodeURIComponent(frag))) {
            problems.push(`${relative(site, file)}: missing anchor ${ref}`);
        }
    }
}

// Search index entries must point at real pages and anchors.
const indexJs = readFileSync(join(site, 'assets/js/search-index.js'), 'utf8');
const index = JSON.parse(indexJs.match(/window\.MM_INDEX = (\[[\s\S]*\]);/)[1]);
for (const entry of index) {
    const [p, frag] = entry.u.split('#');
    const target = join(site, p);
    checked++;
    if (!existsSync(target)) {
        problems.push(`search index: missing ${entry.u}`);
    } else if (frag && !ids.get(target).has(frag)) {
        problems.push(`search index: missing anchor ${entry.u}`);
    }
}
// Sitemap locations must exist.
const base = 'https://ding-ding-projects.github.io/material-moodle/';
for (const m of readFileSync(join(site, 'sitemap.xml'), 'utf8').matchAll(/<loc>([^<]+)<\/loc>/g)) {
    const p = m[1].slice(base.length) || 'index.html';
    checked++;
    if (!existsSync(join(site, p))) {
        problems.push(`sitemap.xml: missing ${m[1]}`);
    }
}
for (const json of files.filter((f) => f.endsWith('.json'))) {
    try {
        JSON.parse(readFileSync(json, 'utf8'));
    } catch (e) {
        problems.push(`${relative(site, json)}: ${e.message}`);
    }
}

console.log(`Pages: ${pages.length}. Internal references checked: ${checked}. External links (not fetched): ${external.size}.`);
if (problems.length) {
    console.log(`Problems (${problems.length}):\n  ${problems.join('\n  ')}`);
    process.exit(1);
}
console.log('All internal links, anchors and assets resolve; tags are balanced; JSON parses.');
