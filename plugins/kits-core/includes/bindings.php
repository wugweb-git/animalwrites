<?php
/**
 * Block Bindings source "kits/post-field": core blocks (Paragraph, Heading, Button, Image) show a post's field, so
 * templates and patterns need no custom block for a value. Kits Core knows no field: a site registers its keys on the
 * kits_post_fields filter. With no keys registered, every bound block is simply left out.
 *
 * Usage in a template or pattern:
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"kits/post-field","args":{"key":"source_meta"}}}}} -->
 *
 * A key is registered as:
 *   'source_meta' => array(
 *       'callback' => function ( int $post_id, string $key ): string { ... }, // the raw value; '' = nothing to show
 *       'type'     => 'text',                                               // text (default), url or html
 *       'label'    => 'Source and date',                                    // optional, for screens and docs
 *   )
 * The value is escaped by its type: text with esc_html(), url with esc_url(), html with wp_kses_post().
 *
 * A block bound to an empty value is removed from the page. A site can show something else instead (a greyed button)
 * with the kits_post_field_empty filter. Other sources that read the same keys (a site's older source name) join the
 * empty rule through kits_post_field_sources.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registered keys.
 *
 * @return array<string, array{callback: callable, type?: string, label?: string}>
 */
function kits_post_fields() {
	return (array) apply_filters( 'kits_post_fields', array() );
}

/**
 * A key's raw value for a post ('' for an unknown key).
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 * @return string
 */
function kits_post_field_value( $key, $post_id ) {
	$fields = kits_post_fields();
	if ( ! isset( $fields[ $key ]['callback'] ) || ! is_callable( $fields[ $key ]['callback'] ) ) {
		return '';
	}
	return (string) call_user_func( $fields[ $key ]['callback'], (int) $post_id, (string) $key );
}

/**
 * A key's value for a post, escaped for output by the key's type.
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 * @return string
 */
function kits_post_field_output( $key, $post_id ) {
	$value = kits_post_field_value( $key, $post_id );
	$type  = (string) ( kits_post_fields()[ $key ]['type'] ?? 'text' );
	if ( 'url' === $type ) {
		return esc_url( $value );
	}
	return 'html' === $type ? wp_kses_post( $value ) : esc_html( $value );
}

/**
 * The binding sources whose keys are kits_post_fields keys (they share the empty-block rule).
 *
 * @return string[]
 */
function kits_post_field_sources() {
	return array_values( array_unique( (array) apply_filters( 'kits_post_field_sources', array( 'kits/post-field' ) ) ) );
}

/**
 * The post a bound block is about: the block context (inside a Query Loop) or the current post.
 *
 * @param WP_Block|null $block Block instance.
 * @return int
 */
function kits_post_field_post_id( $block ) {
	return ( $block instanceof WP_Block && isset( $block->context['postId'] ) ) ? (int) $block->context['postId'] : (int) get_the_ID();
}

add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'kits/post-field',
			array(
				'label'              => __( 'Kits post field', 'kits-core' ),
				'uses_context'       => array( 'postId' ),
				'get_value_callback' => function ( $args, $block ) {
					return kits_post_field_output( (string) ( $args['key'] ?? '' ), kits_post_field_post_id( $block ) );
				},
			)
		);
	}
);

/**
 * Leave out a block whose kits_post_fields binding is empty, so a card or a template never shows an empty line or a
 * button that goes nowhere. kits_post_field_empty can return other markup instead (default '').
 *
 * @param string        $content  Rendered block.
 * @param array         $block    Parsed block.
 * @param WP_Block|null $instance Block instance.
 * @return string
 */
function kits_post_field_drop_empty( $content, $block, $instance = null ) {
	$bindings = $block['attrs']['metadata']['bindings'] ?? array();
	if ( ! $bindings || ! is_array( $bindings ) ) {
		return $content;
	}
	$sources = kits_post_field_sources();
	$post_id = kits_post_field_post_id( $instance );
	foreach ( $bindings as $binding ) {
		if ( ! in_array( $binding['source'] ?? '', $sources, true ) ) {
			continue;
		}
		$key = (string) ( $binding['args']['key'] ?? '' );
		if ( '' === kits_post_field_value( $key, $post_id ) ) {
			/**
			 * What a block bound to an empty field shows instead ('' = nothing).
			 *
			 * @param string $out     Replacement markup.
			 * @param string $content The block as rendered.
			 * @param array  $block   Parsed block.
			 * @param string $key     The empty key.
			 * @param int    $post_id Post ID.
			 */
			return (string) apply_filters( 'kits_post_field_empty', '', $content, $block, $key, $post_id );
		}
	}
	return $content;
}
add_filter( 'render_block', 'kits_post_field_drop_empty', 10, 3 );
