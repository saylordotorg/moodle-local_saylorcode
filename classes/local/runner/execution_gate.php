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

use cache;

/**
 * Limits how much execution one user may have in flight.
 *
 * A class of students hitting Run at the same moment is normal; one browser tab
 * hammering Run in a loop is not. This gate bounds the second case without
 * getting in the way of the first (specification sections 13.7 and 14.4).
 *
 * Enforcement is deliberately optimistic. The counter lives in the application
 * cache rather than the database, because the cost of an occasional miscount
 * under a race is far lower than the cost of a write on every keystroke driven
 * execution.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_gate {
    /** @var int Seconds after which a stale in flight marker is ignored. */
    private const LEASE_SECONDS = 60;

    /** @var int The user being limited. */
    protected int $userid;

    /**
     * Build a gate for one user.
     *
     * @param int $userid The user id.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Try to claim a slot.
     *
     * @return string|null A lease token to release later, or null when the user is at their limit.
     */
    public function acquire(): ?string {
        $cache = $this->get_cache();
        $leases = $this->live_leases($cache);

        if (count($leases) >= $this->get_limit()) {
            return null;
        }

        $token = uniqid('scl', true);
        $leases[$token] = time();
        $cache->set($this->key(), $leases);

        return $token;
    }

    /**
     * Release a previously claimed slot.
     *
     * Safe to call with an unknown or already released token, so it can sit in
     * a finally block without further checks.
     *
     * @param string|null $token The lease token returned by acquire().
     */
    public function release(?string $token): void {
        if ($token === null) {
            return;
        }

        $cache = $this->get_cache();
        $leases = $this->live_leases($cache);
        unset($leases[$token]);
        $cache->set($this->key(), $leases);
    }

    /**
     * How many executions this user may have in flight.
     *
     * @return int
     */
    protected function get_limit(): int {
        $limit = (int) get_config('local_saylorcode', 'maxconcurrentperuser');

        return $limit > 0 ? $limit : 2;
    }

    /**
     * Current leases, with expired ones discarded.
     *
     * A request that dies without releasing its lease would otherwise lock the
     * student out permanently, so leases expire on their own.
     *
     * @param cache $cache The cache to read.
     * @return array Token => timestamp.
     */
    protected function live_leases(cache $cache): array {
        $leases = $cache->get($this->key());
        if (!is_array($leases)) {
            return [];
        }

        $cutoff = time() - self::LEASE_SECONDS;

        return array_filter($leases, static function ($started) use ($cutoff): bool {
            return (int) $started > $cutoff;
        });
    }

    /**
     * Cache key for this user.
     *
     * @return string
     */
    protected function key(): string {
        return 'inflight_' . $this->userid;
    }

    /**
     * The cache holding in flight markers.
     *
     * @return cache
     */
    protected function get_cache(): cache {
        return cache::make('local_saylorcode', 'executiongate');
    }
}
