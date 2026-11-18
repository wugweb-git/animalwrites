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
 *       'consent_field' => '',                // Optional: a field that must be set before an item can be published.
 *       'schema'      => array( 'type' => 'Event', 'fields' => array( 'startDate' => 'kits_event_start' ) ),
 *       'noindex'     => false,               // Optional: its own pages say noindex and leave the sitemaps.
 *   )
 * Field formats: text, textarea, url, urls (one per line), date (stored Y-m-d), datetime (stored Y-m-d H:i, site
 * time), html, attachment (a Media Library ID). Dates are accepted as Y-m-d, Ymd (the SCF date picker), d-m-y or d-m-Y
 * (also with / . or : between), each with an optional time H:i or H:i:s (the SCF date-time picker), and stored in the
 * form above. They are shown as d-m-y H:i (filter kits_preset_date_format).
 * A field with 'private' => true is not in the REST API and has no binding key.
 *
 * Shared keys: when two switched-on presets use the same field key, a post reads it from the preset of its own post
 * type. When two switched-on presets register the same post type, the later one in kits_presets wins (fields, schema
 * and consent), as register_post_type() does.
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
		case 'datetime':
			$when = kits_preset_date_parse( $value );
			if ( ! $when ) {
				return ''; // Not a real day or time in any accepted form.
			}
			return 'datetime' === $format && '' !== $when[1] ? $when[0] . ' ' . $when[1] : $when[0];
		case 'html':
			return wp_kses_post( $value );
		default:
			return sanitize_text_field( $value );
	}
}

/**
 * A date in any accepted form, as array( 'Y-m-d', 'H:i' or '' ), or null when it is not a real day and time.
 * Accepted: Y-m-d, Ymd (SCF date picker), d-m-y or d-m-Y with - / . or : between (a two-digit year is 20yy), each
 * optionally followed by a space, T or ": " and H:i or
 * H:i:s (SCF date-time picker stores Y-m-d H:i:s); and ISO 8601 with a zone (2026-12-01T13:00:00.000Z), turned into
 * site time. Seconds are dropped.
 *
 * @param string $value Date as submitted or stored.
 * @return array{0: string, 1: string}|null
 */
function kits_preset_date_parse( string $value ): ?array {
	$value = trim( $value );
	// ISO 8601 with a zone (Z or an offset) and maybe fractions, as JavaScript and REST clients send it: site time.
	if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/i', $value, $m ) ) {
		$at = checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[4] <= 23 && (int) $m[5] <= 59 && (int) ( $m[6] ?? 0 ) <= 59 ? date_create_immutable( $value ) : false;
		if ( ! $at ) {
			return null;
		}
		$at = $at->setTimezone( wp_timezone() );
		return array( $at->format( 'Y-m-d' ), $at->format( 'H:i' ) );
	}
	$time = '(?:(?:[T ]|: ?)(\d{1,2}):(\d{2})(?::(\d{2}))?)?';
	if ( 1 === preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})' . $time . '$/', $value, $m ) || 1 === preg_match( '/^(\d{4})(\d{2})(\d{2})' . $time . '$/', $value, $m ) ) {
		list( $y, $mo, $d ) = array( (int) $m[1], (int) $m[2], (int) $m[3] );
	} elseif ( 1 === preg_match( '#^(\d{1,2})([/.:\-])(\d{1,2})\2(\d{4}|\d{2})' . $time . '$#', $value, $m ) ) {
		list( $d, $mo, $y ) = array( (int) $m[1], (int) $m[3], strlen( $m[4] ) > 2 ? (int) $m[4] : 2000 + (int) $m[4] );
		array_splice( $m, 1, 1 ); // Drop the separator group, so the time is in 4 to 6 as in the other forms.
	} else {
		return null;
	}
	if ( ! checkdate( $mo, $d, $y ) ) {
		return null;
	}
	$hm = '';
	if ( isset( $m[4] ) && '' !== $m[4] ) {
		if ( (int) $m[4] > 23 || (int) $m[5] > 59 || (int) ( $m[6] ?? 0 ) > 59 ) {
			return null;
		}
		$hm = sprintf( '%02d:%02d', (int) $m[4], (int) $m[5] );
	}
	return array( sprintf( '%04d-%02d-%02d', $y, $mo, $d ), $hm );
}

/**
 * A stored date as a time in the site's time zone, or null.
 *
 * @param string $raw Stored value.
 * @return array{0: DateTimeImmutable, 1: bool}|null The time, and whether a time of day was stored.
 */
