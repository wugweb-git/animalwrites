<?php
/**
 * Animal Writes' side of Mailweb (owner, 20 Oct 2026: Mailweb is a product; what only Animal Writes uses lives here).
 *
 * - The Mail API token stays in wp-config as AW_HOSTINGER_MAIL_TOKEN; Mailweb reads MAILWEB_API_TOKEN.
 * - The open campaign's strip under every signature (mail-campaign-strip.php, `mailweb_after_signature`).
 * - Mail → Website forms: the Contact, Volunteer, Claim and Report forms' Fluent Forms entries and the older contact
 *   messages (aw_message), through `mailweb_forms_source`; a notice in wp-admin while any of them is unread (no email).
 *   Inbox → Contact messages opens it.
 * - The old webhook address /wp-json/aw/v1/mail-in still answers (the hPanel webhooks point there).
 * - Animal Writes → Check site: a line on email sending.
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

// The one-time move of aw-mail's options, table and schedule to Mailweb ran on 20 Oct 2026 and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

/* ---------- Mail → Website forms: Fluent Forms entries (Contact, Volunteer, Claim, Report) and older contact messages (aw_message) ---------- */

// Since 21 Oct 2026 (owner) a Contact form message is a Fluent Forms entry (form #4), not an aw_message; since 1 Nov 2026
// (owner) the Volunteer, Claim and Report forms' entries show here too. All are listed newest first. An entry's ID in
// Mail is AW_MAIL_FF_ID + its entry ID (Mail passes whole numbers; post IDs stay far below). Read/unread and trash are
// the entry's own Fluent Forms status, so both screens agree. Fluent Forms stores created_at in site time.
const AW_MAIL_FF_ID = 1000000000;

/** Forms shown in Mail → Website forms: key => label. */
const AW_MAIL_FF_KEYS = array(
	'contact'   => 'Contact',
	'volunteer' => 'Volunteer',
	'claim'     => 'Claim',
	'report'    => 'Report',
);

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

/**
 * The forms this user sees in Mail: Fluent Forms ID => key. Contact for everyone with Mail; Volunteer, Claim and Report
 * only for those who may read volunteer, claim and report data (edit_aw_volunteers: administrators), as on their own screens.
 */
function aw_mail_ff_forms() {
	$out = array();
	foreach ( array_keys( AW_MAIL_FF_KEYS ) as $key ) {
		$id = function_exists( 'aw_ff_id' ) ? aw_ff_id( $key ) : 0;
		if ( $id && ( 'contact' === $key || current_user_can( 'edit_aw_volunteers' ) ) ) {
			$out[ $id ] = $key;
		}
	}
	return $out;
}

/** "form_id IN (…)" for these forms, prepared. */
function aw_mail_ff_in( $forms ) {
	global $wpdb;
	$ids = array_map( 'intval', array_keys( $forms ) );
	return $wpdb->prepare( 'form_id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')', $ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %d per form
}

/** One of those forms' entries (not trashed), or null. */
function aw_mail_ff_entry( $id ) {
	global $wpdb;
	$forms = aw_mail_ff_forms();
	if ( ! $forms || $id <= AW_MAIL_FF_ID ) {
		return null;
	}
	$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fluentform_submissions WHERE id = %d AND status <> 'trashed' AND ", $id - AW_MAIL_FF_ID ) . aw_mail_ff_in( $forms );
	return $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- both parts prepared
}

/** An entry's form key (contact, volunteer, claim, report). */
function aw_mail_ff_key( $e ) {
	return aw_mail_ff_forms()[ (int) $e->form_id ] ?? 'contact';
}

/** An entry's reference (AW-12, AW-V-3, AW-C-2, AW-R-1), as aw_ff_ref() makes them. */
function aw_mail_ff_ref( $e ) {
	$prefix = array( 'contact' => 'AW-', 'volunteer' => 'AW-V-', 'claim' => 'AW-C-', 'report' => 'AW-R-' );
	return ( $prefix[ aw_mail_ff_key( $e ) ] ?? 'AW-' ) . (int) $e->serial_number;
}

