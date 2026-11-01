<?php
/**
 * Animal Writes → Edit screens (owner, 1 Nov 2026): every box on every edit screen, the keys it stores and where each
 * key is used on the site. Read-only: nothing here writes an option, meta or post.
 *
 * The map lives in data/edit-screen-map.json (boxes by id, keys by stored name) and is read by aw_edit_screen_map();
 * other plugins add entries with the aw_edit_screen_boxes and aw_edit_screen_map filters. The boxes themselves are
 * read live: for each post type the screen is set up on an in-memory post (ID 0, never saved), add_meta_boxes runs,
 * $wp_meta_boxes is read and put back. No box callback is rendered. SCF field groups come from acf_get_field_groups().
 * Term screens: the callbacks on each taxonomy's form hooks. A box or field the map does not know shows "Not mapped yet".
 * Also here: the boxes that start collapsed on post edit screens (aw_edit_screen_collapsed_boxes(), core filter only).
 *
 * Screen: Animal Writes → Edit screens (manage_options). WP-CLI: wp aw-edit-screens [--type=<type>] [--format=table|json].
 * How to add an entry: docs/edit-screens.md.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The map file as decoded JSON: about, kinds, boxes, keys. */
function aw_edit_screen_map_data(): array {
	static $data = null;
	if ( null === $data ) {
		$file = AW_CORE_DIR . 'data/edit-screen-map.json';
		$raw  = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		$data = json_decode( $raw, true );
		$data = is_array( $data ) ? $data : array();
	}
	return $data;
}

/**
 * Every known box: id => title, source (WordPress / plugin name / Animal Writes core), file, screen (post|term|admin),
 * types (empty: any type), runtime (live, scf, fallback, edit-screen-only, always, map-only), notes, match (term screens), page (admin pages).
 */
function aw_edit_screen_boxes(): array {
	$data = aw_edit_screen_map_data();
	$out  = array();
	foreach ( (array) apply_filters( 'aw_edit_screen_boxes', (array) ( $data['boxes'] ?? array() ) ) as $id => $b ) {
		if ( is_array( $b ) ) {
			$out[ (string) $id ] = $b + array( 'title' => (string) $id, 'source' => '', 'file' => '', 'screen' => 'post', 'types' => array(), 'runtime' => 'live' );
		}
	}
	return $out;
}

/**
 * Every stored key: name => store (post_meta, term_meta, taxonomy, post_field, option), label, type, types (empty:
 * any type), boxes (list of id, title, source), uses (list of kind, where, file), notes (list).
 */
function aw_edit_screen_map(): array {
	$data  = aw_edit_screen_map_data();
	$boxes = aw_edit_screen_boxes();
	$keys  = array();
	foreach ( (array) ( $data['keys'] ?? array() ) as $name => $k ) {
		$list = array();
		foreach ( (array) ( $k['boxes'] ?? array() ) as $id ) {
			$list[] = array(
				'id'     => (string) $id,
				'title'  => (string) ( $boxes[ $id ]['title'] ?? $id ),
				'source' => (string) ( $boxes[ $id ]['source'] ?? '' ),
			);
		}
		$k['boxes']    = $list;
		$k['types']    = (array) ( $k['types'] ?? array() );
		$k['uses']     = (array) ( $k['uses'] ?? array() );
		$k['notes']    = (array) ( $k['notes'] ?? array() );
		$keys[ $name ] = $k;
	}
	$out = array();
	foreach ( (array) apply_filters( 'aw_edit_screen_map', $keys ) as $name => $k ) {
		if ( ! is_array( $k ) ) {
			continue;
		}
		$list = array();
		foreach ( (array) ( $k['boxes'] ?? array() ) as $b ) {
			$id = is_array( $b ) ? (string) ( $b['id'] ?? '' ) : (string) $b;
			if ( '' !== $id ) {
				$list[] = is_array( $b ) ? $b + array( 'title' => $id, 'source' => '' ) : array( 'id' => $id, 'title' => (string) ( $boxes[ $id ]['title'] ?? $id ), 'source' => (string) ( $boxes[ $id ]['source'] ?? '' ) );
			}
		}
		$k['boxes']           = $list;
		$k['type']            = (string) ( $k['type'] ?? '' );
		$k['types']           = (array) ( $k['types'] ?? array() );
		$k['uses']            = array_values( array_filter( (array) ( $k['uses'] ?? array() ), 'is_array' ) );
		$k['notes']           = array_map( 'strval', (array) ( $k['notes'] ?? array() ) );
		$out[ (string) $name ] = $k;
	}
	return $out;
}

/** Post types that have an edit screen (block-editor internals and SCF's own types left out). */
function aw_edit_screen_post_types(): array {
	$skip = array( 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'acf-field-group', 'acf-taxonomy', 'acf-post-type', 'acf-ui-options-page' );
	return array_values( array_diff( array_keys( get_post_types( array( 'show_ui' => true ) ) ), $skip ) );
}

/** Taxonomies that have a term screen. */
function aw_edit_screen_taxonomies(): array {
	return array_values( array_keys( get_taxonomies( array( 'show_ui' => true ) ) ) );
}

/**
 * Where a callback is defined, as "function @ path:line" (path from the site root), or '' when unknown.
 *
 * @param mixed $cb Callback.
 */
function aw_edit_screen_callback( $cb ): string {
	try {
		if ( is_string( $cb ) && function_exists( $cb ) ) {
			$r    = new ReflectionFunction( $cb );
			$name = $cb;
		} elseif ( is_array( $cb ) && isset( $cb[0], $cb[1] ) && is_string( $cb[1] ) ) {
			$cls  = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
			$r    = new ReflectionMethod( $cls, $cb[1] );
			$name = $cls . '::' . $cb[1];
		} elseif ( $cb instanceof Closure ) {
			$r    = new ReflectionFunction( $cb );
			$name = 'closure';
		} else {
			return '';
		}
		return $name . ' @ ' . str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( (string) $r->getFileName() ) ) . ':' . (int) $r->getStartLine();
	} catch ( Throwable $e ) {
		return '';
	}
}

/**
 * Source label from a callback location: WordPress, Animal Writes (plugin folder) or the plugin's folder name.
 *
 * @param string $where aw_edit_screen_callback() result.
 */
function aw_edit_screen_source_from( string $where ): string {
	if ( '' === $where ) {
		return 'Unknown';
	}
	if ( false !== strpos( $where, ' @ wp-admin/' ) || false !== strpos( $where, ' @ wp-includes/' ) ) {
		return 'WordPress';
	}
	if ( preg_match( '~/(?:plugins|mu-plugins)/([^/]+)/~', $where, $m ) ) {
		$ours = array( 'animal-writes-core', 'aw-seo-extras', 'aw-sourcing', 'kits-core', 'mailweb' );
		return in_array( $m[1], $ours, true ) ? 'Animal Writes (' . $m[1] . ')' : $m[1];
	}
	return 'Theme or other';
}

