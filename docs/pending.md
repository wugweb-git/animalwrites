# Pending (24 Oct 2026)

Check this list against git and the live site before acting on it. Refer to items by their title, not their number (owner, 24 Oct 2026); the numbers are only the order of the list. Failing AI keys are the owner's own job: never list them. This list is mirrored in Notion, "8. Status & Pending" (Animal Writes 2.0); the two are kept in step.

## Open

### Only the owner (sign-ins, keys, files)
1. **AW_GITHUB_DISPATCH_TOKEN** in wp-config.php (fine-grained token, workers repo only, Actions read/write). Not set on live (checked 24 Oct): Hostinger has never started sourcing (no cron line, `aw_dispatch_log` empty) and Run now on the Workers tab is off.
2. **Workers repo protection:** add the deploy key (public key under Deploy keys with write access; private key as an Actions secret), so Sources sync and Keepalive can still commit once `main` is protected.
3. **NGO Darpan CSVs** (animal-welfare category) into `data/darpan/` in the workers repo, then run the workflow "NGO Darpan import" (dry run first; proposals go to the review queue with source, file and date). No-contact profiles by state: Rajasthan 1,049, Uttar Pradesh 544, Madhya Pradesh 460, Haryana 343, Gujarat 271, Maharashtra 235, Tamil Nadu 218 (71% of them). Tested only on a sample file, so a real header may need one small mapping change.
4. **Delhi HC order of 16 Sep 2025:** type the CAPTCHA in the court's search (W.P.(C) 9350/2025) so the official PDF can be added (Library #13904 waits for it).
5. **Yandex:** add `sitemap_index.xml` when the Sitemap tool works again (read-only since 21 Oct; wp-sitemap.xml is queued and 301s to it). **Bing Webmaster:** sign in.
6. **GA4:** star volunteer_signup and claim_sent once GA4 lists them. (Starred already: contact_sent, newsletter_signup, newsletter_optin, objection_sent, objection_confirmed.)
7. **Vercel key:** only if that AI provider is wanted.

