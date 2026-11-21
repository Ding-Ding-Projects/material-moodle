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
namespace auth_passkey;

/**
 * Passkey manager tests: people can only list, rename and remove their own passkeys.
 *
 * @package    auth_passkey
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \auth_passkey\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Store a fake passkey row.
     *
     * @param int $userid
     * @param string $name
     * @return int
     */
    private function add_key(int $userid, string $name): int {
        global $DB;
        $id = random_bytes(16);
        return $DB->insert_record(manager::TABLE, (object) ['userid' => $userid, 'credhash' => hash('sha256', $id),
            'credentialid' => base64_encode($id), 'publickey' => 'key', 'signcount' => 0, 'name' => $name,
            'timecreated' => time(), 'timelastused' => 0]);
    }

    /**
     * Listing returns only the person's own keys, oldest first.
     */
    public function test_list_for_user(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $this->add_key($a->id, 'Laptop');
        $this->add_key($a->id, 'Phone');
        $this->add_key($b->id, 'Tablet');
        $names = array_map(fn($k) => $k->name, manager::list_for_user($a->id));
        $this->assertSame(['Laptop', 'Phone'], $names);
    }

    /**
     * Renaming trims, ignores blanks and never touches another person's key.
     */
    public function test_rename(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $key = $this->add_key($a->id, 'Laptop');
        manager::rename($a->id, $key, '  Work laptop  ');
        $this->assertSame('Work laptop', $DB->get_field(manager::TABLE, 'name', ['id' => $key]));
        manager::rename($a->id, $key, '   ');
        $this->assertSame('Work laptop', $DB->get_field(manager::TABLE, 'name', ['id' => $key]));
        manager::rename($b->id, $key, 'Stolen');
        $this->assertSame('Work laptop', $DB->get_field(manager::TABLE, 'name', ['id' => $key]));
    }

    /**
     * Removing needs the owner's id.
     */
    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $key = $this->add_key($a->id, 'Laptop');
        manager::delete($b->id, $key);
        $this->assertTrue($DB->record_exists(manager::TABLE, ['id' => $key]));
        manager::delete($a->id, $key);
        $this->assertFalse($DB->record_exists(manager::TABLE, ['id' => $key]));
    }
}
