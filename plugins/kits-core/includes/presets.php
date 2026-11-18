<?php
/**
 * Content-type presets (docs/content-blocks-feasibility.md, sections 12, 13 and 20 stage 4): a preset is a PHP config
 * array that Kits Core turns into WordPress's own calls, and nothing more. There is no type builder, no stored type
 * definition and no taxonomy of a preset's own: a preset uses only core category and post_tag.
 *
 * A switched-on preset gives, from its config:
 * - its type, with register_post_type() (labels, slug, supports, show_in_rest, the editor template);
 * - its fields, with register_post_meta() (show_in_rest, type, single, sanitize_callback, auth_callback);
 * - a binding key on kits_post_fields for each public field, so core blocks show it through kits/post-field;
 * - its schema mapping on kits_schema_map (section 13). Kits Core prints no JSON-LD; the SEO plugin reads the map.
 *
 * Every preset is off by default (owner, 18 Nov 2026: "don't want to clutter"). Each one is switched on or off in
 * Kits → Settings → Content types (core Settings API; option kits_presets_enabled, a list of preset ids). Switching a
 * preset off only stops registering its type: its items and their fields stay in the database, and come back when it
 * is switched on again.
 *
 * With code:
 * - kits_presets          the definitions (add, change or remove a preset);
 * - kits_presets_enabled  the preset ids that are on (a site that sets this in code shows it on the screen as such).
 *
 * A preset is:
 *   'kits-event' => array(
 *       'post_type'   => 'kits_event',        // At most 20 characters (register_post_type()).
 *       'title'       => 'Events',            // For the settings screen and WP-CLI.
 *       'description' => '…',
 *       'args'        => array( … ),          // register_post_type() arguments; defaults below fill the rest.
 *       'fields'      => array(
 *           'kits_event_start' => array( 'label' => 'Start', 'format' => 'datetime' ),
 *       ),
 *       'consent_field' => '',                // Optional: a field that must be set before any field is shown.
 *       'schema'      => array( 'type' => 'Event', 'fields' => array( 'startDate' => 'kits_event_start' ) ),
 *       'noindex'     => false,               // Optional: its own pages say noindex and leave the sitemap.
 *   )
 * Field formats: text, textarea, url, urls (one per line), date (Y-m-d), datetime (Y-m-d H:i, site time), html,
 * attachment (a Media Library ID). A field with 'private' => true is not in the REST API and has no binding key.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The option that holds the switched-on preset ids. */
const KITS_PRESETS_OPTION = 'kits_presets_enabled';

/**
 * The built-in preset files: preset id => file under includes/presets/.
 *
 * @return array<string, string>
 */
function kits_presets_builtin_files(): array {
	return array(
		'kits-portfolio'   => 'portfolio.php',
		'kits-card'        => 'card.php',
		'kits-infographic' => 'infographic.php',
		'kits-case-study'  => 'case-study.php',
		'kits-event'       => 'event.php',
		'kits-person'      => 'person.php',
		'kits-testimonial' => 'testimonial.php',
	);
}

/**
 * Every preset this site knows, switched on or not: id => definition. Labels are translated, so this is read on init or
 * later (never kept from before init).
 *
 * @return array<string, array>
 */
function kits_presets(): array {
	static $presets = null;
	if ( null !== $presets ) {
		return $presets;
	}
	$built = array();
	foreach ( kits_presets_builtin_files() as $id => $file ) {
		$def = include __DIR__ . '/presets/' . $file;
		if ( is_array( $def ) ) {
			$built[ $id ] = $def;
		}
	}
	/**
	 * Filters the content-type presets: add a site's own, change one, or remove one.
	 *
	 * @param array $presets Preset id => definition (see includes/presets.php).
	 */
	$built = (array) apply_filters( 'kits_presets', $built );
	$out   = array();
	foreach ( $built as $id => $def ) {
		$def = kits_preset_normalise( (string) $id, $def );
		if ( $def ) {
			$out[ (string) $id ] = $def;
		}
	}
	if ( did_action( 'init' ) ) {
		$presets = $out;
	}
	return $out;
}