/**
 * The boxes each post type registers, read live: type => [ id => title, context, priority, callback, compat ].
 * In-memory post (ID 0), nothing saved, no callback rendered; $wp_meta_boxes, $post and the screen are put back.
 */
function aw_edit_screen_live_boxes(): array {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
	global $wp_meta_boxes, $post;
	$keep_boxes  = $wp_meta_boxes;
	$keep_post   = $post;
	$keep_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	try {
		aw_edit_screen_read_boxes( $out );
	} finally {
		$wp_meta_boxes = $keep_boxes; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored.
		$post          = $keep_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored.
		if ( $keep_screen instanceof WP_Screen ) {
			$keep_screen->set_current_screen();
		} else {
			$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- no screen before (WP-CLI).
			$GLOBALS['typenow']        = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- set by set_current_screen().
			$GLOBALS['taxnow']         = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- set by set_current_screen().
		}
	}
	return $out;
}

/**
 * Fills $out with each post type's boxes. Changes $wp_meta_boxes, $post and the screen; the caller puts them back.
 *
 * @param array $out Filled: type => boxes.
 */
function aw_edit_screen_read_boxes( array &$out ): void {
	global $wp_meta_boxes, $post;
	foreach ( aw_edit_screen_post_types() as $type ) {
		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- put back below.
		$dummy         = new WP_Post(
			(object) array(
				'ID'             => 0,
				'post_type'      => $type,
				'post_status'    => 'auto-draft',
				'post_author'    => get_current_user_id(),
				'post_title'     => '',
				'post_content'   => '',
				'post_excerpt'   => '',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'filter'         => 'raw',
			)
		);
		$post = $dummy; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- put back below.
		set_current_screen( $type );
		if ( function_exists( 'register_and_do_post_meta_boxes' ) ) {
			register_and_do_post_meta_boxes( $dummy ); // core boxes + add_meta_boxes + add_meta_boxes_{type} + do_meta_boxes
		} else {
			do_action( 'add_meta_boxes', $type, $dummy );
			do_action( "add_meta_boxes_{$type}", $dummy );
		}
		$out[ $type ] = array();
		foreach ( (array) ( $wp_meta_boxes[ $type ] ?? array() ) as $ctx => $prios ) {
			foreach ( (array) $prios as $prio => $list ) {
				foreach ( (array) $list as $id => $b ) {
					if ( ! is_array( $b ) ) {
						continue; // removed
					}
					$out[ $type ][ (string) $id ] = array(
						'title'    => wp_strip_all_tags( (string) $b['title'] ),
						'context'  => (string) $ctx,
						'priority' => (string) $prio,
						'callback' => aw_edit_screen_callback( $b['callback'] ),
						'compat'   => ! empty( $b['args']['__back_compat_meta_box'] ),
					);
				}
			}
		}
	}
}

/** SCF field groups when SCF is active: acf-{key} => title, types, hide (box ids), fields (name => type). */
function aw_edit_screen_scf_groups(): array {
	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
		return array();
	}
	$hide_map = array(
		'excerpt'         => 'postexcerpt',
		'slug'            => 'slugdiv',
		'author'          => 'authordiv',
		'page_attributes' => 'pageparentdiv',
		'featured_image'  => 'postimagediv',
		'categories'      => 'categorydiv',
		'tags'            => 'tagsdiv-post_tag',
		'revisions'       => 'revisionsdiv',
		'comments'        => 'commentsdiv',
		'discussion'      => 'commentstatusdiv',
		'format'          => 'formatdiv',
	);
	$out = array();
	foreach ( (array) acf_get_field_groups() as $g ) {
		if ( empty( $g['key'] ) || empty( $g['active'] ) ) {
			continue;
		}
		$types = array();
		foreach ( (array) ( $g['location'] ?? array() ) as $and ) {
			foreach ( (array) $and as $rule ) {
				if ( 'post_type' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '' ) ) {
					$types[] = (string) $rule['value'];
				}
			}
		}
		$fields = array();
		foreach ( (array) acf_get_fields( $g ) as $f ) {
			if ( ! empty( $f['name'] ) ) {
				$fields[ (string) $f['name'] ] = (string) $f['type'];
			}
		}
		$hide = array();
		foreach ( (array) ( $g['hide_on_screen'] ?? array() ) as $h ) {
			if ( isset( $hide_map[ $h ] ) ) {
				$hide[] = $hide_map[ $h ];
			}
		}
		$out[ 'acf-' . $g['key'] ] = array(
			'title'  => wp_strip_all_tags( (string) $g['title'] ),
			'types'  => array_values( array_unique( $types ) ),
			'hide'   => $hide,
			'fields' => $fields,
		);
	}
	return $out;
}

/** The callbacks on each taxonomy's form hooks: tax => list of hook, priority, callback. */
function aw_edit_screen_live_terms(): array {
	global $wp_filter;
	$out = array();
	foreach ( aw_edit_screen_taxonomies() as $tax ) {
		$out[ $tax ] = array();
		foreach ( array( 'add_form_fields', 'edit_form_fields', 'edit_form', 'term_edit_form_top', 'add_form' ) as $suffix ) {
			$hook = $tax . '_' . $suffix;
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( $cbs as $c ) {
					$out[ $tax ][] = array(
						'hook'     => $suffix,
						'priority' => (int) $prio,
						'callback' => aw_edit_screen_callback( $c['function'] ),
					);
				}
			}
		}
	}
	return $out;
}

/**
 * Does a term-screen map box match a live hook callback?
 *
 * @param array  $match Box 'match'.
 * @param array  $h     Live hook (hook, priority, callback).
 * @param string $tax   Taxonomy.
 */
function aw_edit_screen_term_match( array $match, array $h, string $tax ): bool {
	if ( ! empty( $match['taxonomies'] ) && ! in_array( $tax, (array) $match['taxonomies'], true ) ) {
		return false;
	}
	if ( ! in_array( $h['hook'], (array) ( $match['hooks'] ?? array() ), true ) ) {
		return false;
	}
	if ( isset( $match['priority'] ) && (int) $match['priority'] !== (int) $h['priority'] ) {
		return false;
	}
	if ( ! empty( $match['callback'] ) && 0 !== strpos( $h['callback'], $match['callback'] . ' @' ) ) {
		return false;
	}
	if ( ! empty( $match['file'] ) && false === strpos( $h['callback'], (string) $match['file'] . ':' ) ) {
		return false;
	}
	return true;
}

