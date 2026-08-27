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
 * Deciding which exercise content something should use.
 *
 * Activities and steps name an exercise by stable id and say whether they want
 * the latest published version or a pinned one. This turns that into content.
 *
 * The fallback matters more than the lookup. Every exercise in CS101 today
 * stores its own starter code and tests on the activity, written before the
 * library existed. Those must keep working untouched, so an activity whose
 * stable id is not in the library resolves to its own fields. Adopting the
 * library is then a per exercise decision an author makes when they are ready,
 * not a migration that has to happen all at once.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exercise_resolver {
    /** @var string Follow the exercise as its author publishes new versions. */
    public const POLICY_LATEST = 'latest';

    /** @var string Stay on one version whatever the author does next. */
    public const POLICY_PINNED = 'pinned';

    /** @var string A pin that names no version that exists. */
    public const SOURCE_BROKEN_PIN = 'pinnedversionmissing';

    /** @var exercise_repository The library. */
    protected exercise_repository $repository;

    /**
     * Build a resolver.
     *
     * @param exercise_repository|null $repository The library, defaulting to the real one.
     */
    public function __construct(?exercise_repository $repository = null) {
        $this->repository = $repository ?? new exercise_repository();
    }

    /**
     * The content a holder should use.
     *
     * @param stdClass $holder An activity or step: anything carrying stableid,
     *                         versionpolicy, pinnedversion and its own content fields.
     * @return resolved_exercise
     */
    public function resolve(stdClass $holder): resolved_exercise {
        $stableid = trim((string) ($holder->stableid ?? ''));

        if ($stableid === '') {
            return $this->from_holder($holder, 'noreference');
        }

        $exercise = $this->repository->find($stableid);

        if ($exercise === null) {
            // Not in the library. The holder's own fields are the exercise,
            // which is how everything authored before the library works.
            return $this->from_holder($holder, 'notinlibrary');
        }

        $policy = (string) ($holder->versionpolicy ?? self::POLICY_LATEST);
        $pinned = (int) ($holder->pinnedversion ?? 0);

        if ($policy === self::POLICY_PINNED) {
            // Every pinned holder is answered here, including one saved with
            // the policy set and no version chosen yet. Letting that fall
            // through to the latest would be the silent content switch this
            // branch exists to prevent, and it is the likeliest way to reach
            // that state by accident.
            $version = $pinned > 0 ? $this->repository->get_version($exercise, $pinned) : null;

            if ($version === null) {
                // Pinned to something that is not there. Falling back to the
                // latest would quietly regrade students against different
                // content, so this reports the problem instead and leaves the
                // caller to decide how loudly to say so.
                return $this->from_holder($holder, 'pinnedversionmissing');
            }

            return new resolved_exercise($version, $exercise, 'pinned');
        }

        // The latest *approved* version, not merely the newest published one:
        // a revision of a Ready exercise waits for a reviewer rather than
        // reaching students the moment its author publishes it.
        $version = $this->repository->get_for_use($exercise);

        if ($version === null) {
            // In the library but never published. A draft is not something a
            // student should meet, so the holder's own content stands.
            return $this->from_holder($holder, 'nopublishedversion');
        }

        return new resolved_exercise($version, $exercise, 'latest');
    }

    /**
     * Treat the holder's own fields as the exercise.
     *
     * @param stdClass $holder The activity or step.
     * @param string $reason Why the library was not used.
     * @return resolved_exercise
     */
    protected function from_holder(stdClass $holder, string $reason): resolved_exercise {
        $version = (object) [
            'version' => 0,
            'entryfilename' => (string) ($holder->entryfilename ?? 'Main.java'),
            'startercode' => (string) ($holder->startercode ?? ''),
            'referencesolution' => (string) ($holder->referencesolution ?? ''),
            'testcases' => (string) ($holder->testcases ?? ''),
            'hints' => (string) ($holder->hints ?? ''),
        ];

        return new resolved_exercise($version, null, $reason);
    }
}
