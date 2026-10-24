<?php
/**
 * Settings → Sourcing → AI models: every key in one core list table (owner, 24 Oct 2026). The keys saved here (in the order the
 * workers try them), then the keys in the workers' GitHub secrets (from their own reports; changed in GitHub).
 * Views by state, search, row actions (Edit, Move up, Move down, Check again, Remove) and bulk actions (Attach to worker,
 * Detach from worker, Check again, Remove), all posting to admin-post.php (action aw_ai_keys_list) with the table's
 * nonce; Edit opens the key's own screen (&edit=<key id>). A broken key gets the plugins-list problem row.
 * Loaded only on that screen.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** AI and tool keys. */
class AW_Sourcing_AI_Keys_Table extends WP_List_Table {

	/** @var array Saved rows (with their position) and GitHub-secret rows. */
	private $all = array();

	public function __construct() {
		parent::__construct( array( 'singular' => 'key', 'plural' => 'keys', 'ajax' => false, 'screen' => 'settings_page_aw-sourcing' ) );
		$usage = aw_ai_usage();
		$shown = array();
		foreach ( aw_ai_rows() as $i => $r ) {
			$uid         = aw_ai_key_id( $r['key'] );
			$shown[]     = $uid;
			$this->all[] = array(
				'pos'      => $i,
				'id'       => $uid,
				'last4'    => substr( $r['key'], -4 ),
				'provider' => (string) $r['provider'],
				'model'    => (string) $r['model'],
				'label'    => (string) ( $r['label'] ?? '' ),
				'workers'  => aw_ai_row_workers( $r ),
				'status'   => (string) $r['status'],
				'last_ok'  => (int) ( $r['last_ok'] ?? 0 ),
				'limits'   => ! empty( $r['cooldown'] ) && $r['cooldown'] > time() ? 'Cooldown until ' . wp_date( 'j M, H:i', $r['cooldown'] ) . ( ! empty( $r['limits'] ) ? ' · ' . $r['limits'] : '' ) : ( ! empty( $r['limits'] ) ? $r['limits'] : 'Not reported' ),
				'usage'    => aw_ai_usage_line( (array) ( $usage[ $uid ] ?? array() ) ),
				'checked'  => (int) $r['checked'],
				'recheck'  => ! empty( $r['recheck'] ),
				'secret'   => false,
			);
		}
		// The workers' GitHub secrets: write-only in GitHub, so shown from the workers' reports.
		$other = array_diff_key( $usage, array_flip( $shown ) );
		uasort( $other, fn( $a, $b ) => (int) ( $b['last']['at'] ?? 0 ) <=> (int) ( $a['last']['at'] ?? 0 ) );
		foreach ( $other as $uid => $u ) {
			$prov        = (string) ( $u['provider'] ?? '' );
			$last        = (array) ( $u['last'] ?? array() );
			$this->all[] = array(
				'pos'      => PHP_INT_MAX,
				'id'       => (string) $uid,
				'last4'    => (string) ( $u['last4'] ?? '' ),
				'provider' => $prov,
				'model'    => implode( ', ', array_keys( (array) ( $last['models'] ?? array() ) ) ),
				'label'    => '',
				'workers'  => array(),
				'status'   => 'paid' === aw_ai_tier( $prov ) ? 'paid' : ( ! empty( $last['ok'] ) ? 'works' : ( ! empty( $last['limited'] ) ? 'limited' : 'failed' ) ),
				'last_ok'  => 0,
				'limits'   => '',
				'usage'    => aw_ai_usage_line( $u ),
				'checked'  => (int) ( $last['at'] ?? 0 ),
				'recheck'  => false,
				'secret'   => true,
			);
		}
	}

	/** ok, wait or bad, as the status badge has it. */
	public static function tone( $status ) {
		if ( in_array( $status, array( 'works', 'tool', 'paid' ), true ) ) {
			return 'ok';
		}
		return in_array( $status, array( 'limited', 'unchecked', 'card' ), true ) ? 'wait' : 'bad';
	}

