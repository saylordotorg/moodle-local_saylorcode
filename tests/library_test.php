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
use local_saylorcode\local\library\exercise_resolver;

/**
 * Tests for the exercise library.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\library\exercise_repository
 * @covers     \local_saylorcode\local\library\exercise_resolver
 * @covers     \local_saylorcode\local\library\resolved_exercise
 */
final class library_test extends \advanced_testcase {
    /** @var exercise_repository The library. */
    private $repository;

    /**
     * A library to work against.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->repository = new exercise_repository();
    }

    /**
     * Draft content for an exercise that can be published.
     *
     * @param string $starter The starter code.
     * @return array
     */
    private function content(string $starter = 'public class Main {}'): array {
        return [
            'startercode' => $starter,
            'referencesolution' => 'public class Main { }',
            'testcases' => json_encode([['name' => 'One', 'expected' => 'x', 'ispublic' => 1, 'weight' => 1]]),
        ];
    }

    /**
     * A holder, of the shape an activity or step has.
     *
     * @param array $fields Field overrides.
     * @return \stdClass
     */
    private function holder(array $fields = []): \stdClass {
        return (object) ($fields + [
            'stableid' => 'CS101-U01-E01',
            'versionpolicy' => exercise_resolver::POLICY_LATEST,
            'pinnedversion' => 0,
            'entryfilename' => 'Main.java',
            'startercode' => 'the activity own starter',
            'referencesolution' => '',
            'testcases' => '',
            'hints' => '',
        ]);
    }

    /**
     * A new exercise starts with a draft and nothing published.
     */
    public function test_a_new_exercise_has_a_draft_and_no_versions(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content());

