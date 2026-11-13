<?php
/**
 * Kits → Modules → Forms and sample listings: options that only WP-CLI could change before (12 Nov 2026).
 * Core Settings API on the Modules screen (social-posts.php prints it); each value keeps its option name and stored shape.
 *
 * - aw_ff_send_email: '1' sends the emails a Fluent Forms submission makes; '0' (default) drops them (forms-fluentforms.php).
 * - aw_nl_ff_form_full: the Fluent Forms form ID of the full newsletter form (newsletter-fluentforms.php, form-blocks.php).
 * - aw_sample_listing_ids: listing IDs only the owner sees (kits-config.php, kits_preview_only_ids).
 * - aw_district_helplines: official district numbers (district-helplines.php): district code => list of
 *   {name, number, line, source, url, checked}; edited as one line per number, fields split by "|".
 * - aw_issuer_short: issuers' short names on card chips (issuers.php): slug => short; edited as "slug = short".
 * - aw_follow_reddit, aw_follow_discord: our Reddit and Discord addresses for "Follow us" on the page Share sections
 *   (campaign-tools.php, aw_follow_accounts). Empty: no icon (never a dead link). Only an https address on the
 *   network's own host is kept.
 * - aw_delete_data_on_uninstall: true lets uninstall.php delete the plugin's own options and tables (default false).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'aw_modules',
			'aw_ff_send_email',
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => fn( $v ) => $v ? '1' : '0',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'aw_modules',
			'aw_nl_ff_form_full',
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'aw_modules',
			'aw_sample_listing_ids',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'aw_site_options_ids',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'aw_modules',
			'aw_district_helplines',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'aw_site_options_helplines_clean',
				'show_in_rest'      => false,
			)
		);
		foreach ( array_keys( AW_FOLLOW_EXTRA ) as $aw_opt ) {
			register_setting(
				'aw_modules',
				$aw_opt,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => fn( $v ) => aw_follow_url_clean( $v, AW_FOLLOW_EXTRA[ $aw_opt ][2] ),
					'show_in_rest'      => false,
				)
			);
		}
		register_setting(
			'aw_modules',
			'aw_issuer_short',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'aw_site_options_short_clean',
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'aw_modules_forms', 'Forms and sample listings', 'aw_site_options_intro', 'aw-modules' );
		add_settings_field( 'aw_ff_send_email', 'Emails from forms', 'aw_site_options_send_email', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_ff_send_email' ) );
		add_settings_field( 'aw_nl_ff_form_full', 'Full newsletter form', 'aw_site_options_form_full', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_nl_ff_form_full' ) );
		add_settings_field( 'aw_sample_listing_ids', 'Sample listings', 'aw_site_options_samples', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_sample_listing_ids' ) );
		add_settings_section( 'aw_modules_lists', 'Directory and Library lists', 'aw_site_options_lists_intro', 'aw-modules' );
		add_settings_field( 'aw_district_helplines', 'District helplines', 'aw_site_options_helplines', 'aw-modules', 'aw_modules_lists', array( 'label_for' => 'aw_district_helplines' ) );
		add_settings_field( 'aw_issuer_short', 'Issuer short names', 'aw_site_options_short', 'aw-modules', 'aw_modules_lists', array( 'label_for' => 'aw_issuer_short' ) );
		add_settings_section( 'aw_modules_follow', 'Follow us accounts', 'aw_site_options_follow_intro', 'aw-modules' );
		foreach ( AW_FOLLOW_EXTRA as $aw_opt => $aw_row ) {
			add_settings_field( $aw_opt, $aw_row[0], 'aw_site_options_follow_url', 'aw-modules', 'aw_modules_follow', array( 'label_for' => $aw_opt ) );
		}
		add_settings_section( 'aw_modules_uninstall', 'Animal Writes Core data', '__return_null', 'aw-modules' );
		add_settings_field( 'aw_delete_data_on_uninstall', 'Delete this plugin\'s data when it is deleted', 'aw_site_options_uninstall', 'aw-modules', 'aw_modules_uninstall', array( 'label_for' => 'aw_delete_data_on_uninstall' ) );
	}
);

// Whether deleting the plugin also deletes its own settings and tables (uninstall.php). Off by default. On init, so
// the REST API has it too; its box is in the Kits → Modules form above.
add_action(
	'init',
	function () {
		register_setting(
			'aw_modules',
			'aw_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
	}
);

/** Delete the data on uninstall: on/off. */
function aw_site_options_uninstall(): void {
	printf(
		'<input type="checkbox" id="aw_delete_data_on_uninstall" name="aw_delete_data_on_uninstall" value="1"%1$s> <p class="description">%2$s</p>',
		checked( (bool) get_option( 'aw_delete_data_on_uninstall', false ), true, false ),
		esc_html( 'Off: everything stays in the database if the plugin is deleted.' )
	);
}

/**
 * A list of positive IDs from "12, 34" or an array.
 *
 * @param mixed $value Posted value.
 * @return int[]
 */
