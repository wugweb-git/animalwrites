#!/bin/bash
# Ship: push HEAD to wordpress-dev, wait for the deploy gate, wait for Hostinger to pull it, purge LiteSpeed.
# In the repo since 25 Oct 2026 (was ~/Documents/GitHub/aw-ship.sh, which now only calls this file). Ships the HEAD of the
# checkout it is run from: fetch and check out the commit first (git checkout --detach origin/wordpress-dev), and ship
# when wordpress-dev is quiet: a newer push cancels this commit's Deploy check and the script refuses.
set -e
cd "$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
sha=$(git rev-parse HEAD)
server=(ssh -i ~/.ssh/hostinger_aw -p 65002 -o LogLevel=ERROR -o BatchMode=yes -o ConnectTimeout=15 u544169507@46.28.45.135)
# No deploy while a FluentCRM campaign is sending (3 Nov 2026): Hostinger replaces aw_deploy on each pull, so for a few
# seconds the plugins are missing, and a campaign batch that starts then sends without mailweb's mailbox (33 emails failed).
# Same check as the Deploy check job (the site's /aw/v1/deploy-status); it refuses when the site can't say.
# Pause the campaign in FluentCRM (Campaigns, the campaign, Pause Sending), ship, then Resume.
python3 tools/ci/mail_sending_check.py || exit 1
git push -q origin "HEAD:refs/heads/wordpress-dev"
# GitHub can take a while to create the run, and it may sit queued behind another run: look it up by this commit and
# wait for it (up to 10 minutes) instead of giving up. A newer push to wordpress-dev still stops the ship.
run=""
for i in $(seq 1 60); do
  sleep 10
  run=$(gh run list --workflow deploy-check.yml --commit "$sha" -L 5 --json databaseId -q '.[0].databaseId' 2>/dev/null || true)
  [ -n "$run" ] && break
  git fetch -q origin
  [ "$(git rev-parse origin/wordpress-dev)" = "$sha" ] || { echo "REFUSING to deploy: wordpress-dev moved on from $sha"; exit 1; }
  [ $((i % 6)) -eq 0 ] && echo "waiting for the Deploy check run for ${sha:0:7} ($((i * 10))s)"
done
[ -n "$run" ] || { echo "REFUSING to deploy: no Deploy check run found for $sha after 10 minutes"; exit 1; }
gh run watch "$run" --exit-status >/dev/null || { echo "REFUSING to deploy: the Deploy check failed for $sha"; exit 1; }
# Admins bypass branch protection on direct pushes, so the status is checked here too: the commit's "check" must be success.
# A re-run adds a second "check" run to the same commit (an old failure beside the new result), so only the newest counts (4 Nov 2026).
status=$(gh api "repos/wugweb-git/animalwrites/commits/$sha/check-runs" -q '[.check_runs[] | select(.name=="check")] | sort_by(.started_at) | last | .conclusion // ""')
[ "$status" = "success" ] || { echo "REFUSING to deploy: check status for $sha is '${status:-none}', not success"; exit 1; }
git fetch -q origin
# Compare full shas: "--short" is 8 characters here and 7 on the server, so short shas never matched (1 Nov 2026).
short=${sha:0:7}
for i in $(seq 1 12); do
  live=$("${server[@]}" 'cd ~/domains/animalwrites.in/public_html && git -C aw_deploy rev-parse HEAD' 2>/dev/null | tail -1)
  [ "$live" = "$sha" ] && break
  sleep 10
done
# Hostinger's auto-deploy stopped pulling on 20 Oct 2026 (last fetch 05:45 UTC). If it has not pulled within 2 minutes,
# check out the gated commit on the server ourselves: only when the deploy branch is exactly this commit and the
# server's copy has no local changes.
if [ "$live" != "$sha" ]; then
  gated=$(git rev-parse origin/wordpress)
  if [ "$gated" = "$sha" ]; then
    live=$("${server[@]}" "cd ~/domains/animalwrites.in/public_html/aw_deploy && test -z \"\$(git status --short)\" && git fetch -q origin wordpress && test \"\$(git rev-parse FETCH_HEAD)\" = $sha && git checkout -q --detach FETCH_HEAD && git rev-parse HEAD" 2>/dev/null | tail -1)
    echo "Hostinger did not pull; checked out on the server: ${live:0:7}"
  fi
fi
[ "$live" = "$sha" ] || { echo "NOT deployed: server is at ${live:0:7}, wanted $short"; exit 1; }
echo "deployed $short"
# Purge through the site, not WP-CLI: "wp litespeed-purge all" cannot notify the web server on this host (24 Oct 2026).
# A one-off home URL is a cache miss, so deploy-purge.php (animal-writes-core) runs and purges once for the new commit.
code=$(curl -s -o /dev/null -m 30 -w "%{http_code}" "https://animalwrites.in/?aw_deploy_nudge=ship-$short-$(date +%s)" || true)
echo "purge request: HTTP $code"
[ "$code" = "200" ] || echo "WARNING: purge request did not return 200; purge by hand (WP Admin, LiteSpeed Cache, Purge All)"
# The same request schedules Check site for the new commit (site-check.php): its result shows on Kits → Check site and
# Kits → Welcome a few minutes from now, with the commit it ran on.
if [ "$code" = "200" ]; then echo "Check site runs for $short in about 2-7 minutes (Kits → Check site)"; fi
