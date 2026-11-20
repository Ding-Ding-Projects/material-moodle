#!/usr/bin/env bash
# Material Moodle one-command installer.
#
#   bash <(curl -fsSL https://raw.githubusercontent.com/Ding-Ding-Projects/material-moodle/main/material/install.sh)
#
# Asks for everything in a terminal UI (whiptail when available, plain prompts otherwise), generates passwords and
# secrets, writes the .env file for you, starts the Docker stack, waits until Moodle is ready and prints the link.
#
# Non-interactive use (automation, the desktop deployer): pass --yes and set any of these variables first:
#   MM_DIR MM_SITE_NAME MM_SHORT_NAME MM_ADMIN_USER MM_ADMIN_EMAIL MM_ADMIN_PASSWORD MM_PORT MM_ACCESS
#   (local|quick|token|url) MM_TUNNEL_TOKEN MM_PUBLIC_URL MM_SEED_COLOR MM_PASSKEYS (1|0) MM_IMAGE
set -euo pipefail

REPO_RAW="https://raw.githubusercontent.com/Ding-Ding-Projects/material-moodle/main"
YES=0
RECONFIGURE=0
for arg in "$@"; do
  case "$arg" in
    -y|--yes) YES=1 ;;
    --reconfigure) RECONFIGURE=1 ;;
    -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

# ---------------------------------------------------------------- output helpers
if [ -t 1 ]; then
  B=$'\e[1m'; D=$'\e[2m'; R=$'\e[0m'; OR=$'\e[38;5;172m'; GR=$'\e[38;5;71m'; RD=$'\e[38;5;167m'; CY=$'\e[38;5;73m'
else
  B=''; D=''; R=''; OR=''; GR=''; RD=''; CY=''
fi
say()  { printf '%s\n' "$*"; }
step() { printf '\n%s●%s %s%s%s\n' "$OR" "$R" "$B" "$*" "$R"; }
ok()   { printf '  %s✓%s %s\n' "$GR" "$R" "$*"; }
warn() { printf '  %s!%s %s\n' "$OR" "$R" "$*"; }
die()  {
  printf '\n%s%s✗ %s%s\n' "$RD" "$B" "$*" "$R" >&2
  printf '%s  Nothing you entered was lost. Fix the problem above and run the same command again.%s\n' "$D" "$R" >&2
  exit 1
}

TTY=/dev/tty
have_tty() { [ "$YES" = 0 ] && [ -r "$TTY" ] && [ -w "$TTY" ]; }
use_whiptail() { have_tty && command -v whiptail >/dev/null 2>&1 && [ "${MM_PLAIN:-0}" != 1 ]; }
WT_TITLE="Material Moodle setup"

# ask VAR "Question" "default" [secret]
ask() {
  local var="$1" question="$2" default="${3:-}" secret="${4:-}" value
  if [ -n "${!var:-}" ]; then return; fi
  if ! have_tty; then printf -v "$var" '%s' "$default"; return; fi
  if use_whiptail; then
    if [ -n "$secret" ]; then
      value=$(whiptail --title "$WT_TITLE" --passwordbox "$question" 10 72 3>&1 1>&2 2>&3 <"$TTY") || die "Setup cancelled."
    else
      value=$(whiptail --title "$WT_TITLE" --inputbox "$question" 10 72 "$default" 3>&1 1>&2 2>&3 <"$TTY") || die "Setup cancelled."
    fi
  else
    if [ -n "$secret" ]; then
      printf '%s%s%s ' "$B" "$question" "$R" >"$TTY"; IFS= read -rs value <"$TTY"; printf '\n' >"$TTY"
    else
      printf '%s%s%s %s[%s]%s ' "$B" "$question" "$R" "$D" "$default" "$R" >"$TTY"; IFS= read -r value <"$TTY"
    fi
  fi
  [ -z "$value" ] && value="$default"
  printf -v "$var" '%s' "$value"
}

