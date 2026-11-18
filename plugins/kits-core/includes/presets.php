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
	return kits_presets_clean( get_option( KITS_PRESETS_OPTION, array() ) );
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
	return array_intersect_key( kits_presets(), array_flip( kits_presets_enabled() ) );
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
	if ( '' !== $preset['consent_field'] && '' === (string) get_post_meta( $post_id, $preset['consent_field'], true ) ) {
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
			register_post_type( $preset['post_type'], kits_preset_type_args( $preset ) );
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

// The REST API gives a consent preset's fields only to people who may edit the item, until its consent is recorded
// (the same rule as the bindings). The item's own words in the editor are the editor's to keep consented.
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
 * A REST response without the preset's fields while consent is not recorded, for people who may not edit the item.
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
	if ( current_user_can( 'edit_post', $post->ID ) || '' !== (string) get_post_meta( $post->ID, $preset['consent_field'], true ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
		$data['meta'] = array_diff_key( $data['meta'], $preset['fields'] );
		$response->set_data( $data );
	}
	return $response;
}

/*
 * Schema contract (section 13): post type => [ 'type' => schema.org type, 'fields' => [ property => binding key ],
 * 'requires' => [ properties without which no node is built ] ]. The SEO plugin reads it and builds the node from
 * kits_post_field_value(), so schema text is the visible text. Kits Core prints no JSON-LD.
 */
add_filter(
	'kits_schema_map',
	function ( $map ) {
		$map = (array) $map;
		foreach ( kits_presets_active() as $preset ) {
			if ( ! empty( $preset['schema']['type'] ) && ! isset( $map[ $preset['post_type'] ] ) ) {
				$map[ $preset['post_type'] ] = $preset['schema'];
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
	$slug   = (string) ( kits_preset_type_args( $preset )['rewrite']['slug'] ?? $preset['post_type'] );
	printf(
		'<input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s%5$s> <p class="description">%6$s</p>',
		esc_attr( 'kits_preset_' . $id ),
		esc_attr( KITS_PRESETS_OPTION ),
		esc_attr( $id ),
		checked( $stored, true, false ),
		$stored !== $on ? ' disabled' : '',
		esc_html( $preset['description'] . ' Type ' . $preset['post_type'] . ', address /' . $slug . '/.' )
	);
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
