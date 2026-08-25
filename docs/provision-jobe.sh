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
    acl \
    git \
    unzip \
    iptables-persistent

# --- Install Jobe -----------------------------------------------------------
cd /var/www/html
if [ ! -d /var/www/html/jobe ]; then
    git clone https://github.com/trampgeek/jobe.git
fi
cd /var/www/html/jobe

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
    KEY="$API_KEY" perl -0pi -e \
        's/public bool \$require_api_keys = (false|true);/public bool \$require_api_keys = true;/' \
        "$CI4_CONFIG"
    # The rate is per key per hour, enforced on restapi\/runs only. Moodle
    # already rate-limits per user and per site; this bound is the backstop
    # for a leaked key, not the working limit.
    KEY="$API_KEY" perl -0pi -e \
        's/public array \$api_keys = \[.*?\];/public array \$api_keys = [\n        \x27$ENV{KEY}\x27 => 6000, \/\/ Saylor Code Studio Moodle. Managed via \/opt\/jobe-api-key.\n    ];/s' \
        "$CI4_CONFIG"
    php -l "$CI4_CONFIG" > /dev/null
    grep -q "require_api_keys = true" "$CI4_CONFIG"
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

echo "=== jobe setup finished $(date -u) ==="
