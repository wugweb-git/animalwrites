<?php
/**
 * MailWeb's routes for its new screens (docs/mailweb-plan.md §2–§4), under mailweb/v1, beside the webhook mail-in:
 *
 *   GET  dashboard                  MAILWEB_CAP     the tiles and sections, from the background counts
 *   POST dashboard/count            MAILWEB_CAP     ask for a count now (one background event)
 *   POST sending/resume             manage_options  resume sending after a pause (as today's Resume button)
 *   POST queue/retry                manage_options  held emails get another try (as today's Retry button)
 *   POST queue/clear                manage_options  held emails are dropped (content cleared, log row kept 30 days)
 *   GET  inbox                      MAILWEB_CAP     one page of the list: box, folder, search, page
 *   GET  inbox/message              MAILWEB_CAP     one message (or form message) for the reading pane; opening marks it read
 *   POST inbox/bulk                 MAILWEB_CAP     read, unread, archive, inbox, spam, trash (mailweb_bulk())
 *   GET  settings                   manage_options  the Sending, Mailboxes and aliases, and Profiles groups
 *   POST settings/<group>           manage_options  save the fields sent, only when they change a value; reset: true
 *                                                   puts Sending or Mailboxes back to the registered defaults
 *   GET  services                   manage_options  Mail API, incoming mail per mailbox, site sending, FluentCRM
 *   POST services/mail-api          manage_options  a new Mail API token (empty keeps the saved one)
 *   POST services/webhook/<key>     manage_options  a new webhook secret for one mailbox (empty keeps it)
 *   POST services/smtp              manage_options  the SMTP mailbox (an empty password keeps the saved one)
 *   POST services/fluentcrm         manage_options  the FluentCRM feed
 *
 * Every route checks the capability and, for a signed-in browser, the wp_rest nonce (kits_shell_rest_can()). None of
 * them sends an email. Keys and passwords are only ever written: the screens see "set", "not set" or "set in
 * wp-config".
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'kits_shell_rest_can' ) ) {
			return; // the screens need Kits Core's shell; without it they show a notice.
		}
		$cap   = kits_shell_rest_can( MAILWEB_CAP );
		$admin = kits_shell_rest_can( 'manage_options' );
		$route = function ( string $path, string $method, callable $perm, callable $cb, array $args = array() ) {
			register_rest_route(
				MAILWEB_REST,
				$path,
				array(
					'methods'             => $method,
					'permission_callback' => $perm,
					'callback'            => $cb,
					'args'                => $args,
				)
			);
		};
		$route( '/dashboard', 'GET', $cap, fn() => rest_ensure_response( mailweb_dashboard_payload() ) );
		$route( '/dashboard/count', 'POST', $cap, fn() => rest_ensure_response( array( 'waiting' => mailweb_dashboard_request() ) ) );
		$route(
			'/sending/resume',
			'POST',
			$admin,
			function () {
				mailweb_resume();
				mailweb_dashboard_request();
				return rest_ensure_response( array( 'message' => __( 'Sending resumed. Waiting emails go out on the next run, within the limit.', 'mailweb' ) ) );
			}
		);
		$route(
			'/queue/retry',
			'POST',
			$admin,
			function () {
				mailweb_retry_held();
				mailweb_dashboard_request();
				return rest_ensure_response( array( 'message' => __( 'Held emails will be tried again on the next run, within the limit.', 'mailweb' ) ) );
			}
		);
		$route(
			'/queue/clear',
			'POST',
			$admin,
			function () {
				$n = mailweb_clear_held();
				mailweb_dashboard_request();
				/* translators: %d: number of held emails cleared. */
				return rest_ensure_response( array( 'message' => sprintf( _n( '%d held email cleared.', '%d held emails cleared.', $n, 'mailweb' ), $n ) ) );
			}
		);
		$route(
			'/inbox',
			'GET',
			$cap,
			fn( WP_REST_Request $r ) => rest_ensure_response( mailweb_inbox_payload( mailweb_rest_state( $r ) ) ),
			array(
				'box'    => array(
					'type'    => 'string',
					'default' => 'all',
				),
				'folder' => array(
					'type'    => 'string',
					'default' => 'inbox',
				),
				'search' => array(
					'type'    => 'string',
					'default' => '',
				),
				'page'   => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
			)
		);
		$route(
			'/inbox/message',
			'GET',
			$cap,
			'mailweb_rest_message',
			array(
				'box'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'folder' => array(
					'type'    => 'string',
					'default' => 'inbox',
				),
				'uid'    => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
				'item'   => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
				'images' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$route(
			'/inbox/move',
			'POST',
			$cap,
			'mailweb_rest_move',
			array(
				'folder' => array(
					'type'    => 'string',
					'default' => 'inbox',
				),
				'to'     => array(
					'type'     => 'string',
					'required' => true,
					'pattern'  => '^INBOX(\\.[\\w .-]+)?$',
				),
				'ids'    => array(
					'type'     => 'array',
					'required' => true,
					'minItems' => 1,
					'maxItems' => 100,
					'items'    => array(
						'type'    => 'string',
						'pattern' => '^[a-z0-9_-]+\\|[0-9]+$',
					),
				),
			)
		);
		$route(
			'/inbox/bulk',
			'POST',
			$cap,
			'mailweb_rest_bulk',
			array(
				'action' => array(
					'type'     => 'string',
					'required' => true,
					'enum'     => array( 'read', 'unread', 'archive', 'inbox', 'spam', 'trash' ),
				),
				'folder' => array(
					'type'    => 'string',
					'default' => 'inbox',
				),
				'ids'    => array(
					'type'     => 'array',
					'required' => true,
					'minItems' => 1,
					'maxItems' => 100,
					'items'    => array(
						'type'    => 'string',
						'pattern' => '^[a-z0-9_-]+\|[0-9]+$',
					),
				),
			)
		);
		$route( '/settings', 'GET', $admin, fn() => rest_ensure_response( mailweb_settings_payload() ) );
		$route(
			'/settings/(?P<group>sending|mailboxes|profiles)',
			'POST',
			$admin,
			'mailweb_rest_save_group',
			array(
				'budget'       => array( 'type' => 'integer' ),
				'reserve'      => array( 'type' => 'integer' ),
				'send'         => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'boolean' ),
				),
				'default_from' => array( 'type' => 'string' ),
				'aliases'      => array( 'type' => 'string' ),
				'profiles'     => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'object' ),
				),
				'delete_data'  => array( 'type' => 'boolean' ),
				'reset'        => array(
					'type'    => 'boolean',
					'default' => false,
				),
			)
		);
		$route( '/services', 'GET', $admin, fn() => rest_ensure_response( array( 'services' => mailweb_services() ) ) );
		$route(
			'/services/mail-api',
			'POST',
			$admin,
			fn( WP_REST_Request $r ) => mailweb_rest_save_secret( 'api_token', (string) $r['token'] ),
			array(
				'token' => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$route(
			'/services/webhook/(?P<key>[a-z0-9_-]+)',
			'POST',
			$admin,
			function ( WP_REST_Request $r ) {
				if ( ! isset( mailweb_all_boxes()[ (string) $r['key'] ] ) ) {
					return new WP_Error( 'rest_invalid_param', __( 'No such mailbox.', 'mailweb' ), array( 'status' => 400 ) );
				}
				return mailweb_rest_save_secret( 'secret_' . (string) $r['key'], (string) $r['secret'] );
			},
			array(
				'secret' => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$route(
			'/services/smtp',
			'POST',
			$admin,
			'mailweb_rest_save_smtp',
			array(
				'user' => array( 'type' => 'string' ),
				'name' => array( 'type' => 'string' ),
				'host' => array( 'type' => 'string' ),
				'port' => array( 'type' => array( 'integer', 'string' ) ),
				'pass' => array(
					'type'    => 'string',
					'default' => '',
				),
			)
		);
		$route(
			'/services/fluentcrm',
			'POST',
			$admin,
			'mailweb_rest_save_crm',
			array(
				'on'    => array( 'type' => 'boolean' ),
				'tag'   => array( 'type' => 'string' ),
				'boxes' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			)
		);
	}
);

/**
 * Only the parameters the screen sent, out of a list of names.
 *
 * @param WP_REST_Request $r     Request.
 * @param string[]        $names Parameter names.
 */
function mailweb_rest_sent( WP_REST_Request $r, array $names ): array {
	$out  = array();
	$body = (array) $r->get_json_params() + (array) $r->get_body_params();
	foreach ( $names as $n ) {
		if ( array_key_exists( $n, $body ) && null !== $r[ $n ] ) {
			$out[ $n ] = $r[ $n ];
		}
	}
	return $out;
}

/* ---------- Dashboard ---------- */

/** Stored states the Dashboard shows as they are (option reads, no counting). */
function mailweb_dashboard_live(): array {
	$smtp = mailweb_smtp_settings();
	$log  = array_filter( (array) get_option( 'mailweb_send_log', array() ), fn( $t ) => (int) $t > time() - HOUR_IN_SECONDS );
	return array(
		'paused'    => mailweb_paused(),
		'lastError' => (array) get_option( 'mailweb_last_error', array() ),
		'smtp'      => array(
			'user' => (string) $smtp['user'],
			'host' => (string) $smtp['host'],
			'port' => (string) $smtp['port'],
			'pass' => mailweb_smtp_pass_state(),
		),
		'replies'   => count( $log ),
		'perHour'   => MAILWEB_SEND_HOUR,
		'budget'    => mailweb_cap_settings()['budget'],
		'reserve'   => mailweb_cap_settings()['reserve'],
		'urls'      => array(
			'inbox'   => mailweb_ui_url( 'inbox' ),
			'forms'   => mailweb_ui_url( 'inbox', array( 'folder' => MAILWEB_FORMS ) ),
			'sending' => current_user_can( 'manage_options' ) ? mailweb_ui_url( 'settings' ) . '#sending' : '#queue',
		),
	);
}

/** The Dashboard: tiles and sections from the stored counts (formatting only). */
function mailweb_dashboard_payload(): array {
	$c        = get_transient( MAILWEB_DASH );
	$counting = ! is_array( $c );
	if ( $counting ) {
		mailweb_dashboard_request(); // The first view after a deploy: count in the background.
		$c = array();
	} elseif ( time() - (int) ( $c['at'] ?? 0 ) > MAILWEB_DASH_FRESH ) {
		mailweb_dashboard_request(); // Show what is stored; a fresh count follows in the background.
	}
	$live = mailweb_dashboard_live();
	$when = fn( $t ) => $t ? wp_date( 'j M, g:i a', (int) $t ) : '';
	$log  = (array) get_option( MAILWEB_DASH_LOG, array() );
	$days = array();
	foreach ( $log as $d => $row ) {
		$days[ $d ] = (int) ( $row['sent'] ?? 0 );
	}
	foreach ( (array) ( $c['perDay'] ?? array() ) as $d => $n ) {
		$days[ $d ] = (int) $n; // The queue table is exact for its 30 days.
	}
	ksort( $days );
	$boxes = array();
	foreach ( (array) ( $c['boxes'] ?? array() ) as $key => $b ) {
		if ( empty( $b['ok'] ) ) {
			$status = array( __( 'Not reachable', 'mailweb' ), 'error' );
		} elseif ( ! empty( $b['stale'] ) ) {
			/* translators: %s: time of the last good answer. */
			$status = array( sprintf( __( 'Shown from %s', 'mailweb' ), wp_date( 'g:i a', (int) $b['staleAt'] ) ), 'warn' );
		} elseif ( ! $b['newest'] ) {
			$status = array( __( 'Empty', 'mailweb' ), '' );
		} else {
			$status = array( __( 'OK', 'mailweb' ), '' );
		}
		$boxes[] = array(
			'key'     => (string) $key,
			'address' => (string) $b['address'],
			'aliases' => (array) $b['aliases'],
			'unread'  => $b['unread'],
			'status'  => $status[0],
			'tone'    => $status[1],
			'url'     => mailweb_ui_url( 'inbox', array( 'box' => (string) $key ) ),
			'newest'  => array_map(
				fn( $m ) => array(
					'from'    => $m['from'],
					'subject' => '' !== $m['subject'] ? $m['subject'] : __( '(no subject)', 'mailweb' ),
					'date'    => $m['date'] && strtotime( $m['date'] ) ? wp_date( 'j M Y', (int) strtotime( $m['date'] ) ) : '',
					'unseen'  => $m['unseen'],
				),
				(array) $b['newest']
			),
		);
	}
	$names  = array( __( 'Replies', 'mailweb' ), __( 'Notices', 'mailweb' ), __( 'Sign-up confirmations', 'mailweb' ), __( 'Campaigns', 'mailweb' ) );
	$queued = array_map( 'intval', (array) ( $c['queued'] ?? array( 0, 0, 0, 0 ) ) );
	$last   = get_option( 'mailweb_last' );
	$stats  = (array) ( $c['stats'] ?? array() );
	return array(
		'counting' => $counting,
		'at'       => (int) ( $c['at'] ?? 0 ),
		'atText'   => ! empty( $c['at'] ) ? wp_date( 'j M Y, g:i a', (int) $c['at'] ) : '',
		'tiles'    => mailweb_dashboard_tiles( $c, $live ),
		'log'      => (object) $log,
		'today'    => wp_date( 'Y-m-d' ),
		'days'     => (object) $days,
		'admin'    => current_user_can( 'manage_options' ),
		'boxes'    => $boxes,
		'lastIn'   => is_array( $last ) ? trim( (string) ( $last['at'] ?? '' ) . ' · ' . (string) ( $last['mailbox'] ?? '' ), ' ·' ) : '',
		'queue'    => array(
			'rows'   => array_map( fn( $i ) => array( $names[ $i ], number_format_i18n( $queued[ $i ] ?? 0 ) ), array( 0, 1, 2, 3 ) ),
			'oldest' => $when( $c['oldest'] ?? 0 ),
			'room1'  => (int) ( $c['room1'] ?? 0 ),
			'room3'  => (int) ( $c['room3'] ?? 0 ),
		),
		'sent'     => array(
			'today'   => (int) ( $days[ wp_date( 'Y-m-d' ) ] ?? 0 ),
			'failed'  => (int) ( $c['failed'] ?? 0 ),
			'nextTry' => $when( $c['nextTry'] ?? 0 ),
			'held'    => array_map(
				fn( $r ) => array(
					$when( $r['at'] ),
					$names[ $r['priority'] ] ?? (string) $r['priority'],
					(string) $r['tries'],
					$r['error'],
				),
				(array) ( $c['heldRows'] ?? array() )
			),
			'heldN'   => (int) ( $c['held'] ?? 0 ),
			/* translators: 1: sent at once, 2: queued, 3: sent from the queue, 4: refused by the mail server. */
			'allTime' => sprintf( __( 'All time: %1$s sent at once, %2$s queued, %3$s sent from the queue, %4$s refused.', 'mailweb' ), number_format_i18n( (int) ( $stats['sent_direct'] ?? 0 ) ), number_format_i18n( (int) ( $stats['queued'] ?? 0 ) ), number_format_i18n( (int) ( $stats['sent_from_queue'] ?? 0 ) ), number_format_i18n( (int) ( $stats['rejected'] ?? 0 ) ) ),
		),
		'status'   => array(
			'paused' => $live['paused'] ? array(
				'at'    => $when( $live['paused']['at'] ?? 0 ),
				'error' => (string) ( $live['paused']['error'] ?? '' ),
			) : null,
			'smtp'   => $live['smtp'],
			/* translators: %d: emails per 24 hours kept back from campaigns. */
			'rule'   => sprintf( __( 'Replies go first, then notices, sign-up confirmations and campaigns. Campaigns leave %d of the daily limit for the others.', 'mailweb' ), (int) $live['reserve'] ),
			'pacing' => __( 'FluentCRM sends at most one email a second, and waits while the campaign share is used up.', 'mailweb' ),
		),
		'help'     => array(
			'webhook' => rest_url( MAILWEB_REST . '/mail-in' ),
			'cli'     => 'wp mailweb crm-sync --all',
		),
	);
}

/* ---------- Inbox ---------- */

/**
 * The list's state from a request, checked as the message views check it (mailweb_state()).
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_state( WP_REST_Request $r ): array {
	$boxes  = mailweb_all_boxes();
	$box    = sanitize_key( (string) $r['box'] );
	$folder = sanitize_key( (string) $r['folder'] );
	return array(
		'box'    => isset( $boxes[ $box ] ) ? $box : 'all',
		'folder' => ( MAILWEB_FORMS === $folder && mailweb_forms() ) || isset( mailweb_roles()[ $folder ] ) ? $folder : 'inbox',
		'page'   => max( 1, (int) $r['page'] ),
		'q'      => MAILWEB_FORMS === $folder ? '' : trim( sanitize_text_field( (string) $r['search'] ) ),
	);
}

/**
 * Where a row opens: the message or form item view (it marks a message read when it opens).
 *
 * @param array $item Row from mailweb_list_rows().
 * @param array $s    State.
 */
function mailweb_open_url( array $item, array $s ): string {
	if ( isset( $item['item'] ) ) {
		return add_query_arg(
			array(
				'page'   => 'mailweb',
				'box'    => $s['box'],
				'folder' => MAILWEB_FORMS,
				'item'   => $item['item'],
			),
			admin_url( 'admin.php' )
		);
	}
	return add_query_arg(
		array(
			'page'   => 'mailweb',
			'box'    => $item['box'],
			'folder' => $s['folder'],
			'uid'    => $item['uid'],
			'view'   => $s['box'],
		),
		admin_url( 'admin.php' )
	);
}

/**
 * One page of the Inbox for the screen: rows, views with unread counts, mailboxes, bulk actions.
 *
 * @param array $s State (mailweb_rest_state()).
 */
function mailweb_inbox_payload( array $s ): array {
	$res   = mailweb_list_rows( $s );
	$forms = MAILWEB_FORMS === $s['folder'];
	$rows  = array();
	foreach ( $res['items'] as $item ) {
		$open   = mailweb_open_url( $item, $s );
		$t      = '' !== $item['date'] ? strtotime( $item['date'] ) : false;
		$rows[] = array(
			'id'       => (string) $item['id'],
			'from'     => '' !== $item['from'] ? (string) $item['from'] : __( '(unknown)', 'mailweb' ),
			'email'    => (string) $item['email'] !== (string) $item['from'] ? (string) $item['email'] : '',
			'subject'  => '' !== $item['subject'] ? (string) $item['subject'] : __( '(no subject)', 'mailweb' ),
			'date'     => $t ? wp_date( 'j M Y', $t ) : '',
			'unread'   => (bool) $item['unseen'],
			'files'    => (bool) $item['files'],
			'mailbox'  => isset( $item['mailbox'] ) ? strtok( (string) $item['mailbox'], '@' ) . '@' : '',
			'openUrl'  => $open,
			'replyUrl' => ! $forms || is_email( $item['email'] ) ? add_query_arg( 'compose', 'reply', $open ) : '',
		);
	}
	$views = array();
	foreach ( mailweb_roles() as $role => $v ) {
		$views[] = array(
			'value' => $role,
			'label' => $v[0],
			'count' => 'sent' === $role ? 0 : mailweb_role_unread( $role, $s['box'] ),
		);
	}
	if ( mailweb_forms() ) {
		$views[] = array(
			'value' => MAILWEB_FORMS,
			'label' => (string) mailweb_forms()['label'],
			'count' => mailweb_forms_unread(),
		);
	}
	$boxes = array(
		array(
			'value' => 'all',
			'label' => __( 'All mailboxes', 'mailweb' ),
		),
	);
	foreach ( mailweb_all_boxes() as $k => $a ) {
		$al      = mailweb_aliases_of( $a );
		$boxes[] = array(
			'value' => (string) $k,
			'label' => $a . ( $al ? ' (+ ' . implode( ', ', $al ) . ')' : '' ),
		);
	}
	$bulk = array();
	foreach ( mailweb_bulk_actions( $s['folder'] ) as $value => $label ) {
		$bulk[] = array(
			'value' => $value,
			'label' => $label,
		);
	}
	return array(
		'state'   => array(
			'box'    => $s['box'],
			'folder' => $s['folder'],
			'search' => $s['q'],
			'page'   => $s['page'],
		),
		'rows'    => $rows,
		'total'   => (int) $res['total'],
		'pages'   => max( 1, (int) ceil( (int) $res['total'] / MAILWEB_PER_PAGE ) ),
		'failed'  => (bool) $res['failed'],
		'views'   => $views,
		'boxes'   => $boxes,
		'bulk'    => $bulk,
		'columns' => array(
			'from'    => $forms || 'sent' !== $s['folder'] ? __( 'From', 'mailweb' ) : __( 'To', 'mailweb' ),
			'subject' => $forms ? __( 'About', 'mailweb' ) : __( 'Subject', 'mailweb' ),
			'mailbox' => ! $forms && 'all' === $s['box'],
			'search'  => ! $forms,
		),
	);
}

/**
 * One message for the reading pane, as today's message screen shows it (mailweb_message_screen()): the header, the
 * text as a safe document for a sandboxed frame, attachments, the compose links and the actions the folder offers.
 * Opening marks it read, as webmail and the message view do. A form message (folder "forms") comes from the forms source.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_message( WP_REST_Request $r ) {
	$folder  = sanitize_key( (string) $r['folder'] );
	$forms   = MAILWEB_FORMS === $folder && mailweb_forms();
	$folder  = $forms || isset( mailweb_roles()[ $folder ] ) ? $folder : 'inbox';
	$actions = array();
	foreach ( mailweb_bulk_actions( $folder ) as $value => $label ) {
		if ( 'read' !== $value ) {
			$actions[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
	}
	$missing = new WP_Error( 'mailweb_not_found', __( 'This message could not be opened (moved or deleted in webmail?).', 'mailweb' ), array( 'status' => 404 ) );
	if ( $forms ) {
		$id  = (int) $r['item'];
		$src = mailweb_forms();
		$f   = $id ? call_user_func( $src['get'], $id ) : null;
		if ( ! is_array( $f ) ) {
			return $missing;
		}
		call_user_func( $src['mark'], $id, 'read' );
		$fields = array();
		foreach ( (array) ( $f['fields'] ?? array() ) as $k => $v ) {
			if ( '' !== trim( (string) $v ) ) {
				$fields[] = array(
					'label' => (string) $k,
					'value' => (string) $v,
				);
			}
		}
		$t     = ! empty( $f['date'] ) ? strtotime( (string) $f['date'] ) : false;
		$email = (string) ( $f['email'] ?? '' );
		return rest_ensure_response(
			array(
				'kind'    => 'form',
				'id'      => 'form|' . $id,
				'subject' => '' !== trim( (string) ( $f['subject'] ?? '' ) ) ? (string) $f['subject'] : __( '(no subject)', 'mailweb' ),
				'fields'  => $fields,
				'text'    => (string) ( $f['text'] ?? '' ),
				'date'    => $t ? wp_date( 'j M Y, g:i a', $t ) : '',
				'compose' => is_email( $email ) ? array(
					'reply' => add_query_arg(
						array(
							'page'    => 'mailweb',
							'folder'  => MAILWEB_FORMS,
							'item'    => $id,
							'compose' => 'reply',
						),
						admin_url( 'admin.php' )
					),
				) : array(),
				'actions' => $actions,
			)
		);
	}
	$boxes = mailweb_all_boxes();
	$box   = sanitize_key( (string) $r['box'] );
	$uid   = (int) $r['uid'];
	if ( ! isset( $boxes[ $box ] ) || ! $uid ) {
		return $missing;
	}
	$path = mailweb_role_path( $folder );
	$msg  = mailweb_get( $box, $path, $uid );
	if ( ! $msg ) {
		return $missing;
	}
	mailweb_mark_seen( $box, $path, $uid, $msg );
	$m                    = $msg['m'];
	$images               = (bool) $r['images'];
	list( $doc, $remote ) = mailweb_message_doc( $box, $path, $uid, $msg, $images );
	$u                    = array(
		'box'    => $box,
		'folder' => $path,
		'uid'    => $uid,
		'view'   => $box,
	);
	$files                = array();
	foreach ( (array) ( $m['attachments'] ?? array() ) as $a ) {
		if ( empty( $a['inline'] ) || empty( $a['contentId'] ) ) {
			$files[] = array(
				'name' => (string) ( $a['filename'] ?? __( 'attachment', 'mailweb' ) ),
				'size' => size_format( (int) ( $a['sizeBytes'] ?? 0 ) ),
				'url'  => mailweb_act_url( 'file', $u + array( 'att' => (string) ( $a['id'] ?? '' ) ) ),
			);
		}
	}
	$compose = array();
	foreach ( array( 'reply', 'all', 'forward' ) as $mode ) {
		$compose[ $mode ] = add_query_arg(
			array(
				'page'    => 'mailweb',
				'box'     => $box,
				'folder'  => $folder,
				'uid'     => $uid,
				'compose' => $mode,
				'view'    => $box,
			),
			admin_url( 'admin.php' )
		);
	}
	$t = ! empty( $m['date'] ) ? strtotime( (string) $m['date'] ) : false;
	return rest_ensure_response(
		array(
			'kind'    => 'mail',
			'id'      => $box . '|' . $uid,
			'subject' => '' !== trim( (string) ( $m['subject'] ?? '' ) ) ? (string) $m['subject'] : __( '(no subject)', 'mailweb' ),
			'from'    => mailweb_who( $m['from'] ?? array() ),
			'to'      => implode( ', ', array_map( 'mailweb_who', (array) ( $m['to'] ?? array() ) ) ),
			'cc'      => implode( ', ', array_map( 'mailweb_who', (array) ( $m['cc'] ?? array() ) ) ),
			'date'    => $t ? wp_date( 'j M Y, g:i a', $t ) : '',
			'mailbox' => (string) $boxes[ $box ],
			'doc'     => $doc,
			'remote'  => $remote && ! $images,
			'files'   => $files,
			'compose' => $compose,
			'actions' => $actions,
			'moveTo'  => mailweb_move_choices( $box, $path ), // Move to folder: the mailbox's other folders
		)
	);
}

/**
 * Bulk and row actions: today's mailweb_bulk(), only with an action the folder offers.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_bulk( WP_REST_Request $r ) {
	$folder = sanitize_key( (string) $r['folder'] );
	$folder = ( MAILWEB_FORMS === $folder && mailweb_forms() ) || isset( mailweb_roles()[ $folder ] ) ? $folder : 'inbox';
	$act    = (string) $r['action'];
	if ( ! isset( mailweb_bulk_actions( $folder )[ $act ] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'That action is not offered in this folder.', 'mailweb' ), array( 'status' => 400 ) );
	}
	$ok = mailweb_bulk( $act, array_map( 'strval', (array) $r['ids'] ), $folder );
	return rest_ensure_response(
		array(
			'ok'      => $ok,
			'message' => $ok ? __( 'Done.', 'mailweb' ) : __( 'That did not work: the mail server refused it. Nothing was changed.', 'mailweb' ),
		)
	);
}

/**
 * Move to folder: today's single-message move, for one or more messages, to a folder of their own mailbox.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_move( WP_REST_Request $r ) {
	$folder = sanitize_key( (string) $r['folder'] );
	if ( ! isset( mailweb_roles()[ $folder ] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'Messages from this folder cannot be moved.', 'mailweb' ), array( 'status' => 400 ) );
	}
	$ok = mailweb_move_to( array_map( 'strval', (array) $r['ids'] ), $folder, (string) $r['to'] );
	return rest_ensure_response(
		array(
			'ok'      => $ok,
			'message' => $ok ? __( 'Moved.', 'mailweb' ) : __( 'That did not work: the folder is not in that mailbox, or the mail server refused it. Nothing else was changed.', 'mailweb' ),
		)
	);
}

/* ---------- Settings ---------- */

/**
 * One setting's state: 'config' (wp-config or the server), 'set' or 'unset'. Never the value.
 *
 * @param string $key api_token, or secret_<mailbox key>.
 */
function mailweb_secret_state( string $key ): string {
	if ( mailweb_setting_fixed( $key ) ) {
		return 'config';
	}
	return '' !== mailweb_setting( $key ) ? 'set' : 'unset';
}

/** The SMTP password's state: 'config', 'set' or 'unset'. Never the value. */
function mailweb_smtp_pass_state(): string {
	if ( getenv( 'MAILWEB_SMTP_PASSWORD' ) || ( defined( 'MAILWEB_SMTP_PASSWORD' ) && MAILWEB_SMTP_PASSWORD ) ) {
		return 'config';
	}
	return '' !== (string) ( ( (array) get_option( 'mailweb_smtp', array() ) )['pass'] ?? '' ) ? 'set' : 'unset';
}

/**
 * A settings group as the screen shows it.
 *
 * @param string $group sending, mailboxes or profiles.
 */
function mailweb_settings_group( string $group ): array {
	if ( 'sending' === $group ) {
		$v = mailweb_cap_current();
		return array(
			'slug'       => 'sending',
			'label'      => __( 'Sending', 'mailweb' ),
			'values'     => $v,
			'fields'     => array(
				array(
					'key'     => 'budget',
					'label'   => __( 'Emails per 24 hours', 'mailweb' ),
					'help'    => __( 'Your mail plan\'s daily limit, less a margin (Hostinger Business Email free: 100, so 90).', 'mailweb' ),
					'min'     => 1,
					'max'     => 10000,
					'default' => 90,
				),
				array(
					'key'     => 'reserve',
					'label'   => __( 'Kept back from campaigns', 'mailweb' ),
					'help'    => __( 'Campaigns use at most the limit minus this, so notices and confirmations always have room.', 'mailweb' ),
					'min'     => 0,
					'max'     => 99,
					'default' => 10,
				),
			),
			'note'       => __( 'Order: replies typed in FluentCRM, then notices, then sign-up confirmations, then campaigns. An email may set its own with the header X-Mailweb-Priority: 0 to 3.', 'mailweb' ),
			'deleteData' => mailweb_delete_data_on(),
			'reset'      => true,
		);
	}
	if ( 'mailboxes' === $group ) {
		$set   = mailweb_box_settings();
		$boxes = array();
		foreach ( mailweb_all_boxes() as $key => $addr ) {
			$boxes[] = array(
				'key'     => (string) $key,
				'address' => $addr,
				'aliases' => mailweb_aliases_of( $addr ),
				'send'    => ! isset( $set[ $key ]['send'] ) || (bool) $set[ $key ]['send'],
			);
		}
		return array(
			'slug'        => 'mailboxes',
			'label'       => __( 'Mailboxes and aliases', 'mailweb' ),
			'boxes'       => $boxes,
			'defaultFrom' => mailweb_default_from(),
			'aliases'     => mailweb_aliases_text( mailweb_aliases() ),
			'reset'       => true,
		);
	}
	$profiles = array();
	foreach ( mailweb_profile_addresses() as $key => $addr ) {
		$p          = mailweb_profile( $addr );
		$profiles[] = array(
			'key'        => (string) $key,
			'address'    => $addr,
			'name'       => (string) $p['name'],
			'signature'  => (string) $p['signature'],
			'picture'    => (int) $p['picture'],
			'pictureUrl' => $p['picture'] ? (string) wp_get_attachment_image_url( (int) $p['picture'], 'thumbnail' ) : '',
		);
	}
	return array(
		'slug'     => 'profiles',
		'label'    => __( 'Profiles', 'mailweb' ),
		'profiles' => $profiles,
	);
}

/** Every settings group, in order. */
function mailweb_settings_payload(): array {
	return array(
		'groups' => array_map( 'mailweb_settings_group', array( 'sending', 'mailboxes', 'profiles' ) ),
	);
}

/**
 * Save one group: only the fields sent, and only when they change what is stored.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_save_group( WP_REST_Request $r ) {
	$group = (string) $r['group'];
	$saved = false;
	if ( $r['reset'] ) {
		if ( 'profiles' === $group ) {
			return new WP_Error( 'rest_invalid_param', __( 'This section has no reset.', 'mailweb' ), array( 'status' => 400 ) );
		}
		$saved = mailweb_settings_reset( $group );
		return rest_ensure_response(
			array(
				'saved'   => $saved,
				'group'   => mailweb_settings_group( $group ),
				'message' => $saved ? __( 'Defaults restored.', 'mailweb' ) : __( 'Nothing changed, so nothing was saved.', 'mailweb' ),
			)
		);
	}
	if ( 'sending' === $group ) {
		$saved = mailweb_save_changed( 'mailweb_cap', mailweb_cap_current(), mailweb_rest_sent( $r, array( 'budget', 'reserve' ) ), 'mailweb_cap_clean' );
		if ( isset( mailweb_rest_sent( $r, array( 'delete_data' ) )['delete_data'] ) ) {
			$saved = mailweb_delete_data_save( (bool) $r['delete_data'] ) || $saved;
		}
	} elseif ( 'mailboxes' === $group ) {
		$keys = array_keys( mailweb_all_boxes() );
		$sent = mailweb_rest_sent( $r, array( 'send', 'default_from', 'aliases' ) );
		if ( isset( $sent['send'] ) ) {
			$now = array();
			foreach ( mailweb_settings_group( 'mailboxes' )['boxes'] as $b ) {
				$now[ $b['key'] ] = $b['send'];
			}
			$saved = mailweb_save_changed( 'mailweb_mailboxes', $now, (array) $sent['send'], fn( $v ) => mailweb_mailboxes_clean( $v, $keys ) ) || $saved;
		}
		if ( isset( $sent['default_from'] ) ) {
			$saved = mailweb_save_changed( 'mailweb_default_from', array( 'v' => (string) get_option( 'mailweb_default_from', '' ) ), array( 'v' => $sent['default_from'] ), fn( $v ) => sanitize_key( (string) $v['v'] ) ) || $saved;
		}
		if ( isset( $sent['aliases'] ) ) {
			$saved = mailweb_save_changed( 'mailweb_aliases', array( 'v' => mailweb_aliases_text( mailweb_aliases() ) ), array( 'v' => $sent['aliases'] ), fn( $v ) => mailweb_aliases_clean( (string) $v['v'] ) ) || $saved;
		}
	} else {
		$addrs = mailweb_profile_addresses();
		$all   = (array) get_option( 'mailweb_profiles', array() );
		foreach ( (array) ( mailweb_rest_sent( $r, array( 'profiles' ) )['profiles'] ?? array() ) as $key => $v ) {
			if ( ! isset( $addrs[ $key ] ) || ! is_array( $v ) ) {
				continue;
			}
			$now = mailweb_profile( $addrs[ $key ] );
			$new = mailweb_profile_clean( array_merge( $now, array_intersect_key( $v, $now ) ) );
			if ( mailweb_profile_clean( $now ) !== $new ) {
				$all[ $addrs[ $key ] ] = $new;
				$saved                 = true;
			}
		}
		if ( $saved ) {
			update_option( 'mailweb_profiles', $all, false );
		}
	}
	return rest_ensure_response(
		array(
			'saved'   => $saved,
			'group'   => mailweb_settings_group( $group ),
			'message' => $saved ? __( 'Saved.', 'mailweb' ) : __( 'Nothing changed, so nothing was saved.', 'mailweb' ),
		)
	);
}

/* ---------- Connected services ---------- */

/** Connected services: the Mail API, incoming mail per mailbox, site sending (SMTP) and the FluentCRM feed. */
function mailweb_services(): array {
	$boxes = mailweb_all_boxes();
	$token = mailweb_secret_state( 'api_token' );
	$ids   = 'unset' !== $token ? (array) get_option( 'mailweb_box_ids', array() ) : array();
	if ( 'unset' === $token ) {
		$api = array( __( 'Not set up', 'mailweb' ), 'warn' );
	} elseif ( ! $ids && get_transient( 'mailweb_box_ids_wait' ) ) {
		$api = array( __( 'Error', 'mailweb' ), 'error' );
	} else {
		/* translators: %d: number of mailboxes. */
		$api = array( sprintf( _n( 'Connected: %d mailbox', 'Connected: %d mailboxes', count( $boxes ), 'mailweb' ), count( $boxes ) ), '' );
	}
	$out   = array();
	$out[] = array(
		'slug'    => 'mail-api',
		'kind'    => 'secret',
		'name'    => __( 'Hostinger Mail API', 'mailweb' ),
		'status'  => $api[0],
		'tone'    => $api[1],
		'summary' => __( 'Reads and files your mail. Its scope decides which mailboxes MailWeb can see.', 'mailweb' ),
		'route'   => 'services/mail-api',
		'field'   => array(
			'key'   => 'token',
			'label' => __( 'Mail API token', 'mailweb' ),
			'state' => $token,
			'help'  => __( 'hPanel → Emails → Agentic mail → API. Or set MAILWEB_API_TOKEN in wp-config.', 'mailweb' ),
		),
		'rows'    => array_map( fn( $k, $a ) => array( $a, (string) ( $ids[ $k ] ?? '' ) ), array_keys( $boxes ), array_values( $boxes ) ),
	);
	$last  = get_option( 'mailweb_last' );
	foreach ( $boxes as $key => $addr ) {
		$state = mailweb_secret_state( 'secret_' . $key );
		$mine  = is_array( $last ) && ( $last['mailbox'] ?? '' ) === $addr;
		if ( 'unset' === $state ) {
			$st = array( __( 'Not set up', 'mailweb' ), 'warn' );
		} elseif ( $mine ) {
			/* translators: %s: date and time of the last delivery. */
			$st = array( sprintf( __( 'Last mail in %s', 'mailweb' ), (string) $last['at'] ), '' );
		} else {
			$st = array( __( 'Set up', 'mailweb' ), '' );
		}
		$out[] = array(
			'slug'    => 'webhook-' . $key,
			'kind'    => 'secret',
			/* translators: %s: mailbox address. */
			'name'    => sprintf( __( 'Incoming mail: %s', 'mailweb' ), $addr ),
			'status'  => $st[0],
			'tone'    => $st[1],
			'summary' => __( 'Hostinger tells the site when mail arrives, so the counts stay fresh.', 'mailweb' ),
			'route'   => 'services/webhook/' . $key,
			'field'   => array(
				'key'   => 'secret',
				'label' => __( 'Webhook secret', 'mailweb' ),
				'state' => $state,
				/* translators: %s: wp-config constant name. */
				'help'  => sprintf( __( 'Shown once when the webhook is made on this mailbox. Or set %s in wp-config.', 'mailweb' ), strtoupper( 'mailweb_secret_' . $key ) ),
			),
			'rows'    => array(
				array( __( 'Webhook address', 'mailweb' ), rest_url( MAILWEB_REST . '/mail-in' ) ),
				array( __( 'Where in hPanel', 'mailweb' ), __( 'Emails → this mailbox → Agentic mail → Webhooks, event "message.received"', 'mailweb' ) ),
			),
		);
	}
	$smtp  = mailweb_smtp_settings();
	$pass  = mailweb_smtp_pass_state();
	$pause = mailweb_paused();
	if ( $pause ) {
		$st = array( __( 'Paused', 'mailweb' ), 'error' );
	} elseif ( 'unset' === $pass ) {
		$st = array( __( 'Not set up', 'mailweb' ), 'warn' );
	} else {
		/* translators: %s: sending mailbox address. */
		$st = array( sprintf( __( 'Sending through %s', 'mailweb' ), $smtp['user'] ), '' );
	}
	$out[]  = array(
		'slug'    => 'smtp',
		'kind'    => 'smtp',
		'name'    => __( 'Site sending (SMTP)', 'mailweb' ),
		'status'  => $st[0],
		'tone'    => $st[1],
		'summary' => 'unset' === $pass ? __( 'Until a password is set, the site sends the server\'s default way.', 'mailweb' ) : __( 'Every email the site sends goes from this mailbox.', 'mailweb' ),
		'route'   => 'services/smtp',
		'values'  => array(
			'user' => (string) $smtp['user'],
			'name' => (string) $smtp['name'],
			'host' => (string) $smtp['host'],
			'port' => (string) $smtp['port'],
		),
		'pass'    => $pass,
	);
	$crm    = mailweb_crm_settings();
	$active = function_exists( 'FluentCrmApi' );
	$on     = mailweb_crm_boxes();
	if ( ! $active ) {
		$st = array( __( 'Not active', 'mailweb' ), 'warn' );
	} elseif ( empty( $crm['on'] ) ) {
		$st = array( __( 'Off', 'mailweb' ), '' );
	} else {
		/* translators: %d: number of mailboxes. */
		$st = array( sprintf( _n( 'On: %d mailbox', 'On: %d mailboxes', count( $on ), 'mailweb' ), count( $on ) ), '' );
	}
	$out[] = array(
		'slug'    => 'fluentcrm',
		'kind'    => 'fluentcrm',
		'name'    => __( 'FluentCRM feed', 'mailweb' ),
		'status'  => $st[0],
		'tone'    => $st[1],
		'summary' => __( 'Notes each incoming email, and each reply sent from the Inbox, on the person\'s contact.', 'mailweb' ),
		'route'   => 'services/fluentcrm',
		'active'  => $active,
		'values'  => array(
			'on'    => ! empty( $crm['on'] ),
			'tag'   => (string) $crm['tag'],
			'boxes' => array_values( $on ),
		),
		'boxes'   => array_map(
			fn( $k, $a ) => array(
				'key'     => (string) $k,
				'address' => $a,
			),
			array_keys( $boxes ),
			array_values( $boxes )
		),
	);
	return $out;
}

/**
 * Save a key or secret (encrypted). Empty keeps the saved one; one set in wp-config cannot be changed here.
 *
 * @param string $key   api_token, or secret_<mailbox key>.
 * @param string $value The new value.
 */
function mailweb_rest_save_secret( string $key, string $value ) {
	$value = trim( sanitize_text_field( $value ) );
	$saved = false;
	if ( mailweb_setting_fixed( $key ) && '' !== $value ) {
		return new WP_Error( 'rest_invalid_param', __( 'This is set in wp-config.php: change it there.', 'mailweb' ), array( 'status' => 400 ) );
	}
	if ( '' !== $value ) {
		mailweb_save_setting( $key, $value );
		if ( 'api_token' === $key ) {
			mailweb_box_ids( true ); // The mailboxes and their IDs, from the new token.
		}
		$saved = true;
	}
	return rest_ensure_response(
		array(
			'saved'    => $saved,
			'services' => mailweb_services(),
			'message'  => $saved ? __( 'Saved.', 'mailweb' ) : __( 'Nothing changed, so nothing was saved.', 'mailweb' ),
		)
	);
}

/**
 * Save the SMTP mailbox: the fields sent over today's, written only when something changed or a new password came.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_save_smtp( WP_REST_Request $r ) {
	$s       = mailweb_smtp_settings();
	$current = array(
		'user' => (string) $s['user'],
		'name' => (string) $s['name'],
		'host' => (string) $s['host'],
		'port' => $s['port'],
	);
	$pass    = 'config' === mailweb_smtp_pass_state() ? '' : (string) $r['pass'];
	$new     = mailweb_smtp_clean( array_merge( $current, mailweb_rest_sent( $r, array( 'user', 'name', 'host', 'port' ) ) ) );
	$saved   = false;
	if ( mailweb_smtp_clean( $current ) !== $new || '' !== $pass ) {
		$old = (array) get_option( 'mailweb_smtp', array() );
		update_option( 'mailweb_smtp', $new + array( 'pass' => '' !== $pass ? mailweb_encrypt( $pass ) : ( $old['pass'] ?? '' ) ), false );
		$saved = true;
	}
	return rest_ensure_response(
		array(
			'saved'    => $saved,
			'services' => mailweb_services(),
			'message'  => $saved ? __( 'Saved.', 'mailweb' ) : __( 'Nothing changed, so nothing was saved.', 'mailweb' ),
		)
	);
}

/**
 * Save the FluentCRM feed: the fields sent over today's.
 *
 * @param WP_REST_Request $r Request.
 */
function mailweb_rest_save_crm( WP_REST_Request $r ) {
	$crm   = mailweb_crm_settings();
	$keys  = array_keys( mailweb_all_boxes() );
	$saved = mailweb_save_changed(
		'mailweb_crm',
		array(
			'on'    => ! empty( $crm['on'] ),
			'tag'   => (string) $crm['tag'],
			'boxes' => mailweb_crm_boxes(),
		),
		mailweb_rest_sent( $r, array( 'on', 'tag', 'boxes' ) ),
		fn( $v ) => mailweb_crm_clean( $v, $keys )
	);
	return rest_ensure_response(
		array(
			'saved'    => $saved,
			'services' => mailweb_services(),
			'message'  => $saved ? __( 'Saved.', 'mailweb' ) : __( 'Nothing changed, so nothing was saved.', 'mailweb' ),
		)
	);
}
