# Staging

`staging.animalwrites.in`: the same code and a copy of the live database without personal data. Never indexed, only
signed-in users can see it, and it sends no email (`plugins/animal-writes-core/includes/staging.php`).

## One-time setup in hPanel (owner)
1. **Subdomain:** Websites → animalwrites.in → Domains → Subdomains → create `staging` (folder `public_html/staging`).
2. **WordPress files:** install WordPress in that folder (Auto Installer), with its own new database. Note nothing.
3. **Git:** Advanced → Git → add the repository `wugweb-git/animalwrites`, branch `staging`,
   folder `public_html/staging/aw_deploy`, with auto-deployment on (same as the live site's `wordpress` branch).
Then tell Claude Code; it links the themes and plugins, copies the database (`tools/staging/copy-db.sh`) and switches
the deploy check to: `wordpress-dev` → checks → `staging` → checks and screenshots on staging → `wordpress` (live).
