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

namespace local_ai_coursecreator;

/**
 * cURL-based client for the AI course creator streaming service.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ApiClient {
    /** @var string Full streaming endpoint URL read from plugin config. */
    private string $stream_url;

    /** @var string Bearer token API key. */
    private string $apikey;

    /** @var int Streaming timeout in seconds (read from plugin config, min 30). */
    private int $stream_timeout;

    /**
     * Initialise the client by reading plugin configuration.
     */
    public function __construct() {
        $this->stream_url     = rtrim(get_config('local_ai_coursecreator', 'stream_url') ?? '', '/');
        $this->apikey         = get_config('local_ai_coursecreator', 'api_key') ?? '';
        $this->stream_timeout = max(30, (int) (get_config('local_ai_coursecreator', 'stream_timeout') ?: 600));
    }

    /**
     * Return the path to the per-user meta JSON file used to persist MBZ info.
     *
     * Keyed by user ID + session ID so it survives session_write_close().
     *
     * @return string Absolute path to the meta JSON file.
     */
    public static function metaPath(): string {
        global $USER;
        $key = md5($USER->id . '_' . session_id());
        return make_temp_directory('ai_coursecreator') . '/meta_' . $key . '.json';
    }

    /**
     * Returns true if all required config values are present.
     *
     * @return bool True when both stream_url and api_key are non-empty.
     */
    public function isConfigured(): bool {
        return $this->stream_url !== '' && $this->apikey !== '';
    }

    /**
     * Returns true when the stream URL is plain HTTP (not HTTPS).
     *
     * @return bool True when the URL begins with http://.
     */
    public function isInsecureUrl(): bool {
        return stripos($this->stream_url, 'http://') === 0;
    }

    /**
     * Returns the full streaming endpoint URL that will be called.
     *
     * @return string The configured stream endpoint URL.
     */
    public function getStreamUrl(): string {
        return $this->stream_url;
    }

    /**
     * Stream the AI pipeline response for the given source text.
     *
     * Uses CURLOPT_HEADERFUNCTION to inspect the HTTP status code before any
     * body bytes are forwarded.  If the server returns a non-2xx response the
     * body is buffered (not sent to $chunk_callback) and a moodle_exception is
     * thrown after curl_exec so generate.php can emit an SSE error event.
     *
     * @param  string   $text           The course source text to send.
     * @param  bool     $include_images Whether to request image generation.
     * @param  callable $chunk_callback Called with each raw SSE chunk on 2xx responses.
     * @return void
     * @throws \moodle_exception on cURL error or non-2xx HTTP status.
     */
    public function stream(string $text, bool $include_images, callable $chunk_callback): void {
        $url = $this->getStreamUrl();
        $ch  = curl_init($url);

        $auth_header = 'Authorization: Bearer ' . $this->apikey;

        // Track HTTP status from response headers so we can distinguish a 404
        // HTML page from a real SSE stream before forwarding body bytes.
        $http_status   = 0;
        $is_bad_status = false;
        $error_body    = '';   // Buffered body when status is non-2xx.

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'text'           => $text,
                'include_images' => $include_images,
            ]),
            CURLOPT_HTTPHEADER     => [
                $auth_header,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT        => $this->stream_timeout,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            // Capture the response status line before any body data arrives.
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$http_status, &$is_bad_status) {
                if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $header, $m)) {
                    $http_status   = (int) $m[1];
                    $is_bad_status = ($http_status < 200 || $http_status >= 300);
                }
                return strlen($header);
            },

            // Forward body only on 2xx; buffer it on error so we can report it.
            CURLOPT_WRITEFUNCTION  => function ($curl, $data) use ($chunk_callback, &$is_bad_status, &$error_body) {
                if ($is_bad_status) {
                    $error_body .= $data;
                    return strlen($data);
                }
                $chunk_callback($data);
                return strlen($data);
            },
        ]);

        if ($this->isInsecureUrl()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $ok    = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($ok === false || $errno !== 0) {
            throw new \moodle_exception(
                'curlerror',
                'local_ai_coursecreator',
                '',
                "cURL error {$errno}: {$error}"
            );
        }

        if ($is_bad_status) {
            $snippet = substr(strip_tags($error_body), 0, 300);
            throw new \moodle_exception(
                'apierror',
                'local_ai_coursecreator',
                '',
                "API returned HTTP {$http_status} from {$url} — {$snippet}"
            );
        }
    }
}
