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

namespace local_saylorcode\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy implementation for the Saylor Code Studio service layer.
 *
 * This plugin stores no user data of its own. Attempts, snapshots and grades
 * belong to mod_saylorcode. What this plugin does do is transmit student source
 * code to a separate execution service, and that transmission has to be
 * declared even though nothing is retained here.
 *
 * The request is deliberately built so that no name, email address, user id or
 * grade travels with it; only the code, any standard input, and a random
 * correlation token.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {

    /**
     * Describe what leaves Moodle and why.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'saylorcode_runner',
            [
                'files' => 'privacy:metadata:runner:files',
                'stdin' => 'privacy:metadata:runner:stdin',
                'requestid' => 'privacy:metadata:runner:requestid',
            ],
            'privacy:metadata:runner'
        );

        return $collection;
    }
}
