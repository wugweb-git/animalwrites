<?php
/**
 * Workers as the owner organises them (owner, 24 Oct 2026). One worker per workflow of the workers repository, with a
 * stable ID: the workflow file without .yml (sourcing, summary-rewrite, focus-keywords …); the ID never changes when a
 * name does. A worker could later map to a workflow and one of its jobs (key "job"), but nothing splits one yet.
 *
 * AW_WORKERS (workers-registry.php, an exact copy of docs/workers.json in the workers repository) stays the source of
 * truth for what exists, its schedule and its workflow file. Option aw_workers_meta (id => fields, not autoloaded) adds:
 * - overrides for a registry worker: name, what, group, late_after_h (present = overridden; null = never late);
 * - Planned workers (planned => true): ID, name, what, group, schedule wanted (words), late_after_h. They show
 *   "Not built yet" until a workflow with that ID reports a run, and never raise an alert.
 * Option aw_workers_groups adds groups to AW_WORKER_GROUPS (slug => [label, dashicon]).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The groups, with a core dashicon each. More can be added on a worker's edit screen. */
const AW_WORKER_GROUPS = array(
	'sourcing'    => array( 'Sourcing', 'rss' ),
	'seo'         => array( 'SEO', 'chart-line' ),
	'directory'   => array( 'Directory', 'location' ),
	'maintenance' => array( 'Maintenance', 'admin-tools' ),
	'one-off'     => array( 'One-off', 'controls-play' ),
);

/** Each registry worker's group until the owner moves it. */
const AW_WORKER_DEFAULT_GROUP = array(
	'sourcing'             => 'sourcing',
	'summary-rewrite'      => 'sourcing',
	'sources-sync'         => 'sourcing',
	'research'             => 'sourcing',
	'focus-keywords'       => 'seo',
	'seo-scores'           => 'seo',
	'search-insights'      => 'seo',
	'directory-enrichment' => 'directory',
	'darpan-import'        => 'directory',
	'tests'                => 'maintenance',
	'backup-offsite'       => 'maintenance',
	'wp-cron'              => 'maintenance',
	'keepalive'            => 'maintenance',
	'backfill'             => 'one-off',
	'submit'               => 'one-off',
	'press-gnews'          => 'one-off',
	'relevance-existing'   => 'one-off',
	'news-title-case'      => 'one-off',
	'same-story-fold'      => 'one-off',
);

/** "sourcing.yml" => "sourcing". */
function aw_worker_id( $file ) {
	return sanitize_key( preg_replace( '/\.ya?ml$/i', '', (string) $file ) );
}

/** Groups: slug => [label, dashicon]. */
function aw_worker_groups() {
	$extra = array();
	foreach ( (array) get_option( 'aw_workers_groups', array() ) as $slug => $g ) {
		if ( is_array( $g ) && ! empty( $g[0] ) ) {
			$extra[ sanitize_key( $slug ) ] = array( (string) $g[0], (string) ( $g[1] ?? 'admin-generic' ) );
		}
	}
	return AW_WORKER_GROUPS + $extra;
}

/** The group's core dashicon. */
function aw_worker_group_icon( $group ) {
	$g = aw_worker_groups()[ $group ] ?? array( '', 'admin-generic' );
	return '<span class="dashicons dashicons-' . esc_attr( $g[1] ) . '" aria-hidden="true"></span>';
}

/** Saved overrides and Planned workers. */
function aw_workers_meta() {
	return (array) get_option( 'aw_workers_meta', array() );
}

/**
 * Every worker: the registry's, in its order, then the Planned ones by name.
 *
 * @return array<string,array> id => id, file, name, what, group, late_after_h, planned, schedule, registry (AW_WORKERS row or null), overrides (field names)
 */
