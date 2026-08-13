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
 * Unit tests for local_ai_coursecreator\FileExtractor.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_coursecreator;

use local_ai_coursecreator\FileExtractor;

/**
 * Tests for FileExtractor.
 *
 * @package   local_ai_coursecreator
 * @covers    \local_ai_coursecreator\FileExtractor
 */
class file_extractor_test extends \advanced_testcase {
    /**
     * Test extract returns raw content for .txt files.
     *
     * @covers ::extract
     */
    public function test_extract_txt(): void {
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
    public function test_extract_csv(): void {
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
    public function test_extract_html_strips_tags(): void {
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
    public function test_extract_htm_strips_tags(): void {
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
    public function test_extract_unknown_returns_empty(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, 'some data');
        $result = FileExtractor::extract($tmp, 'xyz');
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extract_zip_xml reads a named XML entry from a ZIP archive.
     *
     * @covers ::extract_zip_xml
     */
    public function test_extract_zip_xml_reads_entry(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:t>Course Title</w:t>');
        $zip->close();
        $result = FileExtractor::extract_zip_xml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertStringContainsString('Course Title', $result);
    }

    /**
     * Test extract_zip_xml returns empty string when the entry does not exist.
     *
     * @covers ::extract_zip_xml
     */
    public function test_extract_zip_xml_missing_entry(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'test');
        $zip->close();
        $result = FileExtractor::extract_zip_xml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extract_zip_xml returns empty string for an invalid (non-ZIP) file.
     *
     * @covers ::extract_zip_xml
     */
    public function test_extract_zip_xml_invalid_file(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, 'not a zip');
        $result = FileExtractor::extract_zip_xml($tmp, ['word/document.xml']);
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extract_pdf returns a string without throwing for any input.
     *
     * @covers ::extract_pdf
     */
    public function test_extract_pdf_returns_string(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aicc_');
        file_put_contents($tmp, '%PDF-1.4 fake content');
        $result = FileExtractor::extract_pdf($tmp);
        unlink($tmp);
        $this->assertIsString($result);
    }

    /**
     * Test extract dispatches .docx to extract_zip_xml (word/document.xml entry).
     *
     * @covers ::extract
     */
    public function test_extract_docx_reads_document_xml(): void {
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