function kits_preset_date_time( string $raw ): ?array {
	$when = '' !== $raw ? kits_preset_date_parse( $raw ) : null;
	if ( ! $when ) {
		return null;
	}
	$at = date_create_immutable( trim( $when[0] . ' ' . $when[1] ), wp_timezone() );
	return $at ? array( $at, '' !== $when[1] ) : null;
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
	$preset = kits_preset_for_post( $key, $post_id );
	if ( ! $preset || $preset['fields'][ $key ]['private'] || ! kits_preset_consented( $preset, $post_id ) ) {
		return '';
	}
	$raw    = get_post_meta( $post_id, $key, true );
	$format = $preset['fields'][ $key ]['format'];
	if ( 'attachment' === $format ) {
		return kits_preset_field_display( $preset, $key, $post_id );
	}
	if ( 'date' === $format || 'datetime' === $format ) {
		$when = kits_preset_date_time( is_scalar( $raw ) ? (string) $raw : '' );
		if ( ! $when ) {
			return '';
		}
		return $when[1] ? $when[0]->format( 'c' ) : $when[0]->format( 'Y-m-d' );
	}
	return is_scalar( $raw ) ? (string) $raw : '';
}

/**
 * The switched-on preset of a post type. When two presets register the same type, the later one wins, as with
 * register_post_type().
 *
 * @param string $type Post type.
 * @return array|null Normalised preset.
 */
function kits_preset_of_type( string $type ): ?array {
	$found = null;
	foreach ( kits_presets_active() as $preset ) {
		if ( $preset['post_type'] === $type ) {
			$found = $preset;
		}
	}
	return $found;
}

/**
 * The preset a field key is read from for a post: the preset of the post's own type, when it has this key. So a key
 * two presets share gives each post its own type's value, whatever the presets' order.
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 * @return array|null Normalised preset.
 */
function kits_preset_for_post( string $key, int $post_id ): ?array {
	$preset = kits_preset_of_type( (string) get_post_type( $post_id ) );
	return $preset && isset( $preset['fields'][ $key ] ) ? $preset : null;
}

/**
 * A binding's value: the field as its post's own preset shows it ('' for a private field or another type).
 *
 * @param string $key     Field key.
 * @param int    $post_id Post ID.
 */
function kits_preset_binding_value( string $key, int $post_id ): string {
	$preset = kits_preset_for_post( $key, $post_id );
	return $preset && ! $preset['fields'][ $key ]['private'] ? kits_preset_field_display( $preset, $key, $post_id ) : '';
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
			$when = kits_preset_date_time( is_scalar( $raw ) ? (string) $raw : '' );
			if ( ! $when ) {
				return '';
			}
			/**
			 * Filters how preset dates are shown (PHP date format). Default d-m-y (owner, 18 Nov 2026); a stored time adds
			 * " H:i".
			 *
			 * @param string $format Date format.
			 */
			$fmt = (string) apply_filters( 'kits_preset_date_format', 'd-m-y' );
			if ( 'datetime' === $preset['fields'][ $key ]['format'] && $when[1] ) {
				$fmt .= ' H:i';
			}
			return (string) wp_date( $fmt, $when[0]->getTimestamp() );
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
		$shape = array();
		foreach ( kits_presets_active() as $preset ) {
			$args = kits_preset_type_args( $preset );
			if ( ! empty( $args['has_archive'] ) && '' !== kits_preset_slug_taken( kits_preset_slug( $args ) ) ) {
				$args['has_archive'] = false; // A page already has this address: it keeps it (the settings screen says so).
			}
			register_post_type( $preset['post_type'], $args );
			$shape[ $preset['post_type'] ] = array( $args['rewrite'] ?? true, $args['has_archive'] ?? false, $args['public'] ?? false, $args['publicly_queryable'] ?? null );
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
		kits_presets_rewrite_shape( $shape );
	},
	9 // Before the site's own types and Page Options (20).
);

/**
 * The address shape of the types registered on this request (type => rewrite, archive, public): set once on init.
 *
 * @param array|null $set The shape to keep, or null to read it.
 * @return array
 */
function kits_presets_rewrite_shape( ?array $set = null ): array {
	static $shape = array();
	if ( null !== $set ) {
		$shape = $set;
	}
	return $shape;
}

