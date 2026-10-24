# Launch prep: animalwrites.in → 2.0 (applied; kept as a record — today GTM-TB3FCFQB carries GA4, see analytics/ga4-setup.md)

## 1. Redirects
1.0 was a single page: every section was a `#anchor` on `/`, so anchors need no redirects
(browsers never send them to the server). The only real 1.0 URL was `/`.

| From (1.0) | To (2.0) | Code |
|---|---|---|
| `animalwrites.in/` | 2.0 homepage `/` | none needed once DNS points at Hostinger |
| `animalwrites.in/campaign` | `/campaigns/animal-writes-1-0/` (1.0 case study, once published) | 301 |
| `www.animalwrites.in/*` | `https://animalwrites.in/*` | 301 |

Rules to add **above** the `# BEGIN WordPress` block in `public_html/.htaccess` at launch:

```apache
RewriteEngine On
# www -> apex
RewriteCond %{HTTP_HOST} ^www\.animalwrites\.in$ [NC]
RewriteRule ^(.*)$ https://animalwrites.in/$1 [R=301,L]
# 1.0 case study
RewriteRule ^campaign/?$ /campaigns/animal-writes-1-0/ [R=301,L]
```

Check the case-study slug after publishing (WP Admin → Campaign Hub → Animal Writes 1.0 → URL);
it defaults to `animal-writes-1-0`.

## 2. Analytics continuity
- 1.0 loaded Google Tag Manager container **GTM-W9D28GKV**. 2.0 loads the same container from
  Animal Writes Core (`includes/analytics.php`), **only on animalwrites.in** (never on the
  temporary domain), so GA4 history continues. Nothing to switch on at launch.
- Site Kit (optional, for GA reports inside WP Admin): connect it with the Google account that
  owns the GA4 property and **leave Site Kit's Tag Manager snippet off** (or, if you turn it on,
  Animal Writes Core automatically stops printing its own copy).
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
