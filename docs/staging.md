# Staging

`staging.animalwrites.in`: the same code and a copy of the live database without personal data. Never indexed, only
signed-in users can see it, and it sends no email (`plugins/animal-writes-core/includes/staging.php`).

**Status (24 Oct 2026):** the staging site exists (signed-in users only, noindex) but is not part of the deploy chain: its checkout is the deleted `ux-audit` branch and its database is from 15 Oct; moving staging off that branch was dropped (owner, 24 Oct: `docs/pending.md`, "Decided: never / dropped"). Deploys go `wordpress-dev` → Deploy check → `wordpress` (live). The steps below are the original plan.

## Refresh from live, for before/after checks (owner, 11 Nov 2026)
The staging site lives in `~/domains/staging.animalwrites.in/public_html` (not under the live folder). Refresh it
with `tools/staging/refresh.sh [git-ref]`, run on the server. That copy keeps live's personal data, so:
- `tools/staging/staging-guard.php` goes in first as a must-use plugin: no email, no outside requests, noindex,
  signed-in users only. It is on when `AW_STAGING` is set in staging's `wp-config.php` (the script's first step, so
  it holds while the imported database still has live's address), or when the host starts with `staging.`.
- WordPress cron is off (`DISABLE_WP_CRON`); MailWeb (live mailbox) and UpdraftPlus (remote backups) are off.
- Pictures load from live's address; nothing is written to live.
- The code is `origin/wordpress` (live) unless a ref is given. For a 0-difference check: refresh, record each page
  type, check out the branch in staging's `aw_deploy`, record again, compare.
`tools/staging/copy-db.sh` is the older copy that removes personal data; it does not set up plugins or the guard.

## One-time setup in hPanel (owner)
1. **Subdomain:** Websites → animalwrites.in → Domains → Subdomains → create `staging` (folder `public_html/staging`).
2. **WordPress files:** install WordPress in that folder (Auto Installer), with its own new database. Note nothing.
3. **Git:** Advanced → Git → add the repository `wugweb-git/animalwrites`, branch `staging`,
   folder `public_html/staging/aw_deploy`, with auto-deployment on (same as the live site's `wordpress` branch).
Then link the themes and plugins, copy the database (`tools/staging/copy-db.sh`) and switch
the deploy check to: `wordpress-dev` → checks → `staging` → checks and screenshots on staging → `wordpress` (live).
