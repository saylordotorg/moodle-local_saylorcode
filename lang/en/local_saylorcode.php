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

$string['check:runner'] = 'Saylor Code Studio runner';
$string['check:runneraction'] = 'Runner settings';
$string['check:runnerok'] = 'The runner answered in {$a->latency}s and offers {$a->profiles} language profiles.';
$string['editdraft'] = 'Edit the draft';
$string['exerciseactions'] = 'Actions';
$string['exerciseadd'] = 'Add an exercise';
$string['exerciseaddcases'] = 'Add {no} more test cases';
$string['exerciseaddhints'] = 'Add {no} more hints';
$string['exercisecaseexpected'] = 'A named test case needs expected output. Clear the name to drop the row instead.';
$string['exercisecaseunnamed'] = 'Case {$a}';
$string['exercisecaseweight'] = 'A weight must be greater than zero, or the case cannot affect the score.';
$string['exerciseedit'] = 'Edit the exercise';
$string['exerciseempty'] = 'An exercise needs starter code or test cases before it can be published.';
$string['exerciseentryfile'] = 'File the student edits';
$string['exerciseexists'] = 'An exercise with the reference {$a} already exists.';
$string['exercisehint'] = 'Hint';
$string['exercisehints'] = 'Hints, as a JSON array';
$string['exercisehints_help'] = 'Hints are offered one at a time in this order. Write the first as the smallest nudge that might help and the last as the largest. What a student takes is recorded and shown to their teacher.';
$string['exerciseidinvalid'] = '{$a} is not a valid exercise reference. The expected form is COURSE-Unn-Enn, for example CS101-U05-E03.';
$string['exercisejsoninvalid'] = 'This must be a JSON array, or empty.';
$string['exercisename'] = 'Name';
$string['exercisenotpublished'] = 'Exercise {$a} is not published yet. The reference is valid, so this page will show the exercise once it has been authored and approved in the library.';
$string['exercisepublished'] = 'Published as version {$a}.';
$string['exercisesaved'] = 'Draft saved.';
$string['exercisesolution'] = 'Reference solution';
$string['exercisestableid'] = 'Reference';
$string['exercisestableid_help'] = 'The identifier activities use to find this exercise, such as CS101-U01-E01. It cannot be changed once the exercise exists, because every activity pointing at it would be orphaned.';
$string['exercisestarter'] = 'Starter code';
$string['exercisesummary'] = 'Summary';
$string['exercisetestcases'] = 'Test cases, as a JSON array';
$string['exercisetestcases_help'] = 'Each entry needs at least a name and an expected value. The structured editor on the activity form writes the same shape; this field is the library\'s plain equivalent until that editor moves here.';
$string['exercisetitle'] = 'Exercise {$a}';
$string['exerciseunpublished'] = 'No published version';
$string['exerciseversionn'] = 'Version {$a}';
$string['exerciseversionshead'] = 'Published';
$string['framesomitted'] = 'Some internal lines were removed from this message.';
$string['healthbadresponse'] = 'The runner answered, but the response could not be understood.';
$string['healthnourl'] = 'No runner address has been configured.';
$string['healthok'] = 'The runner is reachable.';
$string['healthunreachable'] = 'The runner could not be reached (HTTP status {$a->status}, {$a->error}).';
$string['hiddentest'] = 'Hidden test';
$string['library'] = 'Exercise library';
$string['libraryintro'] = 'Exercises live here, apart from the activities that use them, so the same exercise can appear in a book, a lesson step and a quiz without being written three times. Publishing an exercise fixes a version that activities can pin to; the draft stays editable afterwards.';
$string['librarynone'] = 'No exercises yet. Activities that carry their own starter code and tests keep working exactly as they do now; the library is for exercises you want to share or version.';
$string['outputsuppressed'] = 'Output could not be displayed safely and was withheld.';
$string['pluginname'] = 'Saylor Code Studio';
$string['privacy:metadata:exercises'] = 'Exercises held in the shared library. The content is instructional material rather than personal data; what is recorded about a person is who last edited it.';
$string['privacy:metadata:exercises:timemodified'] = 'When the exercise was last edited.';
$string['privacy:metadata:exercises:usermodified'] = 'The author who last edited the exercise.';
$string['privacy:metadata:runner'] = 'Student code is sent to a separate execution service so that it can be compiled and run.';
$string['privacy:metadata:runner:files'] = 'The source files the student wrote or edited.';
$string['privacy:metadata:runner:requestid'] = 'A random identifier used to correlate the execution with the attempt. It contains no personal data.';
$string['privacy:metadata:runner:stdin'] = 'Any standard input the student supplied to the program.';
$string['privacy:metadata:versions'] = 'Published versions of library exercises. As with exercises, the personal element is the authorship rather than the content.';
$string['privacy:metadata:versions:timecreated'] = 'When the version was published.';
$string['privacy:metadata:versions:usermodified'] = 'The author who published the version.';
$string['privacy:path:library'] = 'Exercise library';
$string['profileid'] = 'Language';
$string['profilejava17'] = 'Java 17 (console)';
$string['publish'] = 'Publish';
$string['publishconfirm'] = 'Publish the draft as a new version? Published versions cannot be changed afterwards, which is what lets graded activities pin to one.';
$string['publishedwithwarning'] = 'Published as version {$a->version}, but its correctness is not confirmed: {$a->detail}. Students are graded against this version, so fix the draft and publish again.';
$string['publishexercise'] = 'Publish exercises';
$string['publishfaileddetail'] = ' ({$a->cases})';
$string['saylorcode:managecontent'] = 'Create and edit Saylor Code Studio exercises';
$string['saylorcode:manageruntimes'] = 'Configure Saylor Code Studio runtime profiles';
$string['saylorcode:publishcontent'] = 'Publish a Ready version of a Saylor Code Studio exercise';
$string['saylorcode:publishexercise'] = 'Publish a version of an exercise';
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
$string['settings:maxconcurrentsite'] = 'Concurrent executions site wide';
$string['settings:maxconcurrentsite_desc'] = 'How many executions the whole site may have in flight at once, across all students. Zero means no limit, which is the default: a ceiling set below what the runner can actually take would turn away work it could have handled. Size it against the runner, not against the number of students. Students refused by this limit are told the site is busy and asked to try again, rather than being told they are doing too much.';
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
$string['tcexpected'] = 'Expected output';
$string['tcfeedback'] = 'Feedback when it fails';
$string['tcname'] = 'Test name';
$string['tcpublic'] = 'Students may see this case';
$string['tcstdin'] = 'Standard input';
$string['tcweight'] = 'Weight';
$string['validatecompileerror'] = 'the reference solution did not compile';
$string['validatefailed'] = 'the reference solution did not pass every test';
$string['validatenocases'] = 'the exercise defines no test cases';
$string['validatenosolution'] = 'the exercise has no reference solution';
$string['validatepassed'] = 'the reference solution passed every test';
$string['validaterunnerdown'] = 'the execution runner was unavailable';