function aw_workers_all() {
	static $memo = null;
	static $memo_key = '';
	$meta = aw_workers_meta();
	$key  = md5( wp_json_encode( $meta ) . wp_json_encode( get_option( 'aw_workers_groups', array() ) ) );
	if ( null !== $memo && $memo_key === $key ) {
		return $memo;
	}
	$groups = aw_worker_groups();
	$out    = array();
	foreach ( AW_WORKERS as $file => $w ) {
		$id         = aw_worker_id( $file );
		$m          = (array) ( $meta[ $id ] ?? array() );
		$over       = array_values( array_intersect( array( 'name', 'what', 'group', 'late_after_h' ), array_keys( $m ) ) );
		$group      = (string) ( $m['group'] ?? ( AW_WORKER_DEFAULT_GROUP[ $id ] ?? ( $w['crons'] || ! empty( $w['after'] ) ? 'maintenance' : 'one-off' ) ) );
		$out[ $id ] = array(
			'id'           => $id,
			'file'         => $file,
			'name'         => '' !== (string) ( $m['name'] ?? '' ) ? (string) $m['name'] : $w['name'],
			'what'         => '' !== (string) ( $m['what'] ?? '' ) ? (string) $m['what'] : $w['what'],
			'group'        => isset( $groups[ $group ] ) ? $group : 'maintenance',
			'late_after_h' => array_key_exists( 'late_after_h', $m ) ? $m['late_after_h'] : $w['late_after_h'],
			'planned'      => false,
			'schedule'     => '',
			'registry'     => $w,
			'overrides'    => $over,
		);
	}
	$planned = array();
	foreach ( $meta as $id => $m ) {
		$id = sanitize_key( $id );
		if ( isset( $out[ $id ] ) || empty( $m['planned'] ) ) {
			continue;
		}
		$group          = (string) ( $m['group'] ?? 'one-off' );
		$planned[ $id ] = array(
			'id'           => $id,
			'file'         => $id . '.yml',
			'name'         => (string) ( $m['name'] ?? $id ),
			'what'         => (string) ( $m['what'] ?? '' ),
			'group'        => isset( $groups[ $group ] ) ? $group : 'one-off',
			'late_after_h' => $m['late_after_h'] ?? null,
			'planned'      => true,
			'schedule'     => (string) ( $m['schedule'] ?? '' ),
			'registry'     => null,
			'overrides'    => array(),
		);
	}
	uasort( $planned, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
	$memo     = $out + $planned;
	$memo_key = $key;
	return $memo;
}

/** One worker, or null. */
function aw_worker( $id ) {
	return aw_workers_all()[ sanitize_key( $id ) ] ?? null;
}

/** A Planned worker (not in the registry) with this ID exists. */
function aw_worker_is_planned( $id ) {
	$w = aw_worker( $id );
	return $w && $w['planned'];
}

/**
 * A registry row with the owner's overrides (name, what, late limit), for the alerts.
 *
 * @param string $file Workflow file.
 * @param array  $w    Its AW_WORKERS row.
 * @return array
 */
function aw_worker_def( $file, array $w ) {
	$all = aw_workers_all()[ aw_worker_id( $file ) ] ?? null;
	if ( ! $all ) {
		return $w;
	}
	return array_merge(
		$w,
		array(
			'name'         => $all['name'],
			'what'         => $all['what'],
			'late_after_h' => $all['late_after_h'],
		)
	);
}

/** Icon and name of a worker (or the bare ID of one that no longer exists), as HTML. */
function aw_worker_label_html( $id, $with_id = false ) {
	$w = aw_worker( $id );
	if ( ! $w ) {
		return '<code>' . esc_html( $id ) . '</code> (no longer exists)';
	}
	return aw_worker_group_icon( $w['group'] ) . ' ' . esc_html( $w['name'] ) . ( $with_id ? ' <code>' . esc_html( $w['id'] ) . '</code>' : '' );
}

/**
 * Saved keys attached to this worker specifically, and those for every worker.
 *
 * @param string $id Worker ID.
 * @return int[] [ specific, for all ]
 */
function aw_worker_key_count( $id ) {
	$own = 0;
	$all = 0;
	foreach ( aw_ai_rows() as $r ) {
		$ws = aw_ai_row_workers( $r );
		if ( ! $ws ) {
			++$all;
		} elseif ( in_array( $id, $ws, true ) ) {
			++$own;
		}
	}
	return array( $own, $all );
}

/**
 * The workers as a checkbox list (core fieldset), grouped as on the Workers tab.
 *
 * @param string   $name    Field name (without []).
 * @param string[] $checked Ticked worker IDs.
 * @param string   $legend  Screen-reader legend.
 */
function aw_workers_checkboxes( $name, array $checked, $legend ) {
	echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $legend ) . '</span></legend>';
	$groups = aw_worker_groups();
	$by     = array();
	foreach ( aw_workers_all() as $id => $w ) {
		$by[ $w['group'] ][ $id ] = $w;
	}
	foreach ( $groups as $slug => $g ) {
		if ( empty( $by[ $slug ] ) ) {
			continue;
		}
		echo '<p><strong>' . aw_worker_group_icon( $slug ) . ' ' . esc_html( $g[0] ) . '</strong></p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in aw_worker_group_icon
		foreach ( $by[ $slug ] as $id => $w ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $id ) . '"' . checked( in_array( $id, $checked, true ), true, false ) . '> '
				. esc_html( $w['name'] ) . ' <code>' . esc_html( $id ) . '</code>' . ( $w['planned'] ? ' (planned)' : '' ) . '</label><br>';
		}
	}
	echo '</fieldset>';
}

/**
 * Worker IDs from a posted list, known workers only.
 *
 * @param mixed $raw Posted value.
 * @return string[]
 */
function aw_workers_posted_ids( $raw ) {
	$all = aw_workers_all();
	return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $raw ), fn( $id ) => isset( $all[ $id ] ) ) ) );
}
