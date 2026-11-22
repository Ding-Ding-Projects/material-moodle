#!/usr/bin/env bash
set -eu

# A worker runs CLI tasks and has no HTTP listener. Record an active child or
# the most recent successful pass; a stopped loop eventually loses readiness.
state=/run/material-cron-status
if [[ -f "$state" ]]; then
    read -r phase marker < "$state" || exit 1
    [[ "$marker" =~ ^[0-9]+$ ]] || exit 1
    case "$phase" in
        running) kill -0 "$marker" 2>/dev/null ;;
        idle) (( $(date +%s) - marker < 180 )) ;;
        *) exit 1 ;;
    esac
else
    exec php -r 'exit(@file_get_contents("http://127.0.0.1/login/index.php") === false ? 1 : 0);'
fi
