<?php
/**
 * MailWeb mail-provider seam.
 *
 * The inbox and the CRM feed talk to a *provider adapter* for their transport, not to a hardcoded service, so other
 * mailbox backends can be added later without touching the call sites (owner, 17 Nov 2026: "make it independent — can
 * instead map other emails as well"). Today the only adapter is the Hostinger Mail API, which is the default; a future
 * generic IMAP adapter implements the same interface and is swapped in with the `mailweb_provider` filter.
 *
 * This file introduces the seam only; it does not change behaviour. `mailweb_call()` (inbox.php) and `mailweb_api()`
 * (mail-in.php) now delegate here; the Hostinger adapter's request body is the transport code moved verbatim from
 * `mailweb_call()`, so the HTTP it makes is identical.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A MailWeb mail provider: the transport the inbox uses to reach a mailbox backend.
 *
 * The current interface is the low-level request the Hostinger Mail API needs. A later generic-IMAP adapter will add
 * higher-level methods (list folders, list/read messages, flags, move, send, attachments) behind the same resolver;
 * when it does, the call sites in inbox.php move onto those methods. For now `request()` is the single seam.
 */
interface Mailweb_Provider {

	/**
	 * One transport request to the mailbox backend.
	 *
	 * @param string     $method HTTP method (GET, POST, PATCH, DELETE).
	 * @param string     $path   Path after the backend's base URL.
	 * @param array|null $body   Optional JSON body.
	 * @param bool       $raw    Return the raw body (attachments) instead of decoded JSON.
	 * @param int        $timeout Request timeout in seconds; 0 uses the adapter's default.
	 * @return array{0:int,1:mixed,2:array} [status code, data (decoded JSON or raw body), headers].
	 */
	public function request( string $method, string $path, $body = null, bool $raw = false, int $timeout = 0 ): array;
}

/**
 * The Hostinger Mail API adapter — the default, and today the only, provider.
 *
 * Supports both direct REST transport (zero dependency, WordPress native) and the official
 * Hostinger Mail API PHP SDK (hostinger/mail-api-php-sdk: Hostinger\MailApi\Api\SendApi) when installed in vendor/.
 * The base URL is `MAILWEB_API_HOST`, overridable with the `mailweb_api_base` filter.
 */
final class Mailweb_Hostinger_Provider implements Mailweb_Provider {

