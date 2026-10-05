# Execution runner setup

Reference build for a Saylor Code Studio execution host, and the operational
notes that go with it. Provisioning script: [`provision-jobe.sh`](provision-jobe.sh).

## The security boundary

Three independent controls keep student code contained. All three must hold; none
is sufficient alone.

| Control | Enforced by | How to verify |
|---|---|---|
| No public access to the runner | Security group: inbound `:80` from the Moodle SG only, no CIDR ranges | `curl` the runner's public IP from outside AWS — must time out |
| Student code cannot reach the network | `iptables` OUTPUT REJECT rules on each `jobeNN` UID | Run a program that opens a socket — must fail with a connection error |
| Runner rejects unauthenticated callers | Jobe `api_keys_required = TRUE` | `curl` without `X-API-KEY` — must be refused |

The runner keeps its own egress for OS patching and SSM. That is deliberate: the
restriction that matters is on the *sandbox user accounts*, not the host.

## Building a runner

1. Launch Ubuntu 22.04 (`t3.medium`, 20 GB gp3, encrypted) in the same VPC as the
   Moodle host, with `provision-jobe.sh` as user data and an instance profile
   granting `AmazonSSMManagedInstanceCore`.
2. Create a security group allowing inbound TCP 80 **from the Moodle host's
   security group only**. Do not add a CIDR range.
3. Set `HttpTokens=required` so IMDSv2 is enforced.
4. Wait for `/var/log/jobe-setup.log` to end with `jobe setup finished`.

Verify before pointing Moodle at it:

```bash
aws ssm send-command --instance-ids <id> --document-name AWS-RunShellScript \
  --parameters 'commands=["KEY=$(cat /opt/jobe-api-key)","curl -s -H \"X-API-KEY: $KEY\" http://localhost/jobe/index.php/restapi/languages"]'
```

Java must report 17.x. If any endpoint returns *"The framework needs the
following extension(s) installed and loaded: intl"*, `php-intl` is missing —
install it and restart Apache.

## Connecting Moodle

*Site administration → Plugins → Local plugins → Saylor Code Studio*

- **Runner base address** — the runner's **private** address, e.g. `http://172.31.90.139`.
  Never a public address.
- **Runner API key** — the value of `/opt/jobe-api-key` on the runner.

Or from the CLI:

```bash
sudo -u www-data php admin/cli/cfg.php --component=local_saylorcode --name=jobeurl --set=http://172.31.90.139
```

### Why the private address needs no extra configuration

Moodle's cURL security helper normally blocks private address ranges to prevent
server-side request forgery. `jobe_provider` marks its calls trusted, because the
URL comes from a site administration setting rather than user input, and the
runner is deliberately on a private address. This mirrors what `qtype_coderunner`
does. If you see *"The URL is blocked"* in the health detail, that exemption is
not being applied.

## Health checks

The provider reports health rather than throwing, so a runner outage degrades
gracefully instead of breaking a course page. A quick check:

```php
$provider = \local_saylorcode\local\runner\jobe_provider::create_from_config();
$health = $provider->get_health();
```

Expected on a healthy runner: `is_healthy()` true, latency under about 50 ms on
the same VPC, and `get_profiles()` listing the runtimes the backend reports.

## Known-good verification results

Recorded against the reference build so a future change has something to compare
against.

| Check | Expected | Maps to |
|---|---|---|
| Java hello world | `outcome 15`, correct stdout | `execution_state::COMPLETED` |
| Program reads stdin | stdin consumed correctly | MVP criterion 5 |
| Syntax error | `outcome 11`, `Main.java:1: error:` preserved, sandbox path stripped | `COMPILE_ERROR` |
| Infinite loop | `outcome 13` | `TIMEOUT`, MVP criterion 16 |
| Socket to a public address | connection refused | MVP criterion 17 |
| Public IP from outside AWS | connection times out | Spec section 14.1 |
| Non-ASCII source and output | `café`, `π`, `∑` compile and print unchanged | Jobe `javac_extraflags` / `java_extraflags` set to UTF-8 |
| Program reading stdin with the Input tab empty | fails as a runtime error, not a hang | batch execution: stdin is the Input tab, not a terminal |

## Languages

