<?php
/**
 * Settings transfer: a site's design settings go out and come back as one readable JSON file.
 *
 * The file holds three parts, each in the form WordPress itself keeps it, so two exports can be compared line by line:
 *
 *     { "kits": 1, "exported": "2026-11-03T10:00:00Z", "site": "https://example.com", "theme": "kits-by-wugweb",
 *       "global_styles": { "styles": {…}, "settings": {…} },
 *       "options": { "kits_page_defaults": {…} },
 *       "patterns": [ { "slug": "…", "title": "…", "content": "…", "sync": "synced", "categories": [ "…" ] } ] }
 *
 * - global_styles: the user's Global Styles record of the active theme (Site Editor → Styles).
 * - options: only the options named by the kits_settings_transfer_options filter (default: kits_page_defaults).
 * - patterns: every published pattern (wp_block), matched by slug on import. Patterns that are not in the file are
 *   left alone; nothing is ever deleted.
 *
 * Ways in and out, all through core APIs and no screen of their own:
 * - REST: GET /wp-json/kits/v1/settings (add ?download=1 for a file), POST the same JSON back to import.
 * - WP-CLI: wp kits export [--file=<path>] and wp kits import <file> [--parts=…] [--dry-run].
 * - kits_settings_transfer_url(): a download link for an admin screen.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The parts a file can hold, in order. */
function kits_settings_transfer_parts(): array {
	return array( 'global_styles', 'options', 'patterns' );
}

/** The options that travel with the file. Anything else in a file's "options" is ignored. */
function kits_settings_transfer_options(): array {
	$names = (array) apply_filters( 'kits_settings_transfer_options', array( 'kits_page_defaults' ) );
	return array_values( array_unique( array_filter( array_map( 'strval', $names ) ) ) );
}

/**
 * The stored Global Styles record of the active theme, decoded, or an empty array when there is none yet.
 *
 * @param bool $create Create the record (the way core does) when it is missing.
 * @return array { id: int, config: array }
 */
function kits_settings_transfer_styles_record( bool $create = false ): array {
	$post = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), $create );
	if ( empty( $post['ID'] ) ) {
		return array(
			'id'     => 0,
			'config' => array(),
		);
	}
	$config = json_decode( (string) ( $post['post_content'] ?? '' ), true );
	// Core trusts the record only when this flag is set: without it the content was never escaped.
	if ( ! is_array( $config ) || empty( $config['isGlobalStylesUserThemeJSON'] ) ) {
		$config = array();
	}
	return array(
		'id'     => (int) $post['ID'],
		'config' => $config,
	);
}

/**
 * Styles and settings in the shape core stores, passed through WP_Theme_JSON so unknown keys are dropped.
 *
 * @param array $config A theme.json-like array with styles and/or settings.
 * @return array { styles: array, settings: array }
 */
function kits_settings_transfer_clean_styles( array $config ): array {
	$config['version'] = WP_Theme_JSON::LATEST_SCHEMA;
	unset( $config['isGlobalStylesUserThemeJSON'] );
	$raw = ( new WP_Theme_JSON( $config, 'custom' ) )->get_raw_data();
	return array(
		'styles'   => isset( $raw['styles'] ) && is_array( $raw['styles'] ) ? $raw['styles'] : array(),
		'settings' => isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array(),
	);
}

/**
 * An empty list becomes {} in the file, so an empty part reads as an object, not [].
 *
 * @param mixed $value Any value.
 * @return mixed
 */
function kits_settings_transfer_object( $value ) {
	return is_array( $value ) && ! $value ? new stdClass() : $value;
}

/**
 * One published pattern as it goes into the file.
 *
 * @param WP_Post $post A wp_block post.
 */
function kits_settings_transfer_pattern( WP_Post $post ): array {
	$terms = wp_get_object_terms( $post->ID, 'wp_pattern_category', array( 'fields' => 'slugs' ) );
	$terms = is_wp_error( $terms ) ? array() : array_values( array_map( 'strval', $terms ) );
	sort( $terms );
	return array(
		'slug'       => $post->post_name,
		'title'      => $post->post_title,
		'content'    => $post->post_content,
		'sync'       => 'unsynced' === get_post_meta( $post->ID, 'wp_pattern_sync_status', true ) ? 'unsynced' : 'synced',
		'categories' => $terms,
	);
}

