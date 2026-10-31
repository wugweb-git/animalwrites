# Staging

`staging.animalwrites.in`: the same code and a copy of the live database without personal data. Never indexed, only
signed-in users can see it, and it sends no email (`plugins/animal-writes-core/includes/staging.php`).

**Status (24 Oct 2026):** the staging site exists (signed-in users only, noindex) but is not part of the deploy chain: its checkout is the deleted `ux-audit` branch and its database is from 15 Oct; moving staging off that branch was dropped (owner, 24 Oct: `docs/pending.md`, "Decided: never / dropped"). Deploys go `wordpress-dev` → Deploy check → `wordpress` (live). The steps below are the original plan.

## One-time setup in hPanel (owner)
1. **Subdomain:** Websites → animalwrites.in → Domains → Subdomains → create `staging` (folder `public_html/staging`).
2. **WordPress files:** install WordPress in that folder (Auto Installer), with its own new database. Note nothing.
3. **Git:** Advanced → Git → add the repository `wugweb-git/animalwrites`, branch `staging`,
   folder `public_html/staging/aw_deploy`, with auto-deployment on (same as the live site's `wordpress` branch).
Then link the themes and plugins, copy the database (`tools/staging/copy-db.sh`) and switch
the deploy check to: `wordpress-dev` → checks → `staging` → checks and screenshots on staging → `wordpress` (live).
