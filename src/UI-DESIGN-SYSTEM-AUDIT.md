# 🎨 UI Design System Audit Report
**Animal Writes Campaign Website**  
**Date:** December 9, 2025

---

## 📋 Executive Summary

**Status:** ⚠️ **MAJOR VIOLATIONS DETECTED**

The codebase currently uses **100+ instances** of hardcoded color values instead of CSS variables from the design system defined in `/styles/globals.css`.

### Issues Found:
- ❌ Hardcoded hex colors (#F59E0B, #D97706, #333333, #666666, #FFF8F3, etc.)
- ❌ Hardcoded spacing values
- ❌ Hardcoded border radius values
- ❌ Hardcoded font sizes (violates typography system)
- ❌ Hardcoded font weights

### Impact:
- Design system cannot be updated centrally
- Inconsistent styling across components
- Difficult to maintain brand consistency
- Theme switching impossible
- Violates single source of truth principle

---

## 🎯 Design System Overview

Your design system in `/styles/globals.css` defines:

### **Colors:**
- `--primary` (rgba(43, 43, 43, 1.00)) → Dark gray/black
- `--accent` (rgba(255, 190, 26, 1.00)) → Yellow/orange
- `--foreground` → Text color
- `--muted-foreground` → Secondary text
- `--background` → Page background
- `--card` → Card backgrounds
- `--border` → Border colors
- Plus 20+ more semantic color tokens

### **Typography:**
- Font: 'Inter Tight', sans-serif
- Sizes: --text-xs to --text-3xl
- Weights: --font-weight-regular (400), medium (500), semibold (600), bold (700)

### **Spacing/Radius:**
- `--radius-sm` (4px)
- `--radius-md` (8px)
- `--radius-lg` (12px)
- `--radius-full` (9999px)

### **Shadows:**
- `--elevation-sm`

---

## 🔴 Hardcoded Values Inventory

### **Color Violations (100+ instances):**

#### **#F59E0B (Yellow/Orange) - 50+ instances**
Should use: `--accent` or `bg-accent`, `text-accent`, `border-accent`

**Files affected:**
- AboutMovement.tsx (10+ instances)
- Hero.tsx (5+ instances)
- CountdownTimer.tsx (15+ instances)
- Footer.tsx (20+ instances)
- FAQ.tsx (5+ instances)
- ImpactStats.tsx
- CampaignGallery.tsx
- FloatingCTA.tsx
- BackToTop.tsx

#### **#333333 (Dark Gray/Black) - 40+ instances**
Should use: `--primary` or `bg-primary`, `text-primary`

**Files affected:**
- Hero.tsx (8 instances)
- AboutMovement.tsx (10+ instances)
- Footer.tsx (5+ instances)
- FAQ.tsx (8 instances)
- FloatingCTA.tsx (4 instances)
- BackToTop.tsx (3 instances)
- App.tsx (skip to content link)

#### **#666666 (Medium Gray) - 20+ instances**
Should use: `--muted-foreground` or `text-muted-foreground`

**Files affected:**
- Hero.tsx (4 instances)
- AboutMovement.tsx (2 instances)
- FAQ.tsx (5 instances)
- CampaignGallery.tsx (2 instances)
- FloatingCTA.tsx (1 instance)

#### **#FFF8F3 (Light Beige/Cream) - 10+ instances**
Should use: Custom CSS variable `--surface-warm` or use `--card` with opacity

**Files affected:**
- Hero.tsx (background)
- AboutMovement.tsx (4 instances)
- FAQ.tsx (2 instances)
- CampaignGallery.tsx (1 instance)
- Confetti.tsx

#### **#D97706 (Darker Orange) - 8+ instances**
Should use: `--accent` with opacity or create `--accent-dark` variable

**Files affected:**
- Hero.tsx (2 instances)
- Footer.tsx (3 instances)
- FAQ.tsx (1 instance)
- Confetti.tsx

### **Typography Violations:**

#### **Hardcoded Font Sizes:**
❌ `text-5xl`, `text-4xl`, `text-3xl`, `text-2xl`, `text-xl`, `text-lg`, `text-base`, `text-sm`, `text-xs`

Should use CSS variables:
- `--text-3xl` (3rem / 48px)
- `--text-2xl` (2.25rem / 36px)
- `--text-xl` (1.5rem / 24px)
- `--text-lg` (1.25rem / 20px)
- `--text-base` (1rem / 16px)
- `--text-sm` (0.875rem / 14px)
- `--text-xs` (0.75rem / 12px)

**However:** Your guidance says NOT to use Tailwind font size classes unless user specifically asks. Components should rely on semantic HTML elements (h1, h2, h3, p) which already have typography defined in globals.css.

#### **Hardcoded Font Weights:**
❌ `font-bold`, `font-semibold`, `font-medium`, `font-regular`

Should use CSS variables via semantic elements or:
- `font-[var(--font-weight-bold)]`
- `font-[var(--font-weight-semibold)]`
- `font-[var(--font-weight-medium)]`
- `font-[var(--font-weight-regular)]`

### **Border Radius Violations:**

❌ Hardcoded values: `rounded-xl`, `rounded-2xl`, `rounded-3xl`, `rounded-full`, `rounded-lg`, `rounded-md`

Should use:
- `rounded-[var(--radius-sm)]` (4px)
- `rounded-[var(--radius-md)]` (8px)
- `rounded-[var(--radius-lg)]` (12px)
- `rounded-[var(--radius-full)]` (9999px)

**Files affected:** All component files

### **Shadow Violations:**

❌ Hardcoded: `shadow-lg`, `shadow-xl`, `shadow-2xl`, `shadow-3xl`, `shadow-md`, `shadow-sm`

Should use:
- `shadow-[var(--elevation-sm)]`

Or define additional elevation variables:
- `--elevation-md`
- `--elevation-lg`
- `--elevation-xl`

---

## ✅ Recommended Mapping

### **Color Mapping:**

| Hardcoded Value | CSS Variable | Tailwind Class |
|-----------------|--------------|----------------|
| `#F59E0B` | `--accent` | `bg-accent`, `text-accent`, `border-accent` |
| `#D97706` | `--accent` (darker) | Create `--accent-hover` if needed |
| `#333333` | `--primary` | `bg-primary`, `text-primary` |
| `#1a1a1a` | `--primary` (darker) | Create `--primary-hover` if needed |
| `#666666` | `--muted-foreground` | `text-muted-foreground` |
| `#FFF8F3` | Create `--surface-warm` | `bg-[var(--surface-warm)]` |
| `rgba(255, 255, 255, 1.00)` | `--background` or `--card` | `bg-background`, `bg-card` |
| `rgba(0, 0, 0, 1.00)` | `--foreground` | `text-foreground` |

### **Typography Mapping:**

| Current | Should Be |
|---------|-----------|
| `text-5xl sm:text-5xl md:text-6xl lg:text-7xl` | Use `<h1>` (48px defined in CSS) |
| `text-4xl md:text-5xl` | Use `<h2>` (36px defined in CSS) |
| `text-3xl md:text-4xl` | Use `<h2>` or `<h3>` |
| `text-2xl` | Use `<h3>` (24px) or custom class |
| `text-xl` | Use `<h4>` (20px) or custom class |
| `text-lg` | Use `<p>` with custom class |
| `text-base` | Use `<p>` (16px default) |
| `font-bold` | Use semantic element or `font-[var(--font-weight-bold)]` |

### **Border Radius Mapping:**

| Tailwind Class | CSS Variable | Pixel Value |
|----------------|--------------|-------------|
| `rounded` | `--radius-sm` | 4px |
| `rounded-md` | `--radius-md` | 8px |
| `rounded-lg` | `--radius-lg` | 12px |
| `rounded-xl` | `--radius-lg` | 12px (adjust if needed) |
| `rounded-2xl` | Create `--radius-xl` | 16px |
| `rounded-3xl` | Create `--radius-2xl` | 24px |
| `rounded-full` | `--radius-full` | 9999px |

---

## 🛠️ Required CSS Variable Additions

To fully support the current design, add these to `/styles/globals.css`:

```css
:root {
  /* Additional color tokens */
  --accent-hover: rgba(217, 119, 6, 1.00); /* Darker orange for hover states */
  --primary-hover: rgba(26, 26, 26, 1.00); /* Darker black for hover states */
  --surface-warm: rgba(255, 248, 243, 1.00); /* Warm beige background */
  
  /* Additional radius tokens */
  --radius-xl: 16px;
  --radius-2xl: 24px;
  --radius-3xl: 32px;
  
  /* Additional elevation tokens */
  --elevation-md: 0px 10px 15px -3px rgba(0, 0, 0, 0.1), 0px 4px 6px -2px rgba(0, 0, 0, 0.05);
  --elevation-lg: 0px 20px 25px -5px rgba(0, 0, 0, 0.1), 0px 10px 10px -5px rgba(0, 0, 0, 0.04);
  --elevation-xl: 0px 25px 50px -12px rgba(0, 0, 0, 0.25);
  --elevation-2xl: 0px 30px 60px -15px rgba(0, 0, 0, 0.3);
}

@theme inline {
  /* Add these to existing @theme inline block */
  --color-accent-hover: var(--accent-hover);
  --color-primary-hover: var(--primary-hover);
  --color-surface-warm: var(--surface-warm);
  --radius-xl: var(--radius-xl);
  --radius-2xl: var(--radius-2xl);
  --radius-3xl: var(--radius-3xl);
  --shadow-md: var(--elevation-md);
  --shadow-lg: var(--elevation-lg);
  --shadow-xl: var(--elevation-xl);
  --shadow-2xl: var(--elevation-2xl);
}
```

---

## 🎯 Component-by-Component Breakdown

### **App.tsx**
- [ ] Line 101: Skip to content link - `#333333` → `bg-primary`

### **AboutMovement.tsx**
- [ ] Line 21: Heading - `#333333` → Use `<h2>` element styling
- [ ] Line 25: Text - `#666666` → `text-muted-foreground`
- [ ] Lines 32-34: Strong tags - `#333333` → `text-primary`
- [ ] Line 44: Card background - `#FFF8F3` → `bg-[var(--surface-warm)]`
- [ ] Line 44: Border - `#F59E0B` → `border-accent`
- [ ] Lines 63, 75, 98, 121: Various backgrounds and borders
- [ ] Lines 114-115, 137-138: Progress bars - `#F59E0B` → `bg-accent`

### **Hero.tsx**
- [ ] Line 15: Section background - `#FFF8F3` → `bg-[var(--surface-warm)]`
- [ ] Lines 20-21: Decorative circles - `#F59E0B` → `bg-accent`
- [ ] Line 33: H1 - Already using semantic `<h1>`, remove hardcoded sizes
- [ ] Line 37: Tagline - `#666666` → `text-muted-foreground`
- [ ] Line 44: Icon - `#333333` → `text-primary`
- [ ] Line 62: Primary CTA - `#F59E0B` bg, `#D97706` hover → `bg-accent hover:bg-[var(--accent-hover)]`
- [ ] Line 70: Secondary CTA - `#333333` → `bg-primary hover:bg-[var(--primary-hover)]`

### **CountdownTimer.tsx**
- [ ] Line 7: Gradient background - `#333333`, `#444444` → Use CSS variables
- [ ] Line 16: Decorative dots - `#F59E0B` → `bg-accent`
- [ ] Line 47: Badge - `#F59E0B` → `bg-accent`
- [ ] Lines 83, 101, 119: Card borders - `#F59E0B` → `border-accent`
- [ ] Lines 92, 110, 128: Icons - `#F59E0B` → `text-accent`

### **Footer.tsx**
- [ ] Line 10: Background gradient - `#333333`, `#222222` → Use CSS variables
- [ ] Line 12: Top wave - `#F59E0B`, `#D97706` → `from-accent via-[var(--accent-hover)] to-accent`
- [ ] Line 19: Decorative dots - `#F59E0B` → `bg-accent`
- [ ] Lines 71, 80, 91, 100: Social buttons hover - `#F59E0B` → `hover:bg-accent`
- [ ] All text colors throughout

### **FAQ.tsx**
- [ ] Line 53: Background - `#FFF8F3` → `bg-[var(--surface-warm)]`
- [ ] Lines 66-67: Icons and heading - `#333333` → `text-primary`
- [ ] Line 91: Input focus - `#F59E0B` → `focus:border-accent`
- [ ] Line 111: Button hover - `#F59E0B`, `#D97706` → `text-accent hover:text-[var(--accent-hover)]`
- [ ] Line 128: Accordion hover - `#F59E0B` → `hover:border-accent`

### **ImpactStats.tsx**
- [ ] All color instances - Convert to design system variables

### **CampaignGallery.tsx**
- [ ] Background - `#FFF8F3` → `bg-[var(--surface-warm)]`
- [ ] Icons - `#F59E0B` → `text-accent`
- [ ] Headings - `#333333` → `text-primary`

### **FloatingCTA.tsx**
- [ ] Background - `#333333` → `bg-primary`
- [ ] Hover states - Convert to CSS variables

### **BackToTop.tsx**
- [ ] Background - `#333333` → `bg-primary`
- [ ] Hover - `#1a1a1a` → `hover:bg-[var(--primary-hover)]`

---

## 📝 Action Items

### **Phase 1: Update globals.css** ✅ (Priority: Critical)
1. Add missing CSS variables:
   - `--accent-hover`
   - `--primary-hover`
   - `--surface-warm`
   - Additional radius tokens
   - Additional elevation tokens

2. Add them to `@theme inline` block

### **Phase 2: Component Updates** (Priority: Critical)
Convert ALL components to use CSS variables:

1. Replace ALL hex colors with semantic tokens
2. Remove Tailwind font size classes (rely on HTML elements)
3. Replace hardcoded border radius with CSS variables
4. Replace hardcoded shadows with elevation variables

### **Phase 3: Testing** (Priority: High)
1. Verify visual consistency after conversion
2. Test theme switching (if applicable)
3. Verify no broken styles
4. Test accessibility (contrast ratios)

### **Phase 4: Documentation** (Priority: Medium)
1. Document color token usage
2. Create component examples
3. Add Figma/design tool integration guide

---

## 🎨 Benefits After Migration

### **Centralized Control:**
- Change entire color scheme from one file
- Update spacing/sizing globally
- Maintain consistency effortlessly

### **Theme Support:**
- Easy dark mode implementation
- Brand color variations
- Seasonal themes

### **Maintainability:**
- Single source of truth
- Reduced code duplication
- Easier code reviews

### **Developer Experience:**
- Semantic naming (accent vs #F59E0B)
- Autocomplete in modern IDEs
- Type safety with CSS variables

### **Performance:**
- Smaller bundle size (CSS variables vs repeated hex codes)
- Better caching
- Faster runtime style calculations

---

## 🚀 Next Steps

**Immediate Actions:**
1. ✅ Update `/styles/globals.css` with missing variables
2. 🔄 Convert all components systematically
3. ✅ Test visual consistency
4. ✅ Update documentation

**Estimated Time:**
- CSS updates: 15 minutes
- Component updates: 2-3 hours (10 components)
- Testing: 30 minutes
- **Total: 3-4 hours**

---

*Audit completed: December 9, 2025*
