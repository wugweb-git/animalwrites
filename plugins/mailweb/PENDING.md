# MailWeb: Pending & Roadmap

## Active Scope & Responsibilities
The email and delivery management plugin owns:
- Transactional and outbound email routing.
- Hostinger Mail API PHP SDK (`hostinger/mail-api-php-sdk`) integration.
- SMTP / IMAP / POP3 protocol configurations.
- Real-time DKIM (`hostingermail1._domainkey`) and SPF DNS deliverability verification card.
- FluentCRM campaign sending safety check integration.

---

## Open Tasks & Enhancements

1. **Hostinger Mail API Transport**:
   - Ensure seamless fallback from API SDK to native `wp_mail` / SMTP if API key is invalid or quota exceeded.
   - Rate limiting and retry queue in database for failed outbound emails.

2. **DNS & Deliverability Telemetry**:
   - Background cron checking DKIM and SPF records every 24 hours, surfacing admin warning if DNS records drop.
   - Test email sending utility directly from the `MailWeb > Settings` admin screen.

3. **Multi-Tenant Mail Sending**:
   - Allow tenants to define separate sender profiles (e.g. `alerts@tenant.com` vs `newsletter@tenant.com`).
