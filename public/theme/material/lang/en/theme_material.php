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
 * English strings for the Material theme.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Material';
$string['choosereadme'] = 'Material restyles every Moodle page in Material 3: one seed colour builds the full light and dark palette, with rounded shapes, expressive motion, a command palette with a regex builder, copy buttons on code and celebration moments.';
$string['configtitle'] = 'Material';
$string['privacy:metadata'] = 'The Material theme does not store any personal data.';
$string['region-side-pre'] = 'Right';

$string['tablook'] = 'Look and motion';
$string['tablogin'] = 'Sign-in page';
$string['tabfeatures'] = 'Features';
$string['tabadvanced'] = 'Advanced';

$string['seedcolor'] = 'Seed colour';
$string['seedcolor_desc'] = 'One colour generates every other colour, for light and dark mode.';
$string['font'] = 'Font';
$string['font_desc'] = 'All fonts are included with the theme, so no font is loaded from another website.';
$string['fontsystem'] = 'The device font';
$string['radius'] = 'Corner roundness';
$string['radius_desc'] = 'Roundness of cards, dialogs and large surfaces. Buttons and fields use half of it.';
$string['density'] = 'Density';
$string['density_desc'] = 'Compact tightens spacing for pages with a lot of content.';
$string['densitycomfortable'] = 'Comfortable';
$string['densitycompact'] = 'Compact';
$string['motion'] = 'Motion';
$string['motion_desc'] = 'Devices that ask for reduced motion always get the calm version.';
$string['motionexpressive'] = 'Expressive';
$string['motioncalm'] = 'Calm';
$string['motionoff'] = 'Off';
$string['pagetransitions'] = 'Page transitions';
$string['pagetransitions_desc'] = 'Content rises in softly when a page opens.';
$string['ripples'] = 'Touch ripples';
$string['ripples_desc'] = 'Buttons show a ripple where they are pressed.';
$string['colourmodes'] = 'Light and dark mode';
$string['colourmodes_desc'] = 'People choose light, dark or automatic from the user menu once colour modes are turned on in the <a href="{$a}">Boost settings</a>. The Material palette follows the choice.';

$string['loginheading'] = 'Sign-in heading';
$string['loginheading_desc'] = 'Large heading on the coloured side of the sign-in page.';
$string['loginheadingdefault'] = 'Learning, in full colour.';
$string['logintext'] = 'Sign-in text';
$string['logintext_desc'] = 'A sentence or two under the heading.';
$string['logintextdefault'] = 'Sign in with your fingerprint, face or security key, or with your username and password.';
$string['loginchips'] = 'Sign-in labels';
$string['loginchips_desc'] = 'Comma separated short labels shown at the bottom of the coloured panel. Leave empty for none.';
$string['loginchipsdefault'] = 'Passkeys, Dark mode, Material 3';
$string['loginimage'] = 'Sign-in image';
$string['loginimage_desc'] = 'Optional photo for the coloured side. Without one, animated shapes in your palette are shown.';

$string['commandpalette'] = 'Command palette';
$string['commandpalette_desc'] = 'Ctrl+Shift+F (or the search button) searches your courses, the links on the page and, for administrators, site settings.';
$string['regexbuilder'] = 'Regex builder';
$string['regexbuilder_desc'] = 'Visual pattern blocks in the command palette and on the page filter boxes, with an ignore-case switch.';
$string['copybuttons'] = 'Copy buttons on code';
$string['copybuttons_desc'] = 'Every code block gets a Copy button.';
$string['celebrations'] = 'Celebrations';
$string['celebrations_desc'] = 'Success messages burst with confetti; error messages shake and flash so they are not missed.';
$string['celebrationlevel'] = 'Celebration level';
$string['celebrationlevel_desc'] = 'How big the success and error moments are.';
$string['celebrationsubtle'] = 'Subtle';
$string['celebrationnormal'] = 'Normal';
$string['celebrationdramatic'] = 'Dramatic';

$string['rawscsspre'] = 'Raw initial SCSS';
$string['rawscsspre_desc'] = 'SCSS variables set before the theme is compiled.';
$string['rawscss'] = 'Raw SCSS';
$string['rawscss_desc'] = 'SCSS or CSS added at the end of the theme.';

