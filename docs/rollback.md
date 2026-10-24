# Rollback and backups

## Code: one command
Every deploy that passes the deploy check is tagged `release-YYYYMMDD-HHMM-<commit>`; the last 5 are kept.

```
gh workflow run rollback.yml
```
puts the live site back on the release before the current one. To pick one: `git tag -l 'release-*'`, then
`gh workflow run rollback.yml -f release=release-…`. Hostinger deploys it within about a minute.
The release's `deploy-purge.php` purges the page cache once Hostinger has pulled it; a release made before 24 Oct 2026 has no such hook, so purge by hand (`wp litespeed-purge all`; rollback.yml warns).

## Database
Daily, about 03:30 IST: `~/backups/auto-db-YYYY-MM-DD.sql.gz` and `~/backups/auto-code-YYYY-MM-DD.tar.gz`, kept 14 days
(Tools → Check site shows the last one). Hand-made backups (`db-…`, `code-…`) are never removed by the rotation.
Restore a database (this replaces the live data; take a fresh backup first):
```
cd ~/domains/animalwrites.in/public_html && zcat ~/backups/auto-db-YYYY-MM-DD.sql.gz | wp db import -
```
