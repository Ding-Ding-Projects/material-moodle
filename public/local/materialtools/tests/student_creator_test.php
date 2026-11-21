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
 * Student creation tests: forced password change, passkey offer and the ready-to-paste message.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_materialtools\student_creator
 */
final class student_creator_test extends \advanced_testcase {
    /**
     * Generated passwords are long enough and differ each time.
     */
    public function test_generate_password(): void {
        $this->resetAfterTest();
        $a = student_creator::generate_password();
        $b = student_creator::generate_password();
        $this->assertGreaterThanOrEqual(8, strlen($a));
        $this->assertNotSame($a, $b);
    }

    /**
     * Suggested usernames are lower case and avoid names already taken.
     */
    public function test_suggest_username(): void {
        $this->resetAfterTest();
        $first = student_creator::suggest_username('Maya', 'Thompson');
        $this->assertSame(\core_text::strtolower($first), $first);
        $this->getDataGenerator()->create_user(['username' => $first]);
        $this->assertNotSame($first, student_creator::suggest_username('Maya', 'Thompson'));
    }

    /**
     * A new student must change the password, is offered a passkey and gets a placeholder address when none is given.
     */
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = student_creator::create((object) ['firstname' => 'Noah', 'lastname' => 'Williams', 'email' => '',
            'username' => 'NoahW', 'password' => 'Temp-Pass-123!', 'title' => 'Sous chef', 'vocabulary' => 1]);
        $this->assertSame('noahw', $user->username);
        $this->assertSame('noahw@students.invalid', $user->email);
        $this->assertEquals(1, get_user_preferences('auth_forcepasswordchange', 0, $user->id));
        $this->assertEquals(1, get_user_preferences('auth_passkey_offer', 0, $user->id));
        $this->assertEquals(1, get_user_preferences(vocabulary::PREF, 0, $user->id));
        $this->assertTrue($DB->record_exists('user', ['id' => $user->id, 'emailstop' => 1]));
    }

    /**
     * The message puts the username and the password each in their own code block.
     */
    public function test_message(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['username' => 'maya', 'firstname' => 'Maya']);
        $message = student_creator::message($user, 'Secret-123');
        $this->assertMatchesRegularExpression('/```\s*\nmaya\s*\n```/', $message);
        $this->assertMatchesRegularExpression('/```\s*\nSecret-123\s*\n```/', $message);
    }
}
