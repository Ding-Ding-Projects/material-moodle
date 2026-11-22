<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
namespace local_materialtools;

/** Account-specific reading preferences and site defaults. */
final class display_preferences_test extends \advanced_testcase {
    public function test_choices_are_bounded_and_private_to_the_account(): void {
        $this->resetAfterTest();
        $reader = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($reader);
        $defaults = ['motion' => 'site', 'textsize' => 'site', 'spacing' => 'site'];
        $chosen = ['motion' => 'off', 'textsize' => '125', 'spacing' => 'relaxed'];
        set_user_preference(display_preferences::PREF, json_encode($chosen));
        $this->assertSame($chosen, display_preferences::current());
        $this->setUser($other);
        $this->assertSame($defaults, display_preferences::current());
        $this->assertSame($defaults, display_preferences::normalise(['textsize' => '500', 'motion' => 'extra']));
    }
}