/** An entry's subject, led by its form: "Contact: List your organisation (AW-12)", "Claim: {listing} (AW-C-2)". */
function aw_mail_ff_subject( $e, $r ) {
	$key  = aw_mail_ff_key( $e );
	$what = '';
	if ( 'contact' === $key ) {
		$about = (string) ( $r['about'] ?? '' );
		$what  = defined( 'AW_CONTACT_ABOUT' ) ? ( AW_CONTACT_ABOUT[ $about ] ?? $about ) : $about;
	} elseif ( 'claim' === $key && (int) ( $r['listing'] ?? 0 ) ) {
		$what = get_the_title( (int) $r['listing'] );
	} elseif ( 'report' === $key && (int) ( $r['item'] ?? 0 ) ) {
		$what = get_the_title( (int) $r['item'] );
	}
	$what = trim( (string) $what );
	return AW_MAIL_FF_KEYS[ $key ] . ( '' !== $what ? ': ' . $what : '' ) . ' (' . aw_mail_ff_ref( $e ) . ')';
}

/** The fields shown with an entry, per form: response key => label. */
function aw_mail_ff_fields( $key ) {
	$map = array(
		'contact'   => array( 'org_name' => 'Organisation', 'org_district' => 'District', 'org_phone' => 'Phone', 'org_services' => 'Services', 'org_website' => 'Website', 'opp_title' => 'Opportunity', 'opp_type' => 'Type', 'opp_ongoing' => 'Ongoing', 'opp_start' => 'Starts', 'opp_end' => 'Ends', 'opp_link' => 'Link', 'whatsapp' => 'WhatsApp' ),
		'volunteer' => array( 'roles' => 'Wants to', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'pincode' => 'Pincode', 'district' => 'District', 'state' => 'State', 'availability' => 'Available', 'travel_range' => 'Travels', 'skills' => 'Skills', 'languages' => 'Languages', 'org_name' => 'Organisation' ),
		'claim'     => array( 'role' => 'Role', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'aw_phone' => 'Listing phone', 'aw_whatsapp' => 'Listing WhatsApp', 'aw_email' => 'Listing email', 'aw_website' => 'Listing website', 'aw_area' => 'Area', 'aw_services' => 'Services' ),
		'report'    => array( 'reason' => 'Reason' ),
	);
	return $map[ $key ] ?? array();
}

/** One field's value as text: choices by their labels where the site has them. */
function aw_mail_ff_value( $k, $v ) {
	$labels = array(
		'roles'  => defined( 'AW_VOL_ROLES' ) ? AW_VOL_ROLES : array(),
		'role'   => defined( 'AW_CLAIM_ROLES' ) ? AW_CLAIM_ROLES : array(),
		'reason' => function_exists( 'aw_report_reasons' ) ? aw_report_reasons() : array(),
	)[ $k ] ?? ( defined( 'AW_SERVICES' ) ? AW_SERVICES : array() );
	return implode( ', ', array_map( fn( $x ) => $labels[ $x ] ?? (string) $x, is_array( $v ) ? $v : array( $v ) ) );
}

/** An entry's message text: the Contact message, the claim's notes, the report's details. */
function aw_mail_ff_text( $key, $r ) {
	$k = array( 'contact' => 'message', 'claim' => 'notes', 'report' => 'details' )[ $key ] ?? '';
	return '' !== $k ? (string) ( $r[ $k ] ?? '' ) : '';
}