	/**
	 * Returns whether the Hostinger Mail API PHP SDK is loaded.
	 */
	public static function has_sdk(): bool {
		return class_exists( 'Hostinger\\MailApi\\Configuration' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function request( string $method, string $path, $body = null, bool $raw = false, int $timeout = 0 ): array {
		$token = function_exists( 'mailweb_setting' ) ? mailweb_setting( 'api_token' ) : '';
		if ( '' === $token ) {
			return array( 0, null, array() );
		}
		$args = array(
			'method'  => $method,
			'timeout' => $timeout > 0 ? $timeout : ( $raw ? 60 : 20 ),
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => $raw ? '*/*' : 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$base = (string) apply_filters( 'mailweb_api_base', defined( 'MAILWEB_API_HOST' ) ? MAILWEB_API_HOST : 'https://api.mail.hostinger.com' );
		$r    = wp_remote_request( $base . $path, $args );
		if ( is_wp_error( $r ) ) {
			return array( 0, null, array() );
		}
		$b = wp_remote_retrieve_body( $r );
		return array( (int) wp_remote_retrieve_response_code( $r ), $raw ? $b : json_decode( $b, true ), wp_remote_retrieve_headers( $r ) );
	}
}

/**
 * Generic IMAP adapter — SKELETON, not yet implemented (owner, 17 Nov 2026: "make it independent — can instead map
 * other emails as well"). It makes MailWeb read *any* IMAP mailbox, not only the Hostinger Mail API. It is opt-in
 * (returned only when something sets the `mailweb_provider` filter to an instance of this class) and **fail-safe**:
 * every method returns a transport-failure triple `[0, null, []]`, which the inbox already treats as "backend down"
 * (it shows no messages rather than wrong ones) — so selecting it can never surface fabricated data. A one-time admin
 * notice makes clear it is not finished.
 *
 * Implementation spec (for a session that has a live IMAP mailbox + WordPress to verify against — it cannot be
 * verified here, so it is left unimplemented rather than guessed):
 *
 * - Account: IMAP host/port/user/pass from settings (mirror `mailweb_smtp_settings()` in smtp.php — add a
 *   `mailweb_imap` option + `MAILWEB_IMAP_PASSWORD`), with a "Hostinger" preset (imap.hostinger.com:993). Connect with
 *   `ext-imap` where present, else a bundled GPL IMAP client; TLS; never log credentials.
 * - `request($method,$path,$body,$raw)` emulates the Hostinger REST surface the inbox calls, returning the SAME shapes
 *   the call sites parse (so no call site changes):
 *   - `GET /api/v1/mailboxes/{id}/folders` → `['data'=>[{path:'INBOX'|'INBOX.Sent'|…, specialUse:'\\Sent'|…,
 *     unreadCount:int, messageCount:int}]]` (map IMAP folders + \Sent/\Junk/\Trash/\Archive special-use).
 *   - `GET …/folders/{f}/messages?page&perPage&sort=-date` → `['data'=>[{uid, from:{name,address},
 *     to:[{name,address}], subject, date(RFC), unseen:bool, attachments:bool|[], flags:['\\Answered','$Forwarded',…]}],
 *     'pagination'=>['total'=>int]]`.
 *   - `POST …/messages/search?…` {text} → same list shape, server-side IMAP SEARCH.
 *   - `GET …/messages/{uid}` → `['data'=>{…, attachments:[{id,filename,contentType,contentId,sizeBytes}]}]`.
 *   - `GET …/messages/{uid}/text` → `['data'=>{html, text}]`.
 *   - `GET …/messages/{uid}/attachments/{id}` (raw) → the decoded bytes as the body.
 *   - `PATCH …/messages/{uid}` {addFlags|removeFlags:[...]} → IMAP STORE; return 200.
 *   - `POST …/messages/{uid}/move` {targetFolder} → IMAP MOVE; return 200.
 *   - `POST /api/v1/mailboxes/{id}/send` {…} → send via SMTP (smtp.php already generic) and APPEND to Sent.
 * - Discovery (`mailweb_api`/`mailweb_box_ids` in mail-in.php) becomes "the configured IMAP account(s)".
 */
final class Mailweb_Imap_Provider implements Mailweb_Provider {

	/**
	 * {@inheritDoc} Fail-safe until implemented: a transport-failure triple, plus a one-time admin notice.
	 */
	public function request( string $method, string $path, $body = null, bool $raw = false, int $timeout = 0 ): array {
		if ( is_admin() && ! get_transient( 'mailweb_imap_notice_shown' ) ) {
			set_transient( 'mailweb_imap_notice_shown', 1, HOUR_IN_SECONDS );
			add_action(
				'admin_notices',
				function () {
					wp_admin_notice(
						esc_html__( 'MailWeb: the generic IMAP backend is selected but not yet implemented; no mail will load. Remove the mailweb_provider override to use the default.', 'mailweb' ),
						array( 'type' => 'warning' )
					);
				}
			);
		}
		return array( 0, null, array() );
	}
}

/**
 * The active mail provider. Swap the backend with the `mailweb_provider` filter (return a Mailweb_Provider).
 *
 * @return Mailweb_Provider
 */
function mailweb_provider(): Mailweb_Provider {
	static $provider = null;
	if ( null === $provider ) {
		$provider = apply_filters( 'mailweb_provider', new Mailweb_Hostinger_Provider() );
		if ( ! $provider instanceof Mailweb_Provider ) {
			$provider = new Mailweb_Hostinger_Provider();
		}
	}
	return $provider;
}
