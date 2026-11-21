# NGO Darpan (now "NPO DARPAN") portal, India, as of 18 Nov 2026

Method note: the live portal https://ngodarpan.gov.in/ was fetched on 18 Nov 2026. It is a JavaScript single-page app (page title "NPODARPAN"), so a plain fetch returns almost no text. The facts marked "live portal, 18 Nov 2026" below were read from the portal's own published files: its English UI text file https://ngodarpan.gov.in/assets/i18n/en.json and its JS bundles (route list, form labels, validation rules, instruction text), all fetched on 18 Nov 2026. These are the same strings the site shows to users, but nobody looked at the rendered page in a browser. The NIC Informatics news page (Oct 2025) could not be fetched (TLS certificate error), so only its search-result title is cited.

## 1. What NGO Darpan is, who runs it, official URL, purpose

### Takeaway
The portal is still at ngodarpan.gov.in, run by NITI Aayog (Voluntary Action Cell) and built and hosted by NIC. It has been rebuilt and renamed on screen to "NPO DARPAN". It issues a "DARPAN Unique ID" that is required for central and state government grants. It is also linked to bank KYC (PMLA Rules 2023), FCRA registration/renewal and 80G.

### Cited Findings
- The live site at https://ngodarpan.gov.in/ returns HTTP 200 with the page title "NPODARPAN". Header logos are NITI Aayog (niti.svg) and the Darpan logo. The home hero reads "A Digital Platform To Obtain DARPAN Unique ID". — [Live portal, 18 Nov 2026](https://ngodarpan.gov.in/); [en.json](https://ngodarpan.gov.in/assets/i18n/en.json)
- About text, verbatim from the portal: "NPO DARPAN Portal is a platform, wherein Non-Profit Organizations (NPOs), can register themselves to obtain a Unique Identity Number. This number is mandatory for all NPOs who are seeking grants from the Ministries/Departments of Government of India, All the States/UT." The same text says that under the PMLA (Maintenance of Records) Amendment Rules, 2023, "all Financial Institutes have been mandated to ensure NPO DARPAN Unique ID for NPOs for opening and operating accounts in any Bank". It adds that the DARPAN Unique ID "is also required for registration/renewal of Foreign Contribution Regulation Act (FCRA), seeking tax exemptions under 80-G of Income Tax." — [en.json, about.para, 18 Nov 2026](https://ngodarpan.gov.in/assets/i18n/en.json)
- Home-page marquee: "NITI Aayog does not issue any Physical Darpan certificate, it only provides Darpan registration ID to NPOs on the basis of self-declared affidavit." — [en.json, home.marquee, 18 Nov 2026](https://ngodarpan.gov.in/assets/i18n/en.json)
- Home-page advisory: NPOs may not use the NITI Aayog name or logo or the National Emblem. A Darpan ID "does not confer any right, endorsement, affiliation, or recognition by NITI Aayog". Misuse can lead to the ID being blacklisted or cancelled. — [en.json, home.advisory-content, 18 Nov 2026](https://ngodarpan.gov.in/assets/i18n/en.json)
- The signup affidavit states that registration "is not a proof/verification about the authenticity/legality of the organisation". — Live portal JS bundle (affidavit text), 18 Nov 2026, https://ngodarpan.gov.in/
- Office address: "Voluntary Action Cell, Room No. 315A, NITI Aayog, Sansad Marg, New Delhi-110001". — [en.json, contact_us.add](https://ngodarpan.gov.in/assets/i18n/en.json); see also [NITI Aayog Voluntary Action Cell](https://www.niti.gov.in/divisions/cell/voluntary-action-cell)
- History: the system started as a Prime Minister's Office initiative. NITI Aayog has maintained it since 1 Jan 2015, and NIC (MeitY) designs and hosts it. — [Vikaspedia, NPO Partnership System portal](https://en.vikaspedia.in/viewcontent/social-welfare/ngo-voluntary-sector-1/npo-partnership-system-portal?lgn=en) (undated); [Ommcom News, 25 Apr 2025](https://ommcomnews.com/odisha-news/darpan-portal-key-to-transparency-accountability-anu-garg/) ("in operation since 2015")
- PMLA link: the PML (Maintenance of Records) Amendment Rules, 2023 were notified on 7 Mar 2023. New Rule 9(9A) requires banks, financial institutions and intermediaries to register their NPO clients on NITI Aayog's DARPAN portal if they are not already registered, and to keep the records for 5 years after the relationship ends. — [Vinod Kothari, Mar 2023](https://vinodkothari.com/2023/03/pml-act-and-rules-recent-changes-may-have-new-compliance-requirements/); [Business Standard, 9 Mar 2023](https://business-standard.com/amp/article/economy-policy/politically-exposed-persons-npos-face-tighter-pmla-regulations-123030901084_1.html)
- FCRA: a CA/legal guide says a Darpan ID is required for FCRA registration. Its URL slug says "darpan-id-is-optional-now-for-ngos", but the article body says the opposite. — [RegistrationWala, 8 Feb 2025](https://www.registrationwala.com/knowledge-base/other-registrations/fcra/darpan-id-is-optional-now-for-ngos)
- State push in 2025: at an Odisha regional workshop, the state's Development Commissioner said no grants should go to NPOs without a Darpan ID. Portal features named there: blacklisting, PFMS integration, and a nodal-officer dashboard for States/UTs. — [Ommcom News, 25 Apr 2025](https://ommcomnews.com/odisha-news/darpan-portal-key-to-transparency-accountability-anu-garg/)
- NIC lists a further regional workshop on the portal in Dehradun in its October 2025 news (title only; the page could not be fetched). — [NIC Informatics, Oct 2025](https://informatics.nic.in/files/websites/october-2025/regional-workshop-on-ngo-darpan-portal-held-in-dehradun-to-strengthen-transparency-and-accountability.php)

### Inferences
- A guide page should say "NGO Darpan (now shown on the portal as NPO DARPAN)". It should not call the Darpan ID a certificate or a government endorsement.
- "Required for 12A" is not stated on the portal. The portal names FCRA and 80G only. Write 80G, and treat 12A only as "asked for in income-tax registration forms", if another source confirms that.

### Gaps
- No PIB release or NITI Aayog notice was found that dates the launch of the redesigned portal. One guide says old profile data "has been ported into the new website" ([ngofeed](https://ngofeed.com/?p=2570), undated).
- The claim that the new Income Tax Act 2025 (in force 1 Apr 2026) needs a Darpan ID appears only in a consultancy blog (incorpx.io) and is unverified.

## 2. Registration steps, documents, fee, time

### Takeaway
On the live portal you go to Login / Signup, then Create an Account. You give the organisation name exactly as on its PAN, the organisation's email and mobile, the NPO PAN, and registration details. You confirm with OTPs on both email and mobile, then log in with the password the system emails you. You then upload documents as PDFs, add office bearers (PAN and Aadhaar are checked online), add a witness, accept a self-declared affidavit, and do a final submit by OTP. The live text says "a minimum of 1 office bearers is required", not the 3 that older guides state.

### Cited Findings (all from the live portal's "Key Instructions for New Sign-Ups, NPO DARPAN Portal" text, JS bundle, 18 Nov 2026, https://ngodarpan.gov.in/ route /create-account, unless marked otherwise)
- Documents, as colour PDF scans of originals:
  - original Registration Certificate and the complete MoA or Trust Deed, showing the Registrar's stamp or e-sign
  - By-laws
  - certified list of present Governing Body members
  - latest resolution copy, if any
  - vernacular documents must have an attested Hindi or English translation merged into the same PDF
  - the upload field allows 2 MB per file (maxFileSize 2*1024*1024)
- PAN of the NPO (verified online). The NPO name must exactly match the name on the NPO's PAN card.
- "PAN and Aadhaar Card Numbers of Chairman/President/Secretary/Treasurer/members or equivalent post ... automatically verified online. A minimum of 1 office bearers is required." Older guides say 3, for example the [SVNIT copy of the old NGO Darpan "How to Signup" annexure](https://www.svnit.ac.in/Unnat_Bharat/PI/pdf/Basic%20Amenities/Schemes%20by%20Government%20of%20India/NGO%20Darpan/Annexure%20II%20NGO_Darpan_How_to_Signup_English.pdf) (undated); this conflicts with the live text.
- The signup email and mobile "should belong to the organisation's". Using another organisation's contact details leads to cancellation without notice, and so does false information.
- Sign-up form, in order:
  - **Basic details:** organisation name as per PAN; email ID (used as Login ID); mobile number (also used as Login ID).
  - **Registration details:** organisation PAN, registration number, whether the registration was renewed (with the latest renewal date), date of registration.
  - **Further details:** NPO type, state and district of registration, registering authority, Act name, number of members.
  - **Confirm and send:** tick to confirm, enter the captcha, then Generate OTP. OTPs go to both mobile and email.
  - **Finish:** enter both OTPs and Submit.
- After sign-up, "you will receive a system-generated password on your registered email ID". At first login the system asks you to set a new password.
- OTP rules from the form code:
  - 6-digit OTP, valid 15 minutes
  - up to 2 resends
  - the session expires after 30 minutes
- After login the profile has parts for members, work area/activities and documents. A "Witness Details" step follows: the witness must be "an independent individual not being any office bearer or related to any office bearer". The NPO then accepts a self-declared affidavit (downloadable as PDF) and does a final submit by OTP. Witness and final submit are only enabled once the profile, member and work-area parts are complete. — Live portal JS bundle, 18 Nov 2026
- Aadhaar consent text on the portal: Aadhaar is collected under the Aadhaar Act 2016 for authentication, "for the purpose of obtaining a DARPAN ID". — Live portal JS bundle, 18 Nov 2026
- Home page "How to Register for DARPAN Unique ID" has 4 steps: Create an Account, Upload Documents, Review and Submit, Receive Unique ID. — [en.json, home.how etc.](https://ngodarpan.gov.in/assets/i18n/en.json)
- NPO types on the home summary: Trust, Society, Section 8 Company. — [en.json, home.table](https://ngodarpan.gov.in/assets/i18n/en.json)

### Inferences
- No fee or payment step appears anywhere in the live portal's text or forms. The government process looks free; the fees in consultancy guides are their service charges.
- Fact-check point: replace "PAN/Aadhaar of 3 office bearers" with "PAN and Aadhaar of office bearers (the portal now asks for at least 1)".

### Gaps
- The portal states no official processing time. Secondary guides give varying figures; none was verified, so leave time out or say "varies".
- "Free" was not seen as an explicit statement on the live portal. It is inferred from the absence of any fee step.

## 3. Searching an NGO by name, state or sector; has the portal moved?

### Takeaway
The domain is the same, but the portal was rebuilt. The old menu paths ("NGO by State", "Search Your NGO", URLs like ngodarpan.gov.in/index.php/...) are no longer in the app. Search now sits at header menu **NPO Directory**, which opens the "Search NPO" page at https://ngodarpan.gov.in/search-ngo. A captcha is required.

### Cited Findings
- Header menu on the live portal:
  - Home
  - About Us > About Darpan
  - NPO Directory (/search-ngo)
  - Grant-in-aid Portals
  - Blacklisted NPOs (/blacklisted)
  - Resources > Circulars, FAQ's
  - Contact Us
  - Login / Signup button
  - — [Live portal JS bundle + en.json, 18 Nov 2026](https://ngodarpan.gov.in/assets/i18n/en.json)
- "Search NPO" page fields (labels verbatim):
  - "NPO Registered State"
  - "NPO Registered District"
  - "Sectors"
  - "NPO Type"
  - "Search by NPO Name"
  - "Search by DARPAN ID"
  - "Search by NPO PAN Number"
  - "Captcha" (required)
  - "Search" button
  - Result columns: "Name of NPO", "Registration No, District (State)", "Address", "Sectors working in".
  - — [en.json, search_ngo.*, 18 Nov 2026](https://ngodarpan.gov.in/assets/i18n/en.json); captcha required per JS form validation
- A separate "Blacklisted NPOs" page can be searched by NPO name, DARPAN ID or PAN. — [en.json, blacklisted_npo.*](https://ngodarpan.gov.in/assets/i18n/en.json)
- The home page shows a state map and table of "Total Darpan ID", "Active Darpan ID" and "Blacklisted Darpan ID". — [en.json, home.*](https://ngodarpan.gov.in/assets/i18n/en.json)
- The "Grant-in-aid Portals" page links to ministry grant portals, for example grants-msje.gov.in and ngo.tribal.gov.in. — Live portal JS bundle, 18 Nov 2026
- Live app routes: about-us, search-ngo, blacklisted, grant-in-aid-portal, circulars, faq, guidelines, contact-us, login, create-account, forgot/:type, account-recovery, reset-password. There are no index.php routes. — Live portal JS bundle, 18 Nov 2026
- Old-style URLs still appear in search results (for example ngodarpan.gov.in/index.php/home/about and /index.php/home/faq). — WebSearch results, 18 Nov 2026

### Inferences
- The guide should give the path "ngodarpan.gov.in > NPO Directory > choose state, district, sector or NPO type, or type a name, Darpan ID or PAN > enter captcha > Search". Drop any "NGO by State" or "Search Your NGO" wording and any index.php links. Old links probably no longer resolve in the new app; this was not tested.
- No merger into another portal or domain change was found for 2024–2026.

### Gaps
- No official date for the redesign was found.

## 4. Login, forgot password, Darpan ID format and verification, updating details, certificate

### Takeaway
Login ID is the organisation's registered email or mobile (password, OTP and 4-digit mPIN login modes exist, all with a captcha). Forgot password and forgot mPIN are reset by OTP. The ID format enforced by the portal is two letters, then a 4-digit year, then 7 digits, for example "XX/2020/0123456". You verify an ID on the NPO Directory with "Search by DARPAN ID". NITI Aayog issues no physical certificate.

### Cited Findings
- Login:
  - button "Login / Signup"
  - Login ID is the email or mobile given at signup
  - the login form has a password mode, an OTP mode and a "4-Digit mPIN" mode, each with a captcha
  - — [en.json header.login_btn](https://ngodarpan.gov.in/assets/i18n/en.json); live portal JS bundle (route /login), 18 Nov 2026
- First login, verbatim: "Enter the old password you received via email or mobile during your first login." Then:
  - set a new password with an uppercase letter, a lowercase letter, a digit and a special character, 8 to 16 characters
  - set a 4-digit mPIN with no repeated digits ("1111 is not allowed")
  - enter the captcha
  - — Live portal JS bundle (route /reset-password), 18 Nov 2026
- Forgot password (route /forgot/:type):
  - enter the Login ID and the captcha
  - Generate OTP (Resend if not received)
  - enter the 6-digit OTP
  - set a new password (same rules) and Submit
  - A parallel "forgot mPIN" flow sets a new 4-digit mPIN by OTP. An "account-recovery" route also exists.
  - — Live portal JS bundle, 18 Nov 2026
- Darpan ID format: the search form only accepts IDs matching `^([A-Za-z]{2})\/\d{4}\/\d{7}$` (2 letters / 4 digits / 7 digits). Results tables label it "DARPAN ID", with "DARPAN ID Status" and "DARPAN Registration Date" in the profile. — Live portal JS bundle, 18 Nov 2026. A vendor blog gives the example "UP/2023/0123456" and reads the parts as state code, year and number ([SurePass blog](https://surepass.io/blog/darpan-id-for-ngos-in-india/), undated).
- Certificate: "NITI Aayog does not issue any Physical Darpan certificate, it only provides Darpan registration ID ... on the basis of self-declared affidavit." The NPO's affidavit can be downloaded as a PDF ("<PAN>_Affidavit.pdf") from the logged-in profile. — [en.json home.marquee](https://ngodarpan.gov.in/assets/i18n/en.json); live portal JS bundle, 18 Nov 2026

### Inferences
- The guide can say "Example format: XX/2020/0123456 (two letters, year, seven digits)". The meaning of the two letters (state code) and four digits (year) comes from a vendor source and the ID's look. The portal itself does not label the parts.
- Updating details: after login, the profile sections (organisation, members, work area, documents) are edited and the affidavit is re-submitted by OTP. This is read from the app code; no official "how to update" text was found.
- Guides that say "download your Darpan certificate" should say instead: "note your Darpan ID from your profile; there is no official physical certificate; you can download the self-declared affidavit PDF".

### Gaps
- No official text was found on how long an ID takes to show as "Active" after final submit.
- No logged-in screens were viewed (no credentials, by design), so exact profile menu labels after login are not confirmed.

## 5. Common errors and support contact

### Takeaway
Support is the NITI Aayog Voluntary Action Cell helpdesk: phone 011-2304 2707 / 2646 / 2145 (working days, 09:00 AM to 05:30 PM), email ngo[at]india[dot]gov[dot]in, plus a Contact Us form. The errors the portal itself shows are about PAN and name mismatch, duplicate contacts, OTP problems and Aadhaar or PAN failing to verify.

### Cited Findings
- Contact Us page:
  - "Helpdesk Centre"
  - Contact Number "011-2304 2707/ 2646/ 2145", "(Will remain operational on all working days from 09:00 AM to 05:30 PM)"
  - Email "ngo[at]india[dot]gov[dot]in"
  - Office address: Voluntary Action Cell, Room No. 315A, NITI Aayog, Sansad Marg, New Delhi-110001
  - an online query form ("Your request has been successfully received")
  - — [en.json contact_us.*](https://ngodarpan.gov.in/assets/i18n/en.json); live portal JS bundle, 18 Nov 2026
- Validation and error states built into signup and search, from the live portal JS bundle (18 Nov 2026):
  - PAN already exists (isPanExist)
  - duplicate contact (isDuplicateContect)
  - invalid registration details (isInvalidReg)
  - PAN not authenticated (isUnAuthPAN)
  - invalid or expired mobile/email OTP (15-minute validity)
  - "Enter a valid Aadhaar Number" / Aadhaar not authenticated
  - "OTP is required and must be 6 digits"
  - "Enter a valid DARPAN ID" / "Enter a valid PAN number" / "Enter a valid NPO Name" in search
  - 2 MB file size limit on uploads
  - limited OTP resends (2) and attempts (5)
- Cancellation risks stated on the portal: using another organisation's email or mobile, false or misleading information, and using the NITI Aayog name, logo or the National Emblem. — Live portal instructions and advisory, 18 Nov 2026

### Inferences
- Practical tips the guide can give:
  - match the NPO name to its PAN exactly
  - use the organisation's own email and mobile
  - keep PDFs under 2 MB
  - check the office bearers' PAN and Aadhaar details before starting
  - finish within the 30-minute session

### Gaps
- The FAQ page content is loaded from the server at run time and was not in the static files, so the official FAQ answers were not read.
- No public figure was found for helpdesk response times.
