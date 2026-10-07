# Directory imports from public lists

Every import is idempotent and never deletes. New listings are published **unverified**, with their source and the list's date on the listing (card label and trust panel). Addresses that may be private are stored in `aw_import_address` and not shown.

Common steps (on your Mac, then on the server):
1. `wp term list aw_city --fields=term_id,slug,name,parent --format=json > terms.json` (on the server; copy it locally).
2. Build the rows with the source's builder (below).
3. Upload the JSON, then on the server: `wp eval-file aw_deploy/tools/directory/import_listings.php rows.json 0 500` (repeat with offset 500, 1000 … if needed), then `wp term recount aw_listing_type aw_city` and `wp litespeed-purge all`.

| Source | Builder | Notes |
|---|---|---|
| AWBI ABC project recognition list (PDF, 29-06-2026) | `pdftotext -bbox abc.pdf abc.html && python3 parse_abc.py abc.html > abc.json && python3 build_abc.py abc.json terms.json awbi_codes.json` | `awbi_codes.json` = `{AWBI code: post ID}` from `aw_awbi_code` meta. Centres of organisations already listed are attached to them (ABC type added, centre addresses kept private in `aw_abc_centres`); others become new listings. |
| 1962 Mobile Veterinary Units (DAHD reply to Lok Sabha Q 2854, 18 Mar 2025) | `pdftotext -layout LS2854.pdf mvu.txt && python3 build_1962.py mvu.txt terms.json` | One listing per state with units; phone 1962 (state call centre). No per-unit numbers are published. |
| District veterinary offices: UP, Maharashtra, Telangana, Karnataka, West Bengal (state Animal Husbandry websites) | `python3 build_state_offices.py terms.json` (fetches live) | Office name, district, office address, **official** phone (landline or department CUG) and official email. Officers' names and personal mobiles are never stored. |
| Tamil Nadu vet hospitals/dispensaries (data.gov.in, GODL-India) | — | Not imported: file downloads need a registered data.gov.in API key (403 without one). |

`places.py` holds the shared state/district matching (alias tables come from `tools/awbi/match.py`).
