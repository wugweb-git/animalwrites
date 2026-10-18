# Buttons

One button system for the Kits master theme. `.kit-btn` (theme and plugin markup) and the core Button block render the same. All rules are in `themes/kits-by-wugweb/assets/css/kit-primitives.css` section 10. The values are `--kit-btn-*` tokens (`tokens/tokens.json`). The registry entry is "Button" in `kits-registry.json`, and every state is drawn on the states sheet (/kits-demo/, section 10: both systems, light and dark, every variant, state, content type and size, and Brand in each campaign palette).

## Variants

| Variant | Use it for | `.kit-btn` | Button block style | Light surface | Dark surface |
|---|---|---|---|---|---|
| Primary | the main action, one per area | `kit-btn` | Default (shown as Primary) | ink fill, white text | brand fill, ink text (ink would vanish) |
| Secondary | other actions next to a primary | `kit-btn--secondary` | Secondary | soft grey fill, light outline, ink text | soft white tint, light outline, white text |
| Ghost | an outlined action that needs more weight than Secondary | `kit-btn--ghost` | Ghost | dark (ink) outline, ink text | white outline, white text |
| Brand | brand moments: the primary shape in the brand colour | `kit-btn--brand` | Brand | brand fill (yellow, ink text), or the campaign's colour | the same |

Brand reads one pair of tokens, `--kit-btn-brand-bg` and `--kit-btn-brand-fg`. By default that is the site's brand (yellow with ink text). A campaign palette sets the pair to the campaign's own colour (`--camp-primary` / `--camp-primary-ink` in `.aw-camp--lab-rules` and `.aw-camp--community-dogs`), so Brand buttons on a campaign page are in the campaign colour. Hover mixes the brand with ink (`--kit-btn-hover-mix`), so every state follows the same pair. A new campaign palette adds the same line; the contrast check tests each palette's pair.

Dark surfaces are `.kit-dark`, `.has-secondary-background-color`, the site header and footer, `.kit-ed-hero`, `.kit-ed-help`, `.kit-ed-story` and `.kit-form--on-dark`. Inside them the `--kit-btn-*` tokens switch, so every variant works without extra classes. Primary takes the brand pair there, because ink would vanish.

Old names still render: `is-style-kits-primary` renders as Brand (it was yellow), `is-style-kits-small` as small Primary, and core `is-style-outline` as Secondary. `kit-btn--primary` and `kit-btn--ink` render as Primary, and `kit-btn--outline` as Secondary. The editor no longer offers the old names.

## States

| State | What changes | Motion |
|---|---|---|
| Default | variant colours | none |
| Hover (pointer devices only) | primary lightens; secondary fills with ink (white on dark); brand darkens towards ink; ghost fills with ink (white on dark) | colours fade over `--kit-motion-fast`; the icon nudges with a spring (`--kit-duration-spring`) |
| Focus-visible | `--kit-focus-width` ring in `--kit-color-focus`; colours unchanged | the icon nudges, as on hover |
| Active (pressed) | moves down `--kit-btn-press` (1px) | instant |
| Disabled | `disabled` on buttons, `aria-disabled="true"` on links (they stop navigating); `--opacity-disabled`; no hover, no press | none |
| Loading | class `is-loading` or `aria-busy="true"`: a spinner replaces the icon, the label stays, clicks are ignored | spinner turns every 700ms |

With `prefers-reduced-motion: reduce` there are no transitions or movement. The spinner stops and shows at 60% opacity.

## Content

| Content | How | Notes |
|---|---|---|
| Icon + label | a function icon before the label is automatic for `tel:`, `mailto:`, WhatsApp, submit and downloads; choose one with `kit-btn--ico-<name>` or a `.kit-ico` span. A link with no function icon gets an arrow after its label: → inside the site, ↗ when it leaves the site. | the arrow moves 3px, a leading icon 2px and slightly larger |
| Label only | `kit-btn--label` (Content: Label only) | a button with no icon: no arrow and no function icon. An explicit choice; icon + label stays the default. |
| Icon only | `kit-btn--icon`: a square, as wide as it is tall | `<button>` needs `aria-label`. On the Button block, the button text is the accessible name; it stays for screen readers and is hidden on screen. |

Icon names: phone, mail, whatsapp, search, send, download, check, alert, person, calendar, file, link, map-pin, megaphone, arrow-right, next, prev.

## Sizes

| Size | Class | Height | Padding | Type |
|---|---|---|---|---|
| Default | — | `--kit-btn-height` (48px) | `--kit-btn-pad` | `--fluid-body-md` |
| Small | `kit-btn--sm` | `--kit-btn-height-sm` (44px, the minimum target) | `--kit-btn-pad-sm` | `--fluid-body-sm` |

## On the Button block

The **Styles** panel picks the variant: Primary (the default), Secondary, Ghost or Brand. The **Size and content** panel in the block sidebar sets Size (Default, Small), Content (icon and label, label only, icon only) and Icon (automatic or a named icon). Those write the classes `kit-btn--sm`, `kit-btn--label` / `kit-btn--icon` and `kit-btn--ico-<name>`, which can also be typed in **Advanced → Additional CSS class(es)**. In the editor the button shows the arrow it will have, and an icon-only button shows its text while you edit it. They combine, so `kit-btn--sm kit-btn--icon kit-btn--ico-search` with the Secondary style gives a small outlined search button.

## Rules

- Every button here is a link, except form buttons (Send, Subscribe, Search), which submit their form. Both use the same classes, variants, states and content options.

- A link styled as a button is never a heading, and a heading is never styled as a button.
- The underlined text link (`.kit-ed-more`) is a link, not a button. Use it only for "see all" links in a section head (home: What's happening, Learn, Take action; Kits pattern lead-list). Calls to action are buttons.
- A component restyles a button by setting the `--_bg`, `--_fg`, `--_line` (and `-h` hover) values or plain properties in one class rule; never copy the button rules.
- The site's own host is added by `functions.php`, so absolute links to this site show →, not ↗.
