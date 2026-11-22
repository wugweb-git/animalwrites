<?php
/**
 * Gutenberg Block: directoryweb/people
 * Displays people of an organisation from FluentCRM.
 * Phase 2 block for DirectoryWeb.
 * Guarded with function_exists so it never fatals without FluentCRM.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/people block.
 */
function directoryweb_register_people_block() {
    register_block_type( 'directoryweb/people', array(
        'attributes' => array(
            'org' => array(
                'type' => 'string',
                'default' => '',
            ),
        ),
        'render_callback' => 'directoryweb_people_render',
    ) );
}
add_action( 'init', 'directoryweb_register_people_block' );

/**
 * Render the directoryweb/people block.
 */
function directoryweb_people_render( $attributes ) {
    $org = isset( $attributes['org'] ) ? sanitize_text_field( $attributes['org'] ) : '';

    if ( empty( $org ) ) {
        return '<!-- directoryweb/people: org required -->';
    }

    $output = '<div class="directoryweb-people-block">';

    // Guard: FluentCRM must be active
    if ( function_exists( 'fluent_crm' ) || class_exists( 'FluentCRM' ) ) {
        /* translators: %s: the organisation's name. */
        $output .= '<p>' . esc_html( sprintf( __( 'People for: %s', 'directoryweb' ), $org ) ) . '</p>';
        $output .= '<!-- Query FluentCRM for org people here -->';
    } else {
        $output .= '<p>' . esc_html__( 'FluentCRM not active', 'directoryweb' ) . '</p>';
    }

    $output .= '</div>';

    return $output;
}