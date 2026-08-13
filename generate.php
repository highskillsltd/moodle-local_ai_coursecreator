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
 * SSE proxy, download, and restore endpoint for local_ai_coursecreator.
 *
 * Dispatches based on the `action` query parameter:
 *   stream          — POST source text; proxy external SSE stream to browser.
 *   download        — Send the cached MBZ file as an attachment download.
 *   restore         — Restore the cached MBZ into Moodle as a new course.
 *   test_connection — Probe the configured API endpoint; return JSON report.
 *   test_stream     — Emit fake SSE events to verify the PHP→browser pipeline.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- login/capability checked per action below.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use local_ai_coursecreator\ApiClient;
use local_ai_coursecreator\FileExtractor;

require_login();
$context = context_system::instance();
require_capability('local/ai_coursecreator:generate', $context);

$action = required_param('action', PARAM_ALPHAEXT);

// ACTION: stream.
if ($action === 'stream') {
    require_sesskey();

    $text = optional_param('text', '', PARAM_TEXT);

    // Extract text from any uploaded files and append to $text.
    if (!empty($_FILES['files']['tmp_name'])) {
        $fileparts = [];
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                continue;
            }
            $name      = $_FILES['files']['name'][$i];
            $ext       = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $extracted = FileExtractor::extract($tmp, $ext);
            if ($extracted !== '') {
                $fileparts[] = "=== {$name} ===\n{$extracted}";
            }
        }
        if (!empty($fileparts)) {
            $combined = implode("\n\n", $fileparts);
            $text     = $text !== '' ? $text . "\n\n" . $combined : $combined;
        }
    }

    // Measure teacher-provided content in bytes before system prompt is prepended.
    // Limit is enforced in the SSE section below so the error reaches the UI panel.
    $textbytes     = strlen($text);
    $includeimages = (bool) optional_param('include_images', 0, PARAM_INT);

    $systemprompt = trim(get_config('local_ai_coursecreator', 'system_prompt') ?? '');
    if ($systemprompt !== '') {
        $text = $systemprompt . "\n\n" . $text;
    }
    $client = new ApiClient();

    // Release the session write lock before the long-running stream so other
    // browser tabs are not blocked for the full ~90 s pipeline duration.
    \core\session\manager::write_close();

    // Clear all output buffering layers Moodle's bootstrap started so that
    // each flush() call sends bytes directly to the browser in real time.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_implicit_flush(true);   // Every echo auto-flushes; prevents re-buffering by hooks.

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: identity');   // Disable compression filters (Apache, IIS).

    if (!$client->is_configured()) {
        $msg = get_string('api_not_configured', 'local_ai_coursecreator');
        echo 'data: ' . json_encode(['event' => 'error', 'message' => $msg]) . "\n\n";
        flush();
        exit;
    }

    if (trim($text) === '') {
        $msg = get_string('upload_no_input', 'local_ai_coursecreator');
        echo 'data: ' . json_encode(['event' => 'error', 'message' => $msg]) . "\n\n";
        flush();
        exit;
    }

    if ($textbytes > 524288) {
        $msg = get_string('input_too_large', 'local_ai_coursecreator');
        echo 'data: ' . json_encode(['event' => 'error', 'message' => $msg]) . "\n\n";
        flush();
        exit;
    }

    $parsebuffer = '';
    $mbzdata     = null;

    $streamcallback = function (string $chunk) use (&$parsebuffer, &$mbzdata): void {
        echo $chunk;
        flush();

        if ($mbzdata !== null) {
            return;
        }

        $parsebuffer .= $chunk;
        $blocks      = explode("\n\n", $parsebuffer);
        $parsebuffer = array_pop($blocks);

        foreach ($blocks as $block) {
            $dataline = null;
            foreach (explode("\n", $block) as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $dataline = substr($line, 6);
                    break;
                }
            }
            if ($dataline === null) {
                continue;
            }

            $payload = json_decode($dataline, true);
            if (!is_array($payload) || ($payload['event'] ?? '') !== 'done') {
                continue;
            }

            $mbzb64 = $payload['mbz_b64'] ?? '';
            if ($mbzb64 === '') {
                continue;
            }

            $safetitle = clean_param($payload['safe_title'] ?? 'course', PARAM_FILE);
            if ($safetitle === '') {
                $safetitle = 'course';
            }

            $mbzdata = [
                'bytes'      => base64_decode($mbzb64, true),
                'safe_title' => $safetitle,
            ];
        }
    };

    try {
        $client->stream($text, $includeimages, $streamcallback);
    } catch (\Throwable $e) {
        echo 'data: ' . json_encode(['event' => 'error', 'message' => $e->getMessage()]) . "\n\n";
        flush();
        exit;
    }

    // Persist MBZ to the user private backup area and record the filename in a
    // meta JSON file.  We cannot write to $SESSION here because session_write_close()
    // was called above; the file API is DB-backed so it works without a session.
    if ($mbzdata !== null) {
        $filename    = $mbzdata['safe_title'] . '_' . time() . '.mbz';
        $usercontext = context_user::instance($USER->id);
        $fs          = get_file_storage();

        // Remove any pre-existing file with the same name to prevent a duplicate error.
        $existing = $fs->get_file($usercontext->id, 'user', 'backup', 0, '/', $filename);
        if ($existing) {
            $existing->delete();
        }

        $fs->create_file_from_string(
            [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea'  => 'backup',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $filename,
            ],
            $mbzdata['bytes']
        );

        file_put_contents(ApiClient::meta_path(), json_encode([
            'filename'   => $filename,
            'safe_title' => $mbzdata['safe_title'],
            'size_bytes' => strlen($mbzdata['bytes']),
        ]));
    }

    exit;
}

