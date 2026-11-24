# CC master plan (19 Nov 2026): claim flow, listing merge, clean-ups

**CC: read the sections marked "CC". Ignore the appendix.** Base: `origin/wordpress-dev`. Rule 1: validate against live before and after. Ship only through `tools/aw-ship.sh`; never during a FluentCRM send. Test at 375 px wide on a phone-sized browser. Report back after each batch.

## Order of work
| Batch | Items | Status |
|---|---|---|
| 1 | B1, B2, B3 (About pre-fill as plain text, 8 MB uploads with one message per cause, error summary after a failed send) | ✅ **Live** (PR #164, merged 20 Nov 2026, release `6230ac51`). Checked 24 Nov: the claim form carries the 8 MB limit and the summary script. Not re-tested on a real submit; live `upload_max_filesize` / `post_max_size` still to read |
| 1 | B4 (merge 18308 into 43, Darpan data) | ✅ **Done on live** (20 Nov 2026): 301 and the 43 pre-fill checked 24 Nov |
| 1 | C2 (press focus clear) | ✅ **Live** (PR #165, merged 19 Nov 2026). The 21230 clear is a post-deploy step, not yet confirmed |
| 1 | C1 (old mail route) | ✅ **Done, alias kept on purpose** (`b7b61d32`): both routes answer 200 (24 Nov). Removing the alias later waits for the `aw_mail_in_old_seen` read |
| 1 | C3 (404 clean-ups) | ✅ **Done in code** (22 Nov). Note: a live probe on 24 Nov still answered 404 for the `tel:` paths; re-probe after the next deploy and purge. Old asset paths: 404, nothing prints them |
| 2 | B5 (3-step claim form & Indian address/initials schema tolerance) | ⏸ **On hold** (owner, 24 Nov): until the Kits Core form engine and theme components mature |
| 3 | B6 (profile screen after approval) | ⏸ **On hold** with B5; after batch 2 is live and measured |

# CC: Part B. Claim flow and listing merge
B1 to B4, C1, C2 and C3 are done (see the table for the notes). Names added or touched by this work use the plugin initials: `DW_CLAIM_*` constants, `dw_claim_plain()`, `dw_claim_upload_error()`. **B5 and B6 are on hold (owner, 24 Nov): do not ship them without the owner's go.**

## B1. About is pre-filled with HTML (bug)
- `textarea[name=l_about]` on `/claim/?listing=18308` holds the rendered listing content, including `<span class="kit-date aw-date"><span class="kit-ico kit-ico--calendar"></span><time datetime="2023-12">Dec 2023</time></span>`. Visitors see raw tags, and a send would propose the markup as the new About.
- Pre-fill every text field from stored text, never rendered output: strip tags, turn a date chip into its text ("Dec 2023"), decode entities. Check the other pre-filled fields the same way.
- In DirectoryWeb Claims (`claims-review.php`, `'l_about' => array( 'About', 'about', 'about', true )`) strip tags from a proposed About before it is applied.
- Scan live for other listings whose stored About or content holds `kit-date` or `kit-ico` markup. Report the count.

## B2. Uploads: one message for four causes
- `kits_ff_upload()` (`kits-core/includes/forms/fluentforms.php`), `aw_claim_logo_ok()` and `AW_CLAIM_LOGO_BYTES` (`directoryweb/includes/claims.php`) all answer "Use a PNG, JPG or WebP under 2 MB." for a failed upload, a wrong type, a file over 2 MB and a failed resize. Live test: a 2.42 MB PNG is refused; 0.49 MB and a 5000x5000 flat PNG (91 KB) are accepted.
- Raise the limit to 8 MB. Check `upload_max_filesize` and `post_max_size`. WP_MEMORY_LIMIT is 40M, so confirm a large photo still resizes, or downscale in the browser first. The server already resizes to 800 px.
- Say what happened: "This file is 3.4 MB. The limit is 8 MB." / "This is a HEIC file. Use PNG, JPG or WebP." Same for the five photos and the proof file.
- Once chosen, show the file name, size and a Remove button.

## B3. Send jumps back to step 2 without saying why (bug)
- `forms.js` (`kits-core/assets/js/forms.js`, about line 345): `fluentform_validation_failed` opens the first step with an error (`.ff-el-is-error` or `[aria-invalid=true]`). Nothing on the last step says what happened.
- Keep the jump, and first show a summary on the last step: "1 thing to fix in step 2: Logo (file is too large)", each item a link that opens that step and focuses the field. Do the same for any later failing step. Keep a field's error visible after the step changes.

## B4. Merge listing 18308 into 43, and add the Darpan data
Keep 43 because: the slug has no city suffix, it carries the organisation's own site and socials and a full address, and it is older. 18308 is an AWO List import with a Justdial address.
1. Before touching anything, check DirectoryWeb Claims for a pending or approved claim on 18308 (the friend's test). Move it to 43 or reject it as a test; report which.
2. Copy to 43 only where 43 is empty: phone 7905934763 (`aw_phones`), `aw_head` ("Dr. Vishakha Shukla", drop the "per LinkedIn title" note), `aw_area` "Lucknow, Uttar Pradesh". Keep 43's address (Sandhu Farms Road, Makhdoompur Kaithi, Lucknow 226301). Drop the Justdial address.
3. Set on 43: `aw_darpan_id` = `UP/2026/1159491`, `aw_founded` = `2021`. Add "Nawabi Tails Rescue Foundation" as Also known as. Do not set `aw_reg_date` (only the year is known). Do not store the date of generation, the RC expiry or any email shown on the Darpan portal. The registered district (Kanpur Nagar) differs from the Lucknow addresses: add it as a note for the reviewer, do not change the address.
4. 301 `/directory/org/nawabi-tails-rescue-lucknow/` to `/directory/org/nawabi-tails-rescue/`, trash 18308, remove it from the sitemap and from `aw_seo_keep_listings` if present.
5. Report: a before and after field table, the redirect test (301, final 200), and the listing count (down by 1).
6. Then find similar pairs on live: same website or email, or the same name without the city suffix (the 12 Nov exact-title scan misses these). Report the count only. Merge nothing else without the owner.

## B5. Stage 1: the claim form (3 steps)
**Global:** red asterisk on every mandatory field and a "* required" legend at the top; no "(optional)" in labels. Validate on blur and on Next. Progress text "Step 1 of 3" (`aria-current="step"`). Next and Back full-width and sticky at the bottom on mobile, Back as a text link. All inputs 16 px, all tap targets 44 px. Hide the site-wide newsletter box on `/claim/`. Step labels: Who you are / Proof / Send.

**Mandatory:** full name, role, email, mobile (10 digits), proof file, "details are correct", "I work here and you may keep these details". Conditional: if Address line 1 is filled, City, State and PIN are required.

**Step 1: Who you are**
| Field | Type | Rule |
|---|---|---|
| Full name * | text | 2+ characters |
| Role at *[listing]* * | select | |
| Email * | email | valid; a domain match with the listing website is a trust signal for the reviewer, never a block |
| Mobile * | text | 10 digits |
| On WhatsApp | checkbox | unticked by default |
| Show my name on this listing | toggle | default **Yes** |
| Join us as a volunteer | checkbox | unticked by default. Label: "Join us as a volunteer." with the words "volunteer" linked to `/volunteer/` (`target="_blank" rel="noopener"`). A normal opt-in checkbox, like the newsletter one. |

**Step 2: Proof**
| Field | Type | Rule |
|---|---|---|
| Document showing you work there * | file or image | `accept="image/*,application/pdf"`, no `capture`, so phones offer camera, photos and files; up to 8 MB; shows name, size, Remove |
| NGO Darpan ID | text | `XX/YYYY/NNNNNNN`, case-insensitive, stored upper-case; pre-fill from `aw_darpan_id` |
| Registration year | text | 4 digits |
| Name as on Darpan | text | not forced equal to the listing name; the review screen shows a soft match for the reviewer, never blocks |
| Anything else we should know? | textarea | 1,000 characters |

**Step 3: Send**
- Read-only summary of what is being sent, with the names of attached files.
- "The details I have given are correct." * (do not say "documents"; the file list sits under it).
- "I work at *[listing]* and Animal Writes may keep these details to check this request. I can ask for them to be deleted at any time." *
- One newsletter checkbox: "Email me Animal Writes updates. I can unsubscribe at any time." Unticked by default.
- Button: "Send for review", with the line "We reply in 4 to 5 days."
- Honeypot `kits_hp` stays: hidden from people and screen readers (`aria-hidden`, `tabindex=-1`, off-screen); bots fill it and are dropped. It is not a user field.

**Volunteer checkbox (owner: "normal"):** a plain opt-in. If ticked on a valid submit, add the claimant's FluentCRM contact to a tag `volunteer-interest` and send one email: "Thanks for offering to volunteer. Finish your volunteer profile here: /volunteer/". Create no volunteer record and map no Form #5 fields. Unticked: nothing happens.

**Mandatory (owner, 19 Nov):** email and mobile are mandatory. I kept name, role and the proof file mandatory as well, because a claim with no proof cannot be checked; the owner can drop the proof requirement.

**Events** (no field values, no personal data, through the existing GTM data layer, no new analytics tool): `claim_view`, `claim_step_view{n}`, `claim_field_error{field,reason}`, `claim_upload_fail{reason}`, `claim_submit`, and `claim_approved` (server side).

## B6. Stage 2: Complete your profile (after approval)
A separate screen opened by an emailed link. Only empty fields are expanded. Each section saves on its own. Progress line and "Save and finish later".

1. **Contact:** listing email; one phone row (number, label, WhatsApp tick) and "+ Add phone", up to 3.
2. **Address** (Indian standard): Address line 1, line 2, Area or landmark, City *, State * (select of 36 states and union territories), PIN * (`^[1-9][0-9]{5}$`), map link. Opening hours.
3. **About:** About (plain text, tags stripped), also known as, year established, head, animals helped, areas served, services, languages.
4. **Pictures:** logo (8 MB, resized to 800 px), photos (up to 5).
5. **Online:** website, Instagram, Facebook, X, YouTube, LinkedIn, adopt link.
6. **Support:** the organisation's own volunteer link (optional) and adopt link. (The "Join us as a volunteer" checkbox is in claim step 1, B5.)
7. **Registrations, regrouped:**
   - Registration: registered as, number, authority, act, date.
   - Tax exemption: 12A, 80G.
   - NGO Darpan: ID, registration year, name as on Darpan (shows a "Verified from NGO Darpan" chip once checked).
   - FCRA.
   - AWBI, together: code, recognised on, renewed on, valid until.

**Privacy switches (visible "Public / Hidden" on each):**
- Always private: claimant mobile, login email, proof file.
- Show or hide: claimant name and role, head of organisation, each phone, the WhatsApp number, the public email, full address or city only.
- Public by default: organisation name, city and state, website and social links, registration and Darpan IDs.


## C. Clean-ups (from the 18 Nov prompt; independent of the claim form)

### C1. Remove the old `aw/v1/mail-in` webhook route
- On 18 Nov the Hostinger webhook for support@animalwrites.in was re-pointed (same secret) to `https://animalwrites.in/wp-json/mailweb/v1/mail-in`. Test delivery returned 200.
- Before removing: read option `aw_mail_in_old_seen` on live. It must show no delivery after 18 Nov about 09:44 UTC. If a mailbox other than support@ is still listed, stop and report.
- Remove the `aw/v1/mail-in` POST and GET routes in `plugins/animal-writes-core/includes/mailweb-aw.php` (about lines 320 to 355) and the header comment at line 10. Keep `mailweb/v1/mail-in`. `aw_mail_in_old_seen` stays in `uninstall.php` for one release, then goes.
- Proof: `/wp-json/aw/v1/mail-in` answers 404; `/wp-json/mailweb/v1/mail-in` GET answers `{"status":"ready"}`; send one test mail and see MailWeb's unread count move.

### C2. Press items cannot clear a focus keyphrase
- `aw_press` has no Rank Math box, and `POST /wp-json/aw/v1/seo-focus` (`plugins/aw-seo-extras/includes/focus-keywords.php`) refuses it (403). Item 21230 ("Lab animal rules need fixing") still holds the keyphrase "lab animal rules", which conflicts with the Lab page.
- Allow `seo-focus` to write and clear `rank_math_focus_keyword` on `aw_press` for an administrator; an empty keyword means clear. No new UI. Then clear it on 21230 and check that InsightWeb's Conflicts count drops. Items 14205 and 14206 were cleared on 3 Nov; confirm they are still empty.

### C3. Stale 404s seen in Rank Math's 404 log on 18 Nov
- `tel:1962` (42 hits), `tel:112`, `tel:01292555700`: a `tel:` link is being requested as a path. Find which blocks or templates print it without the scheme being kept (likely a URL-normalising filter in kits-core or RankWeb internal links) and fix it. Check Home, the 1962 guide and the Directory phone sheet.
- Old child-theme asset paths still requested: `wp-content/themes/animal-writes/assets/css/aw.css` (172 hits), `.../tokens.css` (82), `.../wordmark-light.svg` (10), `wp-content/plugins/animal-writes-core/assets/css/dir-map.css` (25). Find who prints them (cached HTML on the CDN, a stored option or block, an external reference). If it is cached HTML, say so and propose a purge. No redirects before the cause is known.
- `https://animalwrites.in` as a path (101 hits) and `aw_opportunity-sitemap.xml` (42 hits, known): report only.
- Not for CC: `wugweb.com/kits/update.json` is a different site, off on purpose; the owner decides.

## Report back (all items)
For each of B1 to B4: what changed, the live proof, and anything that did not match this spec. Stop and ask if a claim on 18308 exists and is not clearly a test.

---

# APPENDIX (owner only, CC ignore)
Part A is for the owner (plan and decisions). Part B is for CC: **CC reads Part B only.**

---

# PART A: Plan (owner)

## A1. Where we are
- Live form `/claim/?listing=ID` (Fluent Forms #6): 4 steps, 59 inputs. At 375 px step 1 alone is 2,737 px tall. 4 inputs are under 16 px (iOS zooms), 4 tap targets are under 44 px. No sideways scroll, every input labelled.
- Bugs from a friend's live test on listing 18308: About pre-filled with HTML; uploads over 2 MB refused with one vague message; Send jumps back to step 2 with no explanation.
- Duplicates: listing 43 "Nawabi Tails Rescue" and 18308 "Nawabi Tails Rescue, Lucknow". Decision: keep 43.
- NGO Darpan (screenshot): Nawabi Tails Rescue Foundation, ID UP/2026/1159491, registered 2021, district Kanpur Nagar, status Active. Both listings say Lucknow; the mismatch is noted, not guessed.

## A2. The split, explained
Today: 4 steps, one sitting, 59 fields. The split is not 2 more steps.
- **Stage 1, Claim:** 3 steps, about 10 fields, about 2 minutes. Proves you run the organisation.
- **Stage 2, Profile:** a separate screen opened by emailed link after approval. Only empty fields are open; each section saves on its own.
- Net: the claim goes from 4 steps to 3 and from 59 fields to about 10. The other 45-plus fields come later, only for approved claimants.
- Alternative: keep 4 steps with cleaned-up fields. It fixes labels and validation but step 2 stays at about 45 fields.
- Recommendation: split.

## A3. Phases
| Phase | What | Ships |
|---|---|---|
| A | B1 to B3 bug fixes, B4 listing merge and Darpan data | first |
| B | B5 Stage 1 rebuild | after the owner's yes on the split |
| C | B6 Stage 2 profile screen | after B is live and measured |
| D | Measure and audit (A5, A6) | 2 weeks after B |
| E | Announce to NGOs (A7) | after B is stable |

## A4. Design (frontend-design lens)
No new look: the form uses the Kits theme tokens already on the site, so /claim/ does not look foreign.
- One column, one job per step. Step titles say what they are ("Prove you work there"). "Step 1 of 3" in text, not only a bar.
- Sticky bottom action bar on mobile: full-width Next, Back as a text link.
- Errors in plain words under the field, plus a summary at the top of the failing step with links to the field.
- The memorable moment is the Send step: a read-only summary of what is being sent (including file names) and what happens next ("We reply in 4 to 5 days").
- Copy: verbs on buttons ("Send for review"), sentence case, red asterisk only on mandatory fields, no "(optional)" on every label.

## A5. Audit (ux-ui-audit lens)
Measured so far: 4 inputs under 16 px, 4 controls under 44 px, no horizontal scroll, all inputs labelled, no progress text.
Not yet run: contrast, focus ring, keyboard path, screen-reader names, error states, 1280 px width.
After Phase B, run the probes at 375 and 1280 px and report each finding with its number (WCAG 2.2: 1.4.3, 1.4.11, 2.5.8, 3.3.1, 3.3.2, 4.1.2). Nothing in the audit is claimed until it is measured.

## A6. Data and testing (explore-data, analyze, experimentation lenses)
- **Before Phase C:** profile the listing fields on live (fill rate of phone, email, address, About, Darpan ID, registration, AWBI dates, social, photos). A field filled on under 5% of listings and used by no visitor is dropped or hidden. DirectoryWeb's completeness score (8 signals) is the starting table.
- **After Phase B:** funnel from the events in B5 (views, each step, submit, approved): drop-off per step, top error fields, upload failure reasons, time to review, approval rate. The old form's numbers cannot be rebuilt, so read the first 2 weeks as a baseline and as directional.
- **Testing:** claim volume is low, so a true A/B split would take months and prove nothing. Change one thing at a time, in sequence, with the rule written first: primary metric is claims submitted divided by claim-page starts; win is +20% over the 2-week baseline with approval rate not lower. Order: step count and field cut, then the Send summary, then the intro copy. A tie is a valid result. Confirm over 3 windows before calling anything a rule.

## A7. Announcing it (batch-content-plan, interactive-content lenses)
Only after Phase B is live and has run 2 weeks. Four weeks, 3 posts a week, one handle across Instagram, X and Threads. Nothing is scheduled by Claude; the owner or the social worker confirms and publishes. Poll and question stickers are added in the app by a person.
- Week 1, "Is your listing right?": a poll on what adopters check first (phone, address, photos); a carousel "How to claim in 2 minutes".
- Week 2, "What a complete listing looks like": before and after of a real listing the owner agrees to show; a question box, answered next week.
- Week 3, "Rescues on Animal Writes": 3 real organisation spotlights, with consent, no invented quotes.
- Week 4, "Keep it fresh": a reminder to update photos and hours; the poll results shared back.
Rules: only real results; share an answer only with permission; no giveaways.

## A8. Owner decisions
1. Split into claim then profile: owner unsure, so the decision is Claude's: **split** (reason: 59 fields in one sitting is the problem the friend hit). B5 and B6 still wait for the owner's go-ahead.
2. Email and mobile mandatory: decided. Proof file mandatory: kept by Claude, owner may drop it.
3. Volunteer checkbox in claim step 1 that makes the claimant a volunteer: decided (see B5).
4. Hand B1 to B4 to CC: decided, now.

---