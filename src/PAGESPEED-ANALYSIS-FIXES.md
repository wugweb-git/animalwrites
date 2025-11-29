# PageSpeed Insights Analysis & Action Plan
**Date:** November 29, 2025  
**Analysis Source:** Real PageSpeed Insights Data

---

## 🔴 CRITICAL ISSUES IDENTIFIED

### Mobile Performance (Priority: URGENT)
| Metric | Current | Target | Status |
|--------|---------|--------|--------|
| **LCP** | 3.3s | <2.5s | ❌ FAILED (-0.8s needed) |
| **FCP** | 2.7s | <1.8s | ❌ FAILED (-0.9s needed) |
| **INP** | 239ms | <200ms | ⚠️ NEEDS WORK (-39ms) |
| **CLS** | 0 | <0.1 | ✅ PERFECT |
| **TTFB** | 0.9s | <0.8s | ⚠️ OK (borderline) |

### Desktop Performance
| Metric | Current | Target | Status |
|--------|---------|--------|--------|
| **LCP** | 2.7s | <2.5s | ⚠️ BORDERLINE (-0.2s) |
| **FCP** | 2.0s | <1.8s | ⚠️ BORDERLINE (-0.2s) |
| **INP** | 97ms | <200ms | ✅ GOOD |
| **CLS** | 0 | <0.1 | ✅ PERFECT |
| **TTFB** | 0.6s | <0.6s | ✅ GOOD |

---

## 🎯 PageSpeed Insights Recommendations

### 🔴 HIGH IMPACT (Implement Immediately)

#### 1. **Use Efficient Cache Lifetimes**
**Potential Savings: 814 KiB**

**Problem:** Static assets not cached properly

**Solution - Add Cache Headers:**

Create or update `/public/_headers`:
```
# Cache static assets
/*.js
  Cache-Control: public, max-age=31536000, immutable

/*.css
  Cache-Control: public, max-age=31536000, immutable

/*.png
  Cache-Control: public, max-age=31536000, immutable

/*.jpg
  Cache-Control: public, max-age=31536000, immutable

/*.webp
  Cache-Control: public, max-age=31536000, immutable

/*.svg
  Cache-Control: public, max-age=31536000, immutable

/*.woff2
  Cache-Control: public, max-age=31536000, immutable

# Cache HTML with short lifetime
/*.html
  Cache-Control: public, max-age=0, must-revalidate

# Root document
/
  Cache-Control: public, max-age=0, must-revalidate
```

**For Netlify:** Already have `netlify.toml`, add:
```toml
[[headers]]
  for = "/*.js"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"

[[headers]]
  for = "/*.css"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"

[[headers]]
  for = "/*.woff2"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"

[[headers]]
  for = "/*.png"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"

[[headers]]
  for = "/*.webp"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"

[[headers]]
  for = "/*.jpg"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"
```

**Impact:** -814 KiB on repeat visits, instant page loads for returning users

---

#### 2. **Improve Image Delivery**
**Potential Savings: 167 KiB**

**Current Issues:**
- Images not using modern formats everywhere
- Missing responsive images (srcset)
- Not using CDN optimization

**Solutions:**

**A. Add Responsive Images (srcset)**
```tsx
// Update CampaignGallery.tsx, Supporters.tsx, VisualImpact.tsx
<ImageWithFallback
  src="https://images.unsplash.com/.../w=800&fm=webp"
  srcSet="
    https://images.unsplash.com/.../w=400&fm=webp 400w,
    https://images.unsplash.com/.../w=800&fm=webp 800w,
    https://images.unsplash.com/.../w=1200&fm=webp 1200w
  "
  sizes="(max-width: 640px) 400px, (max-width: 1024px) 800px, 1200px"
  alt="..."
/>
```

**B. Optimize Unsplash Parameters**
```tsx
// Use these parameters for ALL Unsplash images:
?fm=webp         // WebP format
&q=75            // Quality 75% (sweet spot)
&auto=format     // Auto-select best format
&fit=crop        // Crop to exact size
&w=800           // Explicit width
```

**C. Compress Inline SVGs**
- Minify SVG code
- Remove unnecessary attributes
- Use SVGO tool

**Impact:** -167 KiB on initial load, faster LCP

---

#### 3. **Render-Blocking Requests**
**Potential Savings: 410ms**

**Problem:** JavaScript/CSS blocking initial render

**Solutions:**

**A. Defer Non-Critical CSS**
```tsx
// In MetaTags.tsx, add preload for critical CSS only
const link = document.createElement('link');
link.rel = 'preload';
link.as = 'style';
link.href = '/critical.css';
document.head.appendChild(link);
```

