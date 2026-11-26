# Pending (Harmonized 26 Nov 2026)

Check this list against git and the live site before acting on it. Refer to items by their bold title (owner, 24 Oct 2026). Failing AI keys are the owner's own job: never list them. Finished work is archived in [`docs/archive/pending-done-2026-11.md`](archive/pending-done-2026-11.md).

## Repository & Architectural Partitioning (22 Nov 2026)
Pending tasks are strictly partitioned by domain:
1. **Kits Platform & Component Roadmaps**:
   - **Theme Engine**: [`themes/kits-by-wugweb/PENDING.md`](../themes/kits-by-wugweb/PENDING.md)
   - **Kits Core**: [`plugins/kits-core/PENDING.md`](../plugins/kits-core/PENDING.md)
   - **DirectoryWeb**: [`plugins/directoryweb/PENDING.md`](../plugins/directoryweb/PENDING.md)
   - **RankWeb**: [`plugins/rankweb/PENDING.md`](../plugins/rankweb/PENDING.md)
   - **MailWeb**: [`plugins/mailweb/PENDING.md`](../plugins/mailweb/PENDING.md)
   - **SourceWeb**: [`plugins/sourceweb/PENDING.md`](../plugins/sourceweb/PENDING.md)
   - **InsightWeb**: [`plugins/insightweb/PENDING.md`](../plugins/insightweb/PENDING.md)
   - **PlanWeb**: [`plugins/planweb/PENDING.md`](../plugins/planweb/PENDING.md)
2. **Animal Writes Site Operations & Content Roadmap**:
   - Active site-specific roadmap: [`docs/animalwrites-operations-and-content-pending.md`](animalwrites-operations-and-content-pending.md)
   - Decoupling inventory: [`docs/animalwrites-monorepo-migration-inventory.md`](animalwrites-monorepo-migration-inventory.md)

---

## Open

### Campaigns & Theme (26 Nov 2026)
- **Rabies awareness campaign (`tools/content/rabies-campaign-page.php`, 26 Nov 2026):**
  - Run `wp eval-file tools/content/rabies-campaign-page.php` on WordPress to create the draft `rabies-awareness-india` campaign, map existing rabies `post` / `aw_news` / `aw_legal` / `aw_press` items into `campaign-rabies-india`, and register `campaign-rabies-india` in `aw_sources['topic_tags']`.
  - Review and publish the draft in `wp-admin`.
  - Once published, update [`tenant/assets/site/patterns/rabies-card.php`](../tenant/assets/site/patterns/rabies-card.php) on Home from `/resources/rabies-after-a-bite-india/` to `/campaigns/rabies-awareness-india/`.
- **Lab Animals Rules 2026 (`/lab/`, `tools/content/lab-animals-campaign-page.php`):**
  - Renamed from `campaign2-page.php` / `campaign2-home-band.php` on 26 Nov.
  - Consultation closed 5 Nov 2026: update `rank_math_title` / `rank_math_description` to post-consultation wording, and ship pending Share (`animal-writes/share`) + Date-card (`kit-dateline`) content edits.
- **Stacked card thumbnail mask (`kit.css` / `kit.min.css`, 26 Nov 2026):**
  - Committed (`ed12bd32`) so `.kit-card--stacked > .kit-card-media img` masks cleanly inside the card bounds; deploy to live.

### Plugins do not own data; SCF check (owner, 26 Nov 2026)
- **Principle:** plugins hold code, not data. Content and settings live in WordPress (`post_meta`, `term_meta`, `options`, custom tables), with field definitions in `acf-json/`.
- **SCF database groups must stay:** The 6 database copies (Organisation, Campaign, Library, Resource, Opportunity, Source details) are required alongside JSON groups (21 served, 126 fields). Do not trash an SCF group that has a JSON file; sync it instead.
- **Leftover inactive rows:** Three inactive-theme Global Styles rows (`29981`, `25164`, `8868`) and the 38-byte `material-design-google` custom CSS row (cannot be deleted via REST).
- **Open, owner to choose:** (1) leave as is; (2) add non-SCF fallback boxes for non-listing groups; (3) have SourceWeb rules and campaign tools read JSON files directly without SCF (recommended first).

