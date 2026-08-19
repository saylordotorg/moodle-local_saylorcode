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
 * The exercise library: where exercises live, apart from the activities using them.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_saylorcode\form\exercise_form;
use local_saylorcode\local\library\exercise_repository;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/saylorcode:viewlibrary', $context);

$pageurl = new moodle_url('/local/saylorcode/library.php');

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('library', 'local_saylorcode'));
$PAGE->set_heading(get_string('library', 'local_saylorcode'));

$repository = new exercise_repository();

if ($action === 'publish') {
    require_sesskey();
    require_capability('local/saylorcode:publishexercise', $context);

    $exercise = $DB->get_record('local_saylorcode_exercises', ['id' => $id], '*', MUST_EXIST);
    $note = optional_param('changenote', '', PARAM_TEXT);

    try {
        $version = $repository->publish($exercise, $note);
        redirect(
            $pageurl,
            get_string('exercisepublished', 'local_saylorcode', $version->version),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'edit' || $action === 'add') {
    require_capability('local/saylorcode:publishexercise', $context);

    $exercise = $id ? $DB->get_record('local_saylorcode_exercises', ['id' => $id], '*', MUST_EXIST) : null;

    $draft = $exercise ? $repository->get_draft($exercise) : null;
    $storedcases = $draft ? json_decode((string) $draft->testcases, true) : [];
    $storedhints = $draft ? json_decode((string) $draft->hints, true) : [];
    $storedcases = is_array($storedcases) ? $storedcases : [];
    $storedhints = is_array($storedhints) ? $storedhints : [];

    $form = new exercise_form(
        new moodle_url($pageurl, ['action' => $action, 'id' => $id]),
        [
            'exerciseid' => $exercise ? $exercise->id : 0,
            'casecount' => count($storedcases),
            'hintcount' => count($storedhints),
        ]
    );

    if ($form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $form->get_data()) {
        $fields = [
            'summary' => $data->summary,
            'profileid' => $data->profileid,
            'entryfilename' => $data->entryfilename,
            'startercode' => $data->startercode,
            'referencesolution' => $data->referencesolution,
            'testcases' => exercise_form::rows_to_cases($data),
            'hints' => exercise_form::rows_to_hints($data),
        ];

        if ($exercise === null) {
            $exercise = $repository->create($data->stableid, $data->name, $fields);
        } else {
            $exercise->name = $data->name;
            $exercise->summary = $data->summary;
            $exercise->profileid = $data->profileid;
            $exercise->timemodified = time();
            $DB->update_record('local_saylorcode_exercises', $exercise);
            $repository->write_draft($exercise, $fields);
        }

        redirect(
            $pageurl,
            get_string('exercisesaved', 'local_saylorcode'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($exercise !== null) {
        $values = [
            'id' => $exercise->id,
            'stableid' => $exercise->stableid,
            'name' => $exercise->name,
            'summary' => $exercise->summary,
            'profileid' => $exercise->profileid,
            'entryfilename' => $draft->entryfilename,
            'startercode' => $draft->startercode,
            'referencesolution' => $draft->referencesolution,
        ];

        foreach (array_values($storedcases) as $i => $case) {
            $values['tcname[' . $i . ']'] = (string) ($case['name'] ?? '');
            $values['tcstdin[' . $i . ']'] = (string) ($case['stdin'] ?? '');
            $values['tcexpected[' . $i . ']'] = (string) ($case['expected'] ?? '');
            $values['tcfeedback[' . $i . ']'] = (string) ($case['feedback'] ?? '');
            $values['tcpublic[' . $i . ']'] = exercise_form::case_is_public($case) ? 1 : 0;
            $values['tcweight[' . $i . ']'] = (float) ($case['weight'] ?? 1);
        }

        foreach (array_values($storedhints) as $i => $hint) {
            $values['hinttext[' . $i . ']'] = (string) ($hint['text'] ?? '');
        }

        $form->set_data($values);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($exercise ? 'exerciseedit' : 'exerciseadd', 'local_saylorcode'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$exercises = $DB->get_records('local_saylorcode_exercises', null, 'stableid ASC');
$canpublish = has_capability('local/saylorcode:publishexercise', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('library', 'local_saylorcode'));
echo html_writer::tag('p', get_string('libraryintro', 'local_saylorcode'), ['class' => 'text-muted']);

if (!$exercises) {
    echo $OUTPUT->notification(get_string('librarynone', 'local_saylorcode'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('exercisestableid', 'local_saylorcode'),
        get_string('exercisename', 'local_saylorcode'),
        get_string('profileid', 'local_saylorcode'),
        get_string('exerciseversionshead', 'local_saylorcode'),
        get_string('exerciseactions', 'local_saylorcode'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($exercises as $exercise) {
        $version = (int) $exercise->currentversion;

        $published = $version > 0
            ? get_string('exerciseversionn', 'local_saylorcode', $version)
            : html_writer::span(get_string('exerciseunpublished', 'local_saylorcode'), 'text-muted');

        $actions = [];

        if ($canpublish) {
            $actions[] = html_writer::link(
                new moodle_url($pageurl, ['action' => 'edit', 'id' => $exercise->id]),
                get_string('editdraft', 'local_saylorcode')
            );

            $actions[] = $OUTPUT->action_link(
                new moodle_url($pageurl, ['action' => 'publish', 'id' => $exercise->id, 'sesskey' => sesskey()]),
                get_string('publish', 'local_saylorcode'),
                new confirm_action(get_string('publishconfirm', 'local_saylorcode'))
            );
        }

        $table->data[] = [
            $exercise->stableid,
            format_string($exercise->name),
            $exercise->profileid,
            $published,
            implode(' &nbsp; ', $actions),
        ];
    }

    echo html_writer::table($table);
}

if ($canpublish) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url($pageurl, ['action' => 'add']),
            get_string('exerciseadd', 'local_saylorcode'),
            ['class' => 'btn btn-primary']
        ),
        'mt-3'
    );
}

echo $OUTPUT->footer();
