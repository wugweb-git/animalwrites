<?php
/**
 * Sourcing → Workers (owner, 24 Oct 2026): the rows and saves of the Workers screen (the Kits shell's; its routes in
 * sourceweb-rest-pages.php): the list, a worker's form (name, what it does, group, late limit; for a Planned worker also
 * the ID and the schedule wanted; for every worker the saved keys it may use) and its runs. A registry worker's ID,
 * workflow and schedule are read-only: they are changed in the workflow. Every change goes to Sourcing → Change log.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Run now is offered: a registry workflow that needs no input, and the dispatch token is set. */
function aw_workers_can_run( array $w ) {
	return ! $w['planned'] && empty( $w['registry']['needs_input'] ) && defined( 'AW_GITHUB_DISPATCH_TOKEN' ) && '' !== (string) AW_GITHUB_DISPATCH_TOKEN;
}

/** "Late after 12 h", "Late after 9 days", "Never late (no alert)" or '' (by hand). */
function aw_workers_late_words( array $w ) {
	$h = $w['late_after_h'];
	if ( empty( $h ) ) {
		$r = (array) ( $w['registry'] ?? array() );
		return ! empty( $r['crons'] ) || ! empty( $r['after'] ) ? 'Never late (no alert)' : '';
	}
	return 'Late after ' . ( $h >= 48 ? round( $h / 24 ) . ' days' : $h . ' h' );
}

/**
 * Every worker with its last run, status (active, late, failing, planned), problem note and key counts, in the
 * registry's order: the rows of the Workers list on both screens.
 *
 * @return array[]
 */
function aw_workers_status_rows() {
	$kinds = array();
	$notes = array();
	foreach ( aw_workers_problems() as $k => $msg ) {
		list( $file, $kind )              = explode( ':', $k, 2 );
		$kinds[ aw_worker_id( $file ) ][] = $kind;
		$notes[ aw_worker_id( $file ) ][] = $msg;
	}
	$out = array();
	foreach ( aw_workers_all() as $id => $w ) {
		$last = aw_workers_last( $w['file'] );
		$k    = $kinds[ $id ] ?? array();
		if ( $w['planned'] && ! $last ) {
			$status = 'planned';
		} elseif ( array_intersect( $k, array( 'late', 'stuck' ) ) ) {
			$status = 'late';
		} elseif ( $k ) {
			$status = 'failing';
		} else {
			$status = 'active';
		}
		$out[] = $w + array(
			'last'   => $last,
			'status' => $status,
			'note'   => implode( ' ', $notes[ $id ] ?? array() ),
			'keys'   => aw_worker_key_count( $id ),
		);
	}
	return $out;
}

/**
 * Attach the ticked keys to a worker and detach the others; returns [changes for the log, keys left as they were].
 *
 * @param string   $id     Worker ID.
 * @param string[] $ticked Key ids.
 * @return array
 */
function aw_workers_apply_keys( $id, array $ticked ) {
	$rows = aw_ai_rows_fresh();
	$did  = array();
	$skip = 0;
	$ids  = array_keys( aw_workers_all() );
	foreach ( $rows as $i => $r ) {
		$ws = aw_ai_row_workers( $r );
		$on = in_array( aw_ai_key_id( $r['key'] ), $ticked, true );
		if ( ! $ws ) {
			if ( $on ) {
				continue;
			}
			$new = array_values( array_diff( $ids, array( $id ) ) );
		} elseif ( $on === in_array( $id, $ws, true ) ) {
			continue;
		} else {
			$new = $on ? array_merge( $ws, array( $id ) ) : array_values( array_diff( $ws, array( $id ) ) );
		}
		if ( ! $new ) {
			++$skip;
			continue;
		}
		$rows[ $i ]['workers'] = $new;
		$did[]                 = aw_ai_key_short( $r ) . ( $on ? ' attached' : ' detached' );
	}
	if ( $did ) {
		update_option( 'aw_ai_models', $rows, false );
	}
	return array( $did, $skip );
}

