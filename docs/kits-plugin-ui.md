# Kits plugin screens (the Kits Core admin shell)

How a Kits plugin builds its admin screens on Kits Core's shared shell. Written from what was built for RankWeb's
new Dashboard and Settings (PR #120, 11 Nov 2026). Where this differs from `docs/admin-ui-spec.md` (the Kits menu's
own screens), this file applies to plugin screens.

The pattern follows Google Site Kit's admin screens, using none of its code, CSS or images:
- an app header with the plugin's icon, name, version and tabs;
- a Dashboard with chips for the time range, a grid of tiles (big number, one sub-line, a link) and sections under them;
- Settings with groups as cards, and connected services as rows with a status and an Edit button.

The look is Kits: black, white and greys, with one yellow accent (#FFC300) used only for the open tab's bar. WordPress
packages are plumbing only (`@wordpress/element`, `api-fetch`, `i18n`). No `@wordpress/components` look is used.

## Parts

Kits Core: `plugins/kits-core/src/shell/` (source), built to `build/shell/` (committed) by `npm run build:shell`.
The script sets `window.kits.shell`, and the script and stylesheet are both registered with the handle `kits-shell` on
every admin screen (`includes/admin-shell.php`). Nothing is printed until a screen asks for it.

| Part | What it is |
|---|---|
| `AppShell { header }` | The page: pinned header (from 768px), then the body (max 1280px). |
| `AppHeader { icon, name, version, tabs, aside }` | Icon, name, version, a link on the right, and tabs (`{ slug, label, url, current }`, `aria-current` on the open one, yellow bar). |
| `ChipRow { label, chips, value, onChange, after }` | Pill buttons (`aria-pressed`), or links when a chip has `href` (`aria-current`). They wrap when wide and scroll sideways on phones, with the chosen chip scrolled into view. `after` is a line of text beside them. |
| `TileGrid { label }`, `Tile { label, value, delta, sub, href, tone, loading }` | 4 columns from 1200px, 2 from 768px, 1 on phones. The value links to `href`. `tone` is `warn` ("Needs attention") or `error` ("Not working"), each shown as a word as well as a mark. `loading` shows "Counting…". |
| `SectionCard { id, title, description, actions }` | A white card with a heading. |
| `SettingsGroup { id, title, description, footer }` | A card with a grey footer for its own Save. |
| `SettingsBlock { title, description, note }` | A part of a group (one module), with a rule between blocks. |
| `Field { label, help, changed, onReset, render( id, helpId ) }` | Label, control and help. "Changed from the default." and a Reset to default link when changed. |
| `Switch { label, checked, onChange, disabled, help }` | A checkbox with `role="switch"`. |
| `ServiceList`, `ServiceRow { icon, name, status, tone, summary, editLabel }` | A row: icon (or the name's first letter), name, one line, status with a dot, and Edit/Close (`aria-expanded`) that shows the row's children. |
| `Notice { tone, onDismiss }` | `info`, `success`, `warning`, `error`. Errors use `role="alert"`, the others `role="status"`. |
| `EmptyState`, `LoadingState { label }`, `ErrorState { message, onRetry }` | The three states every screen needs. |
| `Button { variant, busy }` | `primary` (black), `secondary` (outline), `link`. `busy` disables it and shows a spinner. |
| `RefTable { caption, head, rows }` | A two-column reference table that stacks on phones. |
| `DataList { label, columns, rows, total, page, pages, onPage, search, onSearch, filters, onFilter, views, view, onView, sort, onSort, bulkActions, onBulk, rowActions, loading, error, onRetry, emptyTitle, empty }` | The working list. The screen fetches each page from its own route (search, filters, sort and paging are done on the server). Saved views as pills with counts, a search box, filter selects, sortable headings (`aria-sort`), tick boxes with a bulk action, row actions under the primary cell, Previous/Next with "Page 2 of 5 · 93 rows", and the empty, loading and error states. On phones each row is a card, every cell with its column's name. |
| `RowAction { action: { label, href, onClick, disabled, why, tone } }` | One row action. `disabled` with `why` shows it off and says why ("Undo needs SourceWeb"). |
| `FieldReview { title, fields: [ { key, label, current, proposed, applied, undoOff } ], onApply, onUndo, busy, after }` | One proposed change field by field: now beside proposed, a tick per field, Apply the ticked fields, Undo on an applied field (off with a reason when `undoOff`). Claims and enrichment proposals. |
| `SetupFlow { title, steps: [ { slug, label, done } ], current, onStep, canContinue, busy, onFinish, finishLabel, aside }` | A first run or a service's connection as numbered steps: the steps (a step opens once the one before is done), one step's content, Back and Continue (Finish on the last). |
| `Panel { title, icon, tabs, sections, section, onSection, aside }` | The options panel for Kits Core's own screens: a dark bar with the Kits tabs (the open one with the yellow bar), the screen's sections listed on the left (a sideways row on phones), the open section on the right. |
| `PanelGroup { title, description, summary, defaultOpen }` | A group of settings that folds open and shut (`aria-expanded`); `summary` shows while shut ("6 of 7 on"). |
| `OnOff { label, checked, onChange, help, changed, onReset, disabled }` | An On/Off toggle: two options side by side, the chosen one filled black (a radio group). |
| `MiniChart { label, points: [ { label, value } ], unit }` | One series as grey bars, today's bar black, with the same numbers as a table for screen readers. |
| `HashList { label, help, items: [ { id, note } ], onAdd, onRemove, busy }` | A write-only list: a value is sent once, kept as a fingerprint and never shown again; rows show only "Entry 1 · Added 3 Nov". |
| `LinkedSetting { label, value, plugin, href }` | A setting another Kits plugin keeps, read-only, with "Kept in RankWeb: change it there" (or "…, which is not active"). |
| `MetricPicker { options, value, onChange, busy }` | "Select metrics": which tiles a Dashboard shows, as a fold-out list of tick boxes with Save. |

`Tile` also takes `missing` ("RankWeb"): the tile says "RankWeb not active" in place of a number, or is left out with `hide`.
A data-file service (a bundled file with its date and counts, nothing to connect) is a `ServiceRow` with no children:
it has no Edit button.

PHP helpers in `includes/admin-shell.php`:
- `kits_shell_ui_next()`: the request has `&ui=next`.
- `kits_shell_rest_can( $cap )`: the `permission_callback` every shell route uses (401 signed out; 403 without a valid
  `wp_rest` nonce in a browser request; 403 without the capability).

The second set of parts is in `src/shell/list.js`, `setup.js`, `panel.js`, `extras.js` and `more.css`, built into the
same `build/shell/` files. The empty, loading and error states are in `states.js`, so the list and screens share them.

CSS classes all start `kits-shell-`. The shell's own values are `--ks-*` variables, each copied from a Kits token and
named in the comment at the top of `shell.css`:
- space: `--kit-space-1`…`-6` (4–32px)
- radius: control 8px, card 12px, pill
- greys: `--neutral-0`…`-9`
- control height: 40px (the compact size of admin controls)

Breakpoints are only 767/768 and 1199/1200. Motion stops under `prefers-reduced-motion`. `[hidden]` always hides,
whatever a part's own `display` is.

## Putting a screen on the shell

1. **Build.** The plugin keeps its screen in `src/index.js` and builds with `@wordpress/scripts` 32 into `build/`, which
   is committed. Its `webpack.config.js` maps `@kits/shell` to the shell, so the shell is never bundled and
   `kits-shell` lands in `index.asset.php`:
   ```js
   new DependencyExtractionWebpackPlugin( {
   	requestToExternal: ( r ) => ( '@kits/shell' === r ? [ 'kits', 'shell' ] : undefined ),
   	requestToHandle: ( r ) => ( '@kits/shell' === r ? 'kits-shell' : undefined ),
   } )
   ```
   The screen then imports `{ AppShell, Tile, … } from '@kits/shell'`.
2. **Enqueue**, on `admin_enqueue_scripts`, on the plugin's own screen only, after its capability check:
   ```php
   $ok = kits_shell_app( array(
   	'handle'      => 'myplugin-admin',
   	'src'         => plugins_url( 'build/index.js', MY_FILE ),
   	'asset'       => MY_DIR . 'build/index.asset.php',
   	'object'      => 'myPluginApp',      // window.myPluginApp = data
   	'data'        => array( … ),
   	'text_domain' => 'my-domain',
   ) );
   ```
   It returns false when Kits Core's shell or the plugin's build file is missing. The plugin then shows its current
   screen. It also adds the body class `kits-shell-screen`, so the screen runs edge to edge.
3. **Mount**, from the page callback, first thing:
   `kits_shell_mount( 'myplugin-app', __( 'My plugin', … ), $current_screen_url, __( 'Open the current screen', … ) );`
   This prints the wrap, a screen-reader `h1`, `hr.wp-header-end` (other plugins' notices go after it, outside the app)
   and the mount `div`, with a no-script link to the current screen.
4. **Data** comes from the plugin's own REST namespace (`<plugin>/v1`; `kits/v1` is Kits Core's). Every route has a
   `permission_callback` that:
   - returns 401 when signed out;
   - checks the `wp_rest` nonce for a browser (an Application Password request has none), returning 403;
   - then checks the capability, returning 403.

   Nothing slow is worked out while a screen loads. Counts come from a background job, and the route only formats them.

## Kits Core's own screens: the options panel

Kits Core's settings screens use `Panel`, the pattern of a theme options panel (a dark tab bar, a section list,
groups that fold, On/Off toggles). The first is **Kits → Settings with `&ui=next`** (`includes/admin-settings-next.php`,
`src/settings-next/`, built to `build/settings-next/`):
- Sections: Performance (Scripts and styles, Fonts), Compatibility (Older content), and Preview access, which links to
  the current screen's section for now.
- The fields are the current screen's (`kits_theme_options`: the Speed and Older content switches, the joined files
  cap and the font preload). Save sends only the changed fields to `kits/v1/settings`, which runs them through the
  option's own clean-up (`kits_theme_options_clean()`); nothing is written when nothing changed.
