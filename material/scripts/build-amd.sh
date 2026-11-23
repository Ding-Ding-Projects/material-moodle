#!/usr/bin/env bash
# Copies the hand-written AMD sources of the Material plugins into amd/build with explicit module names,
# so they load without the full Moodle grunt toolchain. Run from anywhere.
set -euo pipefail
shopt -s nullglob
root="$(cd "$(dirname "$0")/../.." && pwd)/public"
for plugin in theme/material auth/passkey local/materialtools local/meetups local/groupchats local/dueextensions local/bathroompass; do
  component="$(basename "$(dirname "$plugin")")_$(basename "$plugin")"
  [ "$plugin" = "theme/material" ] && component="theme_material"
  src="$root/$plugin/amd/src"; out="$root/$plugin/amd/build"
  [ -d "$src" ] || continue
  mkdir -p "$out"
  for f in "$src"/*.js; do
    name="$(basename "$f" .js)"
    node --check "$f"
    sed "0,/^define(\[/s//define('$component\/$name', [/" "$f" > "$out/$name.min.js"
    grep -q "define('$component/$name'" "$out/$name.min.js" || { echo "No define() found in $f" >&2; exit 1; }
  done
done
echo "AMD modules built."