| Profile | Language id | Runs on | Site setting | Default |
|---|---|---|---|---|
| `java17-console` | `java` | runner | `enablejava` | on |
| `python3-console` | `python3` | runner | `enablepython` | off |
| `cpp17-console` | `cpp` | runner | `enablecpp` | off |
| `rust-console` | `rust` | runner | `enablerust` | off |
| `javascript-node` | `nodejs` | runner | `enablejavascript` | off |
| `r-console` | `r` | runner | `enabler` | off |
| `html-web` | `html` | student's browser | `enablehtml` | on |
| `css-web` | `css` | student's browser | `enablecss` | on |

The languages added after Java are off by default, because a runner built
before them may not have them, and every run would fail. Turn each on only once the
runner lists its language id:

```bash
curl -s http://localhost/jobe/index.php/restapi/languages
```

The site status report (*Site administration → Reports → System status*) warns
when an enabled language is missing from the runner.

### Bringing an existing runner up to date

`provision-jobe.sh` installs `nodejs`, `r-base-core` and `rustc`, and writes
Jobe's R and Rust tasks, `app/Libraries/RTask.php` and `RustTask.php`. Stock Jobe
has neither; it discovers languages from `app/Libraries/<Name>Task.php`, so that
file is the whole of adding one. Python 3 and C++ come with Jobe. On a runner
provisioned earlier, install the packages, copy the task blocks out of the
script, and clear Jobe's language cache:

```bash
apt-get install -y --no-install-recommends nodejs r-base-core rustc
# ...write RTask.php, CompilesWithWarnings.php and RustTask.php, and patch
# CppTask.php, as in provision-jobe.sh...
rm -f /tmp/systemd-private-*-apache2.service-*/tmp/jobe_language_cache_file
```

The cache is not in `/tmp`. Apache runs with systemd's `PrivateTmp`, so the
`/tmp` Jobe writes to is a private directory under the real one; removing
`/tmp/jobe_language_cache_file` does nothing, and `/languages` keeps reporting
the old list. Restarting Apache also clears it, at the cost of failing any run
in flight.

### JavaScript needs a higher memory maximum

The runner has Node 24 from NodeSource, not Ubuntu's Node 12, which is end of
life and rejects ordinary modern JavaScript (`??`, `?.`) as syntax errors.

Jobe limits *address space*, not memory actually used, and Node 24's V8 reserves
a great deal of address space it never touches. On the dev runner a hello world
fails with *"Failed to reserve virtual memory for CodeRange"* at 1100 MB and
runs at 1200 MB. (Node 12 needed only 384 MB.)

So the JavaScript profile asks for 1536 MB of address space and caps the V8
heap at 256 MB with `--max-old-space-size=256`. The address space is room for
V8's reservations; the heap cap is what keeps an ordinary runaway program —
arrays, objects, strings — to about 256 MB of real memory. Memory a program
takes outside the heap, such as `Buffer.alloc`, is bounded only by the address
space limit, so one JavaScript job can in the worst case use about 1.5 GB.
Size the runner with that in mind.

The site's **Maximum memory** still governs it, as it does every profile:
settings only ever tighten limits. Below 1200 MB the JavaScript profile is
**withheld** — not offered to authors, and its existing activities report the
language as unavailable — and the status report says which ceiling to raise:

```bash
sudo -u www-data php admin/cli/cfg.php --component=local_saylorcode --name=maxmemorymb --set=1536
```

That only raises what JavaScript gets. Every other profile asks for 256 MB, and
the ceiling only ever lowers a request, so they stay at 256 MB.

Jobe's default interpreter argument for Node is `--use_strict`. On Node 24 it
has no effect (a program assigning an undeclared variable runs), so the profile
sends only the heap cap. Recheck all of this on a new Node major version.

### Compiler warnings for C++ and Rust: the Jobe patch

Stock Jobe decides a compile failed when the compiler printed *anything*,
warnings included. Its own C++ defaults are `-Wall -Werror`, and `rustc` warns
about every unused variable, so on stock Jobe a correct beginner's program fails
over a variable it has not used yet.

`provision-jobe.sh` patches this. It adds a trait,
`app/Libraries/CompilesWithWarnings.php`, that judges a compile by whether the
executable was produced. When it was, the compiler's output is kept as warnings,
the program runs, and the warnings come back in `cmpinfo` next to the program's
real outcome — `SUCCESS`, or `RUNTIME_ERROR` if it then crashes. Moodle shows
them above the program's output; they do not affect grading, which compares
standard output. A compile that produces no executable is a `COMPILE_ERROR`
exactly as before.