/**
 * A definition with its defaults filled in, or array() when it cannot be registered (no valid post type).
 *
 * @param string $id  Preset id.
 * @param mixed  $def Definition.
 * @return array
 */
function kits_preset_normalise( string $id, $def ): array {
	if ( ! is_array( $def ) || '' === $id ) {
		return array();
	}
	$type = (string) ( $def['post_type'] ?? '' );
	if ( '' === $type || strlen( $type ) > 20 || sanitize_key( $type ) !== $type ) {
		return array();
	}
	$fields = array();
	foreach ( (array) ( $def['fields'] ?? array() ) as $key => $field ) {
		$key = (string) $key;
		if ( '' === $key || sanitize_key( $key ) !== $key || ! is_array( $field ) ) {
			continue;
		}
		$format         = (string) ( $field['format'] ?? 'text' );
		$fields[ $key ] = array(
			'label'   => (string) ( $field['label'] ?? $key ),
			'format'  => isset( kits_preset_formats()[ $format ] ) ? $format : 'text',
			'private' => ! empty( $field['private'] ),
		);
	}
	$consent = (string) ( $def['consent_field'] ?? '' );
	return array(
		'id'            => $id,
		'post_type'     => $type,
		'title'         => (string) ( $def['title'] ?? $type ),
		'description'   => (string) ( $def['description'] ?? '' ),
		'args'          => is_array( $def['args'] ?? null ) ? $def['args'] : array(),
		'fields'        => $fields,
		'consent_field' => isset( $fields[ $consent ] ) ? $consent : '',
		'schema'        => is_array( $def['schema'] ?? null ) ? $def['schema'] : array(),
		'noindex'       => ! empty( $def['noindex'] ),
	);
}

/**
 * Field formats: format => [ register_post_meta type, binding type ].
 *
 * @return array<string, array{0: string, 1: string}>
 */
function kits_preset_formats(): array {
	return array(
		'text'       => array( 'string', 'text' ),
		'textarea'   => array( 'string', 'text' ),
		'url'        => array( 'string', 'url' ),
		'urls'       => array( 'string', 'html' ),
		'date'       => array( 'string', 'text' ),
		'datetime'   => array( 'string', 'text' ),
		'html'       => array( 'string', 'html' ),
		'attachment' => array( 'integer', 'url' ),
	);
}

/**
 * The preset ids stored as switched on (what the settings screen saves), known presets only.
 *
 * @return string[]
 */
function kits_presets_stored(): array {
	$stored = get_option( KITS_PRESETS_OPTION, array() );
	return is_array( $stored ) && $stored ? kits_presets_clean( $stored ) : array(); // All off (the default): no preset file is read.
}

/**
 * The preset ids that are on: the stored list, as a site's code may change it.
 *
 * @return string[]
 */
function kits_presets_enabled(): array {
	/**
	 * Filters which presets are on (preset ids). Off by default: an empty list.
	 *
	 * @param string[] $ids The ids saved in Kits → Settings → Content types.
	 */
	$ids = (array) apply_filters( 'kits_presets_enabled', kits_presets_stored() );
	if ( ! $ids ) {
		return array(); // All off: the definitions are not built on this request.
	}
	return array_values( array_intersect( array_keys( kits_presets() ), array_map( 'strval', $ids ) ) );
}

/**
 * Whether a preset is on.
 *
 * @param string $id Preset id.
 */
function kits_preset_enabled( string $id ): bool {
	return in_array( $id, kits_presets_enabled(), true );
}

/**
 * The switched-on presets: id => definition.
 *
 * @return array<string, array>
 */
function kits_presets_active(): array {
	$on = kits_presets_enabled();
	if ( ! $on ) {
		return array();
	}
	return array_intersect_key( kits_presets(), array_flip( $on ) );
}

