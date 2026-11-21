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
 * English strings for the Material tools.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Material tools';
$string['materialtools:createstudents'] = 'Add students with shareable credentials';
$string['materialtools:managevocabulary'] = 'Manage the custom vocabulary pack';
$string['privacy:metadata:preference:vocab'] = 'Whether this person was chosen to see the custom vocabulary pack.';

$string['newstudent'] = 'Add a student';
$string['newstudent_desc'] = 'Create an account and get a message you can paste straight into Discord. The username and the temporary password each sit in their own code block, so each copies with one tap.';
$string['usernameauto'] = 'Made from the name if left empty';
$string['usernameauto_help'] = 'Leave empty to use firstname.lastname in lower case. A number is added when that username is taken.';
$string['emailoptional'] = 'Email (optional)';
$string['passwordmode'] = 'Temporary password';
$string['passwordgenerate'] = 'Generate one for me';
$string['passwordcustom'] = 'Type my own';
$string['enrolincourse'] = 'Enrol in course (optional)';
$string['vocabforstudent'] = 'Custom vocabulary';
$string['vocabforstudent_desc'] = 'This student sees the uploaded vocabulary pack after signing in';
$string['firstsignin'] = 'First sign-in';
$string['firstsignin_desc'] = 'The student must choose a new password, then may add a passkey (optional).';
$string['createstudent'] = 'Create student';
$string['studentcreated'] = '{$a} was created.';
$string['readyfordiscord'] = 'Ready to paste in Discord';
$string['copyfordiscord'] = 'Copy for Discord';
$string['copiedfordiscord'] = 'Copied for Discord';
$string['copy'] = 'Copy';
$string['shownonce'] = 'The password is shown only now. Send it straight away.';
$string['temporarypassword'] = 'Temporary password';
$string['willchangepassword'] = 'They will choose a new password the first time they sign in.';
$string['willofferpasskey'] = 'After that they are offered a passkey for quicker sign-in. It is optional.';
$string['willgetvocab'] = 'They will see the custom vocabulary after signing in.';
$string['addanother'] = 'Add another student';
$string['viewprofile'] = 'View profile';

$string['studentsettings'] = 'Student account settings';
$string['discordtemplate'] = 'Credentials message';
$string['discordtemplate_desc'] = 'Placeholders: {sitename}, {siteurl}, {fullname}, {firstname}, {username}, {password}. Keep the username and password inside their own ``` code blocks for one-tap copying in Discord.';
$string['passwordstyle'] = 'Temporary password style';
$string['passwordstyle_desc'] = 'Words are easier to read out; random is longer. Both follow the site password policy.';
$string['passwordwords'] = 'Two words and a number';
$string['passwordrandom'] = 'Random characters';
$string['passwordlength'] = 'Random password length';
$string['passwordlength_desc'] = 'Used for random passwords. At least 10.';
$string['studentrole'] = 'Role when enrolling';
$string['studentrole_desc'] = 'Role given when a course is chosen on the Add a student page.';

$string['vocabulary'] = 'Custom vocabulary';
$string['vocab_desc'] = 'Upload a vocabulary JSON file to replace interface words with your own, for signed-in people you choose. Signed-out pages always keep the original wording.';
$string['vocabfile'] = 'Vocabulary file';
$string['vocabstatusnone'] = 'No file loaded';
$string['vocabstatusloaded'] = 'Loaded: {$a} terms';
$string['vocabupdated'] = 'Updated {$a}';
$string['vocabdrophint'] = 'Drop a .json file here, or anywhere on the site while you are signed in as a manager.';
$string['vocabchoose'] = 'Choose file';
$string['vocabreplace'] = 'Replace file';
$string['vocabclear'] = 'Clear and restore original wording';
$string['vocabcleared'] = 'Vocabulary cleared. Everyone sees the original wording.';
$string['vocabloaded'] = 'Vocabulary loaded: {$a} terms.';
$string['vocabapplied'] = 'Personal wording applied';
$string['vocabwho'] = 'Who sees it';
$string['vocabwho_desc'] = 'Only signed-in people ever see the vocabulary.';
$string['audiencenobody'] = 'Nobody';
$string['audienceeveryone'] = 'Everyone signed in';
$string['audienceselected'] = 'Selected students and cohorts';
$string['vocabcohorts'] = 'Cohorts that see it (with "Selected")';
$string['vocabchosen'] = 'Selected students';
$string['vocabnochosen'] = 'No students chosen yet.';
$string['addstudents'] = 'Add students';
$string['removeuser'] = 'Remove {$a}';
$string['dropinvalid'] = 'That file was not applied: {$a}';

