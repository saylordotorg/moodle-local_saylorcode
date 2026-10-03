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
 * Java, JavaScript and R run on the execution runner. HTML and CSS are rendered
 * in the student's browser and never reach it. Profiles are defined in code
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

    /** @var string JavaScript run by Node.js on the runner. */
    public const PROFILE_JAVASCRIPT = 'javascript-node';

    /** @var string R scripts run by Rscript on the runner. */
    public const PROFILE_R = 'r-console';

    /** @var string An HTML page rendered in the browser. */
    public const PROFILE_HTML = 'html-web';

    /** @var string A stylesheet rendered in the browser over an author's page. */
    public const PROFILE_CSS = 'css-web';

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
     * Profiles administrators have enabled and that can actually start.
     *
     * A profile whose interpreter cannot start under the site memory maximum
     * is withheld rather than offered, because every run would fail; the
     * runner status check names it and the ceiling it needs.
     *
     * @return profile[] Keyed by profile id.
     */
    public function get_enabled_profiles(): array {
        return array_filter($this->get_all_profiles(), static function (profile $profile): bool {
            return $profile->is_enabled() && $profile->can_start();
        });
    }

    /**
     * Profiles administrators have enabled that cannot start under the site
     * memory maximum.
     *
     * @return profile[] Keyed by profile id.
     */
    public function get_starved_profiles(): array {
        return array_filter($this->get_all_profiles(), static function (profile $profile): bool {
            return $profile->is_enabled() && !$profile->can_start();
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
     * Anything graded by test cases passes false, because a browser profile
     * produces a page rather than output and has nothing to compare.
     *
     * @param bool $includebrowser Whether to list profiles rendered in the browser.
     * @return array Profile id => display name.
     */
    public function get_menu(bool $includebrowser = true): array {
        $menu = [];
        foreach ($this->get_enabled_profiles() as $profile) {
            if (!$includebrowser && $profile->runs_in_browser()) {
                continue;
            }
            $menu[$profile->get_id()] = $profile->get_display_name();
        }
        return $menu;
    }

    /**
     * Ids of every profile rendered in the browser, enabled or not.
     *
     * For form dependencies, which must still hide a field correctly on an
     * activity whose language has since been switched off.
     *
     * @return string[]
     */
    public function get_browser_profile_ids(): array {
        $ids = [];
        foreach ($this->get_all_profiles() as $profile) {
            if ($profile->runs_in_browser()) {
                $ids[] = $profile->get_id();
            }
        }
        return $ids;
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
        $enabled = static function (string $name): bool {
            return (bool) get_config('local_saylorcode', $name);
        };

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
                $enabled('enablejava')
            ),
            // Node's V8 reserves far more address space than it uses, and Jobe
            // limits address space. Measured on the dev runner (Node 12): it
            // dies in CodeRange setup at 256 MB and runs at 384 MB. It asks for
            // 512, and is withheld when the site maximum leaves it under 384.
            new profile(
                self::PROFILE_JAVASCRIPT,
                get_string('profilejavascript', 'local_saylorcode'),
                'nodejs',
                'main.js',
                5,
                512,
                20,
                32,
                65536,
                $enabled('enablejavascript'),
                profile::MODE_RUNNER,
                384
            ),
            new profile(
                self::PROFILE_R,
                get_string('profiler', 'local_saylorcode'),
                'r',
                'main.R',
                5,
                256,
                20,
                32,
                65536,
                $enabled('enabler')
            ),
            // The limits on a browser profile are never sent anywhere; the
            // student's own browser is what renders the page.
            new profile(
                self::PROFILE_HTML,
                get_string('profilehtml', 'local_saylorcode'),
                'html',
                'index.html',
                5,
                256,
                20,
                32,
                65536,
                $enabled('enablehtml'),
                profile::MODE_BROWSER
            ),
            new profile(
                self::PROFILE_CSS,
                get_string('profilecss', 'local_saylorcode'),
                'css',
                'style.css',
                5,
                256,
                20,
                32,
                65536,
                $enabled('enablecss'),
                profile::MODE_BROWSER
            ),
        ];
    }
}
