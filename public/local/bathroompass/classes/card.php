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
 * The state of the student's bathroom pass card, shared by the page and the web service functions.
 *
 * @package    local_bathroompass
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card {
    /** @var int Most pass icons drawn. */
    public const MAX_ICONS = 10;

    /**
     * The card's state for one student.
     *
     * @param int $courseid
     * @param int $userid
     * @param \stdClass|null $ended The break that was just ended, if any.
     * @return array
     */
    public static function state(int $courseid, int $userid, ?\stdClass $ended = null): array {
        $c = 'local_bathroompass';
        $settings = settings::get($courseid);
        $open = breaks::open_break($courseid, $userid);
        $left = ledger::remaining($settings, $userid);
        $golden = min(settings::MAX_STEP, $settings->goldenstep * ledger::extra($settings, $userid));
        $heads = 50 - $golden;
        $tails = 50;
        $period = get_string('period_' . $settings->period, $c);

        $icons = [];
        if ($left !== null) {
            for ($i = 0; $i < min($settings->passes, self::MAX_ICONS); $i++) {
                $icons[] = ['full' => $i < $left];
            }
        }
        if ($left === null) {
            $lefttext = get_string('leftunlimited', $c);
            $iconslabel = $lefttext;
        } else {
            $lefttext = get_string($left > 0 ? 'lefttext' : 'noleft', $c,
                (object) ['left' => $left, 'total' => $settings->passes, 'period' => $period]);
            $iconslabel = get_string('iconslabel', $c, (object) ['left' => $left, 'total' => $settings->passes]);
        }

        $outnote = '';
        if ($open) {
            $outnote = get_string('outnote', $c) . ' ' . get_string($open->overallowance ? 'outnoteover' : 'outnoteuses', $c);
        }
        $durationtext = '';
        $message = '';
        if ($ended) {
            $durationtext = breaks::duration_text((int) $ended->timein - (int) $ended->timeout);
            $message = get_string('welcomeback', $c, $durationtext);
        }
        return [
            'courseid' => $courseid,
            'out' => (bool) $open,
            'breakid' => $open ? (int) $open->id : 0,
            'timeout' => $open ? (int) $open->timeout : 0,
            'now' => time(),
            'left' => $left === null ? -1 : $left,
            'total' => $settings->passes,
            'humanright' => $left !== null && $left <= 0,
            'lefttext' => $lefttext,
            'iconslabel' => $iconslabel,
            'icons' => $icons,
            'humanrighttext' => get_string('humanright', $c),
            'extratext' => get_string('extratext', $c,
                (object) ['period' => get_string('periodword_' . $settings->period, $c), 'step' => $settings->goldenstep]),
            'heads' => $heads,
            'golden' => $golden,
            'tails' => $tails,
            'barlabel' => get_string('barlabel', $c, (object) ['heads' => $heads, 'golden' => $golden, 'tails' => $tails]),
            'legendheads' => get_string('legendheads', $c, $heads),
            'legendgolden' => get_string('legendgolden', $c, $golden),
            'legendtails' => get_string('legendtails', $c, $tails),
            'outnote' => $outnote,
            'durationtext' => $durationtext,
            'message' => $message,
        ];
    }

    /**
     * Template data for the card on the course page.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function context(int $courseid, int $userid): array {
        $state = self::state($courseid, $userid);
        $state['state'] = $state['out'] ? 'out' : 'normal';
        $state['unlimited'] = $state['left'] === -1;
        $state['humanrightattr'] = $state['humanright'] ? 'true' : 'false';
        return $state;
    }
}
