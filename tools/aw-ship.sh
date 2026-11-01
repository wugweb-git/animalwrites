#!/bin/bash
# Ship: push HEAD to wordpress-dev, wait for the deploy gate, wait for Hostinger to pull it, purge LiteSpeed.
# In the repo since 25 Oct 2026 (was ~/Documents/GitHub/aw-ship.sh, which now only calls this file). Ships the HEAD of the
# checkout it is run from: fetch and check out the commit first (git checkout --detach origin/wordpress-dev), and ship
# when wordpress-dev is quiet: a newer push cancels this commit's Deploy check and the script refuses.
set -e
cd "$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
sha=$(git rev-parse HEAD)
git push -q origin "HEAD:refs/heads/wordpress-dev"
sleep 8
run=$(gh run list --workflow deploy-check.yml --branch wordpress-dev -L 5 --json databaseId,headSha -q ".[] | select(.headSha==\"$sha\") | .databaseId" | head -1)
[ -n "$run" ] || { echo "REFUSING to deploy: no Deploy check run found for $sha"; exit 1; }
gh run watch "$run" --exit-status >/dev/null || { echo "REFUSING to deploy: the Deploy check failed for $sha"; exit 1; }
# Admins bypass branch protection on direct pushes, so the status is checked here too: the commit's "check" must be success.
status=$(gh api "repos/wugweb-git/animalwrites/commits/$sha/check-runs" -q '[.check_runs[] | select(.name=="check")] | map(.conclusion) | unique | join(",")')
[ "$status" = "success" ] || { echo "REFUSING to deploy: check status for $sha is '${status:-none}', not success"; exit 1; }
git fetch -q origin
# Compare full shas: "--short" is 8 characters here and 7 on the server, so short shas never matched (1 Nov 2026).
short=${sha:0:7}
server=(ssh -i ~/.ssh/hostinger_aw -p 65002 -o LogLevel=ERROR -o BatchMode=yes -o ConnectTimeout=15 u544169507@46.28.45.135)
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