/**
 * Save a worker (Add New Worker, or the overrides of an existing one), from the Workers screen (POST workers/save).
 * Values come in already sanitised; every change goes to the change log.
 *
 * @param array $in { edit: 'new' or an ID, id (new only), name, what, schedule, late ('' = never), group, group_new,
 *                  keys: ticked key ids, or null when the keys were not shown }.
 * @return array|WP_Error { done: added|saved|same, id, name, skip } or an error coded id, clash, name or missing.
 */
function aw_worker_save( array $in ) {
	$edit  = sanitize_key( (string) ( $in['edit'] ?? '' ) );
	$new   = 'new' === $edit;
	$all   = aw_workers_all();
	$id    = $new ? sanitize_key( (string) ( $in['id'] ?? '' ) ) : $edit;
	$name  = mb_substr( sanitize_text_field( (string) ( $in['name'] ?? '' ) ), 0, 120 );
	$what  = mb_substr( sanitize_textarea_field( (string) ( $in['what'] ?? '' ) ), 0, 500 );
	$sched = mb_substr( sanitize_text_field( (string) ( $in['schedule'] ?? '' ) ), 0, 200 );
	$late  = trim( sanitize_text_field( (string) ( $in['late'] ?? '' ) ) );
	$late  = '' === $late ? null : max( 1, absint( $late ) );
	$group = sanitize_key( (string) ( $in['group'] ?? '' ) );
	$label = mb_substr( sanitize_text_field( (string) ( $in['group_new'] ?? '' ) ), 0, 40 );
	if ( ! $new && ! isset( $all[ $id ] ) ) {
		return new WP_Error( 'missing', 'No such worker.', array( 'status' => 404 ) );
	}
	$w       = $all[ $id ] ?? null;
	$planned = $new || $w['planned'];
	$errs    = array(
		'id'    => 'Choose an ID: lowercase letters, digits and hyphens (the workflow file name without .yml).',
		'clash' => 'That ID is taken by another worker. IDs never change, so choose another.',
		'name'  => 'Give the worker a name.',
	);
	$err     = '';
	if ( $new && ( '' === $id || 'new' === $id ) ) {
		$err = 'id';
	} elseif ( $new && ( isset( $all[ $id ] ) || isset( aw_workers_meta()[ $id ] ) ) ) {
		$err = 'clash';
	} elseif ( $planned && '' === $name ) {
		$err = 'name';
	}
	if ( $err ) {
		return new WP_Error(
			$err,
			$errs[ $err ],
			array(
				'status' => 400,
				'typed'  => array(
					'id'           => $id,
					'name'         => $name,
					'what'         => $what,
					'group'        => $group,
					'late_after_h' => $late,
					'schedule'     => $sched,
				),
			)
		);
	}
	$log = array();
	// A new group: added to the list with a generic icon.
	if ( '' !== $label ) {
		$groups = aw_worker_groups();
		$slug   = sanitize_title( $label );
		if ( '' !== $slug && ! isset( $groups[ $slug ] ) ) {
			$extra          = (array) get_option( 'aw_workers_groups', array() );
			$extra[ $slug ] = array( $label, 'admin-generic' );
			update_option( 'aw_workers_groups', $extra, false );
			$log[] = 'new group "' . $label . '"';
		}
		$group = '' !== $slug ? $slug : $group;
	}
	$group = isset( aw_worker_groups()[ $group ] ) ? $group : ( $w['group'] ?? 'one-off' );
	$meta  = aw_workers_meta();
	$was   = $meta;
	$m     = (array) ( $meta[ $id ] ?? array() );
	if ( $planned ) {
		$m = array_merge(
			$m,
			array(
				'name'         => $name,
				'what'         => $what,
				'group'        => $group,
				'late_after_h' => $late,
				'schedule'     => $sched,
				'planned'      => true,
			)
		);
		if ( $new ) {
			$m['added']    = time();
			$m['added_by'] = wp_get_current_user()->user_login;
		}
	} else {
		$reg = $w['registry'];
		// Only what differs from the registry is kept, so a later registry change shows through.
		foreach ( array(
			'name' => $name,
			'what' => $what,
		) as $k => $val ) {
			if ( '' === $val || $val === $reg[ $k ] ) {
				unset( $m[ $k ] );
			} else {
				$m[ $k ] = $val;
			}
		}
		if ( $late === $reg['late_after_h'] ) {
			unset( $m['late_after_h'] );
		} else {
			$m['late_after_h'] = $late;
		}
		// The group it has with no override (aw_workers_all()): kept only when it differs.
		$def = AW_WORKER_DEFAULT_GROUP[ $id ] ?? ( ! empty( $reg['crons'] ) || ! empty( $reg['after'] ) ? 'maintenance' : 'one-off' );
		if ( $def === $group ) {
			unset( $m['group'] );
		} else {
			$m['group'] = $group;
		}
	}
	if ( $m ) {
		$meta[ $id ] = $m;
	} else {
		unset( $meta[ $id ] );
	}
	if ( $meta !== $was ) { // a Save with no change writes nothing
		update_option( 'aw_workers_meta', $meta, false );
	}
	$after = aw_workers_all()[ $id ];
	if ( $new ) {
		$log[] = 'added planned worker ' . $id . ' "' . $name . '" (' . ( aw_worker_groups()[ $group ][0] ?? $group ) . ( '' !== $sched ? ', wanted: ' . $sched : '' ) . ')';
	} else {
		$groups = aw_worker_groups();
		foreach ( array( 'name', 'what', 'group', 'late_after_h', 'schedule' ) as $k ) {
			$a = $w[ $k ];
			$b = $after[ $k ];
			if ( $a !== $b ) {
				$show  = fn( $x ) => 'group' === $k ? ( $groups[ $x ][0] ?? $x ) : ( 'late_after_h' === $k ? ( null === $x ? 'never' : $x . ' h' ) : '"' . $x . '"' );
				$log[] = str_replace( '_', ' ', 'late_after_h' === $k ? 'late after' : $k ) . ' ' . $show( $a ) . ' → ' . $show( $b );
			}
		}
	}
	$skip = 0;
	$did  = array();
	if ( null !== ( $in['keys'] ?? null ) ) {
		list( $did, $skip ) = aw_workers_apply_keys( $id, array_map( 'sanitize_key', (array) $in['keys'] ) );
		if ( $did ) {
			aw_src_log( 'ai', 'worker ' . $id . ': key ' . implode( ', ', $did ) );
		}
	}
	if ( $log ) {
		aw_src_log( 'workers', 'Worker ' . $id . ': ' . implode( '; ', $log ) );
	}
	return array(
		'done' => $new ? 'added' : ( $log || $did ? 'saved' : 'same' ),
		'id'   => $id,
		'name' => $after['name'],
		'skip' => $skip,
	);
}