/*
 * Addresses follow the registered types, whatever changed them: a switch on the settings screen, a site's code
 * (kits_presets_enabled, kits_presets), or a page taking or leaving a type's address (the clash is checked live on
 * every init, from the database, not from a saved answer). When the shape differs from the one the rewrite rules were
 * last built for, they are rebuilt once, on this request, after every type is registered. With every preset off (the
 * default) nothing is written. Deactivating Kits Core forgets the shape (kits-core.php), as the rules may be rebuilt
 * without these types while it is off.
 */
const KITS_PRESETS_REWRITE_OPTION = 'kits_presets_rewrite';

add_action( 'wp_loaded', 'kits_presets_rewrite_check' );

/** Rebuild the rewrite rules when the presets' address shape changed since they were last built. */
function kits_presets_rewrite_check(): void {
	$none = md5( (string) wp_json_encode( array() ) );
	$sig  = md5( (string) wp_json_encode( kits_presets_rewrite_shape() ) );
	if ( (string) get_option( KITS_PRESETS_REWRITE_OPTION, $none ) === $sig ) {
		return;
	}
	update_option( KITS_PRESETS_REWRITE_OPTION, $sig );
	flush_rewrite_rules( false ); // Soft: the rules option only, never .htaccess.
}

// A preset marked noindex (a type shown inside other pages, such as cards): its own pages say noindex and it is left
// out of the sitemaps, WordPress's own and Rank Math's (whose filters run only while Rank Math is active).
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( kits_presets_noindex_view() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
);
add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		if ( kits_presets_noindex_view() ) {
			$robots          = (array) $robots;
			$robots['index'] = 'noindex';
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
add_filter(
	'rank_math/sitemap/exclude_post_type',
	function ( $exclude, $type ) {
		return $exclude || in_array( (string) $type, kits_presets_noindex_types(), true );
	},
	10,
	2
);

/** Whether this request is the own page of an item of a noindex preset. */
function kits_presets_noindex_view(): bool {
	return is_singular() && in_array( get_post_type(), kits_presets_noindex_types(), true );
}

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
					'callback' => fn( int $post_id ) => kits_preset_binding_value( $key, $post_id ), // Each post reads its own type's preset.
					'type'     => kits_preset_formats()[ $field['format'] ][1],
					'label'    => $field['label'],
				);
			}
		}
		return $fields;
	}
);

/* ---------- Consent: an item of a consent preset is published only once its consent is recorded ---------- */

/*
 * The consent rule is a post status, not an output filter: an item without its consent date cannot be published or
 * scheduled. Asked to publish, it is kept as "pending" (Pending Review). So it is in no public query, REST list or
 * X-WP-Total count, sitemap (WordPress's or Rank Math's), feed or search, whatever plugin or code reads it, and its own
 * address and REST item answer as for any unpublished post. Editors see it in the admin as usual. WP-CLI, cron and the
 * admin read it as they read any pending post. "Private" stays allowed: only editors can read it.
 *
 * SCF and the Custom Fields box save the consent date after the post itself, on the same save (classic editor) or the
 * request that follows it (block editor meta boxes). So the publish that was asked for is remembered on the item
 * (KITS_PRESETS_HELD_META), and done as soon as a save records the consent, by someone who may publish this type.
 * Saving it as a draft, private or to the Trash forgets that publish.
 *
 * The rule also holds where WordPress publishes without wp_insert_post_data (a scheduled item reaching its time is
 * sent to pending before wp_publish_post; any other wp_publish_post call is undone at once), when the consent date of
 * a published item is removed (back to pending), and for items published before the rule held for them (the sweep
 * below).
 */
const KITS_PRESETS_HELD_META = '_kits_preset_held_status';

add_filter( 'wp_insert_post_data', 'kits_presets_hold_unconsented', 99, 2 );

/**
 * The post data with a publish or schedule turned into "pending" while a consent preset item has no consent.
 *
 * @param array|mixed $data    Slashed post data about to be saved.
 * @param array|mixed $postarr Data passed to wp_insert_post().
 * @return array|mixed
 */
function kits_presets_hold_unconsented( $data, $postarr ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$preset = kits_preset_of_type( (string) ( $data['post_type'] ?? '' ) );
	if ( ! $preset || '' === $preset['consent_field'] ) {
		return $data;
	}
	$postarr = is_array( $postarr ) ? $postarr : array();
	$id      = (int) ( $postarr['ID'] ?? 0 );
	$status  = (string) ( $data['post_status'] ?? '' );
	if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
		if ( $id && in_array( $status, array( 'draft', 'private', 'trash' ), true ) ) {
			delete_post_meta( $id, KITS_PRESETS_HELD_META ); // Saved as something else on purpose: forget the publish.
		}
		return $data;
	}
	$field = $preset['consent_field'];
	$given = (array) ( $postarr['meta_input'] ?? array() );
	if ( isset( $given[ $field ] ) && '' !== (string) kits_preset_field_clean( $preset['fields'][ $field ]['format'], $given[ $field ] ) ) {
		return $data; // Consent recorded on this very save (wp_insert_post meta_input).
	}
	if ( $id && kits_preset_consented( $preset, $id ) ) {
		return $data;
	}
	$data['post_status'] = 'pending';
	kits_presets_held_remember( $id, $status );
	return $data;
}

