<?php
/**
 * The store (plan section 2): a non-public post type, planweb_entry, with three private taxonomies (Type, Channel,
 * Owner) and registered meta (date, time, status, link, repeat, flag). All in the core REST API
 * (/wp/v2/planweb-entries) with the same checks as the screens: links only to this site or social sites, no email
 * address or phone number, approved and sent only for administrators.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLANWEB_TYPE = 'planweb_entry';

/** Taxonomy => fixed values (slug => label). */
const PLANWEB_TAXONOMIES = array(
	'planweb_type'    => PLANWEB_TYPES,
	'planweb_channel' => PLANWEB_CHANNELS,
	'planweb_owner'   => PLANWEB_OWNERS,
);

add_action(
	'init',
	function () {
		if ( ! planweb_on() ) {
			return;
		}
		register_post_type(
			PLANWEB_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Calendar entries', 'planweb' ),
					'singular_name' => __( 'Calendar entry', 'planweb' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false, // PlanWeb's own screens (admin.php).
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'rest_base'           => 'planweb-entries',
				'rewrite'             => false,
				'query_var'           => false,
				'has_archive'         => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'capability_type'     => array( 'planweb_entry', 'planweb_entries' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			)
		);
		foreach ( array_keys( PLANWEB_TAXONOMIES ) as $tax ) {
			register_taxonomy(
				$tax,
				PLANWEB_TYPE,
				array(
					'public'             => false,
					'publicly_queryable' => false,
					'show_ui'            => false,
					'show_in_rest'       => true,
					'hierarchical'       => false,
					'rewrite'            => false,
					'query_var'          => false,
					'capabilities'       => array(
						'manage_terms' => 'manage_options',
						'edit_terms'   => 'manage_options',
						'delete_terms' => 'manage_options',
						'assign_terms' => 'edit_planweb_entries',
					),
				)
			);
		}
		$meta = array(
			'planweb_date'         => array(
				'description' => 'Date (Y-m-d).',
				'pattern'     => '^\d{4}-\d{2}-\d{2}$',
			),
			'planweb_time'         => array(
				'description' => 'Time (H:i, IST); empty for all day.',
				'pattern'     => '^(([01]\d|2[0-3]):[0-5]\d)?$',
			),
			'planweb_status'       => array(
				'description' => 'Status.',
				'enum'        => array_keys( PLANWEB_STATUSES ),
				'default'     => 'idea',
			),
			'planweb_link'         => array(
				'description' => 'Link: this site or a social URL (checked on save).',
			),
			'planweb_repeat'       => array(
				'description' => 'Repeat (used by the scheduler).',
				'enum'        => array( 'none', 'weekly', 'monthly', 'yearly' ),
				'default'     => 'none',
			),
			'planweb_repeat_until' => array(
				'description' => 'Repeat until (Y-m-d), optional.',
				'pattern'     => '^(\d{4}-\d{2}-\d{2})?$',
			),
		);
		foreach ( $meta as $key => $schema ) {
			// A default only where one is declared: core refuses a key whose default fails its own pattern (an empty
			// planweb_date). A single string key with no default still reads as ''.
			$args = array(
				'type'              => 'string',
				'single'            => true,
				'revisions_enabled' => true,
				'sanitize_callback' => 'planweb_link' === $key ? 'esc_url_raw' : 'sanitize_text_field',
				'auth_callback'     => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				'show_in_rest'      => array( 'schema' => array( 'type' => 'string' ) + $schema ),
			);
			if ( array_key_exists( 'default', $schema ) ) {
				$args['default'] = $schema['default'];
			}
			register_post_meta( PLANWEB_TYPE, $key, $args );
		}
		// Set by the scheduler (phase 3) and the import only: readable over REST, never writable there.
		foreach ( array( 'planweb_flag', 'planweb_source', 'planweb_source_key' ) as $key ) {
			register_post_meta(
				PLANWEB_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'default'       => '',
					'auth_callback' => '__return_false',
					'show_in_rest'  => 'planweb_flag' === $key,
				)
			);
		}
	}
);

/**
 * Why a set of fields is refused, or null. Used by the REST API, the screen's form and the import (which skips the
 * personal-data check for old entries: it lists hits instead).
 *
 * @param array $f       Fields: title, note, date, time, status, link, type (slug). Missing ones are not checked.
 * @param int   $post_id Entry being changed (0 for new).
 * @param bool  $people  Run the personal-data check.
 * @return WP_Error|null
 */
