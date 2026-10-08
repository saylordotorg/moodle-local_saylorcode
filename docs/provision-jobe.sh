#!/bin/bash
# Saylor Code Studio - Jobe sandbox provisioning.
#
# Reference build for a Saylor Code Studio execution host. Pass as EC2 user
# data on Ubuntu 22.04. Verified on t3.medium, 20 GB gp3, us-east-1.
#
# This script provisions the runner only. The security boundary also depends on
# the instance's security group, which must allow inbound port 80 from the
# Moodle host's security group and from nothing else. See docs/runner-setup.md.
set -x
exec > >(tee -a /var/log/jobe-setup.log) 2>&1

# The log captures xtrace output, and the API key section below turns tracing
# off around the secret, so nothing sensitive lands in it. Restricted anyway,
# because a setup log is exactly where the next secret gets pasted by accident.
touch /var/log/jobe-setup.log
chmod 600 /var/log/jobe-setup.log

echo "=== jobe setup started $(date -u) ==="

export DEBIAN_FRONTEND=noninteractive

# Defined first, because the script has no set -e: a check that calls fail()
# before it exists only prints "command not found" and carries on.
fail() {
    echo "FATAL: $1" >&2
    exit 1
}

apt-get update -y
apt-get upgrade -y

# Apache, PHP and the language runtimes Jobe will offer.
#
# php-intl is required by Jobe's CodeIgniter framework and is easy to miss:
# without it every REST endpoint returns "The framework needs the following
# extension(s) installed and loaded: intl." rather than a useful error.
apt-get install -y \
    apache2 \
    php \
    php-cli \
    libapache2-mod-php \
    php-mbstring \
    php-intl \
    php-xml \
    php-curl \
    build-essential \
    openjdk-17-jdk \
    python3 \
    python3-pip \
    rustc \
    acl \
    git \
    unzip \
    gnupg \
    iptables-persistent

# --- Node.js 24 -------------------------------------------------------------
# Not Ubuntu's nodejs: on 22.04 that is Node 12, end of life since 2022, which
# rejects ordinary modern JavaScript such as ?? and ?. as syntax errors. Node 24
# comes from NodeSource's signed apt repository, so security updates arrive
# through apt like everything else on the host.
#
# Jobe runs /usr/bin/nodejs; NodeSource provides it through alternatives, and
# the link below covers a package that does not.
install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main" \
    > /etc/apt/sources.list.d/nodesource.list
apt-get update -y
apt-get install -y nodejs
[ -e /usr/bin/nodejs ] || ln -s node /usr/bin/nodejs
nodejs --version | grep -q '^v24\.' || { echo "FATAL: expected Node 24, got $(nodejs --version)" >&2; exit 1; }

# --- Install Jobe -----------------------------------------------------------
cd /var/www/html
if [ ! -d /var/www/html/jobe ]; then
    git clone https://github.com/trampgeek/jobe.git
fi
cd /var/www/html/jobe

# --- R and its packages -----------------------------------------------------
# Current R from CRAN's Ubuntu repository rather than Ubuntu's own (4.1 on
# 22.04), because recent packages expect a newer R. Packages come from r2u,
# which serves every CRAN package as a prebuilt Ubuntu binary: minutes to
# install instead of hours of compiling. r2u is pinned above the Ubuntu archive
# so its r-cran-* builds, which match current R, win.
#
# The set is what the R course uses. afex is installed but needs 384 MB and
# 2.3 s of CPU just to load, so it is not usable under the r-console profile's
# limits; car covers the same ANOVA. Packages are site-wide: student code has no
# network, so install.packages() from a program cannot work, by design. r2u's
# bspm bridge (install.packages -> apt) is deliberately not enabled.
curl -fsSL https://cloud.r-project.org/bin/linux/ubuntu/marutter_pubkey.asc \
    | gpg --dearmor --yes -o /etc/apt/keyrings/cran-ubuntu.gpg
echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/cran-ubuntu.gpg] https://cloud.r-project.org/bin/linux/ubuntu jammy-cran40/" \
    > /etc/apt/sources.list.d/cran-r.list
