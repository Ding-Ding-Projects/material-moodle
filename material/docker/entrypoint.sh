#!/usr/bin/env bash
# Material Moodle container entrypoint.
#   web  (default) installs or upgrades Moodle, applies the Material defaults once, then serves Apache.
#   cron           runs the Moodle scheduled tasks every minute.
set -euo pipefail
cd /var/www/moodle
mode="${1:-web}"

wait_for_db() {
  for i in $(seq 1 90); do
    if PGPASSWORD="${MOODLE_DB_PASSWORD:-moodle}" pg_isready -h "${MOODLE_DB_HOST:-db}" -p "${MOODLE_DB_PORT:-5432}" -U "${MOODLE_DB_USER:-moodle}" >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  echo "Database did not become ready." >&2
  exit 1
}

installed() {
  PGPASSWORD="${MOODLE_DB_PASSWORD:-moodle}" psql -h "${MOODLE_DB_HOST:-db}" -p "${MOODLE_DB_PORT:-5432}" \
    -U "${MOODLE_DB_USER:-moodle}" -d "${MOODLE_DB_NAME:-moodle}" -tAc \
    "SELECT 1 FROM information_schema.tables WHERE table_name='mdl_config'" 2>/dev/null | grep -q 1
}

as_www() { su -s /bin/bash www-data -c "$*"; }

chown -R www-data:www-data /var/www/moodledata
wait_for_db

if [ "$mode" = "cron" ]; then
  until installed; do sleep 15; done
  echo "Cron loop started."
  while true; do
    as_www "php admin/cli/cron.php --keep-alive=0" || true
    sleep 60
  done
fi

if ! installed; then
  echo "Installing Moodle. This takes a few minutes the first time."
  if [ -z "${MOODLE_ADMIN_PASSWORD:-}" ]; then
    echo "MOODLE_ADMIN_PASSWORD is required for the first start." >&2
    exit 1
  fi
  as_www "php admin/cli/install_database.php --agree-license \
    --adminuser=$(printf %q "${MOODLE_ADMIN_USER:-admin}") \
    --adminpass=$(printf %q "$MOODLE_ADMIN_PASSWORD") \
    --adminemail=$(printf %q "${MOODLE_ADMIN_EMAIL:-admin@example.com}") \
    --fullname=$(printf %q "${MOODLE_SITE_NAME:-Material Moodle}") \
    --shortname=$(printf %q "${MOODLE_SITE_SHORTNAME:-Moodle}") \
    --lang=en"
  as_www "php public/local/materialtools/cli/configure.php --first-run"
  echo "MATERIAL_MOODLE_INSTALLED"
else
  as_www "php admin/cli/upgrade.php --non-interactive --allow-unstable" || true
  as_www "php public/local/materialtools/cli/configure.php"
fi

exec apache2-foreground
