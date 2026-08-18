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
 * Language strings for the Saylor Code Studio service layer.
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['exerciseidinvalid'] = '{$a} is not a valid exercise reference. The expected form is COURSE-Unn-Enn, for example CS101-U05-E03.';
$string['exercisenotpublished'] = 'Exercise {$a} is not published yet. The reference is valid, so this page will show the exercise once it has been authored and approved in the library.';
$string['exercisetitle'] = 'Exercise {$a}';
$string['framesomitted'] = 'Some internal lines were removed from this message.';
$string['healthbadresponse'] = 'The runner answered, but the response could not be understood.';
$string['healthnourl'] = 'No runner address has been configured.';
$string['healthok'] = 'The runner is reachable.';
$string['healthunreachable'] = 'The runner could not be reached (HTTP status {$a->status}, {$a->error}).';
$string['hiddentest'] = 'Hidden test';
$string['outputsuppressed'] = 'Output could not be displayed safely and was withheld.';
$string['pluginname'] = 'Saylor Code Studio';
$string['privacy:metadata:runner'] = 'Student code is sent to a separate execution service so that it can be compiled and run.';
$string['privacy:metadata:runner:files'] = 'The source files the student wrote or edited.';
$string['privacy:metadata:runner:requestid'] = 'A random identifier used to correlate the execution with the attempt. It contains no personal data.';
$string['privacy:metadata:runner:stdin'] = 'Any standard input the student supplied to the program.';
$string['profilejava17'] = 'Java 17 (console)';
$string['saylorcode:managecontent'] = 'Create and edit Saylor Code Studio exercises';
$string['saylorcode:manageruntimes'] = 'Configure Saylor Code Studio runtime profiles';
$string['saylorcode:publishcontent'] = 'Publish a Ready version of a Saylor Code Studio exercise';
$string['saylorcode:reviewcontent'] = 'Review draft Saylor Code Studio content';
$string['saylorcode:viewaudit'] = 'View the Saylor Code Studio audit trail';
$string['saylorcode:viewhealth'] = 'View Saylor Code Studio runner health';
$string['saylorcode:viewlibrary'] = 'View the Saylor Code Studio library';
$string['settings:enablejava'] = 'Enable Java';
$string['settings:enablejava_desc'] = 'Allow exercises to use the Java console runtime profile. Disable this to take Java offline without editing any exercise.';
$string['settings:executionlogretention'] = 'Execution log retention';
$string['settings:executionlogretention_desc'] = 'How long sanitised execution records are kept before scheduled deletion. Student code is held with the attempt, not in these records.';
$string['settings:jobeapikey'] = 'Runner API key';
$string['settings:jobeapikey_desc'] = 'Sent as the X-API-KEY header. Leave empty if the runner does not require a key.';
$string['settings:jobetimeout'] = 'Runner request timeout';
$string['settings:jobetimeout_desc'] = 'How long Moodle waits for the runner before treating the request as unavailable. This is a network timeout and is separate from the execution limits below.';
$string['settings:jobeurl'] = 'Runner base address';
$string['settings:jobeurl_desc'] = 'Base URL of the private Jobe server, for example https://jobe.internal.example.org. This address must not be reachable from the public internet.';
$string['settings:limitsheading'] = 'Resource ceilings';
$string['settings:limitsheading_desc'] = 'Site wide maximums. A runtime profile may impose stricter limits, but it can never exceed the values set here.';
$string['settings:maxconcurrentperuser'] = 'Concurrent executions per user';
$string['settings:maxconcurrentperuser_desc'] = 'How many executions one student may have in flight at once.';
$string['settings:maxcpuseconds'] = 'Maximum CPU seconds';
$string['settings:maxcpuseconds_desc'] = 'Longest a single student program may run.';
$string['settings:maxdiskmb'] = 'Maximum writable disk (MB)';
$string['settings:maxdiskmb_desc'] = 'Ephemeral scratch space available to a job. The space is destroyed after every execution.';
$string['settings:maxmemorymb'] = 'Maximum memory (MB)';
$string['settings:maxmemorymb_desc'] = 'Memory ceiling for a single student program.';
$string['settings:maxoutputbytes'] = 'Maximum output (bytes)';
$string['settings:maxoutputbytes_desc'] = 'Output beyond this size is truncated and the student is told that truncation occurred.';
$string['settings:maxprocesses'] = 'Maximum processes and threads';
$string['settings:maxprocesses_desc'] = 'Ceiling on processes and threads, which is what stops a runaway program from exhausting the runner.';
$string['settings:retentionheading'] = 'Retention';
$string['settings:retentionheading_desc'] = 'How long Saylor Code Studio keeps snapshots and execution records.';
$string['settings:runnerheading'] = 'Execution backend';
$string['settings:runnerheading_desc'] = 'Student code is executed on a separate sandbox service and never on this Moodle server.';
$string['settings:runtimesheading'] = 'Runtime profiles';
$string['settings:runtimesheading_desc'] = 'Which languages authors may select. Exercises reference a profile by name, so a profile can be retired without editing content.';
$string['settings:snapshotsperattempt'] = 'Snapshots kept per attempt';
$string['settings:snapshotsperattempt_desc'] = 'How many automatic snapshots are retained for each attempt before the oldest is discarded.';
