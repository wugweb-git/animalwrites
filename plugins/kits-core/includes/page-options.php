<?php
/**
 * Page Options: a page can differ from the rest of the site, and each choice falls back page → post type → site.
 *
 * - Page: post meta, set in the editor's document sidebar (assets/js/page-options.js). Empty = Inherit.
 * - Kind of page: the default for every item of a post type, and for each list page (archive_<type>), Search, Authors
 *   and 404, on Kits → Theme Options (one setting, kits_page_defaults, keyed by context; see kits_page_contexts()).
 * - Site-wide: the _site context, on Theme Options → General.
 * - Theme: its own templates, so Inherit everywhere changes nothing.
 *
 * Options: header part, footer part, sidebar (hide), page title (hide on screen; the H1 stays for screen readers; a site's
 * own title block can ask kits_page_option( 'title' ) too), breadcrumbs (hide; the site's trail reads it) and colour set
 * (a block style of the main content, from the active theme's styles). They are applied while the page is drawn, on the front end only. The panel is
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
		'crumbs'  => array( 'Breadcrumbs', 'hide' ),
		'colours' => array( 'Colour set', 'style' ),
	);
}

/**
 * Notes for the Theme Options rows.
 *
 * @param string $key Option key.
 */
function kits_page_option_note( string $key ): string {
	$notes = array(
		'header'  => 'Which header part. Parts are made in Appearance → Editor → Patterns → Header.',
		'footer'  => 'Which footer part. Parts are made in Appearance → Editor → Patterns → Footer.',
		'sidebar' => 'Pages whose template has a sidebar.',
		'title'   => 'Hidden on screen only: the page keeps its H1 for screen readers and search.',
		'crumbs'  => 'The trail above the title. Search engines still get it as data.',
		'colours' => 'A colour set from Styles → Blocks → Group for the page\'s content.',
	);
	return $notes[ $key ] ?? '';
}

/** Which group an option shows under in the editor panel. */
function kits_page_option_group( string $key ): string {
	return array(
		'header' => 'Header',
		'footer' => 'Footer',
	)[ $key ] ?? 'Content';
}

/** Post types whose list has a page of its own: type => label. A site adds its own list pages with kits_archive_types. */
function kits_archive_types(): array {
	$out = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $t ) {
		if ( 'post' === $t->name || ! empty( $t->has_archive ) ) {
			$out[ $t->name ] = $t->labels->name;
		}
	}
	return (array) apply_filters( 'kits_archive_types', $out );
}

/**
 * Every context that can hold defaults: context => the option keys it takes.
 * A post type (each item), archive_<type> (its list, category and tag pages), _search, _author, _404 and _site.
 */
function kits_page_contexts(): array {
	$all  = array_keys( kits_page_option_fields() );
	$list = array( 'header', 'footer', 'sidebar', 'title', 'crumbs' );
	$out  = array( '_site' => $all );
	foreach ( array_keys( kits_page_option_types() ) as $type ) {
		$out[ $type ] = $all;
	}
	foreach ( array_keys( kits_archive_types() ) as $type ) {
		$out[ 'archive_' . $type ] = $list;
	}
	$out['_search'] = $list;
	$out['_author'] = $list;
	$out['_404']    = array( 'header', 'footer', 'sidebar', 'crumbs' );
	return $out;
}

/**
 * The option keys one context takes.
 *
 * @param string $ctx Context.
 */
function kits_page_context_keys( string $ctx ): array {
	return kits_page_contexts()[ $ctx ] ?? array();
}

/** The context of the page being drawn ('' when none applies), after the kits_page_options_context filter. */
function kits_page_context(): string {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$ctx = '';
	if ( is_singular() ) {
		$type = (string) get_post_type( get_queried_object_id() );
		$ctx  = isset( kits_page_option_types()[ $type ] ) ? $type : '';
	} elseif ( is_404() ) {
		$ctx = '_404';
	} elseif ( is_search() ) {
		$ctx = '_search';
	} elseif ( is_author() ) {
		$ctx = '_author';
	} elseif ( is_home() || is_category() || is_tag() ) {
		$ctx = 'archive_post';
	} elseif ( is_post_type_archive() ) {
		$type = get_query_var( 'post_type' );
		$ctx  = 'archive_' . (string) ( is_array( $type ) ? reset( $type ) : $type );
	} elseif ( is_tax() ) {
		$tax  = get_taxonomy( (string) get_query_var( 'taxonomy' ) );
		$ctx  = $tax && $tax->object_type ? 'archive_' . (string) reset( $tax->object_type ) : '';
	}
	// The second argument is the post being edited in the block editor; null on the front end.
	$ctx = (string) apply_filters( 'kits_page_options_context', $ctx, null );
	$ctx = isset( kits_page_contexts()[ $ctx ] ) ? $ctx : '';
	if ( did_action( 'wp' ) ) {
		$cached = $ctx; // One answer per request once the query has run.
	}
	return $ctx;
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
						'revisions_enabled' => post_type_supports( $type, 'revisions' ), // Preview and revisions carry the options too; a type without revisions would make core drop the key.
						'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					)
				);
			}
		}
		register_setting(
			'kits_options', // Kits → Theme Options and Core Settings save both options through one group.
			'kits_page_defaults',
			array(
				'type'              => 'object', // Matches the REST schema below; 'array' would need list items.
				'default'           => array(),
				'sanitize_callback' => 'kits_page_defaults_clean',
				'show_in_rest'      => array( // Kits → Theme Options (new screen) saves through /wp/v2/settings.
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
				),
			)
		);
	},
	20 // After the site's post types.
);