$string['vocaberrorempty'] = 'The file has no terms.';
$string['vocaberrorsize'] = 'The file is larger than 256 KB.';
$string['vocaberrorencoding'] = 'The file is not UTF-8 text.';
$string['vocaberrorsyntax'] = 'The file is not valid JSON.';
$string['vocaberrorduplicate'] = 'The file repeats the same key.';
$string['vocaberrordepth'] = 'The file is nested more than 8 levels deep.';
$string['vocaberrorshape'] = 'The file must be an object with "schemaVersion" and "entries".';
$string['vocaberrorversion'] = 'Only schemaVersion 1 is supported.';
$string['vocaberrorkey'] = 'A key is empty or longer than 160 characters.';
$string['vocaberrorvalue'] = 'A value is not text, is empty, or is longer than 1,000 characters.';

$string['wording'] = 'Site wording';
$string['wording_desc'] = 'Call teachers, students and assignments whatever your school calls them. The names apply everywhere, including every assignment that is posted. Leave a field empty to keep the standard name.';
$string['wording_help'] = 'Course teachers can still rename roles for one course under Course settings > Role renaming.';
$string['wordingroles'] = 'People';
$string['wordingactivities'] = 'Activities';
$string['wordingdefault'] = 'Standard name';
$string['wordingrole_editingteacher'] = 'Teacher';
$string['wordingrole_teacher'] = 'Non-editing teacher';
$string['wordingrole_student'] = 'Student';
$string['wordingassignsingular'] = 'Assignment (one)';
$string['wordingassignplural'] = 'Assignments (many)';
$string['wordingsaved'] = 'Wording saved. Every page now uses the new names.';

$string['typename'] = 'Type name';
$string['typename_help'] = 'Optional. Shows this name instead of the activity type for this one activity, for example "Dropbox" for an assignment that collects resumes. The activity keeps working exactly as before.';
$string['typename_placeholder'] = 'For example: Dropbox';
$string['postnew'] = 'Post a new {$a}';
$string['newposttype'] = 'New quick post type';
$string['quickposts'] = 'Quick post buttons';
$string['quickposts_desc'] = 'One per line as Name|activity, for example "Dropbox|assign" or "Reading check|quiz". Teachers see a "Post a new ..." button for each in editing mode, and the new activity gets that type name.';
$string['titles'] = 'Personal titles';
$string['titles_desc'] = 'Give one teacher or student their own title, shown instead of their role name, for the whole site or one course. Their role and permissions stay exactly the same.';
$string['person'] = 'Person';
$string['titlecourse'] = 'Where';
$string['wholesite'] = 'Whole site';
$string['titletext'] = 'Title';
$string['titletext_placeholder'] = 'For example: Coach';
$string['savetitle'] = 'Save title';
$string['titlesaved'] = 'Title saved. The role is unchanged.';
$string['studenttitle'] = 'Title (optional)';
$string['studenttitle_help'] = 'Shown instead of "Student" for this person across the site. Their role stays Student.';

