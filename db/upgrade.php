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
 * Upgrade steps for local_ai_coursecreator.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from an older version.
 *
 * @param int $oldversion Version currently installed in the database.
 * @return bool
 */
function xmldb_local_ai_coursecreator_upgrade($oldversion) {
    if ($oldversion < 2026062809) {
        // Create the "AI Course creator" role for existing installations.
        require_once(__DIR__ . '/install.php');
        local_ai_coursecreator_create_role();

        upgrade_plugin_savepoint(true, 2026062809, 'local', 'ai_coursecreator');
    }

    return true;
}
