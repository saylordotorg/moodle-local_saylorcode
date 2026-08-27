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

use context_system;
use moodle_exception;
use stdClass;

/**
 * Where an exercise stands in the review workflow, and what may move it.
 *
 * Draft -> In review -> Ready -> Retired -> Archived (specification section
 * 10.8). Status is not the same thing as publishing. Publishing freezes a
 * version of the content; status says whether the exercise may be put to new
 * use. An exercise can have three published versions and still be in review,
 * and a retired exercise keeps every version it ever published, because
 * retiring must not invalidate historical attempts (section 10.9).
 *
 * The transitions are deliberately narrow. Going back is possible only from In
 * review to Draft, which is the reviewer sending work back; there is no path
 * out of Archived, because an archive that can be reopened is not an archive.
 * Anything wider should be a decision someone makes explicitly rather than a
 * side effect of a button.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exercise_workflow {
    /** @var string Being written; not for use. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Submitted, awaiting a reviewer. */
    public const STATUS_INREVIEW = 'inreview';

    /** @var string Approved for use. */
    public const STATUS_READY = 'ready';

    /** @var string No longer offered for new use; existing uses stand. */
    public const STATUS_RETIRED = 'retired';

    /** @var string Closed. */
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Every status, in workflow order.
     *
     * @return string[]
     */
    public static function all_statuses(): array {
        return [
            self::STATUS_DRAFT,
            self::STATUS_INREVIEW,
            self::STATUS_READY,
            self::STATUS_RETIRED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * The statuses each status may move to.
     *
     * @return array<string, string[]>
     */
    protected static function transitions(): array {
        return [
            self::STATUS_DRAFT => [self::STATUS_INREVIEW],
            // Sending work back is the reviewer's other answer, and the only
            // backwards move in the workflow.
            self::STATUS_INREVIEW => [self::STATUS_READY, self::STATUS_DRAFT],
            self::STATUS_READY => [self::STATUS_RETIRED],
            self::STATUS_RETIRED => [self::STATUS_ARCHIVED],
            self::STATUS_ARCHIVED => [],
        ];
    }

    /**
     * The capability a transition requires.
     *
     * Approving is the consequential one: it is what lets an exercise reach
     * students, so the specification reserves it for the publish capability.
     * Submitting for review is ordinary authoring. Retiring and archiving
     * withdraw content, which is a publishing decision too.
     *
     * @param string $to The status being moved to.
     * @return string The capability required.
     */
    public static function required_capability(string $to): string {
        return $to === self::STATUS_INREVIEW
            ? 'local/saylorcode:managecontent'
            : 'local/saylorcode:publishexercise';
    }

    /**
     * Whether one status may move to another at all.
     *
     * @param string $from The current status.
     * @param string $to The wanted status.
     * @return bool
     */
    public static function can_transition(string $from, string $to): bool {
        return in_array($to, self::transitions()[$from] ?? [], true);
    }

    /**
     * The statuses an exercise may move to next.
     *
     * @param stdClass $exercise The exercise.
     * @return string[]
     */
    public static function next_statuses(stdClass $exercise): array {
        return self::transitions()[self::status_of($exercise)] ?? [];
    }

    /**
     * An exercise's status, defaulting for rows written before it existed.
     *
     * @param stdClass $exercise The exercise.
     * @return string
     */
    public static function status_of(stdClass $exercise): string {
        $status = (string) ($exercise->status ?? '');

        return in_array($status, self::all_statuses(), true) ? $status : self::STATUS_DRAFT;
    }

    /**
     * Whether an exercise may be put to new use.
     *
     * Retired and archived exercises keep working wherever they are already
     * referenced -- pulling content out from under a running course would be
     * worse than leaving it -- but nothing new should point at them.
     *
     * @param stdClass $exercise The exercise.
     * @return bool
     */
    public static function is_available_for_new_use(stdClass $exercise): bool {
        return self::status_of($exercise) === self::STATUS_READY;
    }

    /**
     * Move an exercise to a new status.
     *
     * @param stdClass $exercise The exercise.
     * @param string $to The wanted status.
     * @param publication_check|null $check The readiness check, defaulting to the real one.
     * @return stdClass The updated exercise.
     * @throws moodle_exception If the move is not allowed or the exercise is not ready for it.
     */
    public static function transition(
        stdClass $exercise,
        string $to,
        ?publication_check $check = null
    ): stdClass {
        global $DB, $USER;

        $from = self::status_of($exercise);

        if (!self::can_transition($from, $to)) {
            throw new moodle_exception('statustransitioninvalid', 'local_saylorcode', '', (object) [
                'from' => get_string('status' . $from, 'local_saylorcode'),
                'to' => get_string('status' . $to, 'local_saylorcode'),
            ]);
        }

        require_capability(self::required_capability($to), context_system::instance());

        // Approving is the gate the specification puts the publication checks
        // behind, because Ready is the point where an exercise becomes
        // something a student can be sent to.
        if ($to === self::STATUS_READY) {
            $report = ($check ?? new publication_check())->run($exercise);

            if (!$report['passed']) {
                throw new moodle_exception('statusnotready', 'local_saylorcode', '', (object) [
                    'failures' => implode('; ', $report['failures']),
                ]);
            }
        }

        $exercise->status = $to;
        $exercise->timemodified = time();
        $exercise->usermodified = (int) $USER->id;
        $DB->update_record('local_saylorcode_exercises', $exercise);

        return $exercise;
    }
}
