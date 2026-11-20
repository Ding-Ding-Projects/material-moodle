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
 * Material theme callbacks.
 *
 * @package    theme_material
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/boost/lib.php');

/**
 * Main SCSS: the Boost default preset followed by the Material layer.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_material_get_main_scss_content($theme): string {
    global $CFG;
    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $scss .= "\n" . file_get_contents($CFG->dirroot . '/theme/material/scss/material.scss');
    $scss .= "\n" . file_get_contents($CFG->dirroot . '/theme/material/scss/expressive.scss');
    return $scss;
}

/**
 * SCSS variables that Bootstrap needs at compile time.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_material_get_pre_scss($theme): string {
    $settings = \theme_material\settings_values::from_theme($theme);
    $light = \theme_material\palette::scheme($settings->seed, false);
    $scss = '$primary: ' . $light['primary'] . ";\n";
    $scss .= '$success: ' . $light['success'] . ";\n";
    $scss .= '$danger: ' . $light['error'] . ";\n";
    $scss .= '$font-family-sans-serif: ' . $settings->fontstack . ";\n";
    $scss .= '$border-radius: ' . (int) round($settings->radius / 2) . "px;\n";
    $scss .= '$border-radius-lg: ' . (int) $settings->radius . "px;\n";
    $scss .= '$border-radius-sm: ' . max(4, (int) round($settings->radius / 3)) . "px;\n";
    $scss .= '$md-radius: ' . (int) $settings->radius . "px;\n";
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre . "\n";
    }
    return $scss;
}

/**
 * Generated palette, fonts and the administrator's own SCSS, appended after the main SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_material_get_extra_scss($theme): string {
    $settings = \theme_material\settings_values::from_theme($theme);
    $css = \theme_material\palette::css($settings->seed);
    $css .= \theme_material\settings_values::font_faces();
    $css .= ':root{--md-font:' . $settings->fontstack . ';--md-radius:' . (int) $settings->radius . 'px;'
        . '--md-radius-sm:' . max(4, (int) round($settings->radius / 2)) . 'px;}';
    if ($settings->density === 'compact') {
        $css .= ':root{--md-density:-4px;}';
    }
    $imageurl = $theme->setting_file_url('loginimage', 'loginimage');
    if (!empty($imageurl)) {
        $css .= 'body.pagelayout-login #page .login-layout-left{background-image:url("' . $imageurl . '");'
            . 'background-size:cover;background-position:center;}';
        $css .= 'body.pagelayout-login .material-login-shapes{display:none;}';
    } else {
        // Boost paints its own photo behind the login panel; the Material panel uses generated shapes instead.
        $css .= 'body.pagelayout-login #page .login-layout-left{background-image:none;}';
        $css .= 'body.pagelayout-login #page .login-layout-left::after{content:none;}';
    }
    if (!empty($theme->settings->scss)) {
        $css .= "\n" . $theme->settings->scss;
    }
    return $css;
}

/**
 * Serve the files saved in the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_material_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, ['loginimage', 'logo'])) {
        $theme = theme_config::load('material');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