- `smoke.sh` checks the screen (200 and drawn for an administrator, 403 for a subscriber, the current screen still the
  default), the route (401 signed out or without a nonce, 403 for a subscriber, 200 for an administrator), and that an
  unchanged Save leaves `kits_theme_options` byte for byte the same.

## A trial behind `&ui=next`

A new screen ships behind `&ui=next` on the current screen's own address. Without it the current screen shows, and it
stays the default until the owner says switch. The header's "Open the current screen" link drops `&ui=next`. Both
screens save through the same function, so neither can drift from the other.

## RankWeb (the first plugin on the shell)

- `includes/rankweb-ui.php`: the trial switch (`rankweb_ui_wanted()`), the enqueue and the mount.
- Overview with `&ui=next` shows the Dashboard (capability `manage_categories`). Settings with `&ui=next` shows the
  Settings screen (`manage_options`).
- `includes/rankweb-rest.php`, routes under `rankweb/v1`:
  - `dashboard`
  - `dashboard/count`
  - `settings`
  - `settings/<group>`
  - `settings/faq-types`
  - `services`
- `includes/rankweb-dashboard.php`: the job `rankweb_health_build`, which runs hourly plus a single `now` event from
  Count now or the first view.
  - It writes the transient `rankweb_health` (3 hours) and one row a day in `rankweb_health_log` (120 days, not
    autoloaded), which the Dashboard's 7/28/90-day chips compare against.
  - The schema count runs in 20-second slices, with its place kept in `rankweb_health_schema`.
- `includes/settings.php`: `aw_seo_settings_save()` is shared by the current form and the route.
  - It stores only values that differ from the default.
  - A module switch is stored only as `on: false`.
  - Nothing is written when nothing changed.
- Kits → Tools export carries `aw_seo_settings`, `aw_faq_off_types` and `aw_seo_keep_listings`
  (`kits_settings_transfer_options`).
- Proof:
  - `plugins/aw-seo-extras/tests/run.php` runs without WordPress in the Deploy check.
  - `tools/ci/smoke.sh` checks:
    - both screens return 200 for an administrator;
    - the current screens are still the default;
    - the routes return 401 signed out or without a nonce, and 403 for a subscriber;
    - after an unchanged Save of every group, the three options are byte for byte the same.
- Screens at 1280px and 375px: `docs/kits-plugin-ui/`.

## SourceWeb

- `plugins/aw-sourcing/includes/sourceweb-ui.php`: the trial switch (`sourceweb_ui_wanted()`), the enqueue and the
  mount (`sourceweb-app`). Four screens, each with `&ui=next` on today's address:
  - Overview (`aw-sourcing-menu`): Dashboard (`edit_others_posts`).
  - To review (`aw-review`): list with type views, source and reason filters, search, Publish/Reject per row and for
    the ticked rows, Publish all (`edit_others_posts`).
  - Enrichment review (`aw-enrich-review`): one row per listing with status views and filters; Review opens
    `FieldReview` for its values (approve, reject, undo, approve every high-confidence value) (`edit_others_posts`).
  - Settings (`aw-sourcing&tab=settings`): Connected services (GitHub workers, Google Maps with its key and cap, each
    enrichment source with its switch, AI providers, the relay) and the Workers, Directory enrichment and Social posts
    groups (`manage_options`). Sources, Keywords, Rules, AI models and Workers stay on today's tabs, linked.
