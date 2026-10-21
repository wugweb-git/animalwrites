<?php
/**
 * "Report a mistake" for every page (owner, 21 Oct 2026): /report/?item=<ID> from any listing, News, Library or Resource
 * item (/report/?listing=<ID> still works); opened on its own it asks for the page link.
 *
 * Fields: the item (hidden), what's wrong, details (up to 1,000 characters), an optional email (only to reply about the
 * report) and the spam check. Saved as a private aw_report with status New → Fixed / No change, its Type (Listing,
 * News, Library, Resource, or Page when only a link was given), its ticks and visit block (kits-core forms.php).
 * Nothing on the item changes automatically. Administrators only (Inbox → Reports).
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
				'show_in_menu'    => 'aw-inbox', // Inbox → Reports (S2.3)
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

/** The item a page link points to on this site, or null. */
function aw_report_item_from_url( $url ) {
	$url = (string) $url;
	if ( '' === $url || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return null;
	}
	return aw_report_item( url_to_postid( $url ) );
}

/** The report form (S7.3). $item: the item it is about, or null (then it asks for the page link). */
function aw_report_form( $item, $reason = '' ) {
	$reason = isset( aw_report_reasons()[ $reason ] ) ? $reason : '';
	$ff     = apply_filters( 'aw_form_html', '', 'report', array( 'item' => $item, 'listing' => $item && 'aw_listing' === $item->post_type ? $item : null, 'reason' => $reason ) ); // the Fluent Forms version when it is set up (forms-fluentforms.php)
	if ( '' !== $ff ) {
		return $ff;
	}
	$form  = 'aw_report';
	$state = aw_form_state( $form );
	$html  = aw_form_open( $form, array( 'class' => 'kit-form aw-report-form', 'id' => 'aw-form-aw_report', 'data-key' => $item ? (string) $item->ID : '', 'data-source' => $item ? trim( wp_make_link_relative( (string) get_permalink( $item ) ), '/' ) : 'report' ) )
		. '<input type="hidden" name="item" value="' . esc_attr( $item ? (string) $item->ID : '' ) . '">';
	if ( ! $item ) {
		$html .= aw_field( $form, array( 'name' => 'page', 'label' => 'Page link', 'type' => 'url', 'required' => true, 'hint' => 'The address of the page with the mistake.', 'attrs' => array( 'placeholder' => home_url( '/news/…' ), 'inputmode' => 'url' ), 'messages' => array( 'required' => 'Add the link to the page' ) ) );
	}
	$pick  = (string) ( $state['values']['reason'] ?? $reason );
	$html .= aw_field( $form, array( 'name' => 'reason', 'label' => 'What\'s wrong?', 'type' => 'select', 'required' => true, 'value' => $pick, 'options' => array( '' => 'Choose one' ) + aw_report_reasons(), 'messages' => array( 'required' => 'Choose what is wrong' ) ) )
		. aw_field( $form, array( 'name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'hint' => 'Tell us the correct details if you know them.', 'attrs' => array( 'maxlength' => 1000, 'rows' => 4 ) ) )
		. kits_dict_field( $form, 'email', array( 'required' => false, 'hint' => 'Optional. Only to reply about this report.' ) )
		. '<div class="kit-form-actions"><button type="submit" class="kit-btn">Send report</button></div></form>';
	return $html;
}

/** Back-compat: [aw_report_box] prints the report form of the current item. */
add_shortcode( 'aw_report_box', fn() => get_post() ? aw_report_form( aw_report_item( get_the_ID() ) ) : '' );

/** A report's values from submitted data (already unslashed). */
function aw_report_values( $in ) {
	return array(
		'reason'  => sanitize_key( (string) ( $in['reason'] ?? '' ) ),
		'details' => sanitize_textarea_field( (string) ( $in['details'] ?? '' ) ),
		'email'   => aw_clean_email( sanitize_text_field( (string) ( $in['email'] ?? '' ) ) ),
		'page'    => substr( esc_url_raw( trim( (string) ( $in['page'] ?? '' ) ) ), 0, 500 ),
	);
}

/** Server rules for a report: field => message. $item: the item it is about, or null (the page link is then needed). */
function aw_report_errors( $v, $item = null ) {
	$rules  = array( 'reason' => array( 'required' ), 'details' => array( 'max:1000' ), 'email' => array( 'email' ) );
	$msgs   = array( 'reason' => array( 'required' => 'Choose what is wrong' ), 'email' => array( 'email' => 'Enter a full email address, like you@example.com' ) );
	$labels = array( 'reason' => 'What\'s wrong?', 'details' => 'Details', 'email' => 'Email', 'page' => 'Page link' );
	if ( ! $item ) {
		$rules['page'] = array( 'required' );
		$msgs['page']  = array( 'required' => 'Add the link to the page' );
	}
	$errors = aw_form_validate( $rules, $v, $labels, $msgs );
	if ( $v['reason'] && ! isset( aw_report_reasons()[ $v['reason'] ] ) ) {
		$errors['reason'] = 'Choose what is wrong';
	}
	if ( ! $item && '' !== $v['page'] && wp_parse_url( $v['page'], PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$errors['page'] = 'Use the link of a page on ' . wp_parse_url( home_url(), PHP_URL_HOST );
	}
	return $errors;
}

/**
 * Save a checked report (Inbox > Reports, status New) with its ticks and visit block. Nothing on the item changes.
 * Never calls aw_crm_sync(): the email is optional and only for replying about this report, so reporting a mistake is
 * not a reason to add or update anyone in FluentCRM.
 *
 * @param array        $v      Values.
 * @param WP_Post|null $item   The item (null: only a page link was given).
 * @param string       $source The page it was sent from.
 * @return array|false array{url, title, text}, or false when it could not be saved.
 */
function aw_report_save( $v, $item, $source ) {
	$item = $item ? $item : aw_report_item_from_url( $v['page'] );
	$id   = wp_insert_post( array( 'post_type' => 'aw_report', 'post_status' => 'private', 'post_title' => $item ? $item->post_title : ( '' !== $v['page'] ? $v['page'] : 'Report' ) ), true );
	if ( is_wp_error( $id ) ) {
		return false;
	}
	update_post_meta( $id, 'aw_r_item', $item ? $item->ID : 0 );
	update_post_meta( $id, 'aw_r_type', $item ? AW_REPORT_TYPES[ $item->post_type ] : 'Page' );
	if ( $item && 'aw_listing' === $item->post_type ) {
		update_post_meta( $id, 'aw_r_listing', $item->ID ); // as before (listing claims and reports read it)
	}
	update_post_meta( $id, 'aw_r_page', $item ? (string) get_permalink( $item ) : $v['page'] );
	update_post_meta( $id, 'aw_r_reason', $v['reason'] );
	update_post_meta( $id, 'aw_r_details', $v['details'] );
	update_post_meta( $id, 'aw_r_email', $v['email'] );
	update_post_meta( $id, 'aw_r_status', 'new' );
	update_post_meta( $id, 'aw_r_source', $source ); // the page it came from (S5.8)
	list( $visit, $ticks ) = aw_form_record( 'report', array( 'page' => $source ) );
	aw_record_post( $id, $visit, $ticks );
	$args = $item ? array( 'item' => (int) $item->ID, 'from' => false ) : array();
	return array( 'url' => kits_thanks_url( 'report', $args, 'Thanks — we\'ll check it.' ), 'title' => 'Thanks — we\'ll check it.', 'text' => '' );
}

/** Handle a report. */
function aw_report_submit() {
	$form   = 'aw_report';
	$item   = aw_report_item( (int) ( $_POST['item'] ?? ( $_POST['listing'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$back   = aw_report_url( $item ? $item->ID : 0 ); // errors: back to /report/?item=<ID>, details kept
	$done   = kits_thanks_url( 'report', $item ? array( 'item' => (int) $item->ID, 'from' => false ) : array() );
	$rl     = aw_form_guard( $form, $back, 'report', $done, 10 );
	$v      = aw_report_values( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in aw_form_guard()
	$errors = aw_report_errors( $v, $item );
	if ( $errors ) {
		aw_form_fail( $form, $errors, $v, $back, 'report' );
	}
	$res = aw_report_save( $v, $item, kits_submitted_source() );
	if ( ! $res ) {
		aw_form_fail( $form, array(), $v, $back, 'report', aw_form_generic_error( $form ) );
	}
	aw_form_count( $rl );
	aw_form_success( $res['url'], $res['title'] );
}
add_action( 'admin_post_nopriv_aw_report', 'aw_report_submit' );
add_action( 'admin_post_aw_report', 'aw_report_submit' );

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

// The report's own screen: what was sent (read-only), next to Visit details (kits-core forms.php).
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
				);
				echo '<table class="widefat striped"><tbody>';
				foreach ( $rows as $k => $v ) {
					echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td style="white-space:pre-wrap">' . esc_html( '' !== $v ? $v : '—' ) . '</td></tr>';
				}
				echo '</tbody></table>';
			},
			'aw_report',
			'normal',
			'high'
		);
	}
);
