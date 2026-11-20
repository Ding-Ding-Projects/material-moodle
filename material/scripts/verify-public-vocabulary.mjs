#!/usr/bin/env node
// Fail-closed scan of the Material overlay for terms from the maintainer's private conversation vocabulary.
//
// This file holds no term list. The dictionary is read at run time from PRIVATE_VOCABULARY_FILE, or from a sibling
// checkout at ../agent-global-memory/PERSONAL_VOCABULARY.json relative to the repository root. When neither exists
// the scan is skipped with the reason printed and exit code 0, so public contributors without the dictionary are
// not blocked.
//
// Findings print as file:line (or "file: path name") and never print the matched term. Any finding exits 1.
//
// Usage: node material/scripts/verify-public-vocabulary.mjs

import {existsSync, readFileSync, readdirSync, statSync} from 'node:fs';
import {dirname, join, relative, resolve, sep} from 'node:path';
import {fileURLToPath} from 'node:url';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

// The overlay set: everything this fork adds on top of Moodle.
const SCAN_DIRS = ['public/theme/material', 'public/auth/passkey', 'public/local/materialtools', 'public/local/meetups', 'public/local/groupchats', 'material'];
const SCAN_FILES = ['AGENTS.md', 'README.md'];
const WORKFLOW_DIR = '.github/workflows';
const WORKFLOW_PATTERN = /^material-.*\.yml$/;

// Phrases that are documented as safe in public text even though they contain or equal dictionary values.
const ALLOWED_PHRASES = [
    'Ding-Ding-Projects',
    'Ding Ding Projects',
    'Hong Kong Dim Sum',
    'Job Experience Documents',
    'Job Experience Document',
    'Slop Machine',
];

const SKIP_DIRS = new Set(['node_modules', '.git']);
const BINARY_EXTENSIONS = new Set(['.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.icns', '.woff', '.woff2', '.ttf',
    '.otf', '.eot', '.zip', '.gz', '.7z', '.exe', '.dll', '.so', '.dylib', '.node', '.pdf', '.mp4', '.webm', '.mp3',
    '.wav', '.nupkg', '.asar', '.bin']);

/**
 * Locate the dictionary, or return null with a reason.
 *
 * @returns {{file: string|null, reason: string}}
 */
function locateDictionary() {
    const fromEnv = process.env.PRIVATE_VOCABULARY_FILE;
    if (fromEnv && existsSync(resolve(fromEnv))) {
        return {file: resolve(fromEnv), reason: ''};
    }
    const sibling = resolve(repoRoot, '..', 'agent-global-memory', 'PERSONAL_VOCABULARY.json');
    if (existsSync(sibling)) {
        return {file: sibling, reason: ''};
    }
    const envNote = fromEnv ? 'PRIVATE_VOCABULARY_FILE names a file that does not exist'
        : 'PRIVATE_VOCABULARY_FILE is not set';
    return {file: null, reason: `${envNote} and no sibling agent-global-memory checkout was found.`};
}

/**
 * Escape text for a regular expression.
 *
 * @param {string} text
 * @returns {string}
 */
