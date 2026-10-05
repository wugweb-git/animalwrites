# Launch prep: animalwrites.in → 2.0 (prepared, NOT applied)

## 1. Redirects
1.0 was a single page: every section was a `#anchor` on `/`, so anchors need no redirects
(browsers never send them to the server). The only real 1.0 URLs were `/` and the admin
pages `/?admin=volunteers`, `/?admin=receipts`, `/?admin=counter`.

| From (1.0) | To (2.0) | Code |
|---|---|---|
| `animalwrites.in/` | 2.0 homepage `/` | none needed once DNS points at Hostinger |
| `animalwrites.in/campaign` | `/campaigns/animal-writes-1-0/` (1.0 case study, once published) | 301 |
| `/?admin=volunteers`, `/?admin=receipts`, `/?admin=counter` | `/` | 301 (pages retired; 1.0 backend deleted) |
| `www.animalwrites.in/*` | `https://animalwrites.in/*` | 301 |

Rules to add **above** the `# BEGIN WordPress` block in `public_html/.htaccess` at launch:

```apache
RewriteEngine On
# www -> apex
RewriteCond %{HTTP_HOST} ^www\.animalwrites\.in$ [NC]
RewriteRule ^(.*)$ https://animalwrites.in/$1 [R=301,L]
# 1.0 case study
RewriteRule ^campaign/?$ /campaigns/animal-writes-1-0/ [R=301,L]
# retired 1.0 admin pages
RewriteCond %{QUERY_STRING} (^|&)admin=(volunteers|receipts|counter)(&|$) [NC]
RewriteRule ^$ /? [R=301,L]
```

Check the case-study slug after publishing (WP Admin → Campaign Hub → Animal Writes 1.0 → URL);
it defaults to `animal-writes-1-0`.

## 2. Analytics continuity
- 1.0 loaded Google Tag Manager container **GTM-W9D28GKV** (in `src/components/MetaTags.tsx` on `main`).
  The GA4 property is configured inside that container. Reuse the same container in 2.0 to keep
  one continuous traffic history.
- `GA_PROPERTY_ID` (the numeric GA4 property ID) was only stored as a secret on the 1.0 Supabase
  edge function, which has been deleted. It is not in the code. Find it in Google Analytics →
  Admin → Property settings (only needed for the GA4 Data API, not for tracking).

## 3. Launch steps
1. Hostinger: add `animalwrites.in` to the site (currently DNS at dns-parking.com, A → 75.2.60.5 = Netlify).
2. Point DNS at Hostinger; wait for SSL.
3. `wp search-replace 'https://slategray-donkey-829925.hostingersite.com' 'https://animalwrites.in' --all-tables --dry-run`, then without `--dry-run`.
4. Add the redirect rules above.
5. Settings → Reading → untick "Discourage search engines".
6. Update the workers' `WP_BASE_URL` secret.

## 4. Tag
`main` is tagged `v1.0-final` (the last 1.0 code).