/** The whole file as an array, ready for wp_json_encode(). Reads only; nothing is created. */
function kits_settings_export(): array {
	$record = kits_settings_transfer_styles_record();
	$config = $record['config'];

	$options = array();
	foreach ( kits_settings_transfer_options() as $name ) {
		$options[ $name ] = kits_settings_transfer_object( get_option( $name ) );
	}

	$patterns = array();
	$posts    = get_posts(
		array(
			'post_type'      => 'wp_block',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'name',
			'order'          => 'ASC',
		)
	);
	foreach ( $posts as $post ) {
		$patterns[] = kits_settings_transfer_pattern( $post );
	}

	return array(
		'kits'          => 1,
		'exported'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
		'site'          => home_url(),
		'theme'         => get_stylesheet(),
		'global_styles' => array(
			'styles'   => kits_settings_transfer_object( isset( $config['styles'] ) && is_array( $config['styles'] ) ? $config['styles'] : array() ),
			'settings' => kits_settings_transfer_object( isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array() ),
		),
		'options'       => kits_settings_transfer_object( $options ),
		'patterns'      => $patterns,
	);
}

/**
 * Import a file made by kits_settings_export().
 *
 * @param array $data    The decoded file.
 * @param array $parts   Which parts to import: global_styles, options, patterns.
 * @param bool  $dry_run Only report what would change.
 * @return array|WP_Error What changed (or would change), per part.
 */
function kits_settings_import( array $data, array $parts = array( 'global_styles', 'options', 'patterns' ), bool $dry_run = false ) {
	// Objects and arrays alike become plain arrays, whichever way the data arrived.
	$data = json_decode( (string) wp_json_encode( $data ), true );
	if ( ! is_array( $data ) || ! isset( $data['kits'] ) || 1 !== $data['kits'] ) {
		return new WP_Error( 'kits_settings_invalid', 'This is not a Kits settings file (it needs "kits": 1).', array( 'status' => 400 ) );
	}
	$parts = array_values( array_intersect( kits_settings_transfer_parts(), array_map( 'strval', $parts ) ) );

	$summary = array(
		'dry_run' => $dry_run,
		'theme'   => get_stylesheet(),
	);
	if ( isset( $data['theme'] ) && is_string( $data['theme'] ) && get_stylesheet() !== $data['theme'] ) {
		$summary['note'] = sprintf( 'The file was exported from the theme "%s"; its styles go to the active theme.', $data['theme'] );
	}

	if ( in_array( 'global_styles', $parts, true ) ) {
		$result = kits_settings_import_styles( $data['global_styles'] ?? null, $dry_run );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$summary['global_styles'] = $result;
	}
	if ( in_array( 'options', $parts, true ) ) {
		$summary['options'] = kits_settings_import_options( $data['options'] ?? null, $dry_run );
	}
	if ( in_array( 'patterns', $parts, true ) ) {
		$summary['patterns'] = kits_settings_import_patterns( $data['patterns'] ?? null, $dry_run );
	}
	return $summary;
}

/**
 * Write styles and settings into the active theme's Global Styles record, as the Site Editor saves them.
 *
 * A key that is in the file replaces what is stored; a key that is missing keeps what is stored.
 *
 * @param mixed $input   The file's global_styles part.
 * @param bool  $dry_run Only report.
 * @return array|WP_Error { status: skipped|unchanged|updated }
 */
function kits_settings_import_styles( $input, bool $dry_run ) {
	if ( ! is_array( $input ) || ( ! isset( $input['styles'] ) && ! isset( $input['settings'] ) ) ) {
		return array( 'status' => 'skipped' );
	}
	$css = isset( $input['styles'] ) && is_array( $input['styles'] ) ? ( $input['styles']['css'] ?? '' ) : '';
	if ( ! is_string( $css ) || false !== stripos( $css, '</style' ) ) {
		return new WP_Error( 'kits_settings_css', 'The custom CSS in the file is not valid.', array( 'status' => 400 ) );
	}

	$record = kits_settings_transfer_styles_record( ! $dry_run );
	$stored = $record['config'];
	$config = array();
	foreach ( array( 'styles', 'settings' ) as $key ) {
		if ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
			$config[ $key ] = $input[ $key ];
		} elseif ( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ) {
			$config[ $key ] = $stored[ $key ];
		}
	}

	// Style variations the theme defines in its /styles folder must be known, or their styles are dropped as unknown.
	if ( function_exists( 'wp_register_block_style_variations_from_theme_json_partials' ) ) {
		wp_register_block_style_variations_from_theme_json_partials( WP_Theme_JSON_Resolver::get_style_variations( 'block' ) );
	}
	$clean = kits_settings_transfer_clean_styles( $config );
	if ( $stored && kits_settings_transfer_clean_styles( $stored ) === $clean ) {
		return array( 'status' => 'unchanged' );
	}
	if ( $dry_run ) {
		return array( 'status' => 'updated' );
	}
	if ( ! $record['id'] ) {
		return new WP_Error( 'kits_settings_styles', 'The Global Styles record could not be created.', array( 'status' => 500 ) );
	}

	$content = array(
		'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
		'isGlobalStylesUserThemeJSON' => true,
	);
	foreach ( $clean as $key => $value ) {
		if ( $value ) {
			$content[ $key ] = $value;
		}
	}
	// As core: escape <>& so HTML filters cannot mangle the JSON. For users without unfiltered_html, core's own
	// content filter (wp_filter_global_styles_post) also removes unsafe values while the post is saved.
	$saved = wp_update_post(
		wp_slash(
			array(
				'ID'           => $record['id'],
				'post_content' => wp_json_encode( $content, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ),
			)
		),
		true
	);
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	wp_clean_theme_json_cache();
	return array( 'status' => 'updated' );
}

