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
 * Teacher-facing page: paste source text and generate a Moodle course via AI.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/ai_coursecreator:generate', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ai_coursecreator/index.php'));
$PAGE->set_title(get_string('page_title', 'local_ai_coursecreator'));
$PAGE->set_heading(get_string('page_title', 'local_ai_coursecreator'));
$PAGE->set_pagelayout('standard');

$PAGE->requires->js_call_amd('local_ai_coursecreator/generate', 'init', [
    [
        'sesskey'     => sesskey(),
        'streamUrl'   => (new moodle_url('/local/ai_coursecreator/generate.php', ['action' => 'stream']))->out(false),
        'downloadUrl' => (new moodle_url('/local/ai_coursecreator/generate.php', ['action' => 'download']))->out(false),
        'restoreUrl'  => (new moodle_url('/local/ai_coursecreator/generate.php', ['action' => 'restore']))->out(false),
    ],
]);

echo $OUTPUT->header();
?>

<?php
$client = new local_ai_coursecreator\ApiClient();
if (!$client->isConfigured()) :
    ?>
<div class="alert alert-warning" role="alert">
    <?php echo get_string('api_not_configured', 'local_ai_coursecreator'); ?>
</div>
    <?php
endif;
?>

<div class="alert alert-warning" role="alert">
    <?php echo get_string('ai_disclaimer', 'local_ai_coursecreator'); ?>
</div>

<div class="row g-3 align-items-start">

    <!-- ── Left column: source text ──────────────────────────────────────── -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label for="source-text" class="form-label fw-semibold">
                        <?php echo get_string('source_text_label', 'local_ai_coursecreator'); ?>
                    </label>
                    <p class="text-muted small mb-2">
                        <?php echo get_string('source_text_help', 'local_ai_coursecreator'); ?>
                    </p>
                    <?php $phSourceText = s(get_string('source_text_help', 'local_ai_coursecreator')); ?>
                    <textarea id="source-text"
                              class="form-control"
                              rows="10"
                              required
                              placeholder="<?php echo $phSourceText; ?>"></textarea>
                </div>
                <div class="mb-3">
                    <label for="source-files" class="form-label fw-semibold">
                        <?php echo get_string('upload_label', 'local_ai_coursecreator'); ?>
                    </label>
                    <p class="text-muted small mb-1">
                        <?php echo get_string('upload_help', 'local_ai_coursecreator'); ?>
                    </p>
                    <input type="file" id="source-files" class="form-control"
                           accept=".txt,.csv,.html,.htm,.docx,.pdf" multiple>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="include-images" value="1">
                    <label class="form-check-label" for="include-images">
                        <?php echo get_string('include_images_label', 'local_ai_coursecreator'); ?>
                    </label>
                </div>
                <button id="generate-btn" type="button" class="btn btn-primary">
                    <?php echo get_string('generate_btn', 'local_ai_coursecreator'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Right column: progress + result + error ───────────────────────── -->
    <div class="col-md-5">

        <!-- Progress panel (hidden until stream starts) -->
        <div id="progress-panel" class="card mb-3 d-none">
            <div class="card-header fw-semibold">
                <?php echo get_string('progress_heading', 'local_ai_coursecreator'); ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="progress-table">
                    <tbody>
                        <?php
                        $lblWaiting = get_string('status_waiting', 'local_ai_coursecreator');
                        $rows = [
                            ['id' => 'row-agent-1', 'label' => get_string('agent_analyst', 'local_ai_coursecreator')],
                            ['id' => 'row-agent-2', 'label' => get_string('agent_architect', 'local_ai_coursecreator')],
                            ['id' => 'row-agent-3', 'label' => get_string('agent_writer', 'local_ai_coursecreator')],
                            ['id' => 'row-images', 'label' => get_string('image_generator', 'local_ai_coursecreator')],
                            ['id' => 'row-build', 'label' => get_string('mbz_builder', 'local_ai_coursecreator')],
                        ];
                        foreach ($rows as $row) :
                            ?>
                        <tr id="<?php echo $row['id']; ?>">
                            <td class="ps-3 py-2 w-50"><?php echo $row['label']; ?></td>
                            <td class="py-2">
                                <span class="status-badge">
                                    <span class="spinner-grow spinner-grow-sm text-secondary"
                                          role="status"
                                          aria-label="<?php echo $lblWaiting; ?>"></span>
                                </span>
                            </td>
                            <td class="py-2 text-end pe-3 text-muted small detail-cell"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Result panel (hidden until done event) -->
        <div id="result-panel" class="card mb-3 d-none border-success">
            <div class="card-header fw-semibold text-success">
                <?php echo get_string('result_heading', 'local_ai_coursecreator'); ?>
            </div>
            <div class="card-body">
                <p class="mb-1">
                    <span class="fw-semibold">
                        <?php echo get_string('result_title_label', 'local_ai_coursecreator'); ?>
                    </span>
                    <span id="result-course-title"></span>
                </p>
                <p class="mb-3">
                    <span class="fw-semibold">
                        <?php echo get_string('result_size_label', 'local_ai_coursecreator'); ?>
                    </span>
                    <span id="result-file-size"></span>
                </p>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <?php $restoreAction = (new moodle_url(
                        '/local/ai_coursecreator/generate.php',
                        ['action' => 'restore']
                    ))->out(false); ?>
                    <form method="post" action="<?php echo $restoreAction; ?>">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <button type="submit" class="btn btn-success">
                            <?php echo get_string('restore_btn', 'local_ai_coursecreator'); ?>
                        </button>
                    </form>
                    <a href="<?php echo (new moodle_url('/backup/restorefile.php', ['contextid' => 1]))->out(false); ?>"
                       class="btn btn-outline-secondary">
                        <?php echo get_string('backup_area_link', 'local_ai_coursecreator'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Error banner (hidden until error event) -->
        <div id="error-panel" class="alert alert-danger d-none" role="alert">
            <strong><?php echo get_string('error_heading', 'local_ai_coursecreator'); ?>:</strong>
            <span id="error-message"></span>
        </div>

    </div><!-- /.col-md-5 -->

</div><!-- /.row -->

<?php
echo $OUTPUT->footer();
