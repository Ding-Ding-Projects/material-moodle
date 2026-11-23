<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_bathroompass;

/**
 * Sends break events to a webhook such as Home Assistant. A failure never blocks a break.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook {
    /**
     * Whether a URL is an acceptable http or https address.
     *
     * @param string $url
     * @return bool
     */
    public static function valid_url(string $url): bool {
        $parts = parse_url($url);
        return $parts !== false && !empty($parts['host'])
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Post a JSON payload.
     *
     * @param string $url
     * @param array $payload
     * @return int The HTTP status, or 0 when nothing was sent or the delivery failed.
     */
    public static function post(string $url, array $payload): int {
        global $CFG;
        if (!self::valid_url($url)) {
            return 0;
        }
        require_once($CFG->libdir . '/filelib.php');
        try {
            // Home Assistant usually lives on the local network, which Moodle's curl security blocks by default.
            $options = get_config('local_bathroompass', 'allowlocal') ? ['ignoresecurity' => true] : [];
            $curl = new \curl($options);
            $curl->setHeader(['Content-Type: application/json']);
            $response = $curl->post($url, json_encode($payload), ['CURLOPT_TIMEOUT' => 4, 'CURLOPT_CONNECTTIMEOUT' => 3]);
            if ($response === false || $curl->get_errno()) {
                return 0;
            }
            $info = $curl->get_info();
            return (int) ($info['http_code'] ?? 0);
        } catch (\Throwable $e) {
            debugging('Bathroom pass webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Send the test event.
     *
     * @param string $url
     * @return int The HTTP status, or 0 on failure.
     */
    public static function send_test(string $url): int {
        return self::post($url, ['event' => 'test']);
    }

    /**
     * Whether an HTTP status means the delivery worked.
     *
     * @param int $status
     * @return bool
     */
    public static function ok(int $status): bool {
        return $status >= 200 && $status < 300;
    }
}
