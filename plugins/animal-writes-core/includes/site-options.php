<?php
/**
 * Animal Writes → Modules → Forms and sample listings: options that only WP-CLI could change before (12 Nov 2026).
 * Core Settings API on the Modules screen (social-posts.php prints it); each value keeps its option name and stored shape.
 *
 * - aw_ff_send_email: '1' sends the emails a Fluent Forms submission makes; '0' (default) drops them (forms-fluentforms.php).
 * - aw_nl_ff_form_full: the Fluent Forms form ID of the full newsletter form (newsletter-fluentforms.php, form-blocks.php).
 * - aw_sample_listing_ids: listing IDs only the owner sees (kits-config.php, kits_preview_only_ids).
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
		add_settings_section( 'aw_modules_forms', 'Forms and sample listings', 'aw_site_options_intro', 'aw-modules' );
		add_settings_field( 'aw_ff_send_email', 'Emails from forms', 'aw_site_options_send_email', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_ff_send_email' ) );
		add_settings_field( 'aw_nl_ff_form_full', 'Full newsletter form', 'aw_site_options_form_full', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_nl_ff_form_full' ) );
		add_settings_field( 'aw_sample_listing_ids', 'Sample listings', 'aw_site_options_samples', 'aw-modules', 'aw_modules_forms', array( 'label_for' => 'aw_sample_listing_ids' ) );
	}
);

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
