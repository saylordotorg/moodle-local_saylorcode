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

use local_saylorcode\local\library\exercise_repository;
use local_saylorcode\local\library\exercise_workflow;
use local_saylorcode\local\library\publication_check;
use local_saylorcode\local\library\solution_validator;

/**
 * Tests for the exercise review workflow and its publication checks.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\library\exercise_workflow
 * @covers     \local_saylorcode\local\library\publication_check
 */
final class exercise_workflow_test extends \advanced_testcase {
    /**
     * Load the scripted provider fixture.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/scripted_provider.php');
    }

    /**
     * Clear any injected provider.
     */
    protected function tearDown(): void {
        solution_validator::set_test_provider(null);
        parent::tearDown();
    }

    /**
     * A publishable exercise, with a reference solution that passes.
     *
     * @param string $stableid The reference.
     * @return \stdClass The exercise, with one published version.
     */
    private function publishable(string $stableid = 'CS101-U01-E01'): \stdClass {
        $repo = new exercise_repository();
        $exercise = $repo->create($stableid, 'Greeting', [
            'entryfilename' => 'Main.java',
            'startercode' => 'public class Main {}',
            'referencesolution' => 'public class Main { }',
            'testcases' => json_encode([
                [
                    'id' => 'T1',
                    'name' => 'greets',
                    'expected' => 'Hello',
                    'ispublic' => true,
                    'weight' => 1,
                    'feedback' => 'Print exactly Hello.',
                ],
            ]),
        ]);
        $repo->publish($exercise, 'First');

        return $repo->find($stableid);
    }

    /**
     * The workflow only allows the transitions the specification names.
     */
    public function test_only_specified_transitions_are_allowed(): void {
        $allowed = [
            ['draft', 'inreview'],
            ['inreview', 'ready'],
            ['inreview', 'draft'],
            ['ready', 'retired'],
            ['retired', 'archived'],
        ];

        foreach ($allowed as [$from, $to]) {
            $this->assertTrue(exercise_workflow::can_transition($from, $to), "$from -> $to");
        }

        // Skipping review, resurrecting an archive, or going straight back from
        // Ready to Draft are all refused.
        $refused = [
            ['draft', 'ready'],
            ['draft', 'archived'],
            ['ready', 'draft'],
            ['ready', 'inreview'],
            ['archived', 'ready'],
            ['archived', 'draft'],
            ['retired', 'ready'],
        ];

        foreach ($refused as [$from, $to]) {
            $this->assertFalse(exercise_workflow::can_transition($from, $to), "$from -> $to");
        }
    }

    /**
     * A new exercise starts as a draft, and only Ready is available for use.
     */
    public function test_a_new_exercise_starts_as_a_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $exercise = $this->publishable();

