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

namespace local_saylorcode\local\runtime;

/**
 * An immutable runtime profile.
 *
 * Exercises reference a profile by its stable id. They never carry a compiler
 * command, image name or interpreter path, which is what allows the execution
 * backend to be replaced or upgraded without touching content (specification
 * sections 5.9 and 13.8).
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile {
    /** @var string Stable profile id referenced by exercise content. */
    private string $id;

    /** @var string Student facing language name. */
    private string $displayname;

    /** @var string Provider language identifier, for example 'java'. */
    private string $languageid;

    /** @var string Default entry point filename. */
    private string $entryfilename;

    /** @var int CPU seconds allowed for the program. */
    private int $cpuseconds;

    /** @var int Memory ceiling in megabytes. */
    private int $memorymb;

    /** @var int Ephemeral writable disk in megabytes. */
    private int $diskmb;

    /** @var int Maximum processes and threads. */
    private int $maxprocesses;

    /** @var int Output ceiling in bytes. */
    private int $outputlimitbytes;

    /** @var bool Whether administrators have enabled this profile. */
    private bool $enabled;

    /**
     * Build a profile.
     *
     * @param string $id Stable profile id.
     * @param string $displayname Student facing language name.
     * @param string $languageid Provider language identifier.
     * @param string $entryfilename Default entry point filename.
     * @param int $cpuseconds CPU seconds.
     * @param int $memorymb Memory megabytes.
     * @param int $diskmb Disk megabytes.
     * @param int $maxprocesses Process and thread ceiling.
     * @param int $outputlimitbytes Output ceiling in bytes.
     * @param bool $enabled Whether the profile is enabled.
     */
    public function __construct(
        string $id,
        string $displayname,
        string $languageid,
        string $entryfilename,
        int $cpuseconds = 5,
        int $memorymb = 256,
        int $diskmb = 20,
        int $maxprocesses = 32,
        int $outputlimitbytes = 65536,
        bool $enabled = true
    ) {
        $this->id = $id;
        $this->displayname = $displayname;
        $this->languageid = $languageid;
        $this->entryfilename = $entryfilename;
        $this->cpuseconds = $cpuseconds;
        $this->memorymb = $memorymb;
        $this->diskmb = $diskmb;
        $this->maxprocesses = $maxprocesses;
        $this->outputlimitbytes = $outputlimitbytes;
        $this->enabled = $enabled;
    }

    /**
     * Stable profile id.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Student facing language name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return $this->displayname;
    }

    /**
     * Provider language identifier.
     *
     * @return string
     */
    public function get_language_id(): string {
        return $this->languageid;
    }

    /**
     * Default entry point filename.
     *
     * @return string
     */
    public function get_entry_filename(): string {
        return $this->entryfilename;
    }

    /**
     * The filename the source should be compiled under.
     *
     * Usually the entry filename, but Java refuses to compile a public type
     * unless the file is named after it, and an exercise routinely asks a
     * student to write a class of a given name -- "write a class named Hello".
     * Compiling that as Main.java fails with an error about the filename rather
     * than anything about the student's code, which teaches nothing. Naming the
     * file after the public type is exactly what javac expects, and the launcher
     * then runs the class of the same name. This was confirmed against the
     * runner: Main.java holding "public class Hello" fails to compile; Hello.java
     * holding the same source runs.
     *
     * Only Java needs this; every other runtime compiles or interprets a file
     * whatever it is called, so their entry filename stands.
     *
     * @param string $sourcecode The entry file's contents.
     * @return string
     */
    public function resolve_source_filename(string $sourcecode): string {
        if ($this->languageid !== 'java') {
            return $this->entryfilename;
        }

        $classname = self::public_type_name($sourcecode);
        if ($classname === null) {
            // No public top-level type: javac accepts any filename, so there is
            // nothing to reconcile and the entry filename stands.
            return $this->entryfilename;
        }

        $extension = pathinfo($this->entryfilename, PATHINFO_EXTENSION);

        return $classname . ($extension !== '' ? '.' . $extension : '.java');
    }

    /**
     * The name of the first public top-level type in Java source, if any.
     *
     * Comments are stripped first, so a description like "// a public class that
     * greets" cannot be mistaken for the declaration. A string literal holding
     * the same words is left, but that is vanishingly unlikely in the small
     * programs this runs and not worth a full parser to rule out. Valid Java has
     * at most one public top-level type, so the first match is the governing one.
     *
     * @param string $sourcecode The source.
     * @return string|null The type name, or null when there is no public type.
     */
    protected static function public_type_name(string $sourcecode): ?string {
        $stripped = preg_replace('~//.*|/\*.*?\*/~s', '', $sourcecode);

        $pattern = '~\bpublic\s+'
            . '(?:(?:final|abstract|sealed|non-sealed|strictfp)\s+)*'
            . '(?:class|interface|enum|record)\s+'
            . '([A-Za-z_$][A-Za-z0-9_$]*)~';

        if (preg_match($pattern, (string) $stripped, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * CPU seconds allowed.
     *
     * @return int
     */
    public function get_cpu_seconds(): int {
        return $this->cpuseconds;
    }

    /**
     * Memory ceiling in megabytes.
     *
     * @return int
     */
    public function get_memory_mb(): int {
        return $this->memorymb;
    }

    /**
     * Disk ceiling in megabytes.
     *
     * @return int
     */
    public function get_disk_mb(): int {
        return $this->diskmb;
    }

    /**
     * Process and thread ceiling.
     *
     * @return int
     */
    public function get_max_processes(): int {
        return $this->maxprocesses;
    }

    /**
     * Output ceiling in bytes.
     *
     * @return int
     */
    public function get_output_limit_bytes(): int {
        return $this->outputlimitbytes;
    }

    /**
     * Whether the profile is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Return a copy with limits clamped to the supplied site maximums.
     *
     * Authors may request stricter limits than the site default but must never
     * be able to raise them (specification section 13.7).
     *
     * @param array $maximums Keyed by cpuseconds, memorymb, diskmb, maxprocesses, outputlimitbytes.
     * @return self
     */
    public function clamped_to(array $maximums): self {
        return new self(
            $this->id,
            $this->displayname,
            $this->languageid,
            $this->entryfilename,
            min($this->cpuseconds, (int) ($maximums['cpuseconds'] ?? $this->cpuseconds)),
            min($this->memorymb, (int) ($maximums['memorymb'] ?? $this->memorymb)),
            min($this->diskmb, (int) ($maximums['diskmb'] ?? $this->diskmb)),
            min($this->maxprocesses, (int) ($maximums['maxprocesses'] ?? $this->maxprocesses)),
            min($this->outputlimitbytes, (int) ($maximums['outputlimitbytes'] ?? $this->outputlimitbytes)),
            $this->enabled
        );
    }
}
