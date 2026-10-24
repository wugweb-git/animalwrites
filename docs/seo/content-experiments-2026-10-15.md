# Title and description experiments: plan, 15 October 2026

**History (snapshot of 15 Oct 2026); current state: docs/pending.md and docs/plugin-map.md.**

A plan only. Nothing live has been changed for it. It follows the experiment-design rules in `sanity:content-experimentation-best-practices` (hypothesis, one primary metric, fixed duration, no peeking), adapted to a site with no A/B tool: WordPress, Rank Math, and Search Console.

## 1. What we can and can't test

- **No split traffic.** Google shows one title and one description per URL, so a true A/B test on one page isn't possible. We run **before/after tests** on a page, and compare against **control pages** that we leave alone over the same weeks.
- **The variable is the Rank Math title or description,** one field per test. The H1, body, URL and internal links stay the same during the test, otherwise we can't tell what made the difference.
- **Google may rewrite our title or description.** Before judging a test, check in a live search that Google is actually showing the new one.

## 2. What to test first

Choose pages with impressions but a low click-through rate (CTR) for their position. Pages with no impressions can't show a result.

| # | Pages | Hypothesis | Change |
|---|---|---|---|
| 1 | The three guides at about position 5 (ABC Rules, reporting cruelty, injured animal) | We believe putting the question searchers type into the title will raise CTR, because these pages rank but are clicked less than position 5 suggests. | Title only. Same keyword phrase; question-led, such as "Who to Call for an Injured Animal in India", still within 55–58 characters with one separator. |
| 2 | Directory place pages at about position 8 (the largest group of non-brand impressions) | We believe a description that states what the page lists, with a count ("12 animal welfare groups in Gwalior, with phone numbers where known"), will raise CTR. | Description only, through the template, so many pages change at once. Use a few similar place pages as controls. |
| 3 | World Animal Day guide (13797), in September and October 2027 | We believe a title naming the year's theme will get more clicks in the October peak. | Title only. Run it once there are impressions; this year's spike has already passed. |

Leave alone: Library items (their titles follow the document's own name), News (short-lived), and any page with fewer than about 100 impressions in 28 days.

## 3. How to measure in Search Console

1. **Before changing anything:** Performance → Search results → filter **Page** = the exact URL. Note clicks, impressions, CTR and average position for the last 28 days. Also export **Queries** for that page.
2. **Change the field** in Rank Math. Write in the table below the date, old value, new value and hypothesis. Use URL Inspection → Request indexing so Google picks up the change.
3. **After the run:** compare the same length of time before and after, starting the day Google showed the new title.
4. **Primary metric: CTR at a similar position.** CTR only counts if average position moved by less than about 1 place. If position moved more, the result reflects ranking, not the title.
5. **Guardrails:** clicks shouldn't fall, and impressions for the focus keyword shouldn't fall.
6. **Controls:** pick 3–5 similar pages you didn't change. If their CTR moved the same way, the change came from the season or Google, not from us.
7. Search Console data runs about 2 days behind; read it no earlier than 3 days after the end date.

## 4. How long to run a test

- **At least 28 days,** in whole weeks, so weekdays and weekends both count. For pages with a few hundred impressions a month, run **6–8 weeks**.
- **Set the end date before you start,** and don't stop early because the first week looks good.
- **Skip unusual periods:** Google core updates (pause and restart if one rolls out), and festivals or news spikes that affect the page's topic (for example kite season for bird pages, or a Supreme Court hearing for the stray dogs pages).
- **Rough sample check:** to see a CTR change from 3% to 4.5%, a page needs about 2,000–2,500 impressions in each period. Below that, record the result as "not enough data", not as a win.

## 5. How to avoid cannibalisation

- **One focus keyword per page** across the site. Before a new title uses a phrase, search the Rank Math focus keywords for it (15 Oct example: "1962 helpline" was taken out of the two emergency guides when the 1962 guide went live).
- **A test title must not use another page's focus keyword.** Variants change wording, not the target.
- **After each test,** check Search Console → Queries for the target phrase → Pages. If two of our URLs now share the query, return to the old title and link the weaker page to the stronger one.
- **Don't test two pages in the same cluster at the same time** (for example the two emergency guides), because a change on one moves the other.

## 6. Test log

Keep one row per test here (or in the Notion tracker). Write down every result, including "no change"; that is still useful.

| Page | Field | Old | New | Start | End (fixed) | Controls | CTR before / after | Position before / after | Result | Kept? |
|---|---|---|---|---|---|---|---|---|---|---|
| | | | | | | | | | | |

## 7. Decision rule

- **Keep** the new value if CTR rose by 20% or more, position stayed within about 1 place, the controls didn't rise the same way, and clicks didn't fall.
- **Revert** if clicks fell or the page started sharing queries with another of our pages.
- **Otherwise inconclusive:** keep whichever value follows the site title rules more closely, and test another page next.
