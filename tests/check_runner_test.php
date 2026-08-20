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

use core\check\result;
use local_saylorcode\check\runner;

/**
 * Tests for the runner status check.
 *
 * The check itself is thin. What is worth guarding is that it is registered at
 * all, because a check nobody discovers is the same as no check, and that an
 * unconfigured site is not reported as broken.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\check\runner
 */
final class check_runner_test extends \advanced_testcase {
    /**
     * The check is discoverable through the check API.
     *
     * Registration is a callback in lib.php that Moodle finds by name. Renaming
     * the file or the function removes the check from the status report without
     * breaking anything else, and nothing else would report that.
     *
     * @return void
     */
    public function test_the_check_is_registered(): void {
        $this->resetAfterTest();

        $checks = \core\check\manager::get_checks('status');

        $found = false;
        foreach ($checks as $check) {
            if ($check instanceof runner) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'The runner check is not on the site status report.');
    }

    /**
     * A site with no runner configured is not reported as broken.
     *
     * A fresh install has not set one up yet. That is a normal state, not a
     * fault, and reporting it as critical would train an administrator to
     * ignore the check.
     *
     * @return void
     */
    public function test_an_unconfigured_runner_is_not_an_error(): void {
        $this->resetAfterTest();

        set_config('jobeurl', '', 'local_saylorcode');

        $this->assertSame(result::NA, (new runner())->get_result()->get_status());
    }

    /**
     * An unreachable runner is reported as critical.
     *
     * The address is deliberately one that cannot resolve, so this exercises
     * the failure path without depending on a real runner.
     *
     * @return void
     */
    public function test_an_unreachable_runner_is_critical(): void {
        $this->resetAfterTest();

        set_config('jobeurl', 'http://runner.invalid', 'local_saylorcode');
        set_config('jobetimeout', 1, 'local_saylorcode');

        $result = (new runner())->get_result();

        $this->assertSame(result::CRITICAL, $result->get_status());
        $this->assertNotEmpty($result->get_summary());
    }

    /**
     * The check carries a name and somewhere to go.
     *
     * @return void
     */
    public function test_the_check_describes_itself(): void {
        $this->resetAfterTest();

        $check = new runner();

        $this->assertNotEmpty($check->get_name());
        $this->assertNotNull($check->get_action_link());
    }
}
