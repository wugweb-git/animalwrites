# Pending (21 Oct 2026)

Check this list against git and the live site before acting on it. Failing AI keys are the owner's own job: never list them.

## Open
1. **Summary rewrite.** Of 1,376 published items: 830 done, 327 ok, 57 failed, 146 still short, 6 our own. Re-run the failed IDs with the workflow's `ids` input ("Summaries: rewrite those that break the current rules") and report the short and failed list.
2. **Media & PR (page 8811, /press-coverage/).** Title stays "Press Coverage of Animal Writes Campaigns – Animal Writes" (57 characters). Editor score 78. No title reaches 80; only body copy would (the focus keyword "press coverage" appears twice in about 1,000 words, the featured image alt lacks it, no table of contents). Waiting for the owner's yes to change the copy.
3. **Search Console (22 Oct 2026, `docs/seo/pagespeed-2026-10-21.md` §2).** No new errors. Validation started 22 Oct for the 2 sitemap redirects, the 1 sitemap canonical and Events (schema live in `96f463b`). The other "failed" reasons (redirect 15, canonical 14, noindex 37, 404 2) are intended; do not re-validate. Job Postings warnings: the 11 Wildlife SOS roles have no deadline or salary at the source; decide fuller summaries or no JobPosting.
4. **GA4 key events.** Starred 22 Oct: contact_sent, newsletter_signup, newsletter_optin, objection_sent, objection_confirmed. GTM v10 (22 Oct) fires contact_sent / newsletter_signup / volunteer_signup / claim_sent from the Fluent Forms listener's `form_submit`, matching `form` by name or Fluent Forms ID (contact|form-4, newsletter|form-3|form-9, volunteer|form-5, claim|form-6), with the gtag guard. The four triggers were moved off form_submit_success, so nothing counts twice. Tested on live with dataLayer pushes (form_ref TEST-CLAUDE-1…10, internal traffic): all four fired; `report` fired only form_submit. **Star volunteer_signup and claim_sent once GA4 lists them (by 23 Oct).** Generic form_submit is not starred (GA4's enhanced measurement also sends a form_submit).
5. **Yandex Webmaster.** Checked 21 Oct 2026: the Sitemap tool is read-only (Yandex outage), so nothing can be added now. A sitemap is already in its queue: https://animalwrites.in/wp-sitemap.xml (added 9 Oct), which 301s to https://animalwrites.in/sitemap_index.xml, the real one; processing takes 1 to 2 weeks. Add sitemap_index.xml itself when the tool is back.
6. **AWO list: 89 held matches** (`Claude outputs/awo-held-matches-2026-10-21.csv`): 49 similar to an existing listing, 36 with the same name in the same place, 4 posts rather than organisations. Owner decides: merge into the existing listing or add as new.
7. **One central table for volunteers.** Owner wants one central table. `wp_aw_volunteers` (357 rows) and FluentCRM (352 tagged Volunteer) both hold them. Open: which one is the central one. Nothing is deleted.
8. **Plugin audit (19 Oct, 25 findings in `Claude outputs/plugin-audit-2026-10-19.md`).** Findings 8, 9, 11, 19, 24 made on 21 Oct 2026 in this repo (9: Inbox and To-review counts cached 5 min; 11: daily visit rows over 90 days roll into months; 19: plugin hero preload removed; 24: `kits_term_url` guards every term link). 21 is item 7.
11. ~~Forms rebuilt on free Fluent Forms and FluentCRM~~ deployed 21 Oct 2026 (5546b21; forms imported, old forms in `~/backups/forms-before-2026-10-21-063334`), tested on live and test data deleted. Left for the owner: import `docs/gtm` in GTM (form_submit, Clarity form_ref, no token in page_location); place Newsletter (full) #9 elsewhere if wanted (it shows on the thank-you page after the footer sign-up). Campaign letters (21 Oct): both calls use keepalive and retry, and the sign-up also saves the letter's row (one row per send_id), so a lost call loses nothing; the WhatsApp and org boxes save 1/0. Letters before 21 Oct 12:01 have no box value (never stored; not recoverable). ALTCHA (21 Oct): LiteSpeed's REST cache (7 days) served one cached challenge to everyone, so after its first use every form with the check failed with "Couldn't verify you're not a bot"; the endpoint now calls litespeed_control_set_nocache. "Sign it" is now the Fluent Forms Campaign form (#10; owner, 21 Oct 2026); every form send is also copied raw to wp_aw_form_raw (wp aw raw-export).
9. ~~Branch `home-revision-2026-10-18`~~ deleted on 21 Oct 2026 (owner: use it or trash it): it was superseded by live, so nothing was lost. Its tip was `6adbba2`; `git branch home-revision-2026-10-18 6adbba2` brings it back while git keeps the commits.
10. **Ads: Advanced Ads only (done 21 Oct 2026, b77ef65).** Kits Core's ad system is removed; every position is an Advanced Ads placement under its old name (`tools/advanced-ads/setup.php`, applied on live; a re-run changes nothing; DB backup `~/backups/before-advanced-ads-20261021-0557.sql.gz`). ads.txt is a static file in public_html (Advanced Ads 2.0.29 cannot serve it). Site Kit's AdSense snippet is off; each AdSense ad carries its own loader. An unfilled AdSense ad takes no space (kit.css). directory-district-end and opportunity-end are kept as placements, shown nowhere. No Pro (owner): list ads, page limit and lazy loading stay dropped. **Advanced Ads switched off on live by the owner (21 Oct 2026): no ads, no ad markup on any page.** Placements, ads and settings stay in the database; switching it back on restores them as set up. Then check the ads fill once AdSense approves the site (logged out). Old options aw_ad_* and aw_ads_txt deleted (`apply cleanup`).
12. **SEO refresh 21 Oct (`docs/seo/pagespeed-2026-10-21.md` §1).** Done 22 Oct:
    - Microsoft Clarity plugin removed; one Clarity now (kits-core).
    - GTM v9: form_submit, Clarity form_ref, no /update/ token in GA4.
    - Phone filter CLS fix (`1be95a8`).
    - World Animal Day keyword split and the notice → guide link.
    - IndexNow checked: it works (the History tab is wrong). Microsoft IndexNow plugin deactivated 22 Oct; Rank Math alone logged the next publish 200 OK (18:14).
    - Leftover Clarity plugin options deleted 22 Oct (9: clarity_wordpress_site_id, clarity_project_id, brandagent_secret_key_animalwrites-in, brandagent_connected_store_url, brandagent_hmac_platform and 4 transients).
    - Google rankings: second sweep 22 Oct, 92 of 130 now live in rankings-2026-10-21.csv; 38 left (mostly city helpline, gaushala and ambulance variants) for another day.

    Open:
    - Render-blocking CSS merge; Inter preload on phones.
    - Outreach for a credit and link: TOI Mangaluru, India TV Hindi, and 7 articles without a credit.
    - Next runs: a PageSpeed API key; split the 130 Google checks over two days (CAPTCHA at 51).

## Dates
These live in WordPress: **Tools → Dates** (month calendar, add and remove) and a Dashboard box; stored in the option `aw_team_dates` (animal-writes-core `includes/team-dates.php`). Keep both lists in step.

- **5 Nov 2026**: lab rules objections close (campaign 13848).
- **7 Nov 2026**: owner decides the lab rules Post phase. Until then no closed-state or Post content, and no FAQ visibility changes, on 13848.
- **17 Nov 2026**: Directory thin-page indexing re-check (10:00) and the 8 tracked keyword positions (11:00); both are scheduled tasks.
- **1 Dec 2026**: FAQ holdout ends (started 20 Oct, 6 weeks); then compare GSC and GA4 ("Has FAQ", `faq_group`) before rolling out to 100%.

## Done (21 Oct 2026)
FAQ: all 167 topic FAQs and the 4 new ones (directory free, volunteer 18+, claims 4 to 5 days, opportunities free) are approved. AWO list imported (559 new, 63 filled). ngobase: 296 of 306 live; the 10 drafts are held duplicates, never publish. Forms send no email except the Remove-my-data confirmation link. Share images are never AVIF. Fallback picture is the grey logo (attachment 17878). hPanel Git auto-deploy is on.
