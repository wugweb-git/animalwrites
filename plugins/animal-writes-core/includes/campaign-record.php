<?php
/**
 * Campaign record, the parts campaign-tools.php does not hold (Campaign Content Standard v1.0, Notion, approved
 * 17 Oct 2026): metric records for a campaign's own figures, the Real-world impact module, and the editor-only
 * blocks of a campaign page. Lifecycle state, action window, outcome and its "as of" date are campaign-tools.php's
 * (aw_camp_state, aw_camp_window, aw_camp_outcome, [aw_camp_status]); this file only reads them.
 *
 * Post meta on an aw_campaign:
 *   aw_campaign_metrics          JSON list of {key, label, value, attribution, source_label, source_url, verified
 *                                (Y-m-d), threshold (number, 0 = none), count (number compared with the threshold),
 *                                stale_days (0 = never stale)}. For fixed figures such as a closed campaign's results;
 *                                the live letter count stays [aw_campaign_count].
 *   aw_campaign_impact_state     verified|no-change|pending|inconclusive|na   the Real-world impact module (D4.2):
 *                                verified = evidence shows the intended change happened; no-change = evidence shows
 *                                it did not; pending = not yet known; inconclusive = evidence can't settle it; na = hidden
 *   aw_campaign_impact_evidence  int     the item (usually a Library record) that is the evidence
 *
 * Shortcodes:
 *   [aw_campaign_metric key="letters"]  one metric record: hidden when unverified or below its threshold, with its
 *                                       source and "as of" date. Hero and impact use the same record.
 *   [aw_campaign_impact]                the Real-world impact state and its evidence link (hidden when Not applicable)
 *   [aw_campaign_outcome]               the outcome alone, with its "as of" date (for the Campaign goal section)
 *
 * Editor-only blocks (never rendered on the site):
 *   aw-owner-copy      a note where the owner's copy goes; a section with class aw-camp-needs-copy that still holds
 *                      one is hidden as a whole
 *   aw-unused-review   blocks kept for the owner's review after a page was restructured
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CAMP_IMPACT = array(
	'verified'     => 'Verified change',
	'no-change'    => 'No change (verified)', // owner, 17 Oct 2026 (standard D4): evidence shows the change did not happen
	'pending'      => 'Pending verification',
	'inconclusive' => 'Inconclusive',
	'na'           => 'Not applicable',
);

add_action(
	'init',
	function () {
		foreach ( array( 'aw_campaign_metrics' => 'string', 'aw_campaign_impact_state' => 'string', 'aw_campaign_impact_evidence' => 'integer' ) as $k => $t ) {
			register_post_meta(
				'aw_campaign',
				$k,
				array(
					'type'          => $t,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/** A date as the site shows dates (calendar icon), through the theme's helper when it is there. */
function aw_camp_record_date( $day ) {
	if ( function_exists( 'aw_date' ) ) {
		return aw_date( $day );
	}
	$ts = strtotime( $day . ' 12:00:00 Asia/Kolkata' );
	return $ts ? '<span class="aw-date"><span class="kit-ico kit-ico--calendar" aria-hidden="true"></span><time datetime="' . esc_attr( $day ) . '">' . esc_html( wp_date( 'j M Y', $ts ) ) . '</time></span>' : '';
}

/** The metric records of a campaign, by key. */
function aw_camp_metrics( $id ) {
	$list = json_decode( (string) get_post_meta( $id, 'aw_campaign_metrics', true ), true );
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $m ) {
		if ( is_array( $m ) && ! empty( $m['key'] ) ) {
			$out[ (string) $m['key'] ] = $m;
		}
	}
	return $out;
}

