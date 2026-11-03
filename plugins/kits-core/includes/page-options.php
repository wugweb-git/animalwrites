<?php
/**
 * Page Options: a page can differ from the rest of the site, and each choice falls back page → post type → site.
 *
 * - Page: post meta, set in the editor's document sidebar (assets/js/page-options.js). Empty = Inherit.
 * - Post type: the default for every item of that type, on Kits → Overview (one setting, kits_page_defaults).
 * - Site: the theme's own templates, so Inherit everywhere changes nothing.
 *
 * Options: header part, footer part, sidebar (hide), page title (hide on screen; the H1 stays for screen readers; a site's
 * own title block can ask kits_page_option( 'title' ) too) and colour set (a block style of the main
 * content, from the active theme's styles). They are applied while the page is drawn, on the front end only. The panel is
 * in the post editor (not in the Site Editor's Pages view).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The options: key => [label, kind]. */
function kits_page_option_fields(): array {
	return array(
		'header'  => array( 'Header', 'part' ),
		'footer'  => array( 'Footer', 'part' ),
		'sidebar' => array( 'Sidebar', 'hide' ),
		'title'   => array( 'Page title', 'hide' ),
		'colours' => array( 'Colour set', 'style' ),
	);
}

/** Post types that get Page Options: public, with the block editor. */
function kits_page_option_types(): array {
	$types = array_filter(
		get_post_types( array( 'public' => true ), 'objects' ),
		fn( $t ) => 'attachment' !== $t->name && ! empty( $t->show_in_rest ) && post_type_supports( $t->name, 'editor' )
	);
	return (array) apply_filters( 'kits_page_option_types', wp_list_pluck( $types, 'label', 'name' ) );
}

/**
 * The choices for one option, value => label, without Inherit ('').
 *
 * @param string $key Option key.
 */
function kits_page_option_choices( string $key ): array {
	static $cache = array();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$fields = kits_page_option_fields();
	$kind   = $fields[ $key ][1] ?? '';
	$out    = array();
	if ( 'part' === $kind ) {
		foreach ( get_block_templates( array( 'area' => $key ), 'wp_template_part' ) as $part ) {
			if ( $part->slug !== $key ) { // the theme's own part is what Inherit gives.
				$out[ $part->slug ] = $part->title ? $part->title : $part->slug;
			}
		}
	} elseif ( 'hide' === $kind ) {
		$out = array(
			'show' => 'Show',
			'hide' => 'Hide',
		);
	} elseif ( 'style' === $kind ) {
		// Colour sets are block styles of the main content (core/post-content) that the theme or a plugin provides.
		foreach ( WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/post-content' ) as $name => $style ) {
			$out[ $name ] = (string) ( $style['label'] ?? $name );
		}
	}
	$cache[ $key ] = (array) apply_filters( 'kits_page_option_choices', $out, $key );
	return $cache[ $key ];
}

/**
 * Keep a value only if it is one of the choices.
 *
 * @param string $key   Option key.
 * @param mixed  $value Raw value.
 */
function kits_page_option_clean( string $key, $value ): string {
	$value = is_scalar( $value ) ? (string) $value : '';
	return '' !== $value && isset( kits_page_option_choices( $key )[ $value ] ) ? $value : '';
}

add_action(
	'init',
	function () {
		foreach ( array_keys( kits_page_option_types() ) as $type ) {
			add_post_type_support( $type, 'custom-fields' ); // post meta in the REST API, which the editor uses.
			foreach ( array_keys( kits_page_option_fields() ) as $key ) {
				register_post_meta(
					$type,
					'_kits_' . $key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => fn( $value ) => kits_page_option_clean( $key, $value ), // Allowed values only; anything else is Inherit.
						'revisions_enabled' => true, // Preview and revisions carry the options too.
						'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					)
				);
			}
		}
		register_setting(
			'kits_page_defaults',
			'kits_page_defaults',
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'kits_page_defaults_clean',
			)
		);
	},
	20 // After the site's post types.
);

/**
 * Post-type defaults as saved: [type => [key => value]], only known types, keys and choices.
 *
 * @param mixed $input Submitted value.
 */
function kits_page_defaults_clean( $input ): array {
	$out = array();
	foreach ( array_keys( kits_page_option_types() ) as $type ) {
		foreach ( array_keys( kits_page_option_fields() ) as $key ) {
			$v = kits_page_option_clean( $key, $input[ $type ][ $key ] ?? '' );
			if ( '' !== $v ) {
				$out[ $type ][ $key ] = $v;
			}
		}
	}
	return $out;
}

/**
 * The value that applies: the page's own, else its post type's default, else '' (the theme decides).
 *
 * @param string   $key     Option key.
 * @param int|null $post_id Item; the page being viewed when left out.
 */
function kits_page_option( string $key, ?int $post_id = null ): string {
	$post_id = $post_id ? $post_id : (int) get_queried_object_id();
	if ( ! $post_id || ! isset( kits_page_option_fields()[ $key ] ) ) {
		return '';
	}
	$own = kits_page_option_clean( $key, get_post_meta( $post_id, '_kits_' . $key, true ) );
	if ( '' !== $own ) {
		return $own;
	}
	$defaults = (array) get_option( 'kits_page_defaults', array() );
	return kits_page_option_clean( $key, $defaults[ get_post_type( $post_id ) ][ $key ] ?? '' );
}

/** True while drawing a single item on the front end, where Page Options apply. */
function kits_page_options_apply(): bool {
	static $apply = null;
	if ( null === $apply && did_action( 'wp' ) ) {
		$apply = ! is_admin() && is_singular() && ! wp_is_json_request() && isset( kits_page_option_types()[ get_post_type( get_queried_object_id() ) ] );
	}
	return (bool) $apply;
}