$string['onboarding'] = 'Set up Material Moodle';
$string['onboard_steps'] = 'Setup steps';
$string['onboard_colour'] = 'Colour';
$string['onboard_signin'] = 'Sign-in page';
$string['onboard_passkeys'] = 'Passkeys';
$string['onboard_vocabulary'] = 'Vocabulary';
$string['onboard_wording'] = 'Wording';
$string['onboard_done'] = 'Done';
$string['onboard_colour_title'] = 'Pick your colour';
$string['onboard_colour_desc'] = 'One colour builds the whole light and dark palette. You can change it any time under Appearance.';
$string['onboard_custom'] = 'Or any colour';
$string['onboard_signin_title'] = 'Welcome people at the door';
$string['onboard_passkeys_title'] = 'Passkeys';
$string['onboard_passkeys_desc'] = 'Let people sign in with their fingerprint, face or security key. Passwords keep working.';
$string['onboard_passkeys_on'] = 'Show "Sign in with a passkey" on the sign-in page';
$string['onboard_vocab_loaded'] = 'A vocabulary pack is loaded.';
$string['onboard_vocab_open'] = 'Open the vocabulary page';
$string['onboard_skip'] = 'Skip for now';
$string['onboard_done_title'] = 'All set';
$string['onboard_done_desc'] = 'Your site is ready. Add your first student or head to the dashboard.';
$string['onboard_dashboard'] = 'Go to the dashboard';
$string['allowpersonalvocab'] = 'People can set their own wording pack';
$string['allowpersonalvocab_desc'] = 'Everyone can upload a vocabulary JSON in their preferences, or drop one on any page. It changes wording only for them and wins over the site pack.';
$string['personalvocab'] = 'My wording pack';
$string['personalvocab_desc'] = 'Upload a vocabulary JSON to change the wording you see. It applies only to you, after you sign in. You can also drop the file on any page.';
$string['personalvocabsite'] = 'Your site also has a wording pack for you. Where both change the same words, yours wins.';
$string['personalvocabactive'] = 'Your pack changes {$a} phrases';
$string['personalvocabupdated'] = 'Updated {$a}';
$string['personalvocabnone'] = 'No pack yet';
$string['personalvocabdrop'] = 'Choose a file below, paste JSON, or drop a .json file anywhere on the site.';
$string['personalvocabsave'] = 'Use this pack';
$string['personalvocabclear'] = 'Remove my pack';
$string['personalvocabsaved'] = 'Your pack is on: {$a} phrases changed.';
$string['personalvocabcleared'] = 'Your pack was removed. You see the original wording again.';
$string['personalvocabapplied'] = 'Your wording pack is on';
$string['personalvocaboff'] = 'Personal wording packs are turned off on this site.';
$string['vocabtext'] = 'Or paste the JSON';
$string['privacy:metadata:preference:personalvocab'] = 'Fingerprint, phrase count and time of the person\'s own wording pack.';
$string['privacy:metadata:personalvocabfiles'] = 'The person\'s own wording pack file.';
$string['privacy:metadata:title'] = 'Personal titles shown next to a person\'s name, for the whole site or one course.';
$string['privacy:metadata:title:userid'] = 'The person with the title.';
$string['privacy:metadata:title:courseid'] = 'The course the title applies in, or 0 for the whole site.';
$string['privacy:metadata:title:title'] = 'The title text.';
$string['privacy:metadata:title:timemodified'] = 'When the title last changed.';
$string['firstadmin_title'] = 'Set up your site';
$string['firstadmin_desc'] = 'This site is new. Choose the username and password for the site administrator. The first person to finish this form owns the site, so do it now.';
$string['firstadmin_confirm'] = 'Password again';
$string['firstadmin_submit'] = 'Create administrator';
$string['firstadmin_done'] = 'You are the site administrator. Welcome to your site.';
$string['firstadmin_taken'] = 'Someone already set up this site. Sign in with the administrator account.';
$string['firstadmin_busy'] = 'Another setup is in progress. Wait a moment and try again.';