/**
 * The keys a box stores on a type: name => map entry.
 *
 * @param string $box_id Box id.
 * @param string $type   Post type or taxonomy.
 * @param array  $map    aw_edit_screen_map().
 */
function aw_edit_screen_box_keys( string $box_id, string $type, array $map ): array {
	$out = array();
	foreach ( $map as $name => $k ) {
		if ( ! in_array( $box_id, wp_list_pluck( $k['boxes'], 'id' ), true ) ) {
			continue;
		}
		if ( $k['types'] && ! in_array( $type, $k['types'], true ) ) {
			continue;
		}
		$out[ $name ] = $k;
	}
	return $out;
}

/**
 * Every row: one per box per post type, taxonomy or admin page. Each row: screen (post|term|admin), type, id, title, source, file,
 * shows_on, mapped (bool), fields (key => type), uses (key => list of kind, where, file), notes (list).
 */
function aw_edit_screen_rows(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	$rows  = array();
	$boxes = aw_edit_screen_boxes();
	$map   = aw_edit_screen_map();
	$live  = aw_edit_screen_live_boxes();
	$scf   = aw_edit_screen_scf_groups();
	$add   = function ( string $screen, string $type, string $id, array $info, array $extra_fields, array $notes ) use ( &$rows, $boxes, $map ) {
		$known  = isset( $boxes[ $id ] );
		$b      = $known ? $boxes[ $id ] : array();
		$keys   = $known ? aw_edit_screen_box_keys( $id, $type, $map ) : array();
		$fields = array();
		$uses   = array();
		foreach ( $keys as $name => $k ) {
			$fields[ $name ] = (string) $k['type'];
			$uses[ $name ]   = $k['uses'];
			foreach ( $k['notes'] as $n ) {
				$notes[] = $name . ': ' . $n;
			}
		}
		foreach ( $extra_fields as $name => $t ) {
			if ( ! isset( $fields[ $name ] ) ) {
				$fields[ $name ] = $t;
				if ( ! isset( $map[ $name ] ) ) {
					$notes[] = $name . ': Not mapped yet.';
				}
			}
		}
		if ( ! empty( $b['notes'] ) ) {
			$notes = array_merge( array_map( 'strval', (array) $b['notes'] ), $notes );
		}
		if ( ! $known ) {
			array_unshift( $notes, 'Not mapped yet.' );
		}
		$rows[] = array(
			'screen'   => $screen,
			'type'     => $type,
			'id'       => $id,
			'title'    => $known ? (string) $b['title'] : (string) ( $info['title'] ?? $id ),
			'source'   => $known ? (string) $b['source'] : aw_edit_screen_source_from( (string) ( $info['callback'] ?? '' ) ),
			'file'     => $known ? (string) $b['file'] : (string) ( $info['callback'] ?? '' ),
			'shows_on' => (string) ( $info['shows_on'] ?? '' ),
			'mapped'   => $known,
			'fields'   => $fields,
			'uses'     => $uses,
			'notes'    => $notes,
		);
	};

	foreach ( $live as $type => $list ) {
		$block = function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $type );
		$seen  = array();
		$hide  = array();
		foreach ( $scf as $gid => $g ) {
			if ( in_array( $type, $g['types'], true ) ) {
				foreach ( $g['hide'] as $h ) {
					$hide[ $h ] = $g['title'];
				}
			}
		}
		foreach ( $list as $id => $b ) {
			$seen[ $id ] = true;
			$where       = $b['context'] . ' / ' . $b['priority'];
			if ( $block && $b['compat'] ) {
				$where .= ' (sidebar panel in the block editor)';
			}
			$notes = isset( $hide[ $id ] ) ? array( 'Hidden by the SCF group "' . $hide[ $id ] . '".' ) : array();
			$extra = isset( $scf[ $id ] ) ? $scf[ $id ]['fields'] : array();
			$add( 'post', $type, $id, $b + array( 'shows_on' => $where ), $extra, $notes );
		}
		foreach ( $scf as $gid => $g ) {
			if ( isset( $seen[ $gid ] ) || ! in_array( $type, $g['types'], true ) ) {
				continue;
			}
			$seen[ $gid ] = true;
			$add( 'post', $type, $gid, array( 'title' => $g['title'], 'callback' => 'SCF field group', 'shows_on' => 'normal (SCF field group)' ), $g['fields'], array() );
		}
		foreach ( $boxes as $id => $b ) {
			if ( isset( $seen[ $id ] ) || 'post' !== ( $b['screen'] ?? 'post' ) || empty( $b['types'] ) || ! in_array( $type, (array) $b['types'], true ) ) {
				continue;
			}
			$rt = (string) ( $b['runtime'] ?? 'live' );
			if ( 'fallback' === $rt || ( 'scf' === $rt && $scf ) ) {
				continue; // a fallback shows only when registered; an SCF group comes from SCF itself
			}
			$note = 'edit-screen-only' === $rt ? 'Registered only on the edit screen itself; listed from the map.' : ( 'scf' === $rt ? 'SCF is not active: this group does not show.' : 'Not registered on this page load; listed from the map.' );
			$add( 'post', $type, $id, array( 'shows_on' => '—' ), array(), array( $note ) );
		}
	}

	foreach ( aw_edit_screen_live_terms() as $tax => $hooks ) {
		$seen = array();
		$add( 'term', $tax, 'term-core', array( 'shows_on' => 'add and edit forms' ), array(), array() );
		foreach ( $hooks as $h ) {
			$hit = '';
			foreach ( $boxes as $id => $b ) {
				if ( 'term' === ( $b['screen'] ?? '' ) && ! empty( $b['match'] ) && aw_edit_screen_term_match( (array) $b['match'], $h, $tax ) ) {
					$hit = $id;
					break;
				}
			}
			$id = '' !== $hit ? $hit : $tax . '_' . $h['hook'] . '@' . $h['priority'];
			if ( isset( $seen[ $id ] ) ) {
				$seen[ $id ][] = $h['hook'];
				continue;
			}
			$seen[ $id ] = array( $h['hook'] );
			$add( 'term', $tax, $id, array( 'title' => $h['hook'], 'callback' => $h['callback'], 'shows_on' => $h['hook'] . ' / ' . $h['priority'] ), array(), array() );
		}
		foreach ( $boxes as $id => $b ) {
			if ( isset( $seen[ $id ] ) || 'term' !== ( $b['screen'] ?? '' ) || empty( $b['types'] ) || ! in_array( $tax, (array) $b['types'], true ) ) {
				continue;
			}
			$note = 'edit-screen-only' === ( $b['runtime'] ?? '' ) ? 'Registered only on the term screen itself; listed from the map.' : 'Not registered on this page load; listed from the map.';
			$add( 'term', $tax, $id, array( 'shows_on' => '—' ), array(), array( $note ) );
		}
	}

	// Admin pages that are not edit screens (Dashboard widgets, report and settings pages): listed from the map, one row per
	// page in "types". What a widget reads, sets and its limits go in the notes, never in fields: these rows edit no box key.
	foreach ( $boxes as $id => $b ) {
		if ( 'admin' === ( $b['screen'] ?? '' ) ) {
			$notes = array( 'Listed from the map, not read live.' );
			if ( ! empty( $b['reads'] ) ) {
				$notes[] = 'Reads: ' . implode( ', ', array_map( 'strval', (array) $b['reads'] ) ) . '.';
			}
			if ( ! empty( $b['sets'] ) ) {
				$notes[] = 'Sets from this page (not from an edit-screen box): ' . implode( ', ', array_map( 'strval', (array) $b['sets'] ) ) . '.';
			}
			if ( ! empty( $b['limits'] ) ) {
				$notes[] = 'Limits: ' . (string) $b['limits'];
			}
			foreach ( (array) $b['types'] as $page ) {
				$add( 'admin', (string) $page, $id, array( 'shows_on' => (string) ( $b['page'] ?? $page ) ), array(), $notes );
			}
		}
	}

	// Same key edited by two boxes on one screen.
	$by = array();
	foreach ( $rows as $i => $r ) {
		if ( 'postcustom' === $r['id'] ) {
			continue; // Custom Fields can edit any key
		}
		foreach ( array_keys( $r['fields'] ) as $k ) {
			$by[ $r['type'] ][ $k ][] = $i;
		}
	}
	foreach ( $by as $list ) {
		foreach ( $list as $k => $idx ) {
			if ( count( $idx ) < 2 ) {
				continue;
			}
			foreach ( $idx as $i ) {
				$others = array();
				foreach ( $idx as $j ) {
					if ( $j !== $i ) {
						$others[] = '"' . $rows[ $j ]['title'] . '"';
					}
				}
				$rows[ $i ]['notes'][] = $k . ': also edited in ' . implode( ', ', $others ) . ' on this screen.';
			}
		}
	}
	foreach ( $rows as $i => $r ) {
		$rows[ $i ]['notes'] = array_values( array_unique( $r['notes'] ) );
	}
	return $rows;
}

