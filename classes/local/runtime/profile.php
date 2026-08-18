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
