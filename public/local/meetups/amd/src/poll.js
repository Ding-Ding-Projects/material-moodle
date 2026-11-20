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
 * Scheduling poll: saves each answer straight away and keeps the tally live.
 *
 * @module     local_meetups/poll
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var REFRESH = 15000;

    /**
     * Show a new tally on the page.
     *
     * @param {Object} tally
     */
    function render(tally) {
        var voters = document.querySelector('.local-meetups-poll [data-region="voters"]');
        if (voters) {
            voters.textContent = tally.voters;
        }
        tally.options.forEach(function(option) {
            var row = document.querySelector('.local-meetups-option[data-optionid="' + option.id + '"]');
            if (!row) {
                return;
            }
            ['yes', 'ifneeded', 'no'].forEach(function(key) {
                var cell = row.querySelector('[data-count="' + key + '"]');
                if (cell && String(cell.textContent) !== String(option[key])) {
                    cell.textContent = option[key];
                    cell.classList.remove('local-meetups-bump');
                    // Restart the animation.
                    void cell.offsetWidth;
                    cell.classList.add('local-meetups-bump');
                }
            });
            var percent = tally.voters ? Math.round(100 * option.score / (2 * tally.voters)) : 0;
            var bar = row.querySelector('[data-region="bar"]');
            if (bar) {
                bar.style.width = percent + '%';
                bar.parentNode.setAttribute('aria-valuenow', percent);
            }
            row.classList.toggle('is-best', !!option.best);
        });
    }

    return {
        /**
         * Start the poll.
         *
         * @param {number} meetupid
         * @param {boolean} open Whether voting is open.
         */
        init: function(meetupid, open) {
            var live = document.querySelector('.local-meetups-poll [data-region="live"]');
            if (live) {
                live.hidden = false;
            }
            document.querySelectorAll('.local-meetups-poll [data-region="vote"] input[type="radio"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    var optionid = parseInt(radio.name.replace(/\D+/g, ''), 10);
                    var row = radio.closest('.local-meetups-option');
                    row.classList.add('is-saving');
                    Ajax.call([{
                        methodname: 'local_meetups_save_vote',
                        args: {meetupid: meetupid, optionid: optionid, response: parseInt(radio.value, 10)}
                    }])[0].then(function(tally) {
                        row.classList.remove('is-saving');
                        row.classList.add('is-saved');
                        setTimeout(function() {
                            row.classList.remove('is-saved');
                        }, 900);
                        render(tally);
                        return tally;
                    }).catch(function(error) {
                        row.classList.remove('is-saving');
                        Notification.exception(error);
                    });
                });
            });
            var form = document.querySelector('.local-meetups-poll [data-region="poll-form"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Answers are already saved as they change.
                    if (!e.submitter || !e.submitter.getAttribute('form')) {
                        e.preventDefault();
                    }
                });
            }
            if (!open) {
                return;
            }
            setInterval(function() {
                if (document.hidden) {
                    return;
                }
                Ajax.call([{methodname: 'local_meetups_get_tally', args: {meetupid: meetupid}}])[0]
                    .then(function(tally) {
                        render(tally);
                        return tally;
                    }).catch(function() {
                        return null;
                    });
            }, REFRESH);
        }
    };
});
