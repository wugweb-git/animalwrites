# RankWeb: Pending & Roadmap

## Active Scope & Responsibilities
The SEO, schema, and sitemap plugin owns:
- Meta title, description, and canonical URL management.
- Real-time Editor Sidebar with title/meta character length counters and SERP previews.
- Breadcrumbs Block (`build/blocks/breadcrumbs.js`).
- Auto-linker engine with keyword-to-URL mapping.
- XML Sitemaps (posts, pages, directory listings).
- Schema.org JSON-LD generation (`Article`, `Organization`, `BreadcrumbList`).

---

## Open Tasks & Enhancements

1. **Editor Sidebar Integration**:
   - Refine Gutenberg sidebar panel for post types, verifying reactive updates on title/excerpt changes.
   - Social preview card generator (Twitter card / Open Graph image overlay).

2. **Breadcrumbs Block Polish**:
   - Verify block renders cleanly in FSE templates and classic page templates.
   - Ensure accessible ARIA markup (`aria-label="Breadcrumb"` and `aria-current="page"`).

3. **Auto-Linker Performance**:
   - Maintain standalone test suite (`plugins/rankweb/tests`) verifying HTML input/output without WordPress boot.
   - Enforce word boundaries and ignore links inside existing `<a>`, `<h1>-<h6>`, and `<pre>` tags.
