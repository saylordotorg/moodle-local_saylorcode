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

use local_saylorcode\local\runtime\profile_manager;
use local_saylorcode\local\stable_id;
use stdClass;

/**
 * Whether an exercise is fit to be approved for use.
 *
 * Specification section 10.8 lists what publication validation must confirm.
 * This runs the parts that can be established from what the plugin actually
 * stores; the rest are named in deferred() so the gap is visible rather than
 * quietly missing, and so nobody reads a pass here as "the whole checklist
 * passed".
 *
 * These checks gate the move to Ready rather than the act of publishing a
 * version. Publishing freezes content and is how an author iterates; Ready is
 * the promise that the content is fit for a student, which is the promise worth
 * checking.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publication_check {
    /** @var exercise_repository The library. */
    protected exercise_repository $repository;

    /** @var solution_validator Runs the reference solution. */
    protected solution_validator $validator;

    /**
     * Build the check.
     *
     * @param exercise_repository|null $repository The library, defaulting to the real one.
     * @param solution_validator|null $validator The validator, defaulting to the real one.
     */
    public function __construct(
        ?exercise_repository $repository = null,
        ?solution_validator $validator = null
    ) {
        $this->repository = $repository ?? new exercise_repository();
        $this->validator = $validator ?? new solution_validator();
    }

    /**
     * The checks from section 10.8 this cannot yet make, as language string keys.
     *
     * Each needs data the plugin does not hold yet: the metadata fields of
     * section 10.3, a record that a preview was reviewed, and an accessibility
     * pass over the instructions. Named here so a caller can show an approver
     * what is still on them to judge by eye.
     *
     * @return string[]
     */
    public static function deferred(): array {
        return [
            'checkdeferredmetadata',
            'checkdeferredattribution',
            'checkdeferredaccessibility',
            'checkdeferredpreview',
        ];
    }

    /**
     * Run the checks.
     *
     * @param stdClass $exercise The exercise.
     * @return array {
     *     passed: bool Whether every check that could run passed.
     *     failures: string[] Human readable reasons, empty when it passed.
     *     ran: int How many checks were made.
     * }
     */
    public function run(stdClass $exercise): array {
        $failures = [];
        $ran = 0;

        // Stable ID present and well formed. Uniqueness is enforced by a unique
        // index on the table, so reaching here at all satisfies it.
        $ran++;
        if (!stable_id::is_valid((string) $exercise->stableid)) {
            $failures[] = get_string('checkfailstableid', 'local_saylorcode');
        }

        // Runtime enabled. An exercise whose language has been turned off site
        // wide cannot be attempted, so approving it would promise nothing.
        $ran++;
        $profile = (new profile_manager())->get_profile((string) $exercise->profileid);
        if ($profile === null) {
            $failures[] = get_string('checkfailruntime', 'local_saylorcode', s((string) $exercise->profileid));
        }

        // A published version to approve. Ready describes content students can
        // be sent to, and a draft is not that.
        $ran++;
        $version = $this->repository->get_latest($exercise);
        if ($version === null) {
            $failures[] = get_string('checkfailnoversion', 'local_saylorcode');

            // Everything below reads the version, so there is nothing further
            // to say about an exercise that has none.
            return $this->report($failures, $ran);
        }

        // Tests to grade against.
        $ran++;
        $cases = json_decode((string) $version->testcases, true);
        $cases = is_array($cases) ? $cases : [];
        if ($cases === []) {
            $failures[] = get_string('checkfailnotests', 'local_saylorcode');
        }

        // A reference solution for automatically graded content.
        $ran++;
        if (trim((string) $version->referencesolution) === '') {
            $failures[] = get_string('checkfailnosolution', 'local_saylorcode');
        }

        // At least one useful failure message, so a student who gets it wrong
        // is told something.
        $ran++;
        $hasfeedback = false;
        foreach ($cases as $case) {
            if (is_array($case) && trim((string) ($case['feedback'] ?? '')) !== '') {
                $hasfeedback = true;
                break;
            }
        }
        if ($cases !== [] && !$hasfeedback) {
            $failures[] = get_string('checkfailnofeedback', 'local_saylorcode');
        }

        // The reference solution passes every test. The expensive one, and the
        // one that matters most: it is the only check that can tell an author
        // their expected values are wrong.
        $ran++;
        if ($profile !== null && $cases !== [] && trim((string) $version->referencesolution) !== '') {
            $report = $this->validator->validate($version, (string) $exercise->profileid);

            if (!$report['valid']) {
                $detail = get_string($report['reason'], 'local_saylorcode');
                if (!empty($report['failed'])) {
                    $detail .= ' (' . implode(', ', $report['failed']) . ')';
                }
                $failures[] = get_string('checkfailsolution', 'local_saylorcode', $detail);
            }
        }

        return $this->report($failures, $ran);
    }

    /**
     * Shape the outcome.
     *
     * @param string[] $failures What failed.
     * @param int $ran How many checks were made.
     * @return array
     */
    protected function report(array $failures, int $ran): array {
        return [
            'passed' => $failures === [],
            'failures' => $failures,
            'ran' => $ran,
        ];
    }
}
