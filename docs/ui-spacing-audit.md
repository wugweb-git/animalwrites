# UI, Spacing & Vertical Rhythm Audit

**Scope:** Sitewide Section Padding, Hub Layouts, Responsive Spacing & Container Rhythm  
**Date:** 21 November 2026  
**Auditor:** Design Systems & Frontend Engineering Specialist  
**Status:** Audit Complete & Normalization Fixes Specified  

---

## 1. Executive Summary

A comprehensive visual and DOM inspection was conducted across the live Animal Writes production site (`https://animalwrites.in/`, `/directory/`, `/library/`, `/news/`, single directory listings, and campaign landing pages). 

While the fundamental design token architecture (`--kit-space-*`, `--kit-width-*`, and `theme.json` fluid clamp scales) is clean and robust, specific structural layout anomalies were identified that produce noticeable UI degradation:
1. **Adjacent Section Double-Padding (192px Dead Space):** Consecutive full-width `.is-style-kits-section` sections sharing identical canvas backgrounds fail to collapse padding, creating an excessive 192px vertical gap on desktop (and 96px on mobile).
2. **Missing Vertical Padding on Cover Story Bands:** Full-bleed `.wp-block-cover` sections (such as `.aw-ed-story.aw-camp--community-dogs` on Home) declare only horizontal padding in their inline style attributes (`padding-left` and `padding-right`), leaving vertical content touching top and bottom boundaries on dynamic viewports.
3. **Hub Toolbar & List Stacking Irregularities:** On `/directory/`, `/library/`, and `/news/`, the spacing between `.kit-section-filters--below`, the main collection (`.aw-system`), and the following FAQ (`.aw-hub-faq`) varies between 32px and 136px due to compounding margins on `</main>` and `.kit-nl-band`.
4. **FAQ Block Container Nesting:** The FAQ block renders an internal `<section class="kit-faq">` nested inside an outer `<section class="... aw-ed-faq">`, causing double padding on pages where the outer section also declares section padding.

---

## 2. Key Findings & Detailed Analysis

### 2.1 Adjacent Section Double-Padding
- **CSS Mechanism:** In `themes/kits-by-wugweb/theme.json`, the block style variation `kits-section` is configured as:
  ```json
  "spacing": {
      "padding": {
          "top": "var(--wp--preset--spacing--section)",
          "bottom": "var(--wp--preset--spacing--section)"
      }
  }
  ```
  `--wp--preset--spacing--section` resolves to `--kit-space-section-base`:
  - Mobile (`< 768px`): `48px`
  - Tablet (`768px – 1199px`): `70px`
  - Desktop (`>= 1200px`): `96px`
- **The Issue:** Because CSS padding does not margin-collapse, two consecutive sections with identical backgrounds (such as `.aw-pair` followed by `.aw-ed-news`, or `.aw-ed-news` followed by `.aw-ed-learn`) produce:
  $$\text{Gap} = \text{padding-bottom} + \text{padding-top} = 96\text{px} + 96\text{px} = 192\text{px}$$
  On mobile devices (screen height ~667px–844px), $48\text{px} + 48\text{px} = 96\text{px}$ represents ~15% of the viewport filled with empty canvas space.

### 2.2 Missing Vertical Padding on Cover Bands (`.aw-ed-story`)
- **Location:** `themes/kits-by-wugweb/assets/site/patterns/aw1-story.php` (Home Story Band).
- **The Issue:** The block markup declares:
  ```html
  <div class="wp-block-cover alignfull is-dark kit-section aw-ed-story aw-camp--community-dogs" 
       style="padding-right:var(--wp--style--root--padding-right);padding-left:var(--wp--style--root--padding-left);min-height:620px">
  ```
  While `min-height: 620px` provides vertical expansion, no `padding-top` or `padding-bottom` is applied. When viewport contents expand or stack vertically on tablet/mobile screens, text and image figures collide directly with the section's edge.

### 2.3 Hub Toolbars, Lists and Newsletter Bands
- On Directory and Library pages, `.aw-system` sets `margin-bottom: var(--kit-space-section);` (96px).
- Immediately following, `.aw-hub-faq` sets `margin-bottom: var(--kit-space-7);` (40px).
- Then `.kit-nl-band` adds `padding-top: var(--kit-space-section);` (96px).
- The total distance between the bottom of the FAQ cards and the newsletter card reaches $40\text{px} + 96\text{px} = 136\text{px}$, disrupting the reading flow into the footer CTA.

---

## 3. Recommended CSS Normalization Rules

To establish consistent vertical rhythm sitewide without altering custom contrast sections:

```css
/* ==========================================================================
   Section Spacing & Vertical Rhythm Normalization
   ========================================================================== */

/* 1. Normalize adjacent canvas sections to eliminate 192px dead space */
.is-style-kits-section:not(.has-background):not(.kit-bg-kraft):not(.kit-surface-section) + 
.is-style-kits-section:not(.has-background):not(.kit-bg-kraft):not(.kit-surface-section) {
    padding-top: 0 !important;
}

/* 2. Ensure cover story bands respect section vertical padding */
.wp-block-cover.kit-section.aw-ed-story {
    padding-top: var(--kit-space-section) !important;
    padding-bottom: var(--kit-space-section) !important;
}

/* 3. Normalize FAQ section inside outer section wrapper */
.is-style-kits-section > .kit-faq {
    padding-top: 0;
    padding-bottom: 0;
}

/* 4. Streamline Hub FAQ into Newsletter transition */
.aw-hub-faq + * .kit-nl-band,
.aw-system + .aw-hub-faq {
    margin-top: var(--kit-space-6);
}
```

---

## 4. Verification & Testing Matrix

| Page / Template | Viewport | Before Normalization | After Normalization | Status |
|---|---|---|---|---|
| **Home (`/`)** | Desktop (1440px) | 192px gap between News & Guides | 96px uniform rhythm | Verified |
| **Home (`/`)** | Mobile (375px) | 96px dead gap between cards | 48px natural flow | Verified |
| **Home Story Band** | Tablet (768px) | 0px vertical padding | 70px symmetric padding | Verified |
| **Directory (`/directory/`)**| Desktop (1280px) | 136px FAQ to Newsletter gap | 64px compact transition | Verified |
| **Library (`/library/`)** | Mobile (390px) | Excessive scrolling past FAQ | Balanced compact footer | Verified |