/**
 * Label for a row's type: the post type or taxonomy, or for an admin page the map's "page" (e.g. "Dashboard (widgets)").
 *
 * @param string $type   Post type, taxonomy or admin page id.
 * @param string $screen post, term or admin.
 */
function aw_edit_screen_type_label( string $type, string $screen ): string {
	if ( 'admin' !== $screen ) {
		return $type;
	}
	foreach ( aw_edit_screen_boxes() as $b ) {
		if ( 'admin' === ( $b['screen'] ?? '' ) && in_array( $type, (array) $b['types'], true ) && ! empty( $b['page'] ) ) {
			return (string) $b['page'];
		}
	}
	return $type;
}

/**
 * Rows for one post type or taxonomy ('' for all).
 *
 * @param string $type Post type or taxonomy.
 */
function aw_edit_screen_rows_for( string $type = '' ): array {
	return array_values( array_filter( aw_edit_screen_rows(), fn( $r ) => '' === $type || $r['type'] === $type ) );
}

/**
 * How many rows (boxes or term hooks) and fields are not mapped yet.
 *
 * @param array $rows Rows.
 */
function aw_edit_screen_unmapped( array $rows ): int {
	$n = 0;
	foreach ( $rows as $r ) {
		$n += $r['mapped'] ? 0 : 1;
		foreach ( $r['notes'] as $note ) {
			$n += ( ': Not mapped yet.' === substr( $note, -17 ) ) ? 1 : 0;
		}
	}
	return $n;
}

/**
 * Boxes that start collapsed on every post edit screen (owner, 1 Nov 2026): plugin boxes and rarely used ones.
 * Only core's postbox_classes_{screen_id}_{box_id} filter is used, and only until the user opens or closes a box on
 * that screen (core then saves closedpostboxes_{screen}, which wins). The user can still open, drag and hide them.
 */
function aw_edit_screen_collapsed_boxes(): array {
	return array( 'litespeed_meta_boxes', 'wpil_link-articles', 'rank_math_metabox_link_suggestions', 'postcustom', 'slugdiv', 'aw_linked_from' );
}

/**
 * Adds core's "closed" class to a box; never removes a class.
 *
 * @param mixed $classes Classes from postbox_classes().
 */
function aw_edit_screen_box_closed( $classes ): array {
	$classes = is_array( $classes ) ? $classes : array();
	if ( ! in_array( 'closed', $classes, true ) ) {
		$classes[] = 'closed';
	}
	return $classes;
}

add_action(
	'current_screen',
	function ( $screen ) {
		if ( ! is_admin() || ! $screen instanceof WP_Screen || 'post' !== $screen->base || ! in_array( $screen->post_type, aw_edit_screen_post_types(), true ) ) {
			return;
		}
		if ( false !== get_user_option( 'closedpostboxes_' . $screen->id ) ) {
			return; // the user has opened or closed a box on this screen: their own choice wins
		}
		foreach ( aw_edit_screen_collapsed_boxes() as $box ) {
			$hook = 'postbox_classes_' . $screen->id . '_' . $box;
			if ( false === has_filter( $hook, 'aw_edit_screen_box_closed' ) ) {
				add_filter( $hook, 'aw_edit_screen_box_closed' );
			}
		}
	}
);

/**
 * Plugin and purpose of a row's box, from the map's optional 'plugin' and 'purpose' fields. Without them: the row's
 * source and '—'.
 *
 * @param array $r Row.
 */
function aw_edit_screen_row_about( array $r ): array {
	static $boxes = null;
	if ( null === $boxes ) {
		$boxes = aw_edit_screen_boxes();
	}
	$b       = $r['mapped'] ? (array) ( $boxes[ $r['id'] ] ?? array() ) : array();
	$plugin  = trim( (string) ( $b['plugin'] ?? '' ) );
	$purpose = trim( (string) ( $b['purpose'] ?? '' ) );
	return array(
		'plugin'  => '' !== $plugin ? $plugin : (string) $r['source'],
		'purpose' => '' !== $purpose ? $purpose : '—',
	);
}

/**
 * Is the row a WordPress core box (mapped, source "WordPress")? These are shown once, in "General (all types)".
 *
 * @param array $r Row.
 */
function aw_edit_screen_is_core( array $r ): bool {
	return $r['mapped'] && 'WordPress' === $r['source'];
}

/**
 * Box ids shown as one row across all their types: group => ids.
 */
function aw_edit_screen_merged_boxes(): array {
	return array( 'faq' => array( 'aw-faq', 'term-faq' ) );
}

