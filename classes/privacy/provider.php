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

namespace local_saylorcode\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy implementation for the Saylor Code Studio service layer.
 *
 * No student data is held here. Attempts, snapshots and grades belong to
 * mod_saylorcode. Two things do belong to this plugin.
 *
 * The first is transmission: student source code is sent to a separate
 * execution service. Nothing is retained afterwards, but the transmission is
 * declared because it leaves Moodle. The request carries only the code, any
 * standard input, and a random correlation token; no name, email address, user
 * id or grade travels with it.
 *
 * The second is authorship. Every library exercise and published version
 * records the author who last touched it, which is personal data about staff
 * rather than students. Erasure anonymises that link instead of deleting the
 * exercise, because a library exercise is shared instructional content that
 * activities across the site resolve against. Deleting one on an author's
 * erasure request would strip content from courses that have nothing to do
 * with them; removing the authorship link satisfies the request without that
 * collateral damage.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /**
     * Describe the data this plugin stores and sends.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'saylorcode_runner',
            [
                'files' => 'privacy:metadata:runner:files',
                'stdin' => 'privacy:metadata:runner:stdin',
                'requestid' => 'privacy:metadata:runner:requestid',
            ],
            'privacy:metadata:runner'
        );

        $collection->add_database_table('local_saylorcode_exercises', [
            'usermodified' => 'privacy:metadata:exercises:usermodified',
            'timemodified' => 'privacy:metadata:exercises:timemodified',
        ], 'privacy:metadata:exercises');

        $collection->add_database_table('local_saylorcode_versions', [
            'usermodified' => 'privacy:metadata:versions:usermodified',
            'timecreated' => 'privacy:metadata:versions:timecreated',
        ], 'privacy:metadata:versions');

        return $collection;
    }

    /**
     * Find where this user appears.
     *
     * The library is site-wide, so authorship can only ever put a user in the
     * system context.
     *
     * @param int $userid The user to look for.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND (EXISTS (SELECT 1 FROM {local_saylorcode_exercises} e WHERE e.usermodified = :userid1)
                     OR EXISTS (SELECT 1 FROM {local_saylorcode_versions} v WHERE v.usermodified = :userid2))";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Find the users who authored anything in the library.
     *
     * @param userlist $userlist The userlist to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {local_saylorcode_exercises}', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {local_saylorcode_versions}', []);
    }

    /**
     * Export what this user authored.
     *
     * The exercise content itself is not personal data and is not exported;
     * what is exported is the fact that this user authored it, and when.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::has_system_context($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();
        $path = [get_string('pluginname', 'local_saylorcode'), get_string('privacy:path:library', 'local_saylorcode')];

        $exercises = $DB->get_records('local_saylorcode_exercises', ['usermodified' => $userid], 'stableid ASC');
        $authored = [];

        foreach ($exercises as $exercise) {
            $authored[] = (object) [
                'stableid' => $exercise->stableid,
                'name' => $exercise->name,
                'timemodified' => transform::datetime($exercise->timemodified),
            ];
        }

        $versions = $DB->get_records('local_saylorcode_versions', ['usermodified' => $userid], 'exerciseid ASC, version ASC');
        $published = [];

        foreach ($versions as $version) {
            $published[] = (object) [
                'exerciseid' => $version->exerciseid,
                'version' => $version->version,
                'timecreated' => transform::datetime($version->timecreated),
            ];
        }

        if (!$authored && !$published) {
            return;
        }

        writer::with_context($context)->export_data($path, (object) [
            'exercises' => $authored,
            'versions' => $published,
        ]);
    }

    /**
     * Remove authorship for everyone in this context.
     *
     * @param context $context The context to delete in.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }

        $DB->set_field('local_saylorcode_exercises', 'usermodified', 0, []);
        $DB->set_field('local_saylorcode_versions', 'usermodified', 0, []);
    }

    /**
     * Remove this user's authorship.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete in.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (!self::has_system_context($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $DB->set_field('local_saylorcode_exercises', 'usermodified', 0, ['usermodified' => $userid]);
        $DB->set_field('local_saylorcode_versions', 'usermodified', 0, ['usermodified' => $userid]);
    }

    /**
     * Remove authorship for a set of users.
     *
     * @param approved_userlist $userlist The approved users to delete for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if (!$userlist->get_context() instanceof context_system) {
            return;
        }

        $userids = $userlist->get_userids();

        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->set_field_select('local_saylorcode_exercises', 'usermodified', 0, "usermodified {$insql}", $params);
        $DB->set_field_select('local_saylorcode_versions', 'usermodified', 0, "usermodified {$insql}", $params);
    }

    /**
     * Whether the approved contexts include the system context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return bool
     */
    protected static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                return true;
            }
        }

        return false;
    }
}
