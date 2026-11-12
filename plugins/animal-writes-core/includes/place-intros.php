<?php
/**
 * Place intros: a short paragraph on a Directory state or district page, under the page's fixed intro line
 * (aw_directory_intro(), which stays as it is and is still the meta and share description).
 *
 * Kept in the option aw_place_intros, one entry per place, keyed by kind and LGD code, because states and districts
 * have separate code lists ("state:27", "district:519"; Delhi is a state page):
 *   {text, sources (list of URLs), checked (Y-m-d)}
 * The text is plain words with links (<a href> only). Sources and the checked date are for the editor; the page shows
 * only the text. An entry with no text is removed, and a place with no entry shows nothing.
 *
 * Shown on page 1 of a place view with no filter (type, service, name, Claimed or Verified). Edited on DirectoryWeb → Place intros
 * (admin.php?page=directoryweb-place-intros, manage_options) or with `wp aw-place-intro`.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_PLACE_INTROS      = 'aw_place_intros';
const AW_PLACE_INTROS_PAGE = 'directoryweb-place-intros';
const AW_PLACE_INTRO_MAX   = 1000; // characters of visible text: a short paragraph, not an article
const AW_PLACE_INTRO_SRCS  = 10;

/* ---------- Store ---------- */

/** The entry key of a place: "state:{code}" or "district:{code}". */
function aw_place_intro_key( $place ): string {
	return ( 'district' === $place->kind ? 'district' : 'state' ) . ':' . (int) $place->code;
}

/** The place an entry key names, or null when the key is not a state or district in the LGD file. */
function aw_place_intro_place_of( $key ) {
	if ( ! is_string( $key ) || ! preg_match( '/^(state|district):(\d{1,6})$/', $key, $m ) ) {
		return null;
	}
	$place = 'state' === $m[1] ? aw_place_state( (int) $m[2] ) : aw_place_district( (int) $m[2] );
	return $place && $m[1] === $place->kind ? $place : null;
}

/** Every stored entry, key => {text, sources, checked}. */
function aw_place_intros(): array {
	$all = get_option( AW_PLACE_INTROS, array() );
	return is_array( $all ) ? $all : array();
}

/** One place's entry ({text, sources, checked}), or an empty array. */
function aw_place_intro( $place ): array {
	$all = aw_place_intros();
	$e   = $all[ aw_place_intro_key( $place ) ] ?? array();
	return is_array( $e ) && '' !== trim( (string) ( $e['text'] ?? '' ) ) ? $e : array();
}

/** The text with links only (<a href>); every other tag is removed. */
function aw_place_intro_kses( $text ): string {
	return wp_kses( (string) $text, array( 'a' => array( 'href' => true ) ) );
}

/**
 * A clean entry from raw input, or a WP_Error.
 *
 * @param string       $text    Text (links allowed).
 * @param string|array $sources URLs, a list or one per line.
 * @param string       $checked Date, Y-m-d or ''.
 * @return array|WP_Error {text, sources, checked}; text '' means "remove".
 */
function aw_place_intro_clean( $text, $sources, $checked ) {
	$text = (string) $text;
	$flat = '' === wp_check_invalid_utf8( $text ) ? null : preg_replace( '/\s+/u', ' ', force_balance_tags( aw_place_intro_kses( $text ) ) ); // one paragraph, every link closed
	if ( null === $flat && '' !== trim( $text ) ) { // a stray byte (text pasted from another encoding) must not turn a save into a removal
		return new WP_Error( 'aw_place_intro_utf8', __( 'The intro has characters that are not text (often from a paste). Type them again. Nothing was saved.', 'directoryweb' ) );
	}
	$text = trim( (string) $flat );
	if ( mb_strlen( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) > AW_PLACE_INTRO_MAX ) {
		/* translators: %d: number of characters. */
		return new WP_Error( 'aw_place_intro_long', sprintf( __( 'The intro is longer than %d characters. Nothing was saved.', 'directoryweb' ), AW_PLACE_INTRO_MAX ) );
	}
	$list = is_array( $sources ) ? $sources : preg_split( '/[\r\n]+/', (string) $sources );
	$urls = array();
	foreach ( $list as $u ) {
		$u = esc_url_raw( trim( (string) $u ), array( 'http', 'https' ) );
		if ( '' !== $u && ! in_array( $u, $urls, true ) ) {
			$urls[] = $u;
		}
	}
	$checked = trim( (string) $checked );
	if ( '' !== $checked ) {
		$d = DateTime::createFromFormat( '!Y-m-d', $checked );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $checked ) {
			return new WP_Error( 'aw_place_intro_date', __( 'The checked date is not a date (YYYY-MM-DD). Nothing was saved.', 'directoryweb' ) );
		}
	}
	return array(
		'text'    => $text,
		'sources' => array_slice( $urls, 0, AW_PLACE_INTRO_SRCS ),
		'checked' => $checked,
	);
}