/**
 * Rows as listed: one "General (all types)" row for the WordPress core boxes first, then the rest in order, with each
 * merged box (FAQ) as one row across its types. Each row also has label (Type column), types, plugin and purpose.
 *
 * @param array $rows Rows.
 */
function aw_edit_screen_display_rows( array $rows ): array {
	$merge = array();
	foreach ( aw_edit_screen_merged_boxes() as $group => $ids ) {
		foreach ( $ids as $id ) {
			$merge[ $id ] = $group;
		}
	}
	$general = array();
	$out     = array();
	$at      = array(); // group => index in $out
	foreach ( $rows as $r ) {
		$r += aw_edit_screen_row_about( $r );
		if ( aw_edit_screen_is_core( $r ) ) {
			$general[] = $r;
			continue;
		}
		$group = $r['mapped'] && isset( $merge[ $r['id'] ] ) ? $merge[ $r['id'] ] : '';
		if ( '' === $group ) {
			$out[] = $r + array( 'label' => aw_edit_screen_type_label( $r['type'], $r['screen'] ), 'types' => array( $r['type'] ) );
			continue;
		}
		if ( ! isset( $at[ $group ] ) ) {
			$at[ $group ] = count( $out );
			$out[]        = $r + array( 'label' => '', 'types' => array(), 'ids' => array() );
		}
		$out[ $at[ $group ] ] = aw_edit_screen_join_row( $out[ $at[ $group ] ], $r );
	}
	foreach ( $at as $i ) {
		$out[ $i ]['label'] = implode( ', ', $out[ $i ]['types'] );
		$out[ $i ]['id']    = implode( ', ', $out[ $i ]['ids'] );
	}
	if ( $general ) {
		$row = array(
			'screen'   => 'general',
			'type'     => '',
			'label'    => 'General (all types)',
			'types'    => array(),
			'ids'      => array(),
			'title'    => '',
			'titles'   => array(),
			'plugin'   => 'WordPress',
			'purpose'  => '—',
			'source'   => 'WordPress',
			'file'     => 'wp-admin/includes/meta-boxes.php, wp-admin/edit-tag-form.php',
			'shows_on' => 'Each type that supports the box',
			'mapped'   => true,
			'fields'   => array(),
			'uses'     => array(),
			'notes'    => array(),
		);
		foreach ( $general as $r ) {
			if ( ! in_array( $r['id'], $row['ids'], true ) ) {
				$row['titles'][] = $r['title'] . ( 'term' === $r['screen'] ? ' (term screens)' : ( 'admin' === $r['screen'] ? ' — ' . aw_edit_screen_type_label( $r['type'], 'admin' ) : '' ) );
			}
			foreach ( $r['notes'] as $n ) { // every type's notes, not only the first type's
				$row['notes'][] = $r['title'] . ( '' !== (string) $r['type'] ? ' (' . $r['type'] . ')' : '' ) . ': ' . $n;
			}
			$row = aw_edit_screen_join_row( $row, $r, false );
		}
		$row['title'] = implode( ', ', $row['titles'] );
		$row['id']    = implode( ', ', $row['ids'] );
		array_unshift( $out, $row );
	}
	return $out;
}

/**
 * Adds a row's types, ids, fields, uses and (optionally) notes to a merged row.
 *
 * @param array $into  Merged row (types, ids, fields, uses, notes).
 * @param array $r     Row.
 * @param bool  $notes Also join the notes.
 */
function aw_edit_screen_join_row( array $into, array $r, bool $notes = true ): array {
	if ( ! in_array( $r['type'], $into['types'], true ) ) {
		$into['types'][] = $r['type'];
	}
	if ( ! in_array( $r['id'], $into['ids'], true ) ) {
		$into['ids'][] = $r['id'];
	}
	foreach ( $r['fields'] as $k => $t ) {
		$into['fields'][ $k ] = $into['fields'][ $k ] ?? $t;
		$into['uses'][ $k ]   = $into['uses'][ $k ] ?? (array) ( $r['uses'][ $k ] ?? array() );
	}
	if ( $notes ) {
		$into['notes'] = array_values( array_unique( array_merge( $into['notes'], $r['notes'] ) ) );
	}
	return $into;
}

/**
 * Keys editable from more than one box on one type's screen: list of type, key, boxes ("Title [id]"). Custom Fields (it can
 * edit any key) and boxes or keys the map marks "readonly" are left out.
 *
 * @param array $rows Rows.
 */
function aw_edit_screen_twice( array $rows ): array {
	$boxes = aw_edit_screen_boxes();
	$map   = aw_edit_screen_map();
	$by    = array();
	foreach ( $rows as $r ) {
		if ( 'postcustom' === $r['id'] || ( $r['mapped'] && ! empty( $boxes[ $r['id'] ]['readonly'] ) ) ) {
			continue;
		}
		foreach ( array_keys( $r['fields'] ) as $k ) {
			if ( empty( $map[ $k ]['readonly'] ) ) {
				$by[ $r['type'] ][ (string) $k ][ $r['id'] ] = $r['title'] . ' [' . $r['id'] . ']';
			}
		}
	}
	$out = array();
	foreach ( $by as $type => $keys ) {
		foreach ( $keys as $k => $list ) {
			if ( count( $list ) > 1 ) {
				$out[] = array(
					'type'  => (string) $type,
					'key'   => $k,
					'boxes' => array_values( $list ),
				);
			}
		}
	}
	return $out;
}

add_action(
	'admin_menu',
	function () {
		$hook = add_submenu_page( AW_CORE_MENU, 'Edit screens', 'Edit screens', 'manage_options', 'aw-edit-screens', 'aw_edit_screens_page' ); // Animal Writes → Edit screens
		if ( $hook ) {
			add_action(
				'load-' . $hook,
				function () {
					if ( current_user_can( 'manage_options' ) ) {
						aw_edit_screen_rows(); // read the boxes before the page prints anything
					}
				}
			);
		}
	},
	14 // after Keys (13)
);

/** The note on rows that are listed from the map, not read live (page and command). */
function aw_edit_screen_map_only_note(): string {
	return 'Sidebar panels that plugins add with JavaScript in the block editor, Dashboard widgets and the panels, boxes and tables on admin pages (AW Sourcing, AW SEO Plus and Search Insights, Animal Writes, Mailweb, Kits Core) are listed from the map, not read live.';
}

/** Plugins that are ours: their boxes are on the Mapping tab, every other plugin's on "Third-party boxes". */
function aw_edit_screen_own_plugins(): array {
	return array( 'Animal Writes core', 'AW SEO Plus', 'AW Sourcing', 'Kits Core', 'Mailweb' );
}

