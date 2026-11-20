#!/bin/sh
# Stand-in for the docker CLI used by the engine tests. It records every call and emulates
# just enough of docker, docker compose, busybox and cloudflared for the deployer's commands.
echo "$*" >> "$FAKE_DOCKER_LOG"
case "$1" in
  info) exit 0 ;;
  --version) echo "Docker version 28.5.1, build fake"; exit 0 ;;
  rm) exit 0 ;;
  compose)
    shift
    while [ "$1" = "-f" ]; do shift 2; done
    case "$1" in
      version) echo "Docker Compose version v2.40.0" ;;
      pull)
        if [ -n "$FAKE_PULL_FAIL" ] && [ "$2" = "moodle" ]; then echo "manifest unknown" >&2; exit 1; fi
        echo " $2 Pulled" ;;
      up)
        if [ -n "$FAKE_UP_FAIL_ONCE" ] && [ ! -f "$FAKE_UP_FAIL_ONCE" ]; then
          : > "$FAKE_UP_FAIL_ONCE"; echo "Bind for 0.0.0.0:8080 failed: port is already allocated" >&2; exit 1
        fi
        echo " Container material-moodle-moodle-1  Started" ;;
      build) echo " moodle Built" ;;
      logs)
        for last; do :; done
        case "$last" in
          moodle) echo "moodle-1  | Installing Moodle. This takes a few minutes the first time."; echo "moodle-1  | MATERIAL_MOODLE_INSTALLED" ;;
          quicktunnel) echo "quicktunnel-1  | INF |  https://fake-quick-link.trycloudflare.com  |" ;;
          namedtunnel|tunnel) echo "INF Registered tunnel connection connIndex=0" ;;
        esac ;;
      ps) echo "moodle running" ;;
    esac
    exit 0 ;;
  run)
    shift
    while :; do
      case "$1" in
        --rm) shift ;;
        --name) shift 2 ;;
        -v) src="${2%%:*}"; dst="${2#*:}"; shift 2 ;;
        *) break ;;
      esac
    done
    image="$1"; shift
    case "$image" in
      busybox*)
        script=$(printf '%s' "$3" | sed "s#$dst#$src#g")
        exec sh -c "$script" ;;
      cloudflare/cloudflared*)
        case "$1 $2" in
          "tunnel login")
            echo "Please open the following URL and log in with your Cloudflare account:" >&2
            echo "https://dash.cloudflare.com/argotunnel?aud=&callback=https%3A%2F%2Flogin.cloudflareaccess.org%2Ffake" >&2
            sleep 1; echo "fake-cert" > "$src/cert.pem"; echo "You have successfully logged in." >&2 ;;
          "tunnel create")
            echo '{}' > "$src/6ff42ae2-765d-4adf-8112-31c55c1551ef.json"
            echo "Created tunnel material-moodle with id 6ff42ae2-765d-4adf-8112-31c55c1551ef" ;;
          "tunnel route") echo "Added CNAME $6 which will route to this tunnel" ;;
        esac
        exit 0 ;;
    esac ;;
esac
exit 0
