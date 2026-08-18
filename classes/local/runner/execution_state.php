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
 * Canonical execution states shared by every runner provider.
 *
 * These values are persisted in the execution record and are part of the
 * provider contract, so they must not be renamed without an upgrade step.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_state {
    /** @var string Request accepted and waiting for a runner slot. */
    public const QUEUED = 'queued';

    /** @var string Source is being compiled. */
    public const COMPILING = 'compiling';

    /** @var string Student program is executing. */
    public const RUNNING = 'running';

    /** @var string Execution finished and all evaluated tests passed. */
    public const COMPLETED = 'completed';

    /** @var string Source failed to compile. */
    public const COMPILE_ERROR = 'compile_error';

    /** @var string Program terminated with an uncaught error. */
    public const RUNTIME_ERROR = 'runtime_error';

    /** @var string Program ran but one or more tests failed. */
    public const FAILED_TESTS = 'failed_tests';

    /** @var string Wall clock or CPU limit exceeded. */
    public const TIMEOUT = 'timeout';

    /** @var string Memory limit exceeded. */
    public const MEMORY_LIMIT = 'memory_limit';

    /** @var string Output limit exceeded. */
    public const OUTPUT_LIMIT = 'output_limit';

    /** @var string Process or thread limit exceeded. */
    public const PROCESS_LIMIT = 'process_limit';

    /** @var string Request cancelled before completion. */
    public const CANCELLED = 'cancelled';

    /** @var string No healthy runner was reachable. */
    public const RUNNER_UNAVAILABLE = 'runner_unavailable';

    /** @var string Unexpected platform failure. */
    public const INTERNAL_ERROR = 'internal_error';

    /**
     * Every valid state.
     *
     * @return string[]
     */
    public static function all(): array {
        return [
            self::QUEUED,
            self::COMPILING,
            self::RUNNING,
            self::COMPLETED,
            self::COMPILE_ERROR,
            self::RUNTIME_ERROR,
            self::FAILED_TESTS,
            self::TIMEOUT,
            self::MEMORY_LIMIT,
            self::OUTPUT_LIMIT,
            self::PROCESS_LIMIT,
            self::CANCELLED,
            self::RUNNER_UNAVAILABLE,
            self::INTERNAL_ERROR,
        ];
    }

    /**
     * States that represent a finished request, successful or not.
     *
     * @return string[]
     */
    public static function terminal_states(): array {
        return array_values(array_diff(self::all(), [self::QUEUED, self::COMPILING, self::RUNNING]));
    }

    /**
     * Whether the state indicates the platform failed rather than the student's code.
     *
     * Used to decide whether an attempt should be recorded as a genuine failure.
     *
     * @param string $state One of the class constants.
     * @return bool
     */
    public static function is_platform_failure(string $state): bool {
        return in_array($state, [self::RUNNER_UNAVAILABLE, self::INTERNAL_ERROR], true);
    }

    /**
     * Whether the state means a resource limit stopped the program.
     *
     * @param string $state One of the class constants.
     * @return bool
     */
    public static function is_resource_limit(string $state): bool {
        return in_array($state, [
            self::TIMEOUT,
            self::MEMORY_LIMIT,
            self::OUTPUT_LIMIT,
            self::PROCESS_LIMIT,
        ], true);
    }

    /**
     * Whether the state is valid.
     *
     * @param string $state Candidate state.
     * @return bool
     */
    public static function is_valid(string $state): bool {
        return in_array($state, self::all(), true);
    }
}
