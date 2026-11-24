# Directory Duplicates Audit (24 Nov 2026)

> **Context**: Comprehensive audit of the logged queue of **33 duplicate website groups** and **61 duplicate email groups** on live production (`animalwrites.in`), fulfilling **Option C** from `docs/cc-master-plan-19-nov.md` and `docs/animalwrites-operations-and-content-pending.md`.

---

## 1. Executive Summary

A live database audit across 7,738 directory listings (`aw_listing`) was executed on 24 Nov 2026.

| Category | Duplicate Website Groups | Duplicate Email Groups | Total Unique Listings | Risk Assessment & Action |
|---|---|---|---|---|
| **Government & Shared Infrastructure** | 3 groups (31 listings) | 13 groups (27 listings) | 58 listings | **CRITICAL: DO NOT MERGE.** These are distinct government veterinary dispensaries, district AHVS offices, and state 1962 ambulances sharing department web portals. |
| **True NGO & Rescue Duplicates** | 30 groups (64 listings) | 48 groups (103 listings) | 124 listings | **Candidate for Merge / Alias.** Multiple imports (AWBI lists, PFA units, Google Places, AWO scrapers) created duplicate records for the same organisation. |

---

## 2. Category 1: Government & Public Infrastructure (KEEP SEPARATE)

These listings share a website or email because they belong to state government departments (Kerala Animal Husbandry Department, Karnataka AHVS, EMRI Green Health Services 1962). Each record represents a distinct physical veterinary facility or district office.

### Shared Website Portals (Do Not Merge)
- `https://ahd.kerala.gov.in/palakkad` (24 distinct veterinary hospitals/dispensaries across Palakkad district: Alathur, Chittur, Mannarkkad, Ottappalam, Pattambi, etc. — IDs 8407–8440)
- `https://ahd.kerala.gov.in/en/category/institutions/` (4 distinct state institutions — IDs 8713, 8726, 8733, 8741)
- `https://www.emri.in/1962-animal-mobile-medical-ambulance-2/` (3 distinct state 1962 ambulance call centers — IDs 7577, 7578, 7585)

### Shared Departmental Emails (Do Not Merge)
- `ahd@kerala.gov.in` (2 listings: Central Kerala AHD offices)
- `vdmndcde.ahd@kerala.gov.in` / `vdvechu.ahd@kerala.gov.in` (Kerala veterinary dispensaries)
- `*ddahvs@gmail.com` / `*noahvs@gmail.com` (10 Karnataka Animal Husbandry & Veterinary Services district deputy director offices: Bagalkot, Belagavi, Raichur, Gadag, Koppal, Mandya, Shimoga, Tumakuru, Udupi)
- `ddchnagar@gmail.com` (Post 7776 AHVS Chamarajnagar & Post 15900 District SPCA Chamarajnagar)

---

## 3. Category 2: True NGO Duplicate Candidates (Merge & Redirect Queue)

These listings represent identical rescue organisations or shelters created across different data import phases. 
Per **Rule 5**: Never delete listings without asking. The procedure for approved items is:
1. Retain Canonical / Primary Listing (older ID, official AWBI code / Darpan ID, clean slug).
2. Copy missing phones, address details, or registration data to the primary listing.
3. Unpublish secondary listing to `draft` (never hard delete).
4. Add 301 redirect from secondary slug to primary slug.

### Top Duplicate NGO Clusters

