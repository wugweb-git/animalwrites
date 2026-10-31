=== Mailweb ===
Contributors: wugweb
Tags: email, inbox, hostinger, smtp, signature
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 3.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hostinger Mail inside WordPress: read, reply and send from your mailboxes, plus SMTP sending with a daily limit and queue.

== Description ==

Mailweb puts a site's Hostinger mailboxes inside WordPress, so the team reads and answers mail where they already work. Everything is read from and written to the mailboxes through the Hostinger Mail API, so webmail and WordPress always show the same messages, folders and read state. Nothing from the mailboxes is copied into WordPress.

= Inbox =

* Every mailbox the API token can see, with its aliases, in one list or one at a time ("All mailboxes" mixes them, newest first).
* Folders: Inbox, Sent, Archive, Spam and Trash, with unread counts. A site can add a Website forms folder for its own form messages (filter `mailweb_forms_source`).
* WordPress list table: checkboxes, bulk actions (mark read or unread, archive, spam, move to Trash), row actions, search and pagination.
* Reading view in the edit-screen layout. Messages show in a sandboxed frame with scripts removed and pictures from the internet hidden until you choose to show them. Attachments download from the mailbox.
* Reply, Reply all, Forward and New message, sent from the mailbox itself: replies keep the thread, the original is marked answered, and a copy is kept in Sent.
* Delete only moves to Trash; nothing is removed for good.
* Unread badge on the Mail menu and a Mail dashboard widget.

= Profiles and signatures =

* Each mailbox and alias has a display name, a signature and a picture.
* The signature is added to replies and to the emails the site sends, laid out in a simple card.
* A site can add its own block under the signature (a banner, a notice) with the `mailweb_after_signature` filter.

= Sending =

* Sends the site's own email (notices, confirmations, newsletters) through its SMTP mailbox, with the sender name and a default Reply-To. Until a password is saved, WordPress sends the default way. A test email can be sent from the settings.
* A daily sending limit with a queue, for mailboxes that allow a fixed number of emails a day: priorities, retries, a pause when the mail server refuses mail, and pacing for FluentCRM campaigns.
* All mail settings in one place: Mail, then Settings.

= FluentCRM =

* Incoming email from people is noted on their FluentCRM contact (found, or made as "transactional" so they get no marketing), with a tag you choose, the full text and an "Open in Mail" link; replies sent from Mail are noted as "Reply sent". Choose the mailboxes in Mail → Settings.

= Who can use it =

Administrators and editors. Every action checks a nonce and the user's capability, sending is limited to 30 messages an hour, and no message text is written to any log.

= Secrets =

The Mail API token, webhook secrets and SMTP password can be set in `wp-config.php` (`MAILWEB_API_TOKEN`, `MAILWEB_SECRET_<MAILBOX>`, `MAILWEB_SMTP_PASSWORD`). Values saved on the settings screen are stored encrypted with a key made from the site's salts; if the salts change, enter them again.

== Installation ==

1. Upload the `mailweb` folder to `/wp-content/plugins/` and activate Mailweb.
2. In hPanel, create a Mail API token for the domain's mailboxes. Save it in Mail → Settings, or add it to `wp-config.php`: `define( 'MAILWEB_API_TOKEN', '...' );`
3. In Mail → Settings, choose which mailboxes may send and the default From, add aliases, and fill in the profiles.
4. Optional: in hPanel, add a webhook for "message.received" on each mailbox pointing to `/wp-json/mailweb/v1/mail-in`, and save each webhook's secret in Mail → Settings, so unread counts refresh as soon as mail arrives.
5. Optional: set the SMTP mailbox and the daily limit in Mail → Settings → Site sending.

== Frequently Asked Questions ==

= Does it store my email in WordPress? =

No. Messages are read live from the mailboxes. Only send times are kept, for the hourly limit, and the site's own outgoing emails wait in a queue table when the daily limit is reached.

= Can it send as an alias? =

No. The Hostinger Mail API sends only as a real mailbox, so choose a mailbox in the From field.

= What happens on uninstall? =

Nothing is deleted. Settings and earlier data stay in place.

== Changelog ==

= 3.1.1 =
* Opening a message marks it read, and replying to or forwarding a message marks the original read. The Mail API does not do this when the text is fetched.

= 3.0.0 =
* Now a standalone product: slug, folder and text domain `mailweb`; functions, options, hooks and the queue table renamed to `mailweb_`.
* Mailboxes come only from the API token; choose which may send and the default From in Mail → Settings. Webhook secrets per mailbox.
* Webhook address is now `/wp-json/mailweb/v1/mail-in`.
* Secrets saved on the settings screen are encrypted.
* Uploaded attachments are checked against the 10 MB total before they are read.
* FluentCRM feed settings: on/off, tag and mailboxes.
* Website forms folder and the block under signatures are now extension points (`mailweb_forms_source`, `mailweb_after_signature`).
* All text translatable (text domain `mailweb`, `languages/mailweb.pot`).
* Removed: copying mail into WordPress posts, and the old reply screen (both had been switched off).

= 2.9.3 =
* FluentCRM's dashboard no longer shows the "Set Up FluentSMTP" card; Mailweb sends the site's email.

= 2.9.2 =
* Removed the 2.9.1 change (no second signature on emails with their own); back to 2.9.0 behaviour.

= 2.9.0 =
* FluentCRM sync for mail is back: each incoming email from a person finds or creates their contact (tag "Emailed us") with a note holding the full text and an "Open in Mail" link; replies sent from Mail are noted too. Checked every 5 minutes and when the webhook fires; `wp mailweb crm-sync --all` catches up.

= 2.8.0 =
* Renamed from Wug Mail to Mailweb. The folder, settings, webhook address and everything saved stay the same.

= 2.7.0 =
* The SMTP connection (mailbox, password, server, port, sender name, default Reply-To) moved here from Kits Core: all of a site's mail is now in Mailweb. Same saved settings; nothing to re-enter.

= 2.6.0 =
* Plugin Check fixes: output escaped where it is printed, request values sanitised, nonce checks where uploads are read, translator comment for the unread count.
* Plugin information and this readme.

= 2.5.2 =
* Countdown campaign strip under signatures, with a line on what the campaign is about.

= 2.4.0 =
* Campaign strip under signatures.

= 2.3.0 =
* Signatures keep their line breaks. Replies go from a chosen mailbox.

= 2.2.0 =
* Inbox redesigned with the WordPress list table, an "All mailboxes" view and edit-screen layouts.

= 2.0.0 =
* A real inbox in WordPress through the Hostinger Mail API.

= 1.0.0 =
* Incoming mail, Mail menu with unread count, dashboard widget.
