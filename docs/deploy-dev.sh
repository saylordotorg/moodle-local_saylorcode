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
OWNER=www-data
STATEDIR=/var/lib/saylorcode
MARKER="$STATEDIR/pending-upgrade"

PLUGINS=(
    "local/saylorcode"
    "mod/saylorcode"
    "filter/saylorcode"
    "lib/editor/tiny/plugins/saylorcode"
)

log() {
    echo "$(date -u '+%Y-%m-%dT%H:%M:%SZ') $*"
}

# Git runs as the account that owns the checkout, never as root.
#
# The service itself is root, and the checkouts are writable by the web user.
# If root ran git in them, anyone who compromised the web process could drop an
# executable .git/hooks/post-merge and get root on the next upstream update,
# because git runs hooks even for a fast-forward. Running as the owner removes
# that path, and disabling hooks outright removes it again: nothing in these
# checkouts should ever be executing hook scripts.
#
# It also means no safe.directory entries are needed, since git only objects
# when the caller differs from the owner.
git_as_owner() {
    sudo -u "$OWNER" git -c core.hooksPath=/dev/null "$@"
}

mkdir -p "$STATEDIR"

# An upgrade left pending by a previous run must still happen, even if no
# checkout has moved since. Otherwise a run that fast-forwarded and then failed
# during the upgrade would leave new plugin code running against an old schema
# indefinitely, because every later run would see nothing to do.
changed=0
if [ -f "$MARKER" ]; then
    log "an upgrade from an earlier run is still pending"
    changed=1
fi

for rel in "${PLUGINS[@]}"; do
    dir="$MOODLE/$rel"

    if [ ! -d "$dir/.git" ]; then
        log "skip $rel: not a git checkout"
        continue
    fi

    # Never touch a checkout someone has parked on a feature branch. Testing a
    # branch on dev is a normal thing to do, and a deploy that silently yanked
    # it back to main would be worse than not deploying.
    branch=$(git_as_owner -C "$dir" rev-parse --abbrev-ref HEAD 2>/dev/null)
    if [ "$branch" != "main" ]; then
        log "skip $rel: on branch '$branch', not main"
        continue
    fi

    if ! git_as_owner -C "$dir" fetch -q origin main 2>/dev/null; then
        log "skip $rel: fetch failed"
        continue
    fi

    localsha=$(git_as_owner -C "$dir" rev-parse HEAD)
    remotesha=$(git_as_owner -C "$dir" rev-parse origin/main)

    if [ "$localsha" = "$remotesha" ]; then
        continue
    fi

    # Claim the pending upgrade before touching anything, so a crash between
    # the merge and the upgrade is still recovered on the next run.
    touch "$MARKER"

    # Fast-forward only. If the checkout has diverged, something local is going
    # on and overwriting it would destroy someone's work in progress.
    if git_as_owner -C "$dir" merge --ff-only origin/main >/dev/null 2>&1; then
        log "updated $rel ${localsha:0:7} -> ${remotesha:0:7}"
        changed=1
    else
        log "REFUSED $rel: checkout has diverged from main, leaving it untouched"
    fi
done

if [ "$changed" -eq 0 ]; then
    # Nothing moved and nothing was owed, so the marker claimed above (if any)
    # was for a refused merge and should not persist.
    rm -f "$MARKER"
    exit 0
fi

log "running the Moodle upgrade"

if ! sudo -u "$OWNER" php "$MOODLE/admin/cli/upgrade.php" --non-interactive; then
    log "UPGRADE FAILED: leaving the pending marker in place so the next run retries"
    exit 1
fi

if ! sudo -u "$OWNER" php "$MOODLE/admin/cli/purge_caches.php" >/dev/null 2>&1; then
    log "cache purge failed after a successful upgrade"
    rm -f "$MARKER"
    exit 1
fi

rm -f "$MARKER"
log "deploy complete"
