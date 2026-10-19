=== Mailweb ===
Contributors: wugweb
Tags: email, inbox, hostinger, signature, contact form
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One WordPress inbox for every Hostinger mailbox and the website forms, kept in step with webmail through the Hostinger Mail API.

== Description ==

Mailweb puts a site's Hostinger mailboxes inside WordPress, so the team reads and answers mail where they already work. Everything is read from and written to the mailboxes through the Hostinger Mail API, so webmail and WordPress always show the same messages, folders and read state. Nothing from the mailboxes is copied into WordPress.

= Inbox =

* Every mailbox the API token can see, with its aliases, in one list or one at a time ("All mailboxes" mixes them, newest first).
* Folders: Inbox, Sent, Archive, Spam and Trash, with unread counts, plus a Website forms folder for messages sent through the site's contact form.
* WordPress list table: checkboxes, bulk actions (mark read or unread, archive, spam, move to Trash), row actions, search and pagination.
* Reading view in the edit-screen layout. Messages show in a sandboxed frame with scripts removed and pictures from the internet hidden until you choose to show them. Attachments download from the mailbox.
* Reply, Reply all, Forward and New message, sent from the mailbox itself: replies keep the thread, the original is marked answered, and a copy is kept in Sent.
* Delete only moves to Trash; nothing is removed for good.
* Unread badge on the Mail menu and a Mail dashboard widget.

= Profiles, signatures and campaigns =

* Each mailbox and alias has a display name, a signature and a picture.
* The signature is added to replies and to emails the site sends to people outside.
* While a campaign is open, a countdown strip in the campaign's colour (days left, what the campaign is about, a button to its page) appears under the signature, and disappears when it closes.

= Sending =

* Sends the site's own email (notices, confirmations, newsletters) through its SMTP mailbox, with the sender name and a default Reply-To. Until a password is saved, WordPress sends the default way. A test email can be sent from the settings.
* A daily sending limit with a queue, for mailboxes that allow a fixed number of emails a day: priorities, retries, a pause when the mail server refuses mail, and pacing for FluentCRM campaigns.
* All mail settings in one place: Mail, then Settings.

= FluentCRM =

* Incoming email from people is noted on their FluentCRM contact (found or created, tag "Emailed us"), with the full text and an "Open in Mail" link; replies sent from Mail are noted as "Reply sent".

= Who can use it =

Administrators and editors. Every action checks a nonce and the user's capability, sending is limited to 30 messages an hour, and no message text is written to any log.

== Installation ==

1. Upload the `aw-mail` folder to `/wp-content/plugins/` and activate Mailweb.
2. In hPanel, create a Mail API token for the domain's mailboxes and add it to `wp-config.php`: `define( 'AW_HOSTINGER_MAIL_TOKEN', '...' );`
3. Open Mail, then Settings, to check the mailboxes and aliases and to fill in the profiles.
4. Optional: add a webhook in hPanel for "message.received" pointing to `/wp-json/aw/v1/mail-in` so unread counts refresh as soon as mail arrives.

== Frequently Asked Questions ==

= Does it store my email in WordPress? =

No. Messages are read live from the mailboxes. Only send times are kept, for the hourly limit.

= Can it send as an alias? =

No. The Hostinger Mail API sends only as a real mailbox, so choose a mailbox in the From field.

= What happens on uninstall? =

Nothing is deleted. Settings and earlier data stay in place.

== Changelog ==

= 2.9.0 =
* FluentCRM sync for mail is back: each incoming email from a person finds or creates their contact (tag "Emailed us") with a note holding the full text and an "Open in Mail" link; replies sent from Mail are noted too. Checked every 5 minutes and when the webhook fires; `wp aw-mail crm-sync --all` catches up.

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
