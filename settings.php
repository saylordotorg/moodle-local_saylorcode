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
 * Site administration entry for the exercise library.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Added outside any fulltree guard so the page stays findable through admin
// search. It carries its own capability, so it is offered only to people who
// can open it.
$ADMIN->add('localplugins', new admin_externalpage(
    'localsaylorcodelibrary',
    get_string('library', 'local_saylorcode'),
    new moodle_url('/local/saylorcode/library.php'),
    'local/saylorcode:viewlibrary'
));
