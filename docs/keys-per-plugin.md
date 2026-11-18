# Service keys: one place per plugin (18 Nov 2026)

Owner rule, 18 Nov 2026: every Wugweb Kits plugin that is sold or listed on WordPress.org keeps its own service keys in
its own settings, as Yoast, Rank Math and Site Kit do. A `wp-config.php` constant of the same name wins. The shared
vault (Kits → Keys: Animal Writes Core `includes/keys.php`, option `aw_keys`, reader `aw_key()`, page
`admin.php?page=aw-keys`) is **retired, not moved to Kits Core**, in this order: migrate first, delete second.

The AI provider keys are out of scope: they are the owner's alone, are not on Kits → Keys, and are not listed or touched
here.

## Stage 1: inventory

Checked 18 Nov 2026 on `wordpress-dev` (19494848) and, read only, on live. Live was read with a server-side
`wp eval` that printed key **names** and set yes/no only, never a value.

### Who registers keys on the vault, and who reads them

Only **InsightWeb** (Search Insights) uses the vault. No other plugin adds an `aw_keys` entry or calls `aw_key()`:
RankWeb (`aw-seo-extras`), SourceWeb (`aw-sourcing`), DirectoryWeb, MailWeb, PlanWeb and Kits Core have none.

Before this change (`wordpress-dev` 19494848):

| What | Where |
|---|---|
| Vault: option, reader, screen, save handler | `plugins/animal-writes-core/includes/keys.php:17` (`AW_KEYS_OPTION`), `:20` (`aw_keys_list()`), `:29` (`aw_key()`), `:42` (`aw_key_where()`), `:52` (menu), `:58` (`aw_keys_screen()`), `:96` (`admin_post_aw_keys`) |
| Vault option deleted on Core uninstall | `plugins/animal-writes-core/uninstall.php:73` |
| Registered: `AW_CLARITY_TOKEN`, `AW_BING_WEBMASTER_KEY`, `AW_PSI_KEY` | `plugins/insightweb/includes/search-insights/runs.php:54` (`aw_keys` filter; the three entries at `:57`, `:58`, `:59`) |
| Registered: `AW_GOOGLE_ALERTS_FEED_1`, `AW_GOOGLE_ALERTS_FEED_2` | `plugins/insightweb/includes/search-insights/alerts.php:19` (`aw_keys` filter) |
| InsightWeb's reader `aw_si_key()` (called `aw_key()`, else the constant alone) | `plugins/insightweb/includes/search-insights/runs.php:45` |
| Links to `aw-keys` | InsightWeb `search-insights/admin.php:377`, `:1077`; `insightweb-rest.php:535`; Kits Core tests `kits-core/tests/admin-menu-tests.php:90,111,122,147,152`; Core `admin-home.php:44,160,244,383`, `help-tab.php:25`, `data/edit-screen-map.json` |

### The keys

Every key is InsightWeb's. "Admin" is where the key is set; "front end" is where it is used (here, always a scheduled job
or an admin screen, never a public page).

| Key (= wp-config constant) | Owner | Set (admin) | Used (front end: readers, all through `aw_si_key()`) | Live value from |
|---|---|---|---|---|
| `AW_CLARITY_TOKEN` | InsightWeb | Kits → Keys today; InsightWeb → Settings → Keys after this change | `search-insights/clarity.php:36` (on/off), `:129` (Bearer header of the daily Clarity export) | **option `aw_keys`** (no constant on live) |
| `AW_BING_WEBMASTER_KEY` | InsightWeb | as above | `search-insights/bing.php:35` (on/off), `:59` (monthly Bing Webmaster call), `:386` (masked out of error text) | **option `aw_keys`** (no constant) |
| `AW_PSI_KEY` (optional) | InsightWeb | as above | `search-insights/psi.php:158-159` (PageSpeed fallback key), `insightweb-rest.php:575` (Connected services line) | not set |
| `AW_GOOGLE_ALERTS_FEED_1` | InsightWeb | as above | `search-insights/alerts.php:34` (daily feed read) | not set |
| `AW_GOOGLE_ALERTS_FEED_2` | InsightWeb | as above | `search-insights/alerts.php:34` | not set |

