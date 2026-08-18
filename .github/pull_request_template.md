# What this changes

<!-- One or two sentences. What behaviour is different after this merges? -->

## Why

<!-- The problem being solved, not a restatement of the diff. -->

## How it was verified

<!-- Delete what does not apply. -->

- [ ] Moodle Plugin CI passes
- [ ] Unit tests added or updated for the changed behaviour
- [ ] Exercised on the dev server against a real runner
- [ ] Checked with a keyboard and a screen reader (interface changes)
- [ ] Upgrade step added and tested (schema changes)

## Invariants

Tick only the ones this change touches, and say how each still holds.

- [ ] Hidden test content cannot reach the browser
- [ ] No personal data travels to the runner
- [ ] Site settings can only tighten resource limits, never widen them
- [ ] Runner output shown to students is sanitised
- [ ] Web services check a capability and validate context
- [ ] Student work cannot be silently discarded
- [ ] Embed token attributes remain a whitelist
- [ ] Execution records contain no source code

<!--
The AI review is advisory. A human approving review is required before merge,
and that is deliberate: these plugins execute untrusted student code.
-->
