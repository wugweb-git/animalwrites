<?php
/**
 * Settings (Settings API): the public post types SourceWeb curates, the keyword lists, and the relevance switch.
 * Nothing is hardcoded to any particular post type — the administrator chooses.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public post types the administrator has chosen to curate. Defaults to the built-in `post`.
 *
 * @return string[]
 */
function sourceweb_post_types() {
	$chosen = get_option( 'sourceweb_post_types' );
	$chosen = is_array( $chosen ) ? array_values( array_filter( array_map( 'sanitize_key', $chosen ) ) ) : array();
	if ( ! $chosen ) {
		return array( 'post' );
	}
	// Only ever return registered, public post types (a type could have been removed since it was saved).
	$valid = array();
	foreach ( $chosen as $type ) {
		if ( post_type_exists( $type ) ) {
			$valid[] = $type;
		}
	}
	return $valid ? $valid : array( 'post' );
}

/**
 * Register the settings and their sanitisers.
 */
function sourceweb_register_settings() {
	register_setting(
		'sourceweb',
		'sourceweb_post_types',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sourceweb_sanitize_post_types',
			'default'           => array( 'post' ),
		)
	);
	register_setting(
		'sourceweb',
		'sourceweb_keywords',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sourceweb_sanitize_keywords',
			'default'           => sourceweb_keyword_defaults(),
		)
	);
	register_setting(
		'sourceweb',
		'sourceweb_feeds',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sourceweb_clean_feed_list',
			'default'           => array(),
		)
	);
	register_setting(
		'sourceweb',
		'sourceweb_summary',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sourceweb_sanitize_summary_rules',
			'default'           => array(
				'max_words' => 0,
				'strip'     => array(),
			),
		)
	);
}

/**
 * Sanitise the summary rules: a non-negative word cap and a list of strip phrases (one per line).
 *
 * @param mixed $value Submitted value.
 * @return array{max_words:int,strip:string[]}
 */
function sourceweb_sanitize_summary_rules( $value ) {
	$value = (array) $value;
	$strip = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $value['strip'] ?? '' ) ) as $line ) {
		$phrase = sanitize_text_field( trim( $line ) );
		if ( '' !== $phrase ) {
			$strip[] = $phrase;
		}
	}
	return array(
		'max_words' => isset( $value['max_words'] ) ? max( 0, (int) $value['max_words'] ) : 0,
		'strip'     => array_values( array_unique( $strip ) ),
	);
}
add_action( 'admin_init', 'sourceweb_register_settings' );

/**
 * Let holders of the plugin's capability save the settings group (options.php defaults to manage_options).
 *
 * @return string
 */
function sourceweb_option_page_capability() {
	return SOURCEWEB_CAP;
}
add_filter( 'option_page_capability_sourceweb', 'sourceweb_option_page_capability' );

/**
 * Sanitise the chosen post types: keep only registered public types.
 *
 * @param mixed $value Submitted value.
 * @return string[]
 */
function sourceweb_sanitize_post_types( $value ) {
	$out = array();
	foreach ( (array) $value as $type ) {
		$type = sanitize_key( $type );
		if ( $type && post_type_exists( $type ) ) {
			$out[] = $type;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Sanitise the keyword lists. Each list arrives as a textarea (one term per line); the enabled flag is a checkbox.
 *
 * @param mixed $value Submitted value.
 * @return array
 */
function sourceweb_sanitize_keywords( $value ) {
	$value = (array) $value;
	$lines = static function ( $raw ) {
		$terms = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$term = sanitize_text_field( trim( $line ) );
			if ( '' !== $term ) {
				$terms[] = $term;
			}
		}
		return array_values( array_unique( $terms ) );
	};
	return array(
		'enabled' => ! empty( $value['enabled'] ),
		'strong'  => $lines( $value['strong'] ?? '' ),
		'animal'  => $lines( $value['animal'] ?? '' ),
		'context' => $lines( $value['context'] ?? '' ),
		'exclude' => $lines( $value['exclude'] ?? '' ),
	);
}
