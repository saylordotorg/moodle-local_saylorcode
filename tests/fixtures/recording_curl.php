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

/**
 * A curl that records what it was asked to send and answers from a script.
 *
 * The point of the tests using this is what goes out, not what comes back:
 * whether the API key header is on the request. Reaching a real runner would
 * make that unobservable and the test dependent on a network.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_curl extends \curl {
    /** @var array The options passed to the last request. */
    public $options = [];

    /** @var string The URL of the last request. */
    public $url = '';

    /** @var int The status to answer with. */
    public $status = 200;

    /** @var string The body to answer with. */
    public $body = '[["java","17"],["python3","3.9"]]';

    /**
     * Record the request and answer from the script.
     *
     * @param string $url The URL.
     * @param array $params Query parameters.
     * @param array $options Curl options.
     * @return string
     */
    public function get($url, $params = [], $options = []) {
        $this->url = $url;
        $this->options = $options;

        return $this->body;
    }

    /**
     * The scripted response metadata.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => $this->status];
    }

    /**
     * No transport error, since the response is scripted.
     *
     * @return int
     */
    public function get_errno() {
        return 0;
    }
}
