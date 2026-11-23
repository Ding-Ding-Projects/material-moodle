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
 * English strings for the bathroom pass.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bathroom pass';
$string['bathroompass:use'] = 'Take bathroom breaks';
$string['bathroompass:manage'] = 'Change the bathroom pass settings of a course';
$string['bathroompass:configurewebhooks'] = 'Change the webhook addresses that receive bathroom break events';
$string['bathroompass:viewbreaks'] = 'See who is out of the room and the bathroom break history';
$string['eventbreakstarted'] = 'Bathroom break started';
$string['eventbreakended'] = 'Bathroom break ended';
$string['strftimebreak'] = '%d %B %Y, %H:%M';
$string['durationfmt'] = '{$a->min} min {$a->sec} s';

$string['privacy:metadata:local_bpass_breaks'] = 'Each bathroom break a student took in a course.';
$string['privacy:metadata:local_bpass_breaks:userid'] = 'The student who took the break.';
$string['privacy:metadata:local_bpass_breaks:timeout'] = 'When the student left the room.';
$string['privacy:metadata:local_bpass_breaks:timein'] = 'When the student came back.';
$string['privacy:metadata:local_bpass_breaks:overallowance'] = 'Whether the break was beyond the pass allowance.';
$string['privacy:metadata:local_bpass_breaks:endedby'] = 'The teacher who marked the student as back, if any.';
$string['privacy:metadata:local_bpass_breaks:outstatus'] = 'The HTTP status of the webhook sent when the student left.';
$string['privacy:metadata:local_bpass_breaks:instatus'] = 'The HTTP status of the webhook sent when the student came back.';
$string['privacy:metadata:webhook'] = 'When a teacher sets a webhook address, the site sends it a message each time a student leaves for or comes back from a break, for example to a Home Assistant automation.';
$string['privacy:metadata:webhook:course'] = 'The course short name and id.';
$string['privacy:metadata:webhook:time'] = 'When the student left or came back, and how long the break took.';
$string['privacy:metadata:webhook:student'] = 'The student\'s first name, only when the teacher turns this on. Otherwise no student is named.';

$string['allowlocal'] = 'Allow webhooks to local network addresses';
$string['allowlocal_desc'] = 'Moodle blocks requests to private network addresses by default. Home Assistant usually lives on the local network, so turn this on to let the bathroom pass send webhooks there. This lets teachers who can change webhook addresses make the site send requests to any address your server can reach, so turn it on only if you trust them.';

// Student card.
$string['cardtitle'] = 'Bathroom pass';
$string['leavebutton'] = 'Leave for the bathroom';
$string['outofroom'] = 'Out of the room';
$string['backbutton'] = 'I\'m back';
$string['lefttext'] = '{$a->left} of {$a->total} left {$a->period}';
$string['noleft'] = 'No passes left {$a->period}';
$string['leftunlimited'] = 'Unlimited passes';
$string['iconslabel'] = '{$a->left} of {$a->total} passes left';
$string['period_week'] = 'this week';
$string['period_month'] = 'this month';
$string['period_quarter'] = 'this quarter';
$string['period_halfyear'] = 'this half year';
$string['period_course'] = 'in this course';
$string['periodword_week'] = 'week';
$string['periodword_month'] = 'month';
$string['periodword_quarter'] = 'quarter';
$string['periodword_halfyear'] = 'half year';
$string['periodword_course'] = 'course';
$string['humanright'] = 'You are out of passes, and you can still go. Using the bathroom is a human right and you will never be refused. No grade penalty, ever.';
$string['extratext'] = 'Each extra break this {$a->period} turns {$a->step}% of your heads chance on due date extension coin flips into a golden chance. Golden still grants the extension and shows you a dim sum.';
$string['barlabel'] = 'Coin flip chances: heads {$a->heads} percent, golden {$a->golden} percent, tails {$a->tails} percent';
$string['legendheads'] = 'Heads {$a}%';
$string['legendgolden'] = 'Golden {$a}%';
$string['legendtails'] = 'Tails {$a}%';
$string['outnote'] = 'Your teacher can see that you stepped out.';
$string['outnoteover'] = 'This break does not use a pass.';
$string['outnoteuses'] = 'This break uses one of your passes.';
$string['statusout'] = 'You are out of the room.';
$string['welcomeback'] = 'Welcome back. Break took {$a}.';
$string['busy'] = 'The site was busy. Please try again.';
$string['disabled'] = 'Bathroom passes are turned off in this course.';

