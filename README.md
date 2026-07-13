# AI Course Creator — Moodle Local Plugin

This plugin is part of the **LearningOps** suite by **Highskills and more**.
https://www.highskills.co.il/


## Overview

AI Course Creator lets teachers generate a fully structured Moodle course from source
material — lecture notes, a syllabus, uploaded documents, or any plain text. The plugin
streams the request back from Highskills FastAPI backend, displays real-time agent progress, and
delivers a `.mbz` backup file that can be restored as a new Moodle course in one click.
Teachers can choose to include AI generated images to each section.
Currently this plugin creates courses with the following modules: page, lesson, quiz, forum, feedback, choice.

## Activation

To get your activation endpoint and API key, please [complete the setup process here](https://www.highskills.co.il/blog/ai/coursecreator-moodle).

## Requirements

- **Moodle 4.2** or later
- **PHP 8.0** or later with the following extensions: `curl`, `zip`, `mbstring`
- Access to the **AI Course Creator FastAPI service** (provided by Highskills and more)

## Installation

1. Copy or clone the `ai_coursecreator` folder into your Moodle installation (rename `ai_coursecreator`):

   ```
   <moodle_root>/local/ai_coursecreator/
   ```

2. Log in as a site administrator and navigate to:

   **Site Administration → Notifications**

   Confirm the plugin upgrade. No database tables are created.

> **Note:** The `vendor/` directory (Smalot PDF Parser for PDF text extraction) is
> bundled in the repository. **Composer does not need to be run.**

## Configuration

Navigate to **Site Administration → Local Plugins → AI Course Creator → Settings**.

| Setting | Description |
|---------|-------------|
| **Stream endpoint URL** | Full URL of the FastAPI streaming endpoint provided by Highskills and more |
| **API Key** | 64-character hex Bearer token provided by Highskills and more |
| **Stream timeout (seconds)** | Maximum time to wait for a generation to complete. Default: 600 s (10 min). Increase for very large documents. |
| **System prompt** *(optional)* | Text prepended to every teacher submission — use this for standing instructions such as language, tone, or curriculum standards. |

After saving, use **Test API Connection** in the Diagnostics section at the bottom of the
settings page to verify that the plugin can reach the FastAPI service.

## Accessing the plugin

Authorised users find the plugin at:

**Site Administration → Courses → AI Course Creator**

## Roles

The plugin ships a pre-defined role **AI Course creator** created automatically on install/upgrade:

| Property | Value |
|----------|-------|
| Archetype | manager (all default manager capabilities are inherited) |
| Assignable context | System, Category |
| Who can assign | Site administrators only |

## Capabilities

The capability `local/ai_coursecreator:generate` is granted by default to:

| Role | Access |
|------|--------|
| AI Course creator | Allow |

To restrict or extend access go to **Site Administration → Users → Permissions → Define roles**.

## Supported file formats for upload

| Format | Notes |
|--------|-------|
| `.txt` | Plain text |
| `.csv` | Treated as plain text |
| `.html` / `.htm` | HTML tags stripped; plain text extracted |
| `.docx` | Word document — text extracted from `word/document.xml` |
| `.pdf` | Text extracted via Smalot PDF Parser (pure PHP). Image-only / scanned PDFs produce no text. |

**Plain-text limit:** 512 KB of extracted text per generation. Files are accepted regardless
of file size as long as the extracted text does not exceed this limit.

## Features

- Paste course source text directly and/or upload one or more files
- Optional **Include image generation** checkbox
- Real-time SSE progress panel showing five pipeline stages:
  Analyst → Architect → Writer → Image Generator → MBZ Builder
- **Restore as new course** — one-click restore via Moodle's restore API
- **View in backup area** — the generated `.mbz` is stored in the user's private backup
  area and persists after restore for later download or re-use
- **Hebrew (RTL)** language pack included

## Troubleshooting

| Symptom | Likely cause & fix |
|---------|-------------------|
| "Not fully configured" warning on the course creator page | Stream endpoint URL or API key is missing in settings |
| `cURL error 7` — could not connect | FastAPI service is unreachable — check the stream URL and network/firewall |
| `cURL error 28` — operation timed out | Increase the **Stream timeout** setting; large documents need more processing time |
| `HTTP 401 / 403` from the API | API key is wrong or expired — regenerate it in the Highskills admin UI |
| PDF uploads produce no extracted text | The PDF is image-only (scanned). Convert it to a text-based PDF first |
| Progress panel only updates at the end | Apache/IIS response buffering. Add `SetEnv no-gzip 1` to your Apache virtual host, or disable response buffering in IIS |
| `unable_to_find_conversion_path` on restore | Moodle temp directory issue — check `$CFG->tempdir` is writable |

## License

GNU General Public License v3 or later — see [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html).
