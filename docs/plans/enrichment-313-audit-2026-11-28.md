# Audit of the 313 pending enrichment proposals (28 Nov 2026) — read-only, nothing applied

**What the 313 is:** field-level proposals waiting in Sourcing → Directory enrichment (SourceWeb `aw_enrich`, status `pending`), not listings. They cover 246 listings (248 rows on the screen), all created 12 to 25 Nov (196 on 12 Nov, 57 on 13 Nov, 55 on 18 Nov, 5 on 25 Nov). The worker is not stalled: it ran 28 Nov 10:27, "listings: 0", because all 7,565 listings are marked checked (the queue only hands out new or claimed listings, or ones not checked for 90 days). So there is nothing to "restart"; re-checking the thin pile needs those listings re-opened for check and spends Google quota (this month's spend on the config shows $33.29).

**Confidence:** medium 242, low 71 (none high; high ones apply by themselves when the field is empty).
**Why each waits (the screen's own note):** would replace a value already on the listing 208; from an imported list 59; sources disagree 46 (email 31, website 9, pincode 5, registration 1).
**Fields:** email 107, address 56, facebook 36, services 24, website 24, pincode 24, instagram 12, phone 10, head 7, linkedin 4, registration 3, founded 2, youtube 2, animals 1, x 1.
**Only 8 fill an empty field** (all emails from web-search snippets). 305 would replace or alter something already there. Field rules for phone, email, website are "Both" (the old value stays beside the new, both marked unverified), so approving never drops the old value.

## Buckets (source tier x what the change is)
| Bucket | Rows | Recommendation |
|---|---:|---|
| Own website / organisation / Wikidata / social, value differs from the listing | 130 | Best evidence (trust order 2 after a claim). Approve candidates, spot-check emails (66) first |
| Own source, adds to current (extra phone/email/social/registration) | 5 | Approve |
| Format-only (same pincode / head already there) | 13 | No change; close |
| Search or directory snippet, adds to current (23 are services lists) | 29 | Services lists are low risk; approve those, hold the rest |
| Search or directory snippet, value differs (Bing 55, DuckDuckGo 11, Dog Lover old listing 31, JustDial 5, Gemini low reliability 6, panchayat pages) | 127 | Do not approve: weak or stale sources; reject or re-source |
| Search snippet filling an empty email | 8 | Hold: no second source |
| Other | 1 | Hold |

**Flags to resolve first (11 rows):** Reported closed (Apna foundation, Patna, instagram); Possible duplicate (WSA We Save Animals, People for Animals Bangalore x2, PFA Wildlife Rescue & Conservation Centre x2); Invite to claim only (SAWO, Society for Animal Safety India, TaTa Trusts Small Animal Hospital, Karuna x2). **"CHECK FIRST" notes (4):** Veterinary Dispensary Marayoor (government body, email), The Habitats Trust Noida (listing says Kanpur), TRAFFIC India and TRAFFIC India Regional Office (WWF-India programme, not a shelter; phone is WWF's main line). Reject those four.

Nothing was approved, rejected or written by this audit.
