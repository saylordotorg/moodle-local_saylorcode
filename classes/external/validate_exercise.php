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

namespace local_saylorcode\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_saylorcode\local\library\solution_validator;
use stdClass;

/**
 * Web service behind the Validate button on the library authoring form.
 *
 * Runs the author's reference solution against the test rows as they stand in
 * the form, before the draft is saved, so an author can see a wrong expected
 * value while they can still fix it rather than discovering it once a student
 * has been graded against it. Scoped to the system context and the publish
 * capability, because the library is site wide and running arbitrary source on
 * the runner is an authoring act rather than something enrolment grants.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validate_exercise extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'profileid' => new external_value(PARAM_ALPHANUMEXT, 'Runtime profile id'),
            'entryfilename' => new external_value(PARAM_FILE, 'File the solution lives in'),
            'referencesolution' => new external_value(PARAM_RAW, 'Reference solution source'),
            'testcases' => new external_value(PARAM_RAW, 'JSON encoded test cases'),
        ]);
    }

    /**
     * Run the reference solution against the submitted test cases.
     *
     * @param string $profileid Runtime profile id.
     * @param string $entryfilename Entry filename.
     * @param string $referencesolution Reference solution source.
     * @param string $testcases JSON encoded test cases.
     * @return array
     */
    public static function execute(
        string $profileid,
        string $entryfilename,
        string $referencesolution,
        string $testcases
    ): array {
        [
            'profileid' => $profileid,
            'entryfilename' => $entryfilename,
            'referencesolution' => $referencesolution,
            'testcases' => $testcases,
        ] = self::validate_parameters(self::execute_parameters(), [
            'profileid' => $profileid,
            'entryfilename' => $entryfilename,
            'referencesolution' => $referencesolution,
            'testcases' => $testcases,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/saylorcode:publishexercise', $context);

        // The validator judges a draft-shaped object; the form's current
        // contents are exactly that, unsaved.
        $draft = (object) [
            'entryfilename' => $entryfilename !== '' ? $entryfilename : 'Main.java',
            'referencesolution' => $referencesolution,
            'testcases' => $testcases,
        ];

        $report = (new solution_validator())->validate($draft, $profileid);

        return [
            'valid' => $report['valid'],
            'summary' => self::summarise($report),
            'compileroutput' => (string) ($report['compileroutput'] ?? ''),
            'results' => array_map(static function (array $result): array {
                return [
                    'name' => $result['name'],
                    'passed' => $result['passed'],
                    'ispublic' => $result['ispublic'],
                    'expected' => $result['expected'],
                    'actual' => $result['actual'],
                    'state' => $result['state'],
                ];
            }, $report['results']),
        ];
    }

    /**
     * A sentence the author can act on.
     *
     * The validator returns a reason key; this turns it into something to read.
     * A pass and a plain failure carry counts, because "failed 1 of 4" is more
     * use than "some tests failed"; the not-validatable reasons are complete
     * sentences on their own.
     *
     * @param array $report The validator's report.
     * @return string
     */
    protected static function summarise(array $report): string {
        $total = count($report['results']);

        if ($report['valid']) {
            return get_string('validatesummarypassed', 'local_saylorcode', $total);
        }

        if ($report['reason'] === 'validatefailed') {
            return get_string('validatesummaryfailed', 'local_saylorcode', (object) [
                'failed' => count($report['failed']),
                'total' => $total,
            ]);
        }

        return match ($report['reason']) {
            'validatenosolution' => get_string('validatesummarynosolution', 'local_saylorcode'),
            'validatenocases' => get_string('validatesummarynocases', 'local_saylorcode'),
            'validatecompileerror' => get_string('validatesummarycompileerror', 'local_saylorcode'),
            default => get_string('validatesummaryrunnerdown', 'local_saylorcode'),
        };
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'valid' => new external_value(PARAM_BOOL, 'Whether the reference satisfied every case'),
            'summary' => new external_value(PARAM_TEXT, 'A sentence the author can act on'),
            'compileroutput' => new external_value(PARAM_RAW, 'Compiler output, when the solution did not compile'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    // Raw, not PARAM_TEXT: a case name legitimately holds
                    // code-like text such as "Returns List<String>", which the
                    // form stores raw and PARAM_TEXT would strip during response
                    // cleaning. The client renders it with textContent, so this
                    // never reaches the page as markup.
                    'name' => new external_value(PARAM_RAW, 'Case name'),
                    'passed' => new external_value(PARAM_BOOL, 'Whether the reference satisfied it'),
                    'ispublic' => new external_value(PARAM_BOOL, 'Whether students see this case'),
                    'expected' => new external_value(PARAM_RAW, 'Expected output'),
                    'actual' => new external_value(PARAM_RAW, 'Output the reference produced'),
                    'state' => new external_value(PARAM_ALPHAEXT, 'Execution state'),
                ]),
                'Per case results'
            ),
        ]);
    }
}
