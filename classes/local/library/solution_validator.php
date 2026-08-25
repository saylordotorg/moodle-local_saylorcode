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

use local_saylorcode\local\runner\execution_gate;
use local_saylorcode\local\runner\execution_request;
use local_saylorcode\local\runner\execution_state;
use local_saylorcode\local\runner\jobe_provider;
use local_saylorcode\local\runner\provider_interface;
use stdClass;

/**
 * Runs a draft's reference solution against its own test cases.
 *
 * The library exists to keep a broken exercise away from students, and the
 * cheapest broken exercise to ship is one whose own reference solution does not
 * pass its own tests: a wrong expected value looks perfectly plausible on the
 * page and then fails every learner who meets it (specification section 10.8).
 * Publishing is the moment to catch it, because a published version is
 * immutable and will be graded against for as long as anything pins it.
 *
 * This lives in local_saylorcode rather than the activity module because the
 * runner does, and the module already reaches down here to reach it. The
 * activity form's Validate button and this check therefore judge with the same
 * code path, so an exercise that validates in one cannot fail in the other.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class solution_validator {
    /** @var provider_interface|null A backend injected for testing, used when none is passed. */
    protected static ?provider_interface $testprovider = null;

    /** @var provider_interface The execution backend. */
    protected provider_interface $provider;

    /**
     * Build the validator.
     *
     * @param provider_interface|null $provider Backend, defaulting to the configured one.
     */
    public function __construct(?provider_interface $provider = null) {
        $this->provider = $provider ?? self::$testprovider ?? jobe_provider::create_from_config();
    }

    /**
     * Inject a backend for code that constructs the validator itself.
     *
     * The web service builds its own validator, so a test driving the service
     * has nowhere to pass a scripted provider. This lets it stage one. It is a
     * test seam and nothing in production sets it.
     *
     * @param provider_interface|null $provider The backend, or null to clear it.
     */
    public static function set_test_provider(?provider_interface $provider): void {
        self::$testprovider = $provider;
    }

    /**
     * Validate a draft's reference solution against its test cases.
     *
     * A draft with no reference solution or no cases cannot be judged, and is
     * reported as not validatable rather than as passing: silence must never
     * read as a green light to publish.
     *
     * @param stdClass $draft A version row: entryfilename, profileid, referencesolution, testcases.
     * @param string $profileid The runtime to judge under. The draft carries no
     *                          profile of its own; the exercise does, and the
     *                          published version is stamped with it.
     * @return array {
     *     valid: bool Whether every case passed.
     *     validatable: bool Whether there was anything to judge.
     *     reason: string A language-string key describing the outcome.
     *     results: array Per case: name, passed, ispublic, expected, actual, state.
     *     failed: string[] Names of the cases the reference did not satisfy.
     *     compileroutput: string Compiler output, present only on a compile error.
     * }
     */
    public function validate(stdClass $draft, string $profileid): array {
        $solution = trim((string) ($draft->referencesolution ?? ''));
        if ($solution === '') {
            return $this->outcome(false, false, 'validatenosolution');
        }

        $cases = json_decode((string) ($draft->testcases ?? ''), true);
        if (!is_array($cases) || $cases === []) {
            return $this->outcome(false, false, 'validatenocases');
        }

        // Only entries that are actually runnable count. A decoded array that
        // is non-empty but holds nothing usable -- [null], or objects with no
        // expected value -- would otherwise run zero cases and, with an empty
        // failed list, read as a pass. Drafts can be written straight through
        // the repository as well as the form, so this cannot assume the form's
        // shaping has happened.
        $runnable = [];
        foreach ($cases as $index => $case) {
            if (is_array($case) && array_key_exists('expected', $case)) {
                $runnable[$index] = $case;
            }
        }

        if ($runnable === []) {
            return $this->outcome(false, false, 'validatenocases');
        }

        // Author validation is real runner work, so it goes through the same
        // admission gate as a student run. Without this a burst of publishing
        // during a busy period would push the site past its configured runner
        // ceiling, which is the exact thing the gate exists to hold. Denied
        // admission is reported as unchecked rather than as a failure.
        global $USER;
        $gate = new execution_gate((int) $USER->id);
        $lease = $gate->acquire();

        if ($lease === null) {
            return $this->outcome(false, false, 'validaterunnerdown');
        }

        try {
            return $this->run($runnable, $draft, $profileid);
        } finally {
            $gate->release($lease);
        }
    }

    /**
     * Execute the runnable cases and judge the reference against them.
     *
     * @param array $cases Runnable cases, keyed by their original index.
     * @param stdClass $draft The version being validated.
     * @param string $profileid The runtime to judge under.
     * @return array The report, shaped as validate() documents.
     */
    protected function run(array $cases, stdClass $draft, string $profileid): array {
        $entryfilename = trim((string) ($draft->entryfilename ?? '')) ?: 'Main.java';
        $files = [$entryfilename => (string) $draft->referencesolution];

        $results = [];
        $failed = [];

        foreach ($cases as $index => $case) {
            $name = trim((string) ($case['name'] ?? '')) !== ''
                ? (string) $case['name']
                : get_string('exercisecaseunnamed', 'local_saylorcode', $index + 1);

            $request = new execution_request(
                bin2hex(random_bytes(16)),
                $profileid,
                execution_request::MODE_VALIDATION,
                $files,
                (string) ($case['stdin'] ?? '')
            );

            try {
                $response = $this->provider->execute($request);
            } catch (\Throwable $e) {
                // A transport failure is an outage, not a verdict on the
                // exercise. The caller must be able to tell the two apart, so
                // this is reported as runner-down rather than as a failure.
                return $this->outcome(false, false, 'validaterunnerdown');
            }

            $export = $response->export_for_student();
            $state = $response->get_state();

            if ($state === execution_state::COMPILE_ERROR) {
                // Carry the compiler output so the author sees why, the same as
                // the activity form's Validate button does.
                $outcome = $this->outcome(false, true, 'validatecompileerror');
                $outcome['compileroutput'] = (string) ($export['compileroutput'] ?? '');
                return $outcome;
            }

            if (execution_state::is_platform_failure($state)) {
                return $this->outcome(false, false, 'validaterunnerdown');
            }

            $expected = (string) ($case['expected'] ?? '');
            $actual = (string) ($export['stdout'] ?? '');
            $passed = $state === execution_state::COMPLETED && $this->output_matches($actual, $expected);

            if (!$passed) {
                $failed[] = $name;
            }

            $results[] = [
                'name' => $name,
                'passed' => $passed,
                'ispublic' => !empty($case['ispublic']),
                // The author owns this content, so showing them the diff on a
                // failure is exactly what the button is for. This is never sent
                // to a student: the publish gate and the report both drop it.
                'expected' => $expected,
                'actual' => $actual,
                'state' => $state,
            ];
        }

        $valid = $failed === [];

        return [
            'valid' => $valid,
            'validatable' => true,
            'reason' => $valid ? 'validatepassed' : 'validatefailed',
            'results' => $results,
            'failed' => $failed,
            'compileroutput' => '',
        ];
    }

    /**
     * Whether produced output satisfies an expected value.
     *
     * The same normalisation the student path uses: trailing whitespace on a
     * line and trailing blank lines are ignored, because a missing final
     * newline is not a wrong answer to any exercise anyone means to set. Kept
     * in step with mod_saylorcode\local\output_comparator; the two must agree,
     * or an exercise could validate here and fail a student.
     *
     * @param string $actual What the program printed.
     * @param string $expected What the case expects.
     * @return bool
     */
    protected function output_matches(string $actual, string $expected): bool {
        $normalise = static function (string $text): string {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $lines = array_map('rtrim', explode("\n", $text));

            return rtrim(implode("\n", $lines), "\n");
        };

        return $normalise($actual) === $normalise($expected);
    }

    /**
     * Shape an outcome that never ran the cases.
     *
     * @param bool $valid Whether the exercise may be considered valid.
     * @param bool $validatable Whether there was anything to judge.
     * @param string $reason A language-string key.
     * @return array
     */
    protected function outcome(bool $valid, bool $validatable, string $reason): array {
        return [
            'valid' => $valid,
            'validatable' => $validatable,
            'reason' => $reason,
            'results' => [],
            'failed' => [],
            'compileroutput' => '',
        ];
    }
}
