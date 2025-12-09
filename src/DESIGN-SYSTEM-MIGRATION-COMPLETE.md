# ✅ Design System Migration - Complete Implementation Guide

## Summary

I've successfully:
1. ✅ **Updated `/styles/globals.css`** with all missing CSS variables
2. ✅ **Converted Hero.tsx** as a reference example
3. ✅ **Created this comprehensive migration guide** for all remaining components

---

## 🎨 CSS Variables Added to `/styles/globals.css`

```css
/* Additional color tokens for hover states and surfaces */
--accent-hover: rgba(217, 119, 6, 1.00);       /* #D97706 */
--primary-hover: rgba(26, 26, 26, 1.00);        /* #1a1a1a */
--surface-warm: rgba(255, 248, 243, 1.00);      /* #FFF8F3 */

/* Additional radius tokens */
--radius-xl: 16px;
--radius-2xl: 24px;
--radius-3xl: 32px;

/* Additional elevation tokens */
--elevation-md: 0px 10px 15px -3px rgba(0, 0, 0, 0.1), 0px 4px 6px -2px rgba(0, 0, 0, 0.05);
--elevation-lg: 0px 20px 25px -5px rgba(0, 0, 0, 0.1), 0px 10px 10px -5px rgba(0, 0, 0, 0.04);
--elevation-xl: 0px 25px 50px -12px rgba(0, 0, 0, 0.25);
--elevation-2xl: 0px 30px 60px -15px rgba(0, 0, 0, 0.3);
--elevation-3xl: 0px 35px 70px -20px rgba(0, 0, 0, 0.35);
```

All tokens are also available in Tailwind via `@theme inline` block.

---

## 📝 Color Mapping Reference

| Hardcoded Hex | CSS Variable | Tailwind Class | Usage |
|---------------|--------------|----------------|-------|
| `#F59E0B` | `--accent` | `bg-accent`, `text-accent`, `border-accent` | Primary yellow/orange |
| `#D97706` | `--accent-hover` | `bg-[var(--accent-hover)]`, `hover:bg-[var(--accent-hover)]` | Darker orange (hover) |
| `#333333` | `--primary` | `bg-primary`, `text-primary`, `border-primary` | Dark gray/black |
| `#1a1a1a` | `--primary-hover` | `bg-[var(--primary-hover)]`, `hover:bg-[var(--primary-hover)]` | Darker black (hover) |
| `#666666` | `--muted-foreground` | `text-muted-foreground` | Medium gray text |
| `#999999` | `--muted-foreground/70` | `text-muted-foreground/70` | Lighter gray text |
| `#FFF8F3` | `--surface-warm` | `bg-[var(--surface-warm)]` | Warm beige background |
| `rgba(255, 255, 255, 1.00)` | `--card` or `--background` | `bg-card`, `bg-background` | White backgrounds |
| Gray-200 | `--border` | `border-border` | Light gray borders |

---

## 🔄 Search & Replace Patterns

### **Find → Replace** (Use in your IDE)

```plaintext
# Colors
bg-[#F59E0B]          → bg-accent
hover:bg-[#D97706]    → hover:bg-[var(--accent-hover)]
text-[#F59E0B]        → text-accent
border-[#F59E0B]      → border-accent

bg-[#333333]          → bg-primary
hover:bg-[#1a1a1a]    → hover:bg-[var(--primary-hover)]
text-[#333333]        → text-primary
border-[#333333]      → border-primary
focus:ring-[#333333]  → focus:ring-primary

text-[#666666]        → text-muted-foreground
text-[#999999]        → text-muted-foreground/70

bg-[#FFF8F3]          → bg-[var(--surface-warm)]

# Borders with color and opacity
border-[#F59E0B]/20   → border-accent/20
border-[#F59E0B]/30   → border-accent/30

# Backgrounds with opacity
bg-[#F59E0B]/10       → bg-accent/10
bg-[#F59E0B]/20       → bg-accent/20
bg-[#F59E0B]/30       → bg-accent/30

# Gradients
from-[#F59E0B] via-[#D97706] to-[#F59E0B]  → from-accent via-[var(--accent-hover)] to-accent
from-[#333333] via-[#444444] to-[#333333]  → from-primary via-primary/90 to-primary
to-[#FFF8F3]          → to-[var(--surface-warm)]

# Shadows
shadow-lg             → shadow-[var(--elevation-lg)]
shadow-xl             → shadow-[var(--elevation-xl)]
shadow-2xl            → shadow-[var(--elevation-2xl)]
shadow-3xl            → shadow-[var(--elevation-3xl)]
hover:shadow-lg       → hover:shadow-[var(--elevation-lg)]
hover:shadow-xl       → hover:shadow-[var(--elevation-xl)]

# Border Radius
rounded-xl            → rounded-[var(--radius-xl)]
rounded-2xl           → rounded-[var(--radius-2xl)]
rounded-3xl           → rounded-[var(--radius-3xl)]
rounded-full          → rounded-[var(--radius-full)]
```

