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
    # Moodle redirects to its configured external URL. The local listener has
    # answered at that point; a published port may be unreachable from inside.
    exec php -r '
        $ctx = stream_context_create(["http" => ["follow_location" => 0, "timeout" => 3]]);
        @file_get_contents("http://127.0.0.1/login/index.php", false, $ctx);
        exit(preg_match("~^HTTP/\S+ [23]\d{2}\b~", $http_response_header[0] ?? "") ? 0 : 1);
    '
fi
