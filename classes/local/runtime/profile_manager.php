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
 * Supplies the runtime profiles available on this site.
 *
 * Phase 1 ships a single Java profile for CS101. Profiles are defined in code
 * rather than in the database so that a misconfigured row cannot widen a
 * resource limit; site settings may only tighten them.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_manager {
    /** @var string The Java profile shipped for the CS101 pilot. */
    public const PROFILE_JAVA17 = 'java17-console';

    /** @var profile[]|null Lazily built profile cache, keyed by id. */
    protected ?array $profiles = null;

    /**
     * All profiles known to this site, enabled or not.
     *
     * @return profile[] Keyed by profile id.
     */
    public function get_all_profiles(): array {
        if ($this->profiles !== null) {
            return $this->profiles;
        }

        $maximums = $this->get_site_maximums();
        $definitions = $this->get_definitions();

        $this->profiles = [];
        foreach ($definitions as $definition) {
            $this->profiles[$definition->get_id()] = $definition->clamped_to($maximums);
        }

        return $this->profiles;
    }

    /**
     * Profiles administrators have enabled.
     *
     * @return profile[] Keyed by profile id.
     */
    public function get_enabled_profiles(): array {
        return array_filter($this->get_all_profiles(), static function (profile $profile): bool {
            return $profile->is_enabled();
        });
    }

    /**
     * Look up one profile.
     *
     * @param string $id Stable profile id.
     * @return profile|null Null when the id is unknown or disabled.
     */
    public function get_profile(string $id): ?profile {
        $enabled = $this->get_enabled_profiles();
        return $enabled[$id] ?? null;
    }

    /**
     * Menu of enabled profiles for a settings or authoring form.
     *
     * @return array Profile id => display name.
     */
    public function get_menu(): array {
        $menu = [];
        foreach ($this->get_enabled_profiles() as $profile) {
            $menu[$profile->get_id()] = $profile->get_display_name();
        }
        return $menu;
    }

    /**
     * Site wide ceilings that no profile may exceed.
     *
     * @return array
     */
    protected function get_site_maximums(): array {
        return [
            'cpuseconds' => (int) (get_config('local_saylorcode', 'maxcpuseconds') ?: 5),
            'memorymb' => (int) (get_config('local_saylorcode', 'maxmemorymb') ?: 256),
            'diskmb' => (int) (get_config('local_saylorcode', 'maxdiskmb') ?: 20),
            'maxprocesses' => (int) (get_config('local_saylorcode', 'maxprocesses') ?: 32),
            'outputlimitbytes' => (int) (get_config('local_saylorcode', 'maxoutputbytes') ?: 65536),
        ];
    }

    /**
     * The shipped profile definitions.
     *
     * @return profile[]
     */
    protected function get_definitions(): array {
        $javaenabled = (bool) get_config('local_saylorcode', 'enablejava');

        return [
            new profile(
                self::PROFILE_JAVA17,
                get_string('profilejava17', 'local_saylorcode'),
                'java',
                'Main.java',
                5,
                256,
                20,
                32,
                65536,
                $javaenabled
            ),
        ];
    }
}
