// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module for the local_ai_coursecreator admin settings diagnostics panel.
 *
 * Handles the "Test API Connection" button click and result rendering.
 *
 * @module     local_ai_coursecreator/diagnostics
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'core/notification'], function(Str, Notification) {

    'use strict';

    return {
        /**
         * Initialise the diagnostics panel.
         *
         * Called by settings.php via js_call_amd.
         *
         * @param {Object} config          Configuration object from the server.
         * @param {string} config.testUrl  URL of the test_connection endpoint.
         * @param {string} config.sesskey  Moodle session key.
         */
        init: function(config) {
            var btn = document.getElementById('aicc-conn-btn');
            var out = document.getElementById('aicc-diag-out');
            if (!btn || !out) {
                return;
            }

            // Resolved once, up front, rather than inside the click handler's own
            // promise chain, so the fetch chain below isn't lexically nested inside
            // this .then() callback.
            var connecting = '';
            var fetchErrPrefix = '';

            Str.get_strings([
                {key: 'diag_connecting', component: 'local_ai_coursecreator'},
                {key: 'diag_fetch_error_prefix', component: 'local_ai_coursecreator'},
            ]).then(function(strings) {
                connecting = strings[0];
                fetchErrPrefix = strings[1];
                return null;
            }).catch(Notification.exception);

            btn.addEventListener('click', function() {
                out.textContent = connecting;
                fetch(config.testUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sesskey=' + encodeURIComponent(config.sesskey),
                })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(d) {
                        out.textContent = JSON.stringify(d, null, 2);
                        return null;
                    })
                    .catch(function(e) {
                        out.textContent = fetchErrPrefix + e;
                    });
            });
        },
    };
});
