# Netlify Deployment Guide - Animal Writes Campaign

## 📋 Prerequisites

- [x] GitHub repository with your code
- [x] Netlify account (sign up at https://netlify.com)
- [x] All required files in `/public` directory

## 🚀 Deployment Steps

### 1. **Configure Your Project Structure**

Ensure your project has this structure:
```
animal-writes/
├── public/
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── manifest.json
│   ├── browserconfig.xml
│   ├── security.txt
│   ├── .well-known/
│   │   └── security.txt
│   ├── _redirects
│   └── _headers
├── netlify.toml
├── package.json
└── ... other files
```

### 2. **Update Your Build Configuration**

Check your `package.json` has the correct build script:

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  }
}
```

### 3. **Deploy to Netlify**

#### Option A: Deploy via Netlify UI (Recommended for first deployment)

1. **Go to Netlify Dashboard**: https://app.netlify.com
2. **Click "Add new site" → "Import an existing project"**
3. **Connect to GitHub** and select your repository
4. **Configure build settings**:
   - **Build command**: `npm run build`
   - **Publish directory**: `dist`
   - **Base directory**: (leave empty)

5. **Add Environment Variables** (Important!):
   - Go to: Site settings → Environment variables → Add a variable
   - Add these variables:
     ```
     SUPABASE_URL=https://hnicktesvawczgfwvafh.supabase.co
     SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhuaWNrdGVzdmF3Y3pnZnd2YWZoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQxNzEzNDIsImV4cCI6MjA3OTc0NzM0Mn0.z0lkaamPLgQ8wtZ06oqb5eX_G8L5UC9YoZCu5pxmhSk
     SUPABASE_SERVICE_ROLE_KEY=<your-service-role-key>
     ```
   - ⚠️ **NEVER commit these to Git!**

6. **Click "Deploy site"**

#### Option B: Deploy via Netlify CLI

```bash
# Install Netlify CLI
npm install -g netlify-cli

# Login to Netlify
netlify login

# Initialize site
netlify init

# Deploy
netlify deploy --prod
```

### 4. **Configure Custom Domain** (Optional)

1. Go to: Site settings → Domain management → Add custom domain
2. Add `animalwrites.in`
3. Follow DNS configuration instructions:
   - Add A record: `75.2.60.5` (Netlify's load balancer)
   - Or CNAME: `your-site-name.netlify.app`
4. Enable HTTPS (automatic with Netlify)

### 5. **Verify Static Files Are Accessible**

After deployment, test these URLs:

```
✅ https://your-site.netlify.app/robots.txt
✅ https://your-site.netlify.app/sitemap.xml
✅ https://your-site.netlify.app/manifest.json
✅ https://your-site.netlify.app/browserconfig.xml
✅ https://your-site.netlify.app/.well-known/security.txt
```

### 6. **Submit to Google Search Console**

1. Go to: https://search.google.com/search-console
2. Add property: `https://animalwrites.in`
3. Verify ownership (use HTML tag method or DNS)
4. Submit sitemap: `https://animalwrites.in/sitemap.xml`

## 🔧 Troubleshooting

### Issue: Static files return 404

**Solution**: Ensure `netlify.toml` publish directory matches your build output:
- For Vite: `publish = "dist"`
- For Create React App: `publish = "build"`
- For Next.js: `publish = ".next"`

### Issue: SPA routes return 404

**Solution**: The `_redirects` file handles this. Ensure it contains:
```
/* /index.html 200
```

### Issue: Environment variables not working

**Solution**: 
1. Add variables in Netlify dashboard (not `.env` files)
2. Prefix with `VITE_` for Vite projects: `VITE_SUPABASE_URL`
3. Redeploy after adding variables

### Issue: Build fails

**Solution**: 
1. Check build logs in Netlify dashboard
2. Ensure Node version matches your local: Add to `netlify.toml`:
   ```toml
   [build.environment]
     NODE_VERSION = "20"
   ```
3. Clear cache and retry: Deploy settings → Clear cache and deploy

### Issue: Supabase functions not working

**Solution**: 
1. Check CORS is enabled in `/supabase/functions/server/index.tsx`
2. Verify environment variables are set in Netlify
3. Check function logs in Supabase dashboard

## 📊 Post-Deployment Checklist

- [ ] All static files accessible (`robots.txt`, `sitemap.xml`, etc.)
- [ ] HTTPS enabled (automatic with Netlify)
- [ ] Custom domain configured
- [ ] Environment variables set
- [ ] Volunteer signup form working
- [ ] Admin page accessible: `?admin=volunteers`
- [ ] Google Search Console sitemap submitted
- [ ] Social sharing works (test OG tags)
- [ ] Mobile responsive
- [ ] Page load speed < 3s (test with PageSpeed Insights)
- [ ] Accessibility audit passed (Lighthouse)

## 🎯 Performance Optimization

### Enable Netlify Features:

1. **Asset Optimization**:
   - Site settings → Build & deploy → Post processing
   - Enable: "Bundle CSS", "Minify CSS", "Minify JS", "Compress images"

2. **Netlify Analytics** (Optional, paid):
   - Real-time analytics without impacting performance
   - Site settings → Analytics → Enable

3. **Deploy Previews**:
   - Automatically enabled for pull requests
   - Test changes before merging

4. **Branch Deploys**:
   - Deploy specific branches for testing
   - Site settings → Build & deploy → Continuous deployment

## 📱 Testing URLs

Replace `your-site-name` with your actual Netlify site name:

- **Live Site**: `https://your-site-name.netlify.app`
- **Deploy Preview**: `https://deploy-preview-123--your-site-name.netlify.app`
- **Branch Deploy**: `https://branch-name--your-site-name.netlify.app`

## 🔗 Useful Links

- **Netlify Dashboard**: https://app.netlify.com
- **Netlify Docs**: https://docs.netlify.com
- **Build Logs**: Site → Deploys → [Latest deploy]
- **Function Logs**: Site → Functions → [Function name]
- **Analytics**: Site → Analytics
- **Support**: https://answers.netlify.com

## 🆘 Need Help?

- **Netlify Support Forum**: https://answers.netlify.com
- **Netlify Status**: https://www.netlifystatus.com
- **Community Discord**: https://netlifriends.com

---

**Last Updated**: November 26, 2025  
**Campaign Launch**: November 29, 2025  
**Website**: https://animalwrites.in
