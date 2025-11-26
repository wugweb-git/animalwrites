# 🚀 Quick Deployment Checklist - Animal Writes

## Before Deploying

### ✅ Files to Create Manually (if not in export)

Copy these files to your `/public` directory:

1. **`public/robots.txt`** ✓
2. **`public/sitemap.xml`** ✓
3. **`public/manifest.json`** ✓
4. **`public/browserconfig.xml`** ✓
5. **`public/security.txt`** ✓
6. **`public/.well-known/security.txt`** ✓
7. **`public/_redirects`** ✓
8. **`public/_headers`** ✓
9. **`netlify.toml`** (in root, not public) ✓

### 📝 Configuration Files Created

- ✅ `netlify.toml` - Main Netlify configuration
- ✅ `public/_redirects` - URL redirects and SPA routing
- ✅ `public/_headers` - HTTP headers for security and caching
- ✅ `NETLIFY-DEPLOYMENT-GUIDE.md` - Complete deployment guide

## Netlify Setup Steps

### 1️⃣ Deploy to Netlify

```bash
# Option 1: Via Netlify UI (Recommended)
1. Go to https://app.netlify.com
2. Click "Add new site" → "Import an existing project"
3. Connect GitHub repository
4. Build settings:
   - Build command: npm run build
   - Publish directory: dist
5. Click "Deploy site"

# Option 2: Via CLI
npm install -g netlify-cli
netlify login
netlify init
netlify deploy --prod
```

### 2️⃣ Add Environment Variables

In Netlify Dashboard → Site settings → Environment variables:

```
SUPABASE_URL=https://hnicktesvawczgfwvafh.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhuaWNrdGVzdmF3Y3pnZnd2YWZoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQxNzEzNDIsImV4cCI6MjA3OTc0NzM0Mn0.z0lkaamPLgQ8wtZ06oqb5eX_G8L5UC9YoZCu5pxmhSk
SUPABASE_SERVICE_ROLE_KEY=<get-from-supabase-dashboard>
```

⚠️ **Important**: Get `SUPABASE_SERVICE_ROLE_KEY` from:
https://supabase.com/dashboard/project/hnicktesvawczgfwvafh/settings/api

### 3️⃣ Configure Custom Domain (Optional)

1. Site settings → Domain management
2. Add custom domain: `animalwrites.in`
3. Update DNS records:
   - Type: `A`
   - Name: `@`
   - Value: `75.2.60.5`
4. HTTPS will auto-enable

### 4️⃣ Verify Deployment

Test these URLs after deployment:

```
✅ https://your-site.netlify.app/
✅ https://your-site.netlify.app/robots.txt
✅ https://your-site.netlify.app/sitemap.xml
✅ https://your-site.netlify.app/manifest.json
✅ https://your-site.netlify.app/.well-known/security.txt
✅ https://your-site.netlify.app/?admin=volunteers
```

### 5️⃣ Submit to Google Search Console

1. Go to: https://search.google.com/search-console
2. Add property: `https://animalwrites.in`
3. Verify ownership
4. Submit sitemap: `https://animalwrites.in/sitemap.xml`

## Domain Configuration

### Update Domain in Files

If your domain is NOT `animalwrites.in`, update these files:

1. **`public/robots.txt`** - Line 13:
   ```
   Sitemap: https://YOUR-DOMAIN.com/sitemap.xml
   ```

2. **`public/sitemap.xml`** - All `<loc>` tags:
   ```xml
   <loc>https://YOUR-DOMAIN.com/</loc>
   ```

3. **`public/security.txt`** - Lines 4 & 7:
   ```
   Canonical: https://YOUR-DOMAIN.com/.well-known/security.txt
   Policy: https://YOUR-DOMAIN.com
   ```

4. **`public/.well-known/security.txt`** - Same as above

## Post-Deployment Testing

### Functionality Tests

- [ ] Homepage loads correctly
- [ ] All sections scroll smoothly
- [ ] Countdown timer working
- [ ] Volunteer signup form submits to Supabase
- [ ] Admin page accessible: `?admin=volunteers`
- [ ] Volunteer data visible in admin dashboard
- [ ] CSV export works from admin page
- [ ] Mobile responsive (test on phone)
- [ ] All images load

### SEO Tests

- [ ] `robots.txt` accessible and correct
- [ ] `sitemap.xml` accessible and valid
- [ ] Meta tags present (view page source)
- [ ] Open Graph tags working (test: https://www.opengraph.xyz/)
- [ ] Twitter Card preview working
- [ ] Favicon displays in browser tab
- [ ] Manifest for PWA installation

### Performance Tests

- [ ] PageSpeed Insights score > 90: https://pagespeed.web.dev/
- [ ] Lighthouse audit passed (Chrome DevTools)
- [ ] Images optimized and loading fast
- [ ] No console errors
- [ ] HTTPS enabled (green padlock)

### Security Tests

- [ ] HTTPS enforced
- [ ] Security headers present (check: https://securityheaders.com/)
- [ ] No exposed API keys in frontend code
- [ ] CORS configured correctly
- [ ] Admin page not password protected (warning - add auth later!)

## Common Issues & Fixes

### Issue: 404 on page refresh
**Fix**: Ensure `public/_redirects` contains:
```
/* /index.html 200
```

### Issue: Environment variables not working
**Fix**: 
1. Add in Netlify UI, not `.env` file
2. Redeploy after adding
3. Check build logs

### Issue: Supabase functions fail
**Fix**:
1. Verify environment variables set
2. Check Supabase project is active
3. Review function logs in Supabase dashboard

### Issue: Static files return 404
**Fix**: Ensure `netlify.toml` has:
```toml
publish = "dist"
```

## Quick Commands

```bash
# Local development
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Deploy to Netlify (if using CLI)
netlify deploy --prod

# View live site logs
netlify logs

# Open Netlify dashboard
netlify open
```

## Support Resources

- **Full Guide**: See `NETLIFY-DEPLOYMENT-GUIDE.md`
- **Netlify Docs**: https://docs.netlify.com
- **Netlify Support**: https://answers.netlify.com
- **Supabase Docs**: https://supabase.com/docs

---

**Campaign Launch**: November 29, 2025 (3 days away!)  
**Target Domain**: https://animalwrites.in  
**Admin Dashboard**: Add `?admin=volunteers` to any URL
