<?php
/**
 * Animal Writes' side of Mailweb (owner, 20 Oct 2026: Mailweb is a product; what only Animal Writes uses lives here).
 *
 * - The Mail API token stays in wp-config as AW_HOSTINGER_MAIL_TOKEN; Mailweb reads MAILWEB_API_TOKEN.
 * - The open campaign's strip under every signature (mail-campaign-strip.php, `mailweb_after_signature`).
 * - Mail → Website forms: the Contact form messages kept in WordPress (aw_message), through `mailweb_forms_source`.
 *   Inbox → Contact messages opens it.
 * - The old webhook address /wp-json/aw/v1/mail-in still answers (the hPanel webhooks point there).
 * - Tools → Check site: a line on email sending.
 * - One-time move (20 Oct 2026) from the aw-mail plugin: options aw_mail_* → mailweb_* (secrets encrypted), kits_mail →
 *   mailweb_smtp, table aw_mail_queue → mailweb_queue, old cron jobs cleared, and Animal Writes' choices set: replies go
 *   from support@ only (never vedanshu@ or noreply@), contact@ is vedanshu@'s alias, FluentCRM notes for vedanshu@ and
 *   support@ with the tag "Emailed us". The old options are kept, not deleted.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MAILWEB_API_TOKEN' ) && defined( 'AW_HOSTINGER_MAIL_TOKEN' ) && AW_HOSTINGER_MAIL_TOKEN ) {
	define( 'MAILWEB_API_TOKEN', AW_HOSTINGER_MAIL_TOKEN );
}

require_once AW_CORE_DIR . 'includes/mail-campaign-strip.php';

add_filter(
	'mailweb_after_signature',
	fn( $html, $context = 'site' ) => $html . aw_mail_campaign_strip_html(),
	10,
	2
);

/* ---------- One-time move from aw-mail ---------- */

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'mailweb_encrypt' ) || get_option( 'aw_mailweb_moved' ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time move
		$names = (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'aw\\_mail\\_%'" );
		foreach ( $names as $old ) {
			$new = 'mailweb_' . substr( $old, 8 );
			if ( false !== get_option( $new, false ) || 'aw_mail_webhook_secret' === $old ) {
				continue;
			}
			$v = get_option( $old );
			if ( 'mailweb_api_token' === $new || str_starts_with( $new, 'mailweb_secret_' ) ) {
				$v = mailweb_encrypt( (string) $v );
			}
			add_option( $new, $v, '', false );
		}
		$legacy = (string) get_option( 'aw_mail_webhook_secret', '' ); // the single secret saved before each mailbox had its own
		if ( '' !== $legacy && '' === (string) get_option( 'mailweb_secret_vedanshu', '' ) ) {
			update_option( 'mailweb_secret_vedanshu', mailweb_encrypt( $legacy ), false );
		}
		$smtp = get_option( 'kits_mail', false );
		if ( is_array( $smtp ) && false === get_option( 'mailweb_smtp', false ) ) {
			$smtp['pass'] = mailweb_encrypt( (string) ( $smtp['pass'] ?? '' ) );
			add_option( 'mailweb_smtp', $smtp, '', false );
		}
		$old_t = $wpdb->prefix . 'aw_mail_queue';
		$new_t = $wpdb->prefix . 'mailweb_queue';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time table rename
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_t ) ) === $old_t ) {
			$has_new = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_t ) ) === $new_t;
			if ( $has_new && 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new_t}" ) ) {
				$wpdb->query( "DROP TABLE {$new_t}" ); // made empty by an earlier load; the old table has the log and queue
				$has_new = false;
			}
			if ( ! $has_new ) {
				$wpdb->query( "RENAME TABLE {$old_t} TO {$new_t}" );
			}
		}
		// phpcs:enable
		foreach ( array( 'aw_mail_flush', 'aw_mail_crm_sync', 'aw_mail_poll' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		// Animal Writes' choices (owner): replies from support@ only; contact@ → vedanshu@; FluentCRM notes as before.
		add_option(
			'mailweb_mailboxes',
			array(
				'vedanshu' => array( 'send' => false ),
				'noreply'  => array( 'send' => false ),
				'support'  => array( 'send' => true ),
			),
			'',
			false
		);
		add_option( 'mailweb_default_from', 'support', '', false );
		add_option( 'mailweb_aliases', array( 'contact@animalwrites.in' => 'vedanshu@animalwrites.in' ), '', false );
		add_option(
			'mailweb_crm',
			array(
				'on'    => true,
				'tag'   => 'Emailed us',
				'boxes' => array( 'vedanshu', 'support' ),
			),
			'',
			false
		);
		update_option( 'aw_mailweb_moved', current_time( 'mysql' ), false );
	},
	0
);

/* ---------- Mail → Website forms: Contact form messages (aw_message) ---------- */

/** aw_message items that are form messages (not old copies of emails). */
function aw_mail_forms_where() {
	return array(
		'relation' => 'OR',
		array(
			'key'     => 'aw_m_about',
			'value'   => 'email',
			'compare' => '!=',
		),
		array(
			'key'     => 'aw_m_about',
			'compare' => 'NOT EXISTS',
		),
	);
}

