<?php
/**
 * Query Loop Variation: directoryweb/featured-in
 * Shows listings "Featured in" (News, Library, Resources, Press mentioning an org).
 * Based on plan §1.6: "Query Loop variations (directoryweb/featured-in, /opportunities, /campaigns),
 * with the query set through query_loop_block_query_vars from aw_featured_items() and the aw_organisation / partner meta."
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/featured-in query loop variation.
 */
function directoryweb_register_featured_in_variation() {
    register_block_type( 'directoryweb/featured-in', array(
        'render_callback' => 'directoryweb_featured_in_render',
    ) );
}
add_action( 'init', 'directoryweb_register_featured_in_variation' );

/**
 * Render the directoryweb/featured-in query loop variation.
 */
function directoryweb_featured_in_render( $attributes ) {
    $query_args = get_option( 'directoryweb_featured_in_query_args', array(
        'post_type'      => 'aw_listing',
        'posts_per_page' => get_option( 'directoryweb_featured_count', 10 ),
        'meta_query' => array(
            array(
                'key'     => 'aw_rel_org',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    $loop = new WP_Query( $query_args );

    $output = '<div class="directoryweb-featured-in">';

    if ( $loop->have_posts() ) {
        $output .= '<h3>' . esc_html__( 'Featured in', 'directoryweb' ) . '</h3>';
        $output .= '<ul>';

        while ( $loop->have_posts() ) {
            $loop->the_post();

            $title = get_the_title();
            $output .= '<li>';
            $output .= '<a href="' . get_permalink() . '" rel="bookmark">' . esc_html( $title ) . '</a>';
            $output .= '</li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<p>' . esc_html__( 'No featured listings found', 'directoryweb' ) . '</p>';
    }

    $output .= '</div>';

    wp_reset_postdata();

    return $output;
}
