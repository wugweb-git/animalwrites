# Card sizes (21 Oct 2026)

Limits every list card keeps, set in `themes/animal-writes/assets/css/aw.css` (custom properties on `:root`):

| Limit | Value | Property |
|---|---|---|
| Card, minimum width | 18rem (288px, fits a 320px screen) | `--kit-card-min` |
| Card width | none: a card fills its grid cell; the grid (columns, gap) sets the width | n/a |
| Stacked picture | 4.5rem to 15rem (72 to 240px); wide stacked cards 30% of the card inside those limits | `--kit-thumb-min`, `--kit-thumb-max` |
| Block card picture (picture above text), maximum height | 22.5rem (360px) | `--kit-card-media-max` |
| Text column in a stacked card, minimum width | 10rem (160px) | `--kit-body-min` |

Card states: `kit-card--block-lg|md|sm` (picture above the text) and `kit-card--stacked-lg|md|sm` (picture left). List cards (the `kit-card--*` states) and the Card block style have 16px padding (`--kit-space-4`) on every side at every width by default, and at least 12px (`--kit-space-3`). News, Library and Resources lists are stacked (owner, 13 Nov 2026: "i like stacked version and lesser padding"; "max 2:1 and 3:5"; "stacked card minimum width will be 350px, max is 600"): every card `kit-card--stacked-md` in an `aw-stack-list` grid. **Stacked list:** card width 350px to 600px (`--kit-card-stacked-min`, `--kit-card-stacked-max`; phones narrower than 350 take the full width); gap 16px (`--kit-space-4`); columns at the site breakpoints, one below 768, two from 768, three from 1200 only where three cards of 350px fit (the hub and campaign lists, 1120 to 1184px wide), two in the 720px main column; the card never flatter than 2:1 (`--kit-card-stacked-ratio`), the picture 3:5 (`--kit-card-stacked-media-ratio`) at the card's full height, flush to the card edge, so 30% of the width at 2:1; the text padded 12px (`--kit-space-3`), title H3, three lines at most, no summary; the meta row is one line (owner, 13 Nov 2026: "metarow - it is a single row - will always be a row"), date first, the last item ends in an ellipsis, the date never does; a card whose words need more room grows taller; Theme Options → Cards can set it higher (Global Styles `settings.custom.kits.card`; owner, 11 Nov 2026). Every other card and block has at least 24px (`--kit-space-5`; owner, 2 Nov 2026). Both are checked by `tools/ci/theme_rules_check.py`, kind `pad`. The meta row (date, read time, place) wraps to a second line when it is full; the date stays first. The wide stacked card has a 30:70 picture-to-text split; its gap runs from 12px on the narrowest screens to 24px (`clamp`, no extra breakpoint: the CI check allows only 767/768 and 1199/1200). Three-across rows (`.is-three`: Home News and Resources sections) become two across when a third card would be under 18rem.

One look (10 Nov 2026): the theme templates' Query Loops (archive, tag, search, blog index) print the same card, `kit-card--block-md` (`kits_card_markup()` in the Kits functions.php): white raised surface, 1px line border, card radius, no resting shadow, `--kit-elevation-2` and a title underline on hover, picture flush at 4:3, body padded 16px (`--kit-space-4`, or the Theme Options → Cards value), date first. The Card block style (Group → Card, theme.json `kits-card`) uses the same values: background preset, border preset, card radius, and the same padding (the Cards value, else preset 40, 16px).

Campaign cards (`aw-story--card`) and Directory rows (`aw-org`) are separate components: Directory rows are capped at 720px (by their list container); campaign cards follow the card grid.

To check: load a list page in an iframe at 320, 375, 480, 600, 768, 900, 1024, 1200 and 1440px wide and read each card's width, picture size and text-column width; none should be outside the limits above and no page should scroll sideways.
