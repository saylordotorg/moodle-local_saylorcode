# Saylor Code Studio — shared service layer (`local_saylorcode`)

Foundation plugin for [Saylor Code Studio](../SAYLOR_CODE_STUDIO_SPEC.md), a Moodle-native
coding-learning environment for Saylor Academy courses.

This plugin contains no user interface of its own. It provides the shared services the rest
of the plugin suite builds on: the runner-provider abstraction, runtime profiles, stable-ID
resolution, output sanitisation, health checks and site configuration.

**Status: alpha, Phase 1 vertical slice.** Not production-ready. See
[Scope](#scope-and-what-is-not-here) below for an explicit list of what is not yet built.

## Requirements

| | |
|---|---|
| Moodle | 4.5 (build 2024100700) |
| PHP | 8.1 – 8.3 |
| Execution backend | A privately hosted [Jobe](https://github.com/trampgeek/jobe) server |

## Why the code is shaped this way

Three design decisions carry most of the weight, and all three come from the specification's
security boundary rather than from convenience.

**Student code never executes on the Moodle server.** Everything runs on a separate Jobe
sandbox host reached over an internal network. `local_saylorcode` only marshals requests and
interprets results.

**Exercises reference a runtime profile, never a command.** An exercise names
`java17-console`; it never carries a compiler invocation, image name or URL. That indirection
is what allows the backend to be upgraded, replaced or taken offline without editing content
(spec §5.9, §13.8). Site settings may only *tighten* a profile's resource limits — see
`profile::clamped_to()` — so a misconfiguration cannot widen the sandbox.

**Protected data is separated by type, not by discipline.** `execution_response` holds the
complete result for server-side scoring, and `export_for_student()` is the only supported way
to produce a browser payload. Hidden tests are reduced to a counted outcome there, so a
template cannot leak assessment content by forgetting to filter. This is enforced by
adversarial tests in `tests/local/runner/hidden_test_disclosure_test.php`.

## Architecture

```
provider_interface          Contract any execution backend must satisfy
├── jobe_provider           Jobe REST implementation
execution_request           Immutable work description; carries no user identity
execution_response          Full result server-side; export_for_student() strips protection
├── test_result             One test outcome, public or hidden
execution_state             The 14 canonical states from spec §13.6
output_sanitiser            Removes paths and hosts, keeps compiler line numbers
runtime/profile             Immutable resource + language profile
runtime/profile_manager     Site profiles, clamped to configured ceilings
stable_id                   Parsing and validation for IDs like CS101-U05-E03
```

`execution_request` deliberately has no field for a user id, name, email or grade. Correlation
back to a Moodle attempt happens through an opaque random request id, so no personal data
reaches the runner (spec §13.4). The privacy provider declares this transmission.

## Configuration

*Site administration → Plugins → Local plugins → Saylor Code Studio*

- **Execution backend** — Jobe base URL, API key, request timeout.
- **Resource ceilings** — CPU seconds, memory, disk, processes, output bytes, per-user
  concurrency. These are site maximums that no profile may exceed.
- **Runtime profiles** — enable or disable a language. Disabling Java takes it offline
  without touching a single exercise.
- **Retention** — snapshots kept per attempt, execution log retention.

The Jobe address must not be reachable from the public internet.

## Scope and what is not here

This is the Phase 1 vertical slice from spec §24. Deliberately **not** in this plugin:

- The activity, embed filter and TinyMCE button — separate repositories.
- The central authoring library, versioning workflow and usage reporting (Phase 3).
- Languages other than Java (Phase 2+).
- Test execution against a harness. `jobe_provider::execute()` currently returns program
  output; assembling and scoring a test set is the next increment.

## Development

Continuous integration runs [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci)
against `MOODLE_405_STABLE` on PHP 8.1 and 8.3, across PostgreSQL and MariaDB: phplint, phpmd,
phpcs, phpdoc, validate, savepoints, mustache, grunt, PHPUnit and Behat.

Run the unit tests locally from your Moodle root:

```bash
vendor/bin/phpunit --testsuite local_saylorcode_testsuite
```

## Licence

GPL-3.0-or-later, matching Moodle.