add_shortcode(
	'aw_campaign_metric',
	function ( $a ) {
		$a  = shortcode_atts( array( 'key' => '' ), $a );
		$id = aw_camp_id();
		$m  = $id ? ( aw_camp_metrics( $id )[ $a['key'] ] ?? null ) : null;
		if ( ! $m || empty( $m['verified'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $m['verified'] ) ) {
			return ''; // unverified: not shown
		}
		if ( ! empty( $m['threshold'] ) && (float) ( $m['count'] ?? 0 ) < (float) $m['threshold'] ) {
			return ''; // below its display threshold
		}
		$stale = ! empty( $m['stale_days'] ) && time() - strtotime( $m['verified'] . ' 00:00:00 Asia/Kolkata' ) > (int) $m['stale_days'] * DAY_IN_SECONDS;
		$src   = esc_html( (string) ( $m['source_label'] ?? '' ) );
		if ( '' !== $src && ! empty( $m['source_url'] ) ) {
			$src = '<a href="' . esc_url( $m['source_url'] ) . '">' . $src . '</a>';
		}
		$note = implode( ' · ', array_filter( array( esc_html( (string) ( $m['attribution'] ?? '' ) ), $src ) ) );
		return '<div class="aw-camp-metric' . ( $stale ? ' is-stale' : '' ) . '">'
			. '<span class="aw-camp-metric-value">' . esc_html( (string) $m['value'] ) . '</span>'
			. '<span class="aw-camp-metric-label">' . esc_html( (string) ( $m['label'] ?? '' ) ) . '</span>'
			. '<span class="aw-camp-metric-note">' . $note . ( '' !== $note ? ' · ' : '' ) . 'As of ' . aw_camp_record_date( (string) $m['verified'] ) . '</span>'
			. '</div>';
	}
);

add_shortcode(
	'aw_campaign_impact',
	function () {
		$id = aw_camp_id();
		if ( ! $id ) {
			return '';
		}
		$state = (string) get_post_meta( $id, 'aw_campaign_impact_state', true );
		if ( ! isset( AW_CAMP_IMPACT[ $state ] ) || 'na' === $state ) {
			return ''; // Not applicable: hidden
		}
		$ev   = (int) get_post_meta( $id, 'aw_campaign_impact_evidence', true );
		$link = $ev && 'publish' === get_post_status( $ev ) ? '<span class="aw-camp-impact-evidence">Evidence: <a href="' . esc_url( get_permalink( $ev ) ) . '">' . esc_html( get_the_title( $ev ) ) . '</a></span>' : '';
		return '<p class="aw-camp-status aw-camp-impact is-' . esc_attr( $state ) . '"><span class="aw-camp-status-window">' . esc_html( AW_CAMP_IMPACT[ $state ] ) . '</span>' . $link . '</p>';
	}
);

add_shortcode(
	'aw_campaign_outcome',
	function () {
		$id = aw_camp_id();
		if ( ! $id || ! function_exists( 'aw_camp_outcome' ) || 'closed' !== aw_camp_window( $id ) ) {
			return '';
		}
		list( $key, $label, $asof ) = aw_camp_outcome( $id );
		return '<p class="aw-camp-status"><span class="aw-camp-status-outcome is-' . esc_attr( $key ) . '">Outcome: ' . esc_html( $label ) . '</span>'
			. ( $asof ? '<span class="aw-camp-status-asof">As of ' . aw_camp_record_date( $asof ) . '</span>' : '' ) . '</p>';
	}
);

/* ---------- Editor-only blocks ---------- */

/** Whether a parsed block, or any block inside it, has the class. */
function aw_camp_block_has_class( $block, $cls ) {
	if ( preg_match( '/(^|\s)' . preg_quote( $cls, '/' ) . '(\s|$)/', (string) ( $block['attrs']['className'] ?? '' ) ) ) {
		return true;
	}
	foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $b ) {
		if ( aw_camp_block_has_class( $b, $cls ) ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'render_block', // runs for nested blocks too (pre_render_block only sees top-level ones)
	function ( $html, $block ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		if ( preg_match( '/(^|\s)(aw-owner-copy|aw-unused-review)(\s|$)/', $cls ) ) {
			return ''; // editor only
		}
		if ( preg_match( '/(^|\s)aw-camp-needs-copy(\s|$)/', $cls ) && aw_camp_block_has_class( $block, 'aw-owner-copy' ) ) {
			return ''; // the section waits for the owner's copy
		}
		return $html;
	},
	5,
	2
);

/* The editor shows those blocks outlined and labelled, so the owner sees what waits for copy or review. */
add_action(
	'enqueue_block_editor_assets',
	function () {
		if ( 'aw_campaign' !== get_post_type() ) {
			return;
		}
		wp_register_style( 'aw-campaign-record-editor', false, array(), AW_CORE_VERSION );
		wp_enqueue_style( 'aw-campaign-record-editor' );
		wp_add_inline_style(
			'aw-campaign-record-editor',
			'.aw-owner-copy{outline:2px dashed #b45309;outline-offset:4px;background:#fff7ed}'
			. '.aw-owner-copy::before{content:"Owner copy (not shown on the site)";display:block;font:600 12px/1.4 sans-serif;color:#9a3412;margin-bottom:4px}'
			. '.aw-unused-review{outline:2px dashed #64748b;outline-offset:6px;opacity:.75}'
			. '.aw-unused-review::before{content:"Unused (review): not shown on the site";display:block;font:600 12px/1.4 sans-serif;color:#334155;margin-bottom:6px}'
		);
	}
);

/* ---------- Styles on the site ---------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( 'aw_campaign' ) && ! is_front_page() && ! is_page( 'campaigns' ) ) { // the campaign story block shows on Home and the Campaigns page
			return;
		}
		$deps = wp_style_is( 'aw-campaign', 'registered' ) ? array( 'aw-campaign' ) : array(); // after campaign.css, so a campaign's colours win
		wp_enqueue_style( 'aw-campaign-record', plugins_url( 'assets/css/campaign-record.css', AW_CORE_DIR . 'animal-writes-core.php' ), $deps, (string) filemtime( AW_CORE_DIR . 'assets/css/campaign-record.css' ) );
	},
	30
);

/* ---------- WP Admin: Real-world impact ---------- */
add_action(
	'add_meta_boxes_aw_campaign',
	fn() => add_meta_box( 'aw-campaign-record', 'Real-world impact', 'aw_camp_record_box', 'aw_campaign', 'side', 'default' )
);

function aw_camp_record_box( $post ) {
	wp_nonce_field( 'aw_camp_record', 'aw_camp_record_nonce' );
	$cur = (string) get_post_meta( $post->ID, 'aw_campaign_impact_state', true );
	echo '<p><label for="aw-camp-impact">State</label><br><select id="aw-camp-impact" name="aw_campaign_impact_state" style="width:100%"><option value="">—</option>';
	foreach ( AW_CAMP_IMPACT as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="aw-camp-evidence">Evidence (item ID)</label><br><input type="number" id="aw-camp-evidence" name="aw_campaign_impact_evidence" value="' . esc_attr( (string) get_post_meta( $post->ID, 'aw_campaign_impact_evidence', true ) ) . '" style="width:100%"></p>';
	echo '<p class="description">Verified change only with evidence that the intended change happened (policy, a court decision, practice or conditions); No change (verified) when the evidence shows it did not. Never results (letters, events, coverage). Metrics: aw_campaign_metrics (campaign-record.php).</p>';
}

add_action(
	'save_post_aw_campaign',
	function ( $id ) {
		if ( ! isset( $_POST['aw_camp_record_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_camp_record_nonce'] ) ), 'aw_camp_record' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$imp = sanitize_key( wp_unslash( $_POST['aw_campaign_impact_state'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_impact_state', isset( AW_CAMP_IMPACT[ $imp ] ) ? $imp : '' );
		update_post_meta( $id, 'aw_campaign_impact_evidence', absint( $_POST['aw_campaign_impact_evidence'] ?? 0 ) );
	}
);