/**
 * The publishes held on this request, by post ID (0 = a post being created, which has no ID yet), until the post's
 * save_post takes them.
 *
 * @return array<int, string>
 */
function &kits_presets_held_list(): array {
	static $held = array();
	return $held;
}

/**
 * Remember a held publish for a post's save_post.
 *
 * @param int    $post_id Post ID (0 for a post being created).
 * @param string $status  'publish' or 'future'.
 */
function kits_presets_held_remember( int $post_id, string $status ): void {
	$held             = &kits_presets_held_list();
	$held[ $post_id ] = $status;
}

/**
 * Take (and forget) the held publish of a post.
 *
 * @param int $post_id Post ID (0 for a post being created).
 * @return string 'publish', 'future' or ''.
 */
function kits_presets_held_take( int $post_id ): string {
	$held = &kits_presets_held_list();
	$was  = (string) ( $held[ $post_id ] ?? '' );
	unset( $held[ $post_id ] );
	return $was;
}

// First on save_post: the item keeps the publish that was asked for. Last on save_post: once consent is recorded
// (SCF saves on save_post 10), that publish is done.
add_action( 'save_post', 'kits_presets_note_held', 1, 3 );
add_action( 'save_post', 'kits_presets_release_held', 999, 2 );

/**
 * Keep the publish held on this request on the item.
 *
 * @param int           $post_id Post ID.
 * @param WP_Post|mixed $post    Post.
 * @param bool          $update  Whether an existing post was updated (false: just created).
 */
function kits_presets_note_held( $post_id, $post, $update = true ): void {
	if ( ! ( $post instanceof WP_Post ) || ! kits_preset_consent_type( $post->post_type ) ) {
		return;
	}
	$held = kits_presets_held_take( $update ? (int) $post_id : 0 );
	if ( '' !== $held && 'pending' === $post->post_status ) {
		update_post_meta( (int) $post_id, KITS_PRESETS_HELD_META, $held );
	}
}

/**
 * Do the held publish once the item has its consent, when the person saving may publish this type.
 *
 * @param int           $post_id Post ID.
 * @param WP_Post|mixed $post    Post.
 */
function kits_presets_release_held( $post_id, $post ): void {
	if ( ! ( $post instanceof WP_Post ) || 'pending' !== $post->post_status ) {
		return;
	}
	$preset = kits_preset_consent_type( $post->post_type );
	$held   = (string) get_post_meta( (int) $post_id, KITS_PRESETS_HELD_META, true );
	if ( ! $preset || ! in_array( $held, array( 'publish', 'future' ), true ) || ! kits_preset_consented( $preset, (int) $post_id ) ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || ! current_user_can( $type->cap->publish_posts ) ) {
		return; // The item waits, pending, for someone who may publish it.
	}
	delete_post_meta( (int) $post_id, KITS_PRESETS_HELD_META );
	wp_update_post(
		array(
			'ID'          => (int) $post_id,
			'post_status' => $held, // A schedule whose time has passed is published by WordPress itself.
		)
	);
}

/**
 * The switched-on preset of a type when it has a consent field, or null.
 *
 * @param string $type Post type.
 * @return array|null
 */
function kits_preset_consent_type( string $type ): ?array {
	$preset = kits_preset_of_type( $type );
	return $preset && '' !== $preset['consent_field'] ? $preset : null;
}

/**
 * Send an item back to pending when it is published without consent.
 *
 * @param int $post_id Post ID.
 */
function kits_presets_unpublish_unconsented( int $post_id ): void {
	$post   = get_post( $post_id );
	$preset = $post instanceof WP_Post ? kits_preset_consent_type( $post->post_type ) : null;
	if ( ! $preset || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) || kits_preset_consented( $preset, $post_id ) || kits_presets_deleting( $post_id ) ) {
		return;
	}
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'pending',
		)
	);
}