/**
 * The saved value: a list of known preset ids, in the presets' order, each once.
 *
 * @param mixed $input Submitted value.
 * @return string[]
 */
function kits_presets_clean( $input ): array {
	$input = is_array( $input ) ? array_map( 'strval', array_filter( $input, 'is_scalar' ) ) : array();
	return array_values( array_intersect( array_keys( kits_presets() ), $input ) );
}

/**
 * A preset's register_post_type() arguments: its own, over the defaults every preset shares. Only core category and
 * post_tag are ever attached.
 *
 * @param array $preset Normalised preset.
 * @return array
 */
function kits_preset_type_args( array $preset ): array {
	$args               = array_merge(
		array(
			'public'       => true,
			'show_in_rest' => true, // The block editor and the REST API.
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-admin-post',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
		),
		$preset['args']
	);
	$args['taxonomies'] = array_values( array_intersect( array( 'category', 'post_tag' ), (array) ( $args['taxonomies'] ?? array( 'category', 'post_tag' ) ) ) );
	if ( ! empty( $preset['fields'] ) && ! in_array( 'custom-fields', (array) $args['supports'], true ) ) {
		$args['supports'][] = 'custom-fields'; // Meta in the REST API needs it; it is also the core panel the fields are edited in without SCF.
	}
	return $args;
}

/**
 * A field's stored value, cleaned by its format.
 *
 * @param string $format Field format.
 * @param mixed  $value  Submitted value.
 * @return string|int
 */
function kits_preset_field_clean( string $format, $value ) {
	if ( 'attachment' === $format ) {
		return absint( $value );
	}
	$value = is_scalar( $value ) ? (string) $value : '';
	switch ( $format ) {
		case 'textarea':
			return sanitize_textarea_field( $value );
		case 'url':
			return esc_url_raw( trim( $value ) );
		case 'urls':
			$lines = array_filter( array_map( fn( $l ) => esc_url_raw( trim( $l ) ), preg_split( '/\R/', $value ) ) );
			return implode( "\n", $lines );
		case 'date':
			$value = trim( $value );
			return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && kits_preset_date_valid( $value ) ? $value : '';
		case 'datetime':
			$value = str_replace( 'T', ' ', trim( $value ) );
			if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})(?: (\d{2}):(\d{2}))?$/', $value, $m ) && kits_preset_date_valid( $m[1] ) ) {
				return isset( $m[2] ) && ( (int) $m[2] > 23 || (int) $m[3] > 59 ) ? '' : $value;
			}
			return '';
		case 'html':
			return wp_kses_post( $value );
		default:
			return sanitize_text_field( $value );
	}
}

/**
 * Whether a Y-m-d date is a real day.
 *
 * @param string $ymd Date.
 */
function kits_preset_date_valid( string $ymd ): bool {
	$p = array_map( 'intval', explode( '-', $ymd ) );
	return 3 === count( $p ) && checkdate( $p[1], $p[2], $p[0] );
}

/**
 * Whether an item of a preset may be shown: always, unless the preset has a consent field and it is empty.
 *
 * @param array $preset  Normalised preset.
 * @param int   $post_id Post ID.
 */
function kits_preset_consented( array $preset, int $post_id ): bool {
	return '' === $preset['consent_field'] || '' !== (string) get_post_meta( $post_id, $preset['consent_field'], true );
}

/**
 * A field's value for machines (the schema contract): dates in ISO 8601 (site time zone), a Media Library item as its
 * address, other formats as stored. '' when there is nothing, on another type, or without consent.
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 */
function kits_preset_schema_value( string $key, int $post_id ): string {
	foreach ( kits_presets_active() as $preset ) {
		if ( ! isset( $preset['fields'][ $key ] ) ) {
			continue;
		}
		if ( $preset['fields'][ $key ]['private'] || get_post_type( $post_id ) !== $preset['post_type'] || ! kits_preset_consented( $preset, $post_id ) ) {
			return '';
		}
		$raw    = get_post_meta( $post_id, $key, true );
		$format = $preset['fields'][ $key ]['format'];
		if ( 'attachment' === $format || 'urls' === $format ) {
			return 'attachment' === $format ? kits_preset_field_display( $preset, $key, $post_id ) : (string) $raw;
		}
		if ( ( 'date' === $format || 'datetime' === $format ) && '' !== (string) $raw ) {
			$when = date_create_immutable( (string) $raw, wp_timezone() );
			if ( ! $when ) {
				return '';
			}
			return strlen( (string) $raw ) > 10 ? $when->format( 'c' ) : $when->format( 'Y-m-d' );
		}
		return is_scalar( $raw ) ? (string) $raw : '';
	}
	return '';
}

