<?php
/**
 * Sourcing → Workers screens (owner, 24 Oct 2026), drawn outside the shared Sourcing form:
 * - the list (core list table, class-aw-workers-table.php) with "Add New Worker", and the Run now log;
 * - &edit=new / &edit=<id>: a worker's form-table (name, what it does, group, late limit; for a Planned worker also the
 *   ID and the schedule wanted; for every worker the saved keys it may use). A registry worker's ID, workflow and
 *   schedule are read-only: they are changed in the workflow;
 * - &runs=<id>: the worker's reported runs, read-only.
 * Handlers (admin-post.php): aw_worker_save, aw_worker_delete (Planned only), aw_worker_run (Run now, as before). Every
 * change goes to Sourcing → Change log.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Workers tab's address. */
function aw_workers_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=aw-sourcing&tab=workers' ) );
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

/** Sourcing → Workers. */
function aw_workers_tab() {
	$edit = sanitize_key( $_GET['edit'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- which screen is shown
	$runs = sanitize_key( $_GET['runs'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- which screen is shown
	if ( '' !== $edit ) {
		aw_workers_edit_screen( $edit );
		return;
	}
	if ( '' !== $runs ) {
		aw_workers_runs_screen( $runs );
		return;
	}
	if ( isset( $_GET['aw_ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ok = ! empty( $_GET['aw_ok'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$f  = sanitize_file_name( wp_unslash( $_GET['aw_ran'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$l  = (array) ( get_option( 'aw_worker_dispatch_log', array() )[0] ?? array() );
		$w  = aw_worker( aw_worker_id( $f ) );
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( ( $w ? $w['name'] : $f ) . ': ' . ( $ok ? 'started on GitHub. Its start report appears here within a minute or two.' : 'not started — ' . ( $l['msg'] ?? '' ) ) ) . '</p></div>';
	}
	$done = sanitize_key( $_GET['worker_done'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $done ) {
		$name = sanitize_text_field( wp_unslash( $_GET['worker_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$msg  = array(
			'added'   => 'Planned worker "%s" added. It shows "Not built yet" until a workflow with its ID reports a run.',
			'saved'   => 'Worker "%s" saved.',
			'same'    => 'Nothing changed for "%s".',
			'deleted' => 'Planned worker "%s" deleted.',
		);
		if ( isset( $msg[ $done ] ) ) {
			echo '<div class="notice notice-' . ( 'same' === $done ? 'info' : 'success' ) . ' is-dismissible"><p>' . esc_html( sprintf( $msg[ $done ], $name ) ) . '</p></div>';
		}
		$skip = absint( $_GET['worker_skip'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $skip ) {
			/* translators: %d: number of keys. */
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sprintf( _n( '%d key was left ticked: it is for this worker only, and a key needs at least one worker. Remove it on AI models instead.', '%d keys were left ticked: each is for this worker only, and a key needs at least one worker. Remove them on AI models instead.', $skip ), $skip ) ) . '</p></div>';
		}
	}
	echo '<p>One worker per workflow of <a href="https://github.com/wugweb-git/animal-writes-workers/actions" target="_blank" rel="noopener">wugweb-git/animal-writes-workers</a>, which reports its start and end here. Schedules are changed in the workflows, not here; names, groups and late limits can be changed here. A problem (a failed, cancelled, skipped or stuck run, or no report within the late limit) is emailed to the owner once. Planned workers never raise an alert.'
		. ' <a href="' . esc_url( aw_workers_url( array( 'edit' => 'new' ) ) ) . '" class="page-title-action">Add New Worker</a></p>';
	if ( ! defined( 'AW_GITHUB_DISPATCH_TOKEN' ) || '' === (string) AW_GITHUB_DISPATCH_TOKEN ) {
		echo '<div class="notice notice-warning inline"><p>Run now is off: set <code>AW_GITHUB_DISPATCH_TOKEN</code> in wp-config.php (a fine-grained GitHub token for wugweb-git/animal-writes-workers only, Actions read and write).</p></div>';
	}
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-workers-table.php';
	$table = new AW_Workers_Table();
	$table->prepare_items();
	$table->views();
	$view = sanitize_key( $_GET['wview'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<form method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="workers">' . ( '' !== $view ? '<input type="hidden" name="wview" value="' . esc_attr( $view ) . '">' : '' );
	$table->search_box( 'Search workers', 'aw-workers' );
	$table->display();
	echo '</form>';

	echo '<h2>Run now log</h2>';
	$rows = array();
	foreach ( (array) get_option( 'aw_worker_dispatch_log', array() ) as $e ) {
		$w      = aw_worker( aw_worker_id( $e['file'] ) );
		$rows[] = array(
			'at'     => esc_html( wp_date( 'j M Y, g:i a', (int) $e['at'] ) ),
			'user'   => esc_html( $e['user'] ),
			'worker' => esc_html( $w ? $w['name'] : $e['file'] ),
			'result' => esc_html( $e['ok'] ? 'started' : $e['msg'] ),
		);
	}
	aw_sourcing_simple_table( 'run-now', array( 'at' => 'When', 'user' => 'Who', 'worker' => 'Worker', 'result' => 'Result' ), $rows, array( 'empty' => 'None yet.' ) );
}

/** A worker's runs (&runs=<id>), newest first, read-only. */
function aw_workers_runs_screen( $id ) {
	$w = aw_worker( $id );
	if ( ! $w ) {
		echo '<div class="notice notice-error inline"><p>No such worker. <a href="' . esc_url( aw_workers_url() ) . '">Back to the workers</a></p></div>';
		return;
	}
	echo '<h2>' . esc_html( 'Runs: ' . $w['name'] ) . ' <a href="' . esc_url( aw_workers_url() ) . '" class="page-title-action">Back to the workers</a>'
		. ( $w['planned'] ? '' : ' <a href="' . esc_url( 'https://github.com/wugweb-git/animal-writes-workers/actions/workflows/' . $w['file'] ) . '" class="page-title-action" target="_blank" rel="noopener">Runs on GitHub</a>' ) . '</h2>';
	echo '<p>The last ' . (int) AW_WORKERS_KEEP . ' runs the workflow reported (start and end), newest first.</p>';
	$runs = (array) get_option( aw_workers_option( $w['file'] ), array() );
	krsort( $runs, SORT_NUMERIC );
	$ev   = array(
		'schedule'          => 'scheduled',
		'workflow_dispatch' => 'started by hand or from the site',
		'workflow_run'      => 'after another workflow',
		'push'              => 'push',
	);
	$rows = array();
	foreach ( $runs as $r ) {
		$dur    = ! empty( $r['ended'] ) && ! empty( $r['started'] ) && $r['ended'] >= $r['started'] ? aw_workers_duration( $r['ended'] - $r['started'] ) : '';
		$counts = (array) ( $r['counts'] ?? array() );
		$rows[] = array(
			'run'     => ( ! empty( $r['link'] ) ? '<a href="' . esc_url( $r['link'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $r['run_id'] ) . '</a>' : esc_html( (string) ( $r['run_id'] ?? '' ) ) ) . ( (int) ( $r['attempt'] ?? 1 ) > 1 ? esc_html( ' (attempt ' . (int) $r['attempt'] . ')' ) : '' ),
			'started' => esc_html( ! empty( $r['started'] ) ? wp_date( 'j M Y, g:i a', (int) $r['started'] ) : '—' ),
			'took'    => esc_html( $dur ? $dur : '—' ),
			'result'  => aw_workers_badge( $r, false ),
			'event'   => esc_html( $ev[ $r['event'] ?? '' ] ?? ( $r['event'] ?? '' ) ),
			'counts'  => $counts ? implode( '<br>', array_map( fn( $k, $v ) => esc_html( str_replace( '_', ' ', $k ) . ': ' . $v ), array_keys( $counts ), $counts ) ) : '—',
			'commit'  => ! empty( $r['sha'] ) ? '<a href="' . esc_url( 'https://github.com/wugweb-git/animal-writes-workers/commit/' . $r['sha'] ) . '" target="_blank" rel="noopener"><code>' . esc_html( substr( $r['sha'], 0, 7 ) ) . '</code></a>' : '—',
		);
	}
	aw_sourcing_simple_table(
		'runs',
		array(
			'run'     => 'Run',
			'started' => 'Started',
			'took'    => 'Took',
			'result'  => 'Result',
			'event'   => 'Started by',
			'counts'  => 'Counts',
			'commit'  => 'Commit',
		),
		$rows,
		array( 'empty' => $w['planned'] ? 'Not built yet: no runs.' : 'No runs reported yet.' )
	);
}

/** Add New Worker (&edit=new) or edit one (&edit=<id>), in the core form-table layout. */
function aw_workers_edit_screen( $id ) {
	$new = 'new' === $id;
	$w   = $new ? null : aw_worker( $id );
	if ( ! $new && ! $w ) {
		echo '<div class="notice notice-error inline"><p>No such worker. <a href="' . esc_url( aw_workers_url() ) . '">Back to the workers</a></p></div>';
		return;
	}
	// After a refused save, the values typed (kept for 5 minutes for this user only).
	$typed = (array) get_transient( 'aw_worker_form_' . get_current_user_id() );
	delete_transient( 'aw_worker_form_' . get_current_user_id() );
	$err = sanitize_key( $_GET['worker_err'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$msg = array(
		'id'    => 'Choose an ID: lowercase letters, digits and hyphens (the workflow file name without .yml).',
		'clash' => 'That ID is taken by another worker. IDs never change, so choose another.',
		'name'  => 'Give the worker a name.',
	);
	if ( isset( $msg[ $err ] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg[ $err ] ) . '</p></div>';
	}
	$v       = array_merge(
		$w ? $w : array(
			'id'           => '',
			'name'         => '',
			'what'         => '',
			'group'        => 'one-off',
			'late_after_h' => null,
			'schedule'     => '',
			'planned'      => true,
			'registry'     => null,
		),
		array_intersect_key( $typed, array_flip( array( 'id', 'name', 'what', 'group', 'late_after_h', 'schedule' ) ) )
	);
	$planned = $new || $w['planned'];
	echo '<h2>' . esc_html( $new ? 'Add New Worker' : 'Edit ' . $w['name'] ) . '</h2>';
	if ( $new ) {
		echo '<p>A planned worker: the ID is the file name its workflow will have, without .yml. It shows "Not built yet" until a workflow with that ID reports a run, and never raises an alert.</p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_worker_save"><input type="hidden" name="worker_edit" value="' . esc_attr( $new ? 'new' : $w['id'] ) . '">';
	wp_nonce_field( 'aw_worker_save' );
	echo '<table class="form-table" role="presentation"><tbody>';
	if ( $new ) {
		echo '<tr><th scope="row"><label for="aw-worker-id">ID</label></th><td><input type="text" id="aw-worker-id" name="worker_id" class="regular-text code" value="' . esc_attr( $v['id'] ) . '" pattern="[a-z0-9_-]+" required><p class="description">e.g. <code>press-news</code> for <code>press-news.yml</code>. It never changes.</p></td></tr>';
	} else {
		echo '<tr><th scope="row">ID</th><td><code>' . esc_html( $w['id'] ) . '</code><p class="description">IDs never change.</p></td></tr>';
	}
	if ( ! $planned ) {
		echo '<tr><th scope="row">Workflow</th><td><a href="' . esc_url( 'https://github.com/wugweb-git/animal-writes-workers/blob/main/.github/workflows/' . $w['file'] ) . '" target="_blank" rel="noopener"><code>' . esc_html( $w['file'] ) . '</code></a><p class="description">Changed in the workers repository.</p></td></tr>';
		echo '<tr><th scope="row">Schedule (IST)</th><td>' . esc_html( aw_workers_schedule( $w['registry'] + array( 'file' => $w['file'] ) ) ) . '<p class="description">Changed in the workflow, not here.</p></td></tr>';
	}
	$reg = $w && $w['registry'] ? $w['registry'] : null;
	echo '<tr><th scope="row"><label for="aw-worker-name">Name</label></th><td><input type="text" id="aw-worker-name" name="worker_name" class="regular-text" value="' . esc_attr( $v['name'] ) . '"' . ( $planned ? ' required' : '' ) . '>'
		. ( $reg ? '<p class="description">' . esc_html( 'In the workflow: ' . $reg['name'] . '. Leave empty for that name.' ) . '</p>' : '' ) . '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-worker-what">What it does</label></th><td><textarea id="aw-worker-what" name="worker_what" rows="3" class="large-text">' . esc_textarea( $v['what'] ) . '</textarea>'
		. ( $reg ? '<p class="description">Leave empty for the registry\'s description.</p>' : '' ) . '</td></tr>';
	echo '<tr><th scope="row"><label for="aw-worker-group">Group</label></th><td><select id="aw-worker-group" name="worker_group">';
	foreach ( aw_worker_groups() as $slug => $g ) {
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $v['group'], $slug, false ) . '>' . esc_html( $g[0] ) . '</option>';
	}
	echo '</select> <label for="aw-worker-group-new" class="screen-reader-text">Or a new group</label><input type="text" id="aw-worker-group-new" name="worker_group_new" class="regular-text" placeholder="Or a new group"><p class="description">A new group is added to the list (with a generic icon).</p></td></tr>';
	$late = null === $v['late_after_h'] || '' === $v['late_after_h'] ? '' : (string) (int) $v['late_after_h'];
	echo '<tr><th scope="row"><label for="aw-worker-late">Late after</label></th><td><input type="number" id="aw-worker-late" name="worker_late" class="small-text" min="1" step="1" value="' . esc_attr( $late ) . '"> hours'
		. '<p class="description">' . esc_html( $planned ? 'Blank: never late. Planned workers never raise an alert; this applies once the workflow is in the registry.' : 'Blank: never late (no alert). In the registry: ' . ( null === $reg['late_after_h'] ? 'never' : $reg['late_after_h'] . ' h' ) . '.' ) . '</p></td></tr>';
	if ( $planned ) {
		echo '<tr><th scope="row"><label for="aw-worker-schedule">Schedule wanted</label></th><td><input type="text" id="aw-worker-schedule" name="worker_schedule" class="regular-text" value="' . esc_attr( $v['schedule'] ) . '" placeholder="e.g. daily at 06:00 IST"><p class="description">In words, for whoever builds the workflow.</p></td></tr>';
	}
	echo '<tr><th scope="row">Keys</th><td>';
	aw_workers_keys_checkboxes( $new ? '' : $w['id'] );
	echo '</td></tr></tbody></table><p class="submit">';
	submit_button( $new ? 'Add worker' : 'Save changes', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( aw_workers_url() ) . '" class="button">Cancel</a></p></form>';
}

/** The saved keys as checkboxes: ticked = the worker may use it (a key for every worker is ticked). Never the key itself. */
function aw_workers_keys_checkboxes( $id ) {
	$rows = aw_ai_rows();
	if ( ! $rows ) {
		echo '<p>No keys saved. <a href="' . esc_url( aw_ai_keys_url( array( 'edit' => 'new' ) ) ) . '">Add New Key</a></p>';
		return;
	}
	echo '<fieldset><legend class="screen-reader-text"><span>Keys</span></legend>';
	foreach ( $rows as $r ) {
		$kid = aw_ai_key_id( $r['key'] );
		$ws  = aw_ai_row_workers( $r );
		$on  = ! $ws || ( '' !== $id && in_array( $id, $ws, true ) );
		echo '<label><input type="checkbox" name="worker_keys[]" value="' . esc_attr( $kid ) . '"' . checked( $on, true, false ) . '> <code>…' . esc_html( substr( $r['key'], -4 ) ) . '</code> '
			. aw_ai_provider_html( (string) $r['provider'] ) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in aw_ai_provider_html
			. ( ! empty( $r['label'] ) ? ' · ' . esc_html( $r['label'] ) : '' )
			. ' <span class="description">(' . esc_html( $ws ? 'for ' . count( $ws ) . ' worker' . ( 1 === count( $ws ) ? '' : 's' ) : 'all workers' ) . ')</span></label><br>';
	}
	echo '<input type="hidden" name="worker_keys_shown" value="1"></fieldset>';
	echo '<p class="description">Ticked: this worker may use the key. Unticking a key that is for all workers makes it for every other worker. The same is on Sourcing → AI models (Edit, or Attach to / Detach from worker).</p>';
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

// Save a worker (Add New Worker, or the overrides of an existing one).
add_action(
	'admin_post_aw_worker_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_worker_save' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$edit  = sanitize_key( wp_unslash( $_POST['worker_edit'] ?? '' ) );
		$new   = 'new' === $edit;
		$all   = aw_workers_all();
		$id    = $new ? sanitize_key( wp_unslash( $_POST['worker_id'] ?? '' ) ) : $edit;
		$name  = mb_substr( sanitize_text_field( wp_unslash( $_POST['worker_name'] ?? '' ) ), 0, 120 );
		$what  = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['worker_what'] ?? '' ) ), 0, 500 );
		$sched = mb_substr( sanitize_text_field( wp_unslash( $_POST['worker_schedule'] ?? '' ) ), 0, 200 );
		$late  = trim( sanitize_text_field( wp_unslash( $_POST['worker_late'] ?? '' ) ) );
		$late  = '' === $late ? null : max( 1, absint( $late ) );
		$group = sanitize_key( wp_unslash( $_POST['worker_group'] ?? '' ) );
		$label = mb_substr( sanitize_text_field( wp_unslash( $_POST['worker_group_new'] ?? '' ) ), 0, 40 );
		if ( ! $new && ! isset( $all[ $id ] ) ) {
			wp_die( 'No such worker.', 404 );
		}
		$w       = $all[ $id ] ?? null;
		$planned = $new || $w['planned'];
		$err     = '';
		if ( $new && ( '' === $id || 'new' === $id ) ) {
			$err = 'id';
		} elseif ( $new && ( isset( $all[ $id ] ) || isset( aw_workers_meta()[ $id ] ) ) ) {
			$err = 'clash';
		} elseif ( $planned && '' === $name ) {
			$err = 'name';
		}
		if ( $err ) {
			set_transient(
				'aw_worker_form_' . get_current_user_id(),
				array(
					'id'           => $id,
					'name'         => $name,
					'what'         => $what,
					'group'        => $group,
					'late_after_h' => $late,
					'schedule'     => $sched,
				),
				5 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( aw_workers_url( array( 'edit' => $new ? 'new' : $id, 'worker_err' => $err ) ) );
			exit;
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
			if ( ( AW_WORKER_DEFAULT_GROUP[ $id ] ?? '' ) === $group ) {
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
		update_option( 'aw_workers_meta', $meta, false );
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
		if ( ! empty( $_POST['worker_keys_shown'] ) ) {
			list( $did, $skip ) = aw_workers_apply_keys( $id, array_map( 'sanitize_key', (array) wp_unslash( $_POST['worker_keys'] ?? array() ) ) );
			if ( $did ) {
				kits_sourcing_log( 'ai', 'worker ' . $id . ': key ' . implode( ', ', $did ) );
			}
		}
		if ( $log ) {
			kits_sourcing_log( 'workers', 'Worker ' . $id . ': ' . implode( '; ', $log ) );
		}
		wp_safe_redirect(
			aw_workers_url(
				array(
					'worker_done' => $new ? 'added' : ( $log || ! empty( $did ) ? 'saved' : 'same' ),
					'worker_name' => rawurlencode( $after['name'] ),
					'worker_skip' => $skip,
				)
			)
		);
		exit;
	}
);

// Delete a Planned worker. Its ID leaves the keys attached to it (a key left with no worker is for every worker again).
add_action(
	'admin_post_aw_worker_delete',
	function () {
		$id = sanitize_key( wp_unslash( $_GET['worker'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_worker_delete_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$w = aw_worker( $id );
		if ( ! $w || ! $w['planned'] ) {
			wp_die( 'Only a planned worker can be deleted.', 400 );
		}
		$meta = aw_workers_meta();
		unset( $meta[ $id ] );
		update_option( 'aw_workers_meta', $meta, false );
		$rows = aw_ai_rows_fresh();
		$freed = array();
		foreach ( $rows as $i => $r ) {
			$ws = aw_ai_row_workers( $r );
			if ( in_array( $id, $ws, true ) ) {
				$rows[ $i ]['workers'] = array_values( array_diff( $ws, array( $id ) ) );
				if ( ! $rows[ $i ]['workers'] ) {
					$freed[] = aw_ai_key_short( $r );
				}
			}
		}
		update_option( 'aw_ai_models', $rows, false );
		kits_sourcing_log( 'workers', 'deleted planned worker ' . $id . ' "' . $w['name'] . '"' . ( $freed ? '; key ' . implode( ', ', $freed ) . ' now for all workers' : '' ) );
		wp_safe_redirect(
			aw_workers_url(
				array(
					'worker_done' => 'deleted',
					'worker_name' => rawurlencode( $w['name'] ),
				)
			)
		);
		exit;
	}
);

// Run now (a row action with its own nonce): starts the workflow on GitHub, logged as before.
add_action(
	'admin_post_aw_worker_run',
	function () {
		$id = sanitize_key( wp_unslash( $_GET['worker'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_worker_run_' . $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$w = aw_worker( $id );
		if ( ! $w || $w['planned'] || ! empty( $w['registry']['needs_input'] ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$res = aw_workers_run_now( $w['file'] );
		wp_safe_redirect(
			aw_workers_url(
				array(
					'aw_ran' => $w['file'],
					'aw_ok'  => true === $res ? 1 : 0,
				)
			)
		);
		exit;
	}
);

add_filter( 'removable_query_args', fn( $args ) => 'aw-sourcing' === sanitize_key( $_GET['page'] ?? '' ) && 'workers' === sanitize_key( $_GET['tab'] ?? '' ) ? array_merge( $args, array( 'aw_ran', 'aw_ok', 'worker_done', 'worker_name', 'worker_skip', 'worker_err' ) ) : $args ); // phpcs:ignore WordPress.Security.NonceVerification -- only on this screen