        $this->assertSame(exercise_workflow::STATUS_DRAFT, exercise_workflow::status_of($exercise));
        $this->assertFalse(exercise_workflow::is_available_for_new_use($exercise));
    }

    /**
     * The happy path: draft, review, ready, and then available for use.
     */
    public function test_an_exercise_walks_the_workflow(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        solution_validator::set_test_provider(new scripted_provider(['Hello']));

        $exercise = $this->publishable();

        $exercise = exercise_workflow::transition($exercise, exercise_workflow::STATUS_INREVIEW);
        $this->assertSame(exercise_workflow::STATUS_INREVIEW, $exercise->status);

        $exercise = exercise_workflow::transition($exercise, exercise_workflow::STATUS_READY);
        $this->assertSame(exercise_workflow::STATUS_READY, $exercise->status);
        $this->assertTrue(exercise_workflow::is_available_for_new_use($exercise));

        // Retiring stops new use but does not erase the exercise or its versions.
        $exercise = exercise_workflow::transition($exercise, exercise_workflow::STATUS_RETIRED);
        $this->assertFalse(exercise_workflow::is_available_for_new_use($exercise));
        $this->assertCount(1, (new exercise_repository())->get_history($exercise));
    }

    /**
     * The status is persisted, not just returned.
     */
    public function test_a_transition_is_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $exercise = $this->publishable();
        exercise_workflow::transition($exercise, exercise_workflow::STATUS_INREVIEW);

        $reloaded = $DB->get_record('local_saylorcode_exercises', ['id' => $exercise->id]);
        $this->assertSame(exercise_workflow::STATUS_INREVIEW, $reloaded->status);
    }

    /**
     * An invalid move is refused rather than silently applied.
     */
    public function test_an_invalid_transition_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $exercise = $this->publishable();

        $this->expectException(\moodle_exception::class);
        exercise_workflow::transition($exercise, exercise_workflow::STATUS_READY);
    }

    /**
     * Approving runs the publication checks, and a failing solution blocks it.
     *
     * The check that matters: Ready is a promise the exercise works, so it must
     * not be grantable to one whose own reference solution fails its tests.
     */
    public function test_approval_is_blocked_by_a_failing_reference_solution(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // The runner returns the wrong output, so the reference fails its case.
        solution_validator::set_test_provider(new scripted_provider(['Goodbye']));

        $exercise = $this->publishable();
        $exercise = exercise_workflow::transition($exercise, exercise_workflow::STATUS_INREVIEW);

        try {
            exercise_workflow::transition($exercise, exercise_workflow::STATUS_READY);
            $this->fail('Approval should have been refused.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('not ready', $e->getMessage());
        }

        // And it stayed in review rather than half moving.
        global $DB;
        $reloaded = $DB->get_record('local_saylorcode_exercises', ['id' => $exercise->id]);
        $this->assertSame(exercise_workflow::STATUS_INREVIEW, $reloaded->status);
    }

    /**
     * An exercise with no published version cannot be approved.
     */
    public function test_approval_needs_a_published_version(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $repo = new exercise_repository();
        $exercise = $repo->create('CS101-U09-E09', 'Unpublished', [
            'startercode' => 'public class Main {}',
        ]);

        $report = (new publication_check())->run($exercise);

        $this->assertFalse($report['passed']);
        $this->assertStringContainsString(
            'no published version',
            implode(' ', $report['failures'])
        );
    }

    /**
     * The checks catch an exercise with no tests and no feedback.
     */
    public function test_the_checks_catch_missing_tests_and_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $repo = new exercise_repository();

        // No tests at all.
        $notests = $repo->create('CS101-U08-E01', 'No tests', [
            'startercode' => 'public class Main {}',
            'referencesolution' => 'public class Main {}',
        ]);
        $repo->publish($notests, 'v1');
        $report = (new publication_check())->run($repo->find('CS101-U08-E01'));
        $this->assertFalse($report['passed']);
        $this->assertStringContainsString('no test cases', implode(' ', $report['failures']));

        // Tests, but not one of them says anything to a student who fails.
        solution_validator::set_test_provider(new scripted_provider(['Hello']));
        $nofeedback = $repo->create('CS101-U08-E02', 'No feedback', [
            'startercode' => 'public class Main {}',
            'referencesolution' => 'public class Main {}',
            'testcases' => json_encode([
                ['id' => 'T1', 'name' => 'greets', 'expected' => 'Hello', 'ispublic' => true, 'weight' => 1],
            ]),
        ]);
        $repo->publish($nofeedback, 'v1');
        $report = (new publication_check())->run($repo->find('CS101-U08-E02'));
        $this->assertFalse($report['passed']);
        $this->assertStringContainsString('feedback', implode(' ', $report['failures']));
    }

    /**
     * A complete exercise passes every check that can be made.
     */
    public function test_a_complete_exercise_passes_the_checks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        solution_validator::set_test_provider(new scripted_provider(['Hello']));

        $report = (new publication_check())->run($this->publishable());

        $this->assertTrue($report['passed'], implode('; ', $report['failures']));
        $this->assertGreaterThan(0, $report['ran']);
    }

    /**
     * Submitting for review needs only the authoring capability; approving
     * needs the publish one.
     */
    public function test_approving_needs_the_publish_capability(): void {
        $this->assertSame(
            'local/saylorcode:managecontent',
            exercise_workflow::required_capability(exercise_workflow::STATUS_INREVIEW)
        );

        $publishing = [
            exercise_workflow::STATUS_READY,
            exercise_workflow::STATUS_RETIRED,
            exercise_workflow::STATUS_ARCHIVED,
        ];

        foreach ($publishing as $status) {
            $this->assertSame(
                'local/saylorcode:publishexercise',
                exercise_workflow::required_capability($status)
            );
        }
    }
}
