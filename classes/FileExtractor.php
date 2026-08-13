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
 * File-content extraction utilities for local_ai_coursecreator.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class FileExtractor {
    /**
     * Extract plain text from an uploaded temporary file by extension.
     *
     * @param string $tmp Absolute path to the uploaded temporary file.
     * @param string $ext Lowercase file extension without the dot (e.g. 'pdf').
     * @return string Extracted plain-text content, or empty string on failure.
     */
    public static function extract(string $tmp, string $ext): string {
        switch ($ext) {
            case 'txt':
            case 'csv':
                return file_get_contents($tmp) ?: '';

            case 'html':
            case 'htm':
                $raw = file_get_contents($tmp) ?: '';
                return trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            case 'docx':
                return self::extract_zip_xml($tmp, ['word/document.xml']);

            case 'pdf':
                return self::extract_pdf($tmp);

            default:
                return '';
        }
    }

    /**
     * Extract and strip XML from named entries inside a ZIP archive.
     *
     * @param string   $tmp     Absolute path to the ZIP/DOCX file.
     * @param string[] $entries List of entry paths within the archive to extract.
     * @return string Concatenated, tag-stripped text from all matched entries.
     */
    public static function extract_zip_xml(string $tmp, array $entries): string {
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            return '';
        }
        $parts = [];
        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml !== false) {
                $parts[] = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        $zip->close();
        return implode("\n", $parts);
    }

    /**
     * Extract plain text from a PDF file.
     *
     * Tries pdftotext (poppler-utils) first; falls back to Smalot PDF Parser.
     *
     * @param string $tmp Absolute path to the PDF file.
     * @return string Extracted plain-text content, or empty string on failure.
     */
    public static function extract_pdf(string $tmp): string {
        // Primary: pdftotext (poppler-utils) — available on Linux/Mac servers.
        $pdftotext = self::find_pdftotext();
        if ($pdftotext !== null) {
            $out = tempnam(sys_get_temp_dir(), 'aicc_');
            $process = @proc_open(
                [$pdftotext, $tmp, $out],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (is_resource($process)) {
                // Drain stdout/stderr before closing to avoid a pipe-buffer deadlock.
                stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $text = file_get_contents($out) ?: '';
                @unlink($out);
                if ($text !== '') {
                    return $text;
                }
            } else {
                @unlink($out);
            }
        }

        // Fallback: Smalot PDF Parser (pure PHP — works on Windows/XAMPP).
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            return '';
        }
        require_once($autoload);

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($tmp);
            return $pdf->getText();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Locate the pdftotext binary from a fixed allowlist of known install paths.
     *
     * Deliberately avoids resolving via the shell (e.g. `which`), which would
     * depend on the process's $PATH — an uncontrolled command source.
     *
     * @return string|null Absolute path to pdftotext, or null if not found.
     */
    private static function find_pdftotext(): ?string {
        $candidates = [
            '/usr/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/opt/homebrew/bin/pdftotext',
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }
}
