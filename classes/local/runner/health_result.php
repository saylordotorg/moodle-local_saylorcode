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
 * Outcome of a provider health probe.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class health_result {
    /** @var bool Whether the backend answered correctly. */
    private bool $healthy;

    /** @var string Untranslated diagnostic detail, safe for administrator display. */
    private string $detail;

    /** @var float Round trip time in seconds. */
    private float $latency;

    /** @var string[] Runtime profile ids the backend reported. */
    private array $profiles;

    /**
     * Build a health result.
     *
     * @param bool $healthy Whether the backend is usable.
     * @param string $detail Diagnostic detail for administrators.
     * @param float $latency Round trip seconds.
     * @param string[] $profiles Reported runtime profile ids.
     */
    public function __construct(bool $healthy, string $detail = '', float $latency = 0.0, array $profiles = []) {
        $this->healthy = $healthy;
        $this->detail = $detail;
        $this->latency = $latency;
        $this->profiles = $profiles;
    }

    /**
     * Whether the backend is usable.
     *
     * @return bool
     */
    public function is_healthy(): bool {
        return $this->healthy;
    }

    /**
     * Diagnostic detail.
     *
     * @return string
     */
    public function get_detail(): string {
        return $this->detail;
    }

    /**
     * Round trip seconds.
     *
     * @return float
     */
    public function get_latency(): float {
        return $this->latency;
    }

    /**
     * Reported runtime profile ids.
     *
     * @return string[]
     */
    public function get_profiles(): array {
        return $this->profiles;
    }
}
