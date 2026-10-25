<?php
/**
 * "Report a mistake" for every page (owner, 21 Oct 2026): /report/?item=<ID> from any listing, News, Library or Resource
 * item (/report/?listing=<ID> still works); opened on its own it asks for the page link.
 *
 * Fields: the item (hidden), what's wrong, details (up to 1,000 characters), an optional email (only to reply about the
 * report) and the spam check. The form is the Fluent Forms form #7 (/forms/7-report-a-mistake.json): it reads the
 * item from the link ({get.item}) and the reason ({get.reason}); each report is a Fluent Forms entry (favourite = needs
 * action, notes = what was done). Nothing on the item changes automatically. The site's own form and its handler were
 * removed (owner, 25 Oct 2026); reports sent before are private aw_report posts, still listed below.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** What can be wrong: key => label. "Details missing" is what the "Data not yet available" links choose. */
function aw_report_reasons() {
	return array(
		'wrong'     => 'Wrong information',
		'outdated'  => 'Out of date',
		'missing'   => 'Details missing',
		'link'      => 'Broken link',
		'closed'    => 'Closed or no longer operating',
		'duplicate' => 'Listed more than once',
		'not_org'   => 'Not an animal welfare organisation',
		'other'     => 'Something else',
	);
}

/** Reasons from before 21 Oct 2026, for reports made then. */
const AW_REPORT_OLD_REASONS = array( 'contact' => 'Phone, email or website is wrong', 'location' => 'Location is wrong' );

/** Item types a report can be about: post type => Type column. */
const AW_REPORT_TYPES = array( 'aw_listing' => 'Listing', 'aw_news' => 'News', 'aw_legal' => 'Library', 'post' => 'Resource' );

/** Statuses: New → Fixed / No change ("resolved" from before 21 Oct 2026 shows as Fixed). */
const AW_REPORT_STATUSES = array( 'new' => 'New', 'fixed' => 'Fixed', 'no_change' => 'No change' );

