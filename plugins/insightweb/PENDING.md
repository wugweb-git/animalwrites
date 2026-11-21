# InsightWeb: Pending & Roadmap

## Active Scope & Responsibilities
The analytics, telemetry, and search metrics plugin owns:
- Minimalist admin dashboard with inline SVG MiniCharts (zero external JS chart dependencies).
- Privacy-first engagement telemetry (no cookies, no PII stored).
- Search query and impression tracking.
- Daily aggregation crons with zero write-locks on front-end views.

---

## Open Tasks & Enhancements

1. **Dashboard Tile Refinements**:
   - Verify all dashboard tiles render correctly when analytics tables are empty (first install state).
   - Ensure CSV/JSON export capability for site analytics.

2. **Search Insights Integration**:
   - Step 0–9 keyword action plan integration: surface rising queries and declining keywords directly in the dashboard.
   - 404 error tracking and internal search query logging.