/**
 * Store a clean entry for a key; an entry with no text removes the key.
 *
 * @param string $key   Entry key (aw_place_intro_key()).
 * @param array  $entry Clean entry (aw_place_intro_clean()).
 * @return bool False when the key is not a place.
 */
function aw_place_intro_store( $key, array $entry ): bool {
	if ( ! aw_place_intro_place_of( $key ) ) {
		return false;
	}
	$all = aw_place_intros();
	if ( '' === (string) ( $entry['text'] ?? '' ) ) {
		unset( $all[ $key ] );
	} else {
		$all[ $key ] = $entry;
	}
	ksort( $all, SORT_NATURAL );
	update_option( AW_PLACE_INTROS, $all, false ); // read only on place pages and this screen
	do_action( 'litespeed_purge_url', aw_directory_url( aw_place_intro_place_of( $key )->slug ) ); // the page shows the change now, not when its cached copy runs out
	return true;
}

/* ---------- Page ---------- */

/** The place whose page shows its intro on this request (page 1, no type, service, name, Claimed or Verified filter), or null. */
function aw_place_intro_place() {
	if ( ! aw_is_directory_view() || get_query_var( 'aw_dir_type' ) || aw_request_tags() || ! empty( $_GET['q'] ) || ! empty( $_GET['verified'] ) || ! empty( $_GET['gov'] ) || aw_hub_page() > 1 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: is the list filtered
		return null;
	}
	return aw_current_place();
}

/** The intro paragraph for the current place page, or ''. */
function aw_place_intro_html(): string {
	$place = aw_place_intro_place();
	$entry = $place ? aw_place_intro( $place ) : array();
	if ( ! $entry ) {
		return '';
	}
	return '<p class="kit-section-desc">' . aw_place_intro_kses( $entry['text'] ) . '</p>';
}

/* ---------- DirectoryWeb → Place intros ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'Place intros', 'directoryweb' ), __( 'Place intros', 'directoryweb' ), 'manage_options', AW_PLACE_INTROS_PAGE, 'aw_place_intros_page' );
	},
	11 // after DirectoryWeb's own menu (directoryweb-ui.php)
);

/** The screen's address, with extra query args. */
function aw_place_intros_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => AW_PLACE_INTROS_PAGE ), $args ), admin_url( 'admin.php' ) );
}

/** A place's name as the screen shows it: "Mumbai, Maharashtra" or "Kerala". */
function aw_place_intro_label( $place ): string {
	return 'district' === $place->kind ? $place->name . ', ' . aw_place_state( $place->state )->name : $place->name;
}

