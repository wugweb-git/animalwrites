<?php
/**
 * Gutenberg Block: directoryweb/status
 * Displays Claimed/Verified/Blacklisted/Reported closed status after the title.
 * Replaces four title filters (prio 25, 26 in hub-blocks.php, :464, :480)
 * so the title stays plain.
 * Based on plan §1.6: "Custom block directoryweb/status, placed after the title
 * in the card and the profile. It replaces four title filters, so the title stays plain."
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/status block.
 */
function directoryweb_register_status_block() {
    register_block_type( 'directoryweb/status', array(
        'attributes' => array(),
        'render_callback' => 'directoryweb_status_block_render',
    ) );
}
add_action( 'init', 'directoryweb_register_status_block' );

/**
 * Render the directoryweb/status block.
 */
function directoryweb_status_block_render() {
    global $post;

    if ( empty( $post ) ) {
        return '';
    }

    $post_id = $post->ID;

    // Check Claimed (aw_verified)
    $claimed = get_post_meta( $post_id, 'aw_verified', true );

    // Check Verified (aw_gov_verified_on)
    $verified = get_post_meta( $post_id, 'aw_gov_verified_on', true );

    // Check Blacklisted
    $blacklisted = get_post_meta( $post_id, 'aw_blacklisted', true );

    // Check Reported closed
    $reported_closed = get_post_meta( $post_id, 'aw_reported_closed', true );

    $output = '';

    // Build status items (only show if values exist)
    $status_items = array();

    if ( $claimed ) {
        $claimed_date = get_post_meta( $post_id, 'aw_verified_on', true );
        $status_items[] = sprintf(
            '<span class="directoryweb-status-item directoryweb-status-claimed">%s %s</span>',
            esc_html__( 'Claimed', 'directoryweb' ),
            $claimed_date ? sprintf( 'on %s', $claimed_date ) : ''
        );
    }

    if ( $verified ) {
        $verified_source = get_post_meta( $post_id, 'aw_gov_verified_by', true );
        $status_items[] = sprintf(
            '<span class="directoryweb-status-item directoryweb-status-verified">%s %s</span>',
            esc_html__( 'Verified', 'directoryweb' ),
            $verified_source ? sprintf( '(%s)', $verified_source ) : ''
        );
    }

    if ( $blacklisted ) {
        $status_items[] = '<span class="directoryweb-status-item directoryweb-status-blacklisted">' . esc_html__( 'Blacklisted', 'directoryweb' ) . '</span>';
    }

    if ( $reported_closed ) {
        $status_items[] = '<span class="directoryweb-status-item directoryweb-status-closed">' . esc_html__( 'Reported closed', 'directoryweb' ) . '</span>';
    }

    if ( empty( $status_items ) ) {
        return '';
    }

    $output .= '<div class="directoryweb-status-block">' . implode( ' ', $status_items ) . '</div>';

    return $output;
}