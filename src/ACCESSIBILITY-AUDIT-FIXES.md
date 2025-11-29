# Accessibility Audit - Critical Issues & Fixes
**Date:** November 29, 2025  
**Audit Source:** Lighthouse Accessibility Audit

---

## 🔴 CRITICAL ISSUES IDENTIFIED

### 1. **Elements use prohibited ARIA attributes** ❌
**Status:** Reviewing  
**Severity:** HIGH

**Problem:** Invalid ARIA attributes found in the codebase

**Checked:**
- All `aria-` attributes scanned (115 instances found)
- Common violations: `aria-expanded` on non-interactive elements, invalid `aria-valuemin`/`aria-valuemax` usage

**Analysis:**
After reviewing all 115 ARIA attribute usages, found potential issues:

✅ **Valid ARIA Usage (Most of codebase):**
- `aria-labelledby` - Correctly used on sections
- `aria-label` - Properly used on buttons, links, icons
- `aria-hidden="true"` - Correctly used on decorative icons
- `aria-expanded` - Used correctly on accordion/menu buttons
- `aria-controls` - Used correctly on menu buttons
- `aria-live="polite"` - Used correctly for dynamic content

⚠️ **Potential Issues Found:**

1. **ScrollProgress.tsx** - Using `aria-valuemin` and `aria-valuemax` on a div
   - Issue: These attributes should only be used on elements with `role="progressbar"` or similar
   - Already has `role="progressbar"` ✅ - This is VALID

2. **Motion.div with aria-label** - Some motion divs have aria-label without role
   - Need to verify if these need `role="img"` or should use aria-hidden instead

**Action Required:**
- Review any `motion.div` or `div` with `aria-label` without a `role` attribute
- Check for any ARIA attributes on inappropriate elements

---

### 2. **Background and foreground colors do not have sufficient contrast ratio** ❌
**Status:** CRITICAL FIX NEEDED  
**Severity:** HIGH

**Problem:** Colors don't meet WCAG AA standards (4.5:1 for normal text, 3:1 for large text)

**Current Color Scheme:**
- Primary: `#F59E0B` (amber/yellow)
- Secondary: `#D97706` (darker amber)
- Text: `#333333` (dark gray)
- Light text: `#666666` (medium gray)
- Background: `#FFFFFF` (white)
- Light background: `#FFF8F3` (cream)

**Contrast Ratios:**

| Foreground | Background | Ratio | WCAG AA (Normal) | WCAG AA (Large) | Status |
|-----------|-----------|-------|------------------|-----------------|--------|
| `#F59E0B` | `#FFFFFF` | 2.15:1 | 4.5:1 ❌ | 3:1 ❌ | **FAIL** |
| `#D97706` | `#FFFFFF` | 3.23:1 | 4.5:1 ❌ | 3:1 ✅ | **Large Text Only** |
| `#333333` | `#FFFFFF` | 12.63:1 | 4.5:1 ✅ | 3:1 ✅ | **PASS** |
| `#666666` | `#FFFFFF` | 5.74:1 | 4.5:1 ✅ | 3:1 ✅ | **PASS** |
| `#F59E0B` | `#FFF8F3` | 2.08:1 | 4.5:1 ❌ | 3:1 ❌ | **FAIL** |
| `#333333` | `#FFF8F3` | 12.23:1 | 4.5:1 ✅ | 3:1 ✅ | **PASS** |

**🔴 CRITICAL FINDINGS:**

1. **Yellow text on white (`#F59E0B` on `#FFFFFF`)** - FAILS both normal (4.5:1) and large text (3:1) requirements
   - Current: 2.15:1
   - Used extensively in badges, tags, and accent text

2. **Secondary yellow on white (`#D97706` on `#FFFFFF`)** - FAILS normal text, barely passes large text
   - Current: 3.23:1
   - Only acceptable for text ≥18pt (24px) or bold ≥14pt (18.5px)

**Problematic Usage Patterns:**

1. **Badge/Tag text:** `text-[#F59E0B]` on white backgrounds
   ```tsx
   <span className="text-[#F59E0B]">29 November 2025</span>
   ```

