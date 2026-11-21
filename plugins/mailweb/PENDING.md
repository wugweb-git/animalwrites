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

4. **FluentSMTP Feature Consumption (Outbound Logging & Delivery Diagnostics)**:
   - *Status (22 Nov 2026):* Added `mailweb_has_external_smtp()` deconfliction guard in `includes/smtp.php` so MailWeb does not override PHPMailer when FluentSMTP, Post SMTP, or WP Mail SMTP is active.
   - *Open:* Build native outbound delivery log table (`mailweb_email_logs`) capturing all `wp_mail` events (To, Subject, Status, Error, Timestamp).
   - *Open:* Build Outbound Delivery UI card on Dashboard with delivery rate metrics (Sent, Delivered, Failed) and 1-click Resend action.
   - *Open:* Expand test email runner to send to any custom email recipient with raw SMTP handshake diagnostics.
