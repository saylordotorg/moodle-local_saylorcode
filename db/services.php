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

/**
 * Web service definitions for local_saylorcode.
 *
 * Declared ajax => true so the library authoring form can call it from the
 * page, and deliberately not added to any published service: it assumes a
 * logged in author in the system context and is not meant for an external
 * token.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_saylorcode_validate_exercise' => [
        'classname' => 'local_saylorcode\external\validate_exercise',
        'description' => 'Run a library exercise\'s reference solution against its test cases.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/saylorcode:publishexercise',
    ],
];