/**
 * Save the allowed options. update_option() runs each option's registered sanitize callback.
 *
 * @param mixed $input   The file's options part.
 * @param bool  $dry_run Only report.
 * @return array { updated: string[], unchanged: string[], ignored: string[] }
 */
function kits_settings_import_options( $input, bool $dry_run ): array {
	$out = array(
		'updated'   => array(),
		'unchanged' => array(),
		'ignored'   => array(),
	);
	if ( ! is_array( $input ) ) {
		return $out;
	}
	$allowed = kits_settings_transfer_options();
	foreach ( $input as $name => $value ) {
		$name = (string) $name;
		if ( ! in_array( $name, $allowed, true ) ) {
			$out['ignored'][] = $name;
			continue;
		}
		// The value as it would be saved, to tell a real change from a no-op.
		if ( sanitize_option( $name, $value ) === get_option( $name ) ) {
			$out['unchanged'][] = $name;
			continue;
		}
		if ( $dry_run || update_option( $name, $value ) ) {
			$out['updated'][] = $name;
		} else {
			$out['unchanged'][] = $name;
		}
	}
	return $out;
}

/**
 * Create or update patterns by slug. Patterns that are not in the file stay as they are.
 *
 * @param mixed $input   The file's patterns part.
 * @param bool  $dry_run Only report.
 * @return array { created: string[], updated: string[], unchanged: string[], failed: string[] }
 */
function kits_settings_import_patterns( $input, bool $dry_run ): array {
	$out = array(
		'created'   => array(),
		'updated'   => array(),
		'unchanged' => array(),
		'failed'    => array(),
	);
	if ( ! is_array( $input ) ) {
		return $out;
	}
	$raw_html = current_user_can( 'unfiltered_html' );
	foreach ( $input as $item ) {
		if ( ! is_array( $item ) || empty( $item['slug'] ) || ! is_string( $item['slug'] ) ) {
			continue;
		}
		$slug    = sanitize_title( $item['slug'] );
		$content = is_string( $item['content'] ?? null ) ? $item['content'] : '';
		$content = $raw_html ? $content : wp_kses_post( $content );
		$wanted  = array(
			'slug'       => $slug,
			'title'      => sanitize_text_field( is_string( $item['title'] ?? null ) ? $item['title'] : $slug ),
			'content'    => $content,
			'sync'       => 'unsynced' === ( $item['sync'] ?? '' ) ? 'unsynced' : 'synced',
			'categories' => array_values( array_unique( array_filter( array_map( 'sanitize_title', array_filter( (array) ( $item['categories'] ?? array() ), 'is_string' ) ) ) ) ),
		);
		sort( $wanted['categories'] );

		$found = get_posts(
			array(
				'post_type'      => 'wp_block',
				'name'           => $slug,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
			)
		);
		$post  = $found ? $found[0] : null;
		if ( $post && 'publish' === $post->post_status && kits_settings_transfer_pattern( $post ) === $wanted ) {
			$out['unchanged'][] = $slug;
			continue;
		}
		$bucket = $post ? 'updated' : 'created';
		if ( $dry_run ) {
			$out[ $bucket ][] = $slug;
			continue;
		}

		$postarr = array(
			'post_type'    => 'wp_block',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $wanted['title'],
			'post_content' => $wanted['content'],
		);
		if ( $post ) {
			$postarr['ID'] = $post->ID;
		}
		$id = $post ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) || ! $id ) {
			$out['failed'][] = $slug;
			continue;
		}

		// Synced is core's default and is stored as no value at all.
		if ( 'unsynced' === $wanted['sync'] ) {
			update_post_meta( $id, 'wp_pattern_sync_status', 'unsynced' );
		} else {
			delete_post_meta( $id, 'wp_pattern_sync_status' );
		}

		$term_ids = array();
		foreach ( $wanted['categories'] as $cat ) {
			$term = term_exists( $cat, 'wp_pattern_category' );
			if ( ! $term ) {
				$term = wp_insert_term( ucwords( str_replace( '-', ' ', $cat ) ), 'wp_pattern_category', array( 'slug' => $cat ) );
			}
			if ( is_array( $term ) && ! empty( $term['term_id'] ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}
		wp_set_object_terms( $id, $term_ids, 'wp_pattern_category' );

		$out[ $bucket ][] = $slug;
	}
	return $out;
}

