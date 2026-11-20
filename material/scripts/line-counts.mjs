#!/usr/bin/env node
// Counts lines per language in the Material overlay (everything this fork adds on top of Moodle) and prints a
// Markdown table for release notes.
//
// Usage: node material/scripts/line-counts.mjs [--json]
//
// Counted: public/theme/material, public/auth/passkey, public/local/materialtools, material/, AGENTS.md and
// .github/workflows/material-*.yml.
// Excluded: node_modules, lock files, binary files (fonts, images), and generated AMD builds (amd/build), which are
// reported separately so hand-written lines are never inflated.

import {existsSync, readFileSync, readdirSync, statSync} from 'node:fs';
import {dirname, extname, join, relative, resolve, sep, basename} from 'node:path';
import {fileURLToPath} from 'node:url';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SCAN_DIRS = ['public/theme/material', 'public/auth/passkey', 'public/local/materialtools', 'material'];
const SCAN_FILES = ['AGENTS.md'];
const WORKFLOW_DIR = '.github/workflows';
const WORKFLOW_PATTERN = /^material-.*\.yml$/;
const SKIP_DIRS = new Set(['node_modules', '.git', 'dist', 'out']);
const SKIP_FILES = new Set(['package-lock.json', 'npm-shrinkwrap.json', 'yarn.lock', 'pnpm-lock.yaml', 'composer.lock']);

// Extension (or exact file name) => [language, kind]. Kind is Source, Styles and markup, Tests, Docs or Config.
const LANGUAGES = {
    '.php': ['PHP', 'Source'],
    '.js': ['JavaScript', 'Source'],
    '.mjs': ['JavaScript', 'Source'],
    '.cjs': ['JavaScript', 'Source'],
    '.ts': ['TypeScript', 'Source'],
    '.sh': ['Shell', 'Source'],
    '.ps1': ['PowerShell', 'Source'],
    '.bat': ['Batch', 'Source'],
    '.scss': ['SCSS', 'Styles and markup'],
    '.css': ['CSS', 'Styles and markup'],
    '.html': ['HTML', 'Styles and markup'],
    '.mustache': ['Mustache', 'Styles and markup'],
    '.svg': ['SVG', 'Styles and markup'],
    '.xml': ['XML', 'Config'],
    '.json': ['JSON', 'Config'],
    '.yml': ['YAML', 'Config'],
    '.yaml': ['YAML', 'Config'],
    '.ini': ['INI', 'Config'],
    '.conf': ['Apache config', 'Config'],
    'Dockerfile': ['Dockerfile', 'Config'],
    '.md': ['Markdown', 'Docs'],
    '.txt': ['Text', 'Docs'],
};

/**
 * Language and kind for a file, or null when it is not counted.
 *
 * @param {string} rel
 * @returns {[string, string]|null}
 */
function classify(rel) {
    const name = basename(rel);
    const entry = LANGUAGES[name] || LANGUAGES[extname(name).toLowerCase()];
    if (!entry) {
        return null;
    }
    const isTest = /(^|\/)(tests?|__tests__)\//.test(rel) || /\.(test|spec)\.[cm]?[jt]s$/.test(name)
        || /_test\.php$/.test(name);
    return isTest && entry[1] === 'Source' ? [entry[0], 'Tests'] : entry;
}

/**
 * Every candidate file, relative to the repository root.
 *
 * @returns {string[]}
 */
function files() {
    const out = [];
    const walk = (dir) => {
        for (const name of readdirSync(dir)) {
            if (SKIP_DIRS.has(name)) {
                continue;
            }
            const full = join(dir, name);
            const info = statSync(full, {throwIfNoEntry: false});
            if (info?.isDirectory()) {
                walk(full);
            } else if (info?.isFile()) {
                out.push(relative(repoRoot, full).split(sep).join('/'));
            }
        }
    };
    for (const dir of SCAN_DIRS) {
        if (existsSync(join(repoRoot, dir))) {
            walk(join(repoRoot, dir));
        }
    }
    for (const file of SCAN_FILES) {
        if (existsSync(join(repoRoot, file))) {
            out.push(file);
        }
    }
    if (existsSync(join(repoRoot, WORKFLOW_DIR))) {
        for (const name of readdirSync(join(repoRoot, WORKFLOW_DIR))) {
            if (WORKFLOW_PATTERN.test(name)) {
                out.push(`${WORKFLOW_DIR}/${name}`);
            }
        }
    }
    return out.sort();
}

function main() {
    const rows = new Map();
    const generated = {files: 0, lines: 0};
    const excluded = {lock: 0, other: 0};
    for (const rel of files()) {
        if (SKIP_FILES.has(basename(rel))) {
            excluded.lock++;
            continue;
        }
        const kind = classify(rel);
        if (!kind) {
            excluded.other++;
            continue;
        }
        const text = readFileSync(join(repoRoot, rel), 'utf8');
        if (text.includes('\u0000')) {
            excluded.other++;
            continue;
        }
        const lines = text.length ? text.replace(/\r?\n$/, '').split(/\r?\n/) : [];
        if (/\/amd\/build\//.test(rel)) {
            generated.files++;
            generated.lines += lines.length;
            continue;
        }
        const key = `${kind[1]}\u0000${kind[0]}`;
        const row = rows.get(key) || {kind: kind[1], language: kind[0], files: 0, lines: 0, nonblank: 0};
        row.files++;
        row.lines += lines.length;
        row.nonblank += lines.filter((l) => l.trim() !== '').length;
        rows.set(key, row);
    }
    const order = ['Source', 'Tests', 'Styles and markup', 'Config', 'Docs'];
    const sorted = [...rows.values()].sort((a, b) =>
        order.indexOf(a.kind) - order.indexOf(b.kind) || b.lines - a.lines || a.language.localeCompare(b.language));
    const total = sorted.reduce((t, r) => ({files: t.files + r.files, lines: t.lines + r.lines,
        nonblank: t.nonblank + r.nonblank}), {files: 0, lines: 0, nonblank: 0});

    if (process.argv.includes('--json')) {
        console.log(JSON.stringify({rows: sorted, total, generated, excluded}, null, 2));
        return;
    }
    const fmt = (n) => n.toLocaleString('en-US');
    console.log('| Kind | Language | Files | Lines | Non-blank lines |');
    console.log('| --- | --- | ---: | ---: | ---: |');
    for (const r of sorted) {
        console.log(`| ${r.kind} | ${r.language} | ${fmt(r.files)} | ${fmt(r.lines)} | ${fmt(r.nonblank)} |`);
    }
    console.log(`| **Total** | | **${fmt(total.files)}** | **${fmt(total.lines)}** | **${fmt(total.nonblank)}** |`);
    console.log('');
    console.log(`Counted with \`node material/scripts/line-counts.mjs\` over the Material overlay only (upstream Moodle `
        + 'is not counted). Generated AMD builds are excluded from the table: '
        + `${fmt(generated.files)} files, ${fmt(generated.lines)} lines. `
        + `Also excluded: ${fmt(excluded.lock)} lock files, ${fmt(excluded.other)} binary or unclassified files, `
        + 'and node_modules.');
}

main();