// A scheduled item reaching its time without consent is sent to pending before WordPress publishes it
// (check_and_publish_future_post() runs on 10 and publishes only an item that is still scheduled).
add_action( 'publish_future_post', fn( $post_id ) => kits_presets_unpublish_unconsented( (int) $post_id ), 5 );

// Any other publish without wp_insert_post_data (wp_publish_post() called by code): back to pending at once.
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && $post instanceof WP_Post ) {
			kits_presets_unpublish_unconsented( (int) $post->ID );
		}
	},
	10,
	3
);

// The consent date of a published item removed or made empty.
add_action(
	'deleted_post_meta',
	function ( $meta_ids, $post_id, $meta_key ) {
		kits_presets_consent_meta_changed( (int) $post_id, (string) $meta_key );
	},
	10,
	3
);
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $meta_key ) {
		kits_presets_consent_meta_changed( (int) $post_id, (string) $meta_key );
	},
	10,
	3
);

/**
 * After a meta change: when it is the consent field of the item's preset, apply the rule.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 */
function kits_presets_consent_meta_changed( int $post_id, string $meta_key ): void {
	if ( ! isset( kits_presets_consent_fields()[ $meta_key ] ) ) {
		return; // Most meta writes: not a consent field of any switched-on preset.
	}
	$preset = kits_preset_consent_type( (string) get_post_type( $post_id ) );
	if ( $preset && $preset['consent_field'] === $meta_key ) {
		kits_presets_unpublish_unconsented( $post_id );
	}
}

/**
 * The consent fields of the switched-on presets, by the preset that owns each type.
 *
 * @return array<string, string> Field key => post type.
 */
function kits_presets_consent_fields(): array {
	$out = array();
	foreach ( kits_presets_active() as $preset ) {
		$owner = kits_preset_consent_type( $preset['post_type'] );
		if ( $owner ) {
			$out[ $owner['consent_field'] ] = $owner['post_type'];
		}
	}
	return $out;
}

/*
 * Items published before the rule held for them (a site preset that gains a consent field, a preset switched back on,
 * Kits Core reactivated, items written straight to the database): whenever the set of consent fields changes, every
 * published or scheduled item of those types without its consent date is sent to pending, once. With every preset
 * off (the default) nothing is written.
 */
const KITS_PRESETS_CONSENT_OPTION = 'kits_presets_consent';

add_action( 'wp_loaded', 'kits_presets_consent_sweep' );

/** Send to pending the published items of consent presets that have no consent, when the consent fields changed. */
function kits_presets_consent_sweep(): void {
	$fields = kits_presets_consent_fields();
	$sig    = md5( (string) wp_json_encode( $fields ) );
	if ( (string) get_option( KITS_PRESETS_CONSENT_OPTION, md5( (string) wp_json_encode( array() ) ) ) === $sig ) {
		return;
	}
	update_option( KITS_PRESETS_CONSENT_OPTION, $sig );
	foreach ( $fields as $field => $type ) {
		for ( $round = 0; $round < 100; $round++ ) { // 100 x 100 items at most on one request; the rest on the next change.
			$ids = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => array( 'publish', 'future' ),
					'fields'         => 'ids',
					'posts_per_page' => 100,
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once, when the consent fields change.
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'     => $field,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => $field,
							'value' => '',
						),
					),
				)
			);
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				kits_presets_unpublish_unconsented( (int) $id );
			}
		}
	}
}

// A post being deleted loses its meta first: that is not a consent withdrawn.
add_action( 'before_delete_post', fn( $post_id ) => kits_presets_deleting( (int) $post_id, true ) );

/**
 * Whether a post is being deleted on this request.
 *
 * @param int  $post_id Post ID.
 * @param bool $mark    True to mark it.
 */
function kits_presets_deleting( int $post_id, bool $mark = false ): bool {
	static $ids = array();
	if ( $mark ) {
		$ids[ $post_id ] = true;
	}
	return isset( $ids[ $post_id ] );
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
		$ours = array();
		foreach ( kits_presets_active() as $preset ) {
			// Two presets of one type: the later one wins, as its type and fields do (one without schema: no node).
			$ours[ $preset['post_type'] ] = empty( $preset['schema']['type'] ) ? null : array_merge( $preset['schema'], array( 'value_callback' => 'kits_preset_schema_value' ) );
		}
		return (array) $map + array_filter( $ours ); // A site's own map for a type wins over every preset.
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

// A switch saved here changes the addresses: kits_presets_rewrite_check() rebuilds the rules on the next request.

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
