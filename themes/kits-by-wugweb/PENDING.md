# Kits by Wugweb Theme: Pending & Roadmap

## Active Scope & Responsibilities
The declarative FSE block theme owns:
- `theme.json` semantic tokens, typography scales, layout definitions, and color palette.
- Templates (`templates/`) and template parts (`parts/`).
- Native block patterns (`patterns/`).
- Style variations (`styles/`).
- Day/Night anti-FOUC runtime and CSS variables.

---

## Open Tasks & Enhancements

1. **Day/Night Anti-FOUC Implementation**:
   - Semantic color tokens in `theme.json` with `--kit-color-*` variables.
   - High-priority early `wp_head` inline script to read `localStorage` / `prefers-color-scheme` before DOM rendering to eliminate theme flash.
   - Header toggle component using WordPress Interactivity API (or lightweight zero-dependency vanilla JS).
   - Dedicated `styles/dark.json` variation for tenants wanting a permanently dark default.

2. **Spacing & Layout Normalization (Audit Follow-up)**:
   - Enforce section vertical padding strictly via theme tokens (`--kit-space-section-base`).
   - Eliminate hardcoded fixed heights in patterns, vertical percentage padding, and inline `!important` declarations.
   - Ensure footer height dynamically follows content without max-height or overflow hidden.
   - Strictly limit responsive media queries to standard breakpoints: 767px / 768px and 1199px / 1200px.

3. **Pattern Library Expansion**:
   - Hero patterns (minimal editorial, split card, full-width bold).
   - Call-to-action (CTA) patterns with accessible contrast ratios (WCAG AAA 4.5:1 text, 3:1 non-text).
   - Feature grids and cards with dynamic flex-wrap.
   - Breadcrumbs placement pattern consuming RankWeb's native breadcrumbs block.

4. **Tenant Asset Decoupling**:
   - Relocate tenant-specific site files (`assets/site/`) and site style variations (`styles/animal-writes.json`) out of the theme core and into the tenant options loader.
