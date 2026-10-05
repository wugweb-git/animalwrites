<?php
/**
 * Small front-end display tweaks.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Section pages read "Directory", not "Archives: Directory".
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

// Empty sections explain themselves instead of suggesting a search.
add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'kits-by-wugweb' === $domain && 'Try another search or browse the latest updates.' === $text ) {
			return __( 'New items appear here after review. Know something that belongs here? Write to contact@animalwrites.in.', 'animal-writes-core' );
		}
		return $translation;
	},
	10,
	3
);

// Content is written for India: <html lang="en-IN">.
add_filter(
	'language_attributes',
	function ( $output ) {
		return preg_replace( '/lang="en-US"/', 'lang="en-IN"', $output );
	}
);
