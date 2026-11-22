#!/bin/bash
# Package ingestion: pull Kits platform packages from wugweb-git/kits into wp-content/.
# Runs after composer install / on deploy. Uses git checkout to extract subdirectories
# from the central monorepo into the tenant's wp-content tree.
#
# Usage:
#   tools/package-ingest.sh --verify      # check that all expected packages exist
#   tools/package-ingest.sh --pull        # fetch latest from wugweb-git/kits and checkout subtrees
set -eo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KITS_REMOTE="https://github.com/wugweb-git/kits.git"
KITS_BRANCH="kits-suite"

# Packages: subdirectory in kits monorepo -> install target under wp-content/
# Format: "subdir target" one per line
PACKAGES_LIST="
themes/kits-by-wugweb wp-content/themes/kits-by-wugweb
plugins/kits-core wp-content/plugins/kits-core
plugins/directoryweb wp-content/plugins/directoryweb
plugins/rankweb wp-content/plugins/rankweb
plugins/mailweb wp-content/plugins/mailweb
plugins/sourceweb wp-content/plugins/sourceweb
plugins/insightweb wp-content/plugins/insightweb
plugins/planweb wp-content/plugins/planweb
"

cmd_verify() {
  echo "Verifying Kits package ingestion targets..."
  local ok=1
  while read -r subdir target; do
    [ -z "$subdir" ] && continue
    if [ ! -d "$ROOT/$target" ]; then
      echo "  MISSING: $target (from kits:$subdir)"
      ok=0
    else
      echo "  OK: $target"
    fi
  done <<< "$PACKAGES_LIST"
  if [ $ok -eq 1 ]; then
    echo "All 8 Kits packages present."
    exit 0
  else
    echo "Some packages missing. Run: tools/package-ingest.sh --pull"
    exit 1
  fi
}

cmd_pull() {
  echo "Fetching Kits monorepo ($KITS_REMOTE @ $KITS_BRANCH)..."
  git remote add kits-upstream "$KITS_REMOTE" 2>/dev/null || true
  git fetch -q kits-upstream "$KITS_BRANCH"

  while read -r subdir target; do
    [ -z "$subdir" ] && continue
    echo "  Checking out $subdir -> $target"
    mkdir -p "$(dirname "$ROOT/$target")"
    git checkout kits-upstream/$KITS_BRANCH -- "$subdir"
    mkdir -p "$(dirname "$target")" 2>/dev/null || true
    git mv "$subdir" "$target" 2>/dev/null || true
  done <<< "$PACKAGES_LIST"
  echo "Package ingestion complete. Review and commit the changes."
}

case "${1:-}" in
  --verify) cmd_verify ;;
  --pull)   cmd_pull ;;
  *)        echo "Usage: $0 --verify|--pull" ; exit 1 ;;
esac
