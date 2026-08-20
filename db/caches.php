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
 * Cache definitions for the Saylor Code Studio service layer.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // Tracks how many executions each user has in flight. Deliberately short
    // lived and non persistent: losing it costs at most a briefly relaxed
    // limit, whereas persisting it would mean a write on every Run.
    'executiongate' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'ttl' => 300,
    ],

    // The site wide counter. Separate from the per user one because every
    // request on the site writes this single key, which needs different
    // handling: it locks on write, and static acceleration is off so a request
    // cannot release against a copy it read before other requests moved it.
    'executiongatesite' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => false,
        // The older requirelockingwrite is deprecated in Moodle 4.5 and does
        // nothing but emit a debugging notice. This one is enforced: writing
        // without holding the lock raises a coding exception.
        'requirelockingbeforewrite' => true,
        'ttl' => 300,
    ],
];