The Rust task uses the trait directly. Jobe's own C++ task gets two verified
insertions (the original is kept as `CppTask.php.stock`), so a Jobe update that
reshapes the file fails the build instead of quietly dropping the patch. C is
left stock; no profile uses it.

With the patch in place the profiles compile with warnings on: C++ with
`-std=c++17 -Wall`, Rust with `--edition 2021 -C codegen-units=1`.

**The profiles depend on the patch.** On a stock Jobe runner, a C++ or Rust
program with any warning fails to compile. The compiler itself runs under Jobe's
compile minimums (500 MB, 2 s, 5 processes, in
`LanguageTask::$min_params_compile`), which are the runner's own configuration
rather than a Moodle setting.

### Verified on the dev runner

2026-10-02, Node 12.22.9 and R 4.1.2 from Ubuntu 22.04, through Jobe's REST API
with the parameters Moodle sends. JavaScript was rechecked on Node 24.21.0 on
2026-10-05 with the 1536 MB / 256 MB-heap profile: every row below holds, the
runaway-array row now ends at the heap cap, and `??`, `?.`, private class fields,
`Array.prototype.at` and `structuredClone` all run.

| Check | JavaScript | R |
|---|---|---|
| Hello world | `SUCCESS` (Node 24: from 1200 MB) | `SUCCESS` (from 128 MB) |
| Reads stdin | `SUCCESS` | `SUCCESS` (`readLines(file("stdin"))`) |
| Non-ASCII output | `café π ∑` unchanged | `café π ∑` unchanged |
| Syntax error | `RUNTIME_ERROR` with line (no compile step) | `RUNTIME_ERROR`, `unexpected end of input` |
| Infinite loop | `TIME_LIMIT` | `TIME_LIMIT` |
| HTTP to a public address | `ECONNREFUSED` | `cannot open the connection` |
| Allocating without bound | `RUNTIME_ERROR`, heap out of memory | `RUNTIME_ERROR`, cannot allocate vector |

2026-10-05, Python 3.10.12, g++ 11.4 and rustc 1.75.0, the same way, with each
profile's compiler arguments and the warnings patch:

| Check | Python | C++ | Rust |
|---|---|---|---|
| Hello world | `SUCCESS` (from 64 MB) | `SUCCESS` (from 64 MB) | `SUCCESS` (from 128 MB), about 0.5 s with the compile |
| Unused variable | — | `SUCCESS`, warning shown (stock Jobe: `COMPILE_ERROR`) | `SUCCESS`, warning shown (stock Jobe: `COMPILE_ERROR`) |
| Warning, then a crash | — | `RUNTIME_ERROR`, warning shown | `RUNTIME_ERROR`, warning shown |
| Reads stdin | `SUCCESS` (`input()`) | `SUCCESS` (`std::cin`) | `SUCCESS` (`read_to_string`) |
| Non-ASCII output | `café π ∑` unchanged | unchanged | unchanged |
| Syntax or type error | `COMPILE_ERROR`, `'(' was never closed` | `COMPILE_ERROR`, `'x' was not declared` | `COMPILE_ERROR`, `E0308 mismatched types` |
| Crash | `RUNTIME_ERROR`, `ZeroDivisionError` | `RUNTIME_ERROR`, segmentation fault | `RUNTIME_ERROR`, index out of bounds panic |
| Infinite loop | `TIME_LIMIT` | `TIME_LIMIT` | `TIME_LIMIT` |
| TCP to a public address | blocked (`URLError`) | blocked | blocked (connection refused) |
| Allocating without bound | `RUNTIME_ERROR`, traceback | `RUNTIME_ERROR`, `std::bad_alloc` | `RUNTIME_ERROR`, allocation failed |

A runaway allocation is reported as a runtime error rather than `MEMORY_LIMIT`,
because every one of these languages catches the failed allocation and exits
itself.

### HTML and CSS

These never reach the runner. The workspace renders the page in a sandboxed
frame (`sandbox="allow-scripts"`, no same origin), so a student's script cannot
read the Moodle session or call Moodle's web services. A CSS activity styles a
page the author supplies on the activity form. Neither can be graded by test
cases, so they are limited to the playground and practice modes.