Live, 18 Nov 2026: `aw_keys` holds 2 names (`AW_BING_WEBMASTER_KEY`, `AW_CLARITY_TOKEN`), autoload `off`. None of the
five constants is defined in `wp-config.php` (`docs/pending.md` still says the Clarity token is in wp-config.php; live
shows it in the option). `insightweb_keys` does not exist yet.

### What counts as a key (and what does not)

| Type | Examples | Where it lives |
|---|---|---|
| Secret (API key or token) | Bing Webmaster key, Clarity Data Export token, PageSpeed key, Google Alerts feed addresses | The owning plugin's own setting; a wp-config.php constant of the same name wins. Never shown. |
| Public ID | Clarity project ID (`kits_clarity_id`, Kits Core `analytics.php:132`, set in Animal Writes Core `kits-config.php:16`), GTM container (`kits_gtm_id` / `KITS_GTM_ID`, `analytics.php:54`) | Config filters or constants, as now. Not secrets, not on a keys screen. |
| OAuth access | Search Console, GA4, Site Kit's PageSpeed | Site Kit by Google; no key is stored by a Kits plugin. |
| AI provider keys | — | Out of scope: owner-only wp-config.php, never an admin screen. |

No shared vault stays anywhere in the suite: each plugin works standalone, and `includes/keys.php` is retired after
copy → check → delete. It is never moved to Kits Core.

### Other plugins' secrets (already in their own place, not part of the vault)

| Plugin | Secret | Where it lives |
|---|---|---|
| MailWeb | Mail API token, webhook secret, mailbox passwords; `MAILWEB_SMTP_PASSWORD` | MailWeb's own settings (encrypted, `includes/crypto.php`), constant wins |
| SourceWeb | `AW_GITHUB_DISPATCH_TOKEN` | wp-config.php constant only (`sourcing-lists.php:111`, `workers-admin.php:17`) |
| Kits Core | ALTCHA signing key | own option `kits_altcha_key`, generated on first use, kept out of the uninstall backup |
| RankWeb, DirectoryWeb, PlanWeb | none | none |

## Stage 2: build (this change, zero behaviour change)

