<?php
// Material Moodle container configuration. Every value comes from the environment (see material/docker/.env.example).
unset($CFG);
global $CFG;
$CFG = new stdClass();

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
};

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = $env('MOODLE_DB_HOST', 'db');
$CFG->dbname    = $env('MOODLE_DB_NAME', 'moodle');
$CFG->dbuser    = $env('MOODLE_DB_USER', 'moodle');
$CFG->dbpass    = $env('MOODLE_DB_PASSWORD', 'moodle');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = ['dbpersist' => 0, 'dbport' => (int) $env('MOODLE_DB_PORT', '5432')];

// MOODLE_WWWROOT is the public address. "auto" follows the address the visitor used, which suits a Cloudflare quick
// tunnel whose address changes; it is limited to MOODLE_TRUSTED_HOSTS so a forged Host header cannot be used.
$wwwroot = rtrim($env('MOODLE_WWWROOT', 'http://localhost:8080'), '/');
if ($wwwroot === 'auto') {
    $wwwroot = rtrim($env('MOODLE_FALLBACK_WWWROOT', 'http://localhost:8080'), '/');
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $trusted = array_filter(array_map('trim', explode(',', $env('MOODLE_TRUSTED_HOSTS', 'localhost,localhost:*,*.trycloudflare.com'))));
    foreach ($trusted as $pattern) {
        if ($host !== '' && preg_match('/^[a-z0-9.:-]+$/', $host)
                && fnmatch(strtolower($pattern), $host)) {
            $https = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['HTTPS'] ?? '') === 'on')
                || str_ends_with($host, '.trycloudflare.com');
            $wwwroot = ($https ? 'https' : 'http') . '://' . $host;
            break;
        }
    }
}
$CFG->wwwroot = $wwwroot;
if (str_starts_with($wwwroot, 'https://') && empty($_SERVER['HTTPS'])) {
    // TLS ends at the tunnel or proxy in front of this container.
    $CFG->sslproxy = true;
}
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02777;
$CFG->routerconfigured = true;
$CFG->preventexecpath = true;
$CFG->pathtogs = '/usr/bin/gs';

require_once(__DIR__ . '/lib/setup.php');