# choose VAR "Question" default tag "label" tag "label" ...
choose() {
  local var="$1" question="$2" default="$3"; shift 3
  if [ -n "${!var:-}" ]; then return; fi
  if ! have_tty; then printf -v "$var" '%s' "$default"; return; fi
  local value
  if use_whiptail; then
    local items=() n=0
    while [ $# -gt 0 ]; do items+=("$1" "$2" "$([ "$1" = "$default" ] && echo ON || echo OFF)"); shift 2; n=$((n + 1)); done
    value=$(whiptail --title "$WT_TITLE" --radiolist "$question" $((n + 9)) 76 "$n" "${items[@]}" 3>&1 1>&2 2>&3 <"$TTY") \
      || die "Setup cancelled."
  else
    printf '%s%s%s\n' "$B" "$question" "$R" >"$TTY"
    local tags=() i=1
    while [ $# -gt 0 ]; do
      printf '  %s) %s%s\n' "$i" "$2" "$([ "$1" = "$default" ] && echo " $D(default)$R")" >"$TTY"
      tags+=("$1"); shift 2; i=$((i + 1))
    done
    printf 'Choose 1-%s: ' "${#tags[@]}" >"$TTY"; IFS= read -r value <"$TTY"
    if [[ "$value" =~ ^[0-9]+$ ]] && [ "$value" -ge 1 ] && [ "$value" -le "${#tags[@]}" ]; then
      value="${tags[$((value - 1))]}"
    else
      value="$default"
    fi
  fi
  printf -v "$var" '%s' "$value"
}

confirm() {
  local question="$1"
  if ! have_tty; then return 0; fi
  if use_whiptail; then whiptail --title "$WT_TITLE" --yesno "$question" 18 76 <"$TTY" >"$TTY" 2>&1; return; fi
  printf '%s\n%sContinue? [Y/n]%s ' "$question" "$B" "$R" >"$TTY"; local a; IFS= read -r a <"$TTY"
  [[ -z "$a" || "$a" =~ ^[Yy] ]]
}

gen_secret() {
  # tr is cut off by head on purpose, so its broken-pipe status is ignored.
  local out
  out=$(set +o pipefail; LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c "${1:-24}")
  printf '%s' "$out"
}
gen_password() {
  local words=(Maple Otter River Cedar Comet Harbor Lantern Meadow Pebble Sparrow Willow Amber Breeze Canyon Ember Hazel)
  printf '%s-%s-%s!' "${words[RANDOM % ${#words[@]}]}" "${words[RANDOM % ${#words[@]}]}" "$((RANDOM % 9000 + 1000))"
}
free_port() {
  local p="${1:-8080}"
  while (exec 3<>"/dev/tcp/127.0.0.1/$p") 2>/dev/null; do p=$((p + 1)); done
  printf '%s' "$p"
}
lan_ip() { hostname -I 2>/dev/null | awk '{print $1}' || true; }
env_get() { [ -f "$1" ] && grep -E "^$2=" "$1" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }
env_quote() { local v="$1"; v="${v//\\/\\\\}"; v="${v//\"/\\\"}"; v="${v//\$/\$\$}"; printf '"%s"' "$v"; }

banner() {
  say ""
  say "${OR}${B}   ╭──────────────────────────────────────────╮${R}"
  say "${OR}${B}   │        Material Moodle  ·  one command        │${R}"
  say "${OR}${B}   ╰──────────────────────────────────────────╯${R}"
  say "${D}   Moodle 6 in Material 3, passkeys, and a public link.${R}"
}

celebrate() {
  [ -t 1 ] || return 0
  local frames=('  ·  ✦  ·  ✧  ·  ✦  ·  ' ' ✧  ·  ✦  ·  ✧  ·  ✦ ' '✦  ✧  ·  ✦  ·  ✧  ·  ✦' ' ·  ✦  ✧  ·  ✦  ✧  · ')
  local colours=("$OR" "$GR" "$CY" "$OR")
  for i in 1 2 3 4 5 6 7 8; do
    printf '\r   %s%s%s' "${colours[$((i % 4))]}" "${frames[$((i % 4))]}" "$R"
    sleep 0.09
  done
  printf '\r%60s\r' ''
}

# ---------------------------------------------------------------- 1. Docker
banner
step "Checking Docker"
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"
if ! command -v docker >/dev/null 2>&1; then
  if confirm "Docker is not installed. Install it now with Docker's official script (get.docker.com)?"; then
    curl -fsSL https://get.docker.com | $SUDO sh || die "Docker could not be installed."
    ok "Docker installed"
  else
    die "Docker is required. Install it, then run this command again."
  fi
fi
DOCKER="docker"
if ! docker info >/dev/null 2>&1; then
  if $SUDO docker info >/dev/null 2>&1; then DOCKER="$SUDO docker"; else die "Docker is installed but not running. Start it and run this command again."; fi
fi
$DOCKER compose version >/dev/null 2>&1 || die "Docker Compose v2 is missing. Update Docker and run this command again."
ok "Docker $($DOCKER version --format '{{.Server.Version}}' 2>/dev/null || echo ready) with Compose"

# ---------------------------------------------------------------- 2. Stack files
step "Getting the stack"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" 2>/dev/null && pwd || echo "")"
if [ -n "$SCRIPT_DIR" ] && [ -f "$SCRIPT_DIR/docker/docker-compose.yml" ] && [ -z "${MM_DIR:-}" ]; then
  MM_DIR="$SCRIPT_DIR/docker"
  FROM_REPO=1
else
  MM_DIR="${MM_DIR:-$HOME/material-moodle}"
  FROM_REPO=0
  mkdir -p "$MM_DIR"
  if [ -n "$SCRIPT_DIR" ] && [ -f "$SCRIPT_DIR/docker/docker-compose.yml" ]; then
    cp "$SCRIPT_DIR/docker/docker-compose.yml" "$MM_DIR/docker-compose.yml"
  else
    curl -fsSL "$REPO_RAW/material/docker/docker-compose.yml" -o "$MM_DIR/docker-compose.yml" \
      || die "Could not download the stack definition. Check the internet connection."
  fi
fi
ENV_FILE="$MM_DIR/.env"
ok "Stack in $MM_DIR"

# ---------------------------------------------------------------- 3. Questions
REUSE=0
if [ -f "$ENV_FILE" ] && [ "$RECONFIGURE" = 0 ]; then
  REUSE=1
  ok "Found your earlier answers in $ENV_FILE (run with --reconfigure to change them)"
  MM_SITE_NAME="${MM_SITE_NAME:-$(env_get "$ENV_FILE" MOODLE_SITE_NAME)}"
  MM_SHORT_NAME="${MM_SHORT_NAME:-$(env_get "$ENV_FILE" MOODLE_SITE_SHORTNAME)}"
  MM_ADMIN_USER="${MM_ADMIN_USER:-$(env_get "$ENV_FILE" MOODLE_ADMIN_USER)}"
  MM_ADMIN_EMAIL="${MM_ADMIN_EMAIL:-$(env_get "$ENV_FILE" MOODLE_ADMIN_EMAIL)}"
  MM_ADMIN_PASSWORD="${MM_ADMIN_PASSWORD:-$(env_get "$ENV_FILE" MOODLE_ADMIN_PASSWORD)}"
  MM_PORT="${MM_PORT:-$(env_get "$ENV_FILE" MOODLE_PORT)}"
  MM_ACCESS="${MM_ACCESS:-$(env_get "$ENV_FILE" MATERIAL_ACCESS)}"
  MM_TUNNEL_TOKEN="${MM_TUNNEL_TOKEN:-$(env_get "$ENV_FILE" CLOUDFLARE_TUNNEL_TOKEN)}"
  MM_PUBLIC_URL="${MM_PUBLIC_URL:-$(env_get "$ENV_FILE" MOODLE_WWWROOT)}"
  MM_SEED_COLOR="${MM_SEED_COLOR:-$(env_get "$ENV_FILE" MATERIAL_SEED_COLOR)}"
  MM_PASSKEYS="${MM_PASSKEYS:-$(env_get "$ENV_FILE" MATERIAL_ENABLE_PASSKEYS)}"
fi
DB_PASSWORD="$(env_get "$ENV_FILE" MOODLE_DB_PASSWORD)"
[ -z "$DB_PASSWORD" ] && DB_PASSWORD="$(gen_secret 32)"

if [ "$REUSE" = 0 ]; then
  step "A few questions (press Enter to accept the suggestion)"
  ask MM_SITE_NAME "Site name, shown at the top of every page" "Material Moodle"
  ask MM_SHORT_NAME "Short name, shown in the browser tab" "Moodle"
  ask MM_ADMIN_USER "Administrator username" "admin"
  while :; do
    ask MM_ADMIN_EMAIL "Administrator email address" "admin@example.com"
    [[ "$MM_ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] && break
    warn "That does not look like an email address."; MM_ADMIN_EMAIL=""
    have_tty || die "MM_ADMIN_EMAIL is not a valid email address."
  done
  choose PWMODE "Administrator password" generate generate "Generate a strong password for me" type "Type my own"
  if [ "$PWMODE" = "type" ] && [ -z "${MM_ADMIN_PASSWORD:-}" ]; then
    while :; do
      ask MM_ADMIN_PASSWORD "Administrator password (at least 8 characters, a digit, upper and lower case, a symbol)" "" secret
      ask MM_ADMIN_PASSWORD2 "Type the password again" "" secret
      if [ "$MM_ADMIN_PASSWORD" = "$MM_ADMIN_PASSWORD2" ] && [ ${#MM_ADMIN_PASSWORD} -ge 8 ] \
        && [[ "$MM_ADMIN_PASSWORD" =~ [0-9] ]] && [[ "$MM_ADMIN_PASSWORD" =~ [a-z] ]] && [[ "$MM_ADMIN_PASSWORD" =~ [A-Z] ]] \
        && [[ "$MM_ADMIN_PASSWORD" =~ [^A-Za-z0-9] ]]; then break; fi
      warn "The passwords differ or the password is too weak. Try again."; MM_ADMIN_PASSWORD=""; MM_ADMIN_PASSWORD2=""
    done
  fi
  [ -z "${MM_ADMIN_PASSWORD:-}" ] && MM_ADMIN_PASSWORD="$(gen_password)"
  ask MM_PORT "Port on this machine" "$(free_port 8080)"
  [[ "$MM_PORT" =~ ^[0-9]+$ ]] || die "The port must be a number."
  choose MM_ACCESS "How will people reach the site?" quick \
    local "Only on this machine and my network" \
    quick "A free public https link from Cloudflare (no account)" \
    token "My own domain, with a Cloudflare tunnel token" \
    url "My own domain, behind a proxy I already run"
  case "$MM_ACCESS" in
    token) ask MM_TUNNEL_TOKEN "Paste the tunnel token (Cloudflare dashboard > Zero Trust > Networks > Tunnels)" "" secret
           [ -n "$MM_TUNNEL_TOKEN" ] || die "A tunnel token is needed for this option."
           ask MM_PUBLIC_URL "The address you routed to this tunnel, for example https://learn.example.org" "" ;;
    url)   ask MM_PUBLIC_URL "The public address, for example https://learn.example.org" "" ;;
  esac
  choose MM_SEED_COLOR "Pick the colour the whole palette is built from (changeable later)" "#8B5000" \
    "#8B5000" "Amber" "#006A6A" "Teal" "#4F5B92" "Indigo" "#9C4146" "Rose" "#3B6939" "Forest" "#6750A4" "Violet"
  choose MM_PASSKEYS "Show \"Sign in with a passkey\"?" 1 1 "Yes, passkeys and passwords" 0 "No, passwords only"
fi

MM_ACCESS="${MM_ACCESS:-quick}"
MM_PORT="${MM_PORT:-8080}"
LAN="$(lan_ip)"
case "$MM_ACCESS" in
  local) WWWROOT="http://${LAN:-localhost}:$MM_PORT"; PROFILES="" ;;
  quick) WWWROOT="auto"; PROFILES="quicktunnel" ;;
  token) WWWROOT="${MM_PUBLIC_URL:?MM_PUBLIC_URL is required with a tunnel token}"; PROFILES="tunnel" ;;
  url)   WWWROOT="${MM_PUBLIC_URL:?MM_PUBLIC_URL is required}"; PROFILES="" ;;
  *) die "Unknown access option: $MM_ACCESS" ;;