/**
 * Delete a Planned worker. Its ID leaves the keys attached to it (a key left with no worker is for every worker again).
 *
 * @param string $id Worker ID.
 * @return array|WP_Error { name, freed: keys now for every worker (…abcd (Provider)) }.
 */
function aw_worker_delete( $id ) {
	$w = aw_worker( $id );
	if ( ! $w || ! $w['planned'] ) {
		return new WP_Error( 'not_planned', 'Only a planned worker can be deleted.', array( 'status' => 400 ) );
	}
	$id   = $w['id'];
	$meta = aw_workers_meta();
	unset( $meta[ $id ] );
	update_option( 'aw_workers_meta', $meta, false );
	$rows  = aw_ai_rows_fresh();
	$freed = array();
	$moved = false;
	foreach ( $rows as $i => $r ) {
		$ws = aw_ai_row_workers( $r );
		if ( in_array( $id, $ws, true ) ) {
			$moved                 = true;
			$rows[ $i ]['workers'] = array_values( array_diff( $ws, array( $id ) ) );
			if ( ! $rows[ $i ]['workers'] ) {
				$freed[] = aw_ai_key_short( $r );
			}
		}
	}
	if ( $moved ) {
		update_option( 'aw_ai_models', $rows, false );
	}
	aw_src_log( 'workers', 'deleted planned worker ' . $id . ' "' . $w['name'] . '"' . ( $freed ? '; key ' . implode( ', ', $freed ) . ' now for all workers' : '' ) );
	return array(
		'name'  => $w['name'],
		'freed' => $freed,
	);
}
