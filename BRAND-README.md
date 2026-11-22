# Wugweb Brand README

> Brand identity, visual OS, and usage standards for Wugweb — the Wugweb Kits platform and all tenant sites (including Animal Writes).

## Contents

- [Brand core](#brand-core)
- [Visual OS tokens](#visual-os-tokens)
- [Color palette](#color-palette)
- [Typography](#typography)
- [Component usage](#component-usage)
- [Do's and don'ts](#dos-and-donts)
- [Files in this repo](#files-in-this-repo)
- [Maintenance](#maintenance)

---

## Brand core

| Element | Standard |
|---------|----------|
| **Company** | Wugweb |
| **Contact** | admin@wugweb.com |
| **Wordmark** | `tenant/assets/site/wordmark-light.svg` / `wordmark-dark.svg` |
| **Mark** | `tenant/assets/site/site-icon.png` |
| **Primary accent** | `#c15a3a` (terracotta) |
| **Background** | Bone cream |
| **License** | MIT |

The Wugweb brand OS is defined as a system of **design tokens**, not fixed values. Tokens live in `tenant/assets/site/tokens/tokens.json` and are compiled into `tokens.compiled.json`. They map to CSS custom properties (`--kit-*`) and Gutenberg `theme.json` palettes.

---

## Visual OS tokens

Token categories:

| Category | Token prefix | Purpose |
|----------|--------------|---------|
| Color | `--kit-color-*` | Brand colors, accents, ink, backgrounds, lines |
| Typography | `--kit-font-*` | Display and body font families |
| Corner radius | `--kit-radius-*` | Control, card, media, pill radii |
| Layout boundaries | `--kit-width-*`, `--kit-space-*` | Content width, wide width, section base spacing |

### Four built-in tenant profiles

Defined in `plugins/kits-core/includes/brand-customizer.php` (Kits platform, master in `wugweb-git/kits`):

1. **Default** — Animal Writes / Neutral
2. **Tenant 1** — Modern Tech & SaaS
3. **Tenant 2** — Editorial & Publication
4. **Tenant 3** — Eco & Community Wellness

Each profile exports/imports as JSON for zero-downtime replication across tenant WordPress instances.

---

## Color palette

| Role | Token | Value | Usage |
|------|-------|-------|-------|
| Accent | `--kit-color-accent` | `#c15a3a` | Primary brand accent, CTAs, links |
| Accent ink | `--kit-color-accent-ink` | — | Text on accent backgrounds |
| Background | `--kit-color-bg` | — | Page backgrounds |
| Raised | `--kit-color-raised` | — | Elevated surfaces, cards |
| Ink | `--kit-color-ink` | — | Primary body text |
| Text | `--kit-color-text` | — | Secondary body text |
| Muted | `--kit-color-muted` | — | Captions, metadata |
| Line | `--kit-color-line` | — | Borders, dividers |

Animal Writes tenant override palette (`tenant/styles/animal-writes.json`):
- Terracotta accent `#c15a3a`
- Forest green
- Warm slate
- Bone cream background

---

## Typography

| Role | Token | Purpose |
|------|-------|---------|
| Display | `--kit-font-display` | Headings, hero text, campaign titles |
| Body | `--kit-font-body` | Body copy, UI text, forms |

Inter is the primary typeface (`themes/kits-by-wugweb/assets/fonts/inter/`).

---

## Component usage

| Component | Location | Notes |
|-----------|----------|-------|
| Wordmark (light) | `tenant/assets/site/wordmark-light.svg` | Use on dark backgrounds |
| Wordmark (dark) | `tenant/assets/site/wordmark-dark.svg` | Use on light backgrounds |
| Site icon / favicon | `tenant/assets/site/site-icon.png` | Favicon and app icon |
| Brand CSS | `tenant/assets/site/css/aw-site.css` | Tenant brand stylesheet |
| Pattern CSS | `tenant/assets/site/css/aw-patterns.css` | Campaign and component rules |
| Theme style variation | `tenant/styles/animal-writes.json` | Gutenberg `theme.json` style variation |

Do not modify files in `themes/kits-by-wugweb/` or `plugins/` directly — those are platform code consumed from `wugweb-git/kits`. All tenant brand overrides live in `tenant/`.

---

## Do's and don'ts

| Do | Don't |
|----|-------|
| Edit brand tokens in `tenant/assets/site/tokens/tokens.json` | Hard-code colors in tenant CSS |
| Use the 4 built-in tenant profiles via the Brand Customizer (`Appearance > Brand Tokens`) | Create ad-hoc brand variants |
| Export/Import brand JSON for tenant replication | Duplicate brand assets across repos |
| Reference platform assets via `wp_theme_json_data_theme` filter | Modify `themes/kits-by-wugweb/` directly |

---

## Files in this repo

```
tenant/
  assets/site/
    tokens/tokens.json          # Design token source of truth
    tokens/tokens.compiled.json # Compiled token output
    wordmark-light.svg          # Light wordmark
    wordmark-dark.svg           # Dark wordmark
    site-icon.png               # Favicon / app icon
    css/aw-site.css             # Tenant brand stylesheet
    css/aw-patterns.css         # Campaign and component rules
  styles/
    animal-writes.json          # theme.json style variation (brand palette)
tools/package-ingest.sh         # Pulls Kits platform from wugweb-git/kits
composer.json                   # Declares Kits packages as dependencies
```

---

## Maintenance

- Token changes: edit `tenant/assets/site/tokens/tokens.json`, then run `python3 tools/build-tokens.py` to regenerate `tokens.compiled.json` and `theme.json` files.
- Brand CSS changes: edit `tenant/assets/site/css/aw-site.css` and `aw-patterns.css`; run CI contrast and css_min checks before deploying.
- Platform updates: run `tools/package-ingest.sh --pull` to fetch the latest Kits platform from `wugweb-git/kits@kits-suite`.
