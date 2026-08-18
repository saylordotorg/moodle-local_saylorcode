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
 * An immutable execution request handed to a runner provider.
 *
 * This object is deliberately the only way to describe work for a runner. It
 * carries no Moodle user identifier, name, email address, grade or course
 * reference, because the runner must never receive personally identifying data
 * (specification section 13.4). Correlation back to a Moodle attempt happens
 * through the request id, which is an opaque random token.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class execution_request {
    /** @var string Execute the student program only; never grades. */
    public const MODE_RUN = 'run';

    /** @var string Execute instructional and public tests. */
    public const MODE_CHECK = 'check';

    /** @var string Execute the assessment test set for an official attempt. */
    public const MODE_SUBMIT = 'submit';

    /** @var string Author-side validation that a reference solution passes. */
    public const MODE_VALIDATION = 'validation';

    /** @var string Opaque request identifier used for correlation and idempotency. */
    private string $requestid;

    /** @var string Stable runtime profile id, never a raw shell command. */
    private string $profileid;

    /** @var string One of the MODE_* constants. */
    private string $mode;

    /** @var array Relative path => file contents. */
    private array $files;

    /** @var string Standard input supplied to the program. */
    private string $stdin;

    /** @var array Test payload, kept server side and never echoed to the browser. */
    private array $tests;

    /** @var array Resource limits resolved from the runtime profile and site maximums. */
    private array $limits;

    /** @var string Locale passed to the runtime, for deterministic formatting. */
    private string $locale;

    /**
     * Build a request.
     *
     * @param string $requestid Opaque identifier, also used as the idempotency key.
     * @param string $profileid Stable runtime profile id.
     * @param string $mode One of the MODE_* constants.
     * @param array $files Relative path => contents.
     * @param string $stdin Standard input.
     * @param array $tests Protected test payload.
     * @param array $limits Resolved resource limits.
     * @param string $locale Runtime locale.
     * @throws coding_exception If the mode is unknown or a file path is unsafe.
     */
    public function __construct(
        string $requestid,
        string $profileid,
        string $mode,
        array $files,
        string $stdin = '',
        array $tests = [],
        array $limits = [],
        string $locale = 'C.UTF-8'
    ) {
        if (!in_array($mode, self::modes(), true)) {
            throw new coding_exception('Unknown execution mode: ' . $mode);
        }
        foreach (array_keys($files) as $path) {
            self::validate_path((string) $path);
        }

        $this->requestid = $requestid;
        $this->profileid = $profileid;
        $this->mode = $mode;
        $this->files = $files;
        $this->stdin = $stdin;
        $this->tests = $tests;
        $this->limits = $limits;
        $this->locale = $locale;
    }

    /**
     * All valid modes.
     *
     * @return string[]
     */
    public static function modes(): array {
        return [self::MODE_RUN, self::MODE_CHECK, self::MODE_SUBMIT, self::MODE_VALIDATION];
    }

    /**
     * Reject absolute paths, traversal and other unsafe file names.
     *
     * Runner sandboxes are the last line of defence, not the first. A malformed
     * path is rejected here so it never reaches the provider at all.
     *
     * @param string $path Candidate relative path.
     * @throws coding_exception If the path is not a safe relative path.
     */
    public static function validate_path(string $path): void {
        if ($path === '') {
            throw new coding_exception('Empty file path in execution request.');
        }
        $normalised = str_replace('\\', '/', $path);
        if (strpos($normalised, '..') !== false) {
            throw new coding_exception('Directory traversal rejected in path: ' . $path);
        }
        if (substr($normalised, 0, 1) === '/' || preg_match('/^[A-Za-z]:/', $normalised)) {
            throw new coding_exception('Absolute path rejected: ' . $path);
        }
        if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $normalised)) {
            throw new coding_exception('Unsupported characters in path: ' . $path);
        }
    }

    /**
     * Get the request id.
     *
     * @return string
     */
    public function get_request_id(): string {
        return $this->requestid;
    }

    /**
     * Get the runtime profile id.
     *
     * @return string
     */
    public function get_profile_id(): string {
        return $this->profileid;
    }

    /**
     * Get the execution mode.
     *
     * @return string
     */
    public function get_mode(): string {
        return $this->mode;
    }

    /**
     * Get the submitted files.
     *
     * @return array Relative path => file contents.
     */
    public function get_files(): array {
        return $this->files;
    }

    /**
     * Get standard input.
     *
     * @return string
     */
    public function get_stdin(): string {
        return $this->stdin;
    }

    /**
     * Get the protected test payload.
     *
     * @return array
     */
    public function get_tests(): array {
        return $this->tests;
    }

    /**
     * Get the resolved resource limits.
     *
     * @return array
     */
    public function get_limits(): array {
        return $this->limits;
    }

    /**
     * Get the runtime locale.
     *
     * @return string
     */
    public function get_locale(): string {
        return $this->locale;
    }

    /**
     * Whether this request creates an official graded attempt.
     *
     * @return bool
     */
    public function is_assessed(): bool {
        return $this->mode === self::MODE_SUBMIT;
    }
}
