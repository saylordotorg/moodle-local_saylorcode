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

namespace local_saylorcode\local\runner;

/**
 * Tests for runner output sanitisation.
 *
 * The balance being struck here matters pedagogically: infrastructure detail
 * must go, but the compiler line numbers a beginner needs must stay.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\output_sanitiser
 */
final class output_sanitiser_test extends \advanced_testcase {

    /**
     * Sandbox working directories must not be shown to a student.
     */
    public function test_sandbox_paths_are_removed(): void {
        $raw = '/home/jobe/runs/jobe_6f2a1c/Main.java:4: error: cannot find symbol';

        $clean = output_sanitiser::sanitise($raw);

        $this->assertStringNotContainsString('/home/jobe', $clean);
        $this->assertStringNotContainsString('jobe_6f2a1c', $clean);
    }

    /**
     * The line number and message a learner needs must survive.
     */
    public function test_compiler_line_numbers_survive(): void {
        $raw = '/home/jobe/runs/jobe_6f2a1c/Main.java:4: error: cannot find symbol';

        $clean = output_sanitiser::sanitise($raw);

        $this->assertStringContainsString('4', $clean);
        $this->assertStringContainsString('cannot find symbol', $clean);
    }

    /**
     * Host names and addresses must not leak.
     */
    public function test_host_detail_is_removed(): void {
        $raw = "Connection refused to 10.0.3.221\nat ip-10-0-3-221.ec2.internal";

        $clean = output_sanitiser::sanitise($raw);

        $this->assertStringNotContainsString('10.0.3.221', $clean);
        $this->assertStringNotContainsString('ec2.internal', $clean);
    }

    /**
     * Harness frames must be stripped, and the student's own frame kept.
     */
    public function test_harness_frames_are_stripped(): void {
        $raw = implode("\n", [
            'Exception in thread "main" java.lang.NullPointerException',
            "\tat Main.calculate(Main.java:12)",
            "\tat SaylorCodeHarness.invokeTest(SaylorCodeHarness.java:88)",
            "\tat org.junit.runner.JUnitCore.run(JUnitCore.java:137)",
        ]);

        $clean = output_sanitiser::sanitise($raw);

        $this->assertStringContainsString('Main.calculate', $clean);
        $this->assertStringContainsString('NullPointerException', $clean);
        $this->assertStringNotContainsString('SaylorCodeHarness', $clean);
        $this->assertStringNotContainsString('JUnitCore', $clean);
    }

    /**
     * Empty output stays empty rather than becoming a placeholder.
     */
    public function test_empty_output_is_preserved(): void {
        $this->assertSame('', output_sanitiser::sanitise(''));
    }

    /**
     * Truncation reports itself so the interface can say so.
     */
    public function test_truncation_sets_the_flag(): void {
        $truncated = false;
        $result = output_sanitiser::truncate(str_repeat('a', 100), 40, $truncated);

        $this->assertTrue($truncated);
        $this->assertSame(40, strlen($result));
    }

    /**
     * Output within the limit is untouched.
     */
    public function test_short_output_is_not_truncated(): void {
        $truncated = true;
        $result = output_sanitiser::truncate('short', 40, $truncated);

        $this->assertFalse($truncated);
        $this->assertSame('short', $result);
    }
}
