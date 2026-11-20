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
