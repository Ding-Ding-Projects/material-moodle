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
namespace local_materialtools;

/**
 * Vocabulary pack validation, storage and audience tests.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_materialtools\vocabulary
 * @covers     \local_materialtools\strict_json
 */
final class vocabulary_test extends \advanced_testcase {
    /**
     * Packs that must be rejected, with the reason shown to the person.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        return [
            'empty' => ['', 'vocaberrorempty'],
            'not json' => ['{schemaVersion: 1}', 'vocaberrorsyntax'],
            'array root' => ['[1, 2]', 'vocaberrorshape'],
            'wrong version' => ['{"schemaVersion": 2, "entries": {"a": "b"}}', 'vocaberrorversion'],
            'duplicate key' => ['{"schemaVersion": 1, "entries": {"a": "b", "a": "c"}}', 'vocaberrorduplicate'],
            'number value' => ['{"schemaVersion": 1, "entries": {"a": 3}}', 'vocaberrorvalue'],
            'blank key' => ['{"schemaVersion": 1, "entries": {"  ": "x"}}', 'vocaberrorkey'],
            'no entries' => ['{"schemaVersion": 1, "entries": {}}', 'vocaberrorempty'],
            'too deep' => ['{"schemaVersion": 1, "entries": {"a": "b"}, "x": [[[[[[[[[1]]]]]]]]]}', 'vocaberrordepth'],
            'trailing text' => ['{"schemaVersion": 1, "entries": {"a": "b"}} x', 'vocaberrorsyntax'],
        ];
    }

    /**
     * Invalid packs fail with the right reason.
     *
     * @dataProvider invalid_provider
     * @param string $json
     * @param string $reason
     */
    public function test_invalid_packs_are_rejected(string $json, string $reason): void {
        try {
            vocabulary::validate($json);
            $this->fail('The pack should have been rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame($reason, $e->errorcode);
        }
    }

    /**
     * Oversized packs are rejected before parsing.
     */
    public function test_oversized_pack_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        vocabulary::validate('{"schemaVersion": 1, "entries": {"a": "' . str_repeat('x', vocabulary::MAX_BYTES) . '"}}');
    }

    /**
     * A valid pack keeps its entries and name; a byte order mark is allowed.
     */
    public function test_valid_pack(): void {
        $result = vocabulary::validate("\xEF\xBB\xBF" . '{"schemaVersion": 1, "name": "Kitchen words", ' .
            '"entries": {"Assignment": "Dropbox", "Teacher": "Coach"}}');
        $this->assertSame(2, $result['count']);
        $this->assertSame('Kitchen words', $result['name']);
        $this->assertSame('Coach', $result['entries']['Teacher']);
    }

    /**
     * The site pack reaches only the chosen audience, and never guests.
     */
    public function test_audience(): void {
        $this->resetAfterTest();
        vocabulary::store('{"schemaVersion": 1, "entries": {"Assignment": "Dropbox"}}');
        $this->assertTrue(vocabulary::present());
        $chosen = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        set_config('vocabaudience', 'nobody', 'local_materialtools');
        $this->assertFalse(vocabulary::eligible($chosen));

        set_config('vocabaudience', 'everyone', 'local_materialtools');
        $this->assertTrue(vocabulary::eligible($other));
        $this->assertFalse(vocabulary::eligible(guest_user()));

        set_config('vocabaudience', 'selected', 'local_materialtools');
        vocabulary::set_user($chosen->id, true);
        $this->assertTrue(vocabulary::eligible($chosen));
        $this->assertFalse(vocabulary::eligible($other));

        vocabulary::clear();
        $this->assertFalse(vocabulary::present());
        $this->assertFalse(vocabulary::eligible($chosen));
    }

    /**
     * A person's own pack is stored for them only and can be removed.
     */
    public function test_personal_pack(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        vocabulary::store_personal($user->id, '{"schemaVersion": 1, "entries": {"Teacher": "Sifu"}}');
        $this->assertSame(['Teacher' => 'Sifu'], vocabulary::personal_entries($user->id));
        $this->assertSame(1, vocabulary::personal_info($user->id)['count']);
        $this->assertSame([], vocabulary::personal_entries($other->id));

        set_config('allowpersonalvocab', 0, 'local_materialtools');
        $this->assertNull(vocabulary::personal_info($user->id));
        set_config('allowpersonalvocab', 1, 'local_materialtools');

        vocabulary::clear_personal($user->id);
        $this->assertNull(vocabulary::personal_info($user->id));
        $this->assertSame([], vocabulary::personal_entries($user->id));
    }
}
