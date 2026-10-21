# Working rules for Animal Writes 2.0 (owner rules; read before changing anything)

1. **Old branches are never merged blind.** Before merging, rebasing or cherry-picking any branch that is more than a day behind `origin/wordpress-dev`:
   - list what it changes against what live changed since: `mb=$(git merge-base BRANCH origin/wordpress); git diff --stat $mb BRANCH; git diff --stat $mb origin/wordpress`;
   - for every file changed on both sides, check whether live already has the feature (classes, patterns, page content) or replaced it;
   - report the result to the owner and wait for a yes. Never push a stale branch over newer live work. (21 Oct 2026: `home-revision-2026-10-18` was audited and is superseded; it stays, unmerged.)
2. **Validate on git and on the live site, not on notes.** Claims such as "it is done" are checked with `git log`, `git merge-base --is-ancestor <commit> origin/wordpress` and the live page or database before they are repeated.
3. **Ship only through `~/Documents/GitHub/aw-ship.sh`** (push to `wordpress-dev`, wait for the Deploy check). Never push to `wordpress`. One session deploys at a time.
4. **Every session works in its own git worktree**; stage explicit paths (never `git add -A`); commit trailer `Co-Authored-By: Wugweb <admin@wugweb.com>`.
5. **Never delete user data, listings or posts without asking.** Unpublish to draft instead.
6. **The Animal Writes 1.0 repo (`main`, old.animalwrites.in) is read-only.**
7. **Open work lives in `docs/pending.md`.** Update it when something is done, decided or added.
