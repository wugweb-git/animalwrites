# Pending (21 Oct 2026)

Check this list against git and the live site before acting on it. Failing AI keys are the owner's own job: never list them.

## Open
1. **Summary rewrite.** Of 1,376 published items: 830 done, 327 ok, 57 failed, 146 still short, 6 our own. Re-run the failed IDs with the workflow's `ids` input ("Summaries: rewrite those that break the current rules") and report the short and failed list.
2. **Media & PR (page 8811, /press-coverage/).** Title stays "Press Coverage of Animal Writes Campaigns – Animal Writes" (57 characters). Editor score 78. No title reaches 80; only body copy would (the focus keyword "press coverage" appears twice in about 1,000 words, the featured image alt lacks it, no table of contents). Waiting for the owner's yes to change the copy.
3. **Search Console.** Page indexing: validation has started on all 8 reasons (6,044 discovered not indexed, 74 crawled not indexed, 1 "duplicate without user-selected canonical"). Job Postings: employmentType passed, 0 invalid, 1 valid; warnings still Not started: addressLocality, streetAddress, postalCode, validThrough, baseSalary.
4. **GA4 key events.** A one-time scheduled task stars the 7 approved events on 21 Oct 2026 at 12:00 IST (`aw-ga4-mark-key-events`). Check it ran.
5. **Yandex Webmaster.** Add the sitemap (https://animalwrites.in/sitemap_index.xml).
6. **AWO list: 89 held matches** (`Claude outputs/awo-held-matches-2026-10-21.csv`): 49 similar to an existing listing, 36 with the same name in the same place, 4 posts rather than organisations. Owner decides: merge into the existing listing or add as new.
7. **One central table for volunteers.** Owner wants one central table. `wp_aw_volunteers` (357 rows) and FluentCRM (352 tagged Volunteer) both hold them. Open: which one is the central one. Nothing is deleted.
8. **Plugin audit (19 Oct, 25 findings in `Claude outputs/plugin-audit-2026-10-19.md`).** Findings 8, 9, 11, 19, 24 made on 21 Oct 2026 in this repo (9: Inbox and To-review counts cached 5 min; 11: daily visit rows over 90 days roll into months; 19: plugin hero preload removed; 24: `kits_term_url` guards every term link). 21 is item 7.
9. **Branch `home-revision-2026-10-18`** (5 commits, local only): superseded by live (page 57 has the same section order; live patterns are newer). Keep, do not merge (rule 1 in CLAUDE.md).

## Dates
These live in WordPress: **Tools → Dates** (month calendar, add and remove) and a Dashboard box; stored in the option `aw_team_dates` (animal-writes-core `includes/team-dates.php`). Keep both lists in step.

- **5 Nov 2026**: lab rules objections close (campaign 13848).
- **7 Nov 2026**: owner decides the lab rules Post phase. Until then no closed-state or Post content, and no FAQ visibility changes, on 13848.
- **17 Nov 2026**: Directory thin-page indexing re-check (10:00) and the 8 tracked keyword positions (11:00); both are scheduled tasks.
- **1 Dec 2026**: FAQ holdout ends (started 20 Oct, 6 weeks); then compare GSC and GA4 ("Has FAQ", `faq_group`) before rolling out to 100%.

## Done (21 Oct 2026)
FAQ: all 167 topic FAQs and the 4 new ones (directory free, volunteer 18+, claims 4 to 5 days, opportunities free) are approved. AWO list imported (559 new, 63 filled). ngobase: 296 of 306 live; the 10 drafts are held duplicates, never publish. Forms send no email except the Remove-my-data confirmation link. Share images are never AVIF. Fallback picture is the grey logo (attachment 17878). hPanel Git auto-deploy is on.