add_action(
	'init',
	function () {
		register_post_type(
			'aw_report',
			array(
				'labels'          => array( 'name' => 'Reports', 'singular_name' => 'Report', 'menu_name' => 'Reports', 'all_items' => 'Reports' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false, // retired (owner, 21 Oct 2026): reports are Fluent Forms entries (#7)
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // administrators only, as for volunteers
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/** A published item a report can be about, or null. */
function aw_report_item( $id ) {
	$p = $id ? get_post( (int) $id ) : null;
	return $p && isset( AW_REPORT_TYPES[ $p->post_type ] ) && 'publish' === $p->post_status ? $p : null;
}

/** The report form (S7.3), the Fluent Forms form. $item: the item it is about, or null (then it asks for the page link). */
function aw_report_form( $item, $reason = '' ) {
	$reason = isset( aw_report_reasons()[ $reason ] ) ? $reason : '';
	return aw_ff_form( 'report', array( 'item' => $item, 'listing' => $item && 'aw_listing' === $item->post_type ? $item : null, 'reason' => $reason ) );
}

/** Back-compat: [aw_report_box] prints the report form of the current item. */
add_shortcode( 'aw_report_box', fn() => get_post() ? aw_report_form( aw_report_item( get_the_ID() ) ) : '' );

/* ---------- Admin review ---------- */

/** A report's status ("resolved" from before is Fixed). */
function aw_report_status( $id ) {
	$st = (string) get_post_meta( $id, 'aw_r_status', true );
	return 'resolved' === $st ? 'fixed' : ( isset( AW_REPORT_STATUSES[ $st ] ) ? $st : 'new' );
}

add_filter( 'manage_aw_report_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Item', 'aw_type' => 'Type', 'aw_reason' => 'Reason', 'aw_details' => 'Details', 'aw_status' => 'Status', 'date' => 'Received' ) );
add_action(
	'manage_aw_report_posts_custom_column',
	function ( $col, $id ) {
		$item = (int) get_post_meta( $id, 'aw_r_item', true );
		$item = $item ? $item : (int) get_post_meta( $id, 'aw_r_listing', true );
		if ( 'aw_type' === $col ) {
			$type = (string) get_post_meta( $id, 'aw_r_type', true );
			echo esc_html( '' !== $type ? $type : ( $item ? 'Listing' : '' ) );
		} elseif ( 'aw_reason' === $col ) {
			$r = (string) get_post_meta( $id, 'aw_r_reason', true );
			echo esc_html( aw_report_reasons()[ $r ] ?? ( AW_REPORT_OLD_REASONS[ $r ] ?? $r ) );
		} elseif ( 'aw_details' === $col ) {
			echo esc_html( wp_trim_words( (string) get_post_meta( $id, 'aw_r_details', true ), 30 ) );
			$email = (string) get_post_meta( $id, 'aw_r_email', true );
			echo '' !== $email ? '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '';
		} elseif ( 'aw_status' === $col ) {
			$st = aw_report_status( $id );
			echo esc_html( AW_REPORT_STATUSES[ $st ] );
			if ( 'new' === $st ) {
				foreach ( array( 'fixed' => 'Mark fixed', 'no_change' => 'No change' ) as $to => $label ) {
					$url = wp_nonce_url( add_query_arg( array( 'action' => 'aw_report_status', 'report' => $id, 'to' => $to ), admin_url( 'admin-post.php' ) ), 'aw_report_status_' . $id );
					echo ' · <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				}
			}
			$page = (string) get_post_meta( $id, 'aw_r_page', true );
			if ( $item ) {
				echo ' · <a href="' . esc_url( (string) get_edit_post_link( $item ) ) . '">Edit item</a> · <a href="' . esc_url( (string) get_permalink( $item ) ) . '" target="_blank">View</a>';
			} elseif ( '' !== $page ) {
				echo ' · <a href="' . esc_url( $page ) . '" target="_blank" rel="noopener">View page</a>';
			}
		}
	},
	10,
	2
);

add_action(
	'admin_post_aw_report_status',
	function () {
		$id = (int) ( $_GET['report'] ?? 0 );
		$to = sanitize_key( wp_unslash( $_GET['to'] ?? '' ) );
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! check_admin_referer( 'aw_report_status_' . $id ) || 'aw_report' !== get_post_type( $id ) || ! isset( AW_REPORT_STATUSES[ $to ] ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		update_post_meta( $id, 'aw_r_status', $to );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=aw_report' ) );
		exit;
	}
);

// The report's own screen: what was sent (read-only); the visit details are on its Fluent Forms entry.
add_action(
	'add_meta_boxes_aw_report',
	function () {
		add_meta_box(
			'aw-report-sent',
			'Report',
			function ( $post ) {
				$r    = (string) get_post_meta( $post->ID, 'aw_r_reason', true );
				$rows = array(
					'Type'          => (string) get_post_meta( $post->ID, 'aw_r_type', true ),
					'Page'          => (string) get_post_meta( $post->ID, 'aw_r_page', true ),
					'What\'s wrong' => aw_report_reasons()[ $r ] ?? ( AW_REPORT_OLD_REASONS[ $r ] ?? $r ),
					'Details'       => (string) get_post_meta( $post->ID, 'aw_r_details', true ),
					'Email'         => (string) get_post_meta( $post->ID, 'aw_r_email', true ),
					'Status'        => AW_REPORT_STATUSES[ aw_report_status( $post->ID ) ],
					'Reference'     => (string) get_post_meta( $post->ID, 'aw_r_ref', true ),
				);
				echo '<table class="form-table" role="presentation"><tbody>';
				foreach ( $rows as $k => $v ) {
					echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( '' !== $v ? $v : '—' ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			},
			'aw_report',
			'normal',
			'high'
		);
	}
);