/**
 * A field's value as a binding shows it ('' = nothing to show).
 *
 * @param array  $preset  Normalised preset.
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 */
function kits_preset_field_display( array $preset, string $key, int $post_id ): string {
	if ( ! isset( $preset['fields'][ $key ] ) || get_post_type( $post_id ) !== $preset['post_type'] ) {
		return '';
	}
	if ( ! kits_preset_consented( $preset, $post_id ) ) {
		return ''; // Nothing of this item is shown without the recorded consent.
	}
	$raw = get_post_meta( $post_id, $key, true );
	switch ( $preset['fields'][ $key ]['format'] ) {
		case 'attachment':
			$url = (int) $raw ? wp_get_attachment_url( (int) $raw ) : '';
			return is_string( $url ) ? $url : '';
		case 'date':
		case 'datetime':
			$raw = (string) $raw;
			if ( '' === $raw ) {
				return '';
			}
			$when = date_create_immutable( $raw, wp_timezone() );
			if ( ! $when ) {
				return '';
			}
			$fmt = (string) get_option( 'date_format' );
			if ( 'datetime' === $preset['fields'][ $key ]['format'] && strlen( $raw ) > 10 ) {
				$fmt .= ' ' . (string) get_option( 'time_format' );
			}
			return (string) wp_date( $fmt, $when->getTimestamp() );
		case 'urls':
			$links = array();
			foreach ( preg_split( '/\R/', (string) $raw ) as $url ) {
				$url = trim( $url );
				if ( '' === $url ) {
					continue;
				}
				$host    = (string) wp_parse_url( $url, PHP_URL_HOST );
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( '' !== $host ? preg_replace( '/^www\./', '', $host ) : $url ) . '</a>';
			}
			return implode( ' · ', $links );
		default:
			return is_scalar( $raw ) ? (string) $raw : '';
	}
}

// Types and fields of the switched-on presets. Switched-off presets register nothing, so the admin menu has none of them.
add_action(
	'init',
	function () {
		foreach ( kits_presets_active() as $preset ) {
			$args = kits_preset_type_args( $preset );
			if ( ! empty( $args['has_archive'] ) && '' !== kits_preset_slug_taken( kits_preset_slug( $args ) ) ) {
				$args['has_archive'] = false; // A page already has this address: it keeps it (the settings screen says so).
			}
			register_post_type( $preset['post_type'], $args );
			foreach ( $preset['fields'] as $key => $field ) {
				$format = $field['format'];
				register_post_meta(
					$preset['post_type'],
					$key,
					array(
						'type'              => kits_preset_formats()[ $format ][0],
						'description'       => $field['label'],
						'single'            => true,
						'default'           => 'attachment' === $format ? 0 : '',
						'show_in_rest'      => ! $field['private'],
						'sanitize_callback' => fn( $value ) => kits_preset_field_clean( $format, $value ),
						'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
					)
				);
			}
		}
	},
	9 // Before the site's own types and Page Options (20).
);

// A preset marked noindex (a type shown inside other pages, such as cards): its own pages say noindex and it is left
// out of WordPress's sitemap.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_singular() && in_array( get_post_type(), kits_presets_noindex_types(), true ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
);
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		return array_diff_key( (array) $types, array_flip( kits_presets_noindex_types() ) );
	}
);

