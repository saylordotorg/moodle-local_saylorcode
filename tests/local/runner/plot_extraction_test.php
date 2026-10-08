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

namespace local_saylorcode\local\runner;

use local_saylorcode\local\runtime\profile;

/**
 * Tests for taking plots out of the runner's stderr.
 *
 * The runner's R wrapper writes each image a program drew as a marked line.
 * Only a real PNG of bounded size may reach the browser, and the marked lines
 * must never be shown to the student as error output.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\jobe_provider::extract_plots
 * @covers     \local_saylorcode\local\runner\execution_response
 */
final class plot_extraction_test extends \advanced_testcase {
    /** @var string A valid one pixel PNG, base64. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * A marked line.
     *
     * @param string $payload What goes inside the marker.
     * @return string
     */
    private static function line(string $payload): string {
        return '[saylorcode-plot:' . $payload . ']';
    }

    /**
     * A valid plot is returned, and its line leaves stderr; other stderr stays.
     */
    public function test_a_plot_is_extracted_and_stderr_kept(): void {
        $stderr = "Warning message:\nNAs introduced by coercion\n" . self::line(self::PNG) . "\n";

        $plots = [];
        $clean = jobe_provider::extract_plots($stderr, $plots);

        $this->assertSame([self::PNG], $plots);
        $this->assertStringNotContainsString('saylorcode-plot', $clean);
        $this->assertStringContainsString('NAs introduced by coercion', $clean);
    }

    /**
     * Stderr without plots is returned untouched.
     */
    public function test_stderr_without_plots_is_unchanged(): void {
        $plots = ['left over'];
        $stderr = "Error: boom\nExecution halted\n";

        $this->assertSame($stderr, jobe_provider::extract_plots($stderr, $plots));
        $this->assertSame([], $plots);
    }

    /**
     * Anything that is not a PNG is dropped, and its line still leaves stderr.
     *
     * An SVG or HTML payload would be the dangerous one: the browser renders
     * the result as an image source, so only PNG bytes may pass.
     */
    public function test_only_png_is_accepted(): void {
        $stderr = implode("\n", [
            self::line(base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')),
            self::line(base64_encode('<html><body>hi</body></html>')),
            self::line('not base64 at all!'),
            self::line(''),
            self::line(self::PNG),
        ]);

        $plots = [];
        $clean = jobe_provider::extract_plots($stderr, $plots);

        $this->assertSame([self::PNG], $plots);
        $this->assertStringNotContainsString('saylorcode-plot', $clean);
        $this->assertStringNotContainsString('svg', $clean);
    }

    /**
     * An image over the size limit is dropped.
     */
    public function test_an_oversized_image_is_dropped(): void {
        $big = base64_encode(jobe_provider::PNG_SIGNATURE . str_repeat("\0", jobe_provider::PLOT_MAX_BYTES));

        $plots = [];
        $clean = jobe_provider::extract_plots(self::line($big), $plots);

        $this->assertSame([], $plots);
        $this->assertSame('', trim($clean));
    }

    /**
     * No more than PLOT_MAX_COUNT plots are kept, in the order drawn.
     */
    public function test_the_plot_count_is_capped(): void {
        $lines = [];
        for ($i = 0; $i < jobe_provider::PLOT_MAX_COUNT + 3; $i++) {
            $lines[] = self::line(self::PNG);
        }

        $plots = [];
        $clean = jobe_provider::extract_plots(implode("\n", $lines), $plots);

        $this->assertCount(jobe_provider::PLOT_MAX_COUNT, $plots);
        $this->assertStringNotContainsString('saylorcode-plot', $clean);
    }

    /**
     * Base64 is returned in canonical form, whatever padding or line endings
     * the runner used.
     */
    public function test_plots_are_reencoded(): void {
        $plots = [];
        jobe_provider::extract_plots(self::line(self::PNG) . "\r\n", $plots);

        $this->assertSame(base64_encode(base64_decode(self::PNG)), $plots[0]);
    }

    /**
     * Through the provider: the student payload carries the plots, and the
     * stderr it shows has no markers.
     */
    public function test_a_run_carries_plots_to_the_student(): void {
        $this->resetAfterTest();

        $provider = new class ('http://runner.invalid', 'key') extends jobe_provider {
            /**
             * Expose the response builder.
             *
             * @param execution_request $request The request.
             * @param profile $profile The profile.
             * @param array $decoded Decoded Jobe JSON.
             * @return execution_response
             */
            public function respond(execution_request $request, profile $profile, array $decoded): execution_response {
                return $this->build_response($request, $profile, $decoded, 0.1);
            }
        };
        $profile = new profile('r', 'R', 'r', 'main.R');
        $request = new execution_request('req', 'r', execution_request::MODE_RUN, ['main.R' => 'plot(1)']);

        $response = $provider->respond($request, $profile, [
            'outcome' => 15,
            'stdout' => "done\n",
            'stderr' => self::line(self::PNG) . "\n",
            'cmpinfo' => '',
        ]);
        $student = $response->export_for_student();

        $this->assertSame(execution_state::COMPLETED, $student['state']);
        $this->assertSame([self::PNG], $student['plots']);
        $this->assertStringNotContainsString('saylorcode-plot', $student['stderr']);
        $this->assertSame("done\n", $student['stdout']);
    }
}
