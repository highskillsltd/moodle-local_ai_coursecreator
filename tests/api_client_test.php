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
 * Unit tests for local_ai_coursecreator\ApiClient.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_coursecreator\tests;

use local_ai_coursecreator\ApiClient;

/**
 * Tests for ApiClient.
 *
 * @package   local_ai_coursecreator
 * @covers    \local_ai_coursecreator\ApiClient
 */
class api_client_test extends \advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test is_configured returns false when both settings are empty.
     *
     * @covers ::is_configured
     */
    public function test_is_configured_false_when_empty(): void {
        set_config('stream_url', '', 'local_ai_coursecreator');
        set_config('api_key', '', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test is_configured returns false when only stream_url is set.
     *
     * @covers ::is_configured
     */
    public function test_is_configured_false_when_only_url(): void {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', '', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test is_configured returns false when only api_key is set.
     *
     * @covers ::is_configured
     */
    public function test_is_configured_false_when_only_key(): void {
        set_config('stream_url', '', 'local_ai_coursecreator');
        set_config('api_key', 'abc123', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->is_configured());
    }

    /**
     * Test is_configured returns true when both settings are present.
     *
     * @covers ::is_configured
     */
    public function test_is_configured_true_when_both_set(): void {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'abc123', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertTrue($client->is_configured());
    }

    /**
     * Test is_insecure_url returns true for http:// URL.
     *
     * @covers ::is_insecure_url
     */
    public function test_is_insecure_url_http(): void {
        set_config('stream_url', 'http://insecure.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertTrue($client->is_insecure_url());
    }

    /**
     * Test is_insecure_url returns false for https:// URL.
     *
     * @covers ::is_insecure_url
     */
    public function test_is_insecure_url_https(): void {
        set_config('stream_url', 'https://secure.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->is_insecure_url());
    }

    /**
     * Test get_stream_url returns the configured URL.
     *
     * @covers ::get_stream_url
     */
    public function test_get_stream_url_returns_url(): void {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertSame('https://api.example.com/stream', $client->get_stream_url());
    }

    /**
     * Test get_stream_url strips a trailing slash from the configured URL.
     *
     * @covers ::get_stream_url
     */
    public function test_get_stream_url_strips_trailing_slash(): void {
        set_config('stream_url', 'https://api.example.com/stream/', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertSame('https://api.example.com/stream', $client->get_stream_url());
    }
}
