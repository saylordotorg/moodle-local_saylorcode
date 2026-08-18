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

use coding_exception;

/**
 * Structured result returned by a runner provider.
 *
 * The object holds the complete result for server side scoring and reporting.
 * Anything bound for the browser must go through export_for_student(), which
 * strips hidden test detail and sanitises diagnostics. Callers should never
 * hand raw properties to a template.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class execution_response {

    /** @var string Correlating request id. */
    private string $requestid;

    /** @var string One of the execution_state constants. */
    private string $state;

    /** @var int|null Process exit code where the backend reported one. */
    private ?int $exitcode;

    /** @var string Program standard output. */
    private string $stdout;

    /** @var string Program standard error. */
    private string $stderr;

    /** @var string Compiler diagnostics. */
    private string $compileroutput;

    /** @var test_result[] Individual test outcomes. */
    private array $testresults;

    /** @var float Seconds spent queued before execution began. */
    private float $queuetime;

    /** @var float Seconds spent compiling and executing. */
    private float $runtime;

    /** @var bool Whether output was truncated at the limit. */
    private bool $truncated;

    /** @var string Machine readable diagnostic code, safe to log and display. */
    private string $diagnostic;

    /**
     * Build a response.
     *
     * @param string $requestid Correlating request id.
     * @param string $state One of the execution_state constants.
     * @param string $stdout Standard output.
     * @param string $stderr Standard error.
     * @param string $compileroutput Compiler diagnostics.
     * @param test_result[] $testresults Test outcomes.
     * @param int|null $exitcode Exit code.
     * @param float $queuetime Seconds queued.
     * @param float $runtime Seconds executing.
     * @param bool $truncated Whether output was truncated.
     * @param string $diagnostic Safe diagnostic code.
     * @throws coding_exception If the state is not recognised.
     */
    public function __construct(
        string $requestid,
        string $state,
        string $stdout = '',
        string $stderr = '',
        string $compileroutput = '',
        array $testresults = [],
        ?int $exitcode = null,
        float $queuetime = 0.0,
        float $runtime = 0.0,
        bool $truncated = false,
        string $diagnostic = ''
    ) {
        if (!execution_state::is_valid($state)) {
            throw new coding_exception('Unknown execution state: ' . $state);
        }
        $this->requestid = $requestid;
        $this->state = $state;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->compileroutput = $compileroutput;
        $this->testresults = $testresults;
        $this->exitcode = $exitcode;
        $this->queuetime = $queuetime;
        $this->runtime = $runtime;
        $this->truncated = $truncated;
        $this->diagnostic = $diagnostic;
    }

    /**
     * Convenience constructor for an unreachable backend.
     *
     * @param string $requestid Correlating request id.
     * @param string $detail Administrator facing detail.
     * @return self
     */
    public static function unavailable(string $requestid, string $detail = ''): self {
        return new self(
            $requestid,
            execution_state::RUNNER_UNAVAILABLE,
            '',
            '',
            '',
            [],
            null,
            0.0,
            0.0,
            false,
            $detail
        );
    }

    /**
     * Get the request id.
     *
     * @return string
     */
    public function get_request_id(): string {
        return $this->requestid;
    }

    /**
     * Get the execution state.
     *
     * @return string
     */
    public function get_state(): string {
        return $this->state;
    }

    /**
     * Get all test results, including hidden ones.
     *
     * @return test_result[]
     */
    public function get_test_results(): array {
        return $this->testresults;
    }

    /**
     * Get the exit code.
     *
     * @return int|null
     */
    public function get_exit_code(): ?int {
        return $this->exitcode;
    }

    /**
     * Seconds spent executing.
     *
     * @return float
     */
    public function get_runtime(): float {
        return $this->runtime;
    }

    /**
     * Seconds spent queued.
     *
     * @return float
     */
    public function get_queue_time(): float {
        return $this->queuetime;
    }

    /**
     * Whether output was truncated.
     *
     * @return bool
     */
    public function was_truncated(): bool {
        return $this->truncated;
    }

    /**
     * Safe diagnostic code.
     *
     * @return string
     */
    public function get_diagnostic(): string {
        return $this->diagnostic;
    }

    /**
     * Weighted proportion of tests passed, between 0 and 1.
     *
     * Hidden tests count towards the score even though their detail is never
     * disclosed. A response with no tests scores zero rather than full marks,
     * so a misconfigured exercise cannot award credit by accident.
     *
     * @return float
     */
    public function get_score_fraction(): float {
        $total = 0.0;
        $earned = 0.0;
        foreach ($this->testresults as $result) {
            $total += $result->get_weight();
            if ($result->has_passed()) {
                $earned += $result->get_weight();
            }
        }
        if ($total <= 0.0) {
            return 0.0;
        }
        return $earned / $total;
    }

    /**
     * Whether every test passed.
     *
     * @return bool
     */
    public function all_tests_passed(): bool {
        if (empty($this->testresults)) {
            return false;
        }
        foreach ($this->testresults as $result) {
            if (!$result->has_passed()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build the payload a student may receive.
     *
     * Hidden test detail is removed, and stderr and compiler output are passed
     * through the sanitiser so that server paths and wrapper scaffolding never
     * reach the browser.
     *
     * @return array
     */
    public function export_for_student(): array {
        $tests = [];
        foreach ($this->testresults as $result) {
            $tests[] = $result->export_for_student();
        }

        return [
            'state' => $this->state,
            'stdout' => $this->stdout,
            'stderr' => output_sanitiser::sanitise($this->stderr),
            'compileroutput' => output_sanitiser::sanitise($this->compileroutput),
            'tests' => $tests,
            'truncated' => $this->truncated,
            'runtime' => round($this->runtime, 3),
            'diagnostic' => $this->diagnostic,
        ];
    }
}