function aw_site_options_ids( $value ): array {
	$parts = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
	return array_values( array_unique( array_filter( array_map( 'absint', (array) $parts ) ) ) );
}

/** Section text. */
function aw_site_options_intro(): void {
	echo '<p>' . esc_html( 'Settings the site\'s forms and the Directory read. Entries are always saved, whatever is set here.' ) . '</p>';
}

/** Emails from forms: on/off. */
function aw_site_options_send_email(): void {
	printf(
		'<input type="checkbox" id="aw_ff_send_email" name="aw_ff_send_email" value="1"%1$s> <span class="description">%2$s</span>',
		checked( '1', (string) get_option( 'aw_ff_send_email', '0' ), false ),
		esc_html( 'Send the emails a Fluent Forms submission makes (alerts to contact@, the newsletter confirmation). Off: no email is sent; entries are still saved.' )
	);
}

/** Full newsletter form: a Fluent Forms form ID. */
function aw_site_options_form_full(): void {
	printf(
		'<input type="number" min="0" step="1" class="small-text" id="aw_nl_ff_form_full" name="aw_nl_ff_form_full" value="%1$d"> <span class="description">%2$s</span>',
		(int) get_option( 'aw_nl_ff_form_full', 0 ),
		esc_html( 'The Fluent Forms form ID of the full newsletter form. 0: none.' )
	);
}

/** Sample listings: IDs, comma separated. */
function aw_site_options_samples(): void {
	printf(
		'<input type="text" class="regular-text" id="aw_sample_listing_ids" name="aw_sample_listing_ids" value="%1$s"> <p class="description">%2$s</p>',
		esc_attr( implode( ', ', array_map( 'intval', (array) get_option( 'aw_sample_listing_ids', array() ) ) ) ),
		esc_html( 'Listing IDs, comma separated: only you see them (preview); everyone else gets "not found" and they appear in no list, sitemap or count. The Wugweb sample (9003) is always one.' )
	);
}

/** The six fields of one official number, in their stored order. */
const AW_HELPLINE_FIELDS = array( 'name', 'number', 'line', 'source', 'url', 'checked' );

/**
 * Official numbers in one shape: district code => list of the six fields as trimmed strings.
 *
 * @param mixed $value Stored value.
 */
function aw_site_options_helplines_norm( $value ): array {
	$out = array();
	foreach ( (array) $value as $code => $list ) {
		foreach ( (array) $list as $o ) {
			$row = array();
			foreach ( AW_HELPLINE_FIELDS as $k ) {
				$row[ $k ] = trim( (string) ( ( (array) $o )[ $k ] ?? '' ) );
			}
			$out[ (int) $code ][] = $row;
		}
	}
	return $out;
}

/**
 * The posted lines ("code | name | number | line | source | url | YYYY-MM-DD") as the option. A line that cannot be
 * read keeps the whole saved list as it was, with a message naming the line: nothing is lost by a typing slip.
 *
 * @param mixed $value Posted text (or an array, from WP-CLI).
 */
function aw_site_options_helplines_clean( $value ): array {
	$saved = get_option( 'aw_district_helplines', array() );
	if ( is_array( $value ) ) {
		return aw_site_options_helplines_norm( $value );
	}
	$out = array();
	foreach ( preg_split( '/\R/', (string) $value ) as $n => $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$code  = absint( $parts[0] );
		$row   = array_combine( AW_HELPLINE_FIELDS, array_pad( array_slice( $parts, 1, 6 ), 6, '' ) );
		$date  = '' === $row['checked'] ? null : DateTime::createFromFormat( '!Y-m-d', $row['checked'] );
		$bad   = count( $parts ) > 7 || ! $code || '' === $row['name'] || '' === $row['number']
			|| ( '' !== $row['url'] && ! wp_http_validate_url( $row['url'] ) )
			|| ( null !== $date && ( ! $date || $date->format( 'Y-m-d' ) !== $row['checked'] ) );
		if ( $bad ) {
			/* translators: %d: line number. */
			add_settings_error( 'aw_district_helplines', 'aw_district_helplines', sprintf( 'District helplines, line %d: needs a district code, a name and a number; a link must be a web address and the date YYYY-MM-DD. Nothing in the list was changed.', $n + 1 ) );
			return is_array( $saved ) ? $saved : array();
		}
		$row['name']   = sanitize_text_field( $row['name'] );
		$row['number'] = sanitize_text_field( $row['number'] );
		$row['line']   = sanitize_text_field( $row['line'] );
		$row['source'] = sanitize_text_field( $row['source'] );
		$row['url']    = esc_url_raw( $row['url'] );
		$out[ $code ][] = $row;
	}
	if ( ! $out && ! empty( $saved ) ) {
		add_settings_error( 'aw_district_helplines', 'aw_district_helplines', 'District helplines: the box was empty, so the saved numbers were kept. To take a number out, delete its line only.' );
		return $saved;
	}
	// The same numbers as saved (perhaps with missing empty fields): keep the stored value, so nothing is rewritten.
	return aw_site_options_helplines_norm( $saved ) === $out && is_array( $saved ) ? $saved : $out;
}

