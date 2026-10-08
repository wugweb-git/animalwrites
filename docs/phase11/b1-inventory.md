# B.1 Inventory: tags, chips, pills, labels, badges, buttons (before Phase 11)

K = themes/kits-by-wugweb/assets/css/kit.css · A = themes/animal-writes/assets/css/aw.css · S = plugins/animal-writes-core/includes/systems.php

| Old class | Defined | Emitted by | Renders on | Role | New primitive |
|---|---|---|---|---|---|
| aw-chips-nav / kit-chips-nav (`a[aria-current]` active) | K:563-566, 687-689 | `aw_filter_chips()` S:76-98 via `aw_list_filters()` | /library/ (6 rows), /news/, /resources/, /opportunities/ | filter (navigates) | kit-filter-group + kit-filter-pill |
| aw-dir-filter (+ aw-dir-q, -check, -actions, -clear) | A:153-164 | `aw_directory_filter()` directory.php:359-368 | /directory/ | filter form | Directory search bar (E2) with kit-field |
| aw-footer-tags / kit-footer-tags | K:675-679 | footer.html:39 + `[aw_topics_list]` pages.php | footer, all pages | navigates | kit-chip (on-ink) |
| wp-block-post-terms a | K:262-273 | `wp:post-terms` in single/archive templates | singles, archives, home | navigates | kit-chip |
| aw-chip / kit-chip | K:262-267 | — (not emitted) | — | — | kit-chip (new definition) |
| aw-pager .page-numbers | K:568-570 | `aw_pager()` S:101-104 | list archives, directory | navigates | unchanged (pagination) |
| aw-states a | A:167-175 | sections.php:97-99 | directory head state tiles | navigates | removed (E1) → Browse by state (E4) |
| aw-ed-states a | A:224-233 | sections.php:150-160 | home Find help | navigates | Browse by state (E4) |
| wp-element-button / wp-block-button__link | K:81-121, kits.css:19-26 | forms, single CTAs, heroes | many | action | kit-btn primary |
| is-style-outline | K:105-110 | listing contact buttons, kits hero | singles | action | kit-btn secondary |
| aw-btn / kit-btn (--outline/--ghost/--ink/--sm/--lg) | K:97-127, 588, 599-601 | header, directory rows, opportunity cards, home hero | many | action | kit-btn (one definition) |
| aw-header-cta / -search | K:433-438, 658-713 | header.html | header | action | kit-btn (icon) |
| aw-share | K:482-493 | share.php | singles | action | kit-btn secondary sm |
| aw-sh-primary / -secondary | K:418-423 | display.php:123 | phone sticky help | action | kit-btn |
| aw-social, aw-powered | K:391-405 | footer.html | footer | navigates | unchanged (footer-specific) |
| aw-org-go | A:427 | S:417 | directory rows | navigates | unchanged (row arrow) |
| aw-ed-more | K:594-596 | home patterns | home | navigates | unchanged (section link) |
| aw-state / --ok / --feat | A:421-424 | S:410-411 | /directory/ rows | state | kit-badge (success / accent) |
| is-style-kits-label | kits.css:10-17 | single templates, ad-slot, sponsored-label | singles | label | kit-eyebrow |
| aw-carousel-dots | K:458-468 | kit.js | phone carousels | action | unchanged |
| aw-issuer / --{slug} / --template | A:316-320, 553 | S:256, 274 | /library/ rows | static metadata | kit-tag (status variants) |
| aw-case-topic | A:321 | S:257, 275 | /library/ rows | static metadata | kit-tag |
| aw-kind / kit-kind | K:567 | S:446 | /opportunities/ | static metadata | kit-tag accent |
| aw-type | A:421, 425 | S:413 | /directory/ rows | static metadata | kit-tag |
| aw-srcs / aw-src-mark | A:563-571 | data-sources.php | directory rows, profiles | static metadata | kit-tag (source variants) |
| aw-src (news publisher row) | A:459-466 | attribution.php | news | metadata | unchanged (publisher row); class clash fixed |
| aw-press / aw-press-meta | A:542-545 | media-pr.php | /media/ | list | News card (I2) |
| aw-ad-label / kit-ad-label | K:497-502 | ads.php:28 | ad slots | label | kit-badge "Advertisement" |
| Dead (not emitted) | | | | | removed: aw-quick, aw-upload-*, aw-file-remove, aw-kind--act/judgment/report, aw-doc-tags, aw-res-tags, aw-search-type, aw-link-cta, aw-btn--ink |

Inconsistencies found: three badge radii (pill / 4px / 10px); wp-element-button vs aw-btn shapes differ; aw-btn--outline defined twice; `.aw-src` used for two things; font sizes outside the scale (0.75rem, 0.6875rem, 10px); hard-coded hex (#fff, source-mark colours, #232323, #2e2e2e) and rgba on dark surfaces; hard-coded px (24, 36, 40, 44, 52, 8, 22).
