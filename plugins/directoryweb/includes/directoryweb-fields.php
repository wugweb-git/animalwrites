<?php
/**
 * Gutenberg Block: directoryweb/fields
 * Prints the filled fields of a listing type group, skipping empty ones.
 * Usage: [directoryweb/fields group="about"] or as a block with "group" attribute.
 * Based on plan §1.6: "One custom block, directoryweb/fields, with a group attribute."
 * WordPress has no native "print the filled fields of this group, skip the empty ones" block.
 * Rows come from the listing type's fields (Settings), so a new listing type needs no new block.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/fields block.
 */
function directoryweb_register_fields_block() {
    register_block_type( 'directoryweb/fields', array(
        'attributes' => array(
            'group' => array(
                'type'      => 'string',
                'default'   => '',
                'source'    => 'attribute',
                'required'  => false,
            ),
        ),
        'editorScript' => 'directoryweb/fields',
        'render_callback' => 'directoryweb_fields_block_render',
    ) );
}
add_action( 'init', 'directoryweb_register_fields_block' );

/**
 * Render the directoryweb/fields block.
 */
function directoryweb_fields_block_render( $attributes ) {
    $group = isset( $attributes['group'] ) ? sanitize_text_field( $attributes['group'] ) : '';

    if ( empty( $group ) ) {
        return '<!-- directoryweb/fields: group attribute required -->';
    }

    // Get the field group settings
    $group_key = 'group_aw_listing_' . $group; // e.g. group_aw_listing_about
    $group_fields = get_option( $group_key, array() );

    if ( empty( $group_fields ) ) {
        /* translators: %s: the field group key (for example "about"). */
        return '<p>' . esc_html( sprintf( __( 'No fields found for group: %s', 'directoryweb' ), $group ) ) . '</p>';
    }

    $output = '<div class="directoryweb-fields-group"><h3>' . esc_html( $group ) . '</h3>';

    foreach ( $group_fields as $field_key => $field_config ) {
        // Skip empty fields
        if ( empty( $field_config['value'] ) ) {
            continue;
        }

        $value = $field_config['value'];
        $label = isset( $field_config['label'] ) ? $field_config['label'] : ucfirst( $field_key );

        // Format output based on field type
        switch ( $field_config['type'] ?? 'text' ) {
            case 'textarea':
                $output .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_textarea( $value ) . '</p>';
                break;
            case 'email':
                $output .= '<p><strong>' . esc_html( $label ) . ':</strong> <a href="mailto:' . esc_attr( $value ) . '">' . esc_attr( $value ) . '</a></p>';
                break;
            case 'url':
                $output .= '<p><strong>' . esc_html( $label ) . ':</strong> <a href="' . esc_attr( $value ) . '" target="_blank">' . esc_attr( $value ) . '</a></p>';
                break;
            case 'image':
                $output .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . wp_get_attachment_image( $value, 'thumbnail' ) . '</p>';
                break;
            default:
                $output .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
                break;
        }
    }

    $output .= '</div>';

    return $output;
}