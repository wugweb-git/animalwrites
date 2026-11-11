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

## Not built yet

- Kits Core has no translation files for the shell yet.
- The other five plugins (InsightWeb, SourceWeb, MailWeb, DirectoryWeb, PlanWeb) do not use the shell yet.
- There is no shared chart part. The Dashboard shows changes as text ("+3 in 28 days").