/**
 * True when a block is about the item being viewed and not inside a Query Loop (lists keep their own titles).
 *
 * @param WP_Block|null $parent_block The block's parent, from render_block_data.
 */
function kits_page_option_is_main( $parent_block ): bool {
	$ctx = $parent_block instanceof WP_Block ? (array) $parent_block->available_context : array();
	if ( isset( $ctx['queryId'] ) ) {
		return false;
	}
	return (int) ( $ctx['postId'] ?? get_queried_object_id() ) === (int) get_queried_object_id();
}

// Applied before each block is built (priority 5, so core's block styles see the colour class). A hidden sidebar becomes
// an empty block: nothing is rendered, and no other filter or asset of it runs. A hidden title keeps its H1, off screen.
add_filter(
	'render_block_data',
	function ( $block, $source = null, $parent_block = null ) {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( ! in_array( $name, array( 'core/template-part', 'core/post-title', 'core/post-content' ), true ) || ! kits_page_options_apply() ) {
			return $block;
		}
		$empty = array(
			'blockName'    => null,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		if ( 'core/template-part' === $name ) {
			$slug = (string) ( $block['attrs']['slug'] ?? '' );
			if ( 'sidebar' === $slug && 'hide' === kits_page_option( 'sidebar' ) ) {
				return $empty;
			}
			if ( in_array( $slug, array( 'header', 'footer' ), true ) ) {
				$part = kits_page_option( $slug );
				if ( '' !== $part ) {
					$block['attrs']['slug']  = $part;
					$block['attrs']['theme'] = get_stylesheet();
					$block['attrs']['area']  = $slug;
				}
			}
		} elseif ( 'core/post-title' === $name ) {
			// Hidden on screen, kept for screen readers and search: the page keeps its H1.
			if ( 'hide' === kits_page_option( 'title' ) && kits_page_option_is_main( $parent_block ) ) {
				$block['attrs']['className'] = trim( 'screen-reader-text ' . (string) ( $block['attrs']['className'] ?? '' ) );
			}
		} elseif ( kits_page_option_is_main( $parent_block ) ) {
			$style = kits_page_option( 'colours' );
			if ( '' !== $style ) {
				$block['attrs']['className'] = trim( 'is-style-' . $style . ' ' . (string) ( $block['attrs']['className'] ?? '' ) );
			}
		}
		return $block;
	},
	5,
	3
);

// The editor's document sidebar: one panel, core components, no build step.
add_action(
	'enqueue_block_editor_assets',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$type   = $screen ? (string) $screen->post_type : '';
		if ( ! $type || ! isset( kits_page_option_types()[ $type ] ) ) {
			return;
		}
		wp_enqueue_script(
			'kits-page-options',
			plugins_url( 'assets/js/page-options.js', KITS_CORE_DIR . 'kits-core.php' ),
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ),
			(string) filemtime( KITS_CORE_DIR . 'assets/js/page-options.js' ),
			true
		);
		$defaults = (array) get_option( 'kits_page_defaults', array() );
		$fields   = array();
		foreach ( kits_page_option_fields() as $key => $f ) {
			$choices = kits_page_option_choices( $key );
			if ( ! $choices ) {
				continue; // nothing to choose (for example no colour sets yet): no field.
			}
			$fallback = kits_page_option_clean( $key, $defaults[ $type ][ $key ] ?? '' );
			$fields[] = array(
				'key'     => $key,
				'label'   => $f[0],
				'inherit' => 'Inherit (' . ( '' !== $fallback ? $choices[ $fallback ] : 'theme' ) . ')',
				'choices' => array_map(
					fn( $v, $l ) => array(
						'value' => $v,
						'label' => $l,
					),
					array_keys( $choices ),
					array_values( $choices )
				),
			);
		}
		wp_add_inline_script( 'kits-page-options', 'window.kitsPageOptions = ' . wp_json_encode( array( 'fields' => $fields ) ) . ';', 'before' );
	}
);

// Kits → Overview: the post-type defaults, as a core settings form.
add_action(
	'kits_overview_after',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$types    = kits_page_option_types();
		$defaults = (array) get_option( 'kits_page_defaults', array() );
		echo '<h2 id="kits-page-defaults">' . esc_html( 'Page Options: defaults for each post type' ) . '</h2>';
		echo '<p>' . esc_html( 'Each item can set its own in the editor (Page Options panel). Left on Inherit, an item uses the default of its post type below, and Inherit here leaves it to the theme.' ) . '</p>';
		settings_errors();
		echo '<form method="post" action="options.php">';
		settings_fields( 'kits_page_defaults' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $types as $type => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
			foreach ( kits_page_option_fields() as $key => $f ) {
				$choices = kits_page_option_choices( $key );
				if ( ! $choices ) {
					continue;
				}
				$id  = 'kits-page-defaults-' . $type . '-' . $key;
				$cur = (string) ( $defaults[ $type ][ $key ] ?? '' );
				echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label> <select id="' . esc_attr( $id ) . '" name="' . esc_attr( 'kits_page_defaults[' . $type . '][' . $key . ']' ) . '">';
				echo '<option value="">' . esc_html( 'Inherit (theme)' ) . '</option>';
				foreach ( $choices as $v => $l ) {
					echo '<option value="' . esc_attr( $v ) . '"' . selected( $cur, $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select> ';
			}
			echo '</fieldset></td></tr>';
		}
		echo '</tbody></table>';
		submit_button( 'Save defaults' );
		echo '</form>';
	}
);
