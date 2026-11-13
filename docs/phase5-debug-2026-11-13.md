# Phase 5: debug, PHP error logs (13 Nov 2026)

Window: 6 to 13 Nov 2026 (UTC). Read-only on the live site. Nothing was written, flushed or run there.

## What was read

| Log | State |
|---|---|
| `wp-content/debug.log` | Not there (`WP_DEBUG` is off) |
| `public_html/error_log` | 8 lines, last 10 Nov. All are database errors from one-off WP-CLI queries |
| The host's per-domain PHP log (`~/.logs/error_log_<domain>`) | 560 lines, 5 Oct to 12 Nov |
| `ini_get('error_log')` | `error_log`, a relative path, so each folder gets its own file. Only the root one exists |
| Staging's per-domain log | 2 lines (Rank Math cron), report only |
| `wp aw error-count --paths` | `PHP fatal errors: 0`. Read both files above |

**PHP's own messages are not logged on this server.** `log_errors` is Off in both the host's PHP ini files (CLI and
web), so the logs above only hold lines that code writes itself with `error_log()`, mainly "WordPress database
error". There are no PHP Warning, Notice, Deprecated or Fatal lines in any log, for any date.

To find them anyway, WordPress was loaded once through WP-CLI with `WP_DEBUG` on, on screen only (no log, no
change). A `doing_it_wrong_run` hook recorded the caller. That load showed four notices, all from our code, and no
warnings, deprecations or fatals.

## Counts, 6 to 13 Nov

| Kind | Count | Last seen |
|---|---|---|
| PHP Fatal / Warning / Notice / Deprecated in the logs | 0 (not logged, see above) | — |
| Notices on a debug load (our code) | 9 per load, 4 distinct | every load |
| Database error, ours: `Reference 'di' not supported` | 24 | 11 Nov 21:04 |
| Database error: `Commands out of sync` at shutdown | 26 | 12 Nov 18:56 |
| Database error: missing `wp_pc_*` tables | 6 | 12 Nov 12:52 |
| Database error: one-off WP-CLI query (unknown column) | 1 | 10 Nov 05:09 |

## Ours: issue → fix

| Where | Message (count) | Fix | Commit |
|---|---|---|---|
| `plugins/kits-core/includes/page-options.php:194` | `register_meta` called incorrectly: meta keys cannot enable revisions support unless the object subtype supports revisions (6 per load) | `revisions_enabled` only where the post type has revisions. Core had **refused** the six page-option keys on `aw_press`, which has no revisions, so they were never registered there | `ca95a5ed` |
| `plugins/planweb/includes/store.php:105` | `register_meta` called incorrectly: the default must match the type provided (1 per load) | A default only where one is declared. Core had **refused** `planweb_date`, because its empty default fails its date pattern. With no default, a missing value still reads as `''` | `ca95a5ed` |
| `plugins/kits-core/includes/page-options.php:209` | `register_setting` called incorrectly: an "array" setting in REST needs `items` (1 per load) | Setting type `object`, the same as its REST schema. REST used the schema's `object` already, so it behaves the same | `ca95a5ed` |
| `plugins/kits-core/includes/theme-options.php:252` | The same, for `kits_theme_options` (1 per load) | The same | `ca95a5ed` |
| `plugins/insightweb/includes/search-insights/gsc-types.php:206` | `Reference 'di' not supported (reference to group function)` in the Discover / Google News pages box (24) | Already fixed: `ORDER BY SUM(impr)`. It has been live since 12 Nov and has not been logged since | `d9163bf7` |

`Commands out of sync` also shows our `aw_si_vis_count` (insightweb `visitors.php`) at shutdown, 8 times. In
every case the first failing query at that shutdown is a third-party one (Action Scheduler or Rank Math), and none
of our code uses the connection directly. Our query is a casualty, not the cause, so it is listed below.

## `wp aw error-count`, now by kind

Each kind is counted on its own, read the same way as before (`6f43cbeb`). The email still goes only for fatals,
and it now lists the other counts too. The dashboard table has one row per kind. A test without WordPress
(`plugins/animal-writes-core/tests/error-count-tests.php`) runs in the Deploy check.

```
PHP fatal errors: 0
PHP warnings: 0
PHP notices: 0
PHP deprecated: 0
  <message> (<count>)        ← up to 5 under each kind with any
read <file> (<lines> lines)
tried <path>: <state>        ← with --paths
```

While `log_errors` stays Off, the warning, notice and deprecated counts stay at 0.

## Third party (report only)

| Source | Message | Count | Last seen |
|---|---|---|---|
| Action Scheduler (FluentCRM / Rank Math), Rank Math, core `wp_template` lookup, at shutdown | `Commands out of sync; you can't run this command now` | 26 (8 of them on our `aw_si_vis_count` query) | 12 Nov 18:56 |
| Plugin Check (its own `wp_pc_` test tables) | `Table … wp_pc_actionscheduler_actions / wp_pc_fc_meta doesn't exist` | 6 | 12 Nov 12:52 |
| Rank Math (staging) | `Cron unschedule event error for hook: rank_math/content-ai/update_plan` | 2 | 10 Nov 10:38 |
| Earlier than the window | Microsoft Clarity `CREATE TABLE` out of sync (Oct; plugin since removed), Rank Math redirections cache table missing (10 Oct), Action Scheduler `every_minute` schedule (Oct) | — | — |