/**
 * The post types of switched-on presets marked noindex.
 *
 * @return string[]
 */
function kits_presets_noindex_types(): array {
	return array_values( array_map( fn( $p ) => $p['post_type'], array_filter( kits_presets_active(), fn( $p ) => $p['noindex'] ) ) );
}

// Binding keys: every public field of a switched-on preset, for core blocks through kits/post-field (bindings.php).
add_filter(
	'kits_post_fields',
	function ( $fields ) {
		$fields = (array) $fields;
		foreach ( kits_presets_active() as $preset ) {
			foreach ( $preset['fields'] as $key => $field ) {
				if ( $field['private'] || isset( $fields[ $key ] ) ) {
					continue; // A site's own key of the same name wins.
				}
				$fields[ $key ] = array(
					'callback' => fn( int $post_id ) => kits_preset_field_display( $preset, $key, $post_id ),
					'type'     => kits_preset_formats()[ $field['format'] ][1],
					'label'    => $field['label'],
				);
			}
		}
		return $fields;
	}
);

// An item of a consent preset is not served to anyone who may not edit it until its consent is recorded: it leaves every
// front-end and REST query (lists, its own page, which then is a 404, feeds and search), whatever field or block holds
// its words. Editors still see it everywhere, so they can finish it.
add_filter(
	'the_posts',
	function ( $posts ) {
		return is_array( $posts ) ? kits_presets_hide_unconsented( $posts ) : $posts;
	},
	10,
	1
);

/**
 * The posts without the items of a consent preset that have no consent yet, for people who may not edit them. The
 * admin screens are left as they are.
 *
 * @param array $posts Posts (WP_Post objects or IDs).
 * @return array
 */
function kits_presets_hide_unconsented( array $posts ): array {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $posts;
	}
	$guarded = array();
	foreach ( kits_presets_active() as $preset ) {
		if ( '' !== $preset['consent_field'] ) {
			$guarded[ $preset['post_type'] ] = $preset;
		}
	}
	if ( ! $guarded ) {
		return $posts;
	}
	$out = array();
	foreach ( $posts as $post ) {
		$id   = is_object( $post ) ? (int) $post->ID : (int) $post;
		$type = is_object( $post ) ? (string) $post->post_type : (string) get_post_type( $id );
		if ( isset( $guarded[ $type ] ) && ! kits_preset_consented( $guarded[ $type ], $id ) && ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		$out[] = $post;
	}
	return count( $out ) === count( $posts ) ? $posts : $out;
}

// A single item in the REST API is read without a query: its words and fields are blanked there by the same rule.
add_action(
	'init',
	function () {
		foreach ( kits_presets_active() as $preset ) {
			if ( '' === $preset['consent_field'] ) {
				continue;
			}
			add_filter(
				'rest_prepare_' . $preset['post_type'],
				function ( $response, $post ) use ( $preset ) {
					return kits_preset_rest_consent( $response, $post, $preset );
				},
				10,
				2
			);
		}
	},
	11
);

/**
 * A REST response with no title, content, excerpt or fields while consent is not recorded, for people who may not edit
 * the item.
 *
 * @param WP_REST_Response|mixed $response Response.
 * @param WP_Post|mixed          $post     Post.
 * @param array                  $preset   Normalised preset.
 * @return WP_REST_Response|mixed
 */
function kits_preset_rest_consent( $response, $post, array $preset ) {
	if ( ! ( $response instanceof WP_REST_Response ) || ! ( $post instanceof WP_Post ) ) {
		return $response;
	}
	if ( kits_preset_consented( $preset, (int) $post->ID ) || current_user_can( 'edit_post', $post->ID ) ) {
		return $response;
	}
	$data = $response->get_data();
	foreach ( array( 'title', 'content', 'excerpt' ) as $part ) {
		if ( isset( $data[ $part ] ) && is_array( $data[ $part ] ) ) {
			$data[ $part ] = array_merge( $data[ $part ], array( 'rendered' => '' ), isset( $data[ $part ]['raw'] ) ? array( 'raw' => '' ) : array() );
		}
	}
	if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
		$data['meta'] = array_diff_key( $data['meta'], $preset['fields'] );
	}
	$response->set_data( $data );
	return $response;
}

