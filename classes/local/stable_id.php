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
 * Parsing and validation for exercise stable identifiers.
 *
 * A stable id such as CS101-U05-E03 is the permanent, human readable handle for
 * an exercise. Activities, embeds and quiz questions all reference this rather
 * than a database id, which is what allows one exercise definition to be reused
 * across contexts without copying it (specification sections 5.2 and 6).
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stable_id {

    /** @var string Pattern for a well formed stable id. */
    public const PATTERN = '/^[A-Z][A-Z0-9]{1,9}-U\d{2}-E\d{2}$/';

    /** @var string Course component, for example CS101. */
    private string $course;

    /** @var int Unit number. */
    private int $unit;

    /** @var int Exercise number within the unit. */
    private int $exercise;

    /**
     * Build a stable id from its parts.
     *
     * @param string $course Course component.
     * @param int $unit Unit number.
     * @param int $exercise Exercise number.
     */
    public function __construct(string $course, int $unit, int $exercise) {
        $this->course = strtoupper($course);
        $this->unit = $unit;
        $this->exercise = $exercise;
    }

    /**
     * Parse a stable id string.
     *
     * @param string $value Candidate stable id.
     * @return self
     * @throws coding_exception If the value is not a well formed stable id.
     */
    public static function parse(string $value): self {
        $value = strtoupper(trim($value));
        if (!self::is_valid($value)) {
            throw new coding_exception('Malformed Saylor Code Studio stable id: ' . $value);
        }

        $parts = explode('-', $value);

        return new self($parts[0], (int) substr($parts[1], 1), (int) substr($parts[2], 1));
    }

    /**
     * Whether a string is a well formed stable id.
     *
     * @param string $value Candidate stable id.
     * @return bool
     */
    public static function is_valid(string $value): bool {
        return (bool) preg_match(self::PATTERN, strtoupper(trim($value)));
    }

    /**
     * Course component.
     *
     * @return string
     */
    public function get_course(): string {
        return $this->course;
    }

    /**
     * Unit number.
     *
     * @return int
     */
    public function get_unit(): int {
        return $this->unit;
    }

    /**
     * Exercise number.
     *
     * @return int
     */
    public function get_exercise(): int {
        return $this->exercise;
    }

    /**
     * Canonical string form.
     *
     * @return string
     */
    public function __toString(): string {
        return sprintf('%s-U%02d-E%02d', $this->course, $this->unit, $this->exercise);
    }
}