        $this->assertSame(0, (int) $exercise->currentversion);
        $this->assertNull($this->repository->get_latest($exercise));
        $this->assertSame('public class Main {}', $this->repository->get_draft($exercise)->startercode);
    }

    /**
     * Stable ids are unique.
     */
    public function test_two_exercises_cannot_share_a_reference(): void {
        $this->repository->create('CS101-U01-E01', 'First', $this->content());

        $this->expectException(\moodle_exception::class);
        $this->repository->create('CS101-U01-E01', 'Second', $this->content());
    }

    /**
     * A malformed reference is refused.
     */
    public function test_a_malformed_reference_is_refused(): void {
        $this->expectException(\moodle_exception::class);
        $this->repository->create('NOTANID', 'Nope', $this->content());
    }

    /**
     * Publishing numbers versions from one.
     */
    public function test_publishing_numbers_from_one(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content());

        $first = $this->repository->publish($exercise, 'First cut');
        $this->assertSame(1, (int) $first->version);

        $exercise = $this->repository->find('CS101-U01-E01');
        $this->repository->write_draft($exercise, ['startercode' => 'changed']);
        $second = $this->repository->publish($exercise, 'Reworded');

        $this->assertSame(2, (int) $second->version);
    }

    /**
     * A published version does not change when the draft does.
     *
     * This is the point of versioning. A graded activity pinned to version one
     * must still be the exercise the student was graded against.
     */
    public function test_a_published_version_is_immutable(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('original'));
        $this->repository->publish($exercise, 'First');

        $exercise = $this->repository->find('CS101-U01-E01');
        $this->repository->write_draft($exercise, ['startercode' => 'rewritten']);

        $published = $this->repository->get_version($exercise, 1);

        $this->assertSame('original', $published->startercode);
        $this->assertSame('rewritten', $this->repository->get_draft($exercise)->startercode);
    }

    /**
     * An empty exercise cannot be published.
     */
    public function test_an_empty_exercise_cannot_be_published(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Empty', []);

        $this->expectException(\moodle_exception::class);
        $this->repository->publish($exercise);
    }

    /**
     * An activity whose reference is not in the library uses its own content.
     *
     * Every exercise in CS101 works this way today, so this is the case that
     * must not break.
     */
    public function test_content_outside_the_library_still_resolves(): void {
        $resolved = (new exercise_resolver())->resolve($this->holder());

        $this->assertFalse($resolved->is_from_library());
        $this->assertSame('notinlibrary', $resolved->get_source());
        $this->assertSame('the activity own starter', $resolved->get_starter_code());
    }

    /**
     * A library exercise with no published version does not override the holder.
     *
     * A draft is not something a student should meet.
     */
    public function test_an_unpublished_exercise_does_not_override_the_activity(): void {
        $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('library starter'));

        $resolved = (new exercise_resolver())->resolve($this->holder());

        $this->assertSame('nopublishedversion', $resolved->get_source());
        $this->assertSame('the activity own starter', $resolved->get_starter_code());
    }

    /**
     * Once published, the library supplies the content.
     */
    public function test_a_published_exercise_supplies_the_content(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('library starter'));
        $this->repository->publish($exercise, 'First');

        $resolved = (new exercise_resolver())->resolve($this->holder());

        $this->assertTrue($resolved->is_from_library());
        $this->assertSame('latest', $resolved->get_source());
        $this->assertSame('library starter', $resolved->get_starter_code());
        $this->assertSame(1, $resolved->get_version_number());
    }

    /**
     * Following the latest means following it.
     */
    public function test_latest_follows_new_versions(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('one'));
        $this->repository->publish($exercise, 'First');

        $exercise = $this->repository->find('CS101-U01-E01');
        $this->repository->write_draft($exercise, ['startercode' => 'two']);
        $this->repository->publish($exercise, 'Second');

        $resolved = (new exercise_resolver())->resolve($this->holder());

        $this->assertSame('two', $resolved->get_starter_code());
        $this->assertSame(2, $resolved->get_version_number());
    }

    /**
     * A pinned activity stays where it was pinned.
     */
    public function test_a_pin_holds_against_later_versions(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('one'));
        $this->repository->publish($exercise, 'First');

        $exercise = $this->repository->find('CS101-U01-E01');
        $this->repository->write_draft($exercise, ['startercode' => 'two']);
        $this->repository->publish($exercise, 'Second');

        $resolved = (new exercise_resolver())->resolve($this->holder([
            'versionpolicy' => exercise_resolver::POLICY_PINNED,
            'pinnedversion' => 1,
        ]));

        $this->assertSame('one', $resolved->get_starter_code());
        $this->assertSame(1, $resolved->get_version_number());
    }

    /**
     * A pin to a version that does not exist is reported, not silently ignored.
     *
     * Falling back to the latest would regrade students against content their
     * activity was never set up with, and nobody would be told.
     */
    public function test_a_broken_pin_is_reported(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('one'));
        $this->repository->publish($exercise, 'First');

        $resolved = (new exercise_resolver())->resolve($this->holder([
            'versionpolicy' => exercise_resolver::POLICY_PINNED,
            'pinnedversion' => 7,
        ]));

        $this->assertTrue($resolved->is_broken_pin());
        $this->assertFalse($resolved->is_from_library());
        $this->assertSame('the activity own starter', $resolved->get_starter_code());
    }

    /**
     * A playground has no reference and resolves to its own content.
     */
    public function test_an_activity_with_no_reference_uses_its_own_content(): void {
        $resolved = (new exercise_resolver())->resolve($this->holder(['stableid' => '']));

        $this->assertSame('noreference', $resolved->get_source());
        $this->assertSame('the activity own starter', $resolved->get_starter_code());
    }

    /**
     * The history lists published versions, newest first.
     */
    public function test_history_lists_published_versions_newest_first(): void {
        $exercise = $this->repository->create('CS101-U01-E01', 'Doubling', $this->content('one'));
        $this->repository->publish($exercise, 'First');

        $exercise = $this->repository->find('CS101-U01-E01');
        $this->repository->write_draft($exercise, ['startercode' => 'two']);
        $this->repository->publish($exercise, 'Second');

        $history = array_values($this->repository->get_history($exercise));

        $this->assertCount(2, $history);
        $this->assertSame(2, (int) $history[0]->version);
        $this->assertSame('Second', $history[0]->changenote);

        // The draft is not history, and must not appear as a version students
        // could be pointed at.
        foreach ($history as $version) {
            $this->assertNotSame(0, (int) $version->version);
        }
    }
}