/*
 * Schema contract (section 13): post type => [ 'type' => schema.org type, 'fields' => [ property => binding key ],
 * 'requires' => [ properties without which no node is built ], 'value_callback' => callable( $key, $post_id ) ]. The
 * SEO plugin reads each value through value_callback when it is set, else kits_post_field_value(). Presets set it to
 * kits_preset_schema_value(): the visible value, except dates in ISO 8601 and pictures as their address, which
 * schema.org needs. Kits Core prints no JSON-LD.
 */
add_filter(
	'kits_schema_map',
	function ( $map ) {
		$map = (array) $map;
		foreach ( kits_presets_active() as $preset ) {
			if ( ! empty( $preset['schema']['type'] ) && ! isset( $map[ $preset['post_type'] ] ) ) {
				$map[ $preset['post_type'] ] = array_merge( $preset['schema'], array( 'value_callback' => 'kits_preset_schema_value' ) );
			}
		}
		return $map;
	}
);

/* ---------- Kits → Settings → Content types ---------- */

add_action(
	'init',
	function () {
		register_setting(
			'kits_presets',
			KITS_PRESETS_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => 'kits_presets_clean',
				'show_in_rest'      => false,
			)
		);
	}
);

// A type switched on or off changes the addresses: WordPress rebuilds its rewrite rules on the next request, when the
// new list of types is registered.
add_action( 'add_option_' . KITS_PRESETS_OPTION, 'kits_presets_rewrite_later' );
add_action( 'update_option_' . KITS_PRESETS_OPTION, 'kits_presets_rewrite_later' );

/** Drop the stored rewrite rules, so the next request builds them with the new types. */
function kits_presets_rewrite_later(): void {
	delete_option( 'rewrite_rules' );
}

add_action(
	'admin_init',
	function () {
		add_settings_section( 'kits_presets', '', '__return_null', 'kits-presets' );
		foreach ( kits_presets() as $id => $preset ) {
			add_settings_field(
				'kits_preset_' . $id,
				esc_html( $preset['title'] ),
				'kits_presets_field',
				'kits-presets',
				'kits_presets',
				array(
					'label_for' => 'kits_preset_' . $id,
					'preset'    => $id,
				)
			);
		}
	}
);

add_filter(
	'kits_settings_sections',
	function ( $sections ) {
		$sections                  = (array) $sections;
		$sections['content-types'] = array(
			'title'  => 'Content types',
			'render' => 'kits_presets_form',
			'cap'    => 'manage_options',
			'order'  => 25,
		);
		return $sections;
	}
);

/**
 * A type's public address base, or '' when it has no pages of its own.
 *
 * @param array $args register_post_type() arguments.
 */
function kits_preset_slug( array $args ): string {
	$public = (bool) ( $args['publicly_queryable'] ?? $args['public'] ?? false );
	if ( ! $public || false === ( $args['rewrite'] ?? true ) ) {
		return '';
	}
	return (string) ( is_array( $args['rewrite'] ?? null ) && ! empty( $args['rewrite']['slug'] ) ? $args['rewrite']['slug'] : '' );
}

/**
 * The title of a published page that already has this address, or ''.
 *
 * @param string $slug Address base.
 */
function kits_preset_slug_taken( string $slug ): string {
	if ( '' === $slug ) {
		return '';
	}
	$page = get_page_by_path( $slug );
	return $page instanceof WP_Post && 'publish' === $page->post_status ? (string) $page->post_title : '';
}

/**
 * One preset's switch.
 *
 * @param array $args Field arguments (preset id in 'preset').
 */
