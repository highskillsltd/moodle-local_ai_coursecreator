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
 * Post-install hook for local_ai_coursecreator.
 *
 * Creates the "AI Course creator" role on a fresh install.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Called by Moodle after the plugin tables (if any) are created on install.
 */
function xmldb_local_ai_coursecreator_install() {
    local_ai_coursecreator_create_role();
}

/**
 * Creates the "AI Course creator" role if it does not already exist.
 *
 * The role is based on the manager archetype (inheriting all its default
 * capability assignments). It is assignable only at system or category
 * context. No allowassign entries are added, so only site administrators
 * can assign it to users.
 */
function local_ai_coursecreator_create_role() {
    global $DB;

    if ($DB->record_exists('role', ['shortname' => 'ai_coursecreator'])) {
        return;
    }

    $roleid = create_role(
        get_string('rolename', 'local_ai_coursecreator'),
        'ai_coursecreator',
        get_string('roledescription', 'local_ai_coursecreator'),
        'manager'
    );

    // Copy all manager archetype default capabilities to the new role.
    reset_role_capabilities($roleid);

    set_role_contextlevels($roleid, [CONTEXT_SYSTEM, CONTEXT_COURSECAT]);

    $syscontext = context_system::instance();
    assign_capability('local/ai_coursecreator:generate', CAP_ALLOW, $roleid, $syscontext->id, true);
}