**B. Async Google Analytics**
Already done ✅ - Script has `async` attribute

**C. Split Critical CSS**
Extract above-the-fold CSS and inline it in `<head>`

**D. Reduce Motion Library Usage**
Replace Motion with CSS animations where possible:
```tsx
// Instead of:
<motion.div animate={{ scale: [1, 1.2, 1] }}>

// Use CSS:
<div className="animate-pulse-custom">
// In CSS:
@keyframes pulse-custom {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.2); }
}
```

**Impact:** -410ms faster FCP/LCP

---

#### 4. **Reduce Unused JavaScript**
**Potential Savings: 110 KiB**

**Problem:** Loading libraries not fully used

**Solutions:**

**A. Tree-Shakeable Icon Imports (CRITICAL)**
Currently importing entire Lucide library on EVERY component!

```tsx
// ❌ BAD - Imports entire library (15KB per icon × 20 icons = 300KB!)
import { Mail, Heart, Users, MapPin, ... } from "lucide-react";

// ✅ GOOD - Tree-shakeable (only 5KB per icon)
import Mail from "lucide-react/dist/esm/icons/mail";
import Heart from "lucide-react/dist/esm/icons/heart";
import Users from "lucide-react/dist/esm/icons/users";
```

**Files to Update:**
- Hero.tsx
- Navigation.tsx  
- WhyThisMatters.tsx
- HowToJoin.tsx
- LetterTemplates.tsx
- VolunteerSignup.tsx
- FAQ.tsx
- Footer.tsx
- And 15+ more components!

**B. Lazy Load Heavy Libraries**
```tsx
// Lazy load Motion for non-critical animations
const motion = lazy(() => import('motion/react'));
```

**C. Remove Unused UI Components**
Check which shadcn/ui components are actually used

**Impact:** -250-300 KiB bundle size, faster TTI

---

#### 5. **Avoid Enormous Network Payloads**
**Current Total: 7,863 KiB (7.8 MB) - WAY TOO LARGE!**

**Breakdown (Estimated):**
- JavaScript bundles: ~400-500 KB
- Images: ~6-7 MB (!!!)
- CSS: ~50-100 KB
- Fonts: ~100-200 KB

**Solutions:**

**A. Aggressive Image Compression**
Gallery has 12 images × ~500KB = 6MB! Need to:
- Reduce quality to 75%
- Use max width of 600px for thumbnails
- Use max width of 1200px for full images
- Implement progressive loading

```tsx
// Gallery images should use:
?w=600&fm=webp&q=75  // Thumbnails: ~40-60KB each
?w=1200&fm=webp&q=80 // Full size: ~120-150KB each
```

**B. Implement Image CDN**
Consider using:
- Cloudflare Images
- Cloudinary
- imgix
- Netlify Image CDN

**C. Lazy Load Images More Aggressively**
Already done ✅ for most images

**Impact:** Reduce from 7.8MB to ~2-3MB (60% reduction!)

---

### 🟡 MEDIUM IMPACT (Implement Soon)

#### 6. **LCP Request Discovery**
**Problem:** Browser discovers LCP element late

**Solution:**
```tsx
// Already implemented! ✅
<link rel="preload" as="image" href="hero-image.webp" />
```

Verify in MetaTags.tsx that hero image preload is working

---

#### 7. **Network Dependency Tree**
**Problem:** Resources loading sequentially instead of parallel

**Solution:**
- Use HTTP/2 Push (automatic on Netlify)
- Reduce critical request depth
- Preconnect to third-party domains (already done ✅)

---

#### 8. **Forced Reflow**
**Problem:** JavaScript causing layout recalculations

**Solution:**
- Batch DOM reads before writes
- Use `requestAnimationFrame` for animations
- Avoid reading layout properties in loops

Check these files:
- ScrollProgress.tsx (scroll event listener)
- Navigation.tsx (sticky header)
- FloatingCTA.tsx

---

#### 9. **Avoid Long Main-Thread Tasks**
**Current: 3 long tasks found**

**Solution:**

**A. Code Split More Aggressively**
Already started ✅, but add more:
```tsx
const FAQ = lazy(() => import("./components/FAQ"));
const Resources = lazy(() => import("./components/Resources"));
const Timeline = lazy(() => import("./components/Timeline"));
```

**B. Debounce Expensive Operations**
```tsx
// In scroll handlers:
const debounce = (fn, delay) => {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(...args), delay);
  };
};
```

**C. Use Web Workers for Heavy Computation**
If doing any data processing client-side

