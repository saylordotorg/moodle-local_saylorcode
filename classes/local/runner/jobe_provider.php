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

namespace local_saylorcode\local\runner;

use curl;
use local_saylorcode\local\runtime\profile;
use local_saylorcode\local\runtime\profile_manager;

/**
 * Runner provider backed by a privately hosted Jobe server.
 *
 * Jobe exposes a small REST interface and performs the actual sandboxing. This
 * class is responsible for translating between our provider contract and the
 * Jobe wire format, and for making sure a Jobe failure degrades into a state
 * rather than an exception, so that a runner outage never breaks a course page
 * (specification section 19.1).
 *
 * @package    local_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobe_provider implements provider_interface {
    /** @var int Jobe reported a compilation failure. */
    private const JOBE_COMPILATION_ERROR = 11;

    /** @var int Jobe reported a runtime failure. */
    private const JOBE_RUNTIME_ERROR = 12;

    /** @var int Jobe hit the CPU or wall clock limit. */
    private const JOBE_TIME_LIMIT = 13;

    /** @var int Jobe ran the program to completion. */
    private const JOBE_SUCCESS = 15;

    /** @var int Jobe hit the memory limit. */
    private const JOBE_MEMORY_LIMIT = 17;

    /** @var int The program attempted a forbidden system call. */
    private const JOBE_ILLEGAL_SYSCALL = 19;

    /** @var int Jobe itself failed. */
    private const JOBE_INTERNAL_ERROR = 20;

    /** @var int Jobe refused the job because it is saturated. */
    private const JOBE_SERVER_OVERLOAD = 21;

    /** @var string Base URL of the Jobe server, without a trailing slash. */
    protected string $baseurl;

    /** @var string API key sent in the X-API-KEY header, empty when not required. */
    protected string $apikey;

    /** @var int Socket timeout in seconds for a single request. */
    protected int $timeout;

    /** @var profile_manager Supplies runtime profile definitions. */
    protected profile_manager $profiles;

    /**
     * Build the provider.
     *
     * @param string $baseurl Base URL of the Jobe server.
     * @param string $apikey API key, or an empty string when the server does not require one.
     * @param int $timeout Socket timeout in seconds.
     * @param profile_manager|null $profiles Profile manager, defaulting to the site manager.
     */
    public function __construct(string $baseurl, string $apikey = '', int $timeout = 30, ?profile_manager $profiles = null) {
        $this->baseurl = rtrim($baseurl, '/');
        $this->apikey = $apikey;
        $this->timeout = $timeout;
        $this->profiles = $profiles ?? new profile_manager();
    }

    /**
     * Build a provider from site configuration.
     *
     * @return self
     */
    public static function create_from_config(): self {
        $baseurl = (string) get_config('local_saylorcode', 'jobeurl');
        $apikey = (string) get_config('local_saylorcode', 'jobeapikey');
        $timeout = (int) get_config('local_saylorcode', 'jobetimeout');

        return new self($baseurl, $apikey, $timeout > 0 ? $timeout : 30);
    }

    /**
     * Short provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'jobe';
    }

    /**
     * Whether Jobe supports cancelling an in flight job.
     *
     * Jobe runs jobs synchronously and exposes no cancellation endpoint.
     *
     * @return bool
     */
    public function supports_cancellation(): bool {
        return false;
    }

    /**
     * Cancellation is not available on this backend.
     *
     * @param string $requestid Ignored.
     * @return bool Always false.
     */
    public function cancel(string $requestid): bool {
        return false;
    }

    /**
     * Probe the Jobe server.
     *
     * @return health_result
     */
    public function get_health(): health_result {
        if ($this->baseurl === '') {
            return new health_result(false, get_string('healthnourl', 'local_saylorcode'));
        }

        $started = microtime(true);
        $curl = $this->new_curl();

        // With the same headers as an execution. A runner configured the way
        // the provisioning documentation requires -- api_keys_required = TRUE,
        // unauthenticated requests rejected -- answers an unauthenticated probe
        // with a 401, so a perfectly healthy runner reported as unreachable
        // while student code ran fine.
        $response = $curl->get(
            $this->baseurl . '/jobe/index.php/restapi/languages',
            [],
            ['CURLOPT_HTTPHEADER' => $this->headers()]
        );
        $latency = microtime(true) - $started;
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno() || $status !== 200) {
            $detail = get_string('healthunreachable', 'local_saylorcode', [
                'status' => $status,
                'error' => $curl->error !== '' ? $curl->error : '-',
            ]);
            return new health_result(false, $detail, $latency);
        }

        $languages = json_decode((string) $response, true);
        if (!is_array($languages)) {
            return new health_result(false, get_string('healthbadresponse', 'local_saylorcode'), $latency);
        }

        $available = [];
        foreach ($languages as $entry) {
            if (is_array($entry) && isset($entry[0])) {
                $available[] = (string) $entry[0];
            }
        }

        return new health_result(true, get_string('healthok', 'local_saylorcode'), $latency, $available);
    }

    /**
     * Runtime profiles this provider can service.
     *
     * @return string[]
     */
    public function get_supported_profiles(): array {
        $enabled = [];
        foreach ($this->profiles->get_enabled_profiles() as $profile) {
            $enabled[] = $profile->get_id();
        }
        return $enabled;
    }

    /**
     * Execute a request against Jobe.
     *
     * @param execution_request $request The work to perform.
     * @return execution_response
     */
    public function execute(execution_request $request): execution_response {
        $profile = $this->profiles->get_profile($request->get_profile_id());
        if ($profile === null) {
            return new execution_response(
                $request->get_request_id(),
                execution_state::INTERNAL_ERROR,
                '',
                '',
                '',
                [],
                null,
                0.0,
                0.0,
                false,
                'unknown_profile'
            );
        }

        if ($this->baseurl === '') {
            return execution_response::unavailable($request->get_request_id(), 'jobe_not_configured');
        }

        $payload = $this->build_payload($request, $profile);
        $started = microtime(true);

        $curl = $this->new_curl();
        $raw = $curl->post(
            $this->baseurl . '/jobe/index.php/restapi/runs',
            json_encode($payload),
            ['CURLOPT_HTTPHEADER' => $this->headers()]
        );
        $elapsed = microtime(true) - $started;

        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno() || $status === 0) {
            return execution_response::unavailable($request->get_request_id(), 'transport_error');
        }
        if ($status === 202 || $status === 503) {
            // Jobe reports saturation with 202 Accepted or 503; both mean try later.
            return execution_response::unavailable($request->get_request_id(), 'runner_saturated');
        }
        if ($status !== 200) {
            return execution_response::unavailable($request->get_request_id(), 'http_' . $status);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return new execution_response(
                $request->get_request_id(),
                execution_state::INTERNAL_ERROR,
                '',
                '',
                '',
                [],
                null,
                0.0,
                $elapsed,
                false,
                'malformed_response'
            );
        }

        return $this->build_response($request, $profile, $decoded, $elapsed);
    }

    /**
     * Translate a decoded Jobe result into our response object.
     *
     * @param execution_request $request Originating request.
     * @param profile $profile Resolved runtime profile.
     * @param array $decoded Decoded Jobe JSON.
     * @param float $elapsed Measured round trip seconds.
     * @return execution_response
     */
    protected function build_response(
        execution_request $request,
        profile $profile,
        array $decoded,
        float $elapsed
    ): execution_response {
        $outcome = (int) ($decoded['outcome'] ?? self::JOBE_INTERNAL_ERROR);
        $stdout = (string) ($decoded['stdout'] ?? '');
        $stderr = (string) ($decoded['stderr'] ?? '');
        $cmpinfo = (string) ($decoded['cmpinfo'] ?? '');

        $truncated = false;
        $limit = $profile->get_output_limit_bytes();
        $stdout = output_sanitiser::truncate($stdout, $limit, $truncated);

        $state = $this->map_outcome($outcome, $truncated);

        return new execution_response(
            $request->get_request_id(),
            $state,
            $stdout,
            $stderr,
            $cmpinfo,
            [],
            isset($decoded['exitcode']) ? (int) $decoded['exitcode'] : null,
            0.0,
            $elapsed,
            $truncated,
            'jobe_outcome_' . $outcome
        );
    }

    /**
     * Map a Jobe outcome code to a canonical execution state.
     *
     * @param int $outcome Jobe outcome code.
     * @param bool $truncated Whether we truncated the output ourselves.
     * @return string One of the execution_state constants.
     */
    protected function map_outcome(int $outcome, bool $truncated): string {
        if ($truncated) {
            return execution_state::OUTPUT_LIMIT;
        }

        switch ($outcome) {
            case self::JOBE_SUCCESS:
                return execution_state::COMPLETED;
            case self::JOBE_COMPILATION_ERROR:
                return execution_state::COMPILE_ERROR;
            case self::JOBE_RUNTIME_ERROR:
                return execution_state::RUNTIME_ERROR;
            case self::JOBE_TIME_LIMIT:
                return execution_state::TIMEOUT;
            case self::JOBE_MEMORY_LIMIT:
                return execution_state::MEMORY_LIMIT;
            case self::JOBE_ILLEGAL_SYSCALL:
                // A blocked syscall is the sandbox working as intended, and is
                // reported to the student as a restriction rather than a crash.
                return execution_state::PROCESS_LIMIT;
            case self::JOBE_SERVER_OVERLOAD:
                return execution_state::RUNNER_UNAVAILABLE;
            case self::JOBE_INTERNAL_ERROR:
            default:
                return execution_state::INTERNAL_ERROR;
        }
    }

    /**
     * Build the Jobe run_spec payload.
     *
     * @param execution_request $request Originating request.
     * @param profile $profile Resolved runtime profile.
     * @return array
     */
    protected function build_payload(execution_request $request, profile $profile): array {
        $files = $request->get_files();
        $entry = $profile->get_entry_filename();

        // Jobe takes one source file plus an attached file list. The entry point
        // is sent as the source and every other file travels as an attachment.
        $sourcecode = $files[$entry] ?? reset($files);
        if ($sourcecode === false) {
            $sourcecode = '';
        }

        // The filename Jobe compiles under is not always the entry filename:
        // Java needs the file named after the student's public class, or javac
        // refuses it. The profile decides, since the rule is language specific.
        $sourcefilename = $profile->resolve_source_filename((string) $sourcecode);

        return [
            'run_spec' => [
                'language_id' => $profile->get_language_id(),
                'sourcefilename' => $sourcefilename,
                'sourcecode' => $sourcecode,
                'input' => $request->get_stdin(),
                'parameters' => [
                    'cputime' => $profile->get_cpu_seconds(),
                    'memorylimit' => $profile->get_memory_mb(),
                    'disklimit' => $profile->get_disk_mb(),
                    'numprocs' => $profile->get_max_processes(),
                ],
            ],
        ];
    }

    /**
     * HTTP headers for a Jobe request.
     *
     * @return string[]
     */
    protected function headers(): array {
        $headers = ['Content-Type: application/json; charset=utf-8'];
        if ($this->apikey !== '') {
            $headers[] = 'X-API-KEY: ' . $this->apikey;
        }
        return $headers;
    }

    /**
     * Build a configured curl client.
     *
     * @return curl
     */
    protected function new_curl(): curl {
        global $CFG;

        // The curl class is a legacy global class rather than an autoloaded
        // one, and filelib is not always included yet in CLI or task context.
        require_once($CFG->libdir . '/filelib.php');

        // Moodle's cURL security helper blocks private address ranges to
        // prevent server side request forgery. That protection exists for URLs
        // that originate from user input; this one is a site administration
        // setting, and the runner is deliberately on a private address that is
        // unreachable from the internet. Without this the request to our own
        // sandbox is rejected as a blocked URL.
        $curl = new curl(['ignoresecurity' => true]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(10, $this->timeout),
            'CURLOPT_FOLLOWLOCATION' => 0,
        ]);
        return $curl;
    }
}
