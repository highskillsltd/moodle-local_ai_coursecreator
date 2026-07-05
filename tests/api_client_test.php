<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Unit tests for local_ai_coursecreator\ApiClient.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_coursecreator\tests;

use advanced_testcase;
use local_ai_coursecreator\ApiClient;

/**
 * Tests for ApiClient.
 *
 * @package   local_ai_coursecreator
 * @covers    \local_ai_coursecreator\ApiClient
 */
class ApiClientTest extends advanced_testcase
{

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test isConfigured returns false when both settings are empty.
     *
     * @covers ::isConfigured
     */
    public function testIsConfiguredFalseWhenEmpty(): void
    {
        set_config('stream_url', '', 'local_ai_coursecreator');
        set_config('api_key', '', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->isConfigured());
    }

    /**
     * Test isConfigured returns false when only stream_url is set.
     *
     * @covers ::isConfigured
     */
    public function testIsConfiguredFalseWhenOnlyUrl(): void
    {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', '', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->isConfigured());
    }

    /**
     * Test isConfigured returns false when only api_key is set.
     *
     * @covers ::isConfigured
     */
    public function testIsConfiguredFalseWhenOnlyKey(): void
    {
        set_config('stream_url', '', 'local_ai_coursecreator');
        set_config('api_key', 'abc123', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->isConfigured());
    }

    /**
     * Test isConfigured returns true when both settings are present.
     *
     * @covers ::isConfigured
     */
    public function testIsConfiguredTrueWhenBothSet(): void
    {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'abc123', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertTrue($client->isConfigured());
    }

    /**
     * Test isInsecureUrl returns true for http:// URL.
     *
     * @covers ::isInsecureUrl
     */
    public function testIsInsecureUrlHttp(): void
    {
        set_config('stream_url', 'http://insecure.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertTrue($client->isInsecureUrl());
    }

    /**
     * Test isInsecureUrl returns false for https:// URL.
     *
     * @covers ::isInsecureUrl
     */
    public function testIsInsecureUrlHttps(): void
    {
        set_config('stream_url', 'https://secure.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertFalse($client->isInsecureUrl());
    }

    /**
     * Test getStreamUrl returns the configured URL.
     *
     * @covers ::getStreamUrl
     */
    public function testGetStreamUrlReturnsUrl(): void
    {
        set_config('stream_url', 'https://api.example.com/stream', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertSame('https://api.example.com/stream', $client->getStreamUrl());
    }

    /**
     * Test getStreamUrl strips a trailing slash from the configured URL.
     *
     * @covers ::getStreamUrl
     */
    public function testGetStreamUrlStripsTrailingSlash(): void
    {
        set_config('stream_url', 'https://api.example.com/stream/', 'local_ai_coursecreator');
        set_config('api_key', 'key', 'local_ai_coursecreator');
        $client = new ApiClient();
        $this->assertSame('https://api.example.com/stream', $client->getStreamUrl());
    }
}
