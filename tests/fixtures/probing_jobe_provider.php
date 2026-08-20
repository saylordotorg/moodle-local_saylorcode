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

use local_saylorcode\local\runner\jobe_provider;

/**
 * A provider whose transport is a recording stub.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class probing_jobe_provider extends jobe_provider {
    /** @var recording_curl The stub handed to the last request. */
    public $lastcurl;

    /** @var int The status the stub should answer with. */
    public $status = 200;

    /**
     * Hand out the recording stub instead of a real transport.
     *
     * @return \curl
     */
    protected function new_curl(): \curl {
        $curl = new recording_curl();
        $curl->status = $this->status;
        $this->lastcurl = $curl;

        return $curl;
    }
}