$string['search'] = 'Search';
$string['searchplaceholder'] = 'Search courses, pages and settings';
$string['copy'] = 'Copy';
$string['copied'] = 'Copied';
$string['useregex'] = 'Regex';
$string['ignorecase'] = 'Ignore case';
$string['clear'] = 'Clear';
$string['nomatch'] = 'No match. Remove a block or turn on Ignore case.';
$string['matches'] = 'matches';
$string['patternincomplete'] = 'Pattern is incomplete';
$string['removeblock'] = 'Remove this block';
$string['searchcourses'] = 'Search all courses for';
$string['searchsettings'] = 'Search site settings for';
$string['kindpage'] = 'Page';
$string['kindcourse'] = 'Course';
$string['kindsearch'] = 'Search';
$string['kindsetting'] = 'Setting';
$string['blockstartswith'] = 'Starts with';
$string['blockendswith'] = 'Ends with';
$string['blockanything'] = 'Anything';
$string['blockdigit'] = 'Digit';
$string['blockletter'] = 'Letter';
$string['blockwordedge'] = 'Word edge';
$string['blockspace'] = 'Space';
$string['blockoneormore'] = 'One or more';
$string['blockoptional'] = 'Optional';
$string['blockeither'] = 'Either';
$string['blockgroupopen'] = 'Group start';
$string['blockgroupclose'] = 'Group end';
$string['contextmenu'] = 'Right-click menu';
$string['contextmenu_desc'] = 'A Material menu on right-click with copy, search, link and page actions and their shortcuts. Shift + right-click opens the browser menu.';
$string['ctxmenu'] = 'Actions';
$string['ctxcopy'] = 'Copy';
$string['ctxsearchfor'] = 'Search for';
$string['ctxopen'] = 'Open link';
$string['ctxopentab'] = 'Open link in new tab';
$string['ctxcopylink'] = 'Copy link address';
$string['ctxopenimage'] = 'Open image in new tab';
$string['ctxcopyimage'] = 'Copy image address';
$string['ctxcopycode'] = 'Copy code';
$string['ctxback'] = 'Back';
$string['ctxreload'] = 'Reload';
$string['ctxcopypage'] = 'Copy page address';
$string['ctxprint'] = 'Print';
$string['ctxtop'] = 'Back to top';
$string['notifications'] = 'Message history';
$string['notifications_desc'] = 'A history button in the top bar keeps every alert and pop-up message in a history you can search, mark read and clear. The history stays in this browser only.';
$string['notifynow'] = 'Just now';
$string['notifyminutes'] = '{$a} min ago';
$string['notifyempty'] = 'Nothing yet. Messages that pop up on any page, like Changes saved, are kept here.';
$string['notifysearch'] = 'Search message history';
$string['notifymarkread'] = 'Mark all read';
$string['notifyremoveselected'] = 'Remove selected';
$string['notifyclear'] = 'Clear all';
$string['notifyselect'] = 'Select this notification';
$string['provenance'] = 'Show where setting values come from';
$string['provenance_desc'] = 'On administration pages each setting shows whether it still uses the shipped default or a saved value.';
$string['provdefault'] = 'Shipped default';
$string['provchanged'] = 'Changed from default';
$string['yes'] = 'Yes';
$string['no'] = 'No';
$string['empty'] = 'Empty';
$string['notifynomatch'] = 'No messages match this search.';
$string['paneltitle'] = 'Display settings';
$string['panelcolour'] = 'Colour mode';
$string['panelauto'] = 'Automatic';
$string['panellight'] = 'Light';
$string['paneldark'] = 'Dark';
$string['panelmotion'] = 'Motion';
$string['panelexpressive'] = 'Expressive';
$string['panelcalm'] = 'Calm';
$string['paneloff'] = 'Off';
$string['panellanguage'] = 'Panel language';
$string['panelen'] = 'English';
$string['panelyue'] = '廣東話';
$string['panelbi'] = 'Bilingual';
$string['panelnote'] = 'Device and site motion limits take priority. Panel language changes these controls.';
$string['panelpreferences'] = 'All preferences';
$string['paneladministration'] = 'Site administration';
$string['panelfailure'] = 'Could not save your motion preference. Please try again.';
$string['feature_addstudent'] = 'Add a student';
$string['feature_addstudent_desc'] = 'Create an account and share its sign-in details';
$string['feature_allcourses'] = 'All courses';
$string['feature_allcourses_desc'] = 'Browse courses by category';
$string['feature_badges'] = 'Badges';
$string['feature_badges_desc'] = 'Badges you have earned';
$string['feature_blogs'] = 'Blogs';
$string['feature_blogs_desc'] = 'Site and course blog entries';
$string['feature_calendar'] = 'Calendar';
$string['feature_calendar_desc'] = 'Deadlines and events by month, week or day';
$string['feature_contentbank'] = 'Content bank';
$string['feature_contentbank_desc'] = 'H5P and other reusable content';
$string['feature_coursebadges'] = 'Course badges';
$string['feature_coursebadges_desc'] = 'Badges this course awards';
$string['feature_coursereports'] = 'Course reports';
$string['feature_coursereports_desc'] = 'Logs, activity and completion for this course';
$string['feature_coursereuse'] = 'Course reuse';
$string['feature_coursereuse_desc'] = 'Import, back up, restore and reset';
$string['feature_dashboard'] = 'Dashboard';
$string['feature_dashboard_desc'] = 'Your timeline, recent items and course overview';
$string['feature_datarequests'] = 'Data requests';
$string['feature_datarequests_desc'] = 'Ask for a copy of your data or for its deletion';
$string['feature_findcourse'] = 'Find a course';
$string['feature_findcourse_desc'] = 'Search every course on this site';
$string['feature_gradebook'] = 'Gradebook';
$string['feature_gradebook_desc'] = 'Grades for every activity in this course';
$string['feature_grades'] = 'Grades';
$string['feature_grades_desc'] = 'Your grades in every course';
$string['feature_logs'] = 'Logs';
$string['feature_logs_desc'] = 'Who did what, and when';
$string['feature_managecourses'] = 'Manage courses';
$string['feature_managecourses_desc'] = 'Courses and categories';
$string['feature_messages'] = 'Messages';
$string['feature_messages_desc'] = 'Private and group conversations';
$string['feature_mycourses'] = 'My courses';
$string['feature_mycourses_desc'] = 'Every course you are enrolled in';
$string['feature_notes'] = 'Notes';
$string['feature_notes_desc'] = 'Notes teachers keep about learners';
$string['feature_notifications'] = 'Notifications';
$string['feature_notifications_desc'] = 'Everything Moodle has told you';
$string['feature_participants'] = 'Participants';
$string['feature_participants_desc'] = 'People enrolled, their roles and groups';
$string['feature_passkeys'] = 'Passkeys';
$string['feature_passkeys_desc'] = 'Sign in without a password';
$string['feature_plans'] = 'Learning plans';
$string['feature_plans_desc'] = 'Competencies and plans assigned to you';
$string['feature_plugins'] = 'Plugins';
$string['feature_plugins_desc'] = 'Installed plugins and available updates';
$string['feature_preferences'] = 'Preferences';
$string['feature_preferences_desc'] = 'Language, forums, editor and calendar choices';
$string['feature_privatefiles'] = 'Private files';
$string['feature_privatefiles_desc'] = 'Files only you can see';
$string['feature_profile'] = 'Profile';
$string['feature_profile_desc'] = 'How other people see you';
$string['feature_questionbank'] = 'Question banks';
$string['feature_questionbank_desc'] = 'Questions for the quizzes in this course';
$string['feature_reports'] = 'Reports';
$string['feature_reports_desc'] = 'Custom reports you can view';
$string['feature_siteadmin'] = 'Site administration';
$string['feature_siteadmin_desc'] = 'Search every setting';
$string['feature_support'] = 'Contact site support';
$string['feature_support_desc'] = 'Ask the people who run this site';
$string['feature_tags'] = 'Tags';
$string['feature_tags_desc'] = 'Find anything by tag';
$string['feature_users'] = 'Users';
$string['feature_users_desc'] = 'Browse, add and edit accounts';
$string['feature_wording'] = 'Site wording';
$string['feature_wording_desc'] = 'Rename roles, activities and labels';
$string['featuregroup_account'] = 'Account';
$string['featuregroup_admin'] = 'Administration';
$string['featuregroup_content'] = 'Content';
$string['featuregroup_course'] = 'This course';
$string['featuregroup_learn'] = 'Learning';
$string['featuregroup_progress'] = 'Progress';
$string['featuregroup_talk'] = 'Talk';
$string['featuregroupintro_account'] = 'You, your sign-in and your data.';
$string['featuregroupintro_admin'] = 'For people who manage this site.';
$string['featuregroupintro_content'] = 'Files and reusable material.';
$string['featuregroupintro_course'] = 'Everything this course offers you.';
$string['featuregroupintro_learn'] = 'Your courses, deadlines and events.';
$string['featuregroupintro_progress'] = 'How you are doing, across every course.';
$string['featuregroupintro_talk'] = 'Conversations and shared writing.';
$string['featureoff'] = 'Off for this site';
$string['featureturnon'] = 'Turn on {$a}';
$string['featuresall'] = 'All';
$string['featuresbadpattern'] = 'That pattern is incomplete. Every feature stays listed until it is fixed.';
$string['featurescount'] = '{$a} features shown';
$string['featuresfind'] = 'Find a feature';
$string['featuresgroups'] = 'Feature groups';
$string['featuresguest'] = 'Sign in to see the features you can use.';
$string['featureslauncher'] = 'All Moodle features';
$string['featureslead'] = 'Every feature you can use on this site, in one place. Features the site has turned off stay listed so you know they exist.';
$string['featuresnone'] = 'No feature matches. Try another word, or switch off the pattern search.';
$string['featuresopenpage'] = 'Open the full page';
$string['featuresshowoff'] = 'Show turned-off features';
$string['featurestitle'] = 'Everything in Moodle';
$string['railnav'] = 'Main';
$string['railnew'] = 'New course';
$string['railsettings'] = 'Settings';
$string['railprofile'] = 'Your profile';
$string['rail_home'] = 'Home';
$string['rail_courses'] = 'Courses';
$string['rail_calendar'] = 'Calendar';
$string['rail_grades'] = 'Grades';
$string['rail_messages'] = 'Messages';
$string['rail_everything'] = 'Everything';
$string['featurescountone'] = '1 feature shown';
$string['featuregroup_more'] = 'More';
$string['featuregroupintro_more'] = 'Other places added by this site.';
$string['featureoffshort'] = 'Off';
$string['featureturnonshort'] = 'Turn on';
$string['featuresclose'] = 'Close';
$string['workspacetitle'] = 'Course workspace';
$string['workspacepanelindex'] = 'Course index';
$string['workspacepanelcourse'] = 'Course';
$string['workspacepanelblocks'] = 'Blocks';
$string['workspaceclose'] = 'Close';
$string['workspaceclosetab'] = 'Close {$a}';
$string['workspaceclosed'] = '{$a} closed. Use Search tabs to open it again.';
$string['workspacereopened'] = '{$a} opened again.';
$string['workspacesearchtabs'] = 'Search tabs';
$string['workspacesearchlabel'] = 'Find a tab';
$string['workspacesearchnone'] = 'No tab matches.';
$string['workspacesearchcount'] = '{$a} tabs shown.';
$string['workspacestateopen'] = 'Open';
$string['workspacestateclosed'] = 'Closed, press Enter to open';
$string['workspacelayout'] = 'Layout';
$string['workspaceresetlayout'] = 'Reset layout';
$string['workspaceclassic'] = 'Classic layout';
$string['workspaceuse'] = 'Use the workspace layout';
$string['workspacelayoutreset'] = 'Layout reset to the default.';
$string['workspacepin'] = 'Pin tab';
$string['workspaceunpin'] = 'Unpin tab';
$string['workspacepinned'] = 'Pinned';
$string['workspacelock'] = 'Lock group';
$string['workspaceunlock'] = 'Unlock group';
$string['workspacelocked'] = 'Locked';
$string['workspacemovenew'] = 'Move to new group';
$string['workspacetabgroup'] = 'Add to a new tab group';
$string['workspaceremovegroup'] = 'Remove from tab group';
$string['workspacegroupname'] = 'Tab group name';
$string['workspacegroupdefault'] = 'Group';
$string['workspacegroupcolour'] = 'Tab group colour';
$string['workspacecolourprimary'] = 'Primary';
$string['workspacecoloursecondary'] = 'Secondary';
$string['workspacecolourtertiary'] = 'Tertiary';
$string['workspacecolourerror'] = 'Error';
$string['workspacecoloursuccess'] = 'Success';
$string['workspacehelp'] = 'Ctrl+Alt+Arrow moves between groups. Ctrl+Tab or Ctrl+Alt+Page Down shows the next tab; add Shift for the previous one. Press Shift+F10 on a tab for its menu.';
$string['workspacemenu'] = 'Tab menu';
$string['workspacepinnednoclose'] = 'Pinned tabs cannot be closed. Unpin it first.';
$string['statechecking'] = 'Checking…';
$string['stateofflinebody'] = 'Moodle cannot save anything until the connection comes back. Keep this page open so your typing is not lost, then try again.';
$string['stateofflinetitle'] = 'You are offline';
$string['stateonline'] = 'You are back online.';
$string['stateretry'] = 'Try again';
