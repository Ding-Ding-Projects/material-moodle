#!/usr/bin/env node
// Prints the release metadata for a workflow run: version, unique tag and dim sum code name.
//
// Usage: node material/release/meta.mjs --run-number <n> [--run-attempt <n>] [--github-output <file>]
//
// The version is read from $plugin->release in public/theme/material/version.php. The code name is
// names[runNumber % names.length] from dim-sum-names.json, so the same run number always gets the same name.
// Without --github-output the values are printed as key=value lines.

import { readFileSync, appendFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..');

export function readVersion(versionFile = join(root, 'public/theme/material/version.php')) {
  const source = readFileSync(versionFile, 'utf8');
  const match = source.match(/\$plugin->release\s*=\s*['"]([^'"]+)['"]/);
  if (!match) {
    throw new Error(`No $plugin->release found in ${versionFile}`);
  }
  const version = match[1].trim();
  if (!/^[0-9A-Za-z][0-9A-Za-z.+-]*$/.test(version)) {
    throw new Error(`Release "${version}" cannot be used in a tag name`);
  }
  return version;
}

export function codeName(runNumber, namesFile = join(here, 'dim-sum-names.json')) {
  const { names } = JSON.parse(readFileSync(namesFile, 'utf8'));
  if (!Array.isArray(names) || names.length === 0) {
    throw new Error(`${namesFile} has no names`);
  }
  return names[((runNumber % names.length) + names.length) % names.length];
}

export function releaseTag(version, runNumber, runAttempt = 1) {
  // A re-run of a failed workflow keeps its run number, so later attempts get a suffix to stay unique.
  const base = `material-v${version}-${runNumber}`;
  return runAttempt > 1 ? `${base}-attempt${runAttempt}` : base;
}

function argValue(args, name) {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : undefined;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const runNumber = Number.parseInt(argValue(args, '--run-number') ?? '', 10);
  const runAttempt = Number.parseInt(argValue(args, '--run-attempt') ?? '1', 10);
  if (!Number.isInteger(runNumber) || runNumber < 0) {
    console.error('Usage: meta.mjs --run-number <n> [--run-attempt <n>] [--github-output <file>]');
    process.exit(2);
  }
  const version = readVersion();
  const values = {
    version,
    tag: releaseTag(version, runNumber, Number.isInteger(runAttempt) ? runAttempt : 1),
    codename: codeName(runNumber),
  };
  const lines = Object.entries(values).map(([key, value]) => `${key}=${value}`).join('\n') + '\n';
  const output = argValue(args, '--github-output');
  if (output) {
    appendFileSync(output, lines);
  }
  process.stdout.write(lines);
}
