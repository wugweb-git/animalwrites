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

	// Admin pages that are not edit screens (Dashboard widgets, report pages): listed from the map, one row per page in "types".
	foreach ( $boxes as $id => $b ) {
		if ( 'admin' === ( $b['screen'] ?? '' ) ) {
			foreach ( (array) $b['types'] as $page ) {
				$add( 'admin', (string) $page, $id, array( 'shows_on' => (string) ( $b['page'] ?? $page ) ), array(), array( 'Listed from the map, not read live.' ) );
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
	return 'Sidebar panels that plugins add with JavaScript in the block editor, and Dashboard widgets and report-page boxes, are listed from the map, not read live.';
}

/** Animal Writes → Edit screens. */
function aw_edit_screens_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$all   = aw_edit_screen_rows();
	$types = array();
	foreach ( $all as $r ) {
		$types[ $r['type'] ] = $r['screen'];
	}
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
	if ( ! isset( $types[ $type ] ) ) {
		$type = '';
	}
	$rows = aw_edit_screen_rows_for( $type );
	$base = admin_url( 'admin.php?page=aw-edit-screens' );

	echo '<div class="wrap"><h1>Edit screens</h1>';
	echo '<p>Every box on each edit screen, the keys it stores and where each key is used. Boxes are read from the plugins as they run now; what each key does comes from the map in <code>animal-writes-core/data/edit-screen-map.json</code> (see <code>docs/edit-screens.md</code>). Read-only.</p>';
	echo '<p class="description">' . esc_html( aw_edit_screen_map_only_note() ) . '</p>';
	$unmapped = aw_edit_screen_unmapped( $rows );
	if ( $unmapped ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( '%d boxes or fields here are not mapped yet.', $unmapped ) ) . '</p></div>';
	} else {
		echo '<div class="notice notice-success inline"><p>Every box and field here is in the map.</p></div>';
	}

	$counts = array_count_values( wp_list_pluck( $all, 'type' ) );
	$links  = array( '<a href="' . esc_url( $base ) . '"' . ( '' === $type ? ' class="current" aria-current="page"' : '' ) . '>All <span class="count">(' . count( $all ) . ')</span></a>' );
	foreach ( $types as $t => $screen ) {
		$obj     = 'term' === $screen ? get_taxonomy( $t ) : ( 'admin' === $screen ? null : get_post_type_object( $t ) );
		$label   = $obj ? $obj->labels->name : aw_edit_screen_type_label( $t, $screen );
		$label  .= 'term' === $screen ? ' (terms)' : '';
		$links[] = '<a href="' . esc_url( add_query_arg( 'type', $t, $base ) ) . '"' . ( $type === $t ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . (int) ( $counts[ $t ] ?? 0 ) . ')</span></a>';
	}
	echo '<ul class="subsubsub"><li>' . implode( ' |</li><li>', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link is escaped above.

	$cells = array();
	foreach ( aw_edit_screen_display_rows( $rows ) as $r ) {
		$fields = array();
		$used   = array();
		foreach ( $r['fields'] as $k => $t ) {
			$fields[] = '<code>' . esc_html( $k ) . '</code> → ' . esc_html( $t );
			$parts    = array();
			foreach ( (array) ( $r['uses'][ $k ] ?? array() ) as $u ) {
				$parts[] = '<strong>' . esc_html( (string) ( $u['kind'] ?? '' ) ) . '</strong> ' . esc_html( (string) ( $u['where'] ?? '' ) ) . ( empty( $u['file'] ) ? '' : ' <code>' . esc_html( (string) $u['file'] ) . '</code>' );
			}
			$used[] = '<code>' . esc_html( $k ) . '</code>: ' . ( $parts ? implode( '; ', $parts ) : 'not used' );
		}
		$cells[] = array(
			'type'    => 'general' === $r['screen'] ? '<strong>' . esc_html( $r['label'] ) . '</strong>' : esc_html( $r['label'] ),
			'box'     => '<strong>' . esc_html( $r['title'] ) . '</strong><br><code>' . esc_html( $r['id'] ) . '</code>',
			'plugin'  => esc_html( $r['plugin'] ) . ( '' === $r['file'] ? '' : '<br><code>' . esc_html( $r['file'] ) . '</code>' ),
			'purpose' => esc_html( $r['purpose'] ),
			'shows'   => esc_html( $r['shows_on'] ),
			'fields'  => $fields ? implode( '<br>', $fields ) : '—',
			'used'    => $used ? implode( '<br>', $used ) : '—',
			'notes'   => $r['notes'] ? implode( '<br>', array_map( 'esc_html', $r['notes'] ) ) : '—',
		);
	}
	$cols = array(
		'type'    => 'Type',
		'box'     => 'Box',
		'plugin'  => 'Plugin',
		'purpose' => 'Purpose',
		'shows'   => 'Shows on',
		'fields'  => 'Fields',
		'used'    => 'Used where',
		'notes'   => 'Notes',
	);
	aw_core_simple_table( 'aw-edit-screens', $cols, $cells, array( 'primary' => 'box', 'empty' => 'No boxes.' ) );
	aw_edit_screens_twice_section( $rows );
	echo '</div>';
}

/**
 * The "Edited in two places" section: each key editable from more than one box on one type's screen.
 *
 * @param array $rows Rows.
 */
function aw_edit_screens_twice_section( array $rows ): void {
	$twice = aw_edit_screen_twice( $rows );
	echo '<h2>Edited in two places</h2>';
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
	aw_core_simple_table( 'aw-edit-screens-twice', array( 'type' => 'Type', 'key' => 'Key', 'boxes' => 'Boxes' ), $cells, array( 'primary' => 'key' ) );
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
