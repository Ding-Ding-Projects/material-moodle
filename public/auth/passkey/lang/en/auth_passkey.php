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


/**
 * English strings for passkey sign-in.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Passkeys';
$string['auth_passkeydescription'] = 'People sign in with a passkey (fingerprint, face, screen lock or security key) that they added to their own account. Accounts keep their usual authentication method and password.';
$string['apasskey'] = 'a passkey';
$string['settings_intro'] = 'Enable this plugin under Manage authentication to show the passkey button on the sign-in page. It does not need to be first in the list.';
$string['rpname'] = 'Name shown by the device';
$string['rpname_desc'] = 'The name people see in the passkey prompt. Empty uses the site name.';
$string['userverification'] = 'User verification';
$string['userverification_desc'] = 'Required asks for a fingerprint, face or PIN every time. Preferred also accepts security keys without a PIN.';
$string['uvrequired'] = 'Required';
$string['uvpreferred'] = 'Preferred';
$string['timeout'] = 'Prompt timeout';
$string['timeout_desc'] = 'How long the device prompt waits.';
$string['offeronfirstlogin'] = 'Offer a passkey to new accounts';
$string['offeronfirstlogin_desc'] = 'Accounts created with the Material student tool see an optional "add a passkey" step after they choose their own password.';

$string['signinwithpasskey'] = 'Sign in with a passkey';
$string['signinwithpasskey_desc'] = 'Use the fingerprint, face, screen lock or security key you set up for this site.';
$string['usepassword'] = 'Use your username and password instead';
$string['orusername'] = 'or use your username';
$string['waiting'] = 'Follow the prompt on your device…';
$string['signedin'] = 'Signed in. Taking you there…';
$string['cancelled'] = 'The passkey prompt was closed. Try again when you are ready.';
$string['errorgeneric'] = 'That passkey could not be used. Try again, or sign in with your password.';
$string['errorunsupported'] = 'This browser cannot use passkeys. Sign in with your password instead.';
$string['errornojs'] = 'Passkeys need JavaScript. Sign in with your password instead.';
$string['errorchallenge'] = 'The sign-in request expired. Try again.';
$string['errorunknownpasskey'] = 'This passkey is not registered on this site.';
$string['erroraccount'] = 'This account cannot sign in right now.';
$string['erroralreadyregistered'] = 'This passkey is already registered.';
$string['errordisabled'] = 'Passkey sign-in is turned off on this site.';

$string['managepasskeys'] = 'Passkeys';
$string['managepasskeys_desc'] = 'Passkeys let you sign in with your fingerprint, face, screen lock or a security key. They cannot be phished or reused on another site. Your password keeps working.';
$string['addpasskey'] = 'Add a passkey';
$string['adding'] = 'Follow the prompt on your device…';
$string['added'] = 'Passkey added. You can use it next time you sign in.';
$string['passkeyname'] = 'Passkey name';
$string['passkeyname_placeholder'] = 'Name, for example "My phone"';
$string['defaultname'] = 'Passkey';
$string['addedon'] = 'Added {$a}';
$string['lastused'] = 'Last used: {$a}';
$string['rename'] = 'Rename';
$string['renamed'] = 'Passkey renamed.';
$string['remove'] = 'Remove';
$string['removed'] = 'Passkey removed.';
$string['removeconfirm'] = 'Remove this passkey? You will not be able to sign in with it any more.';
$string['nopasskeys'] = 'No passkeys yet';
$string['nopasskeys_desc'] = 'Add one to sign in without typing your password.';
$string['notenabled'] = 'Passkey sign-in is not turned on for this site yet, so passkeys you add here cannot be used until an administrator enables it.';

$string['welcometitle'] = 'Faster sign-in next time?';
$string['welcometext'] = 'Nice work, {$a}. Add a passkey and sign in with your fingerprint, face or screen lock. This step is optional and your password keeps working.';
$string['notnow'] = 'Not now';
$string['setupsteps'] = 'Setup steps';
$string['stepnewpassword'] = 'New password';
$string['steppasskey'] = 'Passkey (optional)';
$string['stepdone'] = 'Done';

$string['privacy:metadata:auth_passkey_creds'] = 'Passkeys registered by a user.';
$string['privacy:metadata:auth_passkey_creds:userid'] = 'The user the passkey belongs to.';
$string['privacy:metadata:auth_passkey_creds:name'] = 'The name the user gave the passkey.';
$string['privacy:metadata:auth_passkey_creds:publickey'] = 'The public key of the passkey.';
$string['privacy:metadata:auth_passkey_creds:timecreated'] = 'When the passkey was added.';
$string['privacy:metadata:auth_passkey_creds:timelastused'] = 'When the passkey was last used.';
$string['privacy:metadata:preference:offer'] = 'Whether the optional passkey step is still to be shown after the first sign-in.';
$string['managepasskeysfor'] = 'Passkeys of {$a}';
$string['managepasskeysother_desc'] = 'These are the passkeys this person can sign in with. Remove one if a device was lost or should no longer have access. Only the owner can add new passkeys.';
$string['noneselected'] = 'Choose at least one passkey first.';
$string['removedcount'] = '{$a} passkeys removed.';
$string['removeselected'] = 'Remove selected';
$string['removeselectedconfirm'] = 'Remove {$a} passkeys? Those devices can no longer be used to sign in.';
$string['selectpasskey'] = 'Select {$a}';
$string['passkeysofuser'] = 'Passkeys ({$a})';
