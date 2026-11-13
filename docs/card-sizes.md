# Card sizes (21 Oct 2026)

Limits every list card keeps, set in `themes/animal-writes/assets/css/aw.css` (custom properties on `:root`):

| Limit | Value | Property |
|---|---|---|
| Card, minimum width | 18rem (288px, fits a 320px screen) | `--kit-card-min` |
| Card width | none: a card fills its grid cell; the grid (columns, gap) sets the width | n/a |
| Stacked picture | 4.5rem to 15rem (72 to 240px); wide stacked cards 30% of the card inside those limits | `--kit-thumb-min`, `--kit-thumb-max` |
| Block card picture (picture above text), maximum height | 22.5rem (360px) | `--kit-card-media-max` |
| Text column in a stacked card, minimum width | 10rem (160px) | `--kit-body-min` |

Card states: `kit-card--block-lg|md|sm` (picture above the text) and `kit-card--stacked-lg|md|sm` (picture left). List cards (the `kit-card--*` states) and the Card block style have 16px padding (`--kit-space-4`) on every side at every width by default, and at least 12px (`--kit-space-3`). News, Library and Resources lists are stacked (owner, 13 Nov 2026: "i like stacked version and lesser padding"; "max 2:1 and 3:5"; "stacked card minimum width will be 350px, max is 600", then "Min 340px, two"): every card `kit-card--stacked-md` in an `aw-stack-list` grid. **Stacked list:** card width 340px to 600px (`--kit-card-stacked-min`, `--kit-card-stacked-max`; phones narrower than 340 take the full width); gap 16px (`--kit-space-4`); one column below 1200, two from 1200 (owner, 13 Nov 2026: "keep the news feed single-column at tablet widths", "never 3 column, max 2"); a card stops at 600px, so a wider list leaves room at the end of the row; the picture is 30% of the card (`--kit-card-stacked-media-width`) at 3:4 (`--kit-card-stacked-media-ratio`, Theme Options → Cards), flush to the card's top and left edge, cover crop; picture and text sit at the top and the words set the card's height, the picture never stretches it; the text padded 16px (`--kit-space-4`), title H3, three lines at most, no summary; the meta row is one line (owner, 13 Nov 2026: "metarow - it is a single row - will always be a row"), date first and never cut; the first chip ends in an ellipsis when it is too long, a chip with no room left drops out whole (no empty slivers); with no chips the last meta item ends in an ellipsis; a card whose words need more room grows taller; Theme Options → Cards can set it higher (Global Styles `settings.custom.kits.card`; owner, 11 Nov 2026). Every other card and block has at least 24px (`--kit-space-5`; owner, 2 Nov 2026). Both are checked by `tools/ci/theme_rules_check.py`, kind `pad`. The meta row (date, read time, place) wraps to a second line when it is full; the date stays first. The wide stacked card has a 30:70 picture-to-text split; its gap runs from 12px on the narrowest screens to 24px (`clamp`, no extra breakpoint: the CI check allows only 767/768 and 1199/1200). Three-across rows (`.is-three`: Home News and Resources sections) become two across when a third card would be under 18rem.

One look (10 Nov 2026): the theme templates' Query Loops (archive, tag, search, blog index) print the same card, `kit-card--block-md` (`kits_card_markup()` in the Kits functions.php): white raised surface, 1px line border, card radius, no resting shadow, `--kit-elevation-2` and a title underline on hover, picture flush at 4:3, body padded 16px (`--kit-space-4`, or the Theme Options → Cards value), date first. The Card block style (Group → Card, theme.json `kits-card`) uses the same values: background preset, border preset, card radius, and the same padding (the Cards value, else preset 40, 16px).

Campaign cards (`aw-story--card`) and Directory rows (`aw-org`) are separate components: Directory rows are capped at 720px (by their list container); campaign cards follow the card grid.

To check: load a list page in an iframe at 320, 375, 480, 600, 768, 900, 1024, 1200 and 1440px wide and read each card's width, picture size and text-column width; none should be outside the limits above and no page should scroll sideways.

**Card type in steps (owner, 13 Nov 2026: "h3 one size per breakpoint", "metarow - one font size per breakpoint"):** every card's H3 title is `--kit-type-card-title` (20px, 22px from 1200; owner, 13 Nov 2026: "20-22px desktop, 18-20px mobile") with the H3 line height (`--kit-h3-leading`); everything in a card's meta row (date, read time, chips) is `--kit-meta-size` (12px, 13px from 768). Neither is fluid, so they change only at a breakpoint (aw.css, Card type in steps).

**Picture ratios (owner, 13 Nov 2026):** four display ratios, set by tokens and changed in Kits → Theme Options → Cards (Global Styles `settings.custom.kits.card.ratio.*`); the source image is never cropped, only shown with `object-fit: cover`. The Site Editor's aspect-ratio menus (Image, Featured Image) offer the same set, Standard 4:3, Portrait 3:4, Featured 3:2, Square 1:1 and Wide 16:9 (Kits theme.json `settings.dimensions.aspectRatios`, WordPress's own list off). Card templates and the Kits card markup (`kits_card_markup()`, archive, home, index, search and tag templates, the lead-list pattern, the item page's More cards) print no ratio of their own, so the token (and Theme Options) sets it; item lead pictures keep 16:9.

| Ratio | Token | Used for |
|---|---|---|
| 4:3 standard | `--kit-card-media-ratio` (`--kit-media-ratio-standard`) | cards with the picture above the text (archives, search, categories, story cards, Home Learn row, mini cards, Kits news list), Directory organisation photo, volunteer side photo |
| 3:4 portrait | `--kit-card-stacked-media-ratio` (`--kit-media-ratio-portrait`) | stacked cards: News, Library and Resources lists, Home news and Library rows, the Home lead on phones |
| 3:2 featured | `--kit-card-featured-media-ratio` (`--kit-media-ratio-featured`) | lead stories: Kits news lead, Home Library lead from 768 to 1199 |
| 1:1 square | `--kit-card-square-media-ratio` (`--kit-media-ratio-square`) | compact square thumbnails (none yet) |

Page and block designs keep their own ratios: AW banner 16:5 (4:3 on phones), campaign hero art 10:9, campaign mosaic 16:9 and 3:4, Home mosaic 3:4, document covers 3:4, section pictures 4:5, placards 16:7 (16:9 from 768); the Home lead from 768 fills its column's height.
