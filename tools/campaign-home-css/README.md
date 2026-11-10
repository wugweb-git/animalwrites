# Home CSS extracts

`extract.mjs` loads the home page at 375, 768 and 1200 px and writes the rules of one stylesheet that match something on the page (its CSS coverage) and the state variants kept by `STATE`. GTM, GA4 and Clarity are blocked, so no analytics hit is sent.

```
cd tools/campaign-home-css && npm i puppeteer-core@23.5.0 && node extract.mjs https://animalwrites.in/ /campaign-record.css record-used
```

`campaign-home.css` is gone (10 Nov 2026): the Home campaign hero now uses `aw-camp-tokens.css` and `aw-camp-band.css`, which are kept by hand.

The script makes `campaign-record-home.css` (the community-dogs story, pattern `animal-writes/aw1-story`): `node extract.mjs https://animalwrites.in/ /campaign-record.css record-used`, then copy `record-used.css` under that file's header. While the pattern is missing from Home, the full campaign-record.css loads.

Core block stylesheets (home): `STATE='is-menu-open|has-modal-open|responsive-container|submenu|has-child' node extract.mjs https://animalwrites.in/ /navigation/style.min.css nav-used` and `STATE=zzzz node extract.mjs https://animalwrites.in/ /cover/style.min.css cover-used`; copy each result under the header of `themes/animal-writes/assets/vendor/wp-core/home-navigation.css` / `home-cover.css`. Copy the finished file to the same path under `plugins/animal-writes-core/theme/` too (`tools/ci/theme_copies.py` fails when the two differ); an `OUT` path under `themes/animal-writes/` is written to both places by the script. `STATE` is a regex for state variants to keep (default: the campaign band's). Used only while WordPress is `AW_HOME_CORE_CSS_WP` (themes/animal-writes/functions.php); change the constant after regenerating for a new version.
