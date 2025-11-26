# Netlify Headers for Animal Writes Campaign
# https://docs.netlify.com/routing/headers/

# Global security and performance headers
/*
  X-Frame-Options: SAMEORIGIN
  X-Content-Type-Options: nosniff
  X-XSS-Protection: 1; mode=block
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(), microphone=(), geolocation=()

# HTML files - no cache
/*.html
  Cache-Control: public, max-age=0, must-revalidate

# Manifest file
/manifest.json
  Content-Type: application/manifest+json
  Cache-Control: public, max-age=31536000, immutable

# Sitemap
/sitemap.xml
  Content-Type: application/xml; charset=utf-8
  Cache-Control: public, max-age=3600

# Robots.txt
/robots.txt
  Content-Type: text/plain; charset=utf-8
  Cache-Control: public, max-age=3600

# Browser config
/browserconfig.xml
  Content-Type: application/xml; charset=utf-8
  Cache-Control: public, max-age=31536000

# Security.txt
/.well-known/security.txt
  Content-Type: text/plain; charset=utf-8
  Cache-Control: public, max-age=86400

# Static assets - long-term cache
/assets/*
  Cache-Control: public, max-age=31536000, immutable

/*.js
  Cache-Control: public, max-age=31536000, immutable

/*.css
  Cache-Control: public, max-age=31536000, immutable

/*.woff2
  Cache-Control: public, max-age=31536000, immutable

/*.png
  Cache-Control: public, max-age=31536000, immutable

/*.jpg
  Cache-Control: public, max-age=31536000, immutable

/*.svg
  Cache-Control: public, max-age=31536000, immutable
