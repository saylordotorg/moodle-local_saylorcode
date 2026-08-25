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

use local_saylorcode\local\runner\execution_request;
use local_saylorcode\local\runner\execution_response;
use local_saylorcode\local\runner\execution_state;
use local_saylorcode\local\runner\health_result;
use local_saylorcode\local\runner\provider_interface;

/**
 * A provider that returns scripted outputs, for testing without a runner.
 *
 * Each call to execute() returns the next queued stdout under the configured
 * state, so a test can stage exactly what the runner would say and assert what
 * the caller does with it.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scripted_provider implements provider_interface {
    /** @var string[] Queued stdout values, one per execute() call. */
    public array $outputs = [];

    /** @var string The state every response reports. */
    public string $state = execution_state::COMPLETED;

    /** @var bool Whether execute() should throw, as a transport failure would. */
    public bool $throw = false;

    /** @var int How many times execute() has been called. */
    public int $calls = 0;

    /**
     * Build the provider.
     *
     * @param string[] $outputs Queued stdout values.
     */
    public function __construct(array $outputs = []) {
        $this->outputs = $outputs;
    }

    #[\Override]
    public function get_name(): string {
        return 'scripted';
    }

    #[\Override]
    public function get_health(): health_result {
        return new health_result(true, 'scripted');
    }

    #[\Override]
    public function get_supported_profiles(): array {
        return ['java17-console'];
    }

    #[\Override]
    public function execute(execution_request $request): execution_response {
        $this->calls++;

        if ($this->throw) {
            throw new \moodle_exception('runner is unreachable');
        }

        $stdout = array_shift($this->outputs) ?? '';

        return new execution_response(
            $request->get_request_id(),
            $this->state,
            $stdout,
            '',
            '',
            [],
            0,
            0.0,
            0.0
        );
    }

    #[\Override]
    public function cancel(string $requestid): bool {
        return false;
    }

    #[\Override]
    public function supports_cancellation(): bool {
        return false;
    }
}