/** The screen: the stored intros and a place picker, or one place's form. */
function aw_place_intros_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'directoryweb' ), 403 );
	}
	$key   = sanitize_text_field( wp_unslash( $_GET['place'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which form to show; saving checks its own nonce
	$place = '' !== $key ? aw_place_intro_place_of( $key ) : null;
	$msg   = sanitize_key( wp_unslash( $_GET['aw_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the notice after a save
	echo '<div class="wrap"><h1>' . esc_html__( 'Place intros', 'directoryweb' ) . '</h1>';
	if ( function_exists( 'kits_admin_fold_tabs' ) ) {
		kits_admin_fold_tabs( DIRECTORYWEB_PAGE, __( 'DirectoryWeb', 'directoryweb' ) ); // core tabs to the other DirectoryWeb screens
	}
	$notices = array(
		'saved'   => array( 'success', __( 'Intro saved.', 'directoryweb' ) ),
		'removed' => array( 'success', __( 'Intro removed. The page shows only its usual line.', 'directoryweb' ) ),
		'long'    => array( 'error', sprintf( /* translators: %d: number of characters. */ __( 'The intro is longer than %d characters. Nothing was saved.', 'directoryweb' ), AW_PLACE_INTRO_MAX ) ),
		'date'    => array( 'error', __( 'The checked date is not a date (YYYY-MM-DD). Nothing was saved.', 'directoryweb' ) ),
		'utf8'    => array( 'error', __( 'The intro has characters that are not text (often from a paste). Type them again. Nothing was saved.', 'directoryweb' ) ),
	);
	if ( isset( $notices[ $msg ] ) ) {
		wp_admin_notice( esc_html( $notices[ $msg ][1] ), array( 'type' => $notices[ $msg ][0], 'dismissible' => true ) );
	}
	if ( '' !== $key && ! $place ) {
		wp_admin_notice( esc_html__( 'That place is not in the list of states and districts.', 'directoryweb' ), array( 'type' => 'error' ) );
	}
	if ( $place ) {
		aw_place_intro_form( $place );
	} else {
		aw_place_intros_list();
	}
	echo '</div>';
}

/** The stored intros as a core list table, and the picker that opens a place's form. */
function aw_place_intros_list(): void {
	echo '<p>' . esc_html__( 'A short paragraph under the usual line on a state or district page of the Directory, on its first page only. A place with no intro shows only its usual line.', 'directoryweb' ) . '</p>';
	echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( AW_PLACE_INTROS_PAGE ) . '">';
	echo '<label for="aw-pi-place">' . esc_html__( 'Place', 'directoryweb' ) . '</label> <select id="aw-pi-place" name="place"><optgroup label="' . esc_attr__( 'States and union territories', 'directoryweb' ) . '">';
	foreach ( aw_place_states() as $s ) {
		echo '<option value="' . esc_attr( aw_place_intro_key( $s ) ) . '">' . esc_html( $s->name ) . '</option>';
	}
	echo '</optgroup>';
	foreach ( aw_place_states() as $s ) {
		echo '<optgroup label="' . esc_attr( $s->name ) . '">';
		foreach ( aw_place_districts( $s->code ) as $d ) {
			echo '<option value="' . esc_attr( aw_place_intro_key( $d ) ) . '">' . esc_html( $d->name ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select> ';
	submit_button( __( 'Write or edit', 'directoryweb' ), 'secondary', '', false );
	echo '</form>';

	$rows = array();
	foreach ( aw_place_intros() as $k => $e ) {
		$p = aw_place_intro_place_of( (string) $k );
		if ( ! $p || ! is_array( $e ) ) {
			continue;
		}
		$text   = wp_strip_all_tags( (string) ( $e['text'] ?? '' ) );
		$rows[] = array(
			'place'   => '<strong><a href="' . esc_url( aw_place_intros_url( array( 'place' => $k ) ) ) . '">' . esc_html( aw_place_intro_label( $p ) ) . '</a></strong>'
				. '<div class="row-actions"><span class="edit"><a href="' . esc_url( aw_place_intros_url( array( 'place' => $k ) ) ) . '">' . esc_html__( 'Edit', 'directoryweb' ) . '</a> | </span>'
				. '<span class="view"><a href="' . esc_url( aw_directory_url( $p->slug ) ) . '">' . esc_html__( 'View page', 'directoryweb' ) . '</a></span></div>',
			'kind'    => esc_html( 'district' === $p->kind ? __( 'District', 'directoryweb' ) : __( 'State', 'directoryweb' ) ),
			'text'    => esc_html( wp_trim_words( $text, 24 ) ),
			'sources' => esc_html( number_format_i18n( count( (array) ( $e['sources'] ?? array() ) ) ) ),
			'checked' => esc_html( (string) ( $e['checked'] ?? '' ) ),
		);
	}
	echo '<h2>' . esc_html__( 'Intros on the site', 'directoryweb' ) . '</h2>';
	aw_core_simple_table(
		'aw-place-intros',
		array(
			'place'   => __( 'Place', 'directoryweb' ),
			'kind'    => __( 'Kind', 'directoryweb' ),
			'text'    => __( 'Intro', 'directoryweb' ),
			'sources' => __( 'Sources', 'directoryweb' ),
			'checked' => __( 'Checked', 'directoryweb' ),
		),
		$rows,
		array( 'empty' => __( 'No place has an intro yet.', 'directoryweb' ) )
	);
}

/** One place's form (core form table). */
function aw_place_intro_form( $place ): void {
	$key   = aw_place_intro_key( $place );
	$entry = aw_place_intros()[ $key ] ?? array();
	$entry = is_array( $entry ) ? $entry : array();
	$draft = get_transient( 'aw_place_intro_draft_' . get_current_user_id() );
	if ( is_array( $draft ) && ( $draft['key'] ?? '' ) === $key ) { // after a save that was refused: what the editor typed
		delete_transient( 'aw_place_intro_draft_' . get_current_user_id() );
		$entry = $draft;
	}
	echo '<h2>' . esc_html( aw_place_intro_label( $place ) ) . '</h2>';
	echo '<p><a href="' . esc_url( aw_place_intros_url() ) . '">' . esc_html__( 'All place intros', 'directoryweb' ) . '</a> | <a href="' . esc_url( aw_directory_url( $place->slug ) ) . '">' . esc_html__( 'View page', 'directoryweb' ) . '</a></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="aw_place_intro_save"><input type="hidden" name="place" value="' . esc_attr( $key ) . '">';
	wp_nonce_field( 'aw_place_intro_save_' . $key );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-pi-text">' . esc_html__( 'Intro', 'directoryweb' ) . '</label></th><td><textarea id="aw-pi-text" name="aw_text" rows="6" class="large-text" aria-describedby="aw-pi-text-d">' . esc_textarea( (string) ( $entry['text'] ?? '' ) ) . '</textarea>';
	/* translators: %d: number of characters. */
	echo '<p class="description" id="aw-pi-text-d">' . esc_html( sprintf( __( 'One short paragraph, up to %d characters. Links as <a href="https://…">words</a>; no other formatting. Leave empty to remove the intro.', 'directoryweb' ), AW_PLACE_INTRO_MAX ) ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-pi-src">' . esc_html__( 'Sources', 'directoryweb' ) . '</label></th><td><textarea id="aw-pi-src" name="aw_sources" rows="4" class="large-text code" aria-describedby="aw-pi-src-d">' . esc_textarea( implode( "\n", (array) ( $entry['sources'] ?? array() ) ) ) . '</textarea>';
	/* translators: %d: number of addresses. */
	echo '<p class="description" id="aw-pi-src-d">' . esc_html( sprintf( __( 'Where the facts come from, one address per line (up to %d). Kept for editors; not shown on the page.', 'directoryweb' ), AW_PLACE_INTRO_SRCS ) ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-pi-checked">' . esc_html__( 'Checked', 'directoryweb' ) . '</label></th><td><input type="date" id="aw-pi-checked" name="aw_checked" value="' . esc_attr( (string) ( $entry['checked'] ?? '' ) ) . '">';
	echo '<p class="description">' . esc_html__( 'The day the facts were last checked against the sources.', 'directoryweb' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Save intro', 'directoryweb' ) );
	echo '</form>';
}

add_action(
	'admin_post_aw_place_intro_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'directoryweb' ), 403 );
		}
		$key = sanitize_text_field( wp_unslash( $_POST['place'] ?? '' ) );
		check_admin_referer( 'aw_place_intro_save_' . $key );
		if ( ! aw_place_intro_place_of( $key ) ) {
			wp_die( esc_html__( 'That place is not in the list of states and districts.', 'directoryweb' ), 400 );
		}
		$entry = aw_place_intro_clean(
			wp_unslash( $_POST['aw_text'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kept to links by aw_place_intro_clean() (wp_kses)
			sanitize_textarea_field( wp_unslash( $_POST['aw_sources'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['aw_checked'] ?? '' ) )
		);
		if ( is_wp_error( $entry ) ) {
			$msg = array(
				'aw_place_intro_long' => 'long',
				'aw_place_intro_utf8' => 'utf8',
			)[ $entry->get_error_code() ] ?? 'date';
			set_transient(
				'aw_place_intro_draft_' . get_current_user_id(),
				array(
					'key'     => $key,
					'text'    => aw_place_intro_kses( wp_unslash( $_POST['aw_text'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kept to links by wp_kses
					'sources' => array_filter( array_map( 'esc_url_raw', preg_split( '/[\r\n]+/', sanitize_textarea_field( wp_unslash( $_POST['aw_sources'] ?? '' ) ) ) ) ),
					'checked' => sanitize_text_field( wp_unslash( $_POST['aw_checked'] ?? '' ) ),
				),
				10 * MINUTE_IN_SECONDS
			); // the form shows what was typed again, not the stored text
		} else {
			aw_place_intro_store( $key, $entry );
			$msg = '' === $entry['text'] ? 'removed' : 'saved';
		}
		wp_safe_redirect(
			aw_place_intros_url(
				array(
					'place'  => $key,
					'aw_msg' => $msg,
				)
			)
		);
		exit;
	}
);

/* ---------- WP-CLI ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Place intros on Directory state and district pages.
	 *
	 *     wp aw-place-intro list
	 *     wp aw-place-intro get district:519
	 *     wp aw-place-intro set district:519 --text="…" [--sources="https://a https://b"] [--checked=2026-11-12]
	 *     wp aw-place-intro delete district:519
	 */
	WP_CLI::add_command(
		'aw-place-intro',
		function ( $args, $assoc ) {
			$cmd = $args[0] ?? 'list';
			if ( 'list' === $cmd ) {
				foreach ( aw_place_intros() as $k => $e ) {
					$p = aw_place_intro_place_of( (string) $k );
					WP_CLI::line( $k . "\t" . ( $p ? aw_place_intro_label( $p ) : '?' ) . "\t" . ( $e['checked'] ?? '' ) . "\t" . wp_trim_words( wp_strip_all_tags( (string) ( $e['text'] ?? '' ) ), 12 ) );
				}
				return;
			}
			$key = (string) ( $args[1] ?? '' );
			if ( ! aw_place_intro_place_of( $key ) ) {
				WP_CLI::error( 'Give a place as state:{code} or district:{code} (LGD codes).' );
			}
			if ( 'get' === $cmd ) {
				WP_CLI::line( (string) wp_json_encode( aw_place_intros()[ $key ] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
				return;
			}
			if ( 'delete' === $cmd ) {
				aw_place_intro_store( $key, array( 'text' => '' ) );
				WP_CLI::success( 'Removed ' . $key . '.' );
				return;
			}
			if ( 'set' !== $cmd ) {
				WP_CLI::error( 'Use list, get, set or delete.' );
			}
			$entry = aw_place_intro_clean( (string) ( $assoc['text'] ?? '' ), preg_split( '/\s+/', trim( (string) ( $assoc['sources'] ?? '' ) ) ), (string) ( $assoc['checked'] ?? '' ) );
			if ( is_wp_error( $entry ) ) {
				WP_CLI::error( $entry->get_error_message() );
			}
			aw_place_intro_store( $key, $entry );
			WP_CLI::success( ( '' === $entry['text'] ? 'Removed ' : 'Saved ' ) . $key . '.' );
		}
	);
}
