> Status as of 24 Nov 2026 (checked against git): Partly done. group_aw_legal_document.json, AW_PLACE_EDIT_TYPES and split campaign groups are in wordpress-dev; listing split not found in Core (may have moved to DirectoryWeb); step 7 (map) still open.

# Edit-screen boxes: carry out the approved plan (7 steps)

## Context
The owner (1 Nov 2026) finds too many edit-screen boxes, Details boxes that are too big (Directory listing: 61 fields
in 9 tabs), and fields edited in two places. The approved direction is `/Users/abcom/.claude/plans/lazy-crafting-falcon.md`
(schema audit: `lazy-crafting-falcon-agent-a2de001c4452f768b.md`). This plan is how to ship it: 7 small steps, one
commit each, no stored key changed, core WordPress/SCF admin UI only.

## Step 0: base branch (fixes the wrong base)
This worktree was cut from `main` (Figma Make files). The WordPress code is on `wordpress-dev`. Before any edit:
`git switch -c edit-screen-boxes-1101 origin/wordpress-dev` in this worktree (clean, nothing to lose). Every commit goes
on that branch. The owner pushes: `git push origin edit-screen-boxes-1101:wordpress-dev` (fast-forward only; never
`wordpress`, never force). Commit trailer: `Co-Authored-By: Wugweb <admin@wugweb.com>`. No mention of AI or process.

## How the work is done
Each step goes to one general-purpose subagent with the exact files, the rules (no key or nonce changes, no new
CSS/JS, PHPCS `tools/ci/phpcs-security.xml`), and what "done" means. I review its diff, run `php -l` + PHPCS on the
changed files, commit, and give the owner the push command. After the owner's push and the Deploy check, I verify
read-only (below) before starting the next deploy (one deploy at a time). Shared notes go to the side-session notes folder.

## Steps
**1. Campaign duplicate.** Remove `field_aw_campaign_campaign_close` and `field_aw_campaign_campaign_doc` from
`acf-json/group_aw_campaign.json`. Only the side box (`core/campaign-tools.php:591`, nonce `aw_camp_details`) writes
`aw_campaign_close`/`aw_campaign_doc`. No data change.

**2. Collapsed by default.** In `core/edit-screens.php` (new small section), use `postbox_classes_{screen}_{box}` to add
`closed` for `litespeed_meta_boxes`, `wpil_link-articles`, `rank_math_metabox_link_suggestions`, `postcustom`, `slugdiv`,
`aw_linked_from` on every post type where they appear. Nothing is unregistered. The user's own `closedpostboxes_*` still
wins. Tell the owner that the admin's saved choices on 8 screens take priority until changed once.

**3. Place and Issued by side boxes.** `core/places-admin.php`: register `aw-place` and print its JS only for
`aw_listing`. Keep `AW_PLACE_TYPES` (counts, related, auto-detect) and the save handler as it is (it is skipped when
the nonce is missing). `core/issuers.php:75`: register `aw-issuer` on `aw_legal` only. The save stays for both types
(nonce-gated). The 1 Resource value stays.

**Copy gate (before step 4).** Paste in chat the exact new box titles and field labels for steps 4–6. Build only after
the owner's explicit yes on that list. Draft:
- Library: **Source** (Source URL, Source name, Source date) · **Document** (Document file, Document link, Official
  number, In force, Short name, Issued under, Key sections, Issued by). "Ask for an email before download" goes last,
  under "Not used on the site".
- Directory listing: **Contact** · **Address and place** · **Opening hours** · **Registrations** · **About and services**
  · **Online** · **Photos and support** · **Claimed and sources**, with existing field labels kept. "Verified" →
  "Claimed" and "Verified on" → "Claimed on" are listed for the yes. Never-read fields go under "Not used on the site".
- Campaign: **Event** · **Dates and state** · **Document** · **Organisations** · **Outcome** · **Letters and share**.