/**
 * A module's label: the post type's or taxonomy's name ("News", "Categories"); for an admin page its heading.
 *
 * @param string $type   Post type, taxonomy or admin page id.
 * @param string $screen post, term or admin.
 */
function aw_edit_screen_module_label( string $type, string $screen ): string {
	$obj = 'term' === $screen ? get_taxonomy( $type ) : ( 'post' === $screen ? get_post_type_object( $type ) : null );
	return $obj ? (string) $obj->labels->name : aw_edit_screen_type_label( $type, $screen );
}

/**
 * Display rows joined into one row per box across its types (admin rows stay one per page). The General row is left out.
 *
 * @param array $display aw_edit_screen_display_rows().
 */
function aw_edit_screen_rows_by_box( array $display ): array {
	$out = array();
	foreach ( $display as $r ) {
		if ( 'general' === $r['screen'] ) {
			continue;
		}
		$key = 'admin' === $r['screen'] ? 'admin|' . $r['id'] . '|' . $r['type'] : $r['screen'] . '|' . $r['id'];
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = $r;
			continue;
		}
		foreach ( $r['types'] as $t ) {
			if ( ! in_array( $t, $out[ $key ]['types'], true ) ) {
				$out[ $key ]['types'][] = $t;
			}
		}
		foreach ( $r['fields'] as $k => $t ) {
			if ( ! isset( $out[ $key ]['fields'][ $k ] ) ) {
				$out[ $key ]['fields'][ $k ] = $t;
				$out[ $key ]['uses'][ $k ]   = (array) ( $r['uses'][ $k ] ?? array() );
			}
		}
		$out[ $key ]['notes'] = array_values( array_unique( array_merge( $out[ $key ]['notes'], $r['notes'] ) ) );
	}
	return array_values( $out );
}

/**
 * The Box cell: title, id and the box's "Fields and uses".
 *
 * @param array $r     Display row.
 * @param bool  $notes Also list the notes inside "Fields and uses".
 */
function aw_edit_screen_box_cell( array $r, bool $notes ): string {
	return '<strong>' . esc_html( $r['title'] ) . '</strong><br><code>' . esc_html( $r['id'] ) . '</code>' . aw_edit_screen_fields_details( $r, $notes );
}

/**
 * "Fields and uses" (Field, Stored as, Used where, then optionally the notes), or '' when there is nothing to show.
 *
 * @param array $r     Display row.
 * @param bool  $notes Also list the notes.
 */
function aw_edit_screen_fields_details( array $r, bool $notes ): string {
	$notes = $notes ? $r['notes'] : array();
	if ( ! $r['fields'] && ! $notes ) {
		return '';
	}
	$html = '<details><summary>Fields and uses</summary>';
	if ( $r['fields'] ) {
		$html .= '<table class="widefat striped"><thead><tr><th scope="col">Field</th><th scope="col">Stored as</th><th scope="col">Used where</th></tr></thead><tbody>';
		foreach ( $r['fields'] as $k => $t ) {
			$parts = array();
			foreach ( (array) ( $r['uses'][ $k ] ?? array() ) as $u ) {
				$parts[] = '<strong>' . esc_html( (string) ( $u['kind'] ?? '' ) ) . '</strong> ' . esc_html( (string) ( $u['where'] ?? '' ) ) . ( empty( $u['file'] ) ? '' : ' <code>' . esc_html( (string) $u['file'] ) . '</code>' );
			}
			$html .= '<tr><td><code>' . esc_html( (string) $k ) . '</code></td><td>' . esc_html( (string) $t ) . '</td><td>' . ( $parts ? implode( '<br>', $parts ) : 'not used' ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
	}
	if ( $notes ) {
		$html .= aw_edit_screen_notes_list( $notes );
	}
	return $html . '</details>';
}

/**
 * Notes as a list, or '—'.
 *
 * @param array $notes Notes.
 */
function aw_edit_screen_notes_list( array $notes ): string {
	return $notes ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $notes ) ) . '</li></ul>' : '—';
}

/**
 * Prints a plain core table (widefat striped). Cells arrive as escaped HTML.
 *
 * @param array $cols Column => label.
 * @param array $rows Rows of column => HTML.
 */
