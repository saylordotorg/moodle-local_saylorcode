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

use local_saylorcode\local\runner\jobe_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/fixtures/recording_curl.php');
require_once(__DIR__ . '/fixtures/probing_jobe_provider.php');

/**
 * Tests for the runner health probe.
 *
 * The probe asked the runner for its language list without sending the API key,
 * while execution sent it. Against a runner configured the way the provisioning
 * documentation requires -- keys mandatory, unauthenticated requests rejected --
 * that means a healthy runner reports as unreachable and student code runs
 * perfectly. A monitoring check that cries wolf on a working system is worse
 * than no check, because people learn to ignore it.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\jobe_provider
 */
final class jobe_health_test extends \advanced_testcase {
    /**
     * The probe sends the configured API key.
     *
     * @return void
     */
    public function test_the_health_probe_sends_the_api_key(): void {
        $this->resetAfterTest();

        $provider = new probing_jobe_provider('http://runner.example', 'secret-key', 5);
        $provider->get_health();

        $headers = $provider->lastcurl->options['CURLOPT_HTTPHEADER'] ?? [];

        $this->assertContains(
            'X-API-KEY: secret-key',
            $headers,
            'The health probe omits the API key, so a runner that requires one reports as unreachable.'
        );
    }

    /**
     * An authenticated probe against a well runner reports healthy.
     *
     * @return void
     */
    public function test_an_authenticated_probe_reports_healthy(): void {
        $this->resetAfterTest();

        $provider = new probing_jobe_provider('http://runner.example', 'secret-key', 5);
        $health = $provider->get_health();

        $this->assertTrue($health->is_healthy());
        $this->assertContains('java', $health->get_profiles());
    }

    /**
     * A runner that rejects the request is reported as unhealthy.
     *
     * @return void
     */
    public function test_a_rejected_probe_is_unhealthy(): void {
        $this->resetAfterTest();

        $provider = new probing_jobe_provider('http://runner.example', '', 5);
        $provider->status = 401;
        $health = $provider->get_health();

        $this->assertFalse($health->is_healthy());
        $this->assertNotEmpty($health->get_detail());
    }

    /**
     * No key configured means no key header, rather than an empty one.
     *
     * @return void
     */
    public function test_no_key_configured_sends_no_key_header(): void {
        $this->resetAfterTest();

        $provider = new probing_jobe_provider('http://runner.example', '', 5);
        $provider->get_health();

        $headers = $provider->lastcurl->options['CURLOPT_HTTPHEADER'] ?? [];

        foreach ($headers as $header) {
            $this->assertStringNotContainsString('X-API-KEY', $header);
        }
    }
}