/**
 * "slug = short" lines as slug => short.
 *
 * @param mixed $value Posted text (or an array, from WP-CLI).
 */
function aw_site_options_short_clean( $value ): array {
	if ( is_array( $value ) ) {
		$lines = array();
		foreach ( $value as $k => $v ) {
			$lines[] = $k . ' = ' . $v;
		}
		$value = implode( "\n", $lines );
	}
	$out = array();
	foreach ( preg_split( '/\R/', (string) $value ) as $line ) {
		$pair  = array_map( 'trim', explode( '=', $line, 2 ) );
		$slug  = sanitize_key( $pair[0] );
		$short = sanitize_text_field( $pair[1] ?? '' );
		if ( '' !== $slug && '' !== $short ) {
			$out[ $slug ] = $short;
		}
	}
	return $out;
}

/** Section text. */
function aw_site_options_lists_intro(): void {
	echo '<p>' . esc_html( 'Lists the Directory district pages and the Library cards read. Before 12 Nov 2026 they could only be changed with WP-CLI.' ) . '</p>';
}

/** District helplines: one line per official number. */
function aw_site_options_helplines(): void {
	$lines = array();
	foreach ( aw_site_options_helplines_norm( get_option( 'aw_district_helplines', array() ) ) as $code => $list ) {
		foreach ( $list as $row ) {
			$lines[] = $code . ' | ' . implode( ' | ', $row );
		}
	}
	printf(
		'<textarea class="large-text code" rows="12" wrap="off" spellcheck="false" id="aw_district_helplines" name="aw_district_helplines" aria-describedby="aw_district_helplines-help">%1$s</textarea><p class="description" id="aw_district_helplines-help">%2$s</p>',
		esc_textarea( implode( "\n", $lines ) ),
		/* translators: %d: number of lines. */
		esc_html( sprintf( 'One official number per line: district code | name | number | what the line is for | source | source link | date checked (YYYY-MM-DD). Only numbers from an official page, with its link and the date. %d numbers now. They show in the helpline block on the district\'s Directory page, after 112 and 1962.', count( $lines ) ) )
	);
}

/** Issuer short names: "slug = short". */
function aw_site_options_short(): void {
	$lines = array();
	foreach ( (array) get_option( 'aw_issuer_short', array() ) as $slug => $short ) {
		$lines[] = $slug . ' = ' . $short;
	}
	$slugs = function_exists( 'aw_issuers' ) ? implode( ', ', array_keys( aw_issuers() ) ) : '';
	printf(
		'<textarea class="large-text code" rows="4" spellcheck="false" id="aw_issuer_short" name="aw_issuer_short" aria-describedby="aw_issuer_short-help">%1$s</textarea><p class="description" id="aw_issuer_short-help">%2$s</p>',
		esc_textarea( implode( "\n", $lines ) ),
		esc_html( 'One per line: issuer slug = short name for the card chip (e.g. awbi = AWBI). Empty: the built-in short names. Issuers: ' . $slugs . '.' )
	);
}

/** Follow us accounts the owner sets here (owner, 13 Nov 2026): option => [label, icon, allowed hosts]. */
const AW_FOLLOW_EXTRA = array(
	'aw_follow_reddit'  => array( 'Reddit', 'reddit', array( 'reddit.com', 'www.reddit.com' ) ),
	'aw_follow_discord' => array( 'Discord', 'discord', array( 'discord.gg', 'discord.com', 'www.discord.com' ) ),
);

/**
 * An https address on one of the allowed hosts, else ''.
 *
 * @param mixed    $value Posted value.
 * @param string[] $hosts Allowed hosts.
 */
function aw_follow_url_clean( $value, array $hosts ): string {
	$url  = esc_url_raw( trim( (string) $value ), array( 'https' ) );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return ( '' !== $url && in_array( $host, $hosts, true ) ) ? $url : '';
}

/** Section text. */
function aw_site_options_follow_intro(): void {
	echo '<p>' . esc_html( 'Our accounts shown under "Follow us" on the campaign and Home Share sections, after Instagram, Threads, X, Facebook and LinkedIn. Leave empty to show no icon.' ) . '</p>';
}

/**
 * One account address.
 *
 * @param array $args label_for: the option name.
 */
function aw_site_options_follow_url( array $args ): void {
	$opt = (string) ( $args['label_for'] ?? '' );
	if ( ! isset( AW_FOLLOW_EXTRA[ $opt ] ) ) {
		return;
	}
	printf(
		'<input type="url" class="regular-text" id="%1$s" name="%1$s" value="%2$s" placeholder="https://"> <p class="description">%3$s</p>',
		esc_attr( $opt ),
		esc_attr( (string) get_option( $opt, '' ) ),
		esc_html( 'An https address on ' . implode( ' or ', AW_FOLLOW_EXTRA[ $opt ][2] ) . '. Anything else is not saved.' )
	);
}