	private function view() {
		$v = sanitize_key( $_GET['keys'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $v, array( 'ok', 'wait', 'bad', 'secret' ), true ) ? $v : '';
	}

	private function in_view( $r, $v ) {
		return '' === $v || ( 'secret' === $v ? $r['secret'] : ( ! $r['secret'] && self::tone( $r['status'] ) === $v ) );
	}

	protected function get_views() {
		$base = aw_ai_keys_url();
		$out  = array();
		foreach ( array( '' => 'All', 'ok' => 'Working', 'wait' => 'Waiting', 'bad' => 'Broken', 'secret' => 'GitHub secrets' ) as $k => $l ) {
			$n = count( array_filter( $this->all, fn( $r ) => $this->in_view( $r, $k ) ) );
			if ( $n || '' === $k ) {
				$out[ '' === $k ? 'all' : $k ] = '<a href="' . esc_url( $k ? add_query_arg( 'keys', $k, $base ) : $base ) . '"' . ( $this->view() === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a>';
			}
		}
		return $out;
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'key'      => 'Key',
			'provider' => 'Provider',
			'model'    => 'Model',
			'used_by'  => 'Used by',
			'label'    => 'Label',
			'group'    => 'Group',
			'caps'     => 'Good for',
			'status'   => 'Status',
			'last_ok'  => 'Last worked',
			'limits'   => 'Limit / cooldown',
			'usage'    => 'Work in the last run',
			'checked'  => 'Checked',
		);
	}

	protected function get_sortable_columns() {
		return array( 'provider' => array( 'provider', false ), 'label' => array( 'label', false ), 'status' => array( 'status', false ), 'last_ok' => array( 'last_ok', true ), 'checked' => array( 'checked', true ) );
	}

	protected function get_primary_column_name() {
		return 'key';
	}

	protected function get_bulk_actions() {
		return array( 'attach' => 'Attach to worker…', 'detach' => 'Detach from worker…', 'recheck' => 'Check again', 'remove' => 'Remove' );
	}

	/**
	 * The core bulk menu is named "action", which admin-post.php reads as its own; this one is ai_bulk / ai_bulk2. Beside
	 * it, the worker for Attach to / Detach from (ai_worker / ai_worker2).
	 */
	protected function bulk_actions( $which = '' ) {
		$two = 'top' === $which ? '' : '2';
		echo '<label for="aw-ai-bulk-' . esc_attr( $which ) . '" class="screen-reader-text">Select bulk action</label>';
		echo '<select name="ai_bulk' . esc_attr( $two ) . '" id="aw-ai-bulk-' . esc_attr( $which ) . '"><option value="">Bulk actions</option>';
		foreach ( $this->get_bulk_actions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
		echo '<label for="aw-ai-worker-' . esc_attr( $which ) . '" class="screen-reader-text">Worker to attach to or detach from</label>';
		echo '<select name="ai_worker' . esc_attr( $two ) . '" id="aw-ai-worker-' . esc_attr( $which ) . '"><option value="">Worker (for attach / detach)</option>';
		foreach ( aw_worker_groups() as $slug => $g ) {
			$in = array_filter( aw_workers_all(), fn( $w ) => $w['group'] === $slug );
			if ( ! $in ) {
				continue;
			}
			echo '<optgroup label="' . esc_attr( $g[0] ) . '">';
			foreach ( $in as $id => $w ) {
				echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $w['name'] . ' (' . $id . ')' ) . '</option>';
			}
			echo '</optgroup>';
		}
		echo '</select>';
		submit_button( 'Apply', 'action', '', false, array( 'id' => 'aw-ai-apply-' . $which ) );
	}

	/** Sorted or filtered lists show keys out of the workers' order, so Move up / down only show on the plain list. */
	private function plain() {
		return '' === $this->view() && empty( $_GET['orderby'] ) && empty( $_GET['s'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function prepare_items() {
		$v    = $this->view();
		$s    = mb_strtolower( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows = array_values(
			array_filter(
				$this->all,
				fn( $r ) => $this->in_view( $r, $v ) && ( '' === $s || false !== strpos( mb_strtolower( $r['last4'] . ' ' . $r['provider'] . ' ' . $this->provider_label( $r['provider'] ) . ' ' . $r['model'] . ' ' . $r['label'] . ' ' . $this->used_by_text( $r ) ), $s ) )
			)
		);
		$by = sanitize_key( $_GET['orderby'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $this->get_sortable_columns()[ $by ] ) ) { // otherwise the workers' order
			$asc = 'asc' === sanitize_key( $_GET['order'] ?? 'asc' ); // phpcs:ignore WordPress.Security.NonceVerification
			usort(
				$rows,
				function ( $a, $b ) use ( $by, $asc ) {
					$text = fn( $r ) => 'provider' === $by ? $this->provider_label( $r['provider'] ) : ( 'label' === $by ? $r['label'] : $r['status'] );
					$c    = in_array( $by, array( 'last_ok', 'checked' ), true ) ? ( $a[ $by ] <=> $b[ $by ] ) : strcasecmp( $text( $a ), $text( $b ) );
					return $asc ? $c : -$c;
				}
			);
		}
		$per = $this->get_items_per_page( 'aw_ai_keys_per_page', 100 );
		$this->set_pagination_args( array( 'total_items' => count( $rows ), 'per_page' => $per ) );
		$this->items           = array_slice( $rows, ( $this->get_pagenum() - 1 ) * $per, $per );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'key' );
	}

	private function provider_label( $p ) {
		return $p ? ( AW_AI_PROVIDERS[ $p ][0] ?? AW_AI_TOOLS[ $p ][0] ?? $p ) : '—';
	}

	/** The workers' names (for search). */
	private function used_by_text( $r ) {
		if ( ! $r['workers'] ) {
			return 'all workers';
		}
		return implode( ' ', array_map( fn( $id ) => $id . ' ' . ( aw_worker( $id )['name'] ?? '' ), $r['workers'] ) );
	}

	/** A broken key gets the plugins list's problem row under it (core notice classes). */
	public function single_row( $item ) {
		parent::single_row( $item );
		if ( ! $item['secret'] && 'bad' === self::tone( $item['status'] ) ) {
			echo '<tr class="plugin-update-tr"><td colspan="' . (int) $this->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message notice inline notice-error notice-alt"><p>'
				. esc_html( sprintf( 'Key …%s: %s. It is not used; safe to remove.', $item['last4'], AW_AI_STATUS[ $item['status'] ] ?? $item['status'] ) ) . '</p></div></td></tr>';
		}
	}

	protected function column_cb( $item ) {
		if ( $item['secret'] ) {
			return '';
		}
		return '<label class="screen-reader-text" for="aw-ai-' . esc_attr( $item['id'] ) . '">' . esc_html( 'Select key …' . $item['last4'] ) . '</label><input type="checkbox" name="ids[]" value="' . esc_attr( $item['id'] ) . '" id="aw-ai-' . esc_attr( $item['id'] ) . '">';
	}

	/** A row action is a submit button of the form: ai_row = "op:id". */
	private function op_button( $op, $label, $id, $confirm = '' ) {
		return '<button type="submit" class="button-link" name="ai_row" value="' . esc_attr( $op . ':' . $id ) . '"' . ( $confirm ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>' . esc_html( $label ) . '</button>';
	}

	protected function column_key( $item ) {
		$out = '<code>…' . esc_html( $item['last4'] ) . '</code>';
		if ( $item['secret'] ) {
			return $out . '<br><span class="description">GitHub secret: change it in GitHub</span>';
		}
		$acts = array( 'edit' => '<a href="' . esc_url( aw_ai_keys_url( array( 'edit' => $item['id'] ) ) ) . '">Edit</a>' );
		if ( $this->plain() ) {
			$acts['up']   = $this->op_button( 'up', 'Move up', $item['id'] );
			$acts['down'] = $this->op_button( 'down', 'Move down', $item['id'] );
		}
		$acts['recheck'] = $this->op_button( 'recheck', 'Check again', $item['id'] );
		$acts['trash']   = $this->op_button( 'remove', 'Remove', $item['id'], 'Remove key …' . $item['last4'] . '? This takes effect straight away.' );
		return $out . $this->row_actions( $acts );
	}

	protected function column_provider( $item ) {
		return aw_ai_provider_html( $item['provider'] );
	}

	/** "All workers", or each worker with its group's dashicon. */
	protected function column_used_by( $item ) {
		if ( $item['secret'] ) {
			return 'All workers<br><span class="description">GitHub secret: cannot be attached</span>';
		}
		if ( ! $item['workers'] ) {
			return 'All workers';
		}
		return implode( '<br>', array_map( 'aw_worker_label_html', $item['workers'] ) );
	}

	protected function column_group( $item ) {
		return esc_html( aw_ai_tier_label( $item['provider'] ) );
	}

	protected function column_caps( $item ) {
		return aw_ai_caps_html( $item['provider'] ) . ( ! empty( AW_AI_INFO[ $item['provider'] ][1] ) ? '<p class="description">' . esc_html( AW_AI_INFO[ $item['provider'] ][1] ) . '</p>' : '' );
	}

	protected function column_model( $item ) {
		return '<code>' . esc_html( '' !== $item['model'] ? $item['model'] : '—' ) . '</code>';
	}

	protected function column_status( $item ) {
		if ( 'paid' === $item['status'] ) {
			return '<span class="dashicons dashicons-minus" aria-hidden="true"></span> Not used (paid)';
		}
		if ( 'failed' === $item['status'] ) {
			return '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> Failed in the last run';
		}
		return aw_ai_status_badge( $item['status'] ) . ( $item['recheck'] ? '<p class="description">Being checked again</p>' : '' );
	}

	protected function column_default( $item, $col ) {
		if ( 'last_ok' === $col ) {
			return $item['secret'] ? '—' : esc_html( $item['last_ok'] ? wp_date( 'j M, H:i', $item['last_ok'] ) : 'Never' );
		}
		if ( 'checked' === $col ) {
			return esc_html( $item['checked'] ? wp_date( 'j M, H:i', $item['checked'] ) : '—' );
		}
		return esc_html( '' !== (string) $item[ $col ] ? (string) $item[ $col ] : '—' );
	}

	public function no_items() {
		echo 'No keys yet.';
	}
}
