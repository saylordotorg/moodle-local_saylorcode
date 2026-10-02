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

use local_saylorcode\local\runner\execution_request;
use local_saylorcode\local\runner\execution_state;
use local_saylorcode\local\runner\jobe_provider;
use local_saylorcode\local\runtime\profile;
use local_saylorcode\local\runtime\profile_manager;

/**
 * Tests for the shipped runtime profiles.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runtime\profile_manager
 * @covers     \local_saylorcode\local\runtime\profile
 */
final class profile_manager_test extends \advanced_testcase {
    /**
     * Switch every language on.
     */
    private function enable_all(): void {
        foreach (['enablejava', 'enablejavascript', 'enabler', 'enablehtml', 'enablecss'] as $name) {
            set_config($name, 1, 'local_saylorcode');
        }
    }

    /**
     * Each language is offered under its stable id, with the provider language
     * Jobe knows it by and an entry file of the right kind.
     */
    public function test_every_language_is_defined(): void {
        $this->resetAfterTest();
        $this->enable_all();

        $profiles = (new profile_manager())->get_enabled_profiles();

        $expected = [
            profile_manager::PROFILE_JAVA17 => ['java', 'Main.java', false],
            profile_manager::PROFILE_JAVASCRIPT => ['nodejs', 'main.js', false],
            profile_manager::PROFILE_R => ['r', 'main.R', false],
            profile_manager::PROFILE_HTML => ['html', 'index.html', true],
            profile_manager::PROFILE_CSS => ['css', 'style.css', true],
        ];

        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($profiles));
        foreach ($expected as $id => [$language, $entry, $browser]) {
            $this->assertSame($language, $profiles[$id]->get_language_id(), $id);
            $this->assertSame($entry, $profiles[$id]->get_entry_filename(), $id);
            $this->assertSame($browser, $profiles[$id]->runs_in_browser(), $id);
        }
    }

    /**
     * Turning a language off removes it, and the runner languages start off.
     */
    public function test_languages_follow_their_settings(): void {
        $this->resetAfterTest();

        set_config('enablejava', 1, 'local_saylorcode');
        set_config('enablejavascript', 0, 'local_saylorcode');
        set_config('enabler', 0, 'local_saylorcode');
        set_config('enablehtml', 1, 'local_saylorcode');
        set_config('enablecss', 0, 'local_saylorcode');

        $menu = (new profile_manager())->get_menu();

        $this->assertArrayHasKey(profile_manager::PROFILE_JAVA17, $menu);
        $this->assertArrayHasKey(profile_manager::PROFILE_HTML, $menu);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_JAVASCRIPT, $menu);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_R, $menu);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_CSS, $menu);
    }

    /**
     * A menu for graded content leaves out the browser languages, which have
     * no output for a test case to compare.
     */
    public function test_graded_menu_excludes_browser_profiles(): void {
        $this->resetAfterTest();
        $this->enable_all();

        $menu = (new profile_manager())->get_menu(false);

        $this->assertArrayHasKey(profile_manager::PROFILE_JAVASCRIPT, $menu);
        $this->assertArrayHasKey(profile_manager::PROFILE_R, $menu);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_HTML, $menu);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_CSS, $menu);
    }

    /**
     * The site memory maximum tightens a profile but never below its floor.
     *
     * Jobe limits address space, and Node and R cannot start under a ceiling
     * Java is happy with; clamping them there would fail every run.
     */
    public function test_memory_floor_survives_the_site_maximum(): void {
        $profile = new profile('node', 'Node', 'nodejs', 'main.js', 5, 256, 20, 32, 65536, true, profile::MODE_RUNNER, 512);
        $this->assertSame(512, $profile->clamped_to(['memorymb' => 128])->get_memory_mb());

        $java = new profile('java', 'Java', 'java', 'Main.java', 5, 256);
        $this->assertSame(128, $java->clamped_to(['memorymb' => 128])->get_memory_mb());
    }

    /**
     * Clamping keeps a profile's execution mode.
     */
    public function test_clamping_keeps_the_execution_mode(): void {
        $profile = new profile('web', 'Web', 'html', 'index.html', 5, 256, 20, 32, 65536, true, profile::MODE_BROWSER);

        $this->assertTrue($profile->clamped_to(['cpuseconds' => 1])->runs_in_browser());
    }

    /**
     * A browser profile is refused by the runner provider rather than posted.
     */
    public function test_provider_refuses_a_browser_profile(): void {
        $this->resetAfterTest();
        $this->enable_all();

        $provider = new jobe_provider('http://runner.invalid', 'key');
        $request = new execution_request(
            'req-1',
            profile_manager::PROFILE_HTML,
            execution_request::MODE_RUN,
            ['index.html' => '<p>hi</p>']
        );

        $response = $provider->execute($request);

        $this->assertSame(execution_state::INTERNAL_ERROR, $response->get_state());
        $this->assertNotContains(profile_manager::PROFILE_HTML, $provider->get_supported_profiles());
    }
}