function escapeRegex(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Build one matcher per distinct dictionary value.
 *
 * @param {object} dictionary
 * @returns {RegExp[]}
 */
function buildMatchers(dictionary) {
    if (!dictionary || typeof dictionary !== 'object' || !dictionary.entries || typeof dictionary.entries !== 'object') {
        throw new Error('The dictionary does not have an "entries" object.');
    }
    const allowed = new Set(ALLOWED_PHRASES.map((p) => p.toLowerCase()));
    const values = new Set();
    for (const value of Object.values(dictionary.entries)) {
        if (typeof value !== 'string') {
            continue;
        }
        const trimmed = value.trim();
        if (trimmed && !allowed.has(trimmed.toLowerCase())) {
            values.add(trimmed);
        }
    }
    const matchers = [];
    for (const value of values) {
        if (/\s/.test(value)) {
            // Multi-word values match as substrings, also when written in kebab-case or snake_case.
            matchers.push(new RegExp(escapeRegex(value).replace(/\s+/g, '[\\s_-]+'), 'iu'));
        } else {
            // Single-word values match as whole words only.
            matchers.push(new RegExp(`(?<![\\p{L}\\p{N}_])${escapeRegex(value)}(?![\\p{L}\\p{N}_])`, 'iu'));
        }
    }
    return matchers;
}

/**
 * Replace allowed phrases with spaces of the same length so they cannot match, keeping columns and lines intact.
 *
 * @param {string} text
 * @returns {string}
 */
function maskAllowed(text) {
    let out = text;
    for (const phrase of ALLOWED_PHRASES) {
        out = out.replace(new RegExp(escapeRegex(phrase), 'giu'), (m) => ' '.repeat(m.length));
    }
    return out;
}

/**
 * Does the text hit any matcher?
 *
 * @param {string} text
 * @param {RegExp[]} matchers
 * @returns {boolean}
 */
function hits(text, matchers) {
    const masked = maskAllowed(text);
    return matchers.some((re) => re.test(masked));
}

/**
 * Is this file binary?
 *
 * @param {string} file
 * @param {Buffer} buffer
 * @returns {boolean}
 */
function isBinary(file, buffer) {
    const dot = file.lastIndexOf('.');
    if (dot !== -1 && BINARY_EXTENSIONS.has(file.slice(dot).toLowerCase())) {
        return true;
    }
    return buffer.subarray(0, 8192).includes(0);
}

/**
 * Every file in the overlay set.
 *
 * @returns {string[]} Absolute paths.
 */
function overlayFiles() {
    const out = [];
    const walk = (dir) => {
        for (const name of readdirSync(dir)) {
            if (SKIP_DIRS.has(name)) {
                continue;
            }
            const full = join(dir, name);
            const info = statSync(full, {throwIfNoEntry: false});
            if (!info) {
                continue;
            }
            if (info.isDirectory()) {
                walk(full);
            } else if (info.isFile()) {
                out.push(full);
            }
        }
    };
    for (const dir of SCAN_DIRS) {
        const full = join(repoRoot, dir);
        if (existsSync(full)) {
            walk(full);
        }
    }
    for (const file of SCAN_FILES) {
        const full = join(repoRoot, file);
        if (existsSync(full)) {
            out.push(full);
        }
    }
    const workflows = join(repoRoot, WORKFLOW_DIR);
    if (existsSync(workflows)) {
        for (const name of readdirSync(workflows)) {
            if (WORKFLOW_PATTERN.test(name)) {
                out.push(join(workflows, name));
            }
        }
    }
    return out.sort();
}

function main() {
    const located = locateDictionary();
    if (!located.file) {
        console.log(`Public vocabulary scan skipped: ${located.reason}`);
        process.exit(0);
    }
    let matchers;
    try {
        matchers = buildMatchers(JSON.parse(readFileSync(located.file, 'utf8')));
    } catch (e) {
        // A dictionary that exists but cannot be read is a failure, not a skip.
        console.error(`Public vocabulary scan failed: the dictionary could not be read (${e.message}).`);
        process.exit(1);
    }
    if (!matchers.length) {
        console.error('Public vocabulary scan failed: the dictionary has no usable values.');
        process.exit(1);
    }

    const findings = [];
    let scanned = 0;
    let skipped = 0;
    for (const file of overlayFiles()) {
        const rel = relative(repoRoot, file).split(sep).join('/');
        // Check the path as written and with separators turned into spaces, so kebab-case and snake_case names count.
        if (hits(rel, matchers) || hits(rel.replace(/[\\/._-]+/g, ' '), matchers)) {
            findings.push(`${rel}: path name`);
        }
        const buffer = readFileSync(file);
        if (isBinary(file, buffer)) {
            skipped++;
            continue;
        }
        scanned++;
        const lines = buffer.toString('utf8').split(/\r?\n/);
        lines.forEach((line, index) => {
            if (line && hits(line, matchers)) {
                findings.push(`${rel}:${index + 1}`);
            }
        });
    }

    if (findings.length) {
        console.error(`Public vocabulary scan FAILED: ${findings.length} finding(s) in the Material overlay.`);
        console.error('Matched terms are not printed. Rewrite each line in plain professional English.');
        for (const finding of findings) {
            console.error(`  ${finding}`);
        }
        process.exit(1);
    }
    console.log(`Public vocabulary scan passed: ${scanned} text files checked, ${skipped} binary files skipped, `
        + `${matchers.length} terms.`);
}

main();
