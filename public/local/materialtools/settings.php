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
 * Material tools in Site administration.
 *
 * @package    local_materialtools
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/materialtools:createstudents', context_system::instance())) {
    $c = 'local_materialtools';
    $ADMIN->add('users', new admin_category('local_materialtools', get_string('pluginname', $c)));
    $ADMIN->add('local_materialtools', new admin_externalpage('local_materialtools_newstudent',
        get_string('newstudent', $c), new moodle_url('/local/materialtools/newstudent.php'),
        'local/materialtools:createstudents'));
    $ADMIN->add('local_materialtools', new admin_externalpage('local_materialtools_vocabulary',
        get_string('vocabulary', $c), new moodle_url('/local/materialtools/vocabulary.php'),
        'local/materialtools:managevocabulary'));
    $ADMIN->add('local_materialtools', new admin_externalpage('local_materialtools_titles',
        get_string('titles', $c), new moodle_url('/local/materialtools/titles.php'), 'moodle/role:assign'));
    $ADMIN->add('local_materialtools', new admin_externalpage('local_materialtools_wording',
        get_string('wording', $c), new moodle_url('/local/materialtools/wording.php'), 'moodle/site:config'));

    $page = new admin_settingpage('local_materialtools_settings', get_string('studentsettings', $c),
        'local/materialtools:createstudents');
    if ($ADMIN->fulltree) {
        $page->add(new admin_setting_configtextarea("$c/discordtemplate", get_string('discordtemplate', $c),
            get_string('discordtemplate_desc', $c), \local_materialtools\student_creator::DEFAULT_TEMPLATE, PARAM_RAW, 60, 12));
        $page->add(new admin_setting_configtextarea("$c/quickposts", get_string('quickposts', $c),
            get_string('quickposts_desc', $c), "Dropbox|assign", PARAM_RAW, 40, 5));
        $page->add(new admin_setting_configselect("$c/passwordstyle", get_string('passwordstyle', $c),
            get_string('passwordstyle_desc', $c), 'words', [
                'words' => get_string('passwordwords', $c),
                'random' => get_string('passwordrandom', $c),
            ]));
        $page->add(new admin_setting_configtext("$c/passwordlength", get_string('passwordlength', $c),
            get_string('passwordlength_desc', $c), 14, PARAM_INT));
        $roles = [];
        $allnames = role_get_names(null, ROLENAME_ALIAS, true);
        foreach (get_roles_for_contextlevels(CONTEXT_COURSE) as $roleid) {
            if (isset($allnames[$roleid])) {
                $roles[$roleid] = $allnames[$roleid];
            }
        }
        $student = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $page->add(new admin_setting_configselect("$c/studentrole", get_string('studentrole', $c),
            get_string('studentrole_desc', $c), $student ?: '', $roles ?: ['' => '-']));
    }
    $ADMIN->add('local_materialtools', $page);
}
