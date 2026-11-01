# Edit screens

**Animal Writes → Edit screens** (administrators) lists every box on every edit screen: post types and term screens, boxes from WordPress, plugins (Rank Math, LiteSpeed, Link Whisper), SCF field groups and our own plugins. It also lists the block-editor sidebar panels, the Dashboard widgets and the boxes on the Search Insights report page. For each box it shows the keys it stores, their type and where each key is used on the site. The same rows from the command line: `wp aw-edit-screens [--type=<post_type or taxonomy>] [--format=table|json]`. Nothing on the screen or in the command writes anything.

Code: `plugins/animal-writes-core/includes/edit-screens.php`. Map: `plugins/animal-writes-core/data/edit-screen-map.json`. The full snapshot the map was filled from (box order per type, duplicates, saved-but-never-read keys, live row counts): [edit-screens-map.md](edit-screens-map.md).

## How it works

- **Boxes are read live.** When the screen loads, each post type's edit screen is set up on an in-memory post (ID 0, never saved), `add_meta_boxes` and `add_meta_boxes_{type}` run, the registered boxes are read and everything is put back. Box callbacks are never run. A box any plugin adds therefore appears on its own.
- **SCF field groups** come from SCF itself (`acf_get_field_groups()`, `acf_get_fields()`); the box id is `acf-{group key}` and each field joins the map by its field name.
- **Term screens**: the callbacks on each taxonomy's form hooks, joined to the map by file, hook and priority.
- **The map** says what each box and key is: `boxes` by box id (title, source, file, types, notes) and `keys` by stored name (store, label, type, types, boxes, uses, notes). A box or field missing from the map shows **Not mapped yet**.
- Some boxes are added only on the edit screen itself (Rank Math, the attachment fields); they are listed from the map with a note. LiteSpeed adds its box only in WP admin, so it is missing from the command-line rows.
- **Listed from the map, not read live:** sidebar panels that plugins add with JavaScript in the block editor (Rank Math, Link Whisper target keyword, SCF block bindings: ids `panel-*`), and boxes on admin pages that are not edit screens (`"screen": "admin"`): the Dashboard widgets under "Dashboard (widgets)" (core ones in the General row), the Search Insights boxes under "Search Insights (report page)", and the panels, boxes and tables on the admin pages of AW Sourcing, AW SEO Plus and Search Insights, Animal Writes and Mailweb, one heading per page (e.g. "AW Sourcing: Enrichment review", "Media: Reuse pictures"). Their optional `reads`, `sets` and `limits` show in the row's notes, never as fields. These never count as not mapped and are left out of the per-type checks. There are no classic widgets (`register_widget`) in the repo.

**Collapsed by default:** LiteSpeed, Link Whisper Suggested Links, Rank Math Link Suggestions, Custom Fields, Slug and Linked from start collapsed on every post edit screen (`aw_edit_screen_collapsed_boxes()`, through core's `postbox_classes_{screen}_{box}` filter, which only adds the `closed` class). They are still there: click the title to open one, drag or hide it in Screen Options as before. This applies only until the user opens or closes any box on that screen; from then on WordPress keeps the user's own choice. Nothing is written to the user's saved box settings.

**Fields inside SCF boxes (1 Nov 2026):** the Place fields are inside "Address and place" on Directory listings, Issued by and the Library item fields inside "Document" on Library items, "Where these details come from" at the end of "Claimed and sources" on listings, and the campaign fields inside the five campaign groups (Dates and state, Document, Organisations, Outcome, Letters and share). Each is drawn by an SCF message field (`acf/render_field/key=…`) with its own nonce and save. Places and issuers already stored on other types are kept and still read on the site.

## What the page shows

- **Plugin** and **Purpose** for each box, from the box's optional `plugin` and `purpose` in the map. Our own plugins are named like any other (Animal Writes core, AW SEO Plus, AW Sourcing). Without them the row shows the box's `source` and "—".
- **General (all types)**: one row at the top for the WordPress core boxes (Publish, Categories, Tags, Excerpt, Featured image, Slug, Author, Revisions, Discussion, Custom Fields, the term screen's Name, Slug, Parent, Description, the core Dashboard widgets …). The rows per type list only the other boxes. A core box that is not mapped yet stays under its type.
- **FAQ** (`aw-faq` on posts, `term-faq` on terms) is one row listing every type and taxonomy it shows on.
- **Edited in two places**: each key that more than one box on the same type's screen can edit (type, key, boxes), or "None.". Custom Fields is left out (it can edit any key), and so is any box or key the map marks `"readonly": true`. The command prints the same list and ends with `N rows, N not mapped yet, N edited in two places.`

Notes on each row: the box's own note, a key edited in two boxes on the same screen, keys saved but never read, boxes hidden by an SCF group, not mapped yet.

## When you add or change a field

1. Add the key under `keys` in `data/edit-screen-map.json`:

   ```json
   "aw_example": {
      "store": "post_meta",
      "label": "Example",
      "type": "text",
      "boxes": ["acf-group_aw_listing_online"],
      "types": ["aw_listing"],
      "uses": [
        { "kind": "display", "where": "Contact block", "file": "core/listing-template.php:180" },
        { "kind": "schema", "where": "Organization url", "file": "seo/schema.php:67" }
      ]
   }
   ```

   - `store`: `post_meta`, `term_meta`, `taxonomy`, `post_field` or `option`.
   - `boxes`: the box ids that edit it (see `boxes`; an SCF group is `acf-{group key}`). An SCF field joins its group's row by field name on its own, so a key that is an SCF field must list that group here, or the page reports it as edited in two places. Fields drawn inside a group by a message field (Place, Issued by, the campaign fields) list the group too.
   - `readonly`: `true` for a key that is shown but never edited; it is left out of "Edited in two places".
   - `types`: the post types or taxonomies it is on; empty means any.
   - `uses.kind`: `display`, `schema`, `faq`, `filter`, `sourcing`, `analytics` or `admin`. An empty `uses` list means saved but never read; say so in `notes`.
   - Short paths: `core/` = `animal-writes-core/includes/`, `seo/` = `aw-seo-extras/includes/`, `src/` = `aw-sourcing/includes/`, `kits/` = `kits-core/includes/`.
2. A new box: add it under `boxes` with its id (the first argument of `add_meta_box()`), title, source, file, types, `plugin` and a one-line `purpose`. `runtime` is `live` (default), `scf`, `fallback` (only shown when it registers, e.g. when SCF is off), `edit-screen-only` (also used for the JavaScript sidebar panels) or `map-only` (admin pages). Term-screen entries have `"screen": "term"` and a `match` (file, hooks, priority). Admin-page entries have `"screen": "admin"`, `page` (the heading, e.g. "Dashboard (widgets)") and `types` (the page id, e.g. `dashboard`, `aw-search-insights`).
3. Open Animal Writes → Edit screens (or run `wp aw-edit-screens --type=<type>`) and check the box shows with no "Not mapped yet".

Another plugin can add entries without editing the file, with the filters `aw_edit_screen_boxes` (box id => entry) and `aw_edit_screen_map` (key => entry, boxes already resolved to id, title, source).
