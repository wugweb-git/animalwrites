# Search reading cadence

What to read, and how often, in AW SEO Plus → Search Insights. Each read is a few minutes; nothing here changes the site on its own.

## Weekly (Monday)

| Where | What to look at | What it leads to |
|---|---|---|
| Overview | The source notices first: a source that failed, is late, ran a baseline or kept nothing. Then the Priorities, Quick wins and Movers boxes. | A failed source is fixed or switched off before its figures are trusted. |
| Keywords → Quick wins | Keywords at position 8–20 with enough impressions (stored as `quick_win` by the daily calculation). The count is the week's number to compare. | A title, description or section update on the page that ranks. |
| Keywords → Conflicts | New conflicts since last week (status `conflict`). The focus-keyword API refuses a keyphrase another page owns, so new ones come from hand edits or from Google showing a page that does not own the keyword. | One owner per keyword; the others drop the phrase. |
| Rising | New terms (status New). Each is marked Noted (worth a page or an update) or Dismissed (noise, celebrity tails, unrelated). | A News explainer, an FAQ answer or a guide update; never a keyphrase on its own. |

## Monthly

| When | Where | What |
|---|---|---|
| 1st | Animal Writes → Dates | "Search Console Links read": `wp aw-si links add` with the read date. |
| 2nd (after 07:40 IST) | Settings → Bing searches | Count Bing searches without a keyword record; add records for the useful ones. |
| Any day | Check list | The live Google check (bookmarklet) for the month's keywords. |
| End of month | Settings → Snapshot | Download the monthly snapshot and keyword map. |

## Thresholds

Rising flags Wikipedia pageviews at 2× the usual level and GDELT at 3×. GDELT is switched off while it refuses our requests (Settings → News (GDELT)). The thresholds are reviewed on the date in Animal Writes → Dates.
