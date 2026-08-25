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

/**
 * Stand alone view of one exercise, referenced by stable id.
 *
 * This is the destination for the full screen control and the non JavaScript
 * fallback link on an embedded exercise, so it must remain reachable and
 * readable even when the embed itself cannot render.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_saylorcode\local\library\exercise_repository;
use local_saylorcode\local\library\exercise_view;
use local_saylorcode\local\library\resolved_exercise;
use local_saylorcode\local\runtime\profile_manager;
use local_saylorcode\local\stable_id;

$stableidparam = required_param('stableid', PARAM_ALPHANUMEXT);
$version = optional_param('version', 'latest', PARAM_ALPHANUM);

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/local/saylorcode/exercise.php', ['stableid' => $stableidparam, 'version' => $version]);
$PAGE->set_pagelayout('standard');

// Validate before doing anything else. A malformed reference is a content bug
// rather than a server error, so it is reported plainly instead of throwing.
if (!stable_id::is_valid($stableidparam)) {
    $PAGE->set_title(get_string('pluginname', 'local_saylorcode'));
    $PAGE->set_heading(get_string('pluginname', 'local_saylorcode'));

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('exerciseidinvalid', 'local_saylorcode', s($stableidparam)),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

$stableid = stable_id::parse($stableidparam);

// Resolve the published version being asked for. 'latest' follows the exercise;
// a number pins the view to one version, so a link to a specific version keeps
// showing that version whatever the author publishes next.
$repository = new exercise_repository();
$exercise = $repository->find((string) $stableid);

$versionrow = null;
$versionmissing = false;
if ($exercise !== null) {
    if ($version === 'latest') {
        $versionrow = $repository->get_latest($exercise);
    } else {
        $versionrow = $repository->get_version($exercise, (int) $version);
        // A specific version that is not there is a different fact from an
        // exercise that has never been published, and worth saying so.
        $versionmissing = $versionrow === null;
    }
}

// The heading is the exercise's own name once it resolves, falling back to the
// reference while it does not: a bare reference is all there is to show for an
// exercise that is not published yet.
$title = $versionrow !== null && $exercise !== null
    ? format_string($exercise->name)
    : get_string('exercisetitle', 'local_saylorcode', (string) $stableid);

$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if ($versionrow === null) {
    // Either not published, or a named version that does not exist. Said plainly
    // rather than shown as an empty workspace that looks broken.
    echo $OUTPUT->notification(
        $versionmissing
            ? get_string('exerciseversionmissing', 'local_saylorcode', s($version))
            : get_string('exercisenotpublished', 'local_saylorcode', (string) $stableid),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

$resolved = new resolved_exercise($versionrow, $exercise, $version === 'latest' ? 'latest' : 'pinned');

// The language comes from the version, not the exercise. Publishing snapshots
// the profile onto the version, so a pinned older version keeps the runtime it
// was published against even after the exercise's profile is changed; reading
// the exercise's current profile here would pair old code with a new language.
$profileid = trim((string) ($versionrow->profileid ?? '')) !== ''
    ? (string) $versionrow->profileid
    : (string) $exercise->profileid;
$profile = (new profile_manager())->get_profile($profileid);
$runtimename = $profile ? $profile->get_display_name() : $profileid;

// A read-only presentation, deliberately not a workspace. A page with no course
// module has no attempt, so it can offer no save, run, completion or grade
// (specification section 22.3); showing an inert editor would only invite a
// student to type something and lose it. The way to work on an exercise is the
// activity that carries it.
echo $OUTPUT->notification(get_string('exerciseviewnote', 'local_saylorcode'), \core\output\notification::NOTIFY_INFO);

$meta = html_writer::tag('dt', get_string('exercisestableid', 'local_saylorcode'))
    . html_writer::tag('dd', s((string) $stableid))
    . html_writer::tag('dt', get_string('exerciseversionshead', 'local_saylorcode'))
    . html_writer::tag('dd', s(get_string('exerciseversionn', 'local_saylorcode', $resolved->get_version_number())))
    . html_writer::tag('dt', get_string('profileid', 'local_saylorcode'))
    . html_writer::tag('dd', s($runtimename));
echo html_writer::tag('dl', $meta, ['class' => 'saylorcode-exercise-meta row']);

$summary = trim((string) ($exercise->summary ?? ''));
if ($summary !== '') {
    echo html_writer::tag('p', format_text($summary, FORMAT_PLAIN), ['class' => 'saylorcode-exercise-summary']);
}

// Starter code, shown as text. It is never executed or edited here, so it is
// escaped and read only.
echo $OUTPUT->heading(get_string('exercisestarter', 'local_saylorcode'), 3);
echo html_writer::tag('p', s($resolved->get_entry_filename()), ['class' => 'saylorcode-exercise-filename text-muted']);
echo html_writer::tag('pre', s($resolved->get_starter_code()), ['class' => 'saylorcode-exercise-starter']);

// Public sample tests only. A hidden case is never described outside a
// submission, so it is not disclosed here either -- only that hidden tests
// exist, so a reader is not misled into thinking the sample list is the whole
// assessment.
echo $OUTPUT->heading(get_string('exercisesampletests', 'local_saylorcode'), 3);

$samples = exercise_view::sample_tests($resolved);

if (!$samples) {
    // Two different empties: an exercise with hidden cases but no public ones,
    // and one with no automated tests at all (a starter-only exercise, which
    // publishing permits). Only the first should say hidden tests will run.
    $emptykey = exercise_view::has_hidden_tests($resolved) ? 'exercisenosampletests' : 'exercisenotests';
    echo $OUTPUT->notification(get_string($emptykey, 'local_saylorcode'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('tcname', 'local_saylorcode'),
        get_string('tcexpected', 'local_saylorcode'),
    ];
    $table->attributes['class'] = 'generaltable saylorcode-exercise-tests';

    foreach ($samples as $sample) {
        $table->data[] = [
            s($sample['name']),
            html_writer::tag('pre', s($sample['expected']), ['class' => 'saylorcode-exercise-expected']),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
