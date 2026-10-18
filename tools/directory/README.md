# Directory imports from public lists

Every import is idempotent and never deletes. New listings are published **unverified**, with their source and the list's date on the listing (card label and trust panel). Addresses that may be private are stored in `aw_import_address` and not shown.

Common steps (on your Mac, then on the server):
1. Places come from the LGD file in the plugin (`plugins/animal-writes-core/data/lgd.json`, read by `places.py`); rows carry LGD state and district codes.
2. Build the rows with the source's builder (below).
3. Upload the JSON, then on the server: `wp eval-file aw_deploy/tools/directory/import_listings.php rows.json 0 500` (repeat with offset 500, 1000 … if needed), then `wp term recount post_tag` and `wp litespeed-purge all` (place counts update by themselves).

| Source | Builder | Notes |
|---|---|---|
| AWBI ABC project recognition list (PDF, 29-06-2026) | `pdftotext -bbox abc.pdf abc.html && python3 parse_abc.py abc.html > abc.json && python3 build_abc.py abc.json awbi_codes.json` | `awbi_codes.json` = `{AWBI code: post ID}` from `aw_awbi_code` meta. Centres of organisations already listed are attached to them (ABC type added, centre addresses kept private in `aw_abc_centres`); others become new listings. |
| 1962 Mobile Veterinary Units (DAHD reply to Lok Sabha Q 2854, 18 Mar 2025) | `pdftotext -layout LS2854.pdf mvu.txt && python3 build_1962.py mvu.txt` | One listing per state with units; phone 1962 (state call centre). No per-unit numbers are published. |
| District veterinary offices: UP, Maharashtra, Telangana, Karnataka, West Bengal (state Animal Husbandry websites) | `python3 build_state_offices.py` (fetches live) | Office name, district, office address, **official** phone (landline or department CUG) and official email. Officers' names and personal mobiles are never stored. |
| Kerala government veterinary institutions (Animal Husbandry Department, 14 district pages) | `python3 build_kerala.py` (fetches live) | Main institutions only (hospitals, polyclinics, dispensaries; no sub-centres): name, district, address, official email; official mobile where published (Malappuram). |
| Tamil Nadu vet hospitals/dispensaries (data.gov.in, GODL-India) | — | Not imported: file downloads need a registered data.gov.in API key (403 without one). |

| Existing listings, types from the name (owner, 18 Oct 2026) | `python3 build_name_types.py data/listings.json` | Shelter/home/ashram (about animals) → Animal Shelter, rescue/SPCA → Rescue Group, bird hospital → Bird Hospital, ambulance → Animal Ambulance. Adds only; max 3 types. |
| District Legal Services Authorities (9 SLSA sites, saved in `data/sources/nalsa/`) | `python3 build_dlsa.py data/listings.json` | One Legal Aid listing per DLSA: office phone, office email, office address. No officers' names or personal numbers. |
| District SPCAs (AWBI list Sep 2022; Haryana AH list) | `python3 build_spca.py data/listings.json` | Notified district SPCAs only; existing SPCA listings get Rescue Group. |
| Gujarat Karuna Abhiyan 2026 centre list | `python3 build_gujarat_birds.py data/listings.json` | Hand-picked permanent facilities only (rows checked against the sheet). |
| NGO Darpan via Dataful (collection 637, CSVs in `data/dataful/`) | `python3 build_dataful.py data/listings.json` | Animal Welfare sector, or Animal Husbandry with an animal name; ≤5 sectors. Darpan ID, registration, sectors, email. |

New builders share `match.py` (de-dupe against `wp eval-file export_listings.php > data/listings.json`) and `report.py` (dry-run report in `data/out/`). `data/` is not committed. Every import run logs its changes (`<rows>.log.jsonl`); `wp eval-file undo_import.php <log>` takes a run back (created listings to Trash) — only when the owner asks.

`places.py` holds the shared state/district matching (alias tables come from `tools/awbi/match.py`).
