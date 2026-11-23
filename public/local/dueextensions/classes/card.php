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

namespace local_dueextensions;

/**
 * Template data for the student's extension card.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card {
    /**
     * Build the context.
     *
     * @param \assign $assign
     * @param \stdClass $rules
     * @param int $userid
     * @return array
     */
    public static function context(\assign $assign, \stdClass $rules, int $userid): array {
        $c = 'local_dueextensions';
        $remaining = ledger::remaining($rules, $userid);
        [, $end] = ledger::period($rules, $userid);
        $blocker = granter::blocker($assign, $rules, $userid);
        $tokens = [];
        if ($remaining !== null) {
            for ($i = 0; $i < $rules->allowance && $i < 12; $i++) {
                $tokens[] = ['used' => $i >= $remaining];
            }
        }
        $periodname = get_string('period_' . $rules->period, $c);
        $format = get_string('strftimedue', 'local_dueextensions');
        $due = granter::current_due($assign, $userid);
        $odds = $rules->mode === 'coin' ? decider::coin_odds((int) $rules->courseid, $userid) : null;
        return [
            'odds' => $odds,
            'oddstext' => $odds ? get_string('oddstext', $c, (object) $odds) : '',
            'cmid' => $assign->get_course_module()->id,
            'mode' => $rules->mode,
            'rule' => rules::describe($rules),
            'unlimited' => $remaining === null,
            'lefttext' => $remaining === null ? get_string('leftunlimited', $c)
                : get_string('lefttext', $c, (object) ['left' => $remaining, 'total' => $rules->allowance, 'period' => $periodname]),
            'tokens' => $tokens,
            'duetext' => $due ? userdate($due, $format) : '',
            'canuse' => $blocker === null,
            'blocker' => $blocker ? get_string($blocker, $c, $end ? userdate($end, get_string('strftimedate', 'langconfig')) : '') : '',
            'button' => get_string('button_' . $rules->mode, $c),
            'dicesides' => $rules->dicesides,
            'wheel' => json_encode($rules->wheel),
            'deniedcounts' => (bool) $rules->deniedcounts,
            // Only the coin and the wheel can deny an extension.
            'chance' => in_array($rules->mode, ['coin', 'wheel'], true),
        ];
    }
}
