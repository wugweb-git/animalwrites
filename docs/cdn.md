# CDN and page cache

## What the site sends

`animal-writes-core/includes/cdn-cache.php` adds `Cache-Control: public, s-maxage=N` (and `X-AW-CDN`) to anonymous GET requests for public pages answered 200 without a query string. Never on wp-admin, logged-in requests, REST, feeds, search, 404, forms, nonce pages or crisis guides.

| Pages | Lifetime at the edge | Option |
|---|---|---|
| Home, News, hubs, Directory places, campaigns, other pages | `aw_cdn_ttl` (300 s on 1 Nov 2026; 60–3600) | `aw_cdn_ttl` |
| Directory listings, Library items, Resources | `aw_cdn_ttl_long` (default 3600 s), plus `stale-while-revalidate=600` | `aw_cdn_ttl_long` (0 = same as `aw_cdn_ttl`) |

Browsers get no lifetime (no `max-age`), so they always ask again. A URL with any query string (utm_, fbclid, gclid, filters) gets no header at all and is never stored at the edge.

LiteSpeed's page cache is purged after every deploy (`deploy-purge.php`). The Hostinger CDN cannot be purged from WordPress: a change shows at the edge within the lifetime above.

## Measured on 1 Nov 2026 (from Europe; the edge answering was Frankfurt)

| Page | Edge HIT, time to first byte | Edge miss (DYNAMIC) |
|---|---|---|
| `/` | 0.54–0.56 s | 1.27 s |
| `/directory/` | 0.54–0.56 s | 2.21 s |
| `/campaigns/lab-animals-rules-2026/` | 0.56 s | 1.28–1.40 s; 1.3–2.8 s with `?utm_source=` or `?fbclid=` (never stored) |
| `/directory/kerala/` | — | 1.44–1.50 s (3 of 3 misses) |
| `/directory/org/saahas-for-animals/` | — | DYNAMIC |

## Proposed hPanel settings (owner's decision; not changed)

hPanel → Websites → animalwrites.in → Performance → CDN:

1. **Cache key: ignore marketing parameters.** If the CDN offers "Ignore query string" per parameter, list `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `fbclid`, `gclid`, `igshid`. Keep every other parameter (filters, `?q=`). The site would then also need to send the header for those URLs: a one-line change in `aw_cdn_ttl()` (drop those keys before the query-string check), done only after the CDN setting is on.
2. **Edge location in India.** If the plan offers a PoP choice or "Smart routing", choose India (Mumbai). Visitors are in India; the edge measured today is Frankfurt.
3. **Respect origin cache headers.** Keep "Cache everything" off and the CDN on "respect origin": the site decides what is cached (forms and nonce pages must never be).
4. **Purge on deploy.** If hPanel offers a purge API or "Purge on Git deploy", turn it on; then the long lifetime can rise without any wait after a deploy.
5. **`aw_cdn_ttl` from 300 to 900 s** (Home, News, hubs) once 1–4 are settled: sourcing runs every 4 hours, so a 15-minute edge copy is still fresh.