function kits_presets_field( array $args ): void {
	$id     = (string) ( $args['preset'] ?? '' );
	$preset = kits_presets()[ $id ] ?? null;
	if ( ! $preset ) {
		return;
	}
	$stored = in_array( $id, kits_presets_stored(), true );
	$on     = kits_preset_enabled( $id );
	$slug   = kits_preset_slug( kits_preset_type_args( $preset ) );
	$taken  = kits_preset_slug_taken( $slug );
	printf(
		'<input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s%5$s> <p class="description">%6$s</p>',
		esc_attr( 'kits_preset_' . $id ),
		esc_attr( KITS_PRESETS_OPTION ),
		esc_attr( $id ),
		checked( $stored, true, false ),
		$stored !== $on ? ' disabled' : '',
		esc_html( $preset['description'] ) . ' ' . esc_html(
			'' !== $slug
				/* translators: 1: post type slug, 2: address such as /events/ */
				? sprintf( __( 'Type %1$s, address %2$s.', 'kits-core' ), $preset['post_type'], '/' . $slug . '/' )
				/* translators: %s: post type slug */
				: sprintf( __( 'Type %s, with no pages of its own: shown inside other pages.', 'kits-core' ), $preset['post_type'] )
		)
	);
	if ( '' !== $taken ) {
		/* translators: 1: address such as /events/, 2: page title */
		echo '<p class="description">' . esc_html( sprintf( __( 'The page "%2$s" already has %1$s: it keeps that address, and this type gets no list page there.', 'kits-core' ), '/' . $slug . '/', $taken ) ) . '</p>';
	}
	if ( $stored !== $on ) {
		echo '<p class="description">' . esc_html( $on ? 'On: switched on in this site\'s code.' : 'Off: switched off in this site\'s code.' ) . '</p>';
		if ( $stored ) {
			// A disabled box is not sent: keep the stored value as it is.
			printf( '<input type="hidden" name="%1$s[]" value="%2$s">', esc_attr( KITS_PRESETS_OPTION ), esc_attr( $id ) );
		}
	}
}

/** Kits → Settings → Content types: its own form, coming back to its section after the save. */
function kits_presets_form(): void {
	echo '<p>' . esc_html( 'Switch on only the content types this site uses; each one that is on gets its own item in the admin menu. Switching one off hides it: its items and their fields stay, and come back when it is switched on again.' ) . '</p>';
	echo '<form method="post" action="options.php">';
	echo '<input type="hidden" name="option_page" value="kits_presets"><input type="hidden" name="action" value="update">';
	wp_nonce_field( 'kits_presets-options', '_wpnonce', false );
	echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( wp_make_link_relative( admin_url( 'admin.php?page=kits-settings&kits_section=content-types' ) ) ) . '">';
	do_settings_sections( 'kits-presets' );
	submit_button( 'Save content types' );
	echo '</form>';
}

/* ---------- WP-CLI: wp kits preset list (read only) ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'kits preset list',
		function ( $args, $assoc_args ) {
			$stored = kits_presets_stored();
			$rows   = array();
			foreach ( kits_presets() as $id => $preset ) {
				$on     = kits_preset_enabled( $id );
				$rows[] = array(
					'preset'    => $id,
					'post_type' => $preset['post_type'],
					'on'        => $on ? 'yes' : 'no',
					'set_by'    => in_array( $id, $stored, true ) === $on ? 'settings' : 'code',
					'fields'    => implode( ',', array_keys( $preset['fields'] ) ),
					'schema'    => (string) ( $preset['schema']['type'] ?? '' ),
				);
			}
			WP_CLI\Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $rows, array( 'preset', 'post_type', 'on', 'set_by', 'fields', 'schema' ) );
		},
		array(
			'shortdesc' => 'List the content-type presets, whether each is on, and its fields. Switch them in Kits → Settings → Content types.',
			'synopsis'  => array(
				array(
					'type'        => 'assoc',
					'name'        => 'format',
					'optional'    => true,
					'default'     => 'table',
					'options'     => array( 'table', 'csv', 'json', 'yaml' ),
					'description' => 'Output format.',
				),
			),
		)
	);
}
