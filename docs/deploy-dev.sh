#!/bin/bash
# Saylor Code Studio - pull deploy for the dev server.
#
# Checks each plugin checkout against its GitHub main and fast-forwards when it
# has moved, then runs the Moodle upgrade once for the whole batch.
#
# Pull rather than push, deliberately. The repositories are public, so this
# needs no credentials at all: nothing is stored in GitHub and nothing outside
# this host is granted access to the AWS account. The cost is a couple of
# minutes of latency, which is the right trade on a development server.
#
# Installed at /usr/local/bin/saylorcode-deploy.sh and run by
# saylorcode-deploy.timer. Output goes to the journal:
#
#   journalctl -u saylorcode-deploy.service -n 50

set -uo pipefail

MOODLE=/var/www/html/moodle
export HOME=/root

PLUGINS=(
    "local/saylorcode"
    "mod/saylorcode"
    "filter/saylorcode"
    "lib/editor/tiny/plugins/saylorcode"
)

changed=0

log() {
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') $*"
}

for rel in "${PLUGINS[@]}"; do
    dir="$MOODLE/$rel"

    if [ ! -d "$dir/.git" ]; then
        log "skip $rel: not a git checkout"
        continue
    fi

    git config --global --add safe.directory "$dir" 2>/dev/null

    # Never touch a checkout someone has parked on a feature branch. Testing a
    # branch on dev is a normal thing to do, and a deploy that silently yanked
    # it back to main would be worse than not deploying.
    branch=$(git -C "$dir" rev-parse --abbrev-ref HEAD 2>/dev/null)
    if [ "$branch" != "main" ]; then
        log "skip $rel: on branch '$branch', not main"
        continue
    fi

    if ! git -C "$dir" fetch -q origin main 2>/dev/null; then
        log "skip $rel: fetch failed"
        continue
    fi

    localsha=$(git -C "$dir" rev-parse HEAD)
    remotesha=$(git -C "$dir" rev-parse origin/main)

    if [ "$localsha" = "$remotesha" ]; then
        continue
    fi

    # Fast-forward only. If the checkout has diverged, something local is going
    # on and overwriting it would destroy someone's work in progress.
    if git -C "$dir" merge --ff-only origin/main >/dev/null 2>&1; then
        chown -R www-data:www-data "$dir"
        log "updated $rel ${localsha:0:7} -> ${remotesha:0:7}"
        changed=1
    else
        log "REFUSED $rel: checkout has diverged from main, leaving it untouched"
    fi
done

if [ "$changed" -eq 0 ]; then
    exit 0
fi

log "plugins changed, running the Moodle upgrade"
sudo -u www-data php "$MOODLE/admin/cli/upgrade.php" --non-interactive 2>&1 | tail -6
sudo -u www-data php "$MOODLE/admin/cli/purge_caches.php" >/dev/null 2>&1
log "deploy complete"
