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

## Adding a language

The runner already carries c, cpp, java, php and python3. Exposing one to authors
takes two steps:

1. Add a `profile` in `profile_manager::get_definitions()` with its stable id,
   entry filename and resource limits.
2. Add a site setting to enable it, following `enablejava`.

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
