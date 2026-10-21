# Card sizes (21 Oct 2026)

Limits every list card keeps, set in `themes/animal-writes/assets/css/aw.css` (custom properties on `:root`):

| Limit | Value | Property |
|---|---|---|
| Card, minimum width | 18rem (288px, fits a 320px screen) | `--kit-card-min` |
| Card width | none: a card fills its grid cell; the grid (columns, gap) sets the width | n/a |
| Stacked picture | 4.5rem to 15rem (72 to 240px); wide stacked cards 30% of the card inside those limits | `--kit-thumb-min`, `--kit-thumb-max` |
| Block card picture (picture above text), maximum height | 22.5rem (360px) | `--kit-card-media-max` |
| Text column in a stacked card, minimum width | 10rem (160px) | `--kit-body-min` |

Card states: `kit-card--block-lg|md|sm` (picture above the text) and `kit-card--stacked-lg|md|sm` (picture left). The wide stacked card has 12px padding and a 30:70 picture-to-text split; its gap runs from 12px on the narrowest screens to 24px (`clamp`, no extra breakpoint: the CI check allows only 767/768 and 1199/1200). Three-across rows (`.is-three`: Home News and Resources sections) become two across when a third card would be under 18rem.

Campaign cards (`aw-story--card`) and Directory rows (`aw-org`) are separate components: Directory rows are capped at 720px (by their list container); campaign cards follow the card grid.

To check: load a list page in an iframe at 320, 375, 480, 600, 768, 900, 1024, 1200 and 1440px wide and read each card's width, picture size and text-column width; none should be outside the limits above and no page should scroll sideways.