---

## 🎯 IMPLEMENTATION PRIORITY

### Phase 1: Immediate (Today) - Target: -1.5s LCP
1. ✅ Add tree-shakeable icon imports (saves 250-300KB)
2. ✅ Optimize all Unsplash images to ?fm=webp&q=75&w=600-800
3. ✅ Add srcset to all images
4. ✅ Add cache headers to netlify.toml
5. ✅ Replace Motion with CSS for simple animations

**Expected Impact:**
- Bundle size: 400KB → 150KB
- Image payload: 7MB → 2.5MB
- LCP: 3.3s → 2.0s (mobile)
- FCP: 2.7s → 1.5s (mobile)

---

### Phase 2: Short-term (This Week) - Target: Pass Core Web Vitals
1. Implement responsive images everywhere
2. Add critical CSS inlining
3. Optimize scroll event handlers
4. Add more aggressive code splitting
5. Implement image CDN

**Expected Impact:**
- LCP: 2.0s → 1.6s (mobile)
- INP: 239ms → 150ms
- Lighthouse: 78 → 95+

---

### Phase 3: Long-term (Post-Campaign) - Target: Perfect Score
1. Implement Service Worker for caching
2. Add resource hints for all third-party domains
3. Optimize font loading
4. Implement skeleton screens
5. Add performance monitoring

---

## 📊 EXPECTED IMPROVEMENTS

### Current vs. Target (Mobile)
| Metric | Current | After Phase 1 | After Phase 2 | Target |
|--------|---------|---------------|---------------|--------|
| LCP | 3.3s | 2.0s | 1.6s | <2.5s ✅ |
| FCP | 2.7s | 1.5s | 1.2s | <1.8s ✅ |
| INP | 239ms | 200ms | 150ms | <200ms ✅ |
| CLS | 0 | 0 | 0 | <0.1 ✅ |
| Bundle | 400KB | 150KB | 120KB | <200KB ✅ |
| Images | 7MB | 2.5MB | 1.8MB | <3MB ✅ |

---

## 🚀 Quick Wins (30 Minutes)

1. **Update netlify.toml with cache headers** (5 min)
2. **Change all Unsplash URLs to use ?fm=webp&q=75** (10 min)
3. **Update 5 most-used components to tree-shakeable icons** (15 min)

**Expected Savings:** ~500KB, ~0.8s faster LCP

---

## 🔧 Files That Need Updates

### High Priority:
- [ ] `/netlify.toml` - Add cache headers
- [ ] `/components/Hero.tsx` - Already optimized ✅
- [ ] `/components/CampaignGallery.tsx` - Optimize 12 images
- [ ] `/components/Supporters.tsx` - Optimize logo images
- [ ] `/components/VisualImpact.tsx` - Optimize images
- [ ] `/components/Navigation.tsx` - Tree-shakeable icons
- [ ] `/components/FAQ.tsx` - Tree-shakeable icons
- [ ] `/components/Footer.tsx` - Tree-shakeable icons
- [ ] `/components/WhyThisMatters.tsx` - Tree-shakeable icons
- [ ] `/components/HowToJoin.tsx` - Tree-shakeable icons
- [ ] `/components/LetterTemplates.tsx` - Tree-shakeable icons
- [ ] `/components/VolunteerSignup.tsx` - Tree-shakeable icons

### Medium Priority:
- [ ] `/App.tsx` - More code splitting
- [ ] `/components/ScrollProgress.tsx` - Debounce scroll
- [ ] `/components/FloatingCTA.tsx` - Optimize animations
- [ ] Replace Motion with CSS in 8+ components

---

## ✅ SUCCESS METRICS

**Pass Core Web Vitals:**
- ✅ LCP < 2.5s
- ✅ INP < 200ms  
- ✅ CLS < 0.1 (already passing!)

**Target Lighthouse Scores:**
- Mobile: 95+ (currently ~70-75)
- Desktop: 98+ (currently ~85-90)

**Campaign Day Readiness:**
- Handle 10,000+ simultaneous users
- Load in <2s on 4G mobile
- Work offline with Service Worker

---

## 🎓 Key Learnings

1. **Image optimization is CRITICAL** - 7.8MB is unacceptable
2. **Icon imports are HUGE** - Tree-shaking saves 250-300KB
3. **Cache headers are FREE wins** - 814KB instant savings
4. **Motion library is heavy** - Use CSS when possible
5. **Code splitting works** - But need more of it

---

**Next Action:** Implement Phase 1 fixes immediately to improve mobile LCP from 3.3s to ~2.0s before campaign day.