### Plan files (`docs/plans/`, checked 24 Nov 2026)
Eight open plans remain in [`docs/plans/`](plans/) (index: [`docs/plans/README.md`](plans/README.md)):
- **Help-now keyword & content strategy** (`concurrent-orbiting-taco`): `help-now-keywords-2026-11-01.csv` exists; content-strategy file & per-page briefs open.
- **Technical to-dos, 1 Nov** (`enchanted-wondering-clover`): mostly done; `docs/seo/cdn-hpanel-proposal.md` not written.
- **Master search plan & quick fixes** (`fuzzy-prancing-finch`, `rustling-crafting-glade`): partly done; copy edits await owner's yes.
- **AW SEO Plus & Search Insights** (`pasted…54bc`): partly done; 7-tab Settings & Ubersuggest/vidIQ/OpenSEO imports unconfirmed.
- **Edit screens** (audit + 7-step plan): Step 7 (map) open; listing split moved to DirectoryWeb.
- **Workers audit** (`deep-sparking-harbor`): window default still 2 days, no `tools.yml`, old one-off workflows still present; awaits owner's go-ahead.
- **Directory official import:** dry-run stage; `find_datagov.py` missing; no Dataful CSVs yet.
- **Skill plans:** tracked in `wugweb_site/docs/plans`.

### Platform & Branch Sync
- **Kits Core open:** `theme.json` layer-3 injection check + transient cache, Cloudflare Worker two-way relay, 4-step onboarding wizard, Scripts Manager hardening (`plugins/kits-core/PENDING.md`).
- **DirectoryWeb open:** `kits/collection-filter` block (keeps `[aw_directory_filter]` until shipped), §2.4 shell requests, old Claims address 301.
- **Claim Listing Flow & Profile Completion (B5 & B6):** ⏸️ On HOLD pending upstream Kits platform maturation (owner decision, 24 Nov 2026).
- **Taxonomy rename:** `taxonomy-rename.php` (`aw_campaign_link` → `kits_campaign_link` via **Tools → Taxonomy rename**) not run yet.
- **Unaudited branch:** `origin/feat/repo-restructure-tenant-consumer` (last commit 22 Nov) needs stale-branch audit before any merge.

---

## Decided: never / dropped (owner, 22–25 Oct 2026)
- **Revenue pages:** no `/go/`, `/sponsor/`, or `/advertise/` pages, ever.
- **NGO Darpan import:** unused (no Darpan CSVs).
- Removing Volunteer tag from the 184 campaign senders who unticked the box.
- Rotating Hostinger Mail API token; restoring Fluent Forms demo forms 1 & 2; rewriting 11 Wildlife SOS job summaries or dropping JobPosting markup; press outreach asking for credit/links; "Index or noindex News" (all News indexed); featured-first ordering / pricing after 90 days / CSR one-pager; Emergency help photo unchanged.

---

## Dates & Upcoming Checkpoints
- **PlanWeb:** Branch `claude/planweb-ics-1114` (calendar `.ics` feed, multi-channel entries, Threads/Bluesky, Done status) and `claude/planweb-ui-next` await owner approval/merge; month/week day-grid and reminder settings wait for phase 3.
- **29 Nov 2026:** Help-now guides 28-day checkpoint (`1962`, `who-to-call`, `injured-animal`, `dog-bite`).
- **2027 seasonal reminders:** Tiger Day (1 Jul), World Rabies Day (31 Aug), World Animal Day / Animal Welfare Day (6 Sep), Dolphin Day (7 Sep), Kukur Tihar (30 Sep — verify date).

---

## Notes to keep
- **FluentCRM:** Contact, Volunteer, and Claim senders are all on the Newsletter list; no form changes a contact's subscription status. Any future automation/campaign must target subscribed contacts only.
- **GA4 rule:** Never send test/simulated events to live GA4 (exclude `TEST-CLAUDE*` from 22 Oct). Verify GTM in Preview / Tag Assistant only.
- **404 / Error tracking & Rollback:** 404 returns HTTP 404 and sends `404_view`; releases newer than 24 Oct purge LiteSpeed cache via `deploy-purge.php`.