/** Unread entries per Fluent Forms form (all forms): form ID => count. Once per request. */
function aw_mail_ff_unread_counts() {
	static $out = null;
	if ( null === $out ) {
		global $wpdb;
		$out = defined( 'FLUENTFORM' ) ? array_map( 'intval', array_column( (array) $wpdb->get_results( "SELECT form_id, COUNT(*) AS n FROM {$wpdb->prefix}fluentform_submissions WHERE status = 'unread' GROUP BY form_id", ARRAY_A ), 'n', 'form_id' ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	return $out;
}

add_filter(
	'mailweb_forms_source',
	fn() => array(
		'label'   => 'Website forms',
		'list'    => function ( $page, $per ) {
			global $wpdb;
			$rows = array(); // [date, id]
			foreach ( get_posts( array( 'post_type' => 'aw_message', 'post_status' => array( 'private', 'publish', 'pending', 'draft' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => aw_mail_forms_where() ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
				$rows[] = array( get_post_field( 'post_date', $pid ), (int) $pid );
			}
			$forms = aw_mail_ff_forms();
			if ( $forms ) {
				$sql = "SELECT id, created_at FROM {$wpdb->prefix}fluentform_submissions WHERE status <> 'trashed' AND " . aw_mail_ff_in( $forms );
				foreach ( (array) $wpdb->get_results( $sql ) as $e ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- aw_mail_ff_in() is prepared
					$rows[] = array( (string) $e->created_at, AW_MAIL_FF_ID + (int) $e->id );
				}
			}
			usort( $rows, fn( $a, $b ) => strcmp( $b[0], $a[0] ) );
			$items = array();
			foreach ( array_slice( $rows, ( max( 1, (int) $page ) - 1 ) * $per, $per ) as $row ) {
				$e = aw_mail_ff_entry( $row[1] );
				if ( $e ) {
					$r       = (array) json_decode( (string) $e->response, true );
					$items[] = array( 'id' => $row[1], 'name' => (string) ( $r['name'] ?? '' ), 'email' => (string) ( $r['email'] ?? '' ), 'subject' => aw_mail_ff_subject( $e, $r ), 'date' => $row[0], 'unread' => 'unread' === $e->status );
					continue;
				}
				$p = get_post( $row[1] );
				if ( ! $p ) {
					continue;
				}
				$items[] = array(
					'id'      => $p->ID,
					'name'    => trim( (string) get_post_meta( $p->ID, 'aw_m_name', true ) ),
					'email'   => trim( (string) get_post_meta( $p->ID, 'aw_m_email', true ) ),
					'subject' => 'Contact: ' . trim( preg_replace( '/^Contact form:\s*/', '', $p->post_title ) ),
					'date'    => $p->post_date,
					'unread'  => '' === (string) get_post_meta( $p->ID, 'aw_read', true ),
				);
			}
			return array(
				'items' => $items,
				'total' => count( $rows ),
			);
		},
		'unread'  => function () { // unread messages to handle: the folder count and the Mail menu badge; marking read clears them
			global $wpdb;
			$old    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'aw_m_about' LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = 'aw_read' WHERE p.post_type = 'aw_message' AND p.post_status NOT IN ('trash','auto-draft') AND ( a.meta_value IS NULL OR a.meta_value <> 'email' ) AND r.meta_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$counts = aw_mail_ff_unread_counts();
			return $old + array_sum( array_map( fn( $id ) => $counts[ $id ] ?? 0, array_keys( aw_mail_ff_forms() ) ) );
		},
		'get'     => function ( $id ) {
			$e = aw_mail_ff_entry( (int) $id );
			if ( $e ) {
				$r      = (array) json_decode( (string) $e->response, true );
				$key    = aw_mail_ff_key( $e );
				$fields = array(
					'Form'      => AW_MAIL_FF_KEYS[ $key ],
					'From'      => trim( ( $r['name'] ?? '' ) . ( ! empty( $r['email'] ) ? ' <' . $r['email'] . '>' : '' ) ),
					'Reference' => aw_mail_ff_ref( $e ),
				);
				$about  = (int) ( 'claim' === $key ? ( $r['listing'] ?? 0 ) : ( 'report' === $key ? ( $r['item'] ?? 0 ) : 0 ) );
				if ( $about ) {
					$fields[ 'claim' === $key ? 'Listing' : 'About' ] = get_the_title( $about ) . ' (' . get_permalink( $about ) . ')';
				}
				foreach ( aw_mail_ff_fields( $key ) as $k => $label ) {
					$v = aw_mail_ff_value( $k, $r[ $k ] ?? '' );
					if ( '' !== trim( $v ) ) {
						$fields[ $label ] = 'opp_ongoing' === $k ? 'yes' : $v;
					}
				}
				if ( 'contact' === $key ) {
					$fields['OK on WhatsApp'] = ! empty( $r['whatsapp'] ) ? ( empty( array_filter( (array) ( $r['wa_optin'] ?? array() ) ) ) ? '0' : '1' ) : '';
				}
				$fields['Page'] = (string) ( $r['v_page_title'] ?? $e->source_url );
				if ( 'report' !== $key ) {
					$fields['Newsletter'] = empty( array_filter( (array) ( $r['nl_optin'] ?? array() ) ) ) ? '0' : '1';
				}
				$fields['Entry'] = admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . (int) $e->form_id . '#/entries/' . (int) $e->id );
				return array( 'subject' => aw_mail_ff_subject( $e, $r ), 'email' => (string) ( $r['email'] ?? '' ), 'date' => (string) $e->created_at, 'text' => aw_mail_ff_text( $key, $r ), 'fields' => array_filter( $fields, fn( $v ) => '' !== (string) $v ) );
			}
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
			global $wpdb;
			$e = aw_mail_ff_entry( (int) $id );
			if ( $e ) { // Fluent Forms' own status: read, unread, trashed (restorable in Fluent Forms → Entries → Trash)
				$to = array( 'read' => 'read', 'unread' => 'unread', 'trash' => 'trashed' )[ $what ] ?? '';
				return $to ? false !== $wpdb->update( "{$wpdb->prefix}fluentform_submissions", array( 'status' => $to ), array( 'id' => (int) $e->id ) ) : false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
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
		'replied' => function ( $id ) {
			global $wpdb;
			$e = aw_mail_ff_entry( (int) $id );
			if ( $e ) { // a note on the entry, as Fluent Forms shows them
				return (bool) $wpdb->insert( "{$wpdb->prefix}fluentform_submission_meta", array( 'response_id' => (int) $e->id, 'form_id' => (int) $e->form_id, 'meta_key' => '_notes', 'value' => 'Reply sent from Mail', 'status' => 'note', 'user_id' => get_current_user_id(), 'name' => 'Mail', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			return 'aw_message' === get_post_type( $id ) && update_post_meta( $id, 'aw_m_replied', current_time( 'mysql' ) );
		},
	)
);

/* ---------- New form entries: a notice in wp-admin (owner, 1 Nov 2026: no email; form emails stay off) ---------- */

// For Mail users while Contact, Volunteer, Claim or Report entries they can read are unread (the same count as the
// Mail menu badge); other forms' unread entries are counted in the same line. Marking an entry read clears it.
add_action(
	'admin_notices',
	function () {
		if ( ! defined( 'MAILWEB_CAP' ) || ! current_user_can( MAILWEB_CAP ) || ! function_exists( 'mailweb_forms' ) || ! mailweb_forms() ) {
			return;
		}
		$counts = aw_mail_ff_unread_counts();
		$forms  = aw_mail_ff_forms();
		$parts  = array();
		foreach ( $forms as $id => $key ) {
			if ( ! empty( $counts[ $id ] ) ) {
				$parts[] = AW_MAIL_FF_KEYS[ $key ] . ' ' . (int) $counts[ $id ];
			}
		}
		if ( ! $parts ) {
			return;
		}
		$shown = array_merge( array_keys( $forms ), array_map( 'aw_ff_id', array_keys( AW_MAIL_FF_KEYS ) ) );
		$other = array_diff_key( $counts, array_flip( $shown ) );
		if ( $other && current_user_can( 'manage_options' ) ) {
			global $wpdb;
			$titles = array_column( (array) $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}fluentform_forms", ARRAY_A ), 'title', 'id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $other as $id => $n ) {
				$parts[] = ( $titles[ $id ] ?? 'Form ' . (int) $id ) . ' ' . (int) $n;
			}
		}
		$url = add_query_arg( array( 'page' => 'mailweb', 'folder' => 'forms' ), admin_url( 'admin.php' ) );
		echo '<div class="notice notice-info"><p><strong>New form entries:</strong> ' . esc_html( implode( ', ', $parts ) ) . '. <a href="' . esc_url( $url ) . '">Open Mail → Website forms</a></p></div>';
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

/* ---------- Animal Writes → Check site: email sending ---------- */

add_filter( 'mailweb_pause_notice_always', fn( $always, $id ) => $always || str_ends_with( (string) $id, '_page_aw-site-check' ), 10, 2 );

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