// ACTION: download.
if ($action === 'download') {
    require_sesskey();

    $metapath = ApiClient::meta_path();
    if (!file_exists($metapath)) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }
    $info = json_decode(file_get_contents($metapath), true);
    if (!$info || empty($info['filename'])) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }

    $usercontext = context_user::instance($USER->id);
    $fs   = get_file_storage();
    $file = $fs->get_file($usercontext->id, 'user', 'backup', 0, '/', $info['filename']);
    if (!$file) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }

    $filename = ($info['safe_title'] ?? 'course') . '.mbz';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $file->get_filesize());
    header('Cache-Control: no-store');

    $file->readfile();

    $file->delete();
    @unlink($metapath);
    exit;
}

// ACTION: restore.
if ($action === 'restore') {
    require_sesskey();

    $metapath = ApiClient::meta_path();
    if (!file_exists($metapath)) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }
    $info = json_decode(file_get_contents($metapath), true);
    if (!$info || empty($info['filename'])) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }

    $usercontext = context_user::instance($USER->id);
    $fs   = get_file_storage();
    $file = $fs->get_file($usercontext->id, 'user', 'backup', 0, '/', $info['filename']);
    if (!$file) {
        throw new moodle_exception('no_mbz_in_session', 'local_ai_coursecreator');
    }

    // Extract directly from the file API into the Moodle backup temp dir.
    // restore_controller expects files at $CFG->tempdir/backup/{backupid}/.
    $backupid   = restore_controller::get_tempdir_name(0, $USER->id);
    $backupbase = make_backup_temp_directory('', false);
    $extractdir = $backupbase . '/' . $backupid;
    check_dir_exists($extractdir);

    $packer = get_file_packer('application/vnd.moodle.backup');
    $packer->extract_to_pathname($file, $extractdir . '/');

    // Do NOT delete $file — it stays in the user's private backup area so the
    // teacher can access it via "View in backup area" after the restore.
    @unlink($metapath);

    $defaultcategory = core_course_category::get_default();

    $newcourse = create_course((object)[
        'fullname'  => ($info['safe_title'] ?? 'AI Generated Course'),
        'shortname' => 'ai_' . time(),
        'category'  => $defaultcategory->id,
        'format'    => 'topics',
    ]);

    $controller = new restore_controller(
        $backupid,
        $newcourse->id,
        backup::INTERACTIVE_NO,
        backup::MODE_GENERAL,
        $USER->id,
        backup::TARGET_EXISTING_DELETING
    );

    if ($controller->get_status() == backup::STATUS_REQUIRE_CONV) {
        $controller->convert();
    }

    if (!$controller->execute_precheck()) {
        $results = $controller->get_precheck_results();
        $controller->destroy();
        fulldelete($extractdir);
        $errmsg = implode('; ', array_map('strip_tags', $results['errors'] ?? ['Unknown precheck error']));
        throw new moodle_exception('restore_failed', 'local_ai_coursecreator', '', $errmsg);
    }

    $controller->execute_plan();
    $restoredcourseid = $controller->get_courseid();
    $controller->destroy();

    fulldelete($extractdir);

    $courseurl = new moodle_url('/course/view.php', ['id' => $restoredcourseid]);
    redirect($courseurl, get_string('restore_success', 'local_ai_coursecreator'));
}

