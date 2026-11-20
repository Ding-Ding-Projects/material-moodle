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


namespace auth_passkey;

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

/**
 * Passkey ceremonies and storage.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Table name. */
    public const TABLE = 'auth_passkey_creds';

    /**
     * The WebAuthn server for this site.
     *
     * @return WebAuthn
     */
    public static function server(): WebAuthn {
        global $CFG, $SITE;
        $rpid = (new \moodle_url($CFG->wwwroot))->get_host();
        $name = get_config('auth_passkey', 'rpname') ?: format_string($SITE->fullname);
        return new WebAuthn($name, $rpid);
    }

    /**
     * Is user verification (PIN, fingerprint, face) required?
     *
     * @return bool
     */
    public static function require_uv(): bool {
        return get_config('auth_passkey', 'userverification') !== 'preferred';
    }

    /**
     * Ceremony timeout in seconds.
     *
     * @return int
     */
    public static function timeout(): int {
        return max(30, min(600, (int) (get_config('auth_passkey', 'timeout') ?: 120)));
    }

    /**
     * Save the challenge for one ceremony.
     *
     * @param WebAuthn $server
     * @param string $purpose
     */
    private static function remember_challenge(WebAuthn $server, string $purpose): void {
        global $SESSION;
        $SESSION->{'auth_passkey_' . $purpose} = $server->getChallenge()->getHex();
    }

    /**
     * Take the challenge, once.
     *
     * @param string $purpose
     * @return ByteBuffer
     */
    private static function take_challenge(string $purpose): ByteBuffer {
        global $SESSION;
        $key = 'auth_passkey_' . $purpose;
        if (empty($SESSION->$key)) {
            throw new \moodle_exception('errorchallenge', 'auth_passkey');
        }
        $hex = $SESSION->$key;
        unset($SESSION->$key);
        return ByteBuffer::fromHex($hex);
    }

    /**
     * Options for adding a passkey to the current user.
     *
     * @param \stdClass $user
     * @return \stdClass
     */
    public static function registration_options(\stdClass $user): \stdClass {
        global $DB, $CFG;
        $server = self::server();
        $exclude = [];
        foreach ($DB->get_records(self::TABLE, ['userid' => $user->id], '', 'id, credentialid') as $row) {
            $exclude[] = base64_decode($row->credentialid);
        }
        $handle = hash('sha256', $CFG->siteidentifier . ':' . $user->id, true);
        $args = $server->getCreateArgs($handle, $user->username, fullname($user), self::timeout(), true,
            self::require_uv() ? true : 'preferred', null, $exclude);
        self::remember_challenge($server, 'register');
        return $args;
    }

    /**
     * Verify and store a new passkey.
     *
     * @param \stdClass $user
     * @param string $clientdatajson Base64.
     * @param string $attestation Base64.
     * @param string $name
     * @param string $transports
     * @return \stdClass The stored record.
     */
    public static function register(\stdClass $user, string $clientdatajson, string $attestation, string $name,
            string $transports = ''): \stdClass {
        global $DB;
        $server = self::server();
        $challenge = self::take_challenge('register');
        $data = $server->processCreate(base64_decode($clientdatajson), base64_decode($attestation), $challenge,
            self::require_uv(), true, false);
        $raw = $data->credentialId;
        $record = (object) [
            'userid' => $user->id,
            'credhash' => hash('sha256', $raw),
            'credentialid' => base64_encode($raw),
            'publickey' => $data->credentialPublicKey,
            'signcount' => (int) ($data->signatureCounter ?? 0),
            'name' => \core_text::substr(trim($name) !== '' ? trim($name) : get_string('defaultname', 'auth_passkey'), 0, 255),
            'aaguid' => isset($data->AAGUID) ? bin2hex($data->AAGUID) : null,
            'transports' => \core_text::substr($transports, 0, 255),
            'timecreated' => time(),
            'timelastused' => 0,
        ];
        if ($DB->record_exists(self::TABLE, ['credhash' => $record->credhash])) {
            throw new \moodle_exception('erroralreadyregistered', 'auth_passkey');
        }
        $record->id = $DB->insert_record(self::TABLE, $record);
        set_user_preference('auth_passkey_offer', 0, $user);
        return $record;
    }

    /**
     * Options for signing in with any passkey of this site (discoverable credentials).
     *
     * @return \stdClass
     */
    public static function login_options(): \stdClass {
        $server = self::server();
        $args = $server->getGetArgs([], self::timeout(), true, true, true, true, true, self::require_uv() ? true : 'preferred');
        self::remember_challenge($server, 'login');
        return $args;
    }

    /**
     * Verify an assertion and return the user it belongs to.
     *
     * @param string $id Base64 raw credential id.
     * @param string $clientdatajson Base64.
     * @param string $authenticatordata Base64.
     * @param string $signature Base64.
     * @return \stdClass The user.
     */
    public static function verify_login(string $id, string $clientdatajson, string $authenticatordata,
            string $signature): \stdClass {
        global $DB;
        $challenge = self::take_challenge('login');
        $raw = base64_decode($id);
        $cred = $DB->get_record(self::TABLE, ['credhash' => hash('sha256', $raw)]);
        if (!$cred) {
            throw new \moodle_exception('errorunknownpasskey', 'auth_passkey');
        }
        $server = self::server();
        $server->processGet(base64_decode($clientdatajson), base64_decode($authenticatordata), base64_decode($signature),
            $cred->publickey, $challenge, $cred->signcount ?: null, self::require_uv(), true);
        $cred->signcount = (int) $server->getSignatureCounter();
        $cred->timelastused = time();
        $DB->update_record(self::TABLE, $cred);

        $user = \core_user::get_user($cred->userid);
        if (!$user || $user->deleted || $user->suspended || $user->auth === 'nologin' || isguestuser($user)) {
            throw new \moodle_exception('erroraccount', 'auth_passkey');
        }
        if (!is_enabled_auth($user->auth) && $user->auth !== 'manual') {
            throw new \moodle_exception('erroraccount', 'auth_passkey');
        }
        return $user;
    }

    /**
     * Passkeys of a user.
     *
     * @param int $userid
     * @return array
     */
    public static function list_for_user(int $userid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated ASC'));
    }

    /**
     * Rename one of the user's passkeys.
     *
     * @param int $userid
     * @param int $id
     * @param string $name
     */
    public static function rename(int $userid, int $id, string $name): void {
        global $DB;
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $DB->set_field(self::TABLE, 'name', \core_text::substr($name, 0, 255), ['id' => $id, 'userid' => $userid]);
    }

    /**
     * Remove one of the user's passkeys.
     *
     * @param int $userid
     * @param int $id
     */
    public static function delete(int $userid, int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id, 'userid' => $userid]);
    }
}
