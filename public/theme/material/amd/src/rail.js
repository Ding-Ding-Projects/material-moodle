// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
/**
 * Navigation rail: the Everything destination opens the features launcher when it is on the page.
 *
 * @module     theme_material/rail
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {init: function(rail) {
        if (!rail || rail.dataset.ready) {
            return;
        }
        rail.dataset.ready = 'true';
        var everything = rail.querySelector('[data-rail="everything"]');
        if (everything) {
            everything.addEventListener('click', function(e) {
                // Modified clicks keep their usual meaning, such as opening the page in a new tab.
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
                    return;
                }
                var toggle = document.querySelector('.mm-launcher-toggle');
                if (toggle && toggle.offsetParent !== null) {
                    e.preventDefault();
                    toggle.click();
                }
            });
        }
    }};
});