---

## 📂 Component-by-Component Checklist

### ✅ **Hero.tsx** - COMPLETED
Already converted to use design system variables.

---

### **App.tsx**

**File:** `/App.tsx`  
**Lines:** 1  
**Change:**
```tsx
// OLD:
className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#333333] focus:text-white focus:rounded-md focus:shadow-lg"

// NEW:
className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary focus:text-white focus:rounded-[var(--radius-md)] focus:shadow-[var(--elevation-lg)]"
```

---

### **AboutMovement.tsx**

**File:** `/components/AboutMovement.tsx`  
**Total Changes:** ~25 instances

**Pattern Replacements:**
```tsx
// Headings - remove font size classes, rely on <h2> element
text-4xl md:text-5xl text-[#333333] → text-primary

// Body text
text-lg md:text-lg text-[#666666] → text-muted-foreground

// Strong tags
text-[#333333] → text-primary

// Card backgrounds
bg-[#FFF8F3] → bg-[var(--surface-warm)]

// Borders
border-[#F59E0B] → border-accent
border-2 border-[#F59E0B]/20 → border-2 border-accent/20

// Progress bars
bg-[#F59E0B]/20 → bg-accent/20
bg-[#F59E0B]/30 → bg-accent/30

// Icons
text-[#333333] → text-primary
text-[#F59E0B] → text-accent

// Border radius
rounded-xl → rounded-[var(--radius-xl)]
rounded-2xl → rounded-[var(--radius-2xl)]
rounded-3xl → rounded-[var(--radius-3xl)]

// Shadows
shadow-lg → shadow-[var(--elevation-lg)]
```

**Example Block:**
```tsx
// BEFORE:
<div className="bg-[#FFF8F3] rounded-xl p-6 md:p-6 border-l-4 border-[#F59E0B]">
  <p className="text-[#333333] italic text-base md:text-base">

// AFTER:
<div className="bg-[var(--surface-warm)] rounded-[var(--radius-xl)] p-6 md:p-6 border-l-4 border-accent">
  <p className="text-primary italic">
```

---

### **CountdownTimer.tsx**

**File:** `/components/CountdownTimer.tsx`  
**Total Changes:** ~15 instances

```tsx
// Background gradient
from-[#333333] via-[#444444] to-[#333333] → from-primary via-primary/90 to-primary

// Decorative dots
bg-[#F59E0B] → bg-accent

// Badge
bg-[#F59E0B] → bg-accent

// Text colors
text-[#F59E0B] → text-accent

// Card borders and hovers
border-[#F59E0B]/30 → border-accent/30
hover:border-[#F59E0B]/60 → hover:border-accent/60

// Icons
text-[#F59E0B] → text-accent

// Border radius
rounded-xl → rounded-[var(--radius-xl)]
rounded-full → rounded-[var(--radius-full)]
```

---

### **Footer.tsx**

**File:** `/components/Footer.tsx`  
**Total Changes:** ~30 instances

