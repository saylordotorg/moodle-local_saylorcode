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

use local_saylorcode\local\library\exercise_view;
use local_saylorcode\local\library\resolved_exercise;

/**
 * Tests for what the read-only exercise page may disclose.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\library\exercise_view
 */
final class exercise_view_test extends \advanced_testcase {
    /**
     * A resolved exercise carrying the given cases.
     *
     * @param array $cases Test cases.
     * @return resolved_exercise
     */
    private function resolved(array $cases): resolved_exercise {
        $version = (object) [
            'version' => 1,
            'entryfilename' => 'Main.java',
            'startercode' => '',
            'referencesolution' => '',
            'testcases' => json_encode($cases),
            'hints' => '',
        ];

        return new resolved_exercise($version, (object) ['stableid' => 'CS101-U01-E01'], 'latest');
    }

    /**
     * Only public cases are returned, and only their name and expected output.
     */
    public function test_only_public_cases_are_returned(): void {
        $resolved = $this->resolved([
            ['name' => 'greets', 'stdin' => 'x', 'expected' => 'Hello', 'ispublic' => true, 'weight' => 1],
            ['name' => 'secret', 'stdin' => 'y', 'expected' => '42', 'ispublic' => false, 'weight' => 1],
        ]);

        $samples = exercise_view::sample_tests($resolved);

        $this->assertCount(1, $samples);
        $this->assertSame('greets', $samples[0]['name']);
        $this->assertSame('Hello', $samples[0]['expected']);
        $this->assertSame(['name', 'expected'], array_keys($samples[0]));
    }

    /**
     * A hidden case's name and expected output never appear in the result.
     *
     * The assertion that matters: nothing identifying the hidden case leaks,
     * whatever else the page does with what it is given.
     */
    public function test_a_hidden_case_is_never_disclosed(): void {
        $resolved = $this->resolved([
            ['name' => 'public one', 'expected' => 'ok', 'ispublic' => true, 'weight' => 1],
            ['name' => 'hidden edge case', 'expected' => 'SECRET-EXPECTED', 'ispublic' => false, 'weight' => 1],
        ]);

        $encoded = json_encode(exercise_view::sample_tests($resolved));

        $this->assertStringNotContainsString('hidden edge case', $encoded);
        $this->assertStringNotContainsString('SECRET-EXPECTED', $encoded);
    }

    /**
     * A case with no explicit visibility is withheld.
     *
     * On a broadly reachable page, absent has to read as hidden: an unmarked
     * case is disclosed only if someone said it may be.
     */
    public function test_an_unmarked_case_is_withheld(): void {
        $resolved = $this->resolved([
            ['name' => 'unmarked', 'expected' => 'value'],
        ]);

        $this->assertSame([], exercise_view::sample_tests($resolved));
    }

    /**
     * A malformed case entry is skipped rather than fatal.
     */
    public function test_malformed_entries_are_skipped(): void {
        $resolved = $this->resolved([
            null,
            'not an array',
            ['name' => 'good', 'expected' => 'ok', 'ispublic' => true],
        ]);

        $samples = exercise_view::sample_tests($resolved);

        $this->assertCount(1, $samples);
        $this->assertSame('good', $samples[0]['name']);
    }

    /**
     * An exercise with a hidden case reports having hidden tests.
     */
    public function test_hidden_tests_are_detected(): void {
        $resolved = $this->resolved([
            ['name' => 'shown', 'expected' => 'ok', 'ispublic' => true],
            ['name' => 'hidden', 'expected' => '42', 'ispublic' => false],
        ]);

        $this->assertTrue(exercise_view::has_hidden_tests($resolved));
    }

    /**
     * A starter-only exercise with no tests reports no hidden tests.
     *
     * The distinction the page needs: this must not claim hidden tests run
     * when there are none. An all-public set has none hidden either.
     */
    public function test_no_hidden_tests_when_there_are_none(): void {
        $this->assertFalse(exercise_view::has_hidden_tests($this->resolved([])));
        $this->assertFalse(exercise_view::has_hidden_tests($this->resolved([
            ['name' => 'only public', 'expected' => 'ok', 'ispublic' => true],
        ])));
    }
}