// ACTION: test_connection.
if ($action === 'test_connection') {
    require_sesskey();
    \core\session\manager::write_close();

    $client = new ApiClient();
    $apikey = get_config('local_ai_coursecreator', 'api_key') ?? '';

    $report = [
        'configured'  => $client->is_configured(),
        'stream_url'  => get_config('local_ai_coursecreator', 'stream_url') ?: '(empty)',
        'api_key_set' => $apikey !== '',
        'insecure_url' => $client->is_insecure_url(),
    ];

    if (!$client->is_configured()) {
        $report['result'] = 'SKIP — settings incomplete';
    } else {
        $streamurl = $client->get_stream_url();
        $ch = curl_init($streamurl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['text' => 'connection test']),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
        ]);
        if ($client->is_insecure_url()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_exec($ch);
        $report['http_code']  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $report['curl_errno'] = curl_errno($ch);
        $report['curl_error'] = curl_error($ch) ?: null;
        $timedoutbutstreaming  = $report['curl_errno'] === 28 && $report['http_code'] === 200;
        $report['result']     = ($report['curl_errno'] === 0 || $timedoutbutstreaming)
            ? 'REACHABLE'
            : 'CURL ERROR';
    }

    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

// ACTION: test_stream.
if ($action === 'test_stream') {
    require_sesskey();
    \core\session\manager::write_close();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $fakeevents = [
        ['event' => 'agent_start', 'agent' => 1, 'name' => 'Analyst'],
        ['event' => 'agent_done', 'agent' => 1, 'name' => 'Analyst',
         'elapsed_ms' => 1000, 'usage' => ['in' => 120, 'out' => 40, 'cache' => 0]],
        ['event' => 'agent_start', 'agent' => 2, 'name' => 'Architect'],
        ['event' => 'agent_done', 'agent' => 2, 'name' => 'Architect',
         'elapsed_ms' => 1000, 'usage' => ['in' => 200, 'out' => 80, 'cache' => 0]],
        ['event' => 'agent_start', 'agent' => 3, 'name' => 'Writer'],
        ['event' => 'agent_done', 'agent' => 3, 'name' => 'Writer',
         'elapsed_ms' => 1000, 'usage' => ['in' => 500, 'out' => 300, 'cache' => 0]],
        ['event' => 'images_start'],
        ['event' => 'images_done', 'count' => 4],
        ['event' => 'build_start'],
        ['event' => 'done', 'course_title' => 'SSE Test Course', 'safe_title' => 'SSE_Test_Course',
         'size_bytes' => 2048, 'mbz_b64' => ''],
    ];

    foreach ($fakeevents as $ev) {
        echo 'data: ' . json_encode($ev) . "\n\n";
        flush();
        sleep(1);
    }
    exit;
}

// Unknown action.
throw new moodle_exception('invalidparameter', 'error');
