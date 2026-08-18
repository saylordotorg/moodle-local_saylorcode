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
 * Disclosure tests for protected assessment data.
 *
 * These are deliberately adversarial. A regression here means hidden test
 * content reaches a student, which is an assessment integrity failure rather
 * than a cosmetic bug, so the assertions check the serialised payload as a
 * whole rather than individual fields.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\test_result
 * @covers     \local_saylorcode\local\runner\execution_response
 */
final class hidden_test_disclosure_test extends \advanced_testcase {

    /**
     * A hidden test must not leak its name, values or feedback.
     */
    public function test_hidden_test_is_reduced_to_an_outcome(): void {
        $hidden = new test_result(
            'T-HIDDEN-01',
            'Rejects a negative balance',
            false,
            false,
            2.0,
            'Your withdraw method allowed the balance to go below zero.',
            'balance=-50',
            'IllegalArgumentException'
        );

        $exported = $hidden->export_for_student();
        $serialised = json_encode($exported);

        $this->assertFalse($exported['ispublic']);
        $this->assertFalse($exported['passed']);

        // None of the protected values may appear anywhere in the payload.
        $this->assertStringNotContainsString('T-HIDDEN-01', $serialised);
        $this->assertStringNotContainsString('Rejects a negative balance', $serialised);
        $this->assertStringNotContainsString('balance=-50', $serialised);
        $this->assertStringNotContainsString('IllegalArgumentException', $serialised);
        $this->assertStringNotContainsString('withdraw method', $serialised);
    }

    /**
     * A public test keeps its detail, because that detail is the feedback.
     */
    public function test_public_test_retains_its_detail(): void {
        $public = new test_result(
            'T-PUBLIC-01',
            'Prints the greeting',
            true,
            true,
            1.0,
            'Nicely done.',
            'Hello, world!',
            'Hello, world!'
        );

        $exported = $public->export_for_student();

        $this->assertTrue($exported['ispublic']);
        $this->assertSame('Prints the greeting', $exported['name']);
        $this->assertSame('Nicely done.', $exported['feedback']);
        $this->assertSame('Hello, world!', $exported['expected']);
    }

    /**
     * A mixed response must disclose the public tests and hide the rest.
     */
    public function test_mixed_response_hides_only_the_hidden_tests(): void {
        $response = new execution_response(
            'req-1',
            execution_state::FAILED_TESTS,
            '',
            '',
            '',
            [
                new test_result('T1', 'Handles zero', true, true, 1.0, 'Correct.'),
                new test_result('T2', 'Secret boundary case', false, false, 1.0, 'Off by one at the upper bound.'),
            ]
        );

        $serialised = json_encode($response->export_for_student());

        $this->assertStringContainsString('Handles zero', $serialised);
        $this->assertStringNotContainsString('Secret boundary case', $serialised);
        $this->assertStringNotContainsString('Off by one', $serialised);
    }

    /**
     * Hidden tests still count towards the score even though they stay secret.
     */
    public function test_hidden_tests_contribute_to_the_score(): void {
        $response = new execution_response(
            'req-2',
            execution_state::FAILED_TESTS,
            '',
            '',
            '',
            [
                new test_result('T1', 'Public', true, true, 1.0),
                new test_result('T2', 'Hidden', false, false, 3.0),
            ]
        );

        // One of four weighted points earned.
        $this->assertEqualsWithDelta(0.25, $response->get_score_fraction(), 0.0001);
        $this->assertFalse($response->all_tests_passed());
    }

    /**
     * An exercise with no tests must score zero rather than full marks.
     */
    public function test_absent_tests_score_zero(): void {
        $response = new execution_response('req-3', execution_state::COMPLETED);

        $this->assertSame(0.0, $response->get_score_fraction());
        $this->assertFalse($response->all_tests_passed());
    }
}
