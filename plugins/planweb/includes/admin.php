<?php
/**
 * PlanWeb → Calendar, the interim screen until the Kits Core shell ships (plan, section 5): the month grid from Dates,
 * a list of entries (WP_List_Table), the add and edit form, and the import check. Server-rendered with core markup.
 * The old address admin.php?page=aw-dates opens this screen.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLANWEB_MENU = 'planweb';

add_action(
	'admin_menu',
	function () {
		if ( ! planweb_on() ) {
			return;
		}
		add_menu_page( __( 'Calendar', 'planweb' ), __( 'PlanWeb', 'planweb' ), 'edit_planweb_entries', PLANWEB_MENU, 'planweb_screen', 'dashicons-calendar-alt', 3 );
		add_submenu_page( PLANWEB_MENU, __( 'Calendar', 'planweb' ), __( 'Calendar', 'planweb' ), 'edit_planweb_entries', PLANWEB_MENU, 'planweb_screen' );
		// Kits → Dates moved here: the old address (with its month) opens the Calendar.
		$hook = add_submenu_page( '', __( 'Dates', 'planweb' ), '', 'edit_planweb_entries', 'aw-dates', '__return_empty_string' );
		add_action(
			'load-' . $hook,
			function () {
				$m = planweb_month_arg( $_GET['m'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- a redirect; planweb_month_arg() sanitizes.
				wp_safe_redirect( planweb_url( '' !== $m ? array( 'm' => $m ) : array() ) );
				exit;
			}
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( planweb_on() && ( 'toplevel_page_' . PLANWEB_MENU === $hook || 'index.php' === $hook ) ) {
			wp_enqueue_style( 'planweb-admin', PLANWEB_URL . 'assets/admin.css', array(), PLANWEB_VERSION );
		}
	}
);

/**
 * A month (Y-m) from the request, or ''.
 *
 * @param mixed $raw Raw request value.
 */
function planweb_month_arg( $raw ): string {
	$m = is_string( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '';
	return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $m ) ? $m : '';
}

/**
 * The Calendar's address.
 *
 * @param array $args Query arguments (m, edit, type, status, done).
 */
function planweb_url( array $args = array() ): string {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . PLANWEB_MENU ) );
}

/**
 * A date in the site's one format, j M Y.
 *
 * @param string $ymd Y-m-d.
 */
function planweb_date_label( string $ymd ): string {
	return wp_date( 'j M Y', strtotime( $ymd . ' 12:00:00' ) );
}

/**
 * "today", "tomorrow", "in 12 days" or "3 days ago".
 *
 * @param string $ymd Y-m-d.
 */
function planweb_when( string $ymd ): string {
	$days = (int) round( ( strtotime( $ymd . ' 12:00:00' ) - strtotime( wp_date( 'Y-m-d' ) . ' 12:00:00' ) ) / DAY_IN_SECONDS );
	if ( 0 === $days ) {
		return __( 'today', 'planweb' );
	}
	if ( 1 === $days ) {
		return __( 'tomorrow', 'planweb' );
	}
	/* translators: %d: number of days. */
	return $days > 0 ? sprintf( _n( 'in %d day', 'in %d days', $days, 'planweb' ), $days ) : sprintf( _n( '%d day ago', '%d days ago', abs( $days ), 'planweb' ), abs( $days ) );
}

/**
 * The Type label with its mark (shape and text, so colour is never the only signal).
 *
 * @param string $type Type slug.
 */
function planweb_type_badge( string $type ): string {
	$type = isset( PLANWEB_TYPES[ $type ] ) ? $type : 'reminder';
	return '<span class="planweb-type planweb-type--' . esc_attr( $type ) . '"><span class="planweb-mark" aria-hidden="true"></span>' . esc_html( PLANWEB_TYPES[ $type ] ) . '</span>';
}

/**
 * The Status label.
 *
 * @param string $status Status slug.
 */
function planweb_status_badge( string $status ): string {
	return '<span class="planweb-status planweb-status--' . esc_attr( $status ) . '">' . esc_html( PLANWEB_STATUSES[ $status ] ?? $status ) . '</span>';
}

