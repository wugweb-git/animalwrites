# RankWeb: Internal links

RankWeb adds what Rank Math does not do for internal links. Rank Math's link counter (`wp_rank_math_internal_links`,
`wp_rank_math_internal_meta`) stays Rank Math's: it reads `post_content` only, on save, and keeps no anchor text, rel
or click depth. RankWeb reads it, never writes it.

Four phases, each usable on its own:

1. **Auto-linker** (done on branch `claude/rankweb-internal-links`, 14 Nov 2026).
2. **Link audit** (built 16 Nov 2026 on branch `claude/rankweb-link-audit`): links in and out, click depth, orphans, broken links, redirects, nofollow, canonicals elsewhere.
3. **Anchor mix** (built 17 Nov 2026 on branch `claude/rankweb-anchor-mix`, on top of the audit branch): anchor types per target, warnings, keyword rotation.
4. **Silos** (built 17 Nov 2026 on branch `claude/rankweb-silos`, on top of the anchor-mix branch): hub and supporting items, virtual and circle modes.

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

## Phase 3: anchor mix

Every link in `{prefix}aw_links` gets an `anchor_type`, written with the row. The sorter is `AW_Anchor`
(`class-aw-anchor.php`, pure PHP). First match wins:

| Type | Rule |
|---|---|
| image | the link holds an image and no text (the ALT text is the anchor) |
| naked | the anchor is an address (`https://…`, `www.…`, `example.org/page`) |
| generic | one of the editable list (click here, read more, this page…), compared as normalised words |
| exact | equals the target's **main** keyword (its first link keyword) |
| branded | contains a brand name (setting), as whole words |
| long_tail | more words than the limit (setting, 6) |
| partial | contains a word of 3 letters or more of any of the target's keywords (so another keyword of the target is partial, not exact) |
| other | anything else |

For each target the audit counts the types (links to items only) into the transient `aw_seo_anchor_mix`. RankWeb → Internal
links → Anchor mix lists targets with their shares and warns when exact match is over 20% or any other type is over 60%.
A target with fewer than 5 links in gets no warning (a share of two links says nothing); the minimum is a setting. The
guides disagree on the right exact share (1 to 5%, 20%, 60%), so all three limits are settings.

Rotation: before it links a keyword the auto-linker asks `aw_anchor_ok()` (one transient read, no query per keyword). A
keyword that would put its target over a limit is skipped, so the target is linked by another of its keywords, or not
at all in that item. The snapshot is the last audit's, so it changes once a day; a page already cached keeps its links
until it is built again (the thresholds and the brand, generic and long-tail settings are part of the cache key).

Settings (Internal links): most exact match (20), most of any other type (60), fewest links before a warning (5), brand
names (Animal Writes), generic anchors, words in a long-tail anchor (6). REST: `GET rankweb/v1/links/anchors` (warn,
page, per_page). The Dashboard tile "Internal links" shows the items over a limit as a detail line. Existing rows get
their type at the next audit.

## Phase 4: silos

A silo has a name, one hub item, its supporting items in order, and a mode. **Storage: two custom tables, not a private
taxonomy.** `{prefix}aw_silos` (name, hub, mode; the hub is unique) and `{prefix}aw_silo_items` (the item ID is the primary
key, so the database itself keeps an item in one silo only; `position` is the circle's order). A taxonomy would show in the
editor, the REST API and the sitemaps, allow one item in several silos, and keep no order. Own version option
`aw_silo_db`; uninstall removes the tables (written to the backup first).

| Mode | Links |
|---|---|
| virtual | a supporting item links to the hub and to the other supporting items (the ones after it first, so links spread round the silo, at most "Silos: most links an item shows", 10); the hub links to its supporting items |
| circle | each item links to the next one and back to the hub; the last one only to the hub; the hub links to the first |

- **On the page:** the end of the content of a silo member gets a `<nav class="aw-silo-links">` list (`the_content`, after
  the auto-linker; singular main view only). No CSS is added: it is a plain list under a heading.
- **Between silos:** an item in one silo links to another silo's hub only. The auto-linker skips such a target and tries
  the next candidate of the keyword, and tries the item's own silo's targets first. Items in no silo are not restricted.
- **Audit:** the silo links count as links (they are not saved in the content), so they give in-links, depth and
  orphan results. A new finding "Link into another silo's supporting item" flags any link (the auto-linker's or a hand-made
  one) from an item in one silo to another silo's supporting item. This is wider than the plan's wording (supporting item
  to supporting item): a hub is held to the same rule as the auto-linker.
- **Checks on the Silos tab:** fewer than 5 items (hub counted), and addresses deeper than 3 folders
  (`/library/guides/page/` is 3). Both limits are settings.
- **Screen:** RankWeb → Internal links → Silos: a list, and a form (name, hub item ID, mode, supporting item IDs one per
  line). The form needs an administrator; it refuses an item already in another silo, naming it, and an item that is not
  published with its own page. Deleting a silo removes its settings only. Silo links are not in the anchor mix (their
  anchor is the item's title and they are not in `aw_links`). REST: `GET rankweb/v1/links/silos`.
