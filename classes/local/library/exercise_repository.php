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

use local_saylorcode\local\stable_id;
use moodle_exception;
use stdClass;

/**
 * Exercises, and their published versions.
 *
 * An exercise exists independently of the activities that use it, which is what
 * lets the same exercise appear in a book, a lesson step and a quiz without
 * being written three times (specification section 11).
 *
 * A published version is immutable. That is the whole point of versioning: a
 * graded activity pinned to version three must still be the exercise the
 * student was graded against next year, whatever the author has done since.
 * Editing therefore happens on a draft row, version zero, and publishing copies
 * it to the next number rather than moving a pointer.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exercise_repository {
    /** @var int The version number the mutable working copy always occupies. */
    public const DRAFT_VERSION = 0;

    /** @var string A version still being written. */
    public const STATUS_DRAFT = 'draft';

    /** @var string A version that has been published and can no longer change. */
    public const STATUS_PUBLISHED = 'published';

    /**
     * The exercise with this stable id, if there is one.
     *
     * @param string $stableid The reference.
     * @return stdClass|null
     */
    public function find(string $stableid): ?stdClass {
        global $DB;

        $record = $DB->get_record('saylorcode_exercises', ['stableid' => $stableid]);

        return $record ?: null;
    }

    /**
     * Create an exercise, with an empty draft ready to edit.
     *
     * @param string $stableid The reference.
     * @param string $name What to call it.
     * @param array $fields Draft content and metadata.
     * @return stdClass The exercise.
     */
    public function create(string $stableid, string $name, array $fields = []): stdClass {
        global $DB, $USER;

        if (!stable_id::is_valid($stableid)) {
            throw new moodle_exception('stableidinvalid', 'local_saylorcode');
        }

        if ($this->find($stableid) !== null) {
            throw new moodle_exception('exerciseexists', 'local_saylorcode', '', $stableid);
        }

        $now = time();

        $exercise = (object) [
            'stableid' => $stableid,
            'name' => $name,
            'summary' => (string) ($fields['summary'] ?? ''),
            'profileid' => (string) ($fields['profileid'] ?? 'java17-console'),
            'currentversion' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $USER->id,
        ];

        $exercise->id = $DB->insert_record('saylorcode_exercises', $exercise);

        $this->write_draft($exercise, $fields);

        return $DB->get_record('saylorcode_exercises', ['id' => $exercise->id], '*', MUST_EXIST);
    }

    /**
     * The working draft, created empty if the exercise has none.
     *
     * @param stdClass $exercise The exercise.
     * @return stdClass
     */
    public function get_draft(stdClass $exercise): stdClass {
        global $DB, $USER;

        $draft = $DB->get_record('saylorcode_exercise_versions', [
            'exerciseid' => $exercise->id,
            'version' => self::DRAFT_VERSION,
        ]);

        if ($draft) {
            return $draft;
        }

        $now = time();

        $draft = (object) [
            'exerciseid' => $exercise->id,
            'version' => self::DRAFT_VERSION,
            'status' => self::STATUS_DRAFT,
            'entryfilename' => 'Main.java',
            'startercode' => '',
            'referencesolution' => '',
            'testcases' => '',
            'hints' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $USER->id,
        ];

        $draft->id = $DB->insert_record('saylorcode_exercise_versions', $draft);

        return $DB->get_record('saylorcode_exercise_versions', ['id' => $draft->id], '*', MUST_EXIST);
    }

    /**
     * Change the draft.
     *
     * @param stdClass $exercise The exercise.
     * @param array $fields The content to write.
     * @return stdClass The updated draft.
     */
    public function write_draft(stdClass $exercise, array $fields): stdClass {
        global $DB, $USER;

        $draft = $this->get_draft($exercise);

        foreach (['entryfilename', 'startercode', 'referencesolution', 'testcases', 'hints'] as $key) {
            if (array_key_exists($key, $fields)) {
                $draft->{$key} = (string) $fields[$key];
            }
        }

        $draft->timemodified = time();
        $draft->usermodified = (int) $USER->id;

        $DB->update_record('saylorcode_exercise_versions', $draft);

        return $draft;
    }

    /**
     * Publish the draft as the next version.
     *
     * The draft is copied rather than promoted, so it survives publication and
     * an author can keep working without disturbing what students are using.
     *
     * @param stdClass $exercise The exercise.
     * @param string $changenote Why this version exists.
     * @return stdClass The published version.
     */
    public function publish(stdClass $exercise, string $changenote = ''): stdClass {
        global $DB, $USER;

        $draft = $this->get_draft($exercise);

        // Publishing an exercise nobody can attempt would put a broken thing in
        // front of a student, and the library exists to stop exactly that.
        if (trim((string) $draft->startercode) === '' && trim((string) $draft->testcases) === '') {
            throw new moodle_exception('exerciseempty', 'local_saylorcode');
        }

        $now = time();
        $next = (int) $exercise->currentversion + 1;

        $version = clone $draft;
        unset($version->id);
        $version->version = $next;
        $version->status = self::STATUS_PUBLISHED;
        $version->changenote = \core_text::substr(trim($changenote), 0, 255);
        $version->timecreated = $now;
        $version->timemodified = $now;
        $version->usermodified = (int) $USER->id;

        $version->id = $DB->insert_record('saylorcode_exercise_versions', $version);

        $exercise->currentversion = $next;
        $exercise->timemodified = $now;
        $exercise->usermodified = (int) $USER->id;
        $DB->update_record('saylorcode_exercises', $exercise);

        return $DB->get_record('saylorcode_exercise_versions', ['id' => $version->id], '*', MUST_EXIST);
    }

    /**
     * A particular published version.
     *
     * @param stdClass $exercise The exercise.
     * @param int $version The version number.
     * @return stdClass|null
     */
    public function get_version(stdClass $exercise, int $version): ?stdClass {
        global $DB;

        $record = $DB->get_record('saylorcode_exercise_versions', [
            'exerciseid' => $exercise->id,
            'version' => $version,
            'status' => self::STATUS_PUBLISHED,
        ]);

        return $record ?: null;
    }

    /**
     * The most recently published version, if any.
     *
     * @param stdClass $exercise The exercise.
     * @return stdClass|null
     */
    public function get_latest(stdClass $exercise): ?stdClass {
        if ((int) $exercise->currentversion < 1) {
            return null;
        }

        return $this->get_version($exercise, (int) $exercise->currentversion);
    }

    /**
     * Every published version, newest first.
     *
     * @param stdClass $exercise The exercise.
     * @return stdClass[]
     */
    public function get_history(stdClass $exercise): array {
        global $DB;

        return $DB->get_records(
            'saylorcode_exercise_versions',
            ['exerciseid' => $exercise->id, 'status' => self::STATUS_PUBLISHED],
            'version DESC'
        );
    }
}
