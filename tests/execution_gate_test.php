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

use local_saylorcode\local\runner\execution_gate;

/**
 * Tests for the execution gate.
 *
 * The per user limit existed and worked. What did not exist was any bound on
 * the site as a whole: the gate was keyed on user id, so a class of students at
 * two executions each could put hundreds of simultaneous requests to one runner
 * and nothing would stop it.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\execution_gate
 */
final class execution_gate_test extends \advanced_testcase {
    /**
     * A user is held to their own limit.
     *
     * @return void
     */
    public function test_a_user_is_held_to_their_own_limit(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 2, 'local_saylorcode');
        set_config('maxconcurrentsite', 0, 'local_saylorcode');

        $gate = new execution_gate(101);

        $this->assertNotNull($gate->acquire());
        $this->assertNotNull($gate->acquire());
        $this->assertNull($gate->acquire(), 'A third concurrent execution was allowed.');
        $this->assertSame(execution_gate::DENIED_USER, $gate->get_denial());
    }

    /**
     * One user's traffic does not consume another's allowance.
     *
     * A class pressing Run together is the normal case and must keep working.
     *
     * @return void
     */
    public function test_users_do_not_consume_each_other(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 1, 'local_saylorcode');
        set_config('maxconcurrentsite', 0, 'local_saylorcode');

        $this->assertNotNull((new execution_gate(201))->acquire());
        $this->assertNotNull((new execution_gate(202))->acquire());
        $this->assertNotNull((new execution_gate(203))->acquire());
    }

    /**
     * The site limit bounds the total, across users.
     *
     * This is the gap: every one of these is within its own user's allowance,
     * and before the site limit existed all of them proceeded.
     *
     * @return void
     */
    public function test_the_site_limit_bounds_the_total(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 5, 'local_saylorcode');
        set_config('maxconcurrentsite', 3, 'local_saylorcode');

        $this->assertNotNull((new execution_gate(301))->acquire());
        $this->assertNotNull((new execution_gate(302))->acquire());
        $this->assertNotNull((new execution_gate(303))->acquire());

        $fourth = new execution_gate(304);
        $this->assertNull($fourth->acquire(), 'A fourth user ran despite the site being at its limit.');
        $this->assertSame(execution_gate::DENIED_SITE, $fourth->get_denial());
    }

    /**
     * The two refusals are distinguishable.
     *
     * They need different words in front of a student, so the caller has to be
     * able to tell them apart.
     *
     * @return void
     */
    public function test_the_two_refusals_are_told_apart(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 1, 'local_saylorcode');
        set_config('maxconcurrentsite', 2, 'local_saylorcode');

        $first = new execution_gate(401);
        $first->acquire();
        $this->assertNull($first->acquire());
        $this->assertSame(execution_gate::DENIED_USER, $first->get_denial(), 'At their own limit.');

        (new execution_gate(402))->acquire();

        $third = new execution_gate(403);
        $this->assertNull($third->acquire());
        $this->assertSame(execution_gate::DENIED_SITE, $third->get_denial(), 'Site saturated, not their fault.');
    }

    /**
     * Releasing frees the site slot as well as the user's.
     *
     * Freeing only the user's slot would leak the site counter upwards until
     * the leases expired, and the site would refuse everything for a minute
     * after any burst.
     *
     * @return void
     */
    public function test_release_frees_the_site_slot(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 5, 'local_saylorcode');
        set_config('maxconcurrentsite', 1, 'local_saylorcode');

        $first = new execution_gate(501);
        $lease = $first->acquire();
        $this->assertNotNull($lease);

        $second = new execution_gate(502);
        $this->assertNull($second->acquire(), 'The site limit is one, so this must wait.');

        $first->release($lease);

        $this->assertNotNull(
            (new execution_gate(502))->acquire(),
            'The site slot was not returned on release, so the counter leaks.'
        );
    }

    /**
     * A refused acquire consumes nothing.
     *
     * If the site slot were taken before the user check, or kept after a
     * refusal, a student at their own limit would silently eat site capacity
     * every time they pressed Run.
     *
     * @return void
     */
    public function test_a_refusal_consumes_no_site_capacity(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 1, 'local_saylorcode');
        set_config('maxconcurrentsite', 2, 'local_saylorcode');

        $blocked = new execution_gate(601);
        $blocked->acquire();

        // Refused four times, each at their own limit.
        for ($i = 0; $i < 4; $i++) {
            $this->assertNull($blocked->acquire());
        }

        // One slot of the two is used. The second must still be free.
        $this->assertNotNull(
            (new execution_gate(602))->acquire(),
            'Refused attempts consumed site capacity they never used.'
        );
    }

    /**
     * Zero means no site limit, which is the shipped default.
     *
     * @return void
     */
    public function test_zero_means_no_site_limit(): void {
        $this->resetAfterTest();
        set_config('maxconcurrentperuser', 1, 'local_saylorcode');
        set_config('maxconcurrentsite', 0, 'local_saylorcode');

        for ($userid = 700; $userid < 720; $userid++) {
            $this->assertNotNull((new execution_gate($userid))->acquire());
        }
    }
}
