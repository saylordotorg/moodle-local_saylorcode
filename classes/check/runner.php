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

namespace local_saylorcode\check;

use core\check\check;
use core\check\result;
use local_saylorcode\local\runner\jobe_provider;

/**
 * Whether the execution runner is reachable.
 *
 * The provider has been able to answer this since it was written, and nothing
 * asked. An administrator had no way to tell a healthy runner from an
 * unreachable one except by opening an exercise and pressing Run, which is a
 * poor way to find out that every student's code is failing.
 *
 * Reporting it through the check API puts it on the site status report, where
 * an administrator already looks, and where external monitoring can read it.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class runner extends check {
    /**
     * The name shown on the status report.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check:runner', 'local_saylorcode');
    }

    /**
     * Where an administrator goes to do something about it.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/settings.php', ['section' => 'local_saylorcode']),
            get_string('check:runneraction', 'local_saylorcode')
        );
    }

    /**
     * Ask the runner whether it is well.
     *
     * @return result
     */
    public function get_result(): result {
        // An unconfigured runner is not a fault to alarm anyone about: a site
        // that has not set one up yet is in a normal state, it simply cannot
        // execute anything. That is N/A rather than an error.
        if (trim((string) get_config('local_saylorcode', 'jobeurl')) === '') {
            return new result(result::NA, get_string('healthnourl', 'local_saylorcode'));
        }

        $health = jobe_provider::create_from_config()->get_health();

        if (!$health->is_healthy()) {
            return new result(result::CRITICAL, $health->get_detail());
        }

        $summary = get_string('check:runnerok', 'local_saylorcode', [
            'latency' => number_format($health->get_latency(), 2),
            'profiles' => count($health->get_profiles()),
        ]);

        return new result(result::OK, $summary, implode(', ', $health->get_profiles()));
    }
}
