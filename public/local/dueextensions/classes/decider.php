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
 * Decides an extension on the server with a cryptographically secure random source.
 * The browser only animates the result it is given.
 *
 * @package    local_dueextensions
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decider {
    /** @var int Tails chance on the coin, in percent. It never changes. */
    public const TAILS = 50;
    /** @var int The heads chance never drops below this, in percent. */
    public const MIN_HEADS = 25;

    /** @var callable|null Replaces random_int in tests. */
    public static $random = null;

    /**
     * A random integer between $min and $max inclusive.
     *
     * @param int $min
     * @param int $max
     * @return int
     */
    private static function rand(int $min, int $max): int {
        return self::$random ? (int) (self::$random)($min, $max) : random_int($min, $max);
    }

    /**
     * The coin's odds for a student, in percent. Extra bathroom breaks move part of the heads chance into
     * a golden chance; tails always stays at 50.
     *
     * @param int $courseid
     * @param int $userid
     * @return int[] heads, golden, tails
     */
    public static function coin_odds(int $courseid, int $userid): array {
        $golden = 0;
        // The bathroom pass is optional; ask it only when it is installed.
        if (\core_component::get_component_directory('local_bathroompass') !== null) {
            $golden = (int) component_callback('local_bathroompass', 'golden_chance', [$courseid, $userid], 0);
        }
        $golden = max(0, min(100 - self::TAILS - self::MIN_HEADS, $golden));
        return ['heads' => 100 - self::TAILS - $golden, 'golden' => $golden, 'tails' => self::TAILS];
    }

    /**
     * Decide one extension.
     *
     * @param \stdClass $rules
     * @param int $userid
     * @return array granted, days, penalty and outcome (what the animation shows)
     */
    public static function decide(\stdClass $rules, int $userid = 0): array {
        switch ($rules->mode) {
            case 'dice':
                $face = self::rand(1, $rules->dicesides);
                return ['granted' => true, 'days' => $face, 'penalty' => 0.0,
                    'outcome' => ['sides' => $rules->dicesides, 'face' => $face]];
            case 'coin':
                $odds = self::coin_odds((int) $rules->courseid, $userid);
                $roll = self::rand(1, 100);
                if ($roll <= $odds['heads']) {
                    $side = 'heads';
                } else if ($roll <= $odds['heads'] + $odds['golden']) {
                    $side = 'golden';
                } else {
                    $side = 'tails';
                }
                $granted = $side !== 'tails';
                $outcome = ['side' => $side, 'odds' => $odds];
                if ($side === 'golden') {
                    $outcome['dish'] = dimsum::pick();
                }
                return ['granted' => $granted, 'days' => $granted ? $rules->coindays : 0, 'penalty' => 0.0,
                    'outcome' => $outcome];
            case 'wheel':
                $index = self::rand(0, count($rules->wheel) - 1);
                $segment = $rules->wheel[$index];
                $granted = $segment['days'] > 0;
                return ['granted' => $granted, 'days' => $segment['days'], 'penalty' => $granted ? (float) $segment['penalty'] : 0.0,
                    'outcome' => ['segment' => $index, 'segments' => $rules->wheel]];
            default:
                return ['granted' => true, 'days' => $rules->instantdays, 'penalty' => 0.0, 'outcome' => []];
        }
    }
}
