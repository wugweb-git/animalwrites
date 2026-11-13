<?php
/**
 * Review-queue rules in the plugin (owner, 14 Nov 2026: "you don't have to put me on review"). The rules are in
 * autoresolve-model.php (tested without WordPress); this file applies them.
 *
 * - When a value is queued for review (aw_enrich_queued) it is decided at once: approved, rejected as no change or
 *   another place, or left for the owner. The row shows its reason ("Auto: ...") in the change log.
 * - POST sourceweb/v1/enrichment/autoresolve {dry, limit} decides the rows already waiting (dry run by default).
 * - A PIN code in a listing's address fills an empty PIN field (so the address schema has postalCode), after every
 *   write to the address and in the sweep.
 * - Never on a claimed or verified listing. Off with the option aw_enrich_autoresolve = 0 or the filter
 *   aw_enrich_autoresolve.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/autoresolve-model.php';

/** Whether queued values are decided by the rules. */
function aw_ar_on() {
	return (bool) apply_filters( 'aw_enrich_autoresolve', '0' !== (string) get_option( 'aw_enrich_autoresolve', '1' ) );
}

/**
 * What the model needs to know about where a value came from.
 *
 * @param int   $lid     Listing.
 * @param array $sources Row sources.
 * @return array{own_site: bool, site_stem: string, site_host: string}
 */
function aw_ar_context( $lid, $sources ) {
	$own = false;
	foreach ( (array) $sources as $s ) {
		if ( is_array( $s ) && ( 'website' === ( $s['key'] ?? '' ) || false !== stripos( (string) ( $s['name'] ?? '' ), 'own website' ) ) ) {
			$own = true;
		}
	}
	$host = wp_parse_url( (string) get_post_meta( $lid, 'aw_website', true ), PHP_URL_HOST );
	$stem = $host ? (string) preg_replace( '/\..*$/', '', (string) preg_replace( '/^www\./', '', strtolower( $host ) ) ) : '';
	return array( 'own_site' => $own, 'site_stem' => $stem, 'site_host' => $host ? (string) preg_replace( '/^www\\./', '', strtolower( $host ) ) : '' );
}

/**
 * Whether another listing already holds this phone number (compared by its last 10 digits).
 *
 * @param int    $lid    Listing.
 * @param string $number Number.
 * @return bool
 */