/**
 * Defaults as saved: [context => [key => value]], only known contexts, keys and choices. A context that is in the
 * input replaces what is stored for it; contexts that are not in the input stay (a Theme Options section saves only
 * its own, and an imported file never deletes).
 *
 * @param mixed $input Submitted value.
 */
function kits_page_defaults_clean( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = (array) get_option( 'kits_page_defaults', array() );
	foreach ( kits_page_contexts() as $ctx => $keys ) {
		if ( ! isset( $input[ $ctx ] ) || ! is_array( $input[ $ctx ] ) ) {
			continue;
		}
		$row = is_array( $out[ $ctx ] ?? null ) ? $out[ $ctx ] : array(); // Keys not on this form stay.
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $input[ $ctx ] ) ) {
				continue;
			}
			$v = kits_page_option_clean( $key, $input[ $ctx ][ $key ] );
			if ( '' !== $v ) {
				$row[ $key ] = $v;
			} else {
				unset( $row[ $key ] );
			}
		}
		if ( $row ) {
			$out[ $ctx ] = $row;
		} else {
			unset( $out[ $ctx ] );
		}
	}
	return $out;
}

/**
 * The value that applies: the item's own, else its kind of page (post type, list page, Search, Authors, 404), else the
 * site-wide default, else '' (the theme decides).
 *
 * @param string   $key     Option key.
 * @param int|null $post_id Item; the page being viewed when left out.
 */
function kits_page_option( string $key, ?int $post_id = null ): string {
	if ( ! isset( kits_page_option_fields()[ $key ] ) ) {
		return '';
	}
	$queried = did_action( 'wp' ) && is_singular() ? (int) get_queried_object_id() : 0;
	$post_id = $post_id ? $post_id : $queried;
	$ctx     = '';
	if ( $post_id ) {
		$own = kits_page_option_clean( $key, get_post_meta( $post_id, '_kits_' . $key, true ) );
		if ( '' !== $own ) {
			return $own;
		}
		$ctx = $post_id === $queried ? kits_page_context() : (string) get_post_type( $post_id );
	} elseif ( did_action( 'wp' ) ) {
		$ctx = kits_page_context();
	}
	$defaults = (array) get_option( 'kits_page_defaults', array() );
	if ( '' !== $ctx && in_array( $key, kits_page_context_keys( $ctx ), true ) ) {
		$v = kits_page_option_clean( $key, $defaults[ $ctx ][ $key ] ?? '' );
		if ( '' !== $v ) {
			return $v;
		}
	}
	return kits_page_option_clean( $key, $defaults['_site'][ $key ] ?? '' );
}

/** True while drawing a page on the front end that Page Options apply to (an item, a list page, Search, Authors, 404). */
function kits_page_options_apply(): bool {
	static $apply = null;
	if ( null === $apply && did_action( 'wp' ) ) {
		$apply = ! is_admin() && ! wp_is_json_request() && ( '' !== kits_page_context() || ( is_singular() && isset( kits_page_option_types()[ get_post_type( get_queried_object_id() ) ] ) ) );
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
		if ( ! in_array( $name, array( 'core/template-part', 'core/post-title', 'core/query-title', 'core/post-content' ), true ) || ! kits_page_options_apply() ) {
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
			if ( is_singular() && 'hide' === kits_page_option( 'title' ) && kits_page_option_is_main( $parent_block ) ) {
				$block['attrs']['className'] = trim( 'screen-reader-text ' . (string) ( $block['attrs']['className'] ?? '' ) );
			}
		} elseif ( 'core/query-title' === $name ) {
			// A list page's title, the same way.
			if ( ! is_singular() && 'hide' === kits_page_option( 'title' ) ) {
				$block['attrs']['className'] = trim( 'screen-reader-text ' . (string) ( $block['attrs']['className'] ?? '' ) );
			}
		} elseif ( is_singular() && kits_page_option_is_main( $parent_block ) ) {
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
		// Inherit names what the front end falls back to, so it uses the same context as the page drawn.
		$post = get_post();
		$ctx  = $post ? (string) apply_filters( 'kits_page_options_context', $type, $post ) : $type;
		$ctx  = isset( kits_page_contexts()[ $ctx ] ) ? $ctx : $type;

		$defaults = (array) get_option( 'kits_page_defaults', array() );
		$fields   = array();
		foreach ( kits_page_option_fields() as $key => $f ) {
			$choices = kits_page_option_choices( $key );
			if ( ! $choices ) {
				continue; // nothing to choose (for example no colour sets yet): no field.
			}
			$fallback = in_array( $key, kits_page_context_keys( $ctx ), true ) ? kits_page_option_clean( $key, $defaults[ $ctx ][ $key ] ?? '' ) : '';
			$fallback = '' !== $fallback ? $fallback : kits_page_option_clean( $key, $defaults['_site'][ $key ] ?? '' );
			$fields[] = array(
				'key'     => $key,
				'group'   => kits_page_option_group( $key ),
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
