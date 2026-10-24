# Working rules for Animal Writes 2.0 (owner rules; read before changing anything)

1. **Old branches are never merged blind.** Before merging, rebasing or cherry-picking any branch that is more than a day behind `origin/wordpress-dev`:
   - list what it changes against what live changed since: `mb=$(git merge-base BRANCH origin/wordpress); git diff --stat $mb BRANCH; git diff --stat $mb origin/wordpress`;
   - for every file changed on both sides, check whether live already has the feature (classes, patterns, page content) or replaced it;
   - report the result to the owner and wait for a yes. Never push a stale branch over newer live work. (21 Oct 2026: `home-revision-2026-10-18` was audited, found superseded by live and deleted at the owner's word.)
2. **Validate on git and on the live site, not on notes.** Claims such as "it is done" are checked with `git log`, `git merge-base --is-ancestor <commit> origin/wordpress` and the live page or database before they are repeated.
3. **Two routes, by size of change** (owner, 24 Oct 2026).
   - **Bigger technical work** (a new plugin or function, or restructuring a function): branch → PR → review gate → owner merges → ship. The branch is its own, in its own git worktree, as a pull request into `wordpress-dev`. Review gate before the owner merges: the code-review skill; wp-plugin-audit when a plugin changes; PHPCS on changed files; the Deploy check green (it runs on PRs, with no deploy steps); Codex comments answered (Codex is an extra reviewer, not the gate).
   - **Small changes** (data, docs, small fixes): no PR. Commit straight onto `wordpress-dev` and ship.
   - Either way the Deploy check gates `wordpress`. Ship with `git fetch origin && git merge --ff-only origin/wordpress-dev && bash ~/Documents/GitHub/aw-ship.sh`: it refuses unless the commit's `check` is success, and the cache is purged after every deploy (`animal-writes-core/includes/deploy-purge.php`). No direct pushes to `wordpress`. One session deploys at a time.
4. **Every session works in its own git worktree**; stage explicit paths (never `git add -A`); commit trailer `Co-Authored-By: Wugweb <admin@wugweb.com>`.
5. **Never delete user data, listings or posts without asking.** Unpublish to draft instead.
6. **The Animal Writes 1.0 repo (`main`, old.animalwrites.in) is read-only.**
7. **Open work lives in `docs/pending.md`.** Update it when something is done, decided or added.