esac
[[ "$WWWROOT" == auto || "$WWWROOT" =~ ^https?://[A-Za-z0-9.:-]+$ ]] || die "The address must look like https://learn.example.org"

if [ "$REUSE" = 0 ]; then
  SUMMARY="Site: $MM_SITE_NAME ($MM_SHORT_NAME)
Administrator: $MM_ADMIN_USER <$MM_ADMIN_EMAIL>
Password: $([ "${PWMODE:-generate}" = type ] && echo 'the one you typed' || echo 'generated, shown at the end')
Port: $MM_PORT
Access: $MM_ACCESS $([ "$WWWROOT" != auto ] && echo "($WWWROOT)")
Colour: $MM_SEED_COLOR    Passkeys: $([ "${MM_PASSKEYS:-1}" = 1 ] && echo yes || echo no)
Files: $MM_DIR"
  confirm "Ready to set up Material Moodle with these answers?

$SUMMARY" || die "Setup cancelled. Run the command again to start over."
fi

# ---------------------------------------------------------------- 4. Write .env
step "Writing your settings"
umask 077
TMP="$(mktemp "$MM_DIR/.env.XXXXXX")"
{
  echo "# Written by material/install.sh. Run it again with --reconfigure to change these answers."
  echo "MOODLE_SITE_NAME=$(env_quote "$MM_SITE_NAME")"
  echo "MOODLE_SITE_SHORTNAME=$(env_quote "$MM_SHORT_NAME")"
  echo "MOODLE_ADMIN_USER=$(env_quote "$MM_ADMIN_USER")"
  echo "MOODLE_ADMIN_EMAIL=$(env_quote "$MM_ADMIN_EMAIL")"
  echo "MOODLE_ADMIN_PASSWORD=$(env_quote "$MM_ADMIN_PASSWORD")"
  echo "MOODLE_DB_PASSWORD=$(env_quote "$DB_PASSWORD")"
  echo "MOODLE_WWWROOT=$(env_quote "$WWWROOT")"
  echo "MOODLE_FALLBACK_WWWROOT=$(env_quote "http://${LAN:-localhost}:$MM_PORT")"
  echo "MOODLE_TRUSTED_HOSTS=$(env_quote "localhost,localhost:*,${LAN:+$LAN,$LAN:*,}*.trycloudflare.com")"
  echo "MOODLE_PORT=$MM_PORT"
  echo "MATERIAL_ACCESS=$MM_ACCESS"
  echo "MATERIAL_SEED_COLOR=$(env_quote "${MM_SEED_COLOR:-#8B5000}")"
  echo "MATERIAL_ENABLE_PASSKEYS=${MM_PASSKEYS:-1}"
  echo "CLOUDFLARE_TUNNEL_TOKEN=$(env_quote "${MM_TUNNEL_TOKEN:-}")"
  echo "COMPOSE_PROFILES=$PROFILES"
  [ -n "${MM_IMAGE:-}" ] && echo "MOODLE_IMAGE=$(env_quote "$MM_IMAGE")"
} >"$TMP"
mv "$TMP" "$ENV_FILE"
chmod 600 "$ENV_FILE"
umask 022
ok "Saved to $ENV_FILE (only you can read it)"

# ---------------------------------------------------------------- 5. Start
step "Starting Material Moodle"
cd "$MM_DIR"
images_present() {
  local img
  for img in $($DOCKER compose config --images 2>/dev/null); do
    $DOCKER image inspect "$img" >/dev/null 2>&1 || return 1
  done
}
if ! $DOCKER compose pull --quiet 2>/dev/null; then
  if images_present; then
    warn "Could not check for a newer image; using the copy already on this machine."
  elif [ "$FROM_REPO" = 1 ]; then
    warn "Could not download the ready-made image; building it from this checkout instead (this takes a while)."
    $DOCKER compose build || die "The image could not be built."
  else
    die "Could not download the Material Moodle image. Check the internet connection and run the command again."
  fi
fi
$DOCKER compose up -d || die "The containers did not start. See: cd $MM_DIR && docker compose logs"
ok "Containers started"

step "Waiting for Moodle to finish installing (a few minutes the first time)"
SPIN='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
start=$(date +%s)
while :; do
  if curl -fsS -o /dev/null "http://127.0.0.1:$MM_PORT/login/index.php" 2>/dev/null; then break; fi
  if $DOCKER compose ps --status exited --services 2>/dev/null | grep -qx moodle; then
    $DOCKER compose logs --tail 40 moodle >&2
    die "Moodle stopped while installing. The last log lines are above."
  fi
  now=$(date +%s)
  [ $((now - start)) -gt 1200 ] && die "Moodle did not answer within 20 minutes. See: cd $MM_DIR && docker compose logs moodle"
  i=$(( (now - start) % 10 ))
  [ -t 1 ] && printf '\r  %s%s%s %ss ' "$OR" "${SPIN:$i:1}" "$R" "$((now - start))"
  sleep 3
done
[ -t 1 ] && printf '\r%30s\r' ''
ok "Moodle answers on port $MM_PORT"

LINK="$WWWROOT"
if [ "$MM_ACCESS" = quick ]; then
  step "Getting your public link"
  for _ in $(seq 1 40); do
    LINK="$($DOCKER compose logs quicktunnel 2>/dev/null | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true)"
    [ -n "$LINK" ] && break
    sleep 3
  done
  [ -n "$LINK" ] || { warn "Cloudflare did not hand out a link yet. Your site still works at http://${LAN:-localhost}:$MM_PORT"; LINK="http://${LAN:-localhost}:$MM_PORT"; }
fi

celebrate
say ""
say "${GR}${B}   ✓  Your site is live${R}"
say ""
say "   ${B}Open:${R}      ${CY}$LINK${R}"
[ "$LINK" != "http://${LAN:-localhost}:$MM_PORT" ] && say "   ${B}On your network:${R} http://${LAN:-localhost}:$MM_PORT"
say "   ${B}Username:${R}  $MM_ADMIN_USER"
if [ "$REUSE" = 0 ] && [ "${PWMODE:-generate}" != type ]; then
  say "   ${B}Password:${R}  $MM_ADMIN_PASSWORD   ${D}(shown once; it is also in $ENV_FILE)${R}"
fi
say ""
say "${D}   Next: the setup wizard opens when you first sign in. Change answers later with --reconfigure.${R}"
[ "$MM_ACCESS" = quick ] && say "${D}   The quick link changes if the tunnel restarts; run this command again to see the current one.${R}"
say ""