**4. Library Document.** Split `group_aw_legal.json` into `group_aw_legal` (title "Source") and a new
`group_aw_legal_document` ("Document"). Field keys stay the same; fields only move between the two files. The Document group gets two
`message` fields: one renders the Library item UI and one renders `aw_issuer_box()`, through
`acf/render_field/key=…`. This is the pattern at `aw-sourcing/includes/enrichment-admin.php:112`. To do it, move the
Library item closure in `core/faq-data.php:657` into a named function, and stop `add_meta_box( 'aw-legal-item' )` and
the `aw-issuer` box on aw_legal. The nonces `aw_faq_legal` / `aw_issuer_save` and their save handlers stay unchanged.
Remove `field_aw_legal_msg_issuer`. Add the new group key to `AW_SCF_GROUPS` (`core/scf.php:26`).

**5. Directory listing split.** Split `group_aw_listing.json` into 8 group files, one per box, with field keys
unchanged and `menu_order` setting the order. `group_aw_listing` stays as the first box, so `AW_ENRICH_LISTING_GROUP` and the
box id `acf-group_aw_listing` still exist. **Must fix:** `aw-sourcing/includes/rules.php:177–196` reads only
`group_aw_listing`. It must read every listing group: `AW_SCF_GROUPS` filtered to aw_listing, plus the JSON fallback
across those files. Check `aw-sourcing/tests/rules-tests.php:144` too. Address and place renders `aw_place_box()` (ids
and JS unchanged), and the `aw-place` side box is no longer registered. Claimed and sources renders
`aw_enrich_prov_box()`, and `aw-enrich-prov` is no longer registered. The nonces `aw_place_nonce` / `aw_enrich_identity`
are unchanged. Add every new group key to `AW_SCF_GROUPS`. Repeaters (`aw_phones`, `aw_opening_hours`,
`aw_detail_sources`) keep their keys.

**6. Campaign split.** `group_aw_campaign` → Event (event date/place). New groups render, via message fields:
Dates and state, Document, Organisations, Letters and share. Each is a slice of `aw_camp_details_box()`, which gets
split into one function per section. The nonce `aw_camp_details` is printed once (in Dates and state), and the single save
handler stays unchanged. That keeps "writes every field on any save" exactly as today: no field disappears from the
form. Outcome renders the outcome select + as-of and `aw_camp_record_box()` (nonce `aw_camp_record`). Stop both side boxes.
The "How this campaign works" help goes in Letters and share. Frozen campaigns 13848 and 8 are never saved.

**7. Edit screens page, map, CLI.** `core/edit-screens.php` + `data/edit-screen-map.json`: add Plugin and Purpose
columns (from a new `plugin`/`purpose` per box in the map), collapse the core WordPress boxes into one "General (all
types)" row, show one FAQ row (`aw-faq` + `term-faq`), and add an "Edited in two places" check: the same meta key
editable from two boxes on one type. It must report 0. `wp aw-edit-screens` prints the same. Update every changed box
id in the map, plus `docs/edit-screens-map.md` and `docs/edit-screens.md`. (Map/doc rows for steps 1–6 are updated in the
step that changes them, so `unmapped` stays 0 after each deploy.)

## Verification (after each deploy)
- Before the push: `php -l` and PHPCS clean on changed files; `python3 -m json.tool` on changed acf-json.
- Live, read-only SSH: `wp aw-edit-screens` → 0 unmapped (and after step 7: "Edited in two places" empty).
- Steps 4–6: before/after meta dump (read-only `wp post meta list <id> --format=json` over SSH) for one Library item,
  one listing with place + phones + hours + provenance, and the test campaign only if it is not 13848/8 (otherwise
  page-load check only, no save). The save itself is done in the signed-in admin in the built-in browser: Update with no changes,
  then diff → identical. Also check that PIN "Fill in" still works inside Address and place, and that Issued by shows inside Document.
- Front end: curl a listing, a Library item and a News item. Address/place, "Issued by X.", and one FAQPage each are unchanged.
- Update `pending.md` by title after each finished step.