curl -fsSL https://eddelbuettel.github.io/r2u/assets/dirk_eddelbuettel_key.asc \
    | gpg --dearmor --yes -o /etc/apt/keyrings/r2u.gpg
echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/r2u.gpg] https://r2u.stat.illinois.edu/ubuntu jammy main" \
    > /etc/apt/sources.list.d/r2u.list
cat > /etc/apt/preferences.d/99r2u <<'PIN'
Package: *
Pin: release o=CRAN-Apt Project
Pin: release l=CRAN-Apt Packages
Pin-Priority: 700
PIN
apt-get update -y
apt-get install -y --no-install-recommends \
    r-base-core r-recommended \
    r-cran-stringr r-cran-dplyr r-cran-tibble r-cran-readr r-cran-tidyr r-cran-purrr \
    r-cran-forcats r-cran-lubridate r-cran-data.table r-cran-psych r-cran-car r-cran-afex \
    r-cran-modelr r-cran-readxl r-cran-ggplot2
Rscript -e 'for (p in c("stringr","dplyr","readr","data.table","psych","car","modelr","ggplot2")) suppressPackageStartupMessages(library(p, character.only = TRUE))' \
    || fail "R packages did not load"

# --- R in Jobe --------------------------------------------------------------
# Stock Jobe has no R. It discovers languages from app/Libraries/<Name>Task.php,
# so dropping this class in is the whole of adding one; the language id is the
# lowercased prefix, "r", which is what the r-console profile sends.
#
# Rscript rather than R: it runs a file non-interactively and does not echo the
# program back into the output. --vanilla keeps a site or user profile out of
# the run, so every student starts from the same empty session.
#
# Stock Jobe calls any run that wrote to stderr a runtime error, and never looks
# at the exit status. R writes package startup messages ("Attaching package",
# "Loading required package") and ordinary warnings to stderr, so correct
# programs failed. Rscript runs through this wrapper, which appends its real
# exit status; the task strips it and decides on it. A run with no status line
# was killed (time, memory, signal) and keeps Jobe's own verdict, so a killed
# run can never pass.
install -d -m 0755 /usr/local/lib/jobe
cat > /usr/local/lib/jobe/rscript-status <<'WRAP'
#!/bin/sh
# Run Rscript and append its exit status to stderr, for Jobe's R task.
/usr/bin/Rscript "$@"
status=$?
echo "[saylorcode-exit:$status]" >&2
exit $status
WRAP
chmod 0755 /usr/local/lib/jobe/rscript-status

cat > /var/www/html/jobe/app/Libraries/RTask.php <<'RTASK'
<?php

/* ==============================================================
 *
 * R, added for Saylor Code Studio.
 *
 * ==============================================================
 */

namespace Jobe;

class RTask extends LanguageTask
{
    public function __construct($filename, $input, $params)
    {
        parent::__construct($filename, $input, $params);
        $this->default_params['interpreterargs'] = array('--vanilla');
    }

    public static function getVersionCommand()
    {
        return array('R --version', '/R version ([0-9._]*)/');
    }

    public function compile()
    {
        // Interpreted: nothing to build, and syntax errors surface at run time.
        $this->executableFileName = $this->sourceFileName;
    }

    public function defaultFileName($sourcecode)
    {
        return 'prog.R';
    }

    public function getExecutablePath()
    {
        return '/usr/local/lib/jobe/rscript-status';
    }

    public function getTargetFile()
    {
        return $this->sourceFileName;
    }

    public function diagnoseResult()
    {
        $status = null;
        if (preg_match('/\[saylorcode-exit:(\d+)\]\s*$/', $this->stderr, $m)) {
            $status = (int) $m[1];
            $this->stderr = rtrim(preg_replace('/\n?\[saylorcode-exit:\d+\]\s*$/', '', $this->stderr), "\n");
            if ($this->stderr !== '') {
                $this->stderr .= "\n";
            }
        }
        parent::diagnoseResult();
        if ($status === 0 && $this->result == LanguageTask::RESULT_RUNTIME_ERROR) {
            $this->result = LanguageTask::RESULT_SUCCESS;
        } else if ($status !== null && $status !== 0 && $this->result == LanguageTask::RESULT_SUCCESS) {
            $this->result = LanguageTask::RESULT_RUNTIME_ERROR;
        }
    }
}
RTASK
php -l /var/www/html/jobe/app/Libraries/RTask.php > /dev/null \
    || { echo "FATAL: RTask.php does not parse" >&2; exit 1; }

