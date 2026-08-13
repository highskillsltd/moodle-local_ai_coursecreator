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

namespace local_ai_coursecreator\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Privacy API provider for local_ai_coursecreator.
 *
 * The plugin defines no database tables and writes generated course
 * backups into Moodle core's own user "backup" file area (component
 * 'user'), which core is responsible for exporting/deleting. The only
 * personal data this plugin itself is responsible for disclosing is
 * that course source text (including text extracted from uploaded
 * files) is sent to an externally configured AI generation service.
 *
 * @package   local_ai_coursecreator
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin_provider {

    /**
     * Describe the personal data processed by this plugin.
     *
     * @param collection $collection The initialised metadata collection.
     * @return collection The updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'ai_coursecreator_service',
            [
                'text' => 'privacy:metadata:ai_coursecreator_service:text',
                'includeimages' => 'privacy:metadata:ai_coursecreator_service:includeimages',
            ],
            'privacy:metadata:ai_coursecreator_service'
        );

        return $collection;
    }

    /**
     * Get the list of contexts containing user data for the given user.
     *
     * The plugin owns no database tables or file areas, so it has no
     * contexts of its own to report.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * Nothing plugin-owned to export.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * Nothing plugin-owned to delete.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * Nothing plugin-owned to delete.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
    }
}