// Teacher page.
$string['managetitle'] = 'Bathroom pass';
$string['settingstab'] = 'Settings';
$string['nowtab'] = 'Now and history';
$string['enabled'] = 'Show the bathroom pass to students';
$string['passes'] = 'Passes';
$string['passes_help'] = 'How many bathroom breaks each student has per period, from 0 to 100. A break is never refused: when the passes are used up the student can still go, and the break is recorded as beyond the allowance.';
$string['unlimited'] = 'Unlimited';
$string['period'] = 'Period';
$string['period_help'] = 'When the passes start again. Quarters are January to March, April to June, July to September and October to December. Half years are January to June and July to December. Periods follow the student\'s own time zone.';
$string['periodname_week'] = 'Week';
$string['periodname_month'] = 'Month';
$string['periodname_quarter'] = 'Quarter';
$string['periodname_halfyear'] = 'Half year';
$string['periodname_course'] = 'Whole course';
$string['goldenstep'] = 'Golden chance per extra break (%)';
$string['goldenstep_help'] = 'Each break beyond the allowance in the current period turns this percent of the heads chance on due date extension coin flips into a golden chance, up to 25 percent in total. Golden still grants the extension and shows the student a dim sum. Tails stays at 50 percent and heads never drops below 25 percent. Use 0 to turn this off. No grade is ever changed by bathroom breaks.';
$string['goldenstepinvalid'] = 'Enter a number from 0 to 25.';
$string['webhooks'] = 'Webhooks';
$string['webhooksintro'] = 'Call a web address when a student leaves and when they come back, for example a Home Assistant webhook automation. A webhook that fails never blocks a break.';
$string['webhookout'] = 'Webhook when a student leaves';
$string['webhookout_help'] = 'An http or https address that receives a JSON message with the event "left", the course, the time and whether the break was beyond the allowance.';
$string['webhookin'] = 'Webhook when a student is back';
$string['webhookin_help'] = 'An http or https address that receives a JSON message with the event "back", the course, the time, whether the break was beyond the allowance and how many seconds it took.';
$string['webhookinvalid'] = 'Enter a full http or https address.';
$string['includename'] = 'Send the student\'s first name';
$string['includename_help'] = 'When on, webhook messages include the first name of the student. When off, no student is named.';
$string['sendtest'] = 'Send test';
$string['testok'] = 'The test message was delivered. The webhook answered with HTTP status {$a}.';
$string['testfailed'] = 'The webhook answered with HTTP status {$a}.';
$string['testnoresponse'] = 'The test message could not be delivered. The address did not answer, or the site is not allowed to send requests to local network addresses.';
$string['saved'] = 'Settings saved.';
$string['outnow'] = 'Out of the room now';
$string['nooneout'] = 'Nobody is out of the room.';
$string['history'] = 'History';
$string['historyempty'] = 'No bathroom breaks have been taken yet.';
$string['student'] = 'Student';
$string['left'] = 'Left';
$string['back'] = 'Back';
$string['outfor'] = 'Out for';
$string['duration'] = 'Duration';
$string['overallowance'] = 'Over allowance';
$string['leavewebhook'] = 'Leave webhook';
$string['backwebhook'] = 'Back webhook';
$string['markasback'] = 'Mark as back';
$string['markedback'] = 'The student was marked as back.';
$string['markedbyteacher'] = '(marked by a teacher)';
$string['statusnone'] = 'Not sent';
$string['statusok'] = 'Delivered ({$a})';
$string['statusfailed'] = 'Failed ({$a})';
