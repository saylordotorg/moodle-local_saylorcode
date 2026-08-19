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

use stdClass;

/**
 * The exercise content something resolved to, and where it came from.
 *
 * Provenance travels with the content because the answer alone is not enough:
 * an activity showing its own starter code because the library has no published
 * version looks identical to one showing a published version, and an author
 * needs to be able to tell those apart.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolved_exercise {
    /** @var stdClass The version record, real or synthesised from the holder. */
    protected stdClass $version;

    /** @var stdClass|null The library exercise, absent when the holder supplied its own content. */
    protected ?stdClass $exercise;

    /** @var string Where the content came from. */
    protected string $source;

    /**
     * Build a resolution.
     *
     * @param stdClass $version The version record.
     * @param stdClass|null $exercise The library exercise, if there was one.
     * @param string $source latest, pinned, or why the library was not used.
     */
    public function __construct(stdClass $version, ?stdClass $exercise, string $source) {
        $this->version = $version;
        $this->exercise = $exercise;
        $this->source = $source;
    }

    /**
     * Whether this came from the library at all.
     *
     * @return bool
     */
    public function is_from_library(): bool {
        return $this->exercise !== null;
    }

    /**
     * Where the content came from.
     *
     * One of latest or pinned when it came from the library, otherwise the
     * reason it did not: noreference, notinlibrary, nopublishedversion or
     * pinnedversionmissing.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }

    /**
     * Whether the holder asked for a version that does not exist.
     *
     * Worth surfacing to an author: it means an activity is showing different
     * content from the one it was set up with.
     *
     * @return bool
     */
    public function is_broken_pin(): bool {
        return $this->source === 'pinnedversionmissing';
    }

    /**
     * The version number, or zero for content held on the activity itself.
     *
     * @return int
     */
    public function get_version_number(): int {
        return (int) ($this->version->version ?? 0);
    }

    /**
     * The file the student edits.
     *
     * @return string
     */
    public function get_entry_filename(): string {
        $name = trim((string) ($this->version->entryfilename ?? ''));

        return $name !== '' ? $name : 'Main.java';
    }

    /**
     * The code the student starts from.
     *
     * @return string
     */
    public function get_starter_code(): string {
        return (string) ($this->version->startercode ?? '');
    }

    /**
     * The author's own solution.
     *
     * @return string
     */
    public function get_reference_solution(): string {
        return (string) ($this->version->referencesolution ?? '');
    }

    /**
     * The test cases, decoded.
     *
     * @return array
     */
    public function get_test_cases(): array {
        $decoded = json_decode((string) ($this->version->testcases ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The hints, decoded.
     *
     * @return array
     */
    public function get_hints(): array {
        $decoded = json_decode((string) ($this->version->hints ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}
