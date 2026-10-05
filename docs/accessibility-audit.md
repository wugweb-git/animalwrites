# Accessibility audit: Animal Writes 2.0

**Standard:** WCAG 2.1 AA, plus axe best-practice rules. **Date:** 5 October 2026. **Site:** temporary domain (same code as launch).
**Method:** axe-core 4.10 run in the browser on every page type at 1280px and 375px, plus scripted checks for horizontal overflow and target size, plus manual keyboard, focus and form checks.

## Pages covered (each at 1280px and 375px)
Home · Directory · Directory state page (Maharashtra, empty state) · Listing (Barkville Foundation, with claim form) · Legal Watch · Legal item · News · News item · Knowledge Centre · Guide · Research Library · Research item (PDF viewer) · Opportunities · Opportunity · Campaigns · Campaign (Animal Writes 1.0, receipt upload) · Resources · Resource · Helplines · Get involved (volunteer form) · About · Search results · 404.

## Result
**Before:** 7 distinct issues across all pages, 3 of them serious. **After:** 0 axe violations on all 23 page types at both widths. No horizontal overflow.

| # | Issue | WCAG | Impact | Where | Fix |
|---|---|---|---|---|---|
| 1 | Card image links had no accessible name (duplicate of the title link) | 2.4.4, 4.1.2 | Serious | Every card grid (up to 12 per page) | Image link is `tabindex="-1"` and `aria-hidden="true"`; the title link names the item (`includes/media.php`) |
| 2 | Section count text 4.3:1 on kraft | 1.4.3 | Serious | Every archive header | Count uses ink (13.2:1) (`aw.css`) |
| 3 | Share "Copy link" button broken: WordPress turned the quotes in its inline script into curly quotes | 4.1.2 (and a functional bug) | Serious | Every single page | Moved to `aw.js`; button has no inline script (`includes/share.php`) |
| 4 | Two banner and two contentinfo landmarks (template part and inner group were both `<header>`/`<footer>`) | 1.3.1 | Moderate | Every page | Inner groups are `<div>`; the template parts are the landmarks (`parts/header.html`, `parts/footer.html`) |
| 5 | Share bar outside any landmark | 1.3.1 | Moderate | Every single page | Moved inside `<main>`, after the content |
| 6 | Heading jumped h1 → h3 in empty states | 1.3.1 | Moderate | Empty archives and state pages | Empty-state heading rendered as h2 (`includes/display.php`) |
| 7 | Heading jumped h1 → h3 in search results | 1.3.1 | Moderate | Search | Result titles rendered as h2 on search |

Also tightened (not failures): checkboxes enlarged from 20px to 24px (their labels already give a 44px target); form summary no longer repeats "(optional)" in field names.

## Manual checks
| Check | Result |
|---|---|
| Skip link | First Tab shows "Skip to content", which moves focus to `<main>` |
| Focus visible | 3px marigold outline or 4px ring on links, buttons, inputs, summaries, carousel dots; never removed |
| Keyboard | Nav, finder, cards, carousels (dots are buttons with `aria-current`), claim disclosure, forms and upload buttons all reachable and operable |
| Forms | Every control has a label; hints and errors linked with `aria-describedby`; invalid fields get `aria-invalid`; on submit an error summary (`role="alert"`) takes focus and links to each field; the same errors come back from the server without JavaScript |
| Status messages | Form success uses `role="status"`; upload list and field errors are `aria-live="polite"` |
| Images | Photos have descriptive alt text; placards and decorative tiles are `aria-hidden`; logos have the organisation's name |
| Language | `lang="en-IN"`; the Hindi hero line has `lang="hi"` |
| Motion | Only the homepage hero animates, once per session; `prefers-reduced-motion` disables all animation and transitions |
| Targets | Buttons and controls at least 44px (`--aw-size-target`), inputs 48px (`--aw-size-control`) |
| Zoom / reflow | No horizontal scroll at 375px (equivalent to 1280px at about 340% zoom) |

## Not covered by this audit
- Screen reader testing with NVDA, JAWS or VoiceOver by a person who uses them daily. Automated checks find roughly a third to half of real-world issues; a short session with a screen-reader user before launch is recommended.
- Content written in future (ingested headlines, new pages): alt text and heading order depend on the editor.
- Third-party embeds (ads, Google Tag Manager) once enabled on the live domain.

## Re-running
Open any page in a browser, load axe-core from cdnjs and run `axe.run()` with tags `wcag2a, wcag2aa, wcag21a, wcag21aa, best-practice`, at 1280px and 375px.
