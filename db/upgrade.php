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
 * Upgrade steps for the Saylor Code Studio service layer.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply the upgrade steps.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_saylorcode_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081902) {
        // The install file is only read for a fresh install, so an existing
        // site upgrading to this version would otherwise have no library tables
        // at all and the library page would fail on its first query.
        $table = new xmldb_table('local_saylorcode_exercises');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('stableid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('summary', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('profileid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'java17-console');
            $table->add_field('currentversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
            $table->add_index('stableid', XMLDB_INDEX_UNIQUE, ['stableid']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_saylorcode_versions');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('exerciseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft');
            $table->add_field('profileid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'java17-console');
            $table->add_field('entryfilename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'Main.java');
            $table->add_field('startercode', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('referencesolution', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('testcases', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('hints', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('changenote', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('exerciseid', XMLDB_KEY_FOREIGN, ['exerciseid'], 'local_saylorcode_exercises', ['id']);
            $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
            $table->add_index('exerciseid-version', XMLDB_INDEX_UNIQUE, ['exerciseid', 'version']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081902, 'local', 'saylorcode');
    }

    if ($oldversion < 2026082505) {
        // The review workflow (specification section 10.8). Exercises that
        // already exist predate it, so they need a defensible starting state
        // rather than the column default: one with a published version has been
        // through whatever review the site was doing informally and is in use,
        // so calling it draft would misrepresent it and, once status gates new
        // use, could pull working content out of courses. Published means ready;
        // anything still unpublished stays a draft.
        $table = new xmldb_table('local_saylorcode_exercises');
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft', 'currentversion');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            $DB->set_field_select(
                'local_saylorcode_exercises',
                'status',
                'ready',
                'currentversion > 0'
            );
        }

        // What a latest-following activity serves. Held apart from
        // currentversion so that publishing a revision does not put unreviewed
        // content in front of students the moment it is saved.
        $approved = new xmldb_field('approvedversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');

        if (!$dbman->field_exists($table, $approved)) {
            $dbman->add_field($table, $approved);

            // Existing published exercises are already being served and are
            // being called Ready above, so the version in use is by definition
            // the approved one. Leaving it at zero would be equivalent, since
            // zero falls back to the newest published version, but recording it
            // means the first revision after this upgrade is held back for
            // review rather than going straight out.
            $DB->execute(
                'UPDATE {local_saylorcode_exercises} SET approvedversion = currentversion WHERE currentversion > 0'
            );
        }

        upgrade_plugin_savepoint(true, 2026082505, 'local', 'saylorcode');
    }

    return true;
}
