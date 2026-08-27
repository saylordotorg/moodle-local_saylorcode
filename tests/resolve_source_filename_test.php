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

use local_saylorcode\local\runtime\profile;

/**
 * Tests for naming the source file after a student's public Java class.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_saylorcode\local\runtime\profile::resolve_source_filename
 */
final class resolve_source_filename_test extends \advanced_testcase {
    /**
     * A Java profile, entry file Main.java.
     *
     * @return profile
     */
    private function java(): profile {
        return new profile('java17-console', 'Java 17', 'java', 'Main.java');
    }

    /**
     * A non-Java profile, to prove the behaviour is Java-only.
     *
     * @return profile
     */
    private function python(): profile {
        return new profile('py3', 'Python 3', 'python3', 'program.py');
    }

    /**
     * A public class not matching the entry file is renamed to match it.
     *
     * This is the reported bug: "write a class named Hello" compiled as
     * Main.java fails javac's filename check.
     */
    public function test_a_public_class_names_the_file(): void {
        $source = 'public class Hello { public static void main(String[] a) {} }';

        $this->assertSame('Hello.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * A public class already matching the entry file is left as it is.
     */
    public function test_a_matching_class_keeps_the_entry_filename(): void {
        $source = 'public class Main { public static void main(String[] a) {} }';

        $this->assertSame('Main.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * Modifiers between public and class do not throw the match off.
     */
    public function test_modifiers_are_handled(): void {
        $this->assertSame(
            'Widget.java',
            $this->java()->resolve_source_filename('public final class Widget {}')
        );
        $this->assertSame(
            'Shape.java',
            $this->java()->resolve_source_filename('public abstract class Shape {}')
        );
    }

    /**
     * A public interface, enum or record governs the filename too.
     */
    public function test_other_public_types_name_the_file(): void {
        $this->assertSame('Greeter.java', $this->java()->resolve_source_filename('public interface Greeter {}'));
        $this->assertSame('Suit.java', $this->java()->resolve_source_filename('public enum Suit { HEARTS }'));
        $this->assertSame('Point.java', $this->java()->resolve_source_filename('public record Point(int x, int y) {}'));
    }

    /**
     * A public type mentioned only in a comment is not mistaken for the class.
     */
    public function test_a_comment_is_not_a_declaration(): void {
        $source = "// this is a public class that greets the world\nclass Greeter { }";

        // No public type, so the entry filename stands.
        $this->assertSame('Main.java', $this->java()->resolve_source_filename($source));

        $blockcomment = "/* public class Fake */\npublic class Real {}";
        $this->assertSame('Real.java', $this->java()->resolve_source_filename($blockcomment));
    }

    /**
     * A leading line comment does not swallow the declaration below it.
     *
     * Starter code very often opens with a header comment, so this is the
     * common case, not an edge one.
     */
    public function test_a_leading_line_comment_does_not_hide_the_class(): void {
        $source = "// Your task: greet the world.\npublic class Hello {\n    // fill this in\n}";

        $this->assertSame('Hello.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * A public declaration inside a string literal is not the class.
     */
    public function test_a_string_literal_is_not_a_declaration(): void {
        $source = 'public class Greeter { void t() { System.out.println("public class Fake {}"); } }';

        // The real public class is Greeter; the string must not win.
        $this->assertSame('Greeter.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * A public inner class does not rename the file.
     *
     * The outer class is package-private, so javac accepts any filename; the
     * public inner class is not a top-level type and must not become the name.
     */
    public function test_a_public_inner_class_does_not_name_the_file(): void {
        $source = "class Outer {\n    public class Inner {}\n}";

        $this->assertSame('Main.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * A public top-level class is still found when a public inner class follows.
     */
    public function test_the_top_level_public_class_wins_over_an_inner_one(): void {
        $source = "public class Program {\n    public class Helper {}\n}";

        $this->assertSame('Program.java', $this->java()->resolve_source_filename($source));
    }

    /**
     * Package-private code has no public type, so the entry filename stands.
     *
     * javac accepts any filename for a class that is not public, so there is
     * nothing to reconcile.
     */
    public function test_no_public_type_keeps_the_entry_filename(): void {
        $this->assertSame('Main.java', $this->java()->resolve_source_filename('class Helper {}'));
        $this->assertSame('Main.java', $this->java()->resolve_source_filename(''));
    }

    /**
     * A non-Java runtime never renames, whatever its source looks like.
     */
    public function test_non_java_keeps_the_entry_filename(): void {
        // Even source that looks like a Java public class does not rename a
        // Python file: the rule is Java's alone.
        $this->assertSame('program.py', $this->python()->resolve_source_filename('public class Hello {}'));
        $this->assertSame('program.py', $this->python()->resolve_source_filename('print("hi")'));
    }
}