- `includes/sourceweb-rest.php`, routes under `sourceweb/v1`: `dashboard`, `dashboard/count`, `review`,
  `review/decide`, `review/publish-all`, `enrichment`, `enrichment/decide`, `settings`, `settings/<group>`,
  `settings/google-key`. Every write calls the function today's screen calls (`aw_review_apply`,
  `aw_enrich_approve/reject/undo`, `aw_sources_live_save`, `aw_enrich_sources_sanitize`). The Google key is never sent
  out: Settings says only whether one is set, and an empty key keeps the stored one.
- `includes/sourceweb-dashboard.php`: the job `sourceweb_dashboard_build` (hourly plus a single `now` event), the
  transient `sourceweb_dashboard` (3 hours) and one row a day in `sourceweb_dashboard_log` (120 days, not autoloaded).
  Other plugins add tiles through `aw_sourcing_dashboard_tiles`.
- `includes/sourceweb-model.php`: the pure parts (list shaping, grouping per listing, what changed), tested without
  WordPress in `plugins/aw-sourcing/tests/sourceweb-tests.php`.
- Kits → Tools export carries `aw_sources`, `aw_enrich_sources`, `aw_enrich_live_cap`, `aw_social_on` and
  `aw_social_show`; never the Google key.
- `tools/ci/smoke-ui-sourceweb.sh`: the four screens, every route (401 signed out, 403 for a subscriber), the key never
  in an answer, and an unchanged Save of every group leaving the options byte for byte the same.

## Not built yet

- Kits Core has no translation files for the shell yet.
- The other four plugins (InsightWeb, MailWeb, DirectoryWeb, PlanWeb) do not use the shell yet.
- Parts SourceWeb needed and worked around: a row view that opens inside a `DataList` (FieldReview sits in a card
  above the list), "Reject ticked" in `FieldReview`, a write-only secret field, and a confirm with a count (the
  browser's confirm is used).
- The chart is one small part (`MiniChart`); there is no line chart or a chart with two series.
