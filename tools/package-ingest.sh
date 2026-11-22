#!/bin/bash
# Package ingestion: pull Kits platform packages from wugweb-git/kits into wp-content/.
# Runs after composer install / on deploy. Uses git subtree to extract subdirectories
# from the central monorepo into the tenant's wp-content tree.
#
# Usage:
#   tools/package-ingest.sh --verify      # check that all expected packages exist
#   tools/package-ingest.sh --pull        # fetch latest from wugweb-git/kits and subtrees
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KITS_REMOTE="https://github.com/wugweb-git/kits.git"
KITS_BRANCH="kits-suite"

# Packages: subdirectory in kits monorepo -> install target under wp-content/
declare -A PACKAGES=(
  ["themes/kits-by-wugweb"]="wp-content/themes/kits-by-wugweb"
  ["plugins/kits-core"]="wp-content/plugins/kits-core"
  ["plugins/directoryweb"]="wp-content/plugins/directoryweb"
  ["plugins/rankweb"]="wp-content/plugins/rankweb"
  ["plugins/mailweb"]="wp-content/plugins/mailweb"
  ["plugins/sourceweb"]="wp-content/plugins/sourceweb"
  ["plugins/insightweb"]="wp-content/plugins/insightweb"
  ["plugins/planweb"]="wp-content/plugins/planweb"
)

cmd_verify() {
  echo "Verifying Kits package ingestion targets..."
  local ok=1
  for subdir in "${!PACKAGES[@]}"; do
    local target="${PACKAGES[$subdir]}"
    if [ ! -d "$ROOT/$target" ]; then
      echo "  MISSING: $target (from kits:$subdir)"
      ok=0
    else
      echo "  OK: $target"
    fi
  done
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

  for subdir in "${!PACKAGES[@]}"; do
    local target="${PACKAGES[$subdir]}"
    echo "  Pulling $subdir -> $target"
    if [ -d "$ROOT/$target" ]; then
      git subtree pull --prefix="$target" kits-upstream "$KITS_BRANCH" --squash -m "ingest(kits): sync $subdir from wugweb-git/kits@$KITS_BRANCH" || true
    else
      git subtree add --prefix="$target" kits-upstream "$KITS_BRANCH" --squash -m "ingest(kits): add $subdir from wugweb-git/kits@$KITS_BRANCH" || true
    fi
  done
  echo "Package ingestion complete. Review and commit the changes."
}

case "${1:-}" in
  --verify) cmd_verify ;;
  --pull)   cmd_pull ;;
  *)        echo "Usage: $0 --verify|--pull" ; exit 1 ;;
esac