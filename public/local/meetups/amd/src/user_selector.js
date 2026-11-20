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
 * Autocomplete transport for choosing people to invite.
 *
 * @module     local_meetups/user_selector
 * @copyright  2026 Ding Ding Projects
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    return {
        /**
         * Load matching people.
         *
         * @param {string} selector
         * @param {string} query
         * @param {Function} success
         * @param {Function} failure
         */
        transport: function(selector, query, success, failure) {
            Ajax.call([{methodname: 'local_meetups_search_users', args: {query: query}}])[0]
                .then(success)
                .catch(failure);
        },

        /**
         * Turn results into autocomplete options.
         *
         * @param {string} selector
         * @param {Array} results
         * @return {Array}
         */
        processResults: function(selector, results) {
            return results.map(function(user) {
                return {value: user.id, label: user.fullname};
            });
        }
    };
});