/** A download link for the export, for a signed-in user who can edit theme options. */
function kits_settings_transfer_url(): string {
	return add_query_arg(
		array(
			'download' => 1,
			'_pretty'  => 1,
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
		),
		rest_url( 'kits/v1/settings' )
	);
}

add_action(
	'rest_api_init',
	function () {
		$can = fn() => current_user_can( 'edit_theme_options' );
		register_rest_route(
			'kits/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $can,
					'args'                => array(
						'download' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'callback'            => function ( WP_REST_Request $request ) {
						$response = new WP_REST_Response( kits_settings_export() );
						$response->header( 'Cache-Control', 'no-store' );
						if ( $request['download'] ) {
							$host = sanitize_file_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
							$name = sprintf( 'kits-settings-%s-%s.json', $host ? $host : 'site', gmdate( 'Y-m-d' ) );
							$response->header( 'Content-Disposition', 'attachment; filename="' . $name . '"' );
						}
						return $response;
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can,
					'args'                => array(
						'parts'   => array(
							'type'    => 'array',
							'items'   => array(
								'type' => 'string',
								'enum' => kits_settings_transfer_parts(),
							),
							'default' => kits_settings_transfer_parts(),
						),
						'dry_run' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
					'callback'            => function ( WP_REST_Request $request ) {
						$data = $request->get_json_params();
						if ( ! is_array( $data ) ) {
							return new WP_Error( 'kits_settings_invalid', 'Send the settings file as a JSON body.', array( 'status' => 400 ) );
						}
						return kits_settings_import( $data, (array) $request['parts'], (bool) $request['dry_run'] );
					},
				),
			)
		);
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp kits export [--file=<path>]: the file as pretty JSON, to the file or to standard output.
	$kits_export = function ( $args, $assoc_args ) {
		$json = wp_json_encode( kits_settings_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			WP_CLI::error( 'The settings could not be encoded as JSON.' );
		}
		$file = (string) ( $assoc_args['file'] ?? '' );
		if ( '' === $file ) {
			WP_CLI::line( $json );
			return;
		}
		// A command-line write to a path the operator chose; the WP_Filesystem layer is for web requests.
		if ( false === file_put_contents( $file, $json . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::error( "Could not write {$file}." );
		}
		WP_CLI::success( "Settings exported to {$file}." );
	};

	// wp kits import <file> [--parts=<parts>] [--dry-run]: run with --user=<admin> so pattern HTML is kept as is.
	$kits_import = function ( $args, $assoc_args ) {
		$file = (string) ( $args[0] ?? '' );
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read {$file}." );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file named on the command line.
		if ( ! is_array( $data ) ) {
			WP_CLI::error( "{$file} is not valid JSON." );
		}
		$parts   = isset( $assoc_args['parts'] ) ? array_map( 'trim', explode( ',', (string) $assoc_args['parts'] ) ) : kits_settings_transfer_parts();
		$unknown = array_diff( $parts, kits_settings_transfer_parts() );
		if ( $unknown ) {
			WP_CLI::error( 'Unknown part: ' . implode( ', ', $unknown ) . '. Use global_styles, options, patterns.' );
		}
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$summary = kits_settings_import( $data, $parts, $dry_run );
		if ( is_wp_error( $summary ) ) {
			WP_CLI::error( $summary->get_error_message() );
		}
		WP_CLI::line( (string) wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		WP_CLI::success( $dry_run ? 'Dry run: nothing was changed.' : 'Settings imported.' );
	};

	WP_CLI::add_command(
		'kits export',
		$kits_export,
		array(
			'shortdesc' => 'Export the site\'s Global Styles, Kits options and patterns as one JSON file.',
			'synopsis'  => array(
				array(
					'type'        => 'assoc',
					'name'        => 'file',
					'optional'    => true,
					'description' => 'Write to this file. Without it the JSON goes to standard output.',
				),
			),
		)
	);
	WP_CLI::add_command(
		'kits import',
		$kits_import,
		array(
			'shortdesc' => 'Import a settings file made by wp kits export. Patterns not in the file are kept.',
			'synopsis'  => array(
				array(
					'type'        => 'positional',
					'name'        => 'file',
					'description' => 'The JSON file.',
				),
				array(
					'type'        => 'assoc',
					'name'        => 'parts',
					'optional'    => true,
					'description' => 'Comma-separated: global_styles, options, patterns. Default: all three.',
				),
				array(
					'type'        => 'flag',
					'name'        => 'dry-run',
					'optional'    => true,
					'description' => 'Show what would change and change nothing.',
				),
			),
		)
	);
}