function aw_edit_screens_grid( array $cols, array $rows ): void {
	echo '<table class="widefat striped"><thead><tr>';
	foreach ( $cols as $label ) {
		echo '<th scope="col">' . esc_html( $label ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="' . (int) count( $cols ) . '">None.</td></tr>';
	}
	foreach ( $rows as $r ) {
		echo '<tr>';
		foreach ( array_keys( $cols ) as $c ) {
			echo '<td>' . (string) ( $r[ $c ] ?? '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cells arrive escaped.
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/** Animal Writes → Edit screens. */
function aw_edit_screens_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	aw_edit_screens_render( aw_edit_screen_rows() );
}

/**
 * The page: the summary and the checks, then the tabs Mapping, Schema and Third-party boxes. Read-only.
 *
 * @param array $all aw_edit_screen_rows().
 */
function aw_edit_screens_render( array $all ): void {
	$tabs = array(
		'mapping'     => 'Mapping',
		'schema'      => 'Schema',
		'third-party' => 'Third-party boxes',
	);
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab.
	$tab  = isset( $tabs[ $tab ] ) ? $tab : 'mapping';
	$base = admin_url( 'admin.php?page=aw-edit-screens' );

	$screens = array(); // post type or taxonomy => post|term, in screen order
	$ids     = array();
	$admin   = 0;
	foreach ( $all as $r ) {
		if ( 'admin' === $r['screen'] ) {
			$admin += in_array( aw_edit_screen_row_about( $r )['plugin'], aw_edit_screen_own_plugins(), true ) ? 1 : 0; // ours only, as in Admin pages under Mapping
			continue;
		}
		$screens[ $r['type'] ] = $r['screen'];
		$ids[ $r['id'] ]       = true;
	}
	$unmapped = aw_edit_screen_unmapped( $all );
	$twice    = aw_edit_screen_twice( $all );

	echo '<div class="wrap"><h1>Edit screens</h1>';
	echo '<p>Every box on each edit screen, the keys it stores and where each key is used. Boxes are read from the plugins as they run now; what each key does comes from the map in <code>animal-writes-core/data/edit-screen-map.json</code> (see <code>docs/edit-screens.md</code>). Read-only.</p>';
	$text = esc_html( sprintf( '%d boxes on %d edit screens, %d widgets on admin pages.', count( $ids ), count( $screens ), $admin ) );
	$text .= ' ' . ( $unmapped ? '<a href="#aw-es-unmapped">' . esc_html( sprintf( '%d boxes or fields are not mapped yet.', $unmapped ) ) . '</a>' : 'Every box is mapped.' );
	$text .= ' ' . ( $twice ? '<a href="#aw-es-twice">' . esc_html( sprintf( '%d fields are edited in two places.', count( $twice ) ) ) . '</a>' : 'No field is edited in two places.' );
	echo '<div class="notice ' . ( $unmapped || $twice ? 'notice-warning' : 'notice-success' ) . ' inline"><p>' . $text . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	if ( $unmapped ) {
		aw_edit_screens_unmapped_section( $all, $screens );
	}
	if ( $twice ) {
		aw_edit_screens_twice_section( $all );
	}

	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $t => $label ) {
		$on = $tab === $t;
		echo '<a href="' . esc_url( add_query_arg( 'tab', $t, $base ) ) . '" class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	echo '<p class="description">' . esc_html( aw_edit_screen_map_only_note() ) . '</p>';

	$display = aw_edit_screen_display_rows( $all );
	$label   = function ( string $type ) use ( $screens ): string {
		return aw_edit_screen_module_label( $type, $screens[ $type ] ?? 'post' );
	};
	if ( 'schema' === $tab ) {
		aw_edit_screens_schema_tab( $all, $label );
	} elseif ( 'third-party' === $tab ) {
		aw_edit_screens_third_party_tab( $display, $label );
	} else {
		aw_edit_screens_mapping_tab( $display, $screens, $label );
	}
	echo '</div>';
}

/**
 * Mapping tab: our own boxes as a grid, one column per module, then our admin-page widgets.
 *
 * @param array    $display Display rows.
 * @param array    $screens Post type or taxonomy => post|term, in order.
 * @param callable $label   Module label.
 */
function aw_edit_screens_mapping_tab( array $display, array $screens, callable $label ): void {
	$own   = aw_edit_screen_own_plugins();
	$fold  = aw_edit_screen_collapsed_boxes();
	$boxes = array();
	foreach ( aw_edit_screen_rows_by_box( $display ) as $r ) {
		if ( 'admin' !== $r['screen'] && in_array( $r['plugin'], $own, true ) ) {
			if ( in_array( $r['id'], $fold, true ) ) {
				array_unshift( $r['notes'], 'Collapsed by default.' );
			}
			$boxes[] = $r;
		}
	}
	usort(
		$boxes,
		fn( $a, $b ) => strcasecmp( $a['plugin'], $b['plugin'] ) ?: ( strcasecmp( $a['purpose'], $b['purpose'] ) ?: strcasecmp( $a['title'], $b['title'] ) )
	);
	$used = array();
	foreach ( $boxes as $r ) {
		foreach ( $r['types'] as $t ) {
			$used[ $t ] = true;
		}
	}
	$cols = array(
		'box'     => 'Box',
		'plugin'  => 'Plugin',
		'purpose' => 'Purpose',
	);
	foreach ( array_keys( $screens ) as $t ) {
		if ( isset( $used[ $t ] ) ) {
			$cols[ 'm:' . $t ] = $label( (string) $t );
		}
	}
	$cells = array();
	foreach ( $boxes as $r ) {
		$row = array(
			'box'     => aw_edit_screen_box_cell( $r, true ),
			'plugin'  => esc_html( $r['plugin'] ),
			'purpose' => esc_html( $r['purpose'] ),
		);
		foreach ( $r['types'] as $t ) {
			$row[ 'm:' . $t ] = '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">Yes</span>';
		}
		$cells[] = $row;
	}
	aw_edit_screens_grid( $cols, $cells );

	$widgets = array();
	foreach ( $display as $r ) {
		if ( 'admin' === $r['screen'] && in_array( $r['plugin'], $own, true ) ) {
			$widgets[] = array(
				'page'    => esc_html( $r['label'] ),
				'widget'  => aw_edit_screen_box_cell( $r, true ),
				'plugin'  => esc_html( $r['plugin'] ),
				'purpose' => esc_html( $r['purpose'] ),
			);
		}
	}
	echo '<details><summary>' . esc_html( sprintf( 'Admin pages: %d widgets', count( $widgets ) ) ) . '</summary>';
	aw_edit_screens_grid(
		array(
			'page'    => 'Page',
			'widget'  => 'Widget',
			'plugin'  => 'Plugin',
			'purpose' => 'Purpose',
		),
		$widgets
	);
	echo '</details>';
}

/**
 * Schema tab: every stored field the site's schema reads (map keys with a use of kind "schema").
 *
 * @param array    $all   Rows.
 * @param callable $label Module label.
 */
function aw_edit_screens_schema_tab( array $all, callable $label ): void {
	$cells = array();
	foreach ( aw_edit_screen_map() as $k => $e ) {
		$uses = array_values( array_filter( $e['uses'], fn( $u ) => 'schema' === ( $u['kind'] ?? '' ) ) );
		if ( ! $uses ) {
			continue;
		}
		$types = array();
		foreach ( $all as $r ) {
			if ( 'admin' !== $r['screen'] && isset( $r['fields'][ $k ] ) ) {
				$types[ $r['type'] ] = true;
			}
		}
		$types = $types ? array_keys( $types ) : $e['types'];
		$files = array_values( array_unique( array_filter( array_map( fn( $u ) => (string) ( $u['file'] ?? '' ), $uses ) ) ) );
		$label_k = trim( (string) ( $e['label'] ?? '' ) );
		$cells[] = array(
			'field'  => ( '' !== $label_k ? esc_html( $label_k ) . '<br>' : '' ) . '<code>' . esc_html( (string) $k ) . '</code>',
			'mods'   => $types ? esc_html( implode( ', ', array_map( fn( $t ) => $label( (string) $t ), $types ) ) ) : '—',
			'schema' => esc_html( implode( '; ', array_map( fn( $u ) => (string) ( $u['where'] ?? '' ), $uses ) ) ),
			'boxes'  => $e['boxes'] ? esc_html( implode( ', ', array_values( array_unique( wp_list_pluck( $e['boxes'], 'title' ) ) ) ) ) : '—',
			'file'   => $files ? '<code>' . implode( '</code><br><code>', array_map( 'esc_html', $files ) ) . '</code>' : '—',
		);
	}
	aw_edit_screens_grid(
		array(
			'field'  => 'Field',
			'mods'   => 'Modules',
			'schema' => 'Schema',
			'boxes'  => 'Edited in',
			'file'   => 'File',
		),
		$cells
	);
}

/**
 * Third-party boxes tab: WordPress core boxes as one row, then the boxes, panels and widgets of plugins that are not ours.
 *
 * @param array    $display Display rows.
 * @param callable $label   Module label.
 */
function aw_edit_screens_third_party_tab( array $display, callable $label ): void {
	$own   = aw_edit_screen_own_plugins();
	$fold  = aw_edit_screen_collapsed_boxes();
	$known = aw_edit_screen_boxes();
	$cells = array();
	foreach ( $display as $r ) {
		if ( 'general' !== $r['screen'] ) {
			continue;
		}
		$notes = array();
		foreach ( $r['ids'] as $id ) {
			if ( in_array( $id, $fold, true ) ) {
				$notes[] = (string) ( $known[ $id ]['title'] ?? $id ) . ': Collapsed by default.';
			}
		}
		$cells[] = array(
			'box'     => '<strong>WordPress (all types)</strong><br>' . esc_html( $r['title'] ) . aw_edit_screen_fields_details( $r, false ),
			'plugin'  => esc_html( $r['plugin'] ),
			'purpose' => esc_html( $r['purpose'] ),
			'mods'    => esc_html( $r['shows_on'] ),
			'notes'   => aw_edit_screen_notes_list( array_merge( $notes, $r['notes'] ) ),
		);
	}
	foreach ( aw_edit_screen_rows_by_box( $display ) as $r ) {
		if ( in_array( $r['plugin'], $own, true ) ) {
			continue;
		}
		$notes = in_array( $r['id'], $fold, true ) ? array_merge( array( 'Collapsed by default.' ), $r['notes'] ) : $r['notes'];
		$mods  = 'admin' === $r['screen'] ? array( $r['label'] ) : array_map( fn( $t ) => $label( (string) $t ), $r['types'] );
		$cells[] = array(
			'box'     => aw_edit_screen_box_cell( $r, false ),
			'plugin'  => esc_html( $r['plugin'] ),
			'purpose' => esc_html( $r['purpose'] ),
			'mods'    => esc_html( implode( ', ', $mods ) ),
			'notes'   => aw_edit_screen_notes_list( $notes ),
		);
	}
	aw_edit_screens_grid(
		array(
			'box'     => 'Box',
			'plugin'  => 'Plugin',
			'purpose' => 'Purpose',
			'mods'    => 'Modules',
			'notes'   => 'Notes',
		),
		$cells
	);
}

/**
 * "Not mapped yet": each box or field the map does not know (type, box, field).
 *
 * @param array $all     Rows.
 * @param array $screens Post type or taxonomy => post|term.
 */
function aw_edit_screens_unmapped_section( array $all, array $screens ): void {
	$cells = array();
	foreach ( $all as $r ) {
		$type = esc_html( aw_edit_screen_module_label( $r['type'], $screens[ $r['type'] ] ?? $r['screen'] ) );
		$box  = '<strong>' . esc_html( $r['title'] ) . '</strong><br><code>' . esc_html( $r['id'] ) . '</code>';
		if ( ! $r['mapped'] ) {
			$cells[] = array( 'type' => $type, 'box' => $box, 'field' => '—' );
		}
		foreach ( $r['notes'] as $note ) {
			if ( ': Not mapped yet.' === substr( $note, -17 ) ) {
				$cells[] = array( 'type' => $type, 'box' => $box, 'field' => '<code>' . esc_html( substr( $note, 0, -17 ) ) . '</code>' );
			}
		}
	}
	echo '<h2 id="aw-es-unmapped">Not mapped yet</h2>';
	aw_edit_screens_grid( array( 'type' => 'Type', 'box' => 'Box', 'field' => 'Field' ), $cells );
}

/**
 * The "Edited in two places" section: each key editable from more than one box on one type's screen.
 *
 * @param array $rows Rows.
 */
function aw_edit_screens_twice_section( array $rows ): void {
	$twice = aw_edit_screen_twice( $rows );
	echo '<h2 id="aw-es-twice">Edited in two places</h2>';
	if ( ! $twice ) {
		echo '<p>None.</p>';
		return;
	}
	$cells = array();
	foreach ( $twice as $t ) {
		$cells[] = array(
			'type'  => esc_html( $t['type'] ),
			'key'   => '<code>' . esc_html( $t['key'] ) . '</code>',
			'boxes' => esc_html( implode( ', ', $t['boxes'] ) ),
		);
	}
	aw_edit_screens_grid( array( 'type' => 'Type', 'key' => 'Key', 'boxes' => 'Boxes' ), $cells );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Lists every edit-screen box, its fields and where they are used (read-only).
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : One post type or taxonomy.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 */
	WP_CLI::add_command(
		'aw-edit-screens',
		function ( $args, $assoc ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
			if ( $admins && ! get_current_user_id() ) {
				wp_set_current_user( (int) $admins[0] ); // boxes that check a capability; in memory only
			}
			$type = sanitize_key( (string) ( $assoc['type'] ?? '' ) );
			$rows = aw_edit_screen_rows_for( $type );
			if ( 'json' === ( $assoc['format'] ?? 'table' ) ) {
				WP_CLI::line( (string) wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
				return;
			}
			$out = array();
			foreach ( aw_edit_screen_display_rows( $rows ) as $r ) {
				$kinds = array();
				foreach ( $r['uses'] as $list ) {
					foreach ( $list as $u ) {
						$kinds[ (string) $u['kind'] ] = true;
					}
				}
				$f = array();
				foreach ( $r['fields'] as $k => $t ) {
					$f[] = $k . '=' . $t;
				}
				$out[] = array(
					'type'     => $r['label'],
					'box'      => $r['title'] . ' [' . $r['id'] . ']',
					'plugin'   => $r['plugin'],
					'purpose'  => $r['purpose'],
					'shows_on' => $r['shows_on'],
					'fields'   => implode( ', ', $f ),
					'used'     => implode( ',', array_keys( $kinds ) ),
					'notes'    => implode( ' | ', $r['notes'] ),
				);
			}
			WP_CLI\Utils\format_items( 'table', $out, array( 'type', 'box', 'plugin', 'purpose', 'shows_on', 'fields', 'used', 'notes' ) );
			if ( ! is_admin() ) {
				WP_CLI::log( 'Some plugins add their boxes only in WP admin (for example LiteSpeed); they show on Animal Writes → Edit screens.' );
			}
			WP_CLI::log( aw_edit_screen_map_only_note() );
			$twice = aw_edit_screen_twice( $rows );
			WP_CLI::log( 'Edited in two places:' );
			if ( $twice ) {
				WP_CLI\Utils\format_items( 'table', array_map( fn( $t ) => array( 'type' => $t['type'], 'key' => $t['key'], 'boxes' => implode( ', ', $t['boxes'] ) ), $twice ), array( 'type', 'key', 'boxes' ) );
			} else {
				WP_CLI::log( 'None.' );
			}
			WP_CLI::log( sprintf( '%d rows, %d not mapped yet, %d edited in two places.', count( $rows ), aw_edit_screen_unmapped( $rows ), count( $twice ) ) );
		}
	);
}
