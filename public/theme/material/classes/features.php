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

namespace theme_material;

use context_course;
use context_system;
use context_user;
use moodle_url;

/**
 * The Moodle features a person can reach, grouped for the launcher and the All features page.
 *
 * Each feature is shown only when the person may open it. A feature the site has switched off stays
 * listed with that state, and people who can change the switch get a link to the exact setting.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class features {
    /** @var string[] Group keys in display order. */
    public const GROUPS = ['course', 'learn', 'progress', 'talk', 'content', 'account', 'admin'];

    /** @var array<string, string> Stroke icon paths on a 24 pixel grid, shared with the design boards. */
    private const ICONS = [
        'dashboard' => 'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
        'book' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 19V5',
        'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
        'search' => 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM16 16l4 4',
        'folder' => 'M3 6h6l2 2h10v11H3z',
        'chart' => 'M4 20V10M10 20V4M16 20v-8M22 20H2',
        'badge' => 'M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.9z',
        'target' => 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18zM12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8z',
        'report' => 'M5 3h14v18H5zM9 16v-3M12 16V9M15 16v-5',
        'message' => 'M4 5h16v11H8l-4 4z',
        'bell' => 'M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 20h4',
        'pen' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'note' => 'M5 3h10l4 4v14H5zM9 11h6M9 15h6',
        'tag' => 'M3 12V4h8l10 10-8 8zM7.5 7.5h.01',
        'box' => 'M3 7l9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10',
        'person' => 'M12 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8zM4 21a8 8 0 0 1 16 0',
        'gear' => 'M12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1' .
            'M4.9 19.1L7 17M17 7l2.1-2.1',
        'key' => 'M7.5 9a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9zM11 13L21 3M17 7l3 3',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'help' => 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18zM9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01',
        'people' => 'M9 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zM2 20a7 7 0 0 1 14 0M16 4a3.5 3.5 0 0 1 0 7M18 13.5a7 7 0 0 1 4 6.5',
        'personadd' => 'M9 4a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7zM2 20a7 7 0 0 1 14 0M19 8v6M16 11h6',
        'list' => 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
        'puzzle' => 'M4 8h4a2 2 0 1 1 4 0h4v4a2 2 0 1 1 0 4v4H4z',
        'type' => 'M4 6V4h16v2M12 4v16M9 20h6',
        'reuse' => 'M4 12a8 8 0 0 1 14-5.3M20 4v5h-5M20 12a8 8 0 0 1-14 5.3M4 20v-5h5',
        'question' => 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18zM12 17h.01M12 13a2 2 0 1 0-2-2',
    ];

    /**
     * Groups of features for the current person and page.
     *
     * @param \stdClass|null $course The course being viewed, or null outside a course.
     * @param bool $includeoff Whether to keep features the site has switched off.
     * @return array[] Each group has key, label, intro, count and items.
     */
    public static function groups(?\stdClass $course = null, bool $includeoff = true): array {
        if (!isloggedin() || isguestuser()) {
            return [];
        }
        $byGroup = array_fill_keys(self::GROUPS, []);
        foreach (self::definitions($course) as $feature) {
            if ($feature['off'] && !$includeoff) {
                continue;
            }
            $byGroup[$feature['group']][] = $feature;
        }
        $groups = [];
        foreach ($byGroup as $key => $items) {
            if (!$items) {
                continue;
            }
            $groups[] = [
                'key' => $key,
                'id' => 'mm-features-' . $key,
                'label' => get_string('featuregroup_' . $key, 'theme_material'),
                'intro' => get_string('featuregroupintro_' . $key, 'theme_material'),
                'count' => count($items),
                'items' => $items,
            ];
        }
        return $groups;
    }

    /**
     * Every feature definition that applies to this person.
     *
     * @param \stdClass|null $course
     * @return array[]
     */
    private static function definitions(?\stdClass $course): array {
        global $CFG, $USER;
        $system = context_system::instance();
        $usercontext = context_user::instance($USER->id);
        $out = [];
        $add = function(string $group, string $key, string $icon, moodle_url $url, bool $visible = true,
                ?string $offsetting = null) use (&$out, $system) {
            if (!$visible) {
                return;
            }
            $off = $offsetting !== null && empty(get_config('core', $offsetting));
            $item = [
                'group' => $group,
                'key' => $key,
                'label' => get_string('feature_' . $key, 'theme_material'),
                'desc' => get_string('feature_' . $key . '_desc', 'theme_material'),
                'icon' => self::ICONS[$icon],
                'where' => $url->out_as_local_url(false),
                'url' => $off ? '' : $url->out(false),
                'off' => $off,
                'enableurl' => '',
            ];
            if ($off && has_capability('moodle/site:config', $system)) {
                $item['enableurl'] = (new moodle_url('/admin/search.php', ['query' => $offsetting]))->out(false);
            }
            $out[] = $item;
        };

        if ($course && $course->id != SITEID) {
            $cc = context_course::instance($course->id);
            $id = $course->id;
            $add('course', 'participants', 'people', new moodle_url('/user/index.php', ['id' => $id]),
                has_capability('moodle/course:viewparticipants', $cc));
            $add('course', 'gradebook', 'chart', new moodle_url('/grade/report/index.php', ['id' => $id]),
                has_capability('moodle/grade:viewall', $cc) || has_capability('moodle/grade:view', $cc));
            $add('course', 'coursereports', 'report', new moodle_url('/report/view.php', ['courseid' => $id]),
                has_capability('moodle/site:viewreports', $cc));
            $add('course', 'questionbank', 'question', new moodle_url('/question/banks.php', ['courseid' => $id]),
                has_capability('moodle/question:viewall', $cc) || has_capability('moodle/question:viewmine', $cc));
            $add('course', 'coursebadges', 'badge',
                new moodle_url('/badges/index.php', ['type' => 2, 'id' => $id]),
                has_capability('moodle/badges:viewbadges', $cc), 'enablebadges');
            $add('course', 'coursereuse', 'reuse', new moodle_url('/backup/import.php', ['id' => $id]),
                has_capability('moodle/restore:restoretargetimport', $cc));
        }

        $add('learn', 'dashboard', 'dashboard', new moodle_url('/my/'), !empty($CFG->enabledashboard));
        $add('learn', 'mycourses', 'book', new moodle_url('/my/courses.php'));
        $add('learn', 'calendar', 'calendar', new moodle_url('/calendar/view.php', ['view' => 'month']));
        $add('learn', 'findcourse', 'search', new moodle_url('/course/search.php'));
        $add('learn', 'allcourses', 'folder', new moodle_url('/course/index.php'));

        $add('progress', 'grades', 'chart', new moodle_url('/grade/report/mygrades.php'));
        $add('progress', 'badges', 'badge', new moodle_url('/badges/mybadges.php'),
            has_capability('moodle/badges:manageownbadges', $usercontext), 'enablebadges');
        $add('progress', 'plans', 'target', new moodle_url('/admin/tool/lp/plans.php'));
        if (!\core_competency\api::is_enabled()) {
            self::mark_off($out, 'plans', 'enabled', 'core_competency');
        }
        $add('progress', 'reports', 'report', new moodle_url('/reportbuilder/index.php'),
            \core_reportbuilder\permission::can_view_reports_list(), 'enablecustomreports');

        $add('talk', 'messages', 'message', new moodle_url('/message/index.php'), true, 'messaging');
        $add('talk', 'notifications', 'bell', new moodle_url('/message/output/popup/notifications.php'));
        $add('talk', 'blogs', 'pen', new moodle_url('/blog/index.php'),
            has_capability('moodle/blog:view', $system), 'enableblogs');
        $add('talk', 'notes', 'note', new moodle_url('/notes/index.php', ['user' => $USER->id]), true, 'enablenotes');
        $add('talk', 'tags', 'tag', new moodle_url('/tag/search.php'), true, 'usetags');

        $add('content', 'privatefiles', 'folder', new moodle_url('/user/files.php'),
            has_capability('moodle/user:manageownfiles', $usercontext));
        $add('content', 'contentbank', 'box', new moodle_url('/contentbank/index.php'),
            has_capability('moodle/contentbank:access', $system));

        $add('account', 'profile', 'person', new moodle_url('/user/profile.php'));
        $add('account', 'preferences', 'gear', new moodle_url('/user/preferences.php'));
        $passkeys = is_enabled_auth('passkey') && file_exists($CFG->dirroot . '/auth/passkey/manage.php');
        $add('account', 'passkeys', 'key', new moodle_url('/auth/passkey/manage.php'), $passkeys);
        $add('account', 'datarequests', 'shield', new moodle_url('/admin/tool/dataprivacy/mydatarequests.php'),
            \core_component::get_component_directory('tool_dataprivacy') !== null);
        $add('account', 'support', 'help', new moodle_url('/user/contactsitesupport.php'),
            !isset($CFG->supportavailability) || (int)$CFG->supportavailability !== CONTACT_SUPPORT_DISABLED);

        $add('admin', 'siteadmin', 'gear', new moodle_url('/admin/search.php'), has_capability('moodle/site:config', $system));
        $add('admin', 'users', 'people', new moodle_url('/admin/user.php'), has_capability('moodle/user:update', $system));
        $add('admin', 'addstudent', 'personadd', new moodle_url('/local/materialtools/newstudent.php'),
            self::admin_page('local_materialtools_newstudent'));
        $add('admin', 'managecourses', 'book', new moodle_url('/course/management.php'),
            \core_course_category::has_capability_on_any(['moodle/category:manage', 'moodle/course:create']));
        $add('admin', 'logs', 'list', new moodle_url('/report/log/index.php', ['id' => SITEID]),
            has_capability('report/log:view', $system));
        $add('admin', 'plugins', 'puzzle', new moodle_url('/admin/plugins.php'), has_capability('moodle/site:config', $system));
        $add('admin', 'wording', 'type', new moodle_url('/local/materialtools/wording.php'),
            self::admin_page('local_materialtools_wording'));
        return $out;
    }

    /**
     * Mark a feature off because a plugin setting disables it.
     *
     * @param array $out
     * @param string $key
     * @param string $setting
     * @param string $plugin
     */
    private static function mark_off(array &$out, string $key, string $setting, string $plugin): void {
        foreach ($out as &$item) {
            if ($item['key'] === $key) {
                $item['off'] = true;
                $item['url'] = '';
                if (has_capability('moodle/site:config', context_system::instance())) {
                    $item['enableurl'] = (new moodle_url('/admin/search.php', ['query' => $plugin]))->out(false);
                }
            }
        }
    }

    /**
     * Whether the person can open an external administration page.
     *
     * @param string $name
     * @return bool
     */
    private static function admin_page(string $name): bool {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $page = admin_get_root(false, false)->locate($name);
        return $page && $page->check_access() && empty($page->hidden);
    }

    /**
     * Total features across groups.
     *
     * @param array[] $groups
     * @return int
     */
    public static function count(array $groups): int {
        return array_sum(array_column($groups, 'count'));
    }
}
