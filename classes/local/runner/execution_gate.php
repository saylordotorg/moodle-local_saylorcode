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
 * Limits how much execution is in flight, per user and across the site.
 *
 * Two different problems, and conflating them would be a mistake.
 *
 * The per user limit bounds one browser tab hammering Run in a loop. A class of
 * students all pressing Run at once is normal and must not be affected
 * (specification sections 13.7 and 14.4).
 *
 * The site limit bounds that class. Until it existed, nothing did: the gate was
 * keyed on user id alone, so five hundred students at two each meant a thousand
 * simultaneous requests to a single runner, and the plugin would send every one
 * of them. Load testing would only have confirmed that, because it is a missing
 * control rather than an unmeasured one.
 *
 * The counters live in the application cache rather than the database, because
 * a database write on every keystroke driven execution would cost more than the
 * limit saves. The site counter is read and written under a lock, so the count
 * itself does not drift under load.
 *
 * It is still a shock absorber rather than admission control, for two reasons
 * worth knowing before anyone treats the number as a guarantee. A slot is
 * released by a lease that expires on its own after LEASE_SECONDS, so a request
 * that dies holding one keeps it until then. And if the lock cannot be taken the
 * execution is allowed through rather than refused, because turning lock
 * contention into a site wide outage would be the worse failure. A hard ceiling
 * belongs at the runner, which is the only thing that can actually refuse work.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_gate {
    /** @var string Denied because this user already has too much in flight. */
    public const DENIED_USER = 'user';

    /** @var string Denied because the site as a whole is saturated. */
    public const DENIED_SITE = 'site';

    /** @var int Seconds after which a stale in flight marker is ignored. */
    private const LEASE_SECONDS = 60;

    /** @var string The cache key holding site wide leases. */
    private const SITE_KEY = 'inflight_site';

    /** @var int The user being limited. */
    protected int $userid;

    /** @var string|null Why the last acquire() was refused. */
    protected ?string $denial = null;

    /**
     * Build a gate for one user.
     *
     * @param int $userid The user id.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Try to claim a slot, for this user and for the site.
     *
     * @return string|null A lease token to release later, or null when either limit is reached.
     */
    public function acquire(): ?string {
        $this->denial = null;

        $cache = $this->get_cache();
        $leases = $this->live_leases($cache, $this->key());

        if (count($leases) >= $this->get_user_limit()) {
            $this->denial = self::DENIED_USER;
            return null;
        }

        $token = uniqid('scl', true);

        // The site slot is taken first. Taking the user slot first and failing
        // here would leave a lease the caller has no token for, and the student
        // would be locked out of their own limit until it expired.
        if (!$this->claim_site_slot($token)) {
            $this->denial = self::DENIED_SITE;
            return null;
        }

        $leases[$token] = time();
        $cache->set($this->key(), $leases);

        return $token;
    }

    /**
     * Why the last acquire() was refused.
     *
     * The two cases need different words in front of a student. "You have too
     * many running" is true for one of them and a lie for the other, and being
     * told off for someone else's traffic is worse than being told to wait.
     *
     * @return string|null DENIED_USER, DENIED_SITE, or null if nothing was refused.
     */
    public function get_denial(): ?string {
        return $this->denial;
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
        $leases = $this->live_leases($cache, $this->key());
        unset($leases[$token]);
        $cache->set($this->key(), $leases);

        $sitecache = $this->get_site_cache();

        if (!$sitecache->acquire_lock(self::SITE_KEY)) {
            // Nothing useful to do: the lease expires on its own within
            // LEASE_SECONDS, so the slot returns either way.
            return;
        }

        try {
            $siteleases = $this->live_leases($sitecache, self::SITE_KEY);
            unset($siteleases[$token]);
            $sitecache->set(self::SITE_KEY, $siteleases);
        } finally {
            $sitecache->release_lock(self::SITE_KEY);
        }
    }

    /**
     * Take a site wide slot for this token.
     *
     * @param string $token The lease token.
     * @return bool Whether a slot was available.
     */
    protected function claim_site_slot(string $token): bool {
        $limit = $this->get_site_limit();

        // Zero means the site limit is off, which is the shipped default: a
        // ceiling nobody has sized for their runner would do more harm than
        // good, and the per user limit still applies.
        if ($limit <= 0) {
            return true;
        }

        $cache = $this->get_site_cache();

        // Every request on the site reads and writes this one key, so the
        // read-modify-write has to be held under a lock or the count drifts
        // downwards under exactly the load it exists to bound.
        if (!$cache->acquire_lock(self::SITE_KEY)) {
            // Deliberately allowed through. Refusing when the lock cannot be
            // taken would turn a contended lock into a site wide outage, and
            // this limit is a shock absorber rather than admission control.
            // The per user limit still applies.
            return true;
        }

        try {
            $leases = $this->live_leases($cache, self::SITE_KEY);

            if (count($leases) >= $limit) {
                return false;
            }

            $leases[$token] = time();
            $cache->set(self::SITE_KEY, $leases);

            return true;
        } finally {
            $cache->release_lock(self::SITE_KEY);
        }
    }

    /**
     * How many executions this user may have in flight.
     *
     * @return int
     */
    protected function get_user_limit(): int {
        $limit = (int) get_config('local_saylorcode', 'maxconcurrentperuser');

        return $limit > 0 ? $limit : 2;
    }

    /**
     * How many executions the whole site may have in flight.
     *
     * @return int Zero for no limit.
     */
    protected function get_site_limit(): int {
        return (int) get_config('local_saylorcode', 'maxconcurrentsite');
    }

    /**
     * Current leases under a key, with expired ones discarded.
     *
     * A request that dies without releasing its lease would otherwise consume a
     * slot permanently, so leases expire on their own.
     *
     * @param cache $cache The cache to read.
     * @param string $key The key to read.
     * @return array Token => timestamp.
     */
    protected function live_leases(cache $cache, string $key): array {
        $leases = $cache->get($key);
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
     * The cache holding per user in flight markers.
     *
     * @return cache
     */
    protected function get_cache(): cache {
        return cache::make('local_saylorcode', 'executiongate');
    }

    /**
     * The cache holding the site wide counter.
     *
     * A separate definition because every request on the site writes this one
     * key. It locks on write and does not use static acceleration, so a request
     * cannot release against a copy it read before other requests moved it.
     *
     * @return cache
     */
    protected function get_site_cache(): cache {
        return cache::make('local_saylorcode', 'executiongatesite');
    }
}