/** PlanWeb → Calendar. */
function planweb_screen() {
	if ( ! current_user_can( 'edit_planweb_entries' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'planweb' ), 403 );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filters and messages only; every change is a nonce-checked admin-post.
	$month = planweb_month_arg( $_GET['m'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- planweb_month_arg() sanitizes.
	$month = '' !== $month ? $month : wp_date( 'Y-m' );
	$edit  = isset( $_GET['edit'] ) && 'publish' === get_post_status( absint( $_GET['edit'] ) ) ? planweb_entry( absint( $_GET['edit'] ) ) : null; // Not from the Trash.
	$done  = isset( $_GET['done'] ) ? sanitize_key( $_GET['done'] ) : '';
	$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
	// phpcs:enable

	echo '<div class="wrap planweb"><h1 class="wp-heading-inline">' . esc_html__( 'Calendar', 'planweb' ) . '</h1> <a class="page-title-action" href="' . esc_url( planweb_url( array( 'm' => $month ) ) . '#planweb-form' ) . '">' . esc_html__( 'Add entry', 'planweb' ) . '</a><hr class="wp-header-end">';
	$messages = array(
		'saved'   => __( 'Entry saved.', 'planweb' ),
		'trashed' => __( 'Entry moved to the Trash.', 'planweb' ),
		'import'  => __( 'Import run.', 'planweb' ),
	);
	if ( isset( $messages[ $done ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $done ] ) . '</p></div>';
	}
	if ( '' !== $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	}
	echo '<p class="description">' . esc_html__( 'Nothing on this calendar is posted or sent: it is the team\'s plan. Approved and Sent are set by an administrator.', 'planweb' ) . '</p>';

	planweb_month_grid( $month );

	echo '<h2>' . esc_html__( 'All entries', 'planweb' ) . '</h2>';
	require_once PLANWEB_DIR . 'includes/class-planweb-list-table.php';
	$table = new PlanWeb_List_Table();
	$table->prepare_items();
	echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( PLANWEB_MENU ) . '"><input type="hidden" name="m" value="' . esc_attr( $month ) . '">';
	$table->display();
	echo '</form>';

	planweb_form( $edit, $month );
	if ( current_user_can( 'manage_options' ) ) {
		planweb_import_panel();
	}
	echo '</div>';
}

/**
 * The month grid (as Dates had it): Monday first, today marked, each item with its Type and Status.
 *
 * @param string $month Y-m.
 */
function planweb_month_grid( string $month ) {
	$first = strtotime( $month . '-01 12:00:00' );
	$prev  = wp_date( 'Y-m', strtotime( '-1 month', $first ) );
	$next  = wp_date( 'Y-m', strtotime( '+1 month', $first ) );
	$days  = (int) wp_date( 't', $first );
	$lead  = (int) wp_date( 'N', $first ) - 1;
	$today = wp_date( 'Y-m-d' );
	$by    = array();
	foreach ( planweb_entries( $month . '-01', sprintf( '%s-%02d', $month, $days ) ) as $e ) {
		$by[ $e['date'] ][] = $e;
	}
	echo '<p class="planweb-nav"><a class="button" href="' . esc_url( planweb_url( array( 'm' => $prev ) ) ) . '">‹ ' . esc_html( wp_date( 'F', strtotime( $prev . '-01 12:00:00' ) ) ) . '</a> <strong>' . esc_html( wp_date( 'F Y', $first ) ) . '</strong> <a class="button" href="' . esc_url( planweb_url( array( 'm' => $next ) ) ) . '">' . esc_html( wp_date( 'F', strtotime( $next . '-01 12:00:00' ) ) ) . ' ›</a> <a class="button" href="' . esc_url( planweb_url() ) . '">' . esc_html__( 'Today', 'planweb' ) . '</a></p>';
	echo '<table class="widefat fixed planweb-month"><caption class="screen-reader-text">' . esc_html( wp_date( 'F Y', $first ) ) . '</caption><thead><tr>';
	foreach ( array( __( 'Mon', 'planweb' ), __( 'Tue', 'planweb' ), __( 'Wed', 'planweb' ), __( 'Thu', 'planweb' ), __( 'Fri', 'planweb' ), __( 'Sat', 'planweb' ), __( 'Sun', 'planweb' ) ) as $h ) {
		echo '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody><tr>';
	for ( $i = 0; $i < $lead; $i++ ) {
		echo '<td class="alternate"></td>';
	}
	for ( $day = 1; $day <= $days; $day++ ) {
		$ymd = sprintf( '%s-%02d', $month, $day );
		if ( 0 === ( $lead + $day - 1 ) % 7 && $day > 1 ) {
			echo '</tr><tr>';
		}
		$class = $ymd === $today ? 'highlight' : ( $ymd < $today ? 'planweb-past' : '' );
		echo '<td class="' . esc_attr( $class ) . '"' . ( $ymd === $today ? ' aria-current="date"' : '' ) . '><strong>' . (int) $day . '</strong>';
		foreach ( $by[ $ymd ] ?? array() as $e ) {
			$href = planweb_url(
				array(
					'm'    => $month,
					'edit' => $e['id'],
				)
			) . '#planweb-form';
			$item = planweb_type_badge( $e['type'] ) . ' <a href="' . esc_url( $href ) . '">' . esc_html( ( '' !== $e['time'] ? $e['time'] . ' ' : '' ) . $e['title'] ) . '</a> ' . planweb_status_badge( $e['status'] );
			echo '<p class="planweb-item">' . $item . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the badges escape their parts.
		}
		echo '</td>';
	}
	for ( $i = ( 7 - ( $lead + $days ) % 7 ) % 7; $i > 0; $i-- ) {
		echo '<td class="alternate"></td>';
	}
	echo '</tr></tbody></table>';
}

/**
 * A select box over a fixed list.
 *
 * @param string $name     Field name.
 * @param array  $values   slug => label.
 * @param string $value    Current slug.
 * @param string $none     Label of the empty choice, or '' for none.
 * @param array  $disabled Slugs shown but not selectable.
 */
function planweb_select( string $name, array $values, string $value, string $none = '', array $disabled = array() ): string {
	$h = '<select name="' . esc_attr( $name ) . '" id="planweb-' . esc_attr( $name ) . '">';
	if ( '' !== $none ) {
		$h .= '<option value="">' . esc_html( $none ) . '</option>';
	}
	foreach ( $values as $slug => $label ) {
		$h .= '<option value="' . esc_attr( $slug ) . '"' . selected( $value, $slug, false ) . disabled( in_array( $slug, $disabled, true ) && $slug !== $value, true, false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $h . '</select>';
}

/**
 * The add or edit form.
 *
 * @param array|null $e     Entry being edited.
 * @param string     $month Month shown (to come back to).
 */
function planweb_form( $e, string $month ) {
	$e   = $e ? $e : array(
		'id'      => 0,
		'date'    => '',
		'time'    => '',
		'title'   => '',
		'note'    => '',
		'type'    => 'reminder',
		'channel' => '',
		'owner'   => '',
		'status'  => 'idea',
		'link'    => '',
	);
	$off = array( 'due' );
	if ( ! current_user_can( 'planweb_approve' ) ) {
		$off = array_merge( $off, PLANWEB_APPROVE_STATUSES );
	}
	echo '<h2 id="planweb-form">' . esc_html( $e['id'] ? __( 'Edit entry', 'planweb' ) : __( 'Add an entry', 'planweb' ) ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="planweb_save"><input type="hidden" name="id" value="' . (int) $e['id'] . '"><input type="hidden" name="m" value="' . esc_attr( $month ) . '">';
	wp_nonce_field( 'planweb_save_' . (int) $e['id'] );
	echo '<table class="form-table" role="presentation"><tbody>';
	$rows = array(
		'date'    => array( __( 'Date', 'planweb' ), '<input type="date" name="date" id="planweb-date" required value="' . esc_attr( $e['date'] ) . '"> <label for="planweb-time" class="screen-reader-text">' . esc_html__( 'Time', 'planweb' ) . '</label><input type="time" name="time" id="planweb-time" value="' . esc_attr( $e['time'] ) . '"> <span class="description">' . esc_html__( 'Time is optional (IST).', 'planweb' ) . '</span>' ),
		'title'   => array( __( 'Title', 'planweb' ), '<input type="text" name="title" id="planweb-title" class="regular-text" required maxlength="120" value="' . esc_attr( $e['title'] ) . '">' ),
		'note'    => array( __( 'Note', 'planweb' ), '<textarea name="note" id="planweb-note" rows="3" class="large-text">' . esc_textarea( $e['note'] ) . '</textarea><p class="description">' . esc_html__( 'No email addresses, names or phone numbers.', 'planweb' ) . '</p>' ),
		'type'    => array( __( 'Type', 'planweb' ), planweb_select( 'type', PLANWEB_TYPES, $e['type'] ) ),
		'channel' => array( __( 'Channel', 'planweb' ), planweb_select( 'channel', PLANWEB_CHANNELS, $e['channel'], __( '—', 'planweb' ) ) ),
		'owner'   => array( __( 'Owner', 'planweb' ), planweb_select( 'owner', PLANWEB_OWNERS, $e['owner'], __( '—', 'planweb' ) ) ),
		'status'  => array( __( 'Status', 'planweb' ), planweb_select( 'status', PLANWEB_STATUSES, $e['status'], '', $off ) ),
		'link'    => array( __( 'Link', 'planweb' ), '<input type="url" name="link" id="planweb-link" class="regular-text" value="' . esc_attr( $e['link'] ) . '"><p class="description">' . esc_html__( 'A page on this site, or an X, LinkedIn, Instagram, Facebook, Threads, Reddit or WhatsApp channel address.', 'planweb' ) . '</p>' ),
	);
	foreach ( $rows as $k => $r ) {
		echo '<tr><th scope="row"><label for="planweb-' . esc_attr( $k ) . '">' . esc_html( $r[0] ) . '</label></th><td>' . $r[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	}
	echo '</tbody></table>';
	submit_button( $e['id'] ? __( 'Save entry', 'planweb' ) : __( 'Add entry', 'planweb' ), 'primary', 'submit', false );
	if ( $e['id'] ) {
		echo ' <a class="button" href="' . esc_url( planweb_url( array( 'm' => $month ) ) ) . '">' . esc_html__( 'Cancel', 'planweb' ) . '</a>';
		$revisions = wp_get_post_revisions( $e['id'], array( 'fields' => 'ids' ) );
		if ( $revisions && current_user_can( 'edit_post', $e['id'] ) ) {
			echo ' <a href="' . esc_url( admin_url( 'revision.php?revision=' . (int) reset( $revisions ) ) ) . '">' . esc_html__( 'Revisions', 'planweb' ) . '</a>';
		}
	}
	echo '</form>';
}

add_action(
	'admin_post_planweb_save',
	function () {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! check_admin_referer( 'planweb_save_' . $id ) || ! current_user_can( $id ? 'edit_post' : 'edit_planweb_entries', $id ) || ( $id && ( ! planweb_entry( $id ) || 'publish' !== get_post_status( $id ) ) ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'planweb' ), 403 );
		}
		$f = array();
		foreach ( array( 'date', 'time', 'title', 'type', 'channel', 'owner', 'status', 'link' ) as $k ) {
			$f[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		}
		$f['link'] = esc_url_raw( wp_unslash( $_POST['link'] ?? '' ) );
		$f['note'] = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$month     = planweb_month_arg( $_POST['m'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- planweb_month_arg() sanitizes.
		$saved     = planweb_save_entry( $f, $id );
		if ( is_wp_error( $saved ) ) {
			wp_safe_redirect(
				planweb_url(
					array_filter(
						array(
							'm'     => $month,
							'edit'  => $id ? $id : '',
							'error' => rawurlencode( $saved->get_error_message() ),
						)
					)
				) . '#planweb-form'
			);
			exit;
		}
		wp_safe_redirect(
			planweb_url(
				array(
					'm'    => substr( $f['date'], 0, 7 ),
					'done' => 'saved',
				)
			)
		);
		exit;
	}
);

add_action(
	'admin_post_planweb_trash',
	function () {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! check_admin_referer( 'planweb_trash_' . $id ) || ! planweb_entry( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'planweb' ), 403 );
		}
		wp_trash_post( $id );
		wp_safe_redirect( planweb_url( array( 'done' => 'trashed' ) ) );
		exit;
	}
);

add_action(
	'admin_post_planweb_import',
	function () {
		if ( ! check_admin_referer( 'planweb_import' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'planweb' ), 403 );
		}
		planweb_import();
		wp_safe_redirect( planweb_url( array( 'done' => 'import' ) ) . '#planweb-import' );
		exit;
	}
);

/** Import from Dates: what the import did, and the one-for-one check (administrators). */
function planweb_import_panel() {
	$r = (array) get_option( 'planweb_import_result', array() );
	$v = planweb_verify();
	echo '<h2 id="planweb-import">' . esc_html__( 'Import from Dates', 'planweb' ) . '</h2>';
	if ( ! $r ) {
		echo '<p>' . esc_html__( 'Not run yet: it runs on the first admin page after PlanWeb is switched on.', 'planweb' ) . '</p>';
	} else {
		/* translators: 1: date and time, 2: elements in Dates, 3: entries made, 4: already there. */
		echo '<p>' . esc_html( sprintf( __( 'Last run %1$s: %2$d dates in Dates, %3$d entries made, %4$d already there.', 'planweb' ), wp_date( 'j M Y, g:i a', (int) $r['time'] ), (int) $r['stored'], (int) $r['created'], (int) $r['skipped'] ) ) . '</p>';
		foreach ( (array) ( $r['failed'] ?? array() ) as $f ) {
			/* translators: 1: position in the Dates list, 2: error. */
			echo '<div class="notice notice-error inline"><p>' . esc_html( sprintf( __( 'Date %1$d was not imported: %2$s', 'planweb' ), (int) $f['index'] + 1, (string) $f['error'] ) ) . '</p></div>';
		}
		if ( ! empty( $r['personal'] ) ) {
			/* translators: %s: entry IDs. */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'These imported entries look as if they hold an email address or a phone number; check them: %s', 'planweb' ), implode( ', ', array_map( 'intval', (array) $r['personal'] ) ) ) ) . '</p></div>';
		}
	}
	$ok = 0 === $v['differs'] + $v['missing'] + $v['extra'];
	/* translators: 1: same, 2: differs, 3: missing, 4: extra. */
	$line = sprintf( __( 'Check now: %1$d the same, %2$d different, %3$d missing, %4$d extra.', 'planweb' ), $v['same'], $v['differs'], $v['missing'], $v['extra'] );
	echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-warning' ) . ' inline"><p>' . esc_html( $line ) . ' ' . esc_html__( 'An entry changed or moved to the Trash since the import shows as different or missing.', 'planweb' ) . '</p></div>';
	foreach ( $v['invalid'] as $bad ) {
		/* translators: 1: position in the Dates list, 2: the stored value. */
		echo '<p>' . esc_html( sprintf( __( 'Dates element %1$d is not a date and was not imported: %2$s', 'planweb' ), (int) $bad['index'] + 1, (string) $bad['value'] ) ) . '</p>';
	}
	$odd = array_filter( $v['lines'], fn( $l ) => 'same' !== $l['state'] );
	if ( $odd ) {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Check', 'planweb' ) . '</th><th scope="col">' . esc_html__( 'In Dates', 'planweb' ) . '</th><th scope="col">' . esc_html__( 'In PlanWeb', 'planweb' ) . '</th></tr></thead><tbody>';
		foreach ( $odd as $l ) {
			$side = fn( $x ) => $x ? esc_html( $x['date'] . ' · ' . $x['title'] ) . ( '' !== $x['note'] ? '<br><span class="description">' . esc_html( $x['note'] ) . '</span>' : '' ) : '—';
			echo '<tr><td>' . esc_html( $l['state'] ) . '</td><td>' . $side( $l['dates'] ) . '</td><td>' . $side( $l['entry'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $side escapes.
		}
		echo '</tbody></table>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="planweb_import">';
	wp_nonce_field( 'planweb_import' );
	echo '<p><button class="button">' . esc_html__( 'Run the import again', 'planweb' ) . '</button> <span class="description">' . esc_html__( 'Adds only dates not imported yet; changes nothing in Dates.', 'planweb' ) . '</span></p></form>';
}
