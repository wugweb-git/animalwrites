# Edit screens

**Animal Writes → Edit screens** (administrators) lists every box on every edit screen: post types and term screens, boxes from WordPress, plugins (Rank Math, LiteSpeed, Link Whisper), SCF field groups and our own plugins. For each box it shows the keys it stores, their type and where each key is used on the site. The same rows from the command line: `wp aw-edit-screens [--type=<post_type or taxonomy>] [--format=table|json]`. Nothing on the screen or in the command writes anything.

Code: `plugins/animal-writes-core/includes/edit-screens.php`. Map: `plugins/animal-writes-core/data/edit-screen-map.json`. The full snapshot the map was filled from (box order per type, duplicates, saved-but-never-read keys, live row counts): [edit-screens-map.md](edit-screens-map.md).

## How it works

- **Boxes are read live.** When the screen loads, each post type's edit screen is set up on an in-memory post (ID 0, never saved), `add_meta_boxes` and `add_meta_boxes_{type}` run, the registered boxes are read and everything is put back. Box callbacks are never run. A box any plugin adds therefore appears on its own.
- **SCF field groups** come from SCF itself (`acf_get_field_groups()`, `acf_get_fields()`); the box id is `acf-{group key}` and each field joins the map by its field name.
- **Term screens**: the callbacks on each taxonomy's form hooks, joined to the map by file, hook and priority.
- **The map** says what each box and key is: `boxes` by box id (title, source, file, types, notes) and `keys` by stored name (store, label, type, types, boxes, uses, notes). A box or field missing from the map shows **Not mapped yet**.
- Some boxes are added only on the edit screen itself (Rank Math, the attachment fields); they are listed from the map with a note. LiteSpeed adds its box only in WP admin, so it is missing from the command-line rows.

**Collapsed by default:** LiteSpeed, Link Whisper Suggested Links, Rank Math Link Suggestions, Custom Fields, Slug and Linked from start collapsed on every post edit screen (`aw_edit_screen_collapsed_boxes()`, through core's `postbox_classes_{screen}_{box}` filter, which only adds the `closed` class). They are still there: click the title to open one, drag or hide it in Screen Options as before. A box opened this way starts collapsed again on the next load. Nothing is written to the user's saved box settings.

Notes on each row: the box's own note, a key edited in two boxes on the same screen, keys saved but never read, boxes hidden by an SCF group, not mapped yet.

## When you add or change a field

1. Add the key under `keys` in `data/edit-screen-map.json`:

   ```json
   "aw_example": {
   	"store": "post_meta",
   	"label": "Example",
   	"type": "text",
   	"boxes": ["acf-group_aw_listing"],
   	"types": ["aw_listing"],
   	"uses": [
   		{ "kind": "display", "where": "Contact block", "file": "core/listing-template.php:180" },
   		{ "kind": "schema", "where": "Organization url", "file": "seo/schema.php:67" }
   	]
   }
   ```

   - `store`: `post_meta`, `term_meta`, `taxonomy`, `post_field` or `option`.
   - `boxes`: the box ids that edit it (see `boxes`; an SCF group is `acf-{group key}`).
   - `types`: the post types or taxonomies it is on; empty means any.
   - `uses.kind`: `display`, `schema`, `faq`, `filter`, `sourcing`, `analytics` or `admin`. An empty `uses` list means saved but never read; say so in `notes`.
   - Short paths: `core/` = `animal-writes-core/includes/`, `seo/` = `aw-seo-extras/includes/`, `src/` = `aw-sourcing/includes/`, `kits/` = `kits-core/includes/`.
2. A new box: add it under `boxes` with its id (the first argument of `add_meta_box()`), title, source, file and types. `runtime` is `live` (default), `scf`, `fallback` (only shown when it registers, e.g. when SCF is off) or `edit-screen-only`. Term-screen entries have `"screen": "term"` and a `match` (file, hooks, priority).
3. Open Animal Writes → Edit screens (or run `wp aw-edit-screens --type=<type>`) and check the box shows with no "Not mapped yet".

Another plugin can add entries without editing the file, with the filters `aw_edit_screen_boxes` (box id => entry) and `aw_edit_screen_map` (key => entry, boxes already resolved to id, title, source).
