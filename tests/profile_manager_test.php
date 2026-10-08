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
     * Switch every language on, with room for Node to start.
     */
    private function enable_all(): void {
        $names = [
            'enablejava', 'enablepython', 'enablecpp', 'enablerust',
            'enablejavascript', 'enabler', 'enablehtml', 'enablecss',
        ];
        foreach ($names as $name) {
            set_config($name, 1, 'local_saylorcode');
        }
        set_config('maxmemorymb', 1536, 'local_saylorcode');
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
            profile_manager::PROFILE_PYTHON => ['python3', 'main.py', false],
            profile_manager::PROFILE_CPP => ['cpp', 'main.cpp', false],
            profile_manager::PROFILE_RUST => ['rust', 'main.rs', false],
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
     * The site memory maximum tightens every profile, Node included.
     *
     * Site settings may only tighten limits. A profile that needs more to
     * start than the ceiling allows is reported as unable to start, never
     * raised past it.
     */
    public function test_memory_never_exceeds_the_site_maximum(): void {
        $node = new profile('node', 'Node', 'nodejs', 'main.js', 5, 512, 20, 32, 65536, true, profile::MODE_RUNNER, 384);

        $clamped = $node->clamped_to(['memorymb' => 256]);
        $this->assertSame(256, $clamped->get_memory_mb());
        $this->assertFalse($clamped->can_start());

        $roomy = $node->clamped_to(['memorymb' => 1024]);
        $this->assertSame(512, $roomy->get_memory_mb());
        $this->assertTrue($roomy->can_start());
    }

    /**
     * Under a ceiling Node cannot start in, JavaScript is withheld and named
     * for the status check; R and Java carry on at the ceiling.
     */
    public function test_a_starved_profile_is_withheld(): void {
        $this->resetAfterTest();
        $this->enable_all();
        set_config('maxmemorymb', 256, 'local_saylorcode');

        $manager = new profile_manager();
        $enabled = $manager->get_enabled_profiles();

        $this->assertArrayNotHasKey(profile_manager::PROFILE_JAVASCRIPT, $enabled);
        $this->assertArrayNotHasKey(profile_manager::PROFILE_JAVASCRIPT, $manager->get_menu());
        $this->assertNull($manager->get_profile(profile_manager::PROFILE_JAVASCRIPT));
        $this->assertSame([profile_manager::PROFILE_JAVASCRIPT], array_keys($manager->get_starved_profiles()));

        $this->assertSame(256, $enabled[profile_manager::PROFILE_R]->get_memory_mb());

        // With room, R gets the 512 MB that plots need.
        set_config('maxmemorymb', 1536, 'local_saylorcode');
        $this->assertSame(512, (new profile_manager())->get_profile(profile_manager::PROFILE_R)->get_memory_mb());
        $this->assertSame(256, $enabled[profile_manager::PROFILE_JAVA17]->get_memory_mb());
    }

    /**
     * Node 24 needs 1200 MB of address space: withheld just below, offered at
     * it, and asking for 1536 when the ceiling allows.
     */
    public function test_javascript_starts_at_its_minimum(): void {
        $this->resetAfterTest();
        $this->enable_all();

        set_config('maxmemorymb', 1199, 'local_saylorcode');
        $this->assertNull((new profile_manager())->get_profile(profile_manager::PROFILE_JAVASCRIPT));

        set_config('maxmemorymb', 1200, 'local_saylorcode');
        $this->assertSame(1200, (new profile_manager())->get_profile(profile_manager::PROFILE_JAVASCRIPT)->get_memory_mb());

        set_config('maxmemorymb', 4096, 'local_saylorcode');
        $this->assertSame(1536, (new profile_manager())->get_profile(profile_manager::PROFILE_JAVASCRIPT)->get_memory_mb());
    }

    /**
     * The compiled languages carry their own compiler arguments to the runner:
     * warnings on, never turned into errors. The others send none and keep the
     * runner's defaults.
     */
    public function test_compiler_arguments_reach_the_runner(): void {
        $this->resetAfterTest();
        $this->enable_all();

        $provider = new class ('http://runner.invalid', 'key') extends jobe_provider {
            /**
             * Expose the payload.
             *
             * @param execution_request $request The request.
             * @param profile $profile The profile.
             * @return array
             */
            public function payload(execution_request $request, profile $profile): array {
                return $this->build_payload($request, $profile);
            }
        };
        $manager = new profile_manager();
        $parameters = function (string $id, string $file) use ($provider, $manager): array {
            $request = new execution_request('req', $id, execution_request::MODE_RUN, [$file => 'x']);
            return $provider->payload($request, $manager->get_profile($id))['run_spec']['parameters'];
        };

        $cpp = $parameters(profile_manager::PROFILE_CPP, 'main.cpp')['compileargs'];
        $this->assertContains('-Wall', $cpp);
        $this->assertNotContains('-Werror', $cpp);
        $rust = $parameters(profile_manager::PROFILE_RUST, 'main.rs')['compileargs'];
        $this->assertContains('2021', $rust);
        $this->assertNotContains('warnings', $rust);
        $this->assertArrayNotHasKey('compileargs', $parameters(profile_manager::PROFILE_JAVA17, 'Main.java'));
        $this->assertArrayNotHasKey('compileargs', $parameters(profile_manager::PROFILE_PYTHON, 'main.py'));

        // JavaScript caps its heap, and only JavaScript sends interpreter arguments.
        $javascript = $parameters(profile_manager::PROFILE_JAVASCRIPT, 'main.js');
        $this->assertSame(['--max-old-space-size=256'], $javascript['interpreterargs']);
        $this->assertArrayNotHasKey('interpreterargs', $parameters(profile_manager::PROFILE_PYTHON, 'main.py'));
        $this->assertArrayNotHasKey('interpreterargs', $parameters(profile_manager::PROFILE_R, 'main.R'));
    }

    /**
     * Clamping keeps a profile's compiler arguments.
     */
    public function test_clamping_keeps_compiler_arguments(): void {
        $profile = new profile('c', 'C', 'cpp', 'main.cpp', 5, 256, 20, 32, 65536, true, profile::MODE_RUNNER, 0, ['-std=c++17']);

        $this->assertSame(['-std=c++17'], $profile->clamped_to(['cpuseconds' => 1])->get_compile_args());
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
