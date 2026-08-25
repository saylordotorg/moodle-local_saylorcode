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

use local_saylorcode\local\library\solution_validator;
use local_saylorcode\scripted_provider;

/**
 * Tests for the library Validate web service.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\external\validate_exercise
 */
final class validate_exercise_test extends \advanced_testcase {
    /**
     * Load the scripted provider fixture.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/../fixtures/scripted_provider.php');
    }

    /**
     * Clear the injected provider between tests.
     */
    protected function tearDown(): void {
        solution_validator::set_test_provider(null);
        parent::tearDown();
    }

    /**
     * The two-case exercise the form would submit.
     *
     * @return string JSON test cases.
     */
    private function cases(): string {
        return json_encode([
            ['id' => 'T1', 'name' => 'greets', 'stdin' => '', 'expected' => 'Hello', 'ispublic' => true, 'weight' => 1],
            ['id' => 'T2', 'name' => 'adds', 'stdin' => '', 'expected' => '3', 'ispublic' => false, 'weight' => 1],
        ]);
    }

    /**
     * A publishing author gets a well-formed report with the per-case diff.
     */
    public function test_a_passing_run_returns_results_with_the_diff(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        solution_validator::set_test_provider(new scripted_provider(['Hello', '3']));

        $result = validate_exercise::execute('java17-console', 'Main.java', 'public class Main {}', $this->cases());
        $result = \core_external\external_api::clean_returnvalue(
            validate_exercise::execute_returns(),
            $result
        );

        $this->assertTrue($result['valid']);
        $this->assertSame('The reference solution passed all 2 tests.', $result['summary']);
        $this->assertCount(2, $result['results']);
        $this->assertSame('greets', $result['results'][0]['name']);
        $this->assertSame('Hello', $result['results'][0]['expected']);
        $this->assertSame('Hello', $result['results'][0]['actual']);
    }

    /**
     * A failing run names the count in the summary.
     */
    public function test_a_failing_run_summarises_the_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        solution_validator::set_test_provider(new scripted_provider(['Hello', '4']));

        $result = validate_exercise::execute('java17-console', 'Main.java', 'public class Main {}', $this->cases());
        $result = \core_external\external_api::clean_returnvalue(
            validate_exercise::execute_returns(),
            $result
        );

        $this->assertFalse($result['valid']);
        $this->assertSame('The reference solution did not pass 1 of 2 tests.', $result['summary']);
    }

    /**
     * A code-like case name survives the response cleaning intact.
     *
     * Names routinely contain angle brackets ("Returns List<String>"), which
     * the form stores raw. PARAM_TEXT on the return would strip them; the
     * client renders the name with textContent, so it is returned raw.
     */
    public function test_a_code_like_case_name_is_not_stripped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        solution_validator::set_test_provider(new scripted_provider(['[1, 2]']));

        $cases = json_encode([
            ['id' => 'T1', 'name' => 'Returns List<String>', 'expected' => '[1, 2]', 'ispublic' => true, 'weight' => 1],
        ]);

        $result = validate_exercise::execute('java17-console', 'Main.java', 'public class Main {}', $cases);
        $result = \core_external\external_api::clean_returnvalue(
            validate_exercise::execute_returns(),
            $result
        );

        $this->assertSame('Returns List<String>', $result['results'][0]['name']);
    }

    /**
     * An author without the publish capability may not run the check.
     */
    public function test_it_requires_the_publish_capability(): void {
        $this->resetAfterTest();

        // An ordinary user, with no role granting the library capabilities.
        $this->setUser($this->getDataGenerator()->create_user());

        solution_validator::set_test_provider(new scripted_provider(['Hello', '3']));

        $this->expectException(\required_capability_exception::class);
        validate_exercise::execute('java17-console', 'Main.java', 'public class Main {}', $this->cases());
    }
}