### Decisions waiting on the owner (recommendation in brackets)
8. **AWO list: 45 rows for the owner** (`Claude outputs/awo-held-decisions-2026-10-24.csv`: 33 no phone on one side, 6 similar names with different phones, 4 posts, 2 phone matching another listing).
9. **Swapna Foundation (#46):** which organisation it is (blocks its enrichment).
10. **Lab rules Post phase:** decide by 7 Nov. Until then no closed-state or Post content, and no FAQ visibility changes, on 13848.
11. **Claude Code SSH permission** ("any SSH command on the production server without asking", added 24 Oct in `Animal Writes/.claude/settings.local.json`): keep, or narrow to read-only WP-CLI on the Hostinger host [narrow]. Left as it is until the owner decides.
12. **Letter templates:** keep all 4 (titles for /library/english/ and /library/hindi/).

### Claude Code: next work (bigger work: own branch + PR + review gate; small fixes straight onto wordpress-dev)
13. **After the token:** Hostinger cron line for sourcing every 3 h (`wp eval 'aw_dispatch_workflow("sourcing.yml", array("dry_run" => "false"));'`) and Sourcing `late_after_h` to 6 in both registries; then focus keywords 04:00, SEO scores 05:15 and research Mon 09:10 IST, with their GitHub schedules moved about 2 h later in a small workers PR (registries updated together); test Run now with news-title-case (a dry run). backup-offsite is not dispatched.
14. **Workers repo:** ruleset on `main` (PR + `test` check; bypass for admins and the deploy key); Sources sync and Keepalive push with the key.
15. **Workers tab fixes:** count names keep their spaces (`already sentence case` shows as `alreadysentencecase`); news-title-case runs labelled as dry runs; Sources sync started from the site when the lists are saved, plus one daily run, instead of hourly.
16. **aw-ship.sh:** move it into the repo (`tools/aw-ship.sh`); last step `?aw_deploy_nudge=ship-<sha>` instead of the command-line purge.
17. **AW Sourcing:** Keywords lists as a list table; the Settings API for the Sourcing settings forms (press, categories, sections, summary rules, relay, enrichment, the keyword switches).
    - Not met yet (owner instructions of 24 Oct, found in the end-of-session audit):
      - Per-item Search Console figures over 3 months as well as 28 days: the Sourcing keywords tab (#32) shows 28 days only.
      - Hand-built tables still in aw-sourcing: the Rejected tab, the Change log, AI models "Daily caps on free models" and "Keys by group", the Summary rules length table.
      - Custom CSS left: the `.aw-src-lists` rule in admin_head (keyword and press textareas); inline styles on older Sourcing tabs not audited.
      - Plugins-list problem rows only on AI models; not yet on Blocked publishers, Off sources or Rejected items.
      - kits-registry.json: `aw-pub`, `aw-h` and `aw-ai-status` registered but unused.
      - The fixed search "stray dogs" is mirrored in aw-sourcing (`AW_SOURCING_EXTRA_SEARCHES`) from the workers' `run.py`; the workers repo does not say so.
      - #32 not yet checked on live data: Search Insights has had no successful Search Console run on live (until then the tab shows a notice and the suggestions list is empty).
      - PHPCS ran only from a temporary install; not in CI.
18. **Search Insights:** check on live after the first 07:10 IST run (or Settings → Run now): seed marked done, Search Console "OK", the Movers box filling after about 4 runs. Phase 1 leftovers live (PR #39). Phase 2 built 25 Oct (own PR): GA4 and PageSpeed through Site Kit, Clarity, searches on the site, Pages tab, redirects counted as their target. **Owner:** add `AW_CLARITY_TOKEN` to wp-config.php (Clarity → Settings → Data Export) for the Clarity part; set the Clarity flag thresholds after a month of data. Phase 3 in its own session. No Hindi or Hinglish work until phase 5.
19. **Enrichment rules:** list fields propose the union (never drop current values); no proposals while a listing's identity is unresolved. From 1 Nov the 1,773 listings checked while Google was off (marked Google-pending) come back, no-contact ones first (district searches cost 2 requests each; watch the 950 a month). The live Google block (rating, hours, open now) stays hidden: it needs a paid request per view (no paid plans). Not done from the first brief: Rich Results test and 375px screenshots of an enriched listing.
20. **Summary rewrite:** report the short and failed items after the current runs (24 Oct: 833 done, 402 ok, 146 short, 6 our own, 61 failed; the 64 twice-failed items were reset to 0 tries, old value in `aw_summary_rw` as `tries_before_2026_10_24`, and the half-hourly runs take them).
21. **Observance-day pages:** the 10 in `Claude outputs/pending.md`, World Animal Day first.

### Waiting on dates (see Dates below)
22. **1 Nov:** Google Places free quota returns (950 requests a month, no paid plan); no-contact profiles first.
23. **5 Nov:** lab rules objections close. **7 Nov:** the owner's Post-phase decision.
24. **17 Nov:** indexing re-check (6,752 "discovered, not indexed" should fall after the no-contact noindex of 24 Oct) and the 8 tracked keywords.
25. **1 Dec:** FAQ holdout ends; compare Search Console and GA4 ("Has FAQ", `faq_group`), then roll out to 100%.
26. **AdSense approval:** ads stay off on live until the owner switches Advanced Ads on; then check the ads fill (logged out).

### No source yet (parked until one appears)
27. **Feeder Network, Adoption Network and Veterinary Clinic listings; data.gov.in (503); 7 Odisha districts without an SPCA.**

### Carried over from older lists (checked 24 Oct)
Status checked on 24 Oct against the live site, WP-CLI (read only) and git; the ones found done are under Done (24 Oct).
28. **Home sections taller than one screen** (phone): at 375×812, 6 of 9 home sections are taller than the screen (news 1686px, law 1800, learn 1572, find 816, story 1008, last 1013). Fixed on 9 Oct (32f3f4c), back since the 18 Oct rebuild (8406aef).
29. **Footer on phone ≤ one screen:** `.kit-site-footer` is 1871px tall at 375×812 (the 9 Oct fix 402bf45 has been outgrown).
30. **Directory Search button yellow:** live it is `kit-btn` (primary, ink); the home "Find" button uses `kit-btn--brand`.
31. **"About: sender" column label** (Mailweb list): columns are From | About (the subject) | Date (`list-table.php:32`); the owner to say which change was wanted.
32. **Staging:** staging.animalwrites.in exists (login-only, noindex) but its checkout is the deleted `ux-audit` branch at 9628efd and its database is from 15 Oct; not part of the deploy chain.
33. **UptimeRobot monitor:** no monitor found (only its user agent is recognised in code, d0c6de6).
34. **Performance regression check:** CLS re-measured and fixed on 24 Oct; a full PageSpeed re-run waits for a PageSpeed API key.
35. **Lost form submissions before fab4e94 (8 Oct):** no recovery recorded; pre-8 Oct server access logs would settle it if Hostinger still keeps them.
36. **Opportunities workers:** still paused (workers `run.py:435`, owner 9 Oct); last opportunity published 7 Oct.
37. **Press discovery:** Google News press searches run (press-gnews.yml); the Brave search stays off (no key; no paid plan, owner).
38. **High Court judgments as documents:** the Supreme Court is a source (`judgments-supreme-court`); no High Court source, HC reports arrive only as court News.
39. **Mail:** Hostinger webhooks last arrived 21 Oct 06:04 (the 5-minute `mailweb_crm_sync` covers gaps); FluentCRM free prints "Powered by FluentCRM" in emails; support@ and noreply@ have Mailweb pictures but no Gravatar or BIMI, so recipients do not see them.
40. **Google News Publisher Center; Semrush keyword run** (no API units on the account): not done.
41. **Revenue:** affiliate plumbing (/go/ is 404), Amazon Associates and Cuelinks, sponsors (/sponsor/ and /advertise/ are 404), paid opportunity posts, the first newsletter with a sponsor slot (0 FluentCRM campaigns): none started.
42. **Directory data clean-up:** of 6,794 published listings, 557 have a state but no district (298 on 8 Oct; includes state-wide 1962 MVUs), 14 no place, 227 no state; duplicate names: 55 groups (133 listings) within the same district. The 11 duplicate groups kept as separate organisations are not recorded anywhere: owner's list needed. Project Nayna (52) has no type; Healing Saathi (53) has a second listing "Healing Saathi, Dehradun" (18321); Tabassum (44) is Animal Shelter: confirm.

## Decided: never / dropped (owner, 24 Oct 2026)
- Removing the Volunteer tag from the 184 campaign senders who unticked the box (and the matching campaign-code change).
- The hPanel Git auto-deploy check (`aw-ship.sh` checks out the gated commit itself).
- The real-phone check (Gmail app button, share buttons, the Beta badge in the phone menu).
- Rotating the Hostinger Mail API token.
- Featured listings: featured-first ordering, pricing after the free 90 days, the CSR one-pager.
- "Index or noindex News": settled, all News is indexed.
- The Emergency help photo: kept as it is.
- Restoring the Fluent Forms demo forms 1 and 2 (backup stays in `~/backups/fluentforms-2026-10-16.sql.gz`).
- The carried-over Content items (fact-checking the 7 crisis guides, per-state guides, legal review of Privacy and Terms, the 1.0 invitation to 88 volunteers, AWBI items 142, 144, 145).

## Decided: never (owner, 22 Oct 2026)
- Rewriting the 11 Wildlife SOS job summaries, or dropping their JobPosting markup.
- Press outreach asking for an Animal Writes credit or link (TOI Mangaluru, India TV Hindi, and the articles without a credit).

## Dates
These live in WordPress: **Tools → Dates** (month calendar, add and remove) and a Dashboard box; stored in the option `aw_team_dates` (animal-writes-core `includes/team-dates.php`). Keep both lists in step.

- **5 Nov 2026**: lab rules objections close (campaign 13848).
- **7 Nov 2026**: owner decides the lab rules Post phase. Until then no closed-state or Post content, and no FAQ visibility changes, on 13848.
- **17 Nov 2026**: Directory thin-page indexing re-check (10:00) and the 8 tracked keyword positions (11:00); both are scheduled tasks.
- **1 Dec 2026**: FAQ holdout ends (started 20 Oct, 6 weeks); then compare GSC and GA4 ("Has FAQ", `faq_group`) before rolling out to 100%.

## Notes to keep
- **GA4 test events on 22 Oct 2026 (cannot be deleted; exclude when reading 22 Oct).** About 18:20–18:30 IST on /contact/?gtmtest=1, 10 pushes with form_ref TEST-CLAUDE-1 to TEST-CLAUDE-10 (traffic_type internal). GA4 received contact_sent 2, newsletter_signup 2, volunteer_signup 2, claim_sent 2 and form_submit 10. Exclude with event parameter `ref` begins with TEST-CLAUDE, or subtract 2 from each of the four key events for 22 Oct. **Rule (owner, 22 Oct): never send test or simulated events to live GA4; verify GTM only in Preview / Tag Assistant or by reading the config.**
- **Search Console (22 to 24 Oct).** Validation started for the 2 sitemap redirects, the 1 sitemap canonical, Events and the 26 district × type pages that were noindex on 9–10 Oct. Intended, do not re-validate: redirect 15, canonical 14, 404 2, duplicate 1. Job Postings warnings stay (Decided: never).
- **Rollback:** a rollback to a release older than 24 Oct must be purged by hand (rollback.yml warns); newer releases are purged by `deploy-purge.php`.

## Done (24 Oct 2026)
- **Card titles as H3 and press titles in sentence case (b65f596).** Every card list has an H2 (its own, or one for screen readers) and its card titles are H3; press ingest keeps the worker's title flag, and `wp aw news-titles apply --type=aw_press` applies a reviewed CSV.
- **Stale redirect #705** deleted at the owner's word; the old address returns 404.
- **Enrichment review queue:** empty; the 24 Oct proposals were decided (one approved, seven rejected).
- **Gemini chats:** all 27 chats left by the enrichment lookups deleted by ID.
- **Find help place detection follow-ups (82a29da, 8c4432c).** Delhi's direction districts read and detect as "South Delhi" etc.; a place counts only from the title or the first sentence, or when named 2+ times; neighbourhood aliases for big cities (Kakkanad → Ernakulam). `wp aw-places detect --redo-auto` changed 157 items (80 lost a place named only in passing), pages purged. Related More cards favouring the same district or state: accepted, kept. Remote branch find-help-band-labels deleted (merged).
- **Research 12573** (human-elephant conflict, Assam): date set to 7 Jan 2026, the journal publication date, instead of Europe PMC's indexing date (1 Feb 2026); `aw_source_date` and the post date changed, page purged.
- **Carried-over items found done (checked 24 Oct):** no stray ✕ in the header (44d88a4); hub band gap removed (6b3967f, dabec82); "Guides and research" Option B (72b5131); Directory hub head height (816a9e0); primary nav aria-label "Main" (live); Library card on phone (343×209 at 375, no overflow); every page on head + main (67b9d3d); one www redirect (http://www → https://animalwrites.in in one 301); sample listing 17069 noindex and out of lists (d76f0c1, 521afe6); the 272 held drafts cleared (252 trashed 12 Oct, 34 on 14 Oct; 22 News drafts left); Gazette notification feeds (`gazette-of-india` in sources.yaml); topic descriptions and photos (19 categories, 36 term images); Corrections page (/corrections/, page 8760); the Directory "Service" filter; tag names in Title Case (a9ea4a0); Boarding and Fostering type removed (e29d889, the service tag stays); type-page title pattern ("… by State and District", 651c7d1, e11f3cf).
- **Docs and branch tidy (owner, 24 Oct).** One sequential numbering in this list (items by title); finished items moved here. Deleted remote branches, each merged or replaced and with no commit missing from production (tip in brackets, restorable from it or its PR): deploy-purge-cache (a77db65, replaced by #20), search-insights (f51d5ba, replaced by #17 and #32), fluent-css-preload (4b5e48c, #16 and #21), aw-mail-core-cleanup (930a929), find-help-near-you-1024 (9e40327, feature live), ux-audit (9725024). `pr-screenshots` is kept. The plugin map gained cdn-cache.php and deploy-purge.php (PR #35).
- **CI on pull requests (owner, 24 Oct).** The Deploy check runs on PRs into wordpress-dev (no deploy steps; the live-site picture check is skipped there) and `check` is required before merge (branch protection; admins can still push directly). `aw-ship.sh` refuses to deploy unless the commit's `check` is success.
- **Purge after every deploy (PR #20, `deploy-purge.php`).** The first web request after the deployed commit changes purges LiteSpeed once (option `aw_deployed_head`); the Deploy check and rollback.yml request the home page with a one-off `?aw_deploy_nudge=` every 30 s for 5 minutes. Skipped for CLI PHP and when headers are sent.
- **Home page speed.** CLS at 768 fixed (PR #24, 600d825): the band's artwork now comes after its text; live max CLS 0.0001 (375), 0.0022 (768, the header), 0 (1200) over 12 throttled loads each. Render-blocking CSS on home down from 7 to 2 requests (kits-bundle and aw.css): campaign.css and campaign-record.css rules inline (22 Oct), the navigation and cover block CSS inline (`6397340`), tokens.css inline on every page, Fluent Forms' CSS added on load or first scroll except on form pages (PR #16; the thank-you page counts as a form page, PR #21). Critical CSS for kits-bundle and aw.css: not now (owner). Inter preload on phones stays off (75f5c74).
- **Indexing: discovered, not indexed (6,752; owner OK).** Profiles with no contact are noindex and out of the sitemap and come back when a contact is added (`514966b`); the 901 profiles with Search Console impressions stay indexed (`aw_seo_keep_listings`). Opportunities, News and Library `?state=` lists are noindex, follow, canonical the unfiltered list (PR #14, `465e0e3`). Claim forms (/claim/?listing=) are noindex, follow, out of every sitemap (Rank Math, page 12587). Empty category and tag pages are noindex through Rank Math; pages with 1 or more items are indexed.
- **One central table for volunteers (PR #19, 9308107).** FluentCRM is the central table; `wp_aw_volunteers` is a read-only backup (WP Admin → Volunteers (backup), view and export); "Remove my data" still deletes both. `wp aw vol-to-crm` dry run: all 386 rows already in FluentCRM.
- **Find help near you (`84b5797`, feature `a8b772a`).** News, Library and guides get a place from state and district names (find-help.php; `wp aw-places detect` stored 884 places); More links that state and its districts; the lab rules campaign has a band of 12 states.
- **AW Sourcing plugin.** Step 1 (b0bb47b): sourcing moved out of core and Kits Core into `plugins/aw-sourcing`. Merged and live: #3 (per-feed and per-keyword records), #5 (keyword suggestions), #7 (Site Health "Sourcing ran on time"), #10–#13 (Publishers, source lists, To review, AI models as core list tables), #25, #32 (Search Insights → Sourcing keywords tab). Migration check of step 1 passed (24 Oct): runs at 12:56 and 15:55 UTC succeeded, items record aw_feed and aw_ingested_at, /ingest and /relay answer.
- **Search Insights phase 1 (PR #17, `e6fa41e`).** Tools → Search Insights: Overview, Keywords, Searches (the old Search demand screen redirects there), Settings; Search Console by day through Site Kit with a 90-day backfill; `wp aw-si`.
- **Workers tab (site #9, #18; workers #2, #3, #5).** Settings → Sourcing → Workers lists all 18 workflows with their last run, result, counts and Run now; every workflow reports to `POST aw/v1/worker-run`; alerts on the tab, the Scheduled tasks box, Site Health and one email. Late limits: Sourcing, Sources sync, Summaries 12 h; Focus keywords, SEO scores, Site backup, Directory enrichment 36 h; Research 9 days; Keepalive 35 days; WordPress cron, Tests and by-hand never. The Deploy check fails when `workers-registry.php` differs from `docs/workers.json` in the workers repo.
- **Directory enrichment.** 3,396 of 6,794 listings checked by 24 Oct (1,558 that day; 4,088 still with no contact), values applied with their source URL and date (address 2,780, place link 484, phone 162, website 137, email 105 among them); no-contact profiles queued first (`01a2a86`). Browser lookups for AWBI-register trusts paused (owner asks for more batches).
- **Content (owner's yes).** Guides #17307 (dog bite: three new questions, Hindi summary) and #17308 (NGO Darpan: Hindi summary). FAQ blocks on the 5 Resources guides that had none (all 32 guides with a body have one). Library FAQs: 44 of 96 documents (was 11), 73 field values from official texts (old values in `aw_libfaq_backup_2026_10_24`). Media & PR intro ends "Seen press coverage we have missed? Send us the link."
- **AWO list: 89 held matches (PR #15).** 42 merged, 2 added, 45 for the owner (item "AWO list: 45 rows for the owner").
- **News ingestion stall, 21–22 Oct (fixed).** Workers PR #1 and site PRs #2, #16, #20; AI keys relabelled by status, 10 NVIDIA keys added; a model 404/410 drops only that key and model; research duplicates matched on scheme/host and DOI; `aw/v1/exists` needs Editor.
- **22 Oct outage.** The AI Provider for Google plugin 1.2.0 fataled on every load (missing core AI client class); the owner deleted it on 22 Oct; the site boots cleanly.
- **SEO refresh 21–22 Oct.** One Clarity (kits-core; the Microsoft Clarity plugin and its options removed); GTM v9/v10 form events; phone filter CLS fix (`1be95a8`); Microsoft IndexNow plugin deactivated (Rank Math sends IndexNow); 130 Google rankings checked (next run monthly, split over two days: CAPTCHA at query 51).
- **Ads: Advanced Ads only (21 Oct, b77ef65).** Kits Core's ad system removed; every position is an Advanced Ads placement; ads.txt is a static file. **Advanced Ads is switched off on live (owner)**: no ads and no ad markup; placements and settings stay in the database.
- **Plugin audit of 19 Oct.** Findings 8, 9, 11, 19, 24 made on 21 Oct; 21 became "One central table for volunteers".
- **Forms on free Fluent Forms and FluentCRM (21 Oct, 5546b21).** Forms send no email except the Remove-my-data confirmation; every send is copied raw to `wp_aw_form_raw` (`wp aw raw-export`); the ALTCHA REST cache bug fixed.
- **Directory imports, 18–21 Oct** (`Claude outputs/directory-import-2026-10-18/OUTCOMES.md`): official lists with dry runs, mysqldump backups and JSONL logs (`~/aw-import/`, undo with `tools/directory/undo_import.php`).
- **Branch `home-revision-2026-10-18`** deleted on 21 Oct (tip `6adbba2`).

## Done (21 Oct 2026)
FAQ: all 167 topic FAQs and the 4 new ones (directory free, volunteer 18+, claims 4 to 5 days, opportunities free) are approved. AWO list imported (559 new, 63 filled). ngobase: 296 of 306 live; the 10 drafts are held duplicates, never publish. Forms send no email except the Remove-my-data confirmation link. Share images are never AVIF. Fallback picture is the grey logo (attachment 17878). hPanel Git auto-deploy is on.
