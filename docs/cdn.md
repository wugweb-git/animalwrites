# CDN and page cache

## What the site sends

`animal-writes-core/includes/cdn-cache.php` adds `Cache-Control: public, s-maxage=N` (and `X-AW-CDN`) to anonymous GET requests for public pages answered 200 without a query string. Never on wp-admin, logged-in requests, REST, feeds, search, 404, forms, nonce pages or crisis guides.

| Pages | Lifetime at the edge | Option |
|---|---|---|
| Home, News, hubs, Directory places, campaigns, other pages | `aw_cdn_ttl` (900 s since 1 Nov 2026, was 300; 60–3600) | `aw_cdn_ttl` |
| Directory listings, Library items, Resources | `aw_cdn_ttl_long` (default 3600 s), plus `stale-while-revalidate=600` | `aw_cdn_ttl_long` (0 = same as `aw_cdn_ttl`). Not set on live (2 Nov 2026): no such option is stored, so the default 3600 s applies |

Browsers get no lifetime (no `max-age`), so they always ask again. A URL with any query string (utm_, fbclid, gclid, filters) gets no header at all and is never stored at the edge.

## What purges the cache (2 Nov 2026)

> 3 Nov 2026: LiteSpeed Cache is active on live again (`wp plugin list`; pages answer `x-litespeed-cache: hit`). There are two caches in front of WordPress: the server's LiteSpeed page cache and the Hostinger CDN. The paragraph below describes 2 Nov.

Nothing. LiteSpeed Cache is inactive on live, so its hooks do nothing: `deploy-purge.php` still runs after each deploy but has no page cache to purge, and publishing or editing a post purges nothing either (the `litespeed_purge_*` calls in the plugins are no-ops). The only cache left is the Hostinger CDN (`server: hcdn`), and WordPress cannot purge it. A change (a deploy, a new post, an edit) shows at the edge when the stored copy runs out: within 15 minutes for Home, News, hubs and campaigns (`aw_cdn_ttl` 900), within an hour for listings, Library items and Resources (`aw_cdn_ttl_long` 3600, plus up to 10 minutes stale). Each edge keeps its own copy. To show a change at once: hPanel → CDN → Purge cache (step 4 below).

Checked 2 Nov 2026: `/` answers `cache-control: public, s-maxage=900`, `x-hcdn-cache-status: HIT`, edge `fra` (Frankfurt).

## Measured on 1 Nov 2026 (from Europe; the edge answering was Frankfurt)

| Page | Edge HIT, time to first byte | Edge miss (DYNAMIC) |
|---|---|---|
| `/` | 0.54–0.56 s | 1.27 s |
| `/directory/` | 0.54–0.56 s | 2.21 s |
| `/campaigns/lab-animals-rules-2026/` | 0.56 s | 1.28–1.40 s; 1.3–2.8 s with `?utm_source=` or `?fbclid=` (never stored) |
| `/directory/kerala/` | — | 1.44–1.50 s (3 of 3 misses) |
| `/directory/org/saahas-for-animals/` | — | DYNAMIC |

## hPanel steps for the owner (not done yet)

Only the owner can do these; the server has no crontab command and the CDN has no API here. hPanel labels change from time to time: if a setting below is not offered, note "not offered" and skip it.

### A. Workers on time: the 15-minute tick

1. hPanel → **Websites** → animalwrites.in → **Manage**.
2. Left menu → **Advanced** → **Cron Jobs**.
3. Under **Cron jobs list**, find the job that runs `aw-dispatch-sourcing.sh` (schedule `17 */4 * * *`) → **Delete**.
4. **Create a new cron job**: type **Custom**; command:
   `cd /home/u544169507/domains/animalwrites.in/public_html && /usr/local/bin/wp aw workers-tick >> /home/u544169507/aw-cron.log 2>&1`
