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
 * Result of a single test case.
 *
 * A hidden test contributes to the score but must never expose its input,
 * expected output or implementation to the browser. The visibility flag on this
 * object is what execution_response consults when building a student payload.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class test_result {

    /** @var string Author supplied test identifier. */
    private string $testid;

    /** @var string Student facing test name. */
    private string $name;

    /** @var bool Whether the test passed. */
    private bool $passed;

    /** @var bool Whether the test may be described to the student. */
    private bool $ispublic;

    /** @var float Relative weight for scoring. */
    private float $weight;

    /** @var string Author written feedback for this outcome. */
    private string $feedback;

    /** @var string Actual output, only ever surfaced for public tests. */
    private string $actual;

    /** @var string Expected output, only ever surfaced for public tests. */
    private string $expected;

    /**
     * Build a test result.
     *
     * @param string $testid Author supplied identifier.
     * @param string $name Student facing name.
     * @param bool $passed Whether it passed.
     * @param bool $ispublic Whether details may be shown.
     * @param float $weight Scoring weight.
     * @param string $feedback Author feedback.
     * @param string $actual Actual output.
     * @param string $expected Expected output.
     */
    public function __construct(
        string $testid,
        string $name,
        bool $passed,
        bool $ispublic = true,
        float $weight = 1.0,
        string $feedback = '',
        string $actual = '',
        string $expected = ''
    ) {
        $this->testid = $testid;
        $this->name = $name;
        $this->passed = $passed;
        $this->ispublic = $ispublic;
        $this->weight = $weight;
        $this->feedback = $feedback;
        $this->actual = $actual;
        $this->expected = $expected;
    }

    /**
     * Get the test id.
     *
     * @return string
     */
    public function get_test_id(): string {
        return $this->testid;
    }

    /**
     * Get the student facing name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Whether the test passed.
     *
     * @return bool
     */
    public function has_passed(): bool {
        return $this->passed;
    }

    /**
     * Whether details may be shown to a student.
     *
     * @return bool
     */
    public function is_public(): bool {
        return $this->ispublic;
    }

    /**
     * Scoring weight.
     *
     * @return float
     */
    public function get_weight(): float {
        return $this->weight;
    }

    /**
     * Author feedback for this outcome.
     *
     * @return string
     */
    public function get_feedback(): string {
        return $this->feedback;
    }

    /**
     * Build the representation a student may see.
     *
     * A hidden test is reduced to a counted outcome with no name, no comparison
     * values and no feedback that could reconstruct the assertion.
     *
     * @return array
     */
    public function export_for_student(): array {
        if (!$this->ispublic) {
            return [
                'testid' => '',
                'name' => get_string('hiddentest', 'local_saylorcode'),
                'passed' => $this->passed,
                'ispublic' => false,
                'feedback' => '',
                'actual' => '',
                'expected' => '',
            ];
        }
        return [
            'testid' => $this->testid,
            'name' => $this->name,
            'passed' => $this->passed,
            'ispublic' => true,
            'feedback' => $this->feedback,
            'actual' => $this->actual,
            'expected' => $this->expected,
        ];
    }
}
