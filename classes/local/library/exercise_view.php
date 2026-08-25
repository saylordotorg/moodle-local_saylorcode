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

namespace local_saylorcode\local\library;

/**
 * What the read-only exercise page is allowed to show.
 *
 * The standalone page renders a published exercise to anyone who can reach the
 * reference, which is a wider audience than a student in a graded activity. The
 * one rule that matters here is that a hidden test is never disclosed: its name,
 * its expected output and its input must not leave the server through this page,
 * or the assessment it protects is worthless. Deciding that in one tested place
 * keeps the page from getting it subtly wrong.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exercise_view {
    /**
     * The sample tests a reader may see: the public cases, and only their name
     * and expected output.
     *
     * A case with no explicit visibility is treated as hidden here. That is the
     * safe default for a disclosure decision: the authoring form marks a case
     * public deliberately, and anything that reaches this page without that mark
     * -- migrated data, a hand-written row -- should be withheld rather than
     * revealed on a guess. (The activity form defaults a new row to public for
     * editing convenience; that is a different decision from what a public page
     * may disclose.)
     *
     * @param resolved_exercise $resolved The exercise being shown.
     * @return array[] Each with name and expected, in author order.
     */
    public static function sample_tests(resolved_exercise $resolved): array {
        $samples = [];

        foreach ($resolved->get_test_cases() as $case) {
            if (!is_array($case) || empty($case['ispublic'])) {
                continue;
            }

            $samples[] = [
                'name' => (string) ($case['name'] ?? ''),
                'expected' => (string) ($case['expected'] ?? ''),
            ];
        }

        return $samples;
    }
}