# --- Compiler warnings are not compile errors -------------------------------
# Stock Jobe decides a compile failed when the compiler wrote anything at all,
# so a warning fails a correct program: an unused variable in C++ under -Wall,
# or in any Rust program. This trait decides by whether the compiler produced
# the executable instead. When it did, the compiler's output is kept as
# warnings, the program runs, and the warnings come back in cmpinfo alongside
# the program's real outcome. Moodle shows them above the program's output.
#
# Used by the C++ task (patched below) and the Rust task. Without it the
# cpp17-console and rust-console profiles, which compile with warnings on,
# fail every program that has a warning.
cat > /var/www/html/jobe/app/Libraries/CompilesWithWarnings.php <<'TRAIT'
<?php

/* ==============================================================
 *
 * Compiler warnings are not compile errors. Saylor Code Studio patch.
 *
 * ==============================================================
 */

namespace Jobe;

trait CompilesWithWarnings
{
    /** @var string Compiler output from a compile that succeeded. */
    protected string $compilewarnings = '';

    /**
     * Call after compiling: move warnings out of cmpinfo when the executable
     * exists, and make sure a failed compile always says something.
     */
    protected function separateWarnings(string $execFileName): void
    {
        if (is_file($this->workdir . '/' . $execFileName)) {
            $this->compilewarnings = $this->cmpinfo;
            $this->cmpinfo = '';
        } else if (trim($this->cmpinfo) === '') {
            $this->cmpinfo = 'Compilation failed without a message.';
        }
    }

    public function resultObject()
    {
        $result = parent::resultObject();
        if ($this->compilewarnings === '' || $result->outcome == LanguageTask::RESULT_COMPILATION_ERROR) {
            return $result;
        }
        return new ResultObject($result->run_id, $result->outcome, $this->compilewarnings, $result->stdout, $result->stderr);
    }
}
TRAIT
php -l /var/www/html/jobe/app/Libraries/CompilesWithWarnings.php > /dev/null \
    || fail "CompilesWithWarnings.php does not parse"

# Two insertions into Jobe's own C++ task, each verified, rather than a copy of
# the whole file: a Jobe update that reshapes compile() fails the build here
# instead of silently losing the patch. Idempotent, so a re-run is harmless.
CPP=/var/www/html/jobe/app/Libraries/CppTask.php
[ -f "$CPP.stock" ] || cp -p "$CPP" "$CPP.stock"
if ! grep -q 'use CompilesWithWarnings;' "$CPP"; then
    perl -0pi -e 's/(class CppTask extends LanguageTask\n\{\n)/$1    use CompilesWithWarnings;\n\n/' "$CPP"
    perl -0pi -e 's/(list\(\$output, \$this->cmpinfo\) = \$this->runInSandbox\(\$cmd\);\n)/$1        \$this->separateWarnings(\$execFileName);\n/' "$CPP"
fi
grep -q 'use CompilesWithWarnings;' "$CPP" || fail "could not add CompilesWithWarnings to $CPP"
grep -q 'separateWarnings(\$execFileName);' "$CPP" || fail "could not call separateWarnings in $CPP"
php -l "$CPP" > /dev/null || fail "patched $CPP no longer parses"

# --- Rust -------------------------------------------------------------------
# Stock Jobe has no Rust either. The language id is "rust", which is what the
# rust-console profile sends. The profile sends its own compiler arguments; the
# defaults here match them, for any other client of this runner. Warnings are
# on and handled by CompilesWithWarnings, above. One codegen unit keeps rustc
# to a single LLVM thread, inside the sandbox's process limit.
cat > /var/www/html/jobe/app/Libraries/RustTask.php <<'RUSTTASK'
<?php

/* ==============================================================
 *
 * Rust, added for Saylor Code Studio.
 *
 * ==============================================================
 */

namespace Jobe;