### Adding another language

1. Add a `profile` in `profile_manager::get_definitions()` with its stable id,
   entry filename and resource limits.
2. Add a site setting to enable it, following `enablejava`.
3. Map its language id to an editor grammar in `mod_saylorcode/editor`.

Exercises reference the profile id, so no exercise changes when a runtime is
added, upgraded or retired.

## Rotating the API key

Current Jobe is CodeIgniter 4 and keeps its keys in `app/Config/Jobe.php`; older
releases used `application/config/config.php`. Edit whichever the runner has, and
verify the edit landed rather than trusting the `sed`/`perl` return code — a
substitution that matches nothing exits zero, and a key left half-rotated makes
every execution fail once Moodle is pointed at the new value.

```bash
NEW=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
echo "$NEW" > /opt/jobe-api-key
chmod 600 /opt/jobe-api-key

CI4=/var/www/html/jobe/app/Config/Jobe.php
CI3=/var/www/html/jobe/application/config/config.php

if [ -f "$CI4" ]; then
    KEY="$NEW" perl -0pi -e \
      's/public array \$api_keys = \[.*?\];/public array \$api_keys = [\n        \x27$ENV{KEY}\x27 => 6000,\n    ];/s' "$CI4"
    grep -q "'$NEW' => 6000" "$CI4" || { echo "rotation failed: key not installed in $CI4" >&2; exit 1; }
    php -l "$CI4" >/dev/null || { echo "rotation failed: $CI4 no longer parses" >&2; exit 1; }
else
    sed -i "s/^\$config\['api_keys'\].*/\$config['api_keys'] = array('$NEW');/" "$CI3"
    grep -q "'$NEW'" "$CI3" || { echo "rotation failed: key not installed in $CI3" >&2; exit 1; }
fi

systemctl restart apache2
```

Then update the Moodle setting (`local_saylorcode | jobeapikey`). Confirm the new
key works before walking away — a keyless POST must be refused and a keyed one
accepted:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  -H 'Content-Type: application/json' \
  -d '{"run_spec":{"language_id":"java","sourcecode":"x"}}' \
  http://localhost/jobe/index.php/restapi/runs   # expect 403
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  -H "X-API-KEY: $NEW" -H 'Content-Type: application/json' \
  -d '{"run_spec":{"language_id":"java","sourcecode":"x"}}' \
  http://localhost/jobe/index.php/restapi/runs   # expect 200
```

Runs in flight during the change fail with `runner_unavailable`; student code is
preserved, so a quiet window is preferable but not essential.

## Current estimate

One `t3.medium` runner is roughly $30 per month on-demand, plus EBS. Capacity
should be re-derived from real load before launch, per specification section 19.

## Automatic deployment to dev

The dev server keeps itself up to date. `saylorcode-deploy.timer` runs every two
minutes, checks each plugin checkout against its GitHub `main`, fast-forwards
any that have moved, and runs the Moodle upgrade once for the batch.

```bash
systemctl status saylorcode-deploy.timer
journalctl -u saylorcode-deploy.service -n 50
systemctl start saylorcode-deploy.service   # deploy now rather than waiting
```

### Why it pulls rather than being pushed

The obvious design is a GitHub Action that deploys on merge. That would mean
granting GitHub Actions the ability to run commands inside the AWS account,
through either a stored access key or an OIDC trust relationship.

These repositories are public, so a pull needs **no credentials at all**. No
secret is stored in GitHub, and nothing outside the host is granted access to
the account. The cost is up to two minutes of latency and a deploy failure that
shows in the journal rather than on the pull request, which is the right trade
for a development server.

If deploy status on the pull request becomes worth having, the Action can be
added later without removing this; they are not exclusive.

### What it will not do

- **It will not touch a checkout parked on a feature branch.** Testing a branch
  on dev is normal, and a deploy that silently yanked it back to `main` would be
  worse than not deploying.
- **It will not overwrite a diverged checkout.** Merges are fast-forward only;
  anything else is logged as `REFUSED` and left alone, because a divergence
  means someone is working in there.
- **It will not run the upgrade when nothing changed**, so an idle server does
  no database work.
