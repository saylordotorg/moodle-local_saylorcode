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

namespace local_saylorcode\form;

use local_saylorcode\local\runtime\profile_manager;
use local_saylorcode\local\stable_id;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The working draft of an exercise.
 *
 * Only the draft is editable. A published version is immutable by design, so
 * this form never edits one: it edits the draft, and publishing copies it.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exercise_form extends moodleform {
    /**
     * Build the form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $existing = !empty($this->_customdata['exerciseid']);

        $mform->addElement('hidden', 'id', $this->_customdata['exerciseid'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'stableid', get_string('exercisestableid', 'local_saylorcode'), ['size' => 24]);
        $mform->setType('stableid', PARAM_ALPHANUMEXT);
        $mform->addRule('stableid', null, 'required', null, 'client');
        $mform->addHelpButton('stableid', 'exercisestableid', 'local_saylorcode');

        // The reference is how every activity finds this exercise, so changing
        // it after the fact would orphan them all.
        if ($existing) {
            $mform->hardFreeze('stableid');
        }

        $mform->addElement('text', 'name', get_string('exercisename', 'local_saylorcode'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'textarea',
            'summary',
            get_string('exercisesummary', 'local_saylorcode'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('summary', PARAM_TEXT);

        $mform->addElement(
            'select',
            'profileid',
            get_string('profileid', 'local_saylorcode'),
            (new profile_manager())->get_menu()
        );

        $mform->addElement(
            'text',
            'entryfilename',
            get_string('exerciseentryfile', 'local_saylorcode'),
            ['size' => 40]
        );
        $mform->setType('entryfilename', PARAM_FILE);
        $mform->setDefault('entryfilename', 'Main.java');

        $mform->addElement(
            'textarea',
            'startercode',
            get_string('exercisestarter', 'local_saylorcode'),
            ['rows' => 12, 'cols' => 80, 'spellcheck' => 'false']
        );
        $mform->setType('startercode', PARAM_RAW);

        $mform->addElement(
            'textarea',
            'referencesolution',
            get_string('exercisesolution', 'local_saylorcode'),
            ['rows' => 10, 'cols' => 80, 'spellcheck' => 'false']
        );
        $mform->setType('referencesolution', PARAM_RAW);

        // Rows rather than JSON. An author writing an exercise here should not
        // have to hand write a data structure that the activity form has
        // offered as fields since it was built.
        $mform->addElement('header', 'testcasesheader', get_string('exercisetestcases', 'local_saylorcode'));
        $mform->addElement('static', 'testcasesintro', '', get_string('exercisetestcases_help', 'local_saylorcode'));

        $cases = [
            $mform->createElement('text', 'tcname', get_string('tcname', 'local_saylorcode'), ['size' => 40]),
            $mform->createElement(
                'textarea',
                'tcstdin',
                get_string('tcstdin', 'local_saylorcode'),
                ['rows' => 2, 'cols' => 45, 'spellcheck' => 'false']
            ),
            $mform->createElement(
                'textarea',
                'tcexpected',
                get_string('tcexpected', 'local_saylorcode'),
                ['rows' => 3, 'cols' => 45, 'spellcheck' => 'false']
            ),
            $mform->createElement('text', 'tcfeedback', get_string('tcfeedback', 'local_saylorcode'), ['size' => 55]),
            $mform->createElement('advcheckbox', 'tcpublic', get_string('tcpublic', 'local_saylorcode')),
            $mform->createElement('text', 'tcweight', get_string('tcweight', 'local_saylorcode'), ['size' => 4]),
        ];

        $this->repeat_elements(
            $cases,
            max(1, (int) ($this->_customdata['casecount'] ?? 0)),
            [
                // Names and feedback describe code, so they routinely contain
                // things shaped like tags: List<String>, Map<K, V>, a < b.
                // PARAM_TEXT strips those silently, turning "Returns
                // List<String>" into "Returns List" on save. Everything that
                // renders these escapes them -- the template through {{ }} and
                // the workspace through textContent -- so raw is safe here.
                'tcname' => ['type' => PARAM_RAW],
                'tcstdin' => ['type' => PARAM_RAW],
                'tcexpected' => ['type' => PARAM_RAW],
                'tcfeedback' => ['type' => PARAM_RAW],
                'tcpublic' => ['type' => PARAM_BOOL, 'default' => 1],
                'tcweight' => ['type' => PARAM_FLOAT, 'default' => 1],
            ],
            'testcaserepeats',
            'testcaseadd',
            2,
            get_string('exerciseaddcases', 'local_saylorcode'),
            true
        );

        $mform->addElement('header', 'hintsheader', get_string('exercisehints', 'local_saylorcode'));
        $mform->addElement('static', 'hintsintro', '', get_string('exercisehints_help', 'local_saylorcode'));

        $this->repeat_elements(
            [
                $mform->createElement(
                    'textarea',
                    'hinttext',
                    get_string('exercisehint', 'local_saylorcode'),
                    ['rows' => 2, 'cols' => 60]
                ),
            ],
            max(1, (int) ($this->_customdata['hintcount'] ?? 0)),
            // As with case names: a hint that says "use List<String> here" must
            // survive being saved. Hints are escaped wherever they are shown.
            ['hinttext' => ['type' => PARAM_RAW]],
            'hintrepeats',
            'hintadd',
            2,
            get_string('exerciseaddhints', 'local_saylorcode'),
            true
        );

        $this->add_action_buttons();
    }

    /**
     * Turn the submitted rows into the stored test case JSON.
     *
     * Kept here beside the fields that produce it, and static so the page can
     * call it without building a form.
     *
     * @param \stdClass $data Submitted form data.
     * @return string JSON, or an empty string for no cases.
     */
    public static function rows_to_cases($data): string {
        $expected = (array) ($data->tcexpected ?? []);
        $cases = [];

        foreach ($expected as $i => $value) {
            $name = trim((string) ($data->tcname[$i] ?? ''));
            $value = (string) $value;

            // A row with neither a name nor an expected value is the blank one
            // an author leaves at the bottom, not a case.
            if ($name === '' && trim($value) === '') {
                continue;
            }

            $cases[] = [
                'id' => 'T' . (count($cases) + 1),
                'name' => $name !== '' ? $name : get_string('exercisecaseunnamed', 'local_saylorcode', count($cases) + 1),
                'stdin' => (string) ($data->tcstdin[$i] ?? ''),
                'expected' => $value,
                'feedback' => trim((string) ($data->tcfeedback[$i] ?? '')),
                'ispublic' => !empty($data->tcpublic[$i]),
                'weight' => (float) ($data->tcweight[$i] ?? 1),
            ];
        }

        return $cases ? json_encode($cases) : '';
    }

    /**
     * Whether a stored case should be shown to students.
     *
     * Cases written before the visibility flag existed carry no key at all.
     * Absent has to read as public, because that is what a new row and the
     * runner both default to; reading it as hidden would take the case's name,
     * its output comparison and its feedback away from the student the next
     * time anyone saved the exercise, and nothing would report it. An explicit
     * false still means hidden.
     *
     * @param array $case One stored case.
     * @return bool
     */
    public static function case_is_public(array $case): bool {
        return array_key_exists('ispublic', $case) ? !empty($case['ispublic']) : true;
    }

    /**
     * Turn the submitted rows into the stored hint JSON.
     *
     * @param \stdClass $data Submitted form data.
     * @return string JSON, or an empty string for no hints.
     */
    public static function rows_to_hints($data): string {
        $hints = [];

        foreach ((array) ($data->hinttext ?? []) as $text) {
            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            $hints[] = ['text' => $text];
        }

        return $hints ? json_encode($hints) : '';
    }

    /**
     * Check the form.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $stableid = trim((string) ($data['stableid'] ?? ''));

        if ($stableid !== '' && !stable_id::is_valid($stableid)) {
            $errors['stableid'] = get_string('stableidinvalid', 'local_saylorcode');
        }

        // A row is only meaningful if it says what it expects, and a weight of
        // zero silently removes a case from the score.
        foreach ((array) ($data['tcexpected'] ?? []) as $i => $expected) {
            $named = trim((string) ($data['tcname'][$i] ?? '')) !== '';
            $hasexpected = trim((string) $expected) !== '';

            if (!$named && !$hasexpected) {
                continue;
            }

            if (!$hasexpected) {
                $errors['tcexpected[' . $i . ']'] = get_string('exercisecaseexpected', 'local_saylorcode');
            }

            if ((float) ($data['tcweight'][$i] ?? 1) <= 0) {
                $errors['tcweight[' . $i . ']'] = get_string('exercisecaseweight', 'local_saylorcode');
            }
        }

        return $errors;
    }
}
