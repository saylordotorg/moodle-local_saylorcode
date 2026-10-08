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

`provision-jobe.sh` installs Node 24 from NodeSource, current R with the course's
packages (see *R and its packages*, below) and `rustc`, and writes Jobe's R and Rust tasks, `app/Libraries/RTask.php` and `RustTask.php`.
Stock Jobe has neither; it discovers languages from
`app/Libraries/<Name>Task.php`, so that file is the whole of adding one. Python 3
and C++ come with Jobe. On a runner provisioned earlier:

```bash
# Node 24, not Ubuntu's nodejs: on 22.04 that is Node 12. Ubuntu's package is
# split across nodejs and libnode72, and NodeSource's conflicts with the
# library, so both are removed first.
install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main" \
    > /etc/apt/sources.list.d/nodesource.list
apt-get update
apt-get remove -y nodejs libnode72
apt-get install -y nodejs
[ -e /usr/bin/nodejs ] || ln -s node /usr/bin/nodejs
nodejs --version   # must print v24.x

apt-get install -y --no-install-recommends rustc
# ...add the CRAN and r2u repositories and install R and its packages, write
# /usr/local/lib/jobe/rscript-status, RTask.php, CompilesWithWarnings.php and
# RustTask.php, and patch CppTask.php, all as in provision-jobe.sh...
rm -f /tmp/systemd-private-*-apache2.service-*/tmp/jobe_language_cache_file
```

To roll Node back: remove `/etc/apt/sources.list.d/nodesource.list`, then
`apt-get update && apt-get remove -y nodejs && apt-get install -y nodejs`.
That brings back Ubuntu's Node 12, which needs the old JavaScript profile.

The cache is not in `/tmp`. Apache runs with systemd's `PrivateTmp`, so the
`/tmp` Jobe writes to is a private directory under the real one; removing
`/tmp/jobe_language_cache_file` does nothing, and `/languages` keeps reporting
the old list. Restarting Apache also clears it, at the cost of failing any run
in flight.

### R and its packages

The runner has current R from CRAN's Ubuntu repository (4.6.1 on 2026-10-08)
rather than Ubuntu's 4.1, and the packages the R course uses as prebuilt
binaries from [r2u](https://eddelbuettel.github.io/r2u/), which mirrors all of
CRAN as Ubuntu packages and is pinned above the Ubuntu archive:

stringr, dplyr, tibble, readr, tidyr, purrr, forcats, lubridate, data.table,
psych, car, afex, modelr, readxl, ggplot2, plus R's recommended packages.

They are installed site-wide. Student code has no network, so
`install.packages()` cannot work from a program, and r2u's bspm bridge, which
would turn it into an apt install, is deliberately not enabled. To add a
package, add its `r-cran-<name>` to the list in `provision-jobe.sh` and install
it on the runner the same way.

Loading cost, measured in the sandbox at 256 MB and 5 s of CPU (the r-console
profile now asks for 512 MB, for plots; see below):

| Load | CPU | Memory |
|---|---|---|
| one of stringr, dplyr, tibble, readr, tidyr, purrr, forcats, lubridate, modelr | 0.2–0.5 s | 26–37 MB |
| data.table, psych, car, readxl | under 0.1 s | 21–25 MB |
| ggplot2 | 0.9 s | 59 MB |
| eight tidyverse packages together | 1.2 s | 63 MB |
| afex | **fails at 256 MB** (lme4 runs out of memory); at 384 MB it loads in 2.3 s | |

`afex` loads at 384 MB and above, but takes 2.3 s of the 5 s just to load, so
it suits only very short programs; `car::Anova` gives the same one-way ANOVA
much faster.

### Plots from R

R programs can draw: base graphics and ggplot2 plots appear under the output in
the workspace, up to 4 per run.

- `/usr/local/lib/jobe/Rprofile-plots`, read through `R_PROFILE_USER`, makes R's
  default device a 640x480 PNG at 96 dpi. The student's code is unchanged: a
  `plot()` or a printed ggplot writes `Rplot001.png`, `Rplot002.png` and so on.
  The R task's interpreter arguments are `--vanilla` minus `--no-init-file`, so
  that profile is read and no other.
- After the program ends, the wrapper appends each image of up to 512 KB to
  stderr as a `[saylorcode-plot:<base64>]` line, before the exit-status line,
  and a plain note if any were skipped. The images together are capped at 1 MB
  of PNG (about 1.33 MB of base64), because Jobe stops a run whose stderr
  passes its 2 MB stream limit. Real plots are 10 to 50 KB.
- `jobe_provider` takes those lines out of stderr before anything else reads it,
  and keeps an image only if it decodes strictly as base64, starts with the PNG
  signature and is no larger than 512 KB, at most 4, re-encoded. A marker line
  cut short (a run stopped at the stream limit mid-image) is dropped, never
  shown. The workspace
  checks the shape again before using it as a `data:image/png` source. Only a
  plain Run shows plots; Check and Submit compare standard output, as before.
- Images are never stored: execution records hold states and timings only.
- A file the student saves under another name with `png()` is not collected.

**Plots need 512 MB.** The graphics stack starts threads, each reserving stack,
and Jobe limits address space. Measured on the dev runner:

| Plot | 256 MB | 384 MB | 512 MB |
|---|---|---|---|
| base graphics (`hist`, `plot`) | renders, 0.5 s | renders | renders |
| ggplot2 scatter, histogram, boxplot, time series | fails (thread creation) | renders, 1.4 to 1.6 s | renders, 1.4 to 1.6 s |
| ggplot2 with facets and a smoother | fails | fails | renders, 2.7 s |

So the r-console profile asks for 512 MB. It has no floor: under a site
maximum below 512 MB, R still runs and base plots still draw; only ggplot2 can
fail. A runaway R program can now use up to 512 MB of real memory rather than
256; include that in any capacity planning.

**Messages and warnings are not errors.** Stock Jobe calls any run that wrote
to stderr a runtime error and never looks at the exit status, and R writes
package startup messages (`Attaching package: 'dplyr'`, `Loading required
package: carData`) and ordinary `warning()`s to stderr. So every program using
dplyr, data.table or car failed. `Rscript` now runs through
`/usr/local/lib/jobe/rscript-status`, which appends the real exit status to
stderr, and the R task decides on it: exit 0 is a success, with the messages
still shown; non-zero is a runtime error. A run with no status line was killed
(time, memory or a signal) and keeps Jobe's own verdict, so a killed run can
never count as a success. The status line is stripped before anyone sees the
output. Checked on the dev runner:

| Program | Result |
|---|---|
| `library(dplyr)`, `library(car)`, `warning()`, `message()`, then output | `SUCCESS`, message shown |
| `stop("boom")`, a syntax error, running out of memory | `RUNTIME_ERROR` |
| `quit(status = 2)` | `RUNTIME_ERROR` (stock Jobe called this a success: it wrote no stderr) |
| an infinite loop | `TIME_LIMIT` |

Other languages still use Jobe's stderr rule: a Python program that prints a
warning, or Java that writes to `System.err`, is a runtime error even when it
exits cleanly.

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