5. Schedule: minute `*/15`, hour `*`, day `*`, month `*`, weekday `*` (every 15 minutes) → **Save**.
6. Leave the wp-cron job (every 5 minutes) as it is.
7. Wait 15 minutes, then check: the last lines of `aw-cron.log` show `workers-tick` entries (or Sourcing → Workers shows runs started by the site).
8. Only then: GitHub → wugweb-git/animal-writes-workers → **Settings** → **Secrets and variables** → **Actions** → **Variables** tab → **New repository variable**: name `AW_SCHEDULER`, value `site` → **Add variable**. (Set it before step 7 and nothing starts the workers. Delete the variable to go back to GitHub's own schedule.)

### B. CDN

hPanel → **Websites** → animalwrites.in → **Manage** → **Performance** → **CDN**:

1. **Cache key: ignore marketing parameters.** If the CDN offers ignoring query parameters per name, add `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `fbclid`, `gclid`, `igshid`. Keep every other parameter (filters, `?q=`). Do not choose "ignore all query strings". After this, the site needs a one-line change in `aw_cdn_ttl()` (drop those keys before the query-string check) so those URLs get the header too; that change is made only after the setting is on.
2. **Edge location in India.** If a PoP choice or "Smart routing" is offered, choose India (Mumbai). The edge answering today is Frankfurt.
3. **Respect origin cache headers.** Keep "Cache everything" off: the site decides what is stored (forms and nonce pages never are).
4. **Purge.** **Purge cache** (or **Flush cache**) clears every edge at once: use it after a deploy that must show at once. If hPanel offers "Purge on Git deploy" or a purge API key, turn it on and tell the developers; then the lifetimes above can rise.

### C. Let AI agents and crawlers past the caches (3 Nov 2026)

Why: Markdown for agents and the Server tab's crawler, visitor and error counts work only when a request reaches WordPress. Today two caches answer first: the server's LiteSpeed page cache (`x-litespeed-cache: hit`, checked 3 Nov on `/about/`: an `Accept: text/markdown` request got the cached HTML) and the Hostinger CDN (`x-hcdn-cache-status: HIT`). People keep both caches; only the requests below skip them.

What the site already does (code): the HTML of Home and single pages sends `Vary: Accept`, and the Markdown answer is `no-store`. If the CDN honours Vary it keeps the Markdown and HTML apart by itself (test 3 below).

**C1. Server page cache (LiteSpeed): one block in `.htaccess`.** hPanel → **Websites** → animalwrites.in → **Manage** → **Files** → **File Manager** → `public_html/.htaccess` → **Edit**. Paste this as the very first lines of the file, above `# BEGIN LSCACHE`, and save:

```
# BEGIN AW agents
<IfModule LiteSpeed>
RewriteEngine on
RewriteCond %{HTTP:Accept} text/markdown [NC,OR]
RewriteCond %{HTTP_USER_AGENT} (GPTBot|OAI-SearchBot|ChatGPT-User|ClaudeBot|Claude-User|Claude-SearchBot|PerplexityBot|Perplexity-User|MistralAI-User|cohere-ai|YouBot|Googlebot|Google-InspectionTool|GoogleOther|bingbot|Applebot|Amazonbot|DuckDuckBot|YandexBot|Baiduspider|meta-externalagent|meta-webindexer|Bytespider|CCBot|PetalBot|Diffbot) [NC]
RewriteRule .* - [E=Cache-Control:no-cache]
</IfModule>
# END AW agents
```

It marks only those requests "no cache" for LiteSpeed (the same switch the LiteSpeed block uses for REST requests), so they reach WordPress; nobody else's page is affected. To undo: delete the block. Download a copy of `.htaccess` before editing; if the site shows an error after saving, put the copy back.

**C2. Hostinger CDN.** hPanel offers no rule by user agent or Accept header (Hostinger's CDN help lists only Cache-Control, the WordPress login and cart cookies, and Development mode). Ask Hostinger support (chat): "Please make the CDN for animalwrites.in bypass its cache for requests whose User-Agent contains any of: GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-User, Claude-SearchBot, PerplexityBot, Perplexity-User, MistralAI-User, cohere-ai, YouBot, Googlebot, Google-InspectionTool, GoogleOther, bingbot, Applebot, Amazonbot, DuckDuckBot, YandexBot, Baiduspider, meta-externalagent, meta-webindexer, Bytespider, CCBot, PetalBot, Diffbot; and for requests whose Accept header contains text/markdown. Or tell us whether the CDN honours the `Vary: Accept` response header." Note their answer here. Without it, crawler requests for pages the CDN holds are not counted (the Server tab says so).

**Tests after C1 (and after the code with `Vary: Accept` is live):**

1. `curl -s -o /dev/null -D - -H 'Accept: text/markdown' https://animalwrites.in/about/ | grep -i 'content-type\|x-litespeed-cache\|x-hcdn'` → `content-type: text/markdown`, no `x-litespeed-cache: hit`.
2. `curl -s -o /dev/null -D - -A 'Mozilla/5.0 (compatible; GPTBot/1.2)' https://animalwrites.in/about/ | grep -i 'x-litespeed-cache\|x-hcdn'` → no `x-litespeed-cache: hit` (an `x-hcdn-cache-status: HIT` means C2 is still needed).
3. Run `curl -s -o /dev/null -D - https://animalwrites.in/about/ | grep -i 'vary\|x-hcdn'` twice: `vary` lists `Accept`, and the second answer is `HIT`. If it never turns `HIT`, the CDN does not store pages with that Vary: set **AW SEO Plus → Settings → Markdown for agents → Vary: Accept on the HTML** to 0 and tell the developers.

## Changed

- 3 Nov 2026: step C (agents and crawlers past LiteSpeed and the CDN); `Vary: Accept` on the HTML of Home and single pages; LiteSpeed Cache noted active again.
- 2 Nov 2026: both lifetimes get fields: Animal Writes → Check site → Shared cache (CDN) (whole seconds, 0 or 60–3600, with the header pages get now).
- 1 Nov 2026: `aw_cdn_ttl` 300 → 900 s (Home, News, hubs, campaigns). The CDN settings in B were proposed the same day.
