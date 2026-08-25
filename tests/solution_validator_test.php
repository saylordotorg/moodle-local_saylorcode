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

use local_saylorcode\local\library\solution_validator;
use local_saylorcode\local\runner\execution_state;

/**
 * Tests for the pre-publish solution validator.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\library\solution_validator
 */
final class solution_validator_test extends \advanced_testcase {
    /**
     * Load the scripted provider fixture.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/scripted_provider.php');
    }

    /**
     * A draft with two cases, as the form would store it.
     *
     * @param array $overrides Fields to replace on the draft.
     * @return \stdClass
     */
    private function draft(array $overrides = []): \stdClass {
        $cases = [
            ['id' => 'T1', 'name' => 'greets', 'stdin' => '', 'expected' => "Hello", 'ispublic' => true, 'weight' => 1],
            ['id' => 'T2', 'name' => 'adds', 'stdin' => '', 'expected' => "3", 'ispublic' => false, 'weight' => 1],
        ];

        return (object) array_merge([
            'entryfilename' => 'Main.java',
            'referencesolution' => 'public class Main {}',
            'testcases' => json_encode($cases),
        ], $overrides);
    }

    /**
     * The reference passing every case is reported valid.
     */
    public function test_a_passing_reference_is_valid(): void {
        $this->resetAfterTest();

        // Trailing whitespace and a missing final newline must not fail it:
        // the validator normalises exactly as the student path does.
        $provider = new scripted_provider(["Hello  \n", "3"]);
        $report = (new solution_validator($provider))->validate($this->draft(), 'java17-console');

        $this->assertTrue($report['valid']);
        $this->assertTrue($report['validatable']);
        $this->assertSame('validatepassed', $report['reason']);
        $this->assertSame([], $report['failed']);
        $this->assertSame(2, $provider->calls);
    }

    /**
     * A reference that gets one case wrong is reported invalid, naming it.
     */
    public function test_a_wrong_reference_is_invalid_and_names_the_case(): void {
        $this->resetAfterTest();

        $provider = new scripted_provider(["Hello", "4"]);
        $report = (new solution_validator($provider))->validate($this->draft(), 'java17-console');

        $this->assertFalse($report['valid']);
        $this->assertTrue($report['validatable']);
        $this->assertSame('validatefailed', $report['reason']);
        $this->assertSame(['adds'], $report['failed']);
    }

    /**
     * A compile error stops the run and is reported as such.
     */
    public function test_a_compile_error_is_reported(): void {
        $this->resetAfterTest();

        $provider = new scripted_provider(['']);
        $provider->state = execution_state::COMPILE_ERROR;
        $report = (new solution_validator($provider))->validate($this->draft(), 'java17-console');

        $this->assertFalse($report['valid']);
        $this->assertTrue($report['validatable']);
        $this->assertSame('validatecompileerror', $report['reason']);
        // A compile error is a property of the code, not of one case, so the
        // run stops at the first execution rather than repeating.
        $this->assertSame(1, $provider->calls);
    }

    /**
     * A runner outage is reported as not validatable, never as a pass.
     */
    public function test_a_runner_outage_is_not_a_verdict(): void {
        $this->resetAfterTest();

        $provider = new scripted_provider();
        $provider->throw = true;
        $report = (new solution_validator($provider))->validate($this->draft(), 'java17-console');

        $this->assertFalse($report['valid']);
        $this->assertFalse($report['validatable']);
        $this->assertSame('validaterunnerdown', $report['reason']);
    }

    /**
     * A draft with no reference solution cannot be judged.
     */
    public function test_no_solution_is_not_validatable(): void {
        $this->resetAfterTest();

        $provider = new scripted_provider(['Hello', '3']);
        $report = (new solution_validator($provider))->validate(
            $this->draft(['referencesolution' => '   ']),
            'java17-console'
        );

        $this->assertFalse($report['valid']);
        $this->assertFalse($report['validatable']);
        $this->assertSame('validatenosolution', $report['reason']);
        // Nothing should have been sent to the runner.
        $this->assertSame(0, $provider->calls);
    }

    /**
     * A draft with no test cases cannot be judged.
     */
    public function test_no_cases_is_not_validatable(): void {
        $this->resetAfterTest();

        $provider = new scripted_provider();
        $report = (new solution_validator($provider))->validate(
            $this->draft(['testcases' => '']),
            'java17-console'
        );

        $this->assertFalse($report['valid']);
        $this->assertFalse($report['validatable']);
        $this->assertSame('validatenocases', $report['reason']);
        $this->assertSame(0, $provider->calls);
    }
}