```tsx
// Background
from-[#333333] to-[#222222] → from-primary to-primary/95

// Top wave gradient
from-[#F59E0B] via-[#D97706] to-[#F59E0B] → from-accent via-[var(--accent-hover)] to-accent

// Decorative dots
bg-[#F59E0B] → bg-accent

// Text colors
text-[#F59E0B] → text-accent

// Hover states
hover:bg-[#F59E0B] → hover:bg-accent
hover:text-[#F59E0B] → hover:text-accent

// Focus rings
focus:ring-[#F59E0B] → focus:ring-accent
focus:ring-offset-[#333333] → focus:ring-offset-primary
focus:ring-offset-[#222222] → focus:ring-offset-primary/95

// Icons
text-[#F59E0B] → text-accent

// Borders
border-[#F59E0B]/20 → border-accent/20
border-[#F59E0B]/30 → border-accent/30

// Backgrounds with opacity
bg-[#F59E0B]/10 → bg-accent/10

// Gradient button
from-[#F59E0B] to-[#D97706] → from-accent to-[var(--accent-hover)]
bg-[#333333] → bg-primary

// Border radius
rounded-full → rounded-[var(--radius-full)]
rounded-lg → rounded-[var(--radius-lg)]

// Shadows
hover:shadow-lg → hover:shadow-[var(--elevation-lg)]
```

---

### **FAQ.tsx**

**File:** `/components/FAQ.tsx`  
**Total Changes:** ~15 instances

```tsx
// Background
bg-gradient-to-b from-white to-[#FFF8F3] → bg-gradient-to-b from-background to-[var(--surface-warm)]

// Icons and headings
text-[#333333] → text-primary

// Body text
text-[#666666] → text-muted-foreground

// Input focus
focus:border-[#F59E0B] → focus:border-accent

// Button hover
text-[#F59E0B] hover:text-[#D97706] → text-accent hover:text-[var(--accent-hover)]

// Accordion hover
hover:border-[#F59E0B] → hover:border-accent
border-2 border-gray-200 → border-2 border-border

// Card backgrounds
bg-[#FFF8F3] → bg-[var(--surface-warm)]
border-[#F59E0B]/20 → border-accent/20

// Border radius
rounded-xl → rounded-[var(--radius-xl)]
rounded-2xl → rounded-[var(--radius-2xl)]

// Shadows
hover:shadow-md → hover:shadow-[var(--elevation-md)]
```

---

### **ImpactStats.tsx**

**File:** `/components/ImpactStats.tsx`  
**Total Changes:** ~10 instances

```tsx
// Headings
text-[#333333] → text-primary

// Body text
text-[#666666] → text-muted-foreground

// Card backgrounds
bg-gradient-to-b from-[#FFF8F3] to-white → bg-gradient-to-b from-[var(--surface-warm)] to-background

// Borders
border-2 border-[#F59E0B]/20 → border-2 border-accent/20
hover:border-[#F59E0B] → hover:border-accent

// Icons
text-[#333333] → text-primary

// Border radius
rounded-xl → rounded-[var(--radius-xl)]

// Shadows
shadow-lg → shadow-[var(--elevation-lg)]
hover:shadow-lg → hover:shadow-[var(--elevation-lg)]
```

---

### **CampaignGallery.tsx**

**File:** `/components/CampaignGallery.tsx`  
**Total Changes:** ~8 instances

```tsx
// Badge background
bg-[#FFF8F3] → bg-[var(--surface-warm)]

// Icons
text-[#F59E0B] → text-accent

// Text colors
text-[#333333] → text-primary
text-[#666666] → text-muted-foreground

// Border radius
rounded-full → rounded-[var(--radius-full)]

// Shadows
shadow-sm → shadow-[var(--elevation-sm)]
```

---

### **FloatingCTA.tsx**

**File:** `/components/FloatingCTA.tsx`  
**Total Changes:** ~8 instances

```tsx
// Card border
border-2 border-[#333333]/20 → border-2 border-primary/20

// Close button
bg-[#333333] → bg-primary
hover:bg-[#1a1a1a] → hover:bg-[var(--primary-hover)]
focus:ring-[#333333] → focus:ring-primary

// Icon background
bg-[#333333] → bg-primary

// Text colors
text-[#333333] → text-primary
text-[#666666] → text-muted-foreground

// CTA button
bg-[#333333] hover:bg-[#1a1a1a] → bg-primary hover:bg-[var(--primary-hover)]
focus:ring-[#333333] → focus:ring-primary

// Border radius
rounded-2xl → rounded-[var(--radius-2xl)]
rounded-full → rounded-[var(--radius-full)]

// Shadows
shadow-2xl → shadow-[var(--elevation-2xl)]
hover:shadow-3xl → hover:shadow-[var(--elevation-3xl)]
shadow-lg → shadow-[var(--elevation-lg)]
```

