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
 * Site administration settings for Saylor Code Studio.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_saylorcode',
        get_string('pluginname', 'local_saylorcode')
    );
    $ADMIN->add('localplugins', $settings);

    // The library is a page of its own rather than a setting, and carries its
    // own capability so it can be offered to people who are not site admins.
    $ADMIN->add('localplugins', new admin_externalpage(
        'localsaylorcodelibrary',
        get_string('library', 'local_saylorcode'),
        new moodle_url('/local/saylorcode/library.php'),
        'local/saylorcode:viewlibrary'
    ));

    // Execution backend.
    $settings->add(new admin_setting_heading(
        'local_saylorcode/runnerheading',
        get_string('settings:runnerheading', 'local_saylorcode'),
        get_string('settings:runnerheading_desc', 'local_saylorcode')
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/jobeurl',
        get_string('settings:jobeurl', 'local_saylorcode'),
        get_string('settings:jobeurl_desc', 'local_saylorcode'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_saylorcode/jobeapikey',
        get_string('settings:jobeapikey', 'local_saylorcode'),
        get_string('settings:jobeapikey_desc', 'local_saylorcode'),
        ''
    ));

    $settings->add(new admin_setting_configduration(
        'local_saylorcode/jobetimeout',
        get_string('settings:jobetimeout', 'local_saylorcode'),
        get_string('settings:jobetimeout_desc', 'local_saylorcode'),
        30,
        1
    ));

    // Resource ceilings. These are maximums; a runtime profile may be stricter
    // but can never exceed them.
    $settings->add(new admin_setting_heading(
        'local_saylorcode/limitsheading',
        get_string('settings:limitsheading', 'local_saylorcode'),
        get_string('settings:limitsheading_desc', 'local_saylorcode')
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxcpuseconds',
        get_string('settings:maxcpuseconds', 'local_saylorcode'),
        get_string('settings:maxcpuseconds_desc', 'local_saylorcode'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxmemorymb',
        get_string('settings:maxmemorymb', 'local_saylorcode'),
        get_string('settings:maxmemorymb_desc', 'local_saylorcode'),
        256,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxdiskmb',
        get_string('settings:maxdiskmb', 'local_saylorcode'),
        get_string('settings:maxdiskmb_desc', 'local_saylorcode'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxprocesses',
        get_string('settings:maxprocesses', 'local_saylorcode'),
        get_string('settings:maxprocesses_desc', 'local_saylorcode'),
        32,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxoutputbytes',
        get_string('settings:maxoutputbytes', 'local_saylorcode'),
        get_string('settings:maxoutputbytes_desc', 'local_saylorcode'),
        65536,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxconcurrentperuser',
        get_string('settings:maxconcurrentperuser', 'local_saylorcode'),
        get_string('settings:maxconcurrentperuser_desc', 'local_saylorcode'),
        2,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/maxconcurrentsite',
        get_string('settings:maxconcurrentsite', 'local_saylorcode'),
        get_string('settings:maxconcurrentsite_desc', 'local_saylorcode'),
        0,
        PARAM_INT
    ));

    // Runtime profiles.
    $settings->add(new admin_setting_heading(
        'local_saylorcode/runtimesheading',
        get_string('settings:runtimesheading', 'local_saylorcode'),
        get_string('settings:runtimesheading_desc', 'local_saylorcode')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_saylorcode/enablejava',
        get_string('settings:enablejava', 'local_saylorcode'),
        get_string('settings:enablejava_desc', 'local_saylorcode'),
        1
    ));

    // Retention.
    $settings->add(new admin_setting_heading(
        'local_saylorcode/retentionheading',
        get_string('settings:retentionheading', 'local_saylorcode'),
        get_string('settings:retentionheading_desc', 'local_saylorcode')
    ));

    $settings->add(new admin_setting_configtext(
        'local_saylorcode/snapshotsperattempt',
        get_string('settings:snapshotsperattempt', 'local_saylorcode'),
        get_string('settings:snapshotsperattempt_desc', 'local_saylorcode'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configduration(
        'local_saylorcode/executionlogretention',
        get_string('settings:executionlogretention', 'local_saylorcode'),
        get_string('settings:executionlogretention_desc', 'local_saylorcode'),
        180 * DAYSECS,
        DAYSECS
    ));
}
