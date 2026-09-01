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

use local_saylorcode\local\runner\execution_response;
use local_saylorcode\local\runner\execution_state;

/**
 * Tests for recognising a program that ran out of input.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\execution_response::ran_out_of_input
 */
final class ran_out_of_input_test extends \advanced_testcase {
    /**
     * Build a response with the given state and stderr.
     *
     * @param string $state The execution state.
     * @param string $stderr The error output.
     * @return execution_response
     */
    private function response(string $state, string $stderr): execution_response {
        return new execution_response('rid', $state, '', $stderr);
    }

    /**
     * A Java Scanner reaching end of input is recognised.
     *
     * This is the trace the runner actually returns for a program given an
     * empty Input tab, captured from the dev runner rather than invented.
     */
    public function test_a_java_scanner_at_end_of_input_is_recognised(): void {
        $stderr = "Exception in thread \"main\" java.util.NoSuchElementException\n"
            . "\tat java.base/java.util.Scanner.throwFor(Scanner.java:937)\n"
            . "\tat java.base/java.util.Scanner.next(Scanner.java:1594)\n"
            . "\tat java.base/java.util.Scanner.nextDouble(Scanner.java:2564)\n"
            . "\tat Logarithm.main(Logarithm.java:5)\n";

        $this->assertTrue($this->response(execution_state::RUNTIME_ERROR, $stderr)->ran_out_of_input());
    }

    /**
     * Python reaching end of file on input() is recognised.
     */
    public function test_python_end_of_file_is_recognised(): void {
        $stderr = "Traceback (most recent call last):\n"
            . "  File \"program.py\", line 1, in <module>\n"
            . "    name = input()\n"
            . "EOFError: EOF when reading a line\n";

        $this->assertTrue($this->response(execution_state::RUNTIME_ERROR, $stderr)->ran_out_of_input());
    }

    /**
     * An ordinary runtime error is not mistaken for missing input.
     *
     * The point of the check is a specific, actionable message. Showing it for
     * every crash would make it noise, and would mislead a student whose
     * program divided by zero into hunting for input they never needed.
     */
    public function test_an_ordinary_runtime_error_is_not_recognised(): void {
        $stderr = "Exception in thread \"main\" java.lang.ArithmeticException: / by zero\n"
            . "\tat Main.main(Main.java:3)\n";

        $this->assertFalse($this->response(execution_state::RUNTIME_ERROR, $stderr)->ran_out_of_input());
    }

    /**
     * A successful run is never treated as missing input.
     *
     * A program can legitimately print the words itself -- an exercise about
     * exceptions might -- and completing successfully settles it.
     */
    public function test_a_completed_run_is_never_recognised(): void {
        $chatty = "java.util.NoSuchElementException was mentioned in the output\n";

        $this->assertFalse($this->response(execution_state::COMPLETED, $chatty)->ran_out_of_input());
    }

    /**
     * A compile error is not missing input either.
     */
    public function test_a_compile_error_is_not_recognised(): void {
        $this->assertFalse(
            $this->response(execution_state::COMPILE_ERROR, 'java.util.NoSuchElementException')->ran_out_of_input()
        );
    }

    /**
     * Empty error output is not missing input.
     */
    public function test_empty_stderr_is_not_recognised(): void {
        $this->assertFalse($this->response(execution_state::RUNTIME_ERROR, '')->ran_out_of_input());
    }
}
