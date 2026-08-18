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
 * Contract every execution backend must satisfy.
 *
 * Exercise content references a stable runtime profile id and never a provider
 * URL, image name or shell command, so a second provider can be introduced
 * without editing a single exercise (specification sections 5.9 and 13.3).
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider_interface {
    /**
     * Short stable identifier for this provider, for example 'jobe'.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check whether the backend is reachable and healthy.
     *
     * Implementations must not throw for an unreachable backend; an unhealthy
     * result is a normal operating condition that the interface reports rather
     * than an exception, so callers can degrade gracefully.
     *
     * @return health_result
     */
    public function get_health(): health_result;

    /**
     * Runtime profile ids this provider can currently service.
     *
     * @return string[]
     */
    public function get_supported_profiles(): array;

    /**
     * Execute a request and return a structured result.
     *
     * Implementations must never throw for ordinary student failures such as a
     * compile error or a failing test. Those are represented as states on the
     * response. Exceptions are reserved for programming errors.
     *
     * @param execution_request $request The work to perform.
     * @return execution_response
     */
    public function execute(execution_request $request): execution_response;

    /**
     * Cancel a queued or running request where the backend supports it.
     *
     * @param string $requestid The request to cancel.
     * @return bool True if the backend accepted the cancellation.
     */
    public function cancel(string $requestid): bool;

    /**
     * Whether this provider supports cancellation at all.
     *
     * @return bool
     */
    public function supports_cancellation(): bool;
}
