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

        // Test cases and hints stay as JSON here. The structured editors live on
        // the activity form, and moving them is a separate change from giving
        // exercises somewhere to live.
        $mform->addElement(
            'textarea',
            'testcases',
            get_string('exercisetestcases', 'local_saylorcode'),
            ['rows' => 8, 'cols' => 80, 'spellcheck' => 'false']
        );
        $mform->setType('testcases', PARAM_RAW);
        $mform->addHelpButton('testcases', 'exercisetestcases', 'local_saylorcode');

        $mform->addElement(
            'textarea',
            'hints',
            get_string('exercisehints', 'local_saylorcode'),
            ['rows' => 5, 'cols' => 80, 'spellcheck' => 'false']
        );
        $mform->setType('hints', PARAM_RAW);

        $this->add_action_buttons();
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

        // Malformed JSON here would reach a student as an exercise with no
        // tests, so it is refused at the point somebody can still fix it.
        foreach (['testcases' => 'testcases', 'hints' => 'hints'] as $field => $key) {
            $raw = trim((string) ($data[$field] ?? ''));

            if ($raw === '') {
                continue;
            }

            if (!is_array(json_decode($raw, true))) {
                $errors[$field] = get_string('exercisejsoninvalid', 'local_saylorcode');
            }
        }

        return $errors;
    }
}