function aw_ar_phone_elsewhere( $lid, $number ) {
	global $wpdb;
	$last = substr( preg_replace( '/\D+/', '', (string) $number ), -10 );
	if ( strlen( $last ) < 10 ) {
		return false;
	}
	return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'aw_phones' AND post_id <> %d AND REGEXP_REPLACE( meta_value, '[^0-9]', '' ) LIKE %s LIMIT 1", (int) $lid, '%' . $wpdb->esc_like( $last ) . '%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Decide one waiting row, and apply the decision unless $apply is false.
 *
 * @param int  $id    Row ID.
 * @param bool $apply Write the decision (false: only say what it would be).
 * @return array{act: string, why: string}|null null when the row is not waiting.
 */
function aw_ar_row( $id, $apply = true ) {
	$r = aw_enrich_row( (int) $id );
	if ( ! $r || 'pending' !== $r['status'] || ! is_string( $r['value'] ) ) {
		return null;
	}
	$lid = (int) $r['listing_id'];
	if ( 0 === strpos( (string) $r['note'], 'From an imported list' ) ) {
		return array( 'act' => 'leave', 'why' => 'an imported list is always checked by a person' );
	}
	if ( aw_enrich_is_protected( $lid ) ) {
		return array( 'act' => 'leave', 'why' => 'a claimed or verified listing is never changed automatically' );
	}
	if ( 'phone' === $r['field'] && aw_ar_phone_elsewhere( $lid, $r['value'] ) ) {
		return array( 'act' => 'leave', 'why' => 'the number is on another listing (a name clash?)' );
	}
	$d = aw_ar_decide( $r['field'], (string) aw_enrich_current( $lid, $r['field'] ), $r['value'], (string) $r['confidence'], aw_ar_context( $lid, $r['sources'] ), aw_ar_rules() );
	if ( ! $apply || 'leave' === $d['act'] ) {
		return $d;
	}
	if ( 'reject' === $d['act'] ) {
		aw_enrich_set_status( (int) $id, 'rejected', 'Auto: ' . $d['why'] );
	} elseif ( aw_enrich_approve( (int) $id ) ) {
		aw_enrich_set_status( (int) $id, 'approved', 'Auto: ' . $d['why'] );
	} else {
		return array( 'act' => 'leave', 'why' => 'could not be written' );
	}
	return $d;
}

// A value queued for review is decided at once; the results reply says what became of it.
add_filter(
	'aw_enrich_queued_decision',
	function ( $act, $id ) {
		if ( $id && aw_ar_on() ) {
			$d = aw_ar_row( (int) $id );
			return $d ? $d['act'] : '';
		}
		return $act;
	},
	10,
	2
);

/**
 * The PIN code in the address, when the PIN field is empty.
 *
 * @param int $lid Listing.
 * @return bool Whether the PIN field was filled.
 */
function aw_ar_fill_pin( $lid ) {
	static $busy = false;
	$lid = (int) $lid;
	if ( $busy || 'aw_listing' !== get_post_type( $lid ) || aw_enrich_is_protected( $lid ) || '' !== trim( (string) get_post_meta( $lid, 'aw_pincode', true ) ) ) {
		return false;
	}
	$pin = aw_ar_pin( (string) get_post_meta( $lid, 'aw_address', true ) );
	if ( '' === $pin ) {
		return false;
	}
	$src  = array( array( 'key' => 'listing', 'name' => 'The listing\'s own address', 'url' => '', 'fetched_at' => wp_date( 'Y-m-d' ) ) );
	$busy = true; // writing the PIN calls aw_enrich_changed again
	$ok   = aw_enrich_write( $lid, 'pincode', $pin, 'apply', $src );
	$busy = false;
	if ( $ok ) {
		aw_enrich_add_row( $lid, 'pincode', $pin, '', $src, 'high', 'auto-applied', 'autoresolve', 'From the listing\'s own address' );
	}
	return (bool) $ok;
}

// After every write to a listing from the enrichment: its address may now hold a PIN the field lacks.
add_action(
	'aw_enrich_changed',
	function ( $lid ) {
		if ( aw_ar_on() ) {
			aw_ar_fill_pin( (int) $lid );
		}
	}
);

/**
 * Decide the rows already waiting.
 *
 * @param WP_REST_Request $req dry (default true), limit (default 300, at most 1000).
 * @return WP_REST_Response
 */
function aw_ar_rest_sweep( WP_REST_Request $req ) {
	global $wpdb;
	$dry   = ! ( false === $req->get_param( 'dry' ) || 'false' === $req->get_param( 'dry' ) || '0' === (string) $req->get_param( 'dry' ) );
	$limit = max( 1, min( 1000, (int) ( $req->get_param( 'limit' ) ? $req->get_param( 'limit' ) : 300 ) ) );
	$t     = aw_enrich_table();
	$ids   = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE status = 'pending' AND field NOT IN ( 'closed', 'merge_into', 'place' ) ORDER BY id ASC LIMIT %d", $limit ) ) ); // phpcs:ignore WordPress.DB
	$count = array();
	$seen  = array();
	foreach ( $ids as $id ) {
		$d = aw_ar_row( $id, ! $dry );
		if ( ! $d ) {
			continue;
		}
		$k           = $d['act'] . ': ' . $d['why'];
		$count[ $k ] = ( $count[ $k ] ?? 0 ) + 1;

		$seen[ $d['act'] ][] = $id;
	}
	$pins = 0;
	if ( ! $dry ) {
		foreach ( (array) $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = 'aw_address' LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = 'aw_pincode' WHERE p.post_type = 'aw_listing' AND p.post_status IN ( 'publish', 'pending' ) AND ( c.meta_value IS NULL OR c.meta_value = '' ) AND a.meta_value REGEXP '[1-9][0-9]{2} ?[0-9]{3}' LIMIT 500" ) as $lid ) { // phpcs:ignore WordPress.DB
			$pins += aw_ar_fill_pin( (int) $lid ) ? 1 : 0;
		}
	}
	ksort( $count );
	return rest_ensure_response(
		array(
			'dry'       => $dry,
			'rows'      => count( $ids ),
			'by_reason' => $count,
			'approve'   => count( $seen['approve'] ?? array() ),
			'reject'    => count( $seen['reject'] ?? array() ),
			'leave'     => count( $seen['leave'] ?? array() ),
			'pins'      => $pins,
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'sourceweb/v1',
			'/enrichment/autoresolve',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => current_user_can( defined( 'AW_ENRICH_CAP' ) ? AW_ENRICH_CAP : 'edit_others_posts' ),
				'callback'            => 'aw_ar_rest_sweep',
				'args'                => array(
					'dry'   => array( 'required' => false ),
					'limit' => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);
	}
);

/* ---------- The rules as rows the owner can add, edit, delete and reorder (SourceWeb → Rules → Review-queue rules) ---------- */

/** The option holding the rules (a list of rows; absent: the built-in rules). */
const AW_AR_RULES_OPTION = 'aw_enrich_autoresolve_rules';

/**
 * The rows as stored, cleaned (a row with an unknown field, condition or action is dropped), or the built-in rules.
 *
 * @return array[] each array{id, field, cond, action, on}
 */
function aw_ar_rules() {
	$saved = get_option( AW_AR_RULES_OPTION );
	if ( ! is_array( $saved ) ) {
		return aw_ar_default_rules();
	}
	$conds = aw_ar_conditions();
	$out   = array();
	foreach ( $saved as $r ) {
		if ( is_array( $r ) && isset( $r['id'], AW_AR_FIELDS[ $r['field'] ?? '' ], AW_AR_ACTIONS[ $r['action'] ?? '' ], $conds[ $r['cond'] ?? '' ] ) && in_array( $r['field'], $conds[ $r['cond'] ]['fields'], true ) ) {
			$out[] = array( 'id' => (string) $r['id'], 'field' => $r['field'], 'cond' => $r['cond'], 'action' => $r['action'], 'on' => ! empty( $r['on'] ) );
		}
	}
	return $out;
}

/**
 * Store the rows and log the change.
 *
 * @param array[] $rows What to store.
 * @param string  $what What changed, in words.
 */
function aw_ar_rules_store( array $rows, $what ) {
	$old = aw_ar_rules();
	update_option( AW_AR_RULES_OPTION, array_values( $rows ), false );
	aw_src_log( 'enrich', $what, $old, array_values( $rows ) );
}

/**
 * Add or edit one rule.
 *
 * @param array  $in       field, cond, action, on.
 * @param string $original The id being edited, '' to add.
 * @return array{0: string, 1: string} done (added | edited | none | error) and a message.
 */
function aw_ar_rule_save( array $in, $original = '' ) {
	$field  = (string) ( $in['field'] ?? '' );
	$cond   = (string) ( $in['cond'] ?? '' );
	$action = (string) ( $in['action'] ?? '' );
	$conds  = aw_ar_conditions();
	if ( ! isset( AW_AR_FIELDS[ $field ] ) || ! isset( $conds[ $cond ] ) || ! in_array( $field, $conds[ $cond ]['fields'], true ) || ! isset( AW_AR_ACTIONS[ $action ] ) ) {
		return array( 'error', __( 'Choose a field, a condition that fits it, and what to do.', 'aw-sourcing' ) );
	}
	$row  = array( 'id' => '', 'field' => $field, 'cond' => $cond, 'action' => $action, 'on' => ! empty( $in['on'] ) );
	$rows = aw_ar_rules();
	$ids  = wp_list_pluck( $rows, 'id' );
	if ( '' !== $original && in_array( $original, $ids, true ) ) {
		foreach ( $rows as $i => $r ) {
			if ( $r['id'] === $original ) {
				$row['id'] = $original;
				if ( $r === $row ) {
					return array( 'none', __( 'Nothing changed.', 'aw-sourcing' ) );
				}
				$rows[ $i ] = $row;
			}
		}
		aw_ar_rules_store( $rows, 'Review-queue rule ' . $original . ' edited' );
		return array( 'edited', __( 'Rule saved.', 'aw-sourcing' ) );
	}
	$base = $field . '-' . $cond;
	$id   = $base;
	for ( $n = 2; in_array( $id, $ids, true ); $n++ ) {
		$id = $base . '-' . $n;
	}
	$row['id'] = $id;
	// A new rule goes after the last rule of its field (rules are tried in order), or at the end.
	$at = count( $rows );
	foreach ( $rows as $i => $r ) {
		if ( $r['field'] === $field ) {
			$at = $i + 1;
		}
	}
	array_splice( $rows, $at, 0, array( $row ) );
	aw_ar_rules_store( $rows, 'Review-queue rule ' . $id . ' added' );
	return array( 'added', __( 'Rule added.', 'aw-sourcing' ) );
}

/**
 * Delete one rule.
 *
 * @param string $id Rule id.
 * @return array{0: string, 1: string}
 */
function aw_ar_rule_delete( $id ) {
	$rows = aw_ar_rules();
	$keep = array_values( array_filter( $rows, fn( $r ) => $r['id'] !== $id ) );
	if ( count( $keep ) === count( $rows ) ) {
		return array( 'none', __( 'No such rule.', 'aw-sourcing' ) );
	}
	aw_ar_rules_store( $keep, 'Review-queue rule ' . $id . ' deleted' );
	return array( 'deleted', __( 'Rule deleted.', 'aw-sourcing' ) );
}

/**
 * Move a rule one place earlier or later among the rules of its field (rules are tried in order).
 *
 * @param string $id  Rule id.
 * @param string $dir up | down.
 * @return array{0: string, 1: string}
 */
function aw_ar_rule_move( $id, $dir ) {
	$rows = aw_ar_rules();
	$pos  = array_search( $id, wp_list_pluck( $rows, 'id' ), true );
	if ( false === $pos ) {
		return array( 'none', __( 'No such rule.', 'aw-sourcing' ) );
	}
	$step = 'up' === $dir ? -1 : 1;
	for ( $j = $pos + $step; isset( $rows[ $j ] ); $j += $step ) {
		if ( $rows[ $j ]['field'] === $rows[ $pos ]['field'] ) {
			$tmp          = $rows[ $pos ];
			$rows[ $pos ] = $rows[ $j ];
			$rows[ $j ]   = $tmp;
			aw_ar_rules_store( $rows, 'Review-queue rule ' . $id . ' moved ' . ( 'up' === $dir ? 'earlier' : 'later' ) );
			return array( 'moved', __( 'Rule moved.', 'aw-sourcing' ) );
		}
	}
	return array( 'none', __( 'It is already there.', 'aw-sourcing' ) );
}

/** The built-in rules again (the edits are dropped; the log keeps them). */
function aw_ar_rules_reset() {
	$old = aw_ar_rules();
	delete_option( AW_AR_RULES_OPTION );
	aw_src_log( 'enrich', 'Review-queue rules reset to the built-in ones', $old, aw_ar_default_rules() );
	return array( 'reset', __( 'The built-in rules are back.', 'aw-sourcing' ) );
}

/**
 * A rule row for the screen.
 *
 * @param array $r Row.
 * @return array
 */
function aw_ar_rule_view( array $r ) {
	$conds = aw_ar_conditions();
	return array(
		'id'           => $r['id'],
		'field'        => $r['field'],
		'field_label'  => AW_AR_FIELDS[ $r['field'] ],
		'cond'         => $r['cond'],
		'cond_label'   => $conds[ $r['cond'] ]['label'],
		'action'       => $r['action'],
		'action_label' => AW_AR_ACTIONS[ $r['action'] ],
		'on'           => (bool) $r['on'],
	);
}

/**
 * The reply every save, delete, move and reset route gives (the shape the screen's answerMsg() reads).
 *
 * @param array{0: string, 1: string} $res done and message.
 * @return WP_REST_Response|WP_Error
 */
function aw_ar_answer( array $res ) {
	if ( 'error' === $res[0] ) {
		return new WP_Error( 'aw_ar_error', $res[1], array( 'status' => 400 ) );
	}
	return rest_ensure_response( array( 'done' => $res[0], 'tone' => 'none' === $res[0] ? 'info' : 'success', 'message' => $res[1] ) );
}

add_action(
	'rest_api_init',
	function () {
		$cap  = fn() => current_user_can( defined( 'AW_SRC_CAP' ) ? AW_SRC_CAP : 'manage_options' );
		$id   = array( 'type' => 'string', 'required' => true );
		$read = function ( WP_REST_Request $req ) {
			$rows = aw_ar_rules();
			$cur  = (string) $req->get_param( 'id' );
			$one  = null;
			foreach ( $rows as $r ) {
				if ( $r['id'] === $cur ) {
					$one = aw_ar_rule_view( $r );
				}
			}
			$conds = array();
			foreach ( aw_ar_conditions() as $k => $c ) {
				$conds[] = array( 'value' => $k, 'label' => $c['label'], 'fields' => $c['fields'] );
			}
			$fields  = array();
			$actions = array();
			foreach ( AW_AR_FIELDS as $k => $l ) {
				$fields[] = array( 'value' => $k, 'label' => $l );
			}
			foreach ( AW_AR_ACTIONS as $k => $l ) {
				$actions[] = array( 'value' => $k, 'label' => $l );
			}
			return rest_ensure_response(
				array(
					'on'         => aw_ar_on(),
					'stored'     => is_array( get_option( AW_AR_RULES_OPTION ) ),
					'rows'       => array_map( 'aw_ar_rule_view', $rows ),
					'one'        => $one,
					'fields'     => $fields,
					'conditions' => $conds,
					'actions'    => $actions,
				)
			);
		};
		register_rest_route( 'sourceweb/v1', '/rules/autoresolve', array( 'methods' => 'GET', 'permission_callback' => $cap, 'callback' => $read, 'args' => array( 'id' => array( 'type' => 'string', 'default' => '' ) ) ) );
		register_rest_route(
			'sourceweb/v1',
			'/rules/autoresolve/save',
			array(
				'methods'             => 'POST',
				'permission_callback' => $cap,
				'callback'            => fn( WP_REST_Request $q ) => aw_ar_answer( aw_ar_rule_save( (array) $q->get_json_params(), (string) $q->get_param( 'original' ) ) ),
				'args'                => array( 'original' => array( 'type' => 'string', 'default' => '' ) ),
			)
		);
		register_rest_route( 'sourceweb/v1', '/rules/autoresolve/delete', array( 'methods' => 'POST', 'permission_callback' => $cap, 'callback' => fn( WP_REST_Request $q ) => aw_ar_answer( aw_ar_rule_delete( (string) $q['id'] ) ), 'args' => array( 'id' => $id ) ) );
		register_rest_route( 'sourceweb/v1', '/rules/autoresolve/move', array( 'methods' => 'POST', 'permission_callback' => $cap, 'callback' => fn( WP_REST_Request $q ) => aw_ar_answer( aw_ar_rule_move( (string) $q['id'], 'up' === $q['dir'] ? 'up' : 'down' ) ), 'args' => array( 'id' => $id, 'dir' => array( 'type' => 'string', 'default' => 'down' ) ) ) );
		register_rest_route( 'sourceweb/v1', '/rules/autoresolve/reset', array( 'methods' => 'POST', 'permission_callback' => $cap, 'callback' => fn() => aw_ar_answer( aw_ar_rules_reset() ) ) );
		register_rest_route(
			'sourceweb/v1',
			'/rules/autoresolve/switch',
			array(
				'methods'             => 'POST',
				'permission_callback' => $cap,
				'callback'            => function ( WP_REST_Request $q ) {
					$on = ! empty( $q->get_json_params()['on'] );
					update_option( 'aw_enrich_autoresolve', $on ? '1' : '0', false );
					aw_src_log( 'enrich', 'Review-queue rules switched ' . ( $on ? 'on' : 'off' ), $on ? '0' : '1', $on ? '1' : '0' );
					return aw_ar_answer( array( 'switched', $on ? __( 'The rules decide waiting values again.', 'aw-sourcing' ) : __( 'The rules are off: every value waits for a person.', 'aw-sourcing' ) ) );
				},
			)
		);
	}
);
