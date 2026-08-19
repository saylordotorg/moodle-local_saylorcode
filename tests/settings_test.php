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

namespace local_saylorcode;

/**
 * The administration settings exist and stay existing.
 *
 * These are the only way to configure the runner: its address, its key, its
 * timeouts, the ceilings on what a student's code may consume, and how long
 * execution records are kept. Losing them does not break a test or fail a
 * lint. The site simply stops being configurable, and a fresh install has no
 * way to reach a runner at all.
 *
 * That is not hypothetical. This file was once replaced wholesale by a page
 * that only registered the exercise library, and every control here went with
 * it. Nothing but a human reading the change noticed, which is why this test
 * exists.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class settings_test extends \advanced_testcase {
    /**
     * Every setting the runner needs is registered.
     *
     * @return void
     */
    public function test_the_runner_settings_are_registered(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->libdir . '/adminlib.php');

        $root = admin_get_root(true, true);
        $page = $root->locate('local_saylorcode');

        $this->assertNotNull($page, 'The settings page for this plugin is not registered at all.');

        $registered = [];
        foreach ($page->settings as $setting) {
            $registered[] = $setting->name;
        }

        $required = [
            'jobeurl',
            'jobeapikey',
            'jobetimeout',
            'enablejava',
            'maxcpuseconds',
            'maxmemorymb',
            'maxdiskmb',
            'maxprocesses',
            'maxoutputbytes',
            'maxconcurrentperuser',
            'snapshotsperattempt',
            'executionlogretention',
        ];

        foreach ($required as $name) {
            $this->assertContains(
                $name,
                $registered,
                "The setting {$name} is no longer registered, so nobody can configure it."
            );
        }
    }

    /**
     * The exercise library is reachable from site administration.
     *
     * Added alongside the runner settings rather than in place of them, which
     * is the mistake this pair of assertions is really guarding.
     */
    public function test_the_library_page_is_registered(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->libdir . '/adminlib.php');

        $root = admin_get_root(true, true);

        $this->assertNotNull(
            $root->locate('localsaylorcodelibrary'),
            'The exercise library is not reachable from site administration.'
        );

        $this->assertNotNull(
            $root->locate('local_saylorcode'),
            'The library page displaced the runner settings instead of joining them.'
        );
    }
}
