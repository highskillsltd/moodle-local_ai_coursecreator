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

namespace local_ai_coursecreator\event;

/**
 * Event fired when a teacher downloads a generated course backup (.mbz).
 *
 * The `other` payload carries only non-personal metadata:
 *   - filename  string Name of the .mbz file served.
 *   - safetitle string Sanitised course title.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_backup_downloaded extends \core\event\base {
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud']     = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_course_backup_downloaded', 'local_ai_coursecreator');
    }

    /**
     * Return a human-readable description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $filename = $this->other['filename'] ?? '';
        return "The user with id '{$this->userid}' downloaded the AI course backup " .
            "'{$filename}'.";
    }

    /**
     * Return the URL of the page the event relates to.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/ai_coursecreator/index.php');
    }

    /**
     * Validate the custom event data supplied to create().
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['filename'])) {
            throw new \coding_exception('The \'filename\' value must be set in other.');
        }
    }
}
