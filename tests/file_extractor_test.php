<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Unit tests for local_ai_coursecreator\FileExtractor.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_coursecreator\tests;

use advanced_testcase;
use local_ai_coursecreator\FileExtractor;

/**
 * Tests for FileExtractor.
 *
 * @package   local_ai_coursecreator
 * @covers    \local_ai_coursecreator\FileExtractor
 */
class FileExtractorTest extends advanced_testcase
{

    /**
     * Test extract returns raw content for .txt files.
     *
     * @covers ::extract
     */
    public function testExtractTxt(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, 'Hello world');
        $result = FileExtractor::extract($tmp, 'txt');
        unlink($tmp);
        $this->assertSame('Hello world', $result);
    }

    /**
     * Test extract returns raw content for .csv files.
     *
     * @covers ::extract
     */
    public function testExtractCsv(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, "a,b,c\n1,2,3");
        $result = FileExtractor::extract($tmp, 'csv');
        unlink($tmp);
        $this->assertSame("a,b,c\n1,2,3", $result);
    }

    /**
     * Test extract strips HTML tags for .html files.
     *
     * @covers ::extract
     */
    public function testExtractHtmlStripsTags(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, '<p>Hello <b>World</b></p>');
        $result = FileExtractor::extract($tmp, 'html');
        unlink($tmp);
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test extract strips HTML tags for .htm files.
     *
     * @covers ::extract
     */
    public function testExtractHtmStripsTags(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, '<h1>Title</h1>');
        $result = FileExtractor::extract($tmp, 'htm');
        unlink($tmp);
        $this->assertSame('Title', $result);
    }

    /**
     * Test extract returns empty string for unknown extensions.
     *
     * @covers ::extract
     */
    public function testExtractUnknownReturnsEmpty(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, 'some data');
        $result = FileExtractor::extract($tmp, 'xyz');
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extractZipXml reads a named XML entry from a ZIP archive.
     *
     * @covers ::extractZipXml
     */
    public function testExtractZipXmlReadsEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:t>Course Title</w:t>');
        $zip->close();
        $result = FileExtractor::extractZipXml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertStringContainsString('Course Title', $result);
    }

    /**
     * Test extractZipXml returns empty string when the entry does not exist.
     *
     * @covers ::extractZipXml
     */
    public function testExtractZipXmlMissingEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'test');
        $zip->close();
        $result = FileExtractor::extractZipXml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extractZipXml returns empty string for an invalid (non-ZIP) file.
     *
     * @covers ::extractZipXml
     */
    public function testExtractZipXmlInvalidFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, 'not a zip');
        $result = FileExtractor::extractZipXml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extractPdf returns a string without throwing for any input.
     *
     * @covers ::extractPdf
     */
    public function testExtractPdfReturnsString(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, '%PDF-1.4 fake content');
        $result = FileExtractor::extractPdf($tmp);
        unlink($tmp);
        $this->assertIsString($result);
    }

    /**
     * Test extract dispatches .docx to extractZipXml (word/document.xml entry).
     *
     * @covers ::extract
     */
    public function testExtractDocxReadsDocumentXml(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_') . '.docx';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:t>Docx Content</w:t>');
        $zip->close();
        $result = FileExtractor::extract($tmp, 'docx');
        unlink($tmp);
        $this->assertStringContainsString('Docx Content', $result);
    }
}
