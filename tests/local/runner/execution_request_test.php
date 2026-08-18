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

use coding_exception;

/**
 * Tests for execution request construction and path safety.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runner\execution_request
 */
final class execution_request_test extends \advanced_testcase {
    /**
     * Paths that must be rejected before they can reach a provider.
     *
     * @return array<string, array{string}>
     */
    public static function unsafe_path_provider(): array {
        return [
            'parent traversal' => ['../etc/passwd'],
            'nested traversal' => ['src/../../secrets.txt'],
            'absolute posix' => ['/etc/passwd'],
            'absolute windows' => ['C:/Windows/System32/config'],
            'empty path' => [''],
            'null byte' => ["Main.java\0.txt"],
            'space injection' => ['Main.java; rm -rf /'],
            'pipe character' => ['Main|evil.java'],
        ];
    }

    /**
     * Unsafe paths must be refused at construction time.
     *
     * @param string $path The candidate path.
     * @dataProvider unsafe_path_provider
     */
    public function test_unsafe_paths_are_rejected(string $path): void {
        $this->expectException(coding_exception::class);

        new execution_request('req-1', 'java17-console', execution_request::MODE_RUN, [$path => 'x']);
    }

    /**
     * Ordinary relative paths are accepted.
     */
    public function test_safe_paths_are_accepted(): void {
        $request = new execution_request(
            'req-2',
            'java17-console',
            execution_request::MODE_RUN,
            [
                'Main.java' => 'class Main {}',
                'util/Helper.java' => 'class Helper {}',
                'data-1.txt' => 'sample',
            ]
        );

        $this->assertCount(3, $request->get_files());
    }

    /**
     * An unknown mode is a programming error, not a runtime condition.
     */
    public function test_unknown_mode_is_rejected(): void {
        $this->expectException(coding_exception::class);

        new execution_request('req-3', 'java17-console', 'evaluate', ['Main.java' => '']);
    }

    /**
     * Only a submit request creates an official attempt.
     */
    public function test_only_submit_is_assessed(): void {
        $files = ['Main.java' => ''];

        $run = new execution_request('r', 'java17-console', execution_request::MODE_RUN, $files);
        $check = new execution_request('c', 'java17-console', execution_request::MODE_CHECK, $files);
        $submit = new execution_request('s', 'java17-console', execution_request::MODE_SUBMIT, $files);

        $this->assertFalse($run->is_assessed());
        $this->assertFalse($check->is_assessed());
        $this->assertTrue($submit->is_assessed());
    }
}
