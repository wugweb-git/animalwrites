<?php
/**
 * Gutenberg Block: directoryweb/listing-finder
 * Displays the Organisation claim finder or listing claim stepper and form.
 * Phase 2 block for DirectoryWeb (plan §1.6).
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/listing-finder block.
 */
function directoryweb_register_listing_finder_block() {
	register_block_type(
		'directoryweb/listing-finder',
		array(
			'render_callback' => 'directoryweb_listing_finder_render',
		)
	);
}
add_action( 'init', 'directoryweb_register_listing_finder_block' );

/**
 * Render the directoryweb/listing-finder block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function directoryweb_listing_finder_render( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( function_exists( 'directoryweb_use_style' ) ) {
		directoryweb_use_style( 'dw-listing' );
		directoryweb_use_style( 'aw-directory' );
	}

	$listing = function_exists( 'directoryweb_form_page_listing' ) ? directoryweb_form_page_listing() : null;

	if ( $listing ) {
		$summary = function_exists( 'directoryweb_claim_listing_summary' ) ? directoryweb_claim_listing_summary( $listing ) : '';
		$stepper = function_exists( 'directoryweb_claim_stepper' ) ? directoryweb_claim_stepper( 1 ) : '';
		$form    = function_exists( 'aw_claim_form' ) ? aw_claim_form( $listing ) : '';

		return '<div class="aw-form-page aw-claim-page" id="claim">'
			. $summary
			. $stepper
			. $form
			. '</div>';
	}

	$finder = function_exists( 'directoryweb_claim_finder' ) ? directoryweb_claim_finder() : '';

	return '<div class="aw-form-page aw-claim-finder-page" id="claim">'
		. $finder
		. '</div>';
}
