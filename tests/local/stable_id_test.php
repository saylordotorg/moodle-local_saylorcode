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

namespace local_saylorcode\local;

use coding_exception;

/**
 * Tests for exercise stable identifiers.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\stable_id
 */
final class stable_id_test extends \advanced_testcase {
    /**
     * A well formed id parses into its components.
     */
    public function test_parse_extracts_components(): void {
        $id = stable_id::parse('CS101-U05-E03');

        $this->assertSame('CS101', $id->get_course());
        $this->assertSame(5, $id->get_unit());
        $this->assertSame(3, $id->get_exercise());
    }

    /**
     * Parsing and printing round trip without changing the value.
     */
    public function test_round_trip_is_stable(): void {
        $this->assertSame('CS101-U05-E03', (string) stable_id::parse('CS101-U05-E03'));
    }

    /**
     * Input is normalised to upper case so references are case insensitive.
     */
    public function test_lower_case_input_is_normalised(): void {
        $this->assertSame('CS101-U05-E03', (string) stable_id::parse('cs101-u05-e03'));
    }

    /**
     * Surrounding whitespace from a pasted embed token is tolerated.
     */
    public function test_surrounding_whitespace_is_trimmed(): void {
        $this->assertSame('CS101-U05-E03', (string) stable_id::parse('  CS101-U05-E03  '));
    }

    /**
     * Values that must not be accepted as stable ids.
     *
     * @return array Each entry is a single candidate value.
     */
    public static function malformed_provider(): array {
        return [
            'empty' => [''],
            'missing exercise' => ['CS101-U05'],
            'single digit unit' => ['CS101-U5-E03'],
            'single digit exercise' => ['CS101-U05-E3'],
            'lower case markers' => ['CS101-X05-E03'],
            'leading digit in course' => ['1CS-U05-E03'],
            'course too long' => ['ABCDEFGHIJK-U05-E03'],
            'sql fragment' => ["CS101-U05-E03' OR '1'='1"],
            'path traversal' => ['../../CS101-U05-E03'],
            'html' => ['<b>CS101-U05-E03</b>'],
        ];
    }

    /**
     * Malformed ids are rejected rather than coerced.
     *
     * @param string $value The candidate value.
     * @dataProvider malformed_provider
     */
    public function test_malformed_ids_are_rejected(string $value): void {
        $this->assertFalse(stable_id::is_valid($value));

        $this->expectException(coding_exception::class);
        stable_id::parse($value);
    }

    /**
     * Components are zero padded so ids sort predictably.
     */
    public function test_components_are_zero_padded(): void {
        $id = new stable_id('CS101', 5, 3);

        $this->assertSame('CS101-U05-E03', (string) $id);
    }
}
