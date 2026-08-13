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
 * Admin settings for local_ai_coursecreator.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Course-generation page under Site Administration → Courses ────────────────
// Visible to anyone holding the generate capability who can access the admin area.
$ADMIN->add('courses', new admin_externalpage(
    'local_ai_coursecreator_generate',
    get_string('pluginname', 'local_ai_coursecreator'),
    new moodle_url('/local/ai_coursecreator/index.php'),
    'local/ai_coursecreator:generate'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ai_coursecreator',
        get_string('pluginname', 'local_ai_coursecreator')
    );

    $ADMIN->add('localplugins', $settings);

    // Stream endpoint URL (full URL including tenant path).
    $settings->add(new admin_setting_configtext(
        'local_ai_coursecreator/stream_url',
        get_string('settings_stream_url', 'local_ai_coursecreator'),
        get_string('settings_stream_url_desc', 'local_ai_coursecreator'),
        '',
        PARAM_URL
    ));

    // API Key (Bearer token — stored masked).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_coursecreator/api_key',
        get_string('settings_api_key', 'local_ai_coursecreator'),
        get_string('settings_api_key_desc', 'local_ai_coursecreator'),
        ''
    ));

    // Stream timeout (seconds).
    $settings->add(new admin_setting_configtext(
        'local_ai_coursecreator/stream_timeout',
        get_string('settings_stream_timeout', 'local_ai_coursecreator'),
        get_string('settings_stream_timeout_desc', 'local_ai_coursecreator'),
        '600',
        PARAM_INT
    ));

    // System prompt (optional prefix prepended to every teacher submission).
    $settings->add(new admin_setting_configtextarea(
        'local_ai_coursecreator/system_prompt',
        get_string('settings_system_prompt', 'local_ai_coursecreator'),
        get_string('settings_system_prompt_desc', 'local_ai_coursecreator'),
        '',
        PARAM_TEXT
    ));


    // Diagnostics panel ─────────────────────────────────────────────────
    // Rendered via a Mustache template (templates/diagnostics_panel.mustache) and
    // an AMD module (amd/src/diagnostics.js) rather than embedded raw HTML/inline JS.
    $connurl = (new moodle_url('/local/ai_coursecreator/generate.php', ['action' => 'test_connection']))->out(false);

    $PAGE->requires->js_call_amd('local_ai_coursecreator/diagnostics', 'init', [
        ['testUrl' => $connurl, 'sesskey' => sesskey()],
    ]);

    $diaghtml = $OUTPUT->render_from_template('local_ai_coursecreator/diagnostics_panel', []);

    $settings->add(new admin_setting_description(
        'local_ai_coursecreator/diagnostics',
        get_string('settings_diagnostics_heading', 'local_ai_coursecreator'),
        $diaghtml
    ));
}
