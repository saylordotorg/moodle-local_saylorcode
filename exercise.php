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
 * Stand alone view of one exercise, referenced by stable id.
 *
 * This is the destination for the full screen control and the non JavaScript
 * fallback link on an embedded exercise, so it must remain reachable and
 * readable even when the embed itself cannot render.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_saylorcode\local\stable_id;

$stableidparam = required_param('stableid', PARAM_ALPHANUMEXT);
$version = optional_param('version', 'latest', PARAM_ALPHANUM);

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url('/local/saylorcode/exercise.php', ['stableid' => $stableidparam, 'version' => $version]);
$PAGE->set_pagelayout('standard');

// Validate before doing anything else. A malformed reference is a content bug
// rather than a server error, so it is reported plainly instead of throwing.
if (!stable_id::is_valid($stableidparam)) {
    $PAGE->set_title(get_string('pluginname', 'local_saylorcode'));
    $PAGE->set_heading(get_string('pluginname', 'local_saylorcode'));

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('exerciseidinvalid', 'local_saylorcode', s($stableidparam)),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

$stableid = stable_id::parse($stableidparam);

$PAGE->set_title(get_string('exercisetitle', 'local_saylorcode', (string) $stableid));
$PAGE->set_heading(get_string('exercisetitle', 'local_saylorcode', (string) $stableid));

echo $OUTPUT->header();

// The central exercise library arrives in a later phase. Until it does, this
// page confirms the reference is well formed and tells the reader plainly that
// the content is not published yet, rather than showing an empty workspace that
// looks broken.
echo $OUTPUT->notification(
    get_string('exercisenotpublished', 'local_saylorcode', (string) $stableid),
    \core\output\notification::NOTIFY_INFO
);

echo $OUTPUT->footer();
