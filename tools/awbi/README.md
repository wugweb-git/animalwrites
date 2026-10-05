# AWBI recognised organisations → directory

1. Parse the AWBI "List of AWOs as on <date>" PDF into `awo_2026.json` (pdfplumber; fields n, code, name, address, district, state, recognised, renewed, valid) and the ABC project recognition list into `abc_pr.json`.
2. Export district terms: `wp term list aw_city --fields=term_id,slug,name,parent --format=json > terms.json`.
3. `python3 match.py <dir>` → `awo_import.json` (state by name plus aliases, district by LGD name, aliases and fuzzy match, falling back to the address; types: Animal Welfare Organisation, plus Gaushala by name, plus ABC Centre by AWBI code).
4. Upload and run in chunks: `wp eval-file import_awbi.php awo_import.json <offset> 500`.

Idempotent by AWBI code plus name: re-running updates details and keeps each listing's status and any verified flag. New rows arrive as drafts. The full address is kept in `aw_awbi_address` for checking claims and is not shown on the site; the page shows district and state.
