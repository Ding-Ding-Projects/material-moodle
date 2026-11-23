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
 * English strings for due date extensions.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Due date extensions';
$string['dueextensions:use'] = 'Use due date extensions';
$string['dueextensions:manage'] = 'Change due date extension rules and revoke extensions';
$string['dueextensions:viewlog'] = 'See the due date extension log';
$string['eventextensionused'] = 'Due date extension used';
$string['privacy:metadata:local_dueext_requests'] = 'Each due date extension a student used and its outcome.';
$string['privacy:metadata:local_dueext_requests:userid'] = 'The student who used the extension.';
$string['privacy:metadata:local_dueext_requests:cmid'] = 'The assignment.';
$string['privacy:metadata:local_dueext_requests:outcome'] = 'What the dice, coin or wheel landed on.';
$string['privacy:metadata:local_dueext_requests:days'] = 'Days added to the due date.';
$string['privacy:metadata:local_dueext_requests:penalty'] = 'Grade deduction from the wheel, in percent.';
$string['privacy:metadata:local_dueext_requests:timecreated'] = 'When the extension was used.';
$string['privacy:metadata:dimsum'] = 'The site fetches the public dim sum catalog index to show a dish after a golden coin. No user data is sent.';

$string['cardtitle'] = 'Due date extension';
$string['lefttext'] = '{$a->left} of {$a->total} left {$a->period}';
$string['leftunlimited'] = 'Unlimited extensions';
$string['period_week'] = 'this week';
$string['period_month'] = 'this month';
$string['period_course'] = 'in this course';
$string['currentdue'] = 'Your due date:';
$string['useextension'] = 'Use an extension';
$string['dialogtitle'] = 'Use a due date extension';
$string['button_instant'] = 'Extend my due date';
$string['button_dice'] = 'Roll the die';
$string['button_coin'] = 'Flip the coin';
$string['button_wheel'] = 'Spin the wheel';
$string['done'] = 'Done';
$string['fairnote'] = 'The result is decided fairly by the site before the animation starts.';
$string['deniedcountsnote'] = 'A denied attempt still uses one extension.';
$string['ruleinstant'] = 'Each extension adds {$a} days to your due date.';
$string['ruledice'] = 'Roll a {$a}-sided die: your due date moves by the number rolled.';
$string['rulecoin'] = 'Flip a coin: heads adds {$a} days, tails keeps your due date.';
$string['rulewheel'] = 'Spin the wheel: it decides the days added and any grade deduction.';
$string['oddstext'] = 'Heads {$a->heads}%, golden {$a->golden}%, tails {$a->tails}%';
$string['rolled'] = 'You rolled {$a}.';
$string['heads'] = 'Heads.';
$string['tails'] = 'Tails.';
$string['golden'] = 'Golden coin! Here is a dim sum for you.';
$string['resultgranted'] = 'Granted: {$a->days} more days. Your new due date is {$a->due}.';
$string['resultpenalty'] = '{$a}% will be taken off this assignment\'s grade.';
$string['resultdenied'] = 'Not granted this time. Your due date stays the same.';
$string['resultdeniedcounted'] = 'Not granted this time, and this attempt used one extension. Your due date stays the same.';

$string['blockoff'] = 'Due date extensions are turned off in this course.';
$string['blocknodue'] = 'This assignment has no due date, so there is nothing to extend.';
$string['blocknosubmit'] = 'You cannot submit to this assignment.';
$string['blocksubmitted'] = 'You have already submitted this assignment.';
$string['blockpastdue'] = 'Extensions are used before the due date, and this one has passed. Ask your teacher.';
$string['blocknoneleft'] = 'No extensions left. The next one arrives on {$a}.';
$string['busy'] = 'Another extension request is still being handled. Try again in a moment.';

$string['managetitle'] = 'Due date extensions';
$string['settingstab'] = 'Rules';
$string['logtab'] = 'Log';
$string['enabled'] = 'Students can use due date extensions';
$string['allowance'] = 'Extensions per student';
$string['allowance_help'] = 'How many extensions each student can use in each period. The default is 3 per month.';
$string['unlimited'] = 'Unlimited';
$string['period'] = 'Period';
$string['periodweek'] = 'Week';
$string['periodmonth'] = 'Month';
$string['periodcourse'] = 'Whole course';
$string['mode'] = 'How an extension is decided';
$string['modeinstant'] = 'Instant: always granted';
$string['modedice'] = 'Dice: days equal the side rolled';
$string['modecoin'] = 'Coin: heads granted, tails denied';
$string['modewheel'] = 'Wheel: days and grade deduction';
$string['instantdays'] = 'Days added';
$string['dicesides'] = 'Die';
$string['dicesidesn'] = '{$a} sides';
$string['coindays'] = 'Days added on heads';
$string['wheel'] = 'Wheel segments';
$string['wheel_help'] = 'One segment per line: days, then the grade deduction in percent, for example "3, 5". Use 0 days for a denied segment. Between 2 and 12 segments.';
$string['wheelinvalid'] = 'Enter between 2 and 12 lines, each with days and a deduction, such as "3, 5".';
$string['deniedcounts'] = 'A denied attempt uses one extension';
$string['deniedcounts_help'] = 'On: a denied attempt uses one extension and the assignment is graded as usual. Off: a denied attempt keeps the extension for a later assignment, and this assignment is marked 0. Students see which rule applies before they try.';
$string['keepnote'] = 'If this attempt is denied, you keep the extension for a later assignment, but this assignment will be marked 0.';
$string['resultdeniedzero'] = 'Not granted. You keep your extension for a later assignment, and this assignment is marked 0.';
$string['saved'] = 'Rules saved.';
$string['logempty'] = 'No extensions have been used in this course yet.';
$string['logstudent'] = 'Student';
$string['logassignment'] = 'Assignment';
$string['logmode'] = 'Mode';
$string['logresult'] = 'Result';
$string['logdays'] = 'Days';
$string['logpenalty'] = 'Deduction';
$string['logtime'] = 'Time';
$string['loggranted'] = 'Granted';
$string['logdenied'] = 'Denied';
$string['loggolden'] = 'Golden';
$string['logrevoked'] = 'Revoked';
$string['revoke'] = 'Revoke';
$string['revokeconfirm'] = 'Revoke this extension? The due date goes back to what it was before it, and the attempt no longer counts.';
$string['revoked'] = 'Extension revoked.';
$string['strftimedue'] = '%A, %d %B %Y, %H:%M';