InsightWeb gets its own keys, in `plugins/insightweb/includes/insightweb-keys.php` (loaded by `includes/bootstrap.php`,
so both InsightWeb and RankWeb's bridge load it):

- **Screen [A] admin:** InsightWeb → Settings, group "Keys" on the Kits admin shell
  (`admin.php?page=aw-search-insights&tab=settings#keys`), drawn with the shell's own `SettingsGroup`,
  `SettingsBlock` and `SecretField` (password box with Show/Hide, as MailWeb's secrets), administrators only. Each
  key shows its state: set in wp-config.php (the box is disabled), saved here, saved under Kits → Keys (old place,
  still used), or none saved. The box is always empty; an empty box keeps the saved key; "Remove the …" clears one.
  No new admin page, no CSS. The shell has no no-JS form (without JavaScript it says the screen needs it).
- **Route [A] admin:** `insightweb/v1/keys`, GET (states) and POST (`keys`: name => new value, `clear`: names), both
  `manage_options` with the `wp_rest` nonce (`kits_shell_rest_can`). Write-only: both answer with label, help, where
  and state per key, never a value. A key set in wp-config.php, an unknown name or a non-string value is ignored.
- **Option:** `insightweb_keys` (name => value), not autoloaded (`wp_set_option_autoload()` on first add), not in REST
  (`show_in_rest` false), not in the Kits → Tools export. Values: white space removed, at most 512 characters, unknown
  names dropped (the same rules as Kits → Keys).
- **Reader:** `insightweb_key( $name )`, `insightweb_key_where( $name )`. Precedence: wp-config constant (not empty) →
  `insightweb_keys` → **fallback** to the old `aw_keys` value, read directly. So live keeps reading the two keys it
  has today. `aw_si_key()` stays as a one-line wrapper of `insightweb_key()` for the existing callers.
- **Names:** constants keep their current names (`AW_CLARITY_TOKEN` …) so a constant set today keeps working. New code
  names are InsightWeb-prefixed (`insightweb_key*`, `insightweb_keys`, `INSIGHTWEB_KEYS_OPTION`).
- **Kits → Keys during the move:** InsightWeb still lists its five keys there (`aw_keys` filter, now in
  `insightweb-keys.php`). `aw_key()` is a wrapper: after the constant it asks the key's owner plugin through the new
  `kits_service_key` filter (InsightWeb answers with `insightweb_key()`), then the vault as before. Kits → Keys shows
  "Set in the plugin's own settings (that one is used)" when a plugin's own value is the one used, and offers no field
  for it then.
- **Uninstall:** with "Delete data" on, InsightWeb deletes `insightweb_keys` but keeps it **out of the uninstall backup
  file** (it is taken out before the backup reads the options, and put back if the backup fails), as Kits Core does with
  its ALTCHA key.
- **Words:** every "Animal Writes → Keys" / "Kits → Keys" pointer in InsightWeb (notices, the Alerts setup box, the
  Connected services link, the CLI error, the readme, the JS card description) now points to Settings → Keys.

## Stage 3: migration (built 19 Nov 2026 in `awcore-ops`; deploy runs the copy, deleting `aw_keys` stays owner-gated)

Update 19 Nov 2026: the copy is built as `insightweb_keys_copy()`, which runs once by itself on the first administrator request
after deploy and as `wp insightweb keys copy [--dry-run]` / `wp insightweb keys status`. The vault code (`keys.php`, the
screen, `admin_post_aw_keys`, the `aw_keys` and `kits_service_key` filters) is already deleted; `admin.php?page=aw-keys`
redirects to InsightWeb. Only the option `aw_keys` and InsightWeb's read fallback stay, until the owner's word (step 2,
point 3 below). See `docs/awcore-retirement.md` section 6.


### Step 1: copy InsightWeb's keys (one WP-CLI command, read-only check before and after)

Built in the follow-up PR, not in this one: `wp insightweb keys status` and `wp insightweb keys copy [--dry-run]`.

- `status` prints, per key, `NAME: set yes/no (config | insightweb | old)`. Never a value.
- `copy` reads `aw_keys` and, for each InsightWeb key that is set there and not yet in `insightweb_keys`, writes it
  into `insightweb_keys` (one `update_option`, not autoloaded). It prints `NAME: copied` / `already set` /
  `not in the old place`, and never the value. It writes no key value to any log, transient or file, and it never
  deletes or changes `aw_keys`. `--dry-run` prints the same lines and writes nothing.
- Expected on live (18 Nov 2026): `AW_BING_WEBMASTER_KEY: copied`, `AW_CLARITY_TOKEN: copied`, the other three
  `not in the old place`.
- Check: `wp insightweb keys status` shows both as `set yes (insightweb)`. InsightWeb → Settings → Keys shows
  "set here", and Kits → Keys shows "Set in the plugin's own settings". Then the next Clarity run (daily 06:30 IST)
  and Bing run (2nd of the month 07:40 IST) are OK on Settings → Run log.

No other plugin has vault keys, so there is no other copy command. A future plugin with a key follows the same shape:
its own `<slug>_keys` option, a reader with constant → own option → old vault fallback, and `wp <slug> keys copy`.

### Step 2: retire the vault (separate follow-up PR, after the owner confirms every key shows "set")

1. InsightWeb: drop the `aw_keys` fallback and the `aw_keys` / `kits_service_key` filters from `insightweb-keys.php`.
2. Animal Writes Core: remove `includes/keys.php` (`aw_key()`, `aw_key_where()`, the screen and `admin_post_aw_keys`).
   Keep a redirect: `admin.php?page=aw-keys` → `admin.php?page=aw-search-insights&tab=settings`, plus a short notice
   "Keys now live in each plugin's settings". Remove `aw-keys` from `admin-home.php` (screen list, fold map, the two
   Home lines), `help-tab.php`, `data/edit-screen-map.json` and the Kits Core menu tests.
3. **The option `aw_keys` is deleted only on the owner's word**, after step 1 is checked on live. Until then it stays
   as it is (also listed in Core's `uninstall.php:73`). Before deleting it, export the database to a file and check its
   size (on this host `wp db export -` piped to gzip gives an empty file).