| Primary / Canonical (Keep) | Secondary / Duplicate (Unpublish & Redirect) | Shared Attribute | Cause / Analysis |
|---|---|---|---|
| **#1192: Bombay SPCA**<br>`bombay-spca`<br>AWBI: `MH002/1966` | **#18384**: Bombay Society For Prevention Of Cruelty To Animals (`bombay-society-for-prevention-of-cruelty-to-animals`)<br>**#18391**: The Bai Sakarbai Dinshaw Petit Hospital For Animals (`the-bai-sakarbai-dinshaw-petit-hospital-for-animals`) | Website: `bombayspca.org` | #1192 is the canonical AWBI recognition; #18384 & #18391 are AWO/Google Place imports for the same Parel hospital campus. |
| **#9048: Aman's Animal Rehabilitation Foundation (AARF)**<br>`amans-animal-rehabilitation-foundation-juchandra` | **#18404**: Aman's Animal Rehabilitation Foundation (AARF) (`amans-animal-rehabilitation-foundation-aarf`)<br>**#18102**: AARF Animal Shelter (`aarf-animal-shelter`) | Website: `amansanimalshelter.wixsite.com/website`<br>Email: `strayanimalsworld1212@gmail.com` | Three records for the same Palghar/Juchandra shelter. Merge phones into #9048. |
| **#1417: YODA (Youth Organization in Defence of Animals)**<br>`yoda-youth-organization-in-defence-of-animals`<br>AWBI: `MH230/2023` | **#18393**: Yoda Rehabilitation Center For Animals (`yoda-rehabilitation-center-for-animals`)<br>**#18095**: YODA (`yoda`) | Website: `yoda.co.in`<br>Email: `contact@yoda.co.in` | #1417 is the official AWBI recognised body in Khar/Bandra; #18393 & #18095 are secondary scraped profiles. *(Note: #1325 People In Defence of Animals is distinct)*. |
| **#1090: Animal Care Trust**<br>`animal-care-trust-4`<br>AWBI: `KA021/2000` | **#17995**: Animal Care Trust (Mangalore) (`animal-care-trust-mangalore`)<br>**#18031**: Vatsalya Animal Care Trust (`vatsalya-animal-care-trust`) | Website: `animalcaretrustindia.org`<br>Email: `actmangalore@gmail.com` | Same Mangaluru organisation; #1090 has registration `094630201` and AWBI code. |
| **#1761: Shri Vijayanand Surishwar Jain Sewa Trust**<br>`shri-vijayanand-surishwar-jain-sewa-trust`<br>AWBI: `ND040/2003` | **#16469**: Charity Birds Hospital (Jain Lal Mandir) (`charity-birds-hospital-jain-lal-mandir`)<br>**#16470**: Charitable Birds Hospital, Navin Shahdara (`charitable-birds-hospital-navin-shahdara`) | Website: `charitablebirdshospital.com`<br>Email: `svsjst@gmail.com` | The managing trust is #1761; #16469 is the Chandni Chowk hospital; #16470 is the Shahdara branch. |
| **#529: Nature Club**<br>`nature-club`<br>AWBI: `GJ136/2002` | **#18364**: Nature Club (Ncseere) (`nature-club-ncseere`)<br>**#17931**: Nature Club (Ncseerc) (`nature-club-ncseerc`) | Website: `natureclubsurat.org`<br>Email: `natureclubsurat81@gmail.com` | Surat emergency rescue team; #529 is canonical AWBI body. |
| **#1775: Dhyan Foundation**<br>`dhyan-foundation`<br>AWBI: `ND054/2019` | **#18357**: Dhyan Foundation(Mainly For Cattles) (`dhyan-foundationmainly-for-cattles`) | Website: `dhyanfoundation.com`<br>Email: `info@dhyanfoundation.com` | Same foundation head office; #1775 is canonical. |
| **#18280: Achal Welfare Foundation**<br>`achal-welfare-foundation` | **#9042**: Achal Welfare Foundation - (Ghaziabad) (`achal-welfare-foundation-ghaziabad`) | Website: `achalwf.org.in`<br>Email: `achal.welfare.foundation@gmail.com` | #18280 has clean slug; merge CIN `U80900UP2021NPL152691` from #9042. |
| **#18155: FureverFriends Foundation**<br>`fureverfriends-foundation` | **#9133**: Furever Friends (`furever-friends`) | Website: `fureverfriendsfoundation.in`<br>Email: `navita.ahuja@gmail.com` | Merge RoC Chandigarh incorporation notes from #9133 into #18155. |
| **#9060: Animal Rescue And Care-Kolkata**<br>`animal-rescue-and-care-kolkata` | **#25753**: Animal Rescue (`animal-rescue`) | Website: `arckolkata.org`<br>Email: `arckolkata63@gmail.com` | Same Kolkata rescue; registration `160700034/2016`. |
| **#18328: Badu Reptile Conservation Society**<br>`badu-reptile-conservation-society` | **#18441**: Badu Reptile Conservation Society - Wild Animals (`badu-reptile-conservation-society-wild-animals`) | Website: `badureptile.wordpress.com`<br>Email: `badureptiles@gmail.com` | Merge society registration `S/1L/65279` from #18441 into #18328. |
| **#1220: Blue Cross Society of Pune**<br>`blue-cross-society-of-pune`<br>AWBI: `MH031/1995` | **#18406**: Blue Cross Hospital (`blue-cross-hospital`) | Website: `bcspune.org`<br>Email: `info@bcspune.org` | #1220 is canonical AWBI society. |
| **#9084: Bezubaan Public Welfare Trust**<br>`bezubaan-public-welfare-trust` | **#18203**: Bezubaan welfare (`bezubaan-welfare`) | Website: `bezubaantrust.com` | Same trust; #9084 has complete title and contact info. |
| **#9246: PFA Wildlife Rescue & Conservation Centre**<br>`pfa-wildlife-rescue-conservation-centre` | **#9550**: People For Animals (`people-for-animals-20`) | Website: `pfawildlifehospital.org`<br>Email: `info@peopleforanimalsbangalore.org` | Both point to the Bangalore wildlife hospital; #9246 has specific name and phone. |
| **#1415: The Feline Foundation**<br>`the-feline-foundation` | **#18396**: The Feline Foundation (`the-feline-foundation-2`) | Website: `thefelinefoundation.com`<br>Email: `info@thefelinefoundation.com` | Same Mumbai cat rescue; #1415 is original with canonical slug. |
| **#9360: We Save Animals (WSA)**<br>`we-save-animals-wsa` | **#18025**: We Save Animals(Wsa India) (`we-save-animalswsa-india`) | Website: `wesaveanimalsindia.com` | Same NGO in Mohali / Punjab. |
| **#9067: Animal Welfare Society - AWS**<br>`animal-welfare-society-aws` | **#9066**: ANIMAL WELFARE SOCIETY (`animal-welfare-society-7`) | Website: `animalwelfaresociety.in`<br>Email: `info@animalwelfaresociety.in` | Same NGO; #9067 has descriptive name and proper casing. |
| **#18059: My pal club**<br>`my-pal-club` | **#18416**: My Pal Club(Closed On Wednesdays...) (`my-pal-clubclosed-on-wednesdays...`)<br>**#9209**: Mypalclub Foundation (`mypalclub-foundation`) | Website: `mypalclub.org`<br>Email: `mypalclubfoundation@gmail.com` | #18059 has clean slug; merge data from #9209. |
| **#9257: Raksha Make A Difference**<br>`raksha-make-a-difference` | **#18314**: Raksha Make a Difference, Prayagraj (`raksha-make-a-difference-prayagraj`) | Website: `rakshamad.org` | Same Prayagraj organisation. |
| **#16443: World For All Animal Care and Adoptions**<br>`world-for-all-animal-care-and-adoptions` | **#18077**: World For All - animal rescue (`world-for-all-animal-rescue`) | Website: `worldforall.co` | Same Mumbai organisation; #16443 has full registration data. |

---

## 4. Proposed Execution Strategy

To strictly adhere to **Rule 5** ("Never delete user data, listings or posts without asking. Unpublish to draft instead"):

1. **Step 1 (Safety First)**: The 58 government listings are preserved and permanently marked with an exclusion tag or kept in their respective districts.
2. **Step 2 (Metadata Enrichment)**: For each duplicate pair/cluster above, run a non-destructive script that copies any missing phones, registration notes, or Darpan IDs into the canonical primary post.
3. **Step 3 (Status Transition)**: Set the secondary post status to `draft` (or `trash`).
4. **Step 4 (Search Integrity)**: Add Rank Math 301 redirects from secondary URL slug to canonical primary URL slug, and purge LiteSpeed cache.