class RustTask extends LanguageTask
{
    use CompilesWithWarnings;

    public function __construct($filename, $input, $params)
    {
        parent::__construct($filename, $input, $params);
        $this->default_params['compileargs'] = array('--edition', '2021', '-C', 'codegen-units=1');
    }

    public static function getVersionCommand()
    {
        return array('rustc --version', '/rustc ([0-9._]*)/');
    }

    public function compile()
    {
        $src = basename($this->sourceFileName);
        $this->executableFileName = $execFileName = "$src.exe";
        $compileargs = $this->getParam('compileargs');
        $cmd = 'rustc ' . implode(' ', array_map('escapeshellarg', $compileargs))
            . ' -o ' . escapeshellarg($execFileName) . ' ' . escapeshellarg($src);
        list($output, $this->cmpinfo) = $this->runInSandbox($cmd);
        $this->separateWarnings($execFileName);
    }

    public function defaultFileName($sourcecode)
    {
        return 'prog.rs';
    }

    public function getExecutablePath()
    {
        return './' . $this->executableFileName;
    }

    public function getTargetFile()
    {
        return '';
    }
}
RUSTTASK
php -l /var/www/html/jobe/app/Libraries/RustTask.php > /dev/null \
    || { echo "FATAL: RustTask.php does not parse" >&2; exit 1; }

# Jobe's installer creates the jobe00..jobeNN run accounts, sets ownership and
# builds the runguard sandbox helper.
python3 ./install || /usr/bin/env python3 ./install

# --- Require an API key -----------------------------------------------------
# Generated on the instance so the secret never appears in EC2 user data, which
# is readable through describe-instance-attribute.
#
# Tracing is suspended while the key is in play: set -x prints every command
# after expansion, and this script's output is being logged, so leaving it on
# would write the key into /var/log/jobe-setup.log — and from there into any
# backup, AMI snapshot or log pipeline that touches the box.
set +x
API_KEY=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
echo "$API_KEY" > /opt/jobe-api-key
chmod 600 /opt/jobe-api-key

# Current Jobe is CodeIgniter 4 and configures keys in app/Config/Jobe.php;
# older releases used application/config/config.php. Handle both, and fail
# the build outright when neither matches: a runner that silently skips this
# block accepts execution requests from anything that can reach it, and that
# is exactly how the first runner shipped -- the CI3 sed found no file, the
# condition guarded it into a no-op, and nothing was ever enforced.
CI4_CONFIG=/var/www/html/jobe/app/Config/Jobe.php
CI3_CONFIG=/var/www/html/jobe/application/config/config.php

if [ -f "$CI4_CONFIG" ]; then
    # Each edit is verified rather than trusted. A perl s/// against a future
    # checkout whose whitespace or syntax has drifted matches nothing and exits
    # zero, so without checking the result the script would print success while
    # leaving require_api_keys false -- an unauthenticated runner, which is the
    # exact failure this whole block exists to prevent. set -e is not enough on
    # its own, because a no-op substitution is not an error to it.
    KEY="$API_KEY" perl -0pi -e \
        's/public bool \$require_api_keys = (false|true);/public bool \$require_api_keys = true;/' \
        "$CI4_CONFIG"
    grep -q 'public bool \$require_api_keys = true;' "$CI4_CONFIG" \
        || fail "could not enable require_api_keys in $CI4_CONFIG"

    # The rate is per key per hour, enforced on restapi/runs only. Moodle
    # already rate-limits per user and per site; this bound is the backstop
    # for a leaked key, not the working limit.
    KEY="$API_KEY" perl -0pi -e \
        's/public array \$api_keys = \[.*?\];/public array \$api_keys = [\n        \x27$ENV{KEY}\x27 => 6000, \/\/ Saylor Code Studio Moodle. Managed via \/opt\/jobe-api-key.\n    ];/s' \
        "$CI4_CONFIG"
    grep -q "'$API_KEY' => 6000" "$CI4_CONFIG" \
        || fail "could not install the API key in $CI4_CONFIG"

    php -l "$CI4_CONFIG" > /dev/null \
        || fail "edited $CI4_CONFIG no longer parses"

    echo "api key installed into jobe config (CodeIgniter 4 layout)"
