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
 * Drop zone and file picker on the vocabulary page.
 *
 * @module     local_materialtools/vocabadmin
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_materialtools/vocabulary'], function(Vocabulary) {
    return {
        /**
         * Start.
         *
         * @param {Object} config {endpoint, reload}
         */
        init: function(config) {
            var zone = document.querySelector('[data-region="vocab-drop"]');
            if (!zone) {
                return;
            }
            var input = zone.querySelector('[data-region="vocab-file"]');
            var status = zone.querySelector('[data-region="vocab-status"]');
            var send = function(file) {
                file.text().then(function(text) {
                    return fetch(config.endpoint, {method: 'POST', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json'}, body: text});
                }).then(function(r) {
                    return r.json();
                }).then(function(result) {
                    status.textContent = '';
                    var div = document.createElement('div');
                    div.className = 'alert ' + (result.success ? 'alert-success' : 'alert-danger');
                    div.dataset.mdCelebrated = '1';
                    div.textContent = result.success ? result.headline : result.error;
                    status.appendChild(div);
                    Vocabulary.announce(result.success ? result.headline : result.error, !!result.success);
                    if (result.success) {
                        setTimeout(function() {
                            window.location.href = config.reload;
                        }, 3200);
                    }
                    return null;
                }).catch(function() {
                    return null;
                });
            };
            input.addEventListener('change', function() {
                if (input.files[0]) {
                    send(input.files[0]);
                }
            });
            ['dragenter', 'dragover'].forEach(function(t) {
                zone.addEventListener(t, function(e) {
                    e.preventDefault();
                    zone.classList.add('is-over');
                });
            });
            ['dragleave', 'drop'].forEach(function(t) {
                zone.addEventListener(t, function() {
                    zone.classList.remove('is-over');
                });
            });
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                if (e.dataTransfer.files[0]) {
                    send(e.dataTransfer.files[0]);
                }
            });
        }
    };
});