function planweb_check_fields( array $f, int $post_id = 0, bool $people = true ) {
	if ( array_key_exists( 'title', $f ) && '' === trim( (string) $f['title'] ) ) {
		return new WP_Error( 'planweb_title', __( 'Give the entry a title.', 'planweb' ), array( 'status' => 400 ) );
	}
	if ( array_key_exists( 'date', $f ) && ! planweb_is_date( $f['date'] ) ) {
		return new WP_Error( 'planweb_date', __( 'Pick a date.', 'planweb' ), array( 'status' => 400 ) );
	}
	if ( array_key_exists( 'time', $f ) && ! planweb_is_time( (string) $f['time'] ) ) {
		return new WP_Error( 'planweb_time', __( 'The time must be like 09:30, or empty.', 'planweb' ), array( 'status' => 400 ) );
	}
	if ( array_key_exists( 'type', $f ) && ! isset( PLANWEB_TYPES[ (string) $f['type'] ] ) ) {
		return new WP_Error( 'planweb_type', __( 'Pick a type.', 'planweb' ), array( 'status' => 400 ) );
	}
	if ( array_key_exists( 'status', $f ) ) {
		$status = (string) $f['status'];
		if ( ! isset( PLANWEB_STATUSES[ $status ] ) ) {
			return new WP_Error( 'planweb_status', __( 'Pick a status.', 'planweb' ), array( 'status' => 400 ) );
		}
		$was = $post_id ? (string) get_post_meta( $post_id, 'planweb_status', true ) : '';
		if ( $status !== $was && in_array( $status, PLANWEB_APPROVE_STATUSES, true ) && ! current_user_can( 'planweb_approve' ) ) {
			return new WP_Error( 'planweb_approve', __( 'Only an administrator can set Approved or Sent.', 'planweb' ), array( 'status' => 403 ) );
		}
		if ( 'due' === $status && 'due' !== $was ) {
			return new WP_Error( 'planweb_due', __( 'Due is set by the scheduler on the day; pick Approved.', 'planweb' ), array( 'status' => 400 ) );
		}
	}
	if ( array_key_exists( 'link', $f ) ) {
		$why = planweb_link_problem( (string) $f['link'], (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' !== $why ) {
			return new WP_Error( 'planweb_link', $why, array( 'status' => 400 ) );
		}
	}
	if ( $people ) {
		foreach ( array( 'title', 'note' ) as $k ) {
			$why = array_key_exists( $k, $f ) ? planweb_personal_data_problem( (string) $f[ $k ] ) : '';
			if ( '' !== $why ) {
				return new WP_Error( 'planweb_personal', $why, array( 'status' => 400 ) );
			}
		}
	}
	return null;
}

// The calendar is the team's own: core REST lets anyone read published items of any type that is in the REST API,
// so the entry routes and the three list routes answer only people who work with entries.
add_filter(
	'rest_request_before_callbacks',
	function ( $response, $handler, $request ) {
		$route = (string) $request->get_route();
		if ( preg_match( '#^/wp/v2/(planweb-entries|planweb_type|planweb_channel|planweb_owner)(/|$)#', $route ) && ! current_user_can( 'edit_planweb_entries' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'planweb' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $response;
	},
	10,
	3
);

// The REST API checks the same fields before an entry is saved.
add_filter(
	'rest_pre_insert_' . PLANWEB_TYPE,
	function ( $prepared, $request ) {
		$f    = array();
		$meta = (array) ( $request['meta'] ?? array() );
		if ( isset( $request['title'] ) ) {
			$f['title'] = is_array( $request['title'] ) ? (string) ( $request['title']['raw'] ?? '' ) : (string) $request['title'];
		} elseif ( ! $request['id'] ) {
			$f['title'] = '';
		}
		if ( isset( $request['content'] ) ) {
			$f['note'] = is_array( $request['content'] ) ? (string) ( $request['content']['raw'] ?? '' ) : (string) $request['content'];
		}
		$map = array(
			'planweb_date'   => 'date',
			'planweb_time'   => 'time',
			'planweb_status' => 'status',
			'planweb_link'   => 'link',
		);
		foreach ( $map as $key => $name ) {
			if ( array_key_exists( $key, $meta ) ) {
				$f[ $name ] = (string) $meta[ $key ];
			}
		}
		if ( ! $request['id'] && ! isset( $f['date'] ) ) {
			$f['date'] = '';
		}
		// Type: one term of the fixed list, required on create (planweb_type holds term IDs in REST).
		if ( isset( $request['planweb_type'] ) || ! $request['id'] ) {
			$ids       = array_filter( array_map( 'absint', (array) ( $request['planweb_type'] ?? array() ) ) );
			$term      = 1 === count( $ids ) ? get_term( reset( $ids ), 'planweb_type' ) : null;
			$f['type'] = $term && ! is_wp_error( $term ) ? (string) $term->slug : '';
		}
		$err = planweb_check_fields( $f, (int) $request['id'] );
		if ( $err ) {
			return $err;
		}
		// Entries are always published (planweb_entries() reads only publish; the Trash is DELETE, not a status).
		$prepared->post_status = 'publish';
		return $prepared;
	},
	10,
	2
);

// Entries are always "publish" (the type is private); the REST API may filter by date range and status.
add_filter(
	'rest_' . PLANWEB_TYPE . '_collection_params',
	function ( $params ) {
		$params['planweb_from']   = array(
			'description' => 'Entries on or after this date (Y-m-d).',
			'type'        => 'string',
			'pattern'     => '^\d{4}-\d{2}-\d{2}$',
		);
		$params['planweb_to']     = array(
			'description' => 'Entries on or before this date (Y-m-d).',
			'type'        => 'string',
			'pattern'     => '^\d{4}-\d{2}-\d{2}$',
		);
		$params['planweb_status'] = array(
			'description' => 'Entries with this status.',
			'type'        => 'string',
			'enum'        => array_keys( PLANWEB_STATUSES ),
		);
		return $params;
	}
);

add_filter(
	'rest_' . PLANWEB_TYPE . '_query',
	function ( $args, $request ) {
		$mq = array();
		if ( ! empty( $request['planweb_from'] ) ) {
			$mq[] = array(
				'key'     => 'planweb_date',
				'value'   => (string) $request['planweb_from'],
				'compare' => '>=',
				'type'    => 'DATE',
			);
		}
		if ( ! empty( $request['planweb_to'] ) ) {
			$mq[] = array(
				'key'     => 'planweb_date',
				'value'   => (string) $request['planweb_to'],
				'compare' => '<=',
				'type'    => 'DATE',
			);
		}
		if ( ! empty( $request['planweb_status'] ) ) {
			$mq[] = array(
				'key'   => 'planweb_status',
				'value' => (string) $request['planweb_status'],
			);
		}
		if ( $mq ) {
			$args['meta_query'] = $mq; // phpcs:ignore WordPress.DB.SlowDBQuery -- a few hundred entries at most.
		}
		return $args;
	},
	10,
	2
);

/**
 * One entry as an array.
 *
 * @param WP_Post|int $post Entry.
 * @return array{id: int, date: string, time: string, title: string, note: string, type: string, channel: string,
 *               channels: string[], owner: string, status: string, link: string, flag: string, modified: string}|null
 *               modified is post_modified_gmt (Y-m-d H:i:s). channel is the
 *               first of channels (an entry may have several, 14 Nov 2026).
 */
function planweb_entry( $post ) {
	$post = get_post( $post );
	if ( ! $post || PLANWEB_TYPE !== $post->post_type ) {
		return null;
	}
	$term     = function ( $tax ) use ( $post ) {
		$t = get_the_terms( $post, $tax );
		return is_array( $t ) && $t ? (string) $t[0]->slug : '';
	};
	$status   = (string) get_post_meta( $post->ID, 'planweb_status', true );
	$channels = get_the_terms( $post, 'planweb_channel' );
	$channels = is_array( $channels ) ? array_values( array_map( fn( $t ) => (string) $t->slug, $channels ) ) : array();
	// The list's order, not the database's, so "X, LinkedIn" reads the same everywhere.
	$channels = array_merge( array_values( array_intersect( array_keys( PLANWEB_CHANNELS ), $channels ) ), array_values( array_diff( $channels, array_keys( PLANWEB_CHANNELS ) ) ) );
	return array(
		'id'       => (int) $post->ID,
		'date'     => (string) get_post_meta( $post->ID, 'planweb_date', true ),
		'time'     => (string) get_post_meta( $post->ID, 'planweb_time', true ),
		'title'    => (string) $post->post_title,
		'note'     => (string) $post->post_content,
		'type'     => $term( 'planweb_type' ),
		'channel'  => $channels[0] ?? '',
		'channels' => $channels,
		'owner'    => $term( 'planweb_owner' ),
		'status'   => '' === $status ? 'idea' : $status,
		'link'     => (string) get_post_meta( $post->ID, 'planweb_link', true ),
		'flag'     => (string) get_post_meta( $post->ID, 'planweb_flag', true ),
		'modified' => (string) $post->post_modified_gmt,
	);
}

/**
 * Entries between two dates (inclusive), oldest first; '' for an open end.
 *
 * @param string $from Y-m-d or ''.
 * @param string $to   Y-m-d or ''.
 * @param array  $args More WP_Query arguments (type and status filters).
 * @return array[] planweb_entry() arrays.
 */
function planweb_entries( string $from = '', string $to = '', array $args = array() ): array {
	if ( ! post_type_exists( PLANWEB_TYPE ) ) {
		return array();
	}
	$mq = array(
		'pw_date' => array(
			'key'  => 'planweb_date',
			'type' => 'DATE',
		),
	);
	if ( '' !== $from ) {
		$mq[] = array(
			'key'     => 'planweb_date',
			'value'   => $from,
			'compare' => '>=',
			'type'    => 'DATE',
		);
	}
	if ( '' !== $to ) {
		$mq[] = array(
			'key'     => 'planweb_date',
			'value'   => $to,
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}
	$q = new WP_Query(
		array_merge(
			array(
				'post_type'              => PLANWEB_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one calendar's worth; a few hundred at most.
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'meta_query'             => $mq, // phpcs:ignore WordPress.DB.SlowDBQuery -- a few hundred entries at most.
				'orderby'                => array(
					'pw_date' => 'ASC',
					'title'   => 'ASC',
				),
			),
			$args
		)
	);
	return array_values( array_filter( array_map( 'planweb_entry', $q->posts ) ) );
}

/**
 * The next entry from today, for Kits → Site: Animal Writes ("Next: …").
 *
 * @return array{0: string, 1: string}|null Date and title.
 */
function planweb_next() {
	$next = planweb_entries( wp_date( 'Y-m-d' ), '', array( 'posts_per_page' => 1 ) );
	return $next ? array( $next[0]['date'], $next[0]['title'] ) : null;
}

/**
 * Save an entry from the screen's form (the REST API saves through core). Checks first.
 *
 * @param array $f      Fields: title, note, date, time, type, channels (or channel), owner, status, link. channels is
 *                      a list of slugs (several allowed); without it, channel is one slug.
 * @param int   $id     Entry to update (0 for new).
 * @param bool  $people Run the personal-data check.
 * @return int|WP_Error Entry ID.
 */
function planweb_save_entry( array $f, int $id = 0, bool $people = true ) {
	$err = planweb_check_fields( $f, $id, $people );
	if ( $err ) {
		return $err;
	}
	$channels = planweb_channel_list( array_key_exists( 'channels', $f ) ? $f['channels'] : ( $f['channel'] ?? '' ) );
	foreach ( $channels as $slug ) {
		if ( ! isset( PLANWEB_CHANNELS[ $slug ] ) ) {
			return new WP_Error( 'planweb_channel', __( 'Pick a value from the list.', 'planweb' ) );
		}
	}
	if ( '' !== (string) ( $f['owner'] ?? '' ) && ! isset( PLANWEB_OWNERS[ (string) $f['owner'] ] ) ) {
		return new WP_Error( 'planweb_owner', __( 'Pick a value from the list.', 'planweb' ) );
	}
	$post = array(
		'post_type'    => PLANWEB_TYPE,
		'post_status'  => 'publish',
		'post_title'   => (string) $f['title'],
		'post_content' => (string) ( $f['note'] ?? '' ),
	);
	if ( $id ) {
		$post['ID'] = $id;
	}
	$id = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, 'planweb_date', (string) $f['date'] );
	update_post_meta( $id, 'planweb_time', (string) ( $f['time'] ?? '' ) );
	update_post_meta( $id, 'planweb_status', (string) ( $f['status'] ?? 'idea' ) );
	update_post_meta( $id, 'planweb_link', esc_url_raw( (string) ( $f['link'] ?? '' ) ) );
	foreach ( array(
		'planweb_type'  => 'type',
		'planweb_owner' => 'owner',
	) as $tax => $k ) {
		$slug = (string) ( $f[ $k ] ?? '' );
		wp_set_object_terms( $id, '' === $slug ? array() : array( $slug ), $tax );
	}
	wp_set_object_terms( $id, $channels, 'planweb_channel' );
	return (int) $id;
}
