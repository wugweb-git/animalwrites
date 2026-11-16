# RankWeb: Internal links

RankWeb adds what Rank Math does not do for internal links. Rank Math's link counter (`wp_rank_math_internal_links`,
`wp_rank_math_internal_meta`) stays Rank Math's: it reads `post_content` only, on save, and keeps no anchor text, rel
or click depth. RankWeb reads it, never writes it.

Four phases, each usable on its own:

1. **Auto-linker** (done on branch `claude/rankweb-internal-links`, 14 Nov 2026).
2. **Link audit** (built 16 Nov 2026 on branch `claude/rankweb-link-audit`): links in and out, click depth, orphans, broken links, redirects, nofollow, canonicals elsewhere.
3. Anchor mix: anchor types per target, warnings, keyword rotation.
4. Silos: hub and supporting items, virtual and circle modes.

## Phase 1: auto-linker

| Part | Where |
|---|---|
| Link keywords per item | post meta `_aw_link_keywords` (list). RankWeb content panel (block editor) and the "Link keywords" box (classic editor). Until saved, the item's Rank Math focus keywords stand in. |
| Keyword index | table `{prefix}aw_link_keywords` (keyword, keyword_norm, target_id, priority). One item's rows on save; the whole index by the daily job `aw_link_index_build` and after a Settings save that changes the linked types or the items left out. |
| Links added | `the_content` (priority 25), singular views of a linked type, the item of the main query only. `AW_Linker` (DOMDocument, no regex on HTML). |
| Cache | transient `aw_seo_link_<id>`: the item's modified time, the pages' version (`aw_link_page_version`), the settings and the input HTML; the linked HTML is kept only when a link was added. One item's keyword change raises only the index version (`aw_link_index_version`), so other pages learn a new keyword at the daily rebuild or after a Settings save; an address change (slug, parent, status) or a deleted target raises the pages' version too. The index is read once into `aw_seo_link_index` (the best five targets per keyword, no keyword text); no query per keyword. |
| Links table | `{prefix}aw_links` (source_id, target_id, url, anchor, anchor_type, origin auto/manual, rel, updated), written when an item's linked output is built (one writer per item, by a named database lock; nothing is written when the rows are unchanged) and removed when the item is no longer published or is deleted. |
| Settings | RankWeb → Settings → Internal links. |
| Tests | `tests/LinkerTest.php` (PHPUnit; `php tests/phpunit-lite.php` runs it without PHPUnit, as the Deploy check does). |

Rules: whole words only, case-insensitive, any script (letters, combining marks, digits); the longest keyword first;
never inside h1–h6, a, code, pre, script, style, button, figcaption (nor textarea, svg, noscript, template, select,
option). A keyword needs 3 characters, and one word from "Words that alone make no keyword" never links alone.
An item the HTML parser would change (camelCase SVG attributes, a div inside a p, missing end tags) is left as it is: the page is written back from the parsed tree only when its tags, attribute names and end tags equal the input's. Added links carry the class `aw-auto-link`. With no linked post types (the default) no link is added.

### Settings (defaults)

| Setting | Default |
|---|---|
| Post types linked | none (nothing is linked) |
| Most links added to one item | 5 |
| Most links to the same page in one item | 1 |
| Link only the first time a keyword appears | 1 (on) |
| Never link an item to itself | 1 (on) |
| Skip a page the item already links to by hand | 1 (on) |
| Items left out (never linked from or to) | none |

## Phase 2: link audit

Scheduled job `aw_link_audit` (daily, or "Run the audit now" on RankWeb → Internal links). It works in slices of about 20
seconds and carries on in a one-off event (`more`) until done, so no browser tab must stay open. WP-Cron only: Action
Scheduler is not used. Three steps:

1. **Collect.** Each published item of the linked post types is rendered (`the_content`), linked as a visitor would see it
   (same index and settings as the auto-linker, `aw_link_run()`) and its links are recorded in `{prefix}aw_links`.
2. **Check.** Addresses on this site that no item owns are asked for once (HEAD, no redirect followed, answer kept six
   days): 404 and 410 are broken, 3xx is a redirect. An error or a refusal is never counted as broken.
3. **Analyse.** `AW_Link_Audit` (pure PHP, `class-aw-link-audit.php`) works out each item's links in and out, clicks from the
   home page (breadth first from the items the home page and the menus link to), and the findings. The result is one row
   per item in `{prefix}aw_link_audit`.

Findings: orphan (nothing links in; a link from the home page or a menu counts), too many clicks from the home page (or no
way to reach it), too many links in the item, broken link, link through a redirect (also a link written with an old
address of an item), internal nofollow, link to an item whose canonical points elsewhere, link to an address on the
do-not-link list.

The first click is the front page (when it is a page), menus, and block navigation and template parts saved in the
database. Template files that only live in the theme are not read. Rank Math's link counter is not used.

Settings (RankWeb → Settings → Internal links): most clicks from the home page (3), most links in one item (100),
addresses not to link to (thank-you, thanks, download). Screen: RankWeb → Internal links (core list table, filter by
finding). Dashboard tile "Internal links" (orphans, items with a finding). REST: `GET rankweb/v1/links`
(flag, page, per_page), `GET links/summary`, `POST links/audit` (administrators). Table `{prefix}aw_link_audit` has its
own version option `aw_link_audit_db`; uninstall removes it, with the audit's options.