add_filter(
	'mailweb_forms_source',
	fn() => array(
		'label'   => 'Website forms',
		'list'    => function ( $page, $per ) {
			$q     = new WP_Query(
				array(
					'post_type'      => 'aw_message',
					'post_status'    => array( 'private', 'publish', 'pending', 'draft' ),
					'posts_per_page' => $per,
					'paged'          => $page,
					'meta_query'     => aw_mail_forms_where(), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$items = array();
			foreach ( $q->posts as $p ) {
				$items[] = array(
					'id'      => $p->ID,
					'name'    => trim( (string) get_post_meta( $p->ID, 'aw_m_name', true ) ),
					'email'   => trim( (string) get_post_meta( $p->ID, 'aw_m_email', true ) ),
					'subject' => trim( preg_replace( '/^Contact form:\s*/', '', $p->post_title ) ),
					'date'    => $p->post_date,
					'unread'  => '' === (string) get_post_meta( $p->ID, 'aw_read', true ),
				);
			}
			return array(
				'items' => $items,
				'total' => (int) $q->found_posts,
			);
		},
		'unread'  => function () {
			global $wpdb;
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'aw_m_about' LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = 'aw_read' WHERE p.post_type = 'aw_message' AND p.post_status NOT IN ('trash','auto-draft') AND ( a.meta_value IS NULL OR a.meta_value <> 'email' ) AND r.meta_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		},
		'get'     => function ( $id ) {
			if ( 'aw_message' !== get_post_type( $id ) ) {
				return null;
			}
			$m = fn( $k ) => trim( (string) get_post_meta( $id, 'aw_m_' . $k, true ) );
			return array(
				'subject' => trim( preg_replace( '/^Contact form:\s*/', '', get_the_title( $id ) ) ),
				'email'   => $m( 'email' ),
				'date'    => get_post_field( 'post_date', $id ),
				'text'    => (string) get_post_field( 'post_content', $id ),
				'fields'  => array(
					'From'         => trim( $m( 'name' ) . ( '' !== $m( 'email' ) ? ' <' . $m( 'email' ) . '>' : '' ) ),
					'Organisation' => $m( 'org' ),
					'Page'         => $m( 'source' ),
					'Newsletter'   => $m( 'contact' ),
				),
			);
		},
		'mark'    => function ( $id, $what ) {
			if ( 'aw_message' !== get_post_type( $id ) ) {
				return false;
			}
			if ( 'read' === $what ) {
				update_post_meta( $id, 'aw_read', current_time( 'mysql' ) );
			} elseif ( 'unread' === $what ) {
				delete_post_meta( $id, 'aw_read' );
			} elseif ( 'trash' === $what ) {
				return (bool) wp_trash_post( $id ); // WordPress Trash: restorable
			}
			return true;
		},
		'replied' => fn( $id ) => 'aw_message' === get_post_type( $id ) && update_post_meta( $id, 'aw_m_replied', current_time( 'mysql' ) ),
	)
);

// Inbox → Contact messages is Mail → Website forms (one inbox). The list redirects; single items still open.
add_action(
	'admin_menu',
	function () {
		remove_submenu_page( 'aw-inbox', 'edit.php?post_type=aw_message' );
	},
	999
);
add_action(
	'load-edit.php',
	function () {
		if ( 'aw_message' === sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) && empty( $_GET['post_status'] ) && function_exists( 'mailweb_forms' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a redirect between list screens
			wp_safe_redirect( admin_url( 'admin.php?page=mailweb&folder=forms' ) );
			exit;
		}
	}
);

/* ---------- The old webhook address ---------- */

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'mailweb_in' ) ) {
			return;
		}
		register_rest_route(
			'aw/v1',
			'/mail-in',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'mailweb_webhook_permission',
				'callback'            => 'mailweb_in',
			)
		);
		register_rest_route(
			'aw/v1',
			'/mail-in',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => fn() => new WP_REST_Response(
					array(
						'status' => 'ready',
						'method' => 'POST',
					),
					200
				),
			)
		);
	}
);

/* ---------- Tools → Check site: email sending ---------- */

add_filter( 'mailweb_pause_notice_always', fn( $always, $id ) => $always || 'tools_page_aw-site-check' === $id, 10, 2 );

add_action(
	'aw_site_check_after',
	function () {
		if ( ! function_exists( 'mailweb_paused' ) ) {
			return;
		}
		$pause = mailweb_paused();
		echo '<div class="notice ' . ( $pause ? 'notice-error' : 'notice-success' ) . ' inline"><p><strong>Email sending: ' . ( $pause ? 'PAUSED' : 'running' ) . '.</strong> ';
		if ( $pause ) {
			echo esc_html( 'Refused ' . wp_date( 'j M Y, H:i', (int) $pause['at'] ) . ': ' . $pause['error'] ) . ' <a href="' . esc_url( mailweb_settings_url( 'sending', array(), 'cap' ) ) . '">Resume on Mail → Settings → Site sending</a>.';
		} else {
			$last = (array) get_option( 'mailweb_last_error', array() );
			echo $last ? esc_html( 'Last error ' . wp_date( 'j M H:i', (int) $last['at'] ) . ': ' . $last['error'] ) : 'No errors recorded.';
		}
		echo '</p></div>';
	}
);
