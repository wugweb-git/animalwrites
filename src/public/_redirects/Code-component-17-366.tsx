# Netlify Redirects for Animal Writes Campaign
# https://docs.netlify.com/routing/redirects/

# Redirect security.txt to .well-known location (RFC 9116 compliant)
/security.txt /.well-known/security.txt 301

# Supabase Functions proxy (if needed)
/api/* https://hnicktesvawczgfwvafh.supabase.co/functions/v1/:splat 200

# SPA fallback - serve index.html for all routes (must be last)
/* /index.html 200