elif [ -f "$CI3_CONFIG" ]; then
    sed -i "s/^\$config\['api_keys_required'\].*/\$config['api_keys_required'] = TRUE;/" "$CI3_CONFIG"
    if grep -q "api_keys" "$CI3_CONFIG"; then
        sed -i "s/^\$config\['api_keys'\].*/\$config['api_keys'] = array('$API_KEY');/" "$CI3_CONFIG"
    else
        echo "\$config['api_keys'] = array('$API_KEY');" >> "$CI3_CONFIG"
    fi
    echo "api key installed into jobe config (CodeIgniter 3 layout)"
else
    echo "FATAL: no known jobe config layout found; refusing to ship an unauthenticated runner" >&2
    exit 1
fi
set -x

# --- Make the toolchain UTF-8 -----------------------------------------------
# Java on a C-locale host defaults to US-ASCII, which breaks two ordinary
# things: javac refuses source containing any non-ASCII character with
# "unmappable character for encoding US-ASCII", and a program that prints one
# emits a question mark instead. Exercises are authored in Moodle, which is
# UTF-8 throughout, so accented words and mathematical symbols are normal
# content rather than an edge case.
#
# Set through jobe's own extraflags, which it appends to the JVM defaults.
# Sending interpreterargs from the client would replace jobe's
# -Xrs -Xss8m -Xmx200m instead of adding to them, quietly taking the signal
# handling, stack and heap limits with it.
if [ -f "$CI4_CONFIG" ]; then
    sed -i -E "s/(javac_extraflags = )'';/\1'-encoding UTF-8';/" "$CI4_CONFIG"
    sed -i -E "s/(java_extraflags = )'';/\1'-Dfile.encoding=UTF-8 -Dsun.stdout.encoding=UTF-8 -Dsun.stderr.encoding=UTF-8';/" "$CI4_CONFIG"

    grep -q "encoding UTF-8" "$CI4_CONFIG" \
        || fail "could not set javac_extraflags in $CI4_CONFIG"
    grep -q "file.encoding=UTF-8" "$CI4_CONFIG" \
        || fail "could not set java_extraflags in $CI4_CONFIG"
    php -l "$CI4_CONFIG" > /dev/null \
        || fail "edited $CI4_CONFIG no longer parses"

    echo "toolchain set to UTF-8"
else
    # No silent pass for the older layout. Whether a CI3 jobe honours these
    # settings at all cannot be established from the current checkout, and a
    # runner that quietly mangles every accented character is worse than one
    # that refuses to finish building: the first fails in front of a student,
    # the second fails in front of whoever is provisioning it. Same reasoning
    # as the unknown-layout case above.
    fail "cannot configure UTF-8 for this jobe layout; use a current jobe checkout"
fi

# --- Deny student processes any network -------------------------------------
# Specification section 14.1 requires that student code cannot reach the
# internet or the private network. Jobe runs every job as an unprivileged
# jobeNN account, so blocking those UIDs at the firewall is what enforces it.
# The host itself keeps egress for patching and SSM.
#
# Verify after boot by running a program that opens a socket; it must fail with
# a connection error rather than succeed.
for user in $(getent passwd | awk -F: '$1 ~ /^jobe[0-9]+$/ {print $1}'); do
    uid=$(id -u "$user")
    iptables -A OUTPUT -m owner --uid-owner "$uid" -o lo -j ACCEPT
    iptables -A OUTPUT -m owner --uid-owner "$uid" -j REJECT
    echo "network blocked for $user (uid $uid)"
done

netfilter-persistent save || iptables-save > /etc/iptables/rules.v4

# --- Apache -----------------------------------------------------------------
a2enmod rewrite
systemctl enable apache2
systemctl restart apache2

# Jobe caches the language list in Apache's /tmp, which is a systemd PrivateTmp
# directory rather than the real /tmp. The restart above already gave Apache a
# fresh one; this clears any copy a re-run of the script would leave behind.
rm -f /tmp/systemd-private-*-apache2.service-*/tmp/jobe_language_cache_file

echo "=== jobe setup finished $(date -u) ==="
