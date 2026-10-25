<?php
/**
 * The sourcing lists (option aw_sources, seeded once from data/sources-seed.json). Kept in core: the front end reads
 * the section categories and tags from them (tags.php). The Sourcing tabs that edit them are in aw-sourcing.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The lists (seeded once from data/sources-seed.json). */
function aw_sources_config() {
	$c = get_option( 'aw_sources' );
	if ( ! is_array( $c ) ) {
		$c         = json_decode( (string) file_get_contents( AW_CORE_DIR . 'data/sources-seed.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$c['live'] = false; // the owner switches the workers over after checking
		update_option( 'aw_sources', $c, false );
	}
	$c['categories'] = (array) ( $c['categories'] ?? array() );
	$c['sections']   = (array) ( $c['sections'] ?? array() );
	return $c;
}
