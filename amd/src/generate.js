// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module for local_ai_coursecreator.
 *
 * Handles SSE streaming, progress-row updates, result panel, and error banner.
 *
 * @module     local_ai_coursecreator/generate
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'core/templates', 'core/notification'], function (Str, Templates, Notification) {

    'use strict';

    /** @type {Object} Config injected by index.php via js_call_amd. */
    var cfg = {};

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Render the status_badge template into a row's status-badge cell.
     *
     * @param {Element} cell    The `.status-badge` element to replace.
     * @param {Object}  context Template context (one of {pending: true}, {running: true}, {done: true}).
     */
    function renderStatusBadge(cell, context)
    {
        Templates.render('local_ai_coursecreator/status_badge', context)
            .then(function (html, js) {
                Templates.replaceNodeContents(cell, html, js);
                return null;
            })
            .catch(Notification.exception);
    }

    /**
     * Format a byte count as a human-readable string.
     *
     * @param {number} bytes Number of bytes.
     * @returns {string} Formatted string (e.g. "1.2 KB").
     */
    function formatBytes(bytes)
    {
        if (bytes < 1024) {
            return bytes + ' B';
        } else if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    /**
     * Format elapsed milliseconds as a seconds string.
     *
     * @param {number} ms Elapsed time in milliseconds.
     * @returns {string} Formatted string (e.g. "3.5s").
     */
    function formatElapsed(ms)
    {
        return (ms / 1000).toFixed(1) + 's';
    }

    // ── Row state helpers ──────────────────────────────────────────────────

    /**
     * Set a progress table row to the running state (spinner badge).
     *
     * @param {string} rowId The DOM id of the table row element.
     */
    function rowSetRunning(rowId)
    {
        var row = document.getElementById(rowId);
        if (!row) {
            return;
        }
        renderStatusBadge(row.querySelector('.status-badge'), {running: true});
        row.querySelector('.detail-cell').textContent = '';
    }

    /**
     * Set a progress table row to the done state (success badge).
     *
     * @param {string} rowId  The DOM id of the table row element.
     * @param {string} detail Optional detail text to display.
     */
    function rowSetDone(rowId, detail)
    {
        var row = document.getElementById(rowId);
        if (!row) {
            return;
        }
        renderStatusBadge(row.querySelector('.status-badge'), {done: true});
        row.querySelector('.detail-cell').textContent = detail || '';
    }

    // ── Event handler ──────────────────────────────────────────────────────

    /**
     * Dispatch a single parsed SSE event to the appropriate UI update.
     *
     * @param {Object} msg        Parsed JSON event from the SSE stream.
     * @param {string} msg.event  Event type name (e.g. 'agent_start', 'done', 'error').
     */
    function handleEvent(msg)
    {
        var event = msg.event;

        switch (event) {
            case 'agent_start':
                rowSetRunning('row-agent-' + msg.agent);
                break;

            case 'agent_done': {
                var elapsed = formatElapsed(msg.elapsed_ms || 0);
                var usage   = msg.usage || {};
                var detail  = elapsed
                    + ' · ' + (usage.in  || 0) + '↑ '
                    + (usage.out || 0) + '↓';
                if (usage.cache) {
                    detail += ' ' + usage.cache + '↺';
                }
                rowSetDone('row-agent-' + msg.agent, detail);
                break;
            }

            case 'images_start':
                rowSetRunning('row-images');
                break;

            case 'images_done':
                rowSetDone('row-images', (msg.count || 0) + ' images generated');
                break;

            case 'build_start':
                rowSetRunning('row-build');
                break;

            case 'done': {
                var sizeBytes = msg.size_bytes || 0;
                rowSetDone('row-build', formatBytes(sizeBytes));

                document.getElementById('result-course-title').textContent =
                    msg.course_title || msg.safe_title || '';
                document.getElementById('result-file-size').textContent =
                    formatBytes(sizeBytes);

                document.getElementById('result-panel').classList.remove('d-none');
                document.getElementById('error-panel').classList.add('d-none');
                document.getElementById('generate-btn').disabled = false;
                break;
            }

            case 'error': {
                document.getElementById('error-message').textContent =
                    msg.message || 'Unknown error';
                document.getElementById('error-panel').classList.remove('d-none');
                document.getElementById('result-panel').classList.add('d-none');
                document.getElementById('generate-btn').disabled = false;
                break;
            }
        }
    }

    // ── SSE stream consumer ────────────────────────────────────────────────

    /**
     * Submit the generation form and consume the SSE stream.
     *
     * @param {string}    text          Source text from the textarea.
     * @param {HTMLElement|null} fileInput  The file input element (may be null).
     * @param {boolean}   includeImages Whether to request image generation.
     */
    function startGeneration(text, fileInput, includeImages)
    {
        // Reset UI.
        document.getElementById('result-panel').classList.add('d-none');
        document.getElementById('error-panel').classList.add('d-none');
        document.getElementById('generate-btn').disabled = true;

        ['row-agent-1', 'row-agent-2', 'row-agent-3', 'row-images', 'row-build'].forEach(function (id) {
            var row = document.getElementById(id);
            if (row) {
                renderStatusBadge(row.querySelector('.status-badge'), {pending: true});
                row.querySelector('.detail-cell').textContent = '';
            }
        });

        document.getElementById('progress-panel').classList.remove('d-none');

        var formData = new FormData();
        formData.append('sesskey', cfg.sesskey);
        formData.append('text', text);
        formData.append('include_images', includeImages ? '1' : '0');
        if (fileInput && fileInput.files) {
            for (var i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }
        }

        fetch(cfg.streamUrl, {
            method: 'POST',
            body: formData,
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            var reader  = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer  = '';

            /**
             * Read one chunk from the stream and schedule the next read.
             *
             * @returns {Promise} Resolves when the stream is exhausted.
             */
            function pump()
            {
                return reader.read().then(function (result) {
                    if (result.done) {
                        return;
                    }

                    buffer += decoder.decode(result.value, {stream: true});

                    var blocks = buffer.split('\n\n');
                    buffer = blocks.pop();

                    blocks.forEach(function (block) {
                        var dataLine = null;
                        block.split('\n').forEach(function (line) {
                            if (line.indexOf('data: ') === 0 && dataLine === null) {
                                dataLine = line.slice(6);
                            }
                        });
                        if (dataLine === null) {
                            return;
                        }
                        try {
                            handleEvent(JSON.parse(dataLine));
                        } catch (e) {
                            // Ignore malformed JSON lines.
                        }
                    });

                    return pump();
                });
            }

            return pump();
        }).catch(function (err) {
            document.getElementById('error-message').textContent = String(err);
            document.getElementById('error-panel').classList.remove('d-none');
            document.getElementById('generate-btn').disabled = false;
        });
    }

    // ── Public API ─────────────────────────────────────────────────────────

    return {
        /**
         * Initialise the course generator UI.
         *
         * Called by index.php via js_call_amd.
         *
         * @param {Object} config            Configuration object from the server.
         * @param {string} config.sesskey    Moodle session key.
         * @param {string} config.streamUrl  URL of the SSE streaming endpoint.
         * @param {string} config.downloadUrl URL of the download endpoint.
         * @param {string} config.restoreUrl  URL of the restore endpoint.
         */
        init: function (config) {
            cfg = config || {};

            var btn = document.getElementById('generate-btn');
            if (!btn) {
                return;
            }

            btn.addEventListener('click', function () {
                var textarea     = document.getElementById('source-text');
                var fileInput    = document.getElementById('source-files');
                var imagesChk    = document.getElementById('include-images');
                var text         = textarea ? textarea.value.trim() : '';
                var hasFiles     = fileInput && fileInput.files && fileInput.files.length > 0;
                var includeImages = imagesChk ? imagesChk.checked : false;

                if (!text && !hasFiles) {
                    textarea.focus();
                    return;
                }

                // Client-side byte-size guard for textarea text (512 KB).
                // File extractions are checked server-side (extracted size is unknown here).
                var MAX_BYTES = 524288;
                if (text && new TextEncoder().encode(text).length > MAX_BYTES) {
                    document.getElementById('error-message').textContent =
                        'Source text exceeds the 512 KB limit. Please shorten it or upload a file instead.';
                    document.getElementById('error-panel').classList.remove('d-none');
                    document.getElementById('result-panel').classList.add('d-none');
                    return;
                }

                startGeneration(text, fileInput, includeImages);
            });
        },

        /**
         * Handle a single parsed SSE event. Exposed for testing.
         *
         * @param {Object} msg       Parsed JSON event from the SSE stream.
         * @param {string} msg.event Event type name.
         */
        _handleEvent: handleEvent,
    };
});
