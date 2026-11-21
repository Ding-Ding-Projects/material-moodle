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
 * Material theme settings: every option is editable here, grouped in tabs.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingmaterial', get_string('configtitle', 'theme_material'));
    $reset = 'theme_reset_all_caches';
    $c = 'theme_material';

    // Look: colour, type, shape and motion.
    $page = new admin_settingpage('theme_material_look', get_string('tablook', $c));
    $s = new admin_setting_configcolourpicker("$c/seedcolor", get_string('seedcolor', $c),
        get_string('seedcolor_desc', $c), \theme_material\palette::DEFAULT_SEED);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $s = new admin_setting_configselect("$c/font", get_string('font', $c), get_string('font_desc', $c), 'robotoflex', [
        'robotoflex' => 'Roboto Flex',
        'inter' => 'Inter',
        'atkinson' => 'Atkinson Hyperlegible Next',
        'system' => get_string('fontsystem', $c),
    ]);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $choices = [];
    foreach ([0, 4, 8, 12, 16, 20, 24, 28, 32, 40] as $r) {
        $choices[$r] = $r . ' px';
    }
    $s = new admin_setting_configselect("$c/radius", get_string('radius', $c), get_string('radius_desc', $c), 24, $choices);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $s = new admin_setting_configselect("$c/density", get_string('density', $c), get_string('density_desc', $c), 'comfortable', [
        'comfortable' => get_string('densitycomfortable', $c),
        'compact' => get_string('densitycompact', $c),
    ]);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $page->add(new admin_setting_configselect("$c/motion", get_string('motion', $c), get_string('motion_desc', $c), 'expressive', [
        'expressive' => get_string('motionexpressive', $c),
        'calm' => get_string('motioncalm', $c),
        'off' => get_string('motionoff', $c),
    ]));
    $page->add(new admin_setting_configcheckbox("$c/pagetransitions", get_string('pagetransitions', $c),
        get_string('pagetransitions_desc', $c), 1));
    $page->add(new admin_setting_configcheckbox("$c/ripples", get_string('ripples', $c), get_string('ripples_desc', $c), 1));
    $page->add(new admin_setting_description("$c/colourmodeinfo", get_string('colourmodes', $c),
        get_string('colourmodes_desc', $c, (new moodle_url('/admin/settings.php', ['section' => 'themesettingboost']))->out())));
    $settings->add($page);

    // Sign-in page.
    $page = new admin_settingpage('theme_material_login', get_string('tablogin', $c));
    $page->add(new admin_setting_configtext("$c/loginheading", get_string('loginheading', $c),
        get_string('loginheading_desc', $c), get_string('loginheadingdefault', $c), PARAM_TEXT));
    $page->add(new admin_setting_configtextarea("$c/logintext", get_string('logintext', $c),
        get_string('logintext_desc', $c), get_string('logintextdefault', $c), PARAM_TEXT));
    $page->add(new admin_setting_configtext("$c/loginchips", get_string('loginchips', $c),
        get_string('loginchips_desc', $c), get_string('loginchipsdefault', $c), PARAM_TEXT));
    $s = new admin_setting_configstoredfile("$c/loginimage", get_string('loginimage', $c),
        get_string('loginimage_desc', $c), 'loginimage', 0, ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.png', '.webp']]);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $settings->add($page);

    // Features.
    $page = new admin_settingpage('theme_material_features', get_string('tabfeatures', $c));
    foreach (['commandpalette', 'regexbuilder', 'contextmenu', 'copybuttons', 'celebrations', 'notifications', 'provenance'] as $flag) {
        $page->add(new admin_setting_configcheckbox("$c/$flag", get_string($flag, $c), get_string($flag . '_desc', $c), 1));
    }
    $page->add(new admin_setting_configselect("$c/celebrationlevel", get_string('celebrationlevel', $c),
        get_string('celebrationlevel_desc', $c), 'dramatic', [
            'subtle' => get_string('celebrationsubtle', $c),
            'normal' => get_string('celebrationnormal', $c),
            'dramatic' => get_string('celebrationdramatic', $c),
        ]));
    $settings->add($page);

    // Advanced.
    $page = new admin_settingpage('theme_material_advanced', get_string('tabadvanced', $c));
    $s = new admin_setting_scsscode("$c/scsspre", get_string('rawscsspre', $c), get_string('rawscsspre_desc', $c), '', PARAM_RAW);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $s = new admin_setting_scsscode("$c/scss", get_string('rawscss', $c), get_string('rawscss_desc', $c), '', PARAM_RAW);
    $s->set_updatedcallback($reset);
    $page->add($s);
    $settings->add($page);
}