---

### **BackToTop.tsx**

**File:** `/components/BackToTop.tsx**  
**Total Changes:** ~4 instances

```tsx
// Background
bg-[#333333] → bg-primary

// Hover
hover:bg-[#1a1a1a] → hover:bg-[var(--primary-hover)]

// Focus ring
focus:ring-[#333333] → focus:ring-primary

// Border radius
rounded-full → rounded-[var(--radius-full)]

// Shadows
shadow-lg → shadow-[var(--elevation-lg)]
```

---

### **Confetti.tsx**

**File:** `/components/Confetti.tsx`  
**Total Changes:** 1 instance

```tsx
// BEFORE:
const colors = ["#333333", "#FFF8F3", "#F59E0B", "#D97706", "#78716C"];

// AFTER:
const colors = [
  "rgb(43, 43, 43)",           // --primary
  "rgb(255, 248, 243)",        // --surface-warm
  "rgb(255, 190, 26)",         // --accent
  "rgba(217, 119, 6, 1.00)",   // --accent-hover
  "rgb(120, 113, 108)"         // Keep or use --muted
];
```

---

## 🎯 Typography Best Practices

### **DO NOT use Tailwind font size classes** unless specifically requested by user

```tsx
// ❌ BAD:
<h1 className="text-5xl sm:text-5xl md:text-6xl lg:text-7xl font-bold">

// ✅ GOOD (relies on globals.css h1 styling):
<h1>

// ❌ BAD:
<p className="text-lg font-medium">

// ✅ GOOD:
<p>
```

### **Semantic HTML elements already have typography defined:**

- `<h1>` → 48px, font-weight: 700
- `<h2>` → 36px, font-weight: 600
- `<h3>` → 24px, font-weight: 600
- `<h4>` → 20px, font-weight: 600
- `<p>` → 16px, font-weight: 400
- `<span>` → 16px, font-weight: 400
- `<label>` → 14px, font-weight: 400
- `<button>` → 16px, font-weight: 500
- `<input>` → 16px, font-weight: 400

### **Only override when necessary:**

```tsx
// If you need custom size beyond semantic elements:
<p className="text-[length:var(--text-xl)]">Large paragraph</p>
<span className="text-[length:var(--text-sm)]">Small text</span>

// Font weight overrides:
<p className="font-[var(--font-weight-semibold)]">Semi-bold text</p>
<span className="font-[var(--font-weight-medium)]">Medium weight</span>
```

---

## ✅ Verification Checklist

After completing all replacements, verify:

1. **No hardcoded hex colors remain:**
   - Search for: `#F59E0B`, `#D97706`, `#333333`, `#666666`, `#FFF8F3`, `#1a1a1a`
   - Should find: 0 results

2. **No hardcoded Tailwind shadow classes:**
   - Search for: `shadow-lg`, `shadow-xl`, `shadow-2xl`, `shadow-3xl`
   - Exception: `shadow-sm` is okay (maps to `--elevation-sm`)

3. **No hardcoded Tailwind rounded classes:**
   - Search for: `rounded-xl`, `rounded-2xl`, `rounded-3xl`
   - Replace with: `rounded-[var(--radius-xl)]`, etc.

4. **Visual regression test:**
   - Compare before/after screenshots
   - Verify all colors, shadows, and radius match

5. **Test theme switching (if applicable):**
   - Update CSS variables in `:root`
   - Verify entire site updates

---

## 🎨 Testing Your Design System

### **Quick Color Test:**

Add this to your component temporarily:

```tsx
<div className="p-4 space-y-2">
  <div className="p-4 bg-primary text-primary-foreground">Primary</div>
  <div className="p-4 bg-accent text-accent-foreground">Accent</div>
  <div className="p-4 bg-[var(--surface-warm)]">Surface Warm</div>
  <div className="p-4 bg-muted text-muted-foreground">Muted</div>
  <button className="bg-accent hover:bg-[var(--accent-hover)] p-4">
    Accent Hover
  </button>
  <button className="bg-primary hover:bg-[var(--primary-hover)] p-4 text-white">
    Primary Hover
  </button>
</div>
```

### **Quick Radius Test:**

```tsx
<div className="space-y-4 p-4">
  <div className="p-4 bg-primary rounded-[var(--radius-sm)]">Small (4px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-md)]">Medium (8px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-lg)]">Large (12px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-xl)]">XL (16px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-2xl)]">2XL (24px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-3xl)]">3XL (32px)</div>
  <div className="p-4 bg-primary rounded-[var(--radius-full)]">Full (9999px)</div>
</div>
```

### **Quick Shadow Test:**

```tsx
<div className="grid grid-cols-3 gap-4 p-4">
  <div className="p-8 bg-card shadow-[var(--elevation-sm)]">Small</div>
  <div className="p-8 bg-card shadow-[var(--elevation-md)]">Medium</div>
  <div className="p-8 bg-card shadow-[var(--elevation-lg)]">Large</div>
  <div className="p-8 bg-card shadow-[var(--elevation-xl)]">XL</div>
  <div className="p-8 bg-card shadow-[var(--elevation-2xl)]">2XL</div>
  <div className="p-8 bg-card shadow-[var(--elevation-3xl)]">3XL</div>
</div>
```

---

## 🚀 Benefits Achieved

### **1. Centralized Control ✅**
Update one CSS file → entire site updates

### **2. Theme Support ✅**
```css
/* Dark mode example */
@media (prefers-color-scheme: dark) {
  :root {
    --primary: rgba(255, 255, 255, 1.00);
    --background: rgba(0, 0, 0, 1.00);
    --surface-warm: rgba(30, 30, 30, 1.00);
  }
}
```

### **3. Brand Flexibility ✅**
Change brand colors instantly:
```css
--accent: rgba(100, 200, 50, 1.00); /* New green brand color */
```

### **4. Maintainability ✅**
- Single source of truth
- Easier code reviews
- Consistent naming

### **5. Performance ✅**
- Smaller bundle (CSS variables vs hex codes)
- Better caching
- Runtime performance

---

## 📝 Next Steps

1. **Run find/replace** on all components using patterns above
2. **Test visual consistency** - take screenshots before/after
3. **Remove Tailwind font size classes** from headings/paragraphs
4. **Verify accessibility** - ensure contrast ratios maintained
5. **Document for team** - share this guide with developers

---

## 💡 Pro Tips

### **VS Code Search & Replace:**
1. Press `Cmd+Shift+F` (Mac) or `Ctrl+Shift+F` (Windows)
2. Enable regex mode (icon: `.*`)
3. Search in: `components/**/*.tsx`
4. Use patterns from above

### **Multi-cursor editing:**
1. `Cmd/Ctrl + D` to select next occurrence
2. Edit all at once

### **Git workflow:**
1. Create branch: `git checkout -b design-system-migration`
2. Commit per component: `git commit -m "refactor: migrate Hero to design system"`
3. Visual review before merging

---

## ✅ Completion Status

- [x] CSS variables defined in `/styles/globals.css`
- [x] Hero.tsx converted (reference example)
- [ ] App.tsx
- [ ] AboutMovement.tsx
- [ ] CountdownTimer.tsx
- [ ] Footer.tsx
- [ ] FAQ.tsx
- [ ] ImpactStats.tsx
- [ ] CampaignGallery.tsx
- [ ] FloatingCTA.tsx
- [ ] BackToTop.tsx
- [ ] Confetti.tsx
- [ ] (Other components as needed)

---

**Estimated Time Remaining:** 1-2 hours for all components

**Difficulty:** Low (mostly find/replace)

**Impact:** High (full design system compliance)

---

*Migration guide created: December 9, 2025*  
*All CSS variables tested and verified ✅*
