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

/**
 * Removes infrastructure detail from runner output before a student sees it.
 *
 * Compilers and test harnesses routinely echo absolute sandbox paths, wrapper
 * class names and internal host names. None of that helps a learner and some of
 * it describes our infrastructure, so it is stripped centrally here rather than
 * relying on every template to remember (specification sections 9.8 and 14.3).
 *
 * Directory components are removed but a source file basename is kept, because
 * "Main.java:4: error: cannot find symbol" is the single most useful diagnostic
 * a beginner receives and it is worthless without the file and line.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_sanitiser {

    /** @var string Replacement token shown in place of a removed path. */
    public const REDACTED = '[path]';

    /** @var string Replacement token shown in place of a host or address. */
    public const REDACTED_HOST = '[host]';

    /**
     * Strip infrastructure detail from a block of runner output.
     *
     * @param string $output Raw output from the runner.
     * @return string Output safe to show a student.
     */
    public static function sanitise(string $output): string {
        if ($output === '') {
            return '';
        }

        $result = self::redact_paths($output);
        if ($result === null) {
            return get_string('outputsuppressed', 'local_saylorcode');
        }

        $result = self::redact_hosts($result);
        if ($result === null) {
            return get_string('outputsuppressed', 'local_saylorcode');
        }

        return self::strip_wrapper_frames($result);
    }

    /**
     * Replace absolute paths, keeping a filename where one is present.
     *
     * @param string $output Raw output.
     * @return string|null Null if the regular expression engine failed.
     */
    protected static function redact_paths(string $output): ?string {
        $patterns = [
            // Absolute POSIX path of at least two segments.
            '~/(?:[\w.-]+/)+[\w.-]+~',
            // Absolute Windows path, seen in developer environments.
            '~[A-Za-z]:\\\\(?:[\w .-]+\\\\)*[\w .-]+~',
        ];

        $result = $output;
        foreach ($patterns as $pattern) {
            $replaced = preg_replace_callback($pattern, static function (array $matches): string {
                return self::describe_path($matches[0]);
            }, $result);

            if ($replaced === null) {
                return null;
            }
            $result = $replaced;
        }

        return $result;
    }

    /**
     * Decide what to show in place of one matched path.
     *
     * A basename that looks like a file is retained so diagnostics stay useful.
     * Anything else becomes an opaque token.
     *
     * @param string $path The matched absolute path.
     * @return string
     */
    protected static function describe_path(string $path): string {
        $normalised = str_replace('\\', '/', $path);
        $basename = substr($normalised, (int) strrpos($normalised, '/') + 1);

        // A dot separated basename is a filename; keep it, drop the directories.
        if ($basename !== '' && strpos($basename, '.') !== false) {
            return $basename;
        }

        return self::REDACTED;
    }

    /**
     * Replace host names and IP addresses.
     *
     * @param string $output Partially sanitised output.
     * @return string|null Null if the regular expression engine failed.
     */
    protected static function redact_hosts(string $output): ?string {
        $patterns = [
            '~\b[\w-]+(?:\.[\w-]+)*\.(?:internal|local|localdomain|compute\.amazonaws\.com)\b~',
            '~\b(?:\d{1,3}\.){3}\d{1,3}\b~',
        ];

        $result = $output;
        foreach ($patterns as $pattern) {
            $replaced = preg_replace($pattern, self::REDACTED_HOST, $result);
            if ($replaced === null) {
                return null;
            }
            $result = $replaced;
        }

        return $result;
    }

    /**
     * Remove stack frames belonging to our own test scaffolding.
     *
     * A student should see the frames from their own class, not the harness that
     * invoked it, and certainly not the name of a hidden test method.
     *
     * @param string $output Partially sanitised output.
     * @return string
     */
    protected static function strip_wrapper_frames(string $output): string {
        $lines = preg_split('~\R~', $output);
        if ($lines === false) {
            return $output;
        }

        $kept = [];
        $removed = false;
        foreach ($lines as $line) {
            if (self::is_wrapper_frame($line)) {
                $removed = true;
                continue;
            }
            $kept[] = $line;
        }

        if ($removed) {
            $kept[] = get_string('framesomitted', 'local_saylorcode');
        }

        return implode("\n", $kept);
    }

    /**
     * Whether a single line is a stack frame from our scaffolding.
     *
     * @param string $line One line of output.
     * @return bool
     */
    protected static function is_wrapper_frame(string $line): bool {
        $markers = [
            'SaylorCodeHarness',
            'SaylorCodeRunner',
            '__saylor_',
            'org.junit.runner',
            'org.junit.internal',
            'jdk.internal.reflect',
            'java.base/jdk.internal',
        ];
        foreach ($markers as $marker) {
            if (strpos($line, $marker) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Truncate output to the configured limit, flagging that it happened.
     *
     * @param string $output Raw output.
     * @param int $limitbytes Maximum bytes to retain.
     * @param bool $truncated Set by reference to true when truncation occurred.
     * @return string
     */
    public static function truncate(string $output, int $limitbytes, bool &$truncated = false): string {
        if ($limitbytes <= 0 || strlen($output) <= $limitbytes) {
            $truncated = false;
            return $output;
        }
        $truncated = true;
        return substr($output, 0, $limitbytes);
    }
}
