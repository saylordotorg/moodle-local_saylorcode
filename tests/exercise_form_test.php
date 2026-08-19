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

use local_saylorcode\form\exercise_form;
use stdClass;

/**
 * Tests for the library's exercise form.
 *
 * Both cases here are regressions rather than new ground. The row editors
 * replaced a raw JSON field, and in doing so quietly changed what happened to
 * two kinds of author input: a test case that predates the visibility flag, and
 * any text shaped like an HTML tag. Neither failed loudly. An author would have
 * had to notice, some time later, that a case had gone hidden or that a hint had
 * lost half its sentence.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\form\exercise_form
 */
final class exercise_form_test extends \advanced_testcase {
    /**
     * Rows become the stored case shape.
     *
     * @return void
     */
    public function test_rows_become_cases(): void {
        $this->resetAfterTest();

        $data = new stdClass();
        $data->tcname = ['Greets', 'Adds'];
        $data->tcstdin = ['', "2 3\n"];
        $data->tcexpected = ["Hello\n", "5\n"];
        $data->tcfeedback = ['Check the spelling.', ''];
        $data->tcpublic = [1, 0];
        $data->tcweight = [1, 2];

        $cases = json_decode(exercise_form::rows_to_cases($data), true);

        $this->assertCount(2, $cases);
        $this->assertSame('Greets', $cases[0]['name']);
        $this->assertTrue($cases[0]['ispublic']);
        $this->assertFalse($cases[1]['ispublic']);
        // JSON has one number type, so a whole weight comes back as an int.
        $this->assertEquals(2, $cases[1]['weight']);
    }

    /**
     * The empty row an author leaves at the bottom is not a case.
     *
     * @return void
     */
    public function test_blank_rows_are_dropped(): void {
        $this->resetAfterTest();

        $data = new stdClass();
        $data->tcname = ['Greets', ''];
        $data->tcstdin = ['', ''];
        $data->tcexpected = ["Hello\n", ''];
        $data->tcfeedback = ['', ''];
        $data->tcpublic = [1, 1];
        $data->tcweight = [1, 1];

        $cases = json_decode(exercise_form::rows_to_cases($data), true);

        $this->assertCount(1, $cases);
    }

    /**
     * Tag-shaped text is carried through the row conversion unaltered.
     *
     * This covers the conversion only. The stripping this pairs with happens
     * during form submission, and is guarded separately by the test of the
     * declared parameter types.
     *
     * @return void
     */
    public function test_code_shaped_text_is_not_stripped(): void {
        $this->resetAfterTest();

        $sample = 'Returns List<String> when a < b';

        $data = new stdClass();
        $data->tcname = [$sample];
        $data->tcstdin = [''];
        $data->tcexpected = ["ok\n"];
        $data->tcfeedback = [$sample];
        $data->tcpublic = [1];
        $data->tcweight = [1];

        $cases = json_decode(exercise_form::rows_to_cases($data), true);

        $this->assertSame($sample, $cases[0]['name']);
        $this->assertSame($sample, $cases[0]['feedback']);

        $hintdata = new stdClass();
        $hintdata->hinttext = ['Use List<String> here'];

        $hints = json_decode(exercise_form::rows_to_hints($hintdata), true);

        $this->assertSame('Use List<String> here', $hints[0]['text']);
    }

    /**
     * The fields that describe code are not declared as plain text.
     *
     * This asserts the declared parameter types rather than a round trip,
     * because the stripping happens inside form submission and never reaches
     * rows_to_cases(). A test that only pushes strings through that method
     * passes just as happily against PARAM_TEXT, which makes it worthless as a
     * guard. The type declaration is the thing that actually changed, so the
     * type declaration is what is asserted.
     *
     * @return void
     */
    public function test_code_bearing_fields_are_not_declared_plain_text(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/saylorcode/library.php');

        $form = new exercise_form(null, ['exerciseid' => 0, 'casecount' => 1, 'hintcount' => 1]);

        $property = new \ReflectionProperty(\moodleform::class, '_form');
        $property->setAccessible(true);
        $mform = $property->getValue($form);

        foreach (['tcname[0]', 'tcfeedback[0]', 'hinttext[0]'] as $element) {
            $this->assertSame(
                PARAM_RAW,
                $mform->getCleanType($element, 'x'),
                "{$element} is declared as plain text, so tag-shaped input such as List<String> is silently stripped."
            );
        }
    }

    /**
     * A case with no visibility flag is public, not hidden.
     *
     * Cases written before the flag existed have no key at all. Reading absent
     * as hidden would take the case's name, its output comparison and its
     * feedback away from the student the next time anyone saved the exercise,
     * and nothing would report that it had happened.
     *
     * @return void
     */
    public function test_a_case_without_a_visibility_flag_stays_public(): void {
        $this->resetAfterTest();

        $this->assertTrue(
            exercise_form::case_is_public(['name' => 'Greets', 'expected' => "Hello\n"]),
            'A case saved before the visibility flag existed must load as public.'
        );

        $this->assertFalse(
            exercise_form::case_is_public(['name' => 'Secret', 'ispublic' => false]),
            'An explicitly hidden case must stay hidden.'
        );

        $this->assertTrue(
            exercise_form::case_is_public(['name' => 'Shown', 'ispublic' => true]),
            'An explicitly public case must stay public.'
        );
    }
}