2. **Heading accents:** Yellow headings on white
   ```tsx
   <h2 className="text-[#F59E0B] text-2xl">Our Growing Movement</h2>
   ```

3. **Icon colors:** Yellow icons on white/cream backgrounds
   ```tsx
   <Mail className="w-5 h-5 text-[#F59E0B]" />
   ```

**SOLUTIONS:**

### Option 1: Use Darker Yellow (Recommended for Large Text)
- Replace `#F59E0B` with `#B7540F` (4.55:1 - PASSES AA for normal text)
- Replace `#D97706` with `#9F5706` (5.24:1 - PASSES AA for normal text)

### Option 2: Use Yellow on Dark Backgrounds (Best for Accessibility)
- Keep `#F59E0B` but ONLY on dark backgrounds (#333333, #000000)
- `#F59E0B` on `#333333` = 5.87:1 ✅ PASSES

### Option 3: Use Yellow for Decorative Elements Only
- Keep yellow for backgrounds, borders, large UI elements
- Switch text to `#333333` (black) for all readable content
- This maintains brand colors while ensuring accessibility

### Option 4: Increase Yellow Text Size (Partial Fix)
- For `#D97706` (3.23:1), ensure all usage is:
  - ≥24px font size, OR
  - ≥18.5px font size with bold weight
- Still doesn't fix `#F59E0B` which fails even for large text

**RECOMMENDED APPROACH:**

1. ✅ **Keep yellow for decorative elements:**
   - Backgrounds: `bg-[#F59E0B]` ✅
   - Borders: `border-[#F59E0B]` ✅
   - Icons on dark backgrounds: `text-[#F59E0B]` ✅
   - Large accent elements ✅

2. ❌ **Replace yellow text on light backgrounds:**
   - Headings: Change to `text-[#333333]` or use darker yellow `#B7540F`
   - Body text: Change to `text-[#333333]` or `text-[#666666]`
   - Badge text: Change to `text-[#333333]` or use yellow background with black text

3. ✅ **Use yellow backgrounds with dark text:**
   - `bg-[#F59E0B] text-[#333333]` ✅ (12.63:1)
   - This maintains brand while ensuring readability

**FILES TO UPDATE:**

Priority 1 (Text Contrast):
- [ ] `/components/Hero.tsx` - Badge text, accent text
- [ ] `/components/ImpactStats.tsx` - Heading color
- [ ] `/components/Timeline.tsx` - Badge text
- [ ] `/components/LocalSupport.tsx` - Badge text, icon colors
- [ ] `/components/Resources.tsx` - Badge text
- [ ] `/components/Testimonials.tsx` - Heading color
- [ ] `/components/KeyFacts.tsx` - Badge text
- [ ] `/components/CountdownTimer.tsx` - Icon colors (on dark bg - OK)
- [ ] `/components/SocialShare.tsx` - Icon colors

Priority 2 (Review):
- [ ] All components with `text-[#F59E0B]` on light backgrounds
- [ ] All components with `text-[#D97706]` on light backgrounds

---

### 3. **Heading elements are not in sequentially-descending order** ❌
**Status:** FIX NEEDED  
**Severity:** MEDIUM

**Problem:** Heading hierarchy skips levels (e.g., h1 → h3, skipping h2)

**WCAG Requirement:**
- h1 (page title) → h2 (main sections) → h3 (subsections) → h4, h5, h6
- Should not skip levels going down (h1 → h3 is invalid)
- CAN skip levels going up (h3 → h2 is ok)

**Current Heading Hierarchy:**

```
Hero (/)
├─ h1: "Animal Writes" ✅
├─ AboutMovement
│  └─ h2: "A National Movement of Compassion" ✅
├─ WhyThisMatters  
│  ├─ h2: "Why This Matters" ✅
│  ├─ h3: Reason titles ✅
│  └─ h3: "Why Letters to the Supreme Court Work?" ✅
├─ ImpactStats
│  └─ h2: "Our Growing Movement" ✅
├─ Timeline
│  ├─ h2: "Campaign Timeline" ✅
│  └─ h3: Milestone titles ✅
├─ HowToJoin
│  ├─ h2: "How You Can Join" ✅
│  └─ h3: Step titles ✅
├─ LetterTemplates
│  └─ h2: "Letter Templates & Guidance" ✅
├─ ReceiptUpload
│  ├─ h2: "Share Your Impact" ✅
│  └─ h3: "Why share your receipt?" ✅
├─ LocalSupport
│  ├─ h2: "Find Your Nearest Post Office" ✅
│  ├─ h3: "Post Offices (N)" ✅
│  └─ h3: "Don't See Your City?" ✅
├─ FAQ
│  └─ h2: "Frequently Asked Questions" ✅
├─ VolunteerSignup
│  ├─ h2: "Volunteer With Us" ✅
│  └─ h3: "Thank You!" ✅
├─ KeyFacts (Hidden)
│  ├─ h2: "The Facts Speak for Themselves" ✅
│  ├─ h3: Fact titles ✅
│  └─ h3: "Why ABC Programs Work" ✅
├─ Resources (Hidden)
│  ├─ h2: "Campaign Resources" ✅
│  ├─ h3: Resource titles ✅
│  └─ h3: "Useful Legal & Reference Links" ✅
├─ WhatHappensNext
│  ├─ h2: "What Happens After November 29th?" ✅
│  └─ h3: Step titles ✅
├─ SocialShare
│  ├─ h2: "Spread the Word" ✅
│  └─ h2: "Join the Movement" ⚠️ (Should be h3)
├─ Supporters
│  └─ h2: "Supported By" ✅
├─ CampaignGallery
│  └─ h2: "Moments From Previous Campaigns" ✅
└─ Testimonials
   └─ h2: "Voices From Across India" ✅

CountdownTimer (When visible on campaign day)
├─ h2: "The Day Has Arrived" ✅
└─ h3: Step titles (3x) ✅

Admin Pages
├─ VolunteerAdmin
│  ├─ h1: "Volunteer Signups" ⚠️ (Should be h2 - not main page title)
│  └─ h3: "How to Access This Page" ⚠️ (Should be h2 or h3)
└─ ReceiptAdmin
   └─ h1: "Receipt Admin Dashboard" ⚠️ (Should be h2)

UI Components
└─ Card
   └─ h4: CardTitle ⚠️ (Context-dependent)
```

**⚠️ ISSUES FOUND:**

1. **SocialShare.tsx - Duplicate h2**
   - Line 180: Second `<h2>Join the Movement</h2>` inside the same section
   - Should be `<h3>` since it's within a section that already has an h2

2. **Admin Pages - Incorrect h1 usage**
   - `VolunteerAdmin.tsx` line 97: `<h1>Volunteer Signups</h1>`
   - `ReceiptAdmin.tsx` line 113: `<h1>Receipt Admin Dashboard</h1>`
   - Issue: These are not the main page title (Hero has h1)
   - Should be `<h2>` since they're sections of the application

3. **Card Component - h4 without context**
   - `ui/card.tsx` line 33: `<h4>` used for CardTitle
   - Risk: Could create h4 without h3 parent in some contexts
   - Need to audit all CardTitle usages

**FIXES REQUIRED:**

1. Change SocialShare second heading from h2 to h3
2. Change admin page titles from h1 to h2
3. Audit CardTitle usage to ensure proper hierarchy

---

## 📋 IMPLEMENTATION PLAN

### Phase 1: Fix Contrast Issues (CRITICAL) ⚠️

**Goal:** Ensure all text meets WCAG AA contrast requirements

**Steps:**

1. Create color utility for accessible yellow
   ```css
   /* Add to globals.css */
   --color-amber-accessible: #B7540F; /* 4.55:1 contrast */
   --color-amber-accessible-dark: #9F5706; /* 5.24:1 contrast */
   ```

2. Update components with yellow text on light backgrounds:
   - Replace `text-[#F59E0B]` with `text-[#333333]` OR
   - Use yellow backgrounds: `bg-[#F59E0B] text-[#333333]` OR
   - Use accessible yellow: `text-[#B7540F]`

3. Keep yellow for:
   - Backgrounds (buttons, badges, sections)
   - Borders and decorative elements
   - Icons on dark backgrounds (#333333)
   - Large heading text if ≥24px

**Impact:** Fixes critical accessibility barrier, ensures compliance

---

### Phase 2: Fix Heading Hierarchy (MEDIUM) ⚠️

**Goal:** Ensure proper h1-h6 sequential order

**Steps:**

1. Update `SocialShare.tsx` line 180:
   ```tsx
   // Change from:
   <h2 className="text-white text-2xl md:text-4xl font-bold">
   // To:
   <h3 className="text-white text-2xl md:text-4xl font-bold">
   ```

2. Update `VolunteerAdmin.tsx` line 97:
   ```tsx
   // Change from:
   <h1 className="text-2xl md:text-4xl text-[#333333]">
   // To:
   <h2 className="text-2xl md:text-4xl text-[#333333]">
   ```

3. Update `ReceiptAdmin.tsx` line 113:
   ```tsx
   // Change from:
   <h1 className="text-3xl md:text-4xl text-[#333333]">
   // To:
   <h2 className="text-3xl md:text-4xl text-[#333333]">
   ```

4. Audit CardTitle usages in:
   - LetterTemplates.tsx
   - Resources.tsx
   - Any other Card components

**Impact:** Improves screen reader navigation, meets WCAG standards

---

### Phase 3: Review ARIA Attributes (LOW) ✓

**Goal:** Verify no prohibited ARIA usage

**Steps:**

1. Run automated ARIA validator
2. Review any motion.div with aria-label
3. Test with screen reader (NVDA/JAWS/VoiceOver)

**Current Status:** Most ARIA usage appears correct based on manual review

---

## ✅ TESTING CHECKLIST

After implementing fixes:

### Automated Testing:
- [ ] Run Lighthouse accessibility audit (target: 100 score)
- [ ] Run axe DevTools accessibility scan
- [ ] Check WAVE accessibility report
- [ ] Validate with HTML validator

### Manual Testing:
- [ ] Test with NVDA screen reader (Windows)
- [ ] Test with JAWS screen reader (Windows)
- [ ] Test with VoiceOver (Mac/iOS)
- [ ] Test keyboard navigation (Tab, Enter, Space, Arrow keys)
- [ ] Test with high contrast mode
- [ ] Test with 200% zoom
- [ ] Test color contrast with real users

### Contrast Verification:
- [ ] Use WebAIM Contrast Checker on all yellow text
- [ ] Verify all text meets 4.5:1 ratio (normal text)
- [ ] Verify all text meets 3:1 ratio (large text ≥24px)
- [ ] Check with color blindness simulator

### Heading Hierarchy:
- [ ] Validate with HeadingsMap extension
- [ ] Test screen reader announcement order
- [ ] Verify document outline is logical

---

## 📊 EXPECTED IMPROVEMENTS

### Before vs. After:

| Metric | Before | After | Target |
|--------|--------|-------|--------|
| **Lighthouse A11y Score** | ~85-90 | 98-100 | 100 |
| **Contrast Issues** | 15-20 | 0 | 0 |
| **Heading Issues** | 3-4 | 0 | 0 |
| **ARIA Issues** | 0-2 | 0 | 0 |

---

## 🎯 PRIORITY SUMMARY

1. **🔴 CRITICAL - Fix Contrast Issues**
   - Impact: High - Affects readability for all users
   - Effort: Medium - Need to update ~15-20 components
   - Timeline: Immediate

2. **🟡 MEDIUM - Fix Heading Hierarchy**
   - Impact: Medium - Affects screen reader users
   - Effort: Low - 3-4 simple changes
   - Timeline: Today

3. **🟢 LOW - Review ARIA**
   - Impact: Low - Appears mostly correct
   - Effort: Low - Verification only
   - Timeline: Post-launch

---

## 📝 NOTES

- The contrast issue is the most critical for WCAG compliance
- Consider using the Campaign's black/gray color scheme more prominently
- Yellow should remain a brand accent, not primary text color
- Test with real users who have visual impairments
- Consider adding a high-contrast mode toggle for users

---

**Next Action:** Begin implementing contrast fixes immediately. The heading hierarchy fixes can be done in parallel as they're quick changes.
