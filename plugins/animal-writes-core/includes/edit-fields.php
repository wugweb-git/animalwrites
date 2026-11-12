<?php
/**
 * Edit fields for values that only WP-CLI could change before (12 Nov 2026). Core form tables and meta box markup, no
 * custom CSS; each value keeps its key and stored shape, and a Save with no change writes nothing.
 *
 * - Category → Edit: Card image (term meta aw_category_card, media.php) and Pinned Resources (aw_pinned, one row per
 *   post ID, pages.php).
 * - Linked campaigns → Edit: Short name (term meta aw_short_name; the older key aw_campaign_short is read when it is
 *   empty, hub-blocks.php).
 * - Library item (Document box): Dated (aw_legal_dated, item-head.php).
 * - Campaign (Outcome box): Metrics (aw_campaign_metrics JSON, campaign-record.php), one line per metric.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Category: card image and pinned Resources ---------- */

add_action(
	'category_edit_form_fields',
	function ( $term ) {
		wp_nonce_field( 'aw_edit_fields_cat', 'aw_edit_fields_cat_nonce' );
		$card = (int) get_term_meta( $term->term_id, 'aw_category_card', true );
		$pins = array_map( 'intval', (array) get_term_meta( $term->term_id, 'aw_pinned', false ) );
		printf(
			'<tr class="form-field"><th scope="row"><label for="aw_category_card">%1$s</label></th><td><input type="number" min="0" class="small-text" id="aw_category_card" name="aw_category_card" value="%2$s" aria-describedby="aw_category_card-help"> %3$s<p class="description" id="aw_category_card-help">%4$s</p></td></tr>',
			esc_html( 'Card image' ),
			esc_attr( $card ? (string) $card : '' ),
			$card ? wp_kses_post( wp_get_attachment_image( $card, array( 60, 34 ) ) ) : '',
			esc_html( 'Optional. Media ID of the picture cards in this category use when an item has none of its own. Empty: the category image.' )
		);
		printf(
			'<tr class="form-field"><th scope="row"><label for="aw_pinned">%1$s</label></th><td><input type="text" class="regular-text" id="aw_pinned" name="aw_pinned" value="%2$s" aria-describedby="aw_pinned-help"><p class="description" id="aw_pinned-help">%3$s</p></td></tr>',
			esc_html( 'Pinned Resources' ),
			esc_attr( implode( ', ', $pins ) ),
			esc_html( 'Resource IDs, comma separated, in order: shown first in this category\'s Resources (up to six). Only published Resources show.' )
		);
	},
	15
);

add_action(
	'edited_category',
	function ( $term_id ) {
		if ( ! isset( $_POST['aw_edit_fields_cat_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_edit_fields_cat_nonce'] ) ), 'aw_edit_fields_cat' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$card = absint( wp_unslash( $_POST['aw_category_card'] ?? 0 ) );
		$now  = (int) get_term_meta( $term_id, 'aw_category_card', true );
		if ( $card !== $now ) {
			$card && wp_attachment_is_image( $card ) ? update_term_meta( $term_id, 'aw_category_card', $card ) : delete_term_meta( $term_id, 'aw_category_card' );
		}
		$pins = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', sanitize_text_field( wp_unslash( $_POST['aw_pinned'] ?? '' ) ) ) ) ) ) );
		$pins = array_values( array_filter( $pins, fn( $p ) => 'post' === get_post_type( $p ) ) );
		if ( array_map( 'intval', (array) get_term_meta( $term_id, 'aw_pinned', false ) ) !== $pins ) {
			delete_term_meta( $term_id, 'aw_pinned' );
			foreach ( $pins as $p ) {
				add_term_meta( $term_id, 'aw_pinned', $p );
			}
		}
	}
);

/* ---------- Linked campaigns: short name ---------- */

add_action(
	'aw_campaign_link_edit_form_fields',
	function ( $term ) {
		wp_nonce_field( 'aw_edit_fields_camp', 'aw_edit_fields_camp_nonce' );
		printf(
			'<tr class="form-field"><th scope="row"><label for="aw_term_short_name">%1$s</label></th><td><input type="text" id="aw_term_short_name" name="aw_term_short_name" value="%2$s" aria-describedby="aw_term_short_name-help"><p class="description" id="aw_term_short_name-help">%3$s</p></td></tr>',
			esc_html( 'Short name' ),
			esc_attr( aw_edit_fields_term_short( $term->term_id ) ),
			esc_html( 'The campaign\'s name on chips and in emails about its items. Empty: the full name.' )
		);
	},
	5
);

/**
 * A linked campaign's short name as the site reads it: aw_short_name, else the older aw_campaign_short.
 *
 * @param int $term_id Term ID.
 */
function aw_edit_fields_term_short( $term_id ): string {
	$short = (string) get_term_meta( $term_id, 'aw_short_name', true );
	return '' !== $short ? $short : (string) get_term_meta( $term_id, 'aw_campaign_short', true );
}

add_action(
	'edited_aw_campaign_link',
	function ( $term_id ) {
		if ( ! isset( $_POST['aw_edit_fields_camp_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_edit_fields_camp_nonce'] ) ), 'aw_edit_fields_camp' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$v = sanitize_text_field( wp_unslash( $_POST['aw_term_short_name'] ?? '' ) );
		if ( aw_edit_fields_term_short( $term_id ) === $v ) {
			return; // unchanged: neither key is touched
		}
		if ( '' === $v ) {
			delete_term_meta( $term_id, 'aw_short_name' );
			delete_term_meta( $term_id, 'aw_campaign_short' ); // emptied on purpose: the full name shows
			return;
		}
		update_term_meta( $term_id, 'aw_short_name', $v ); // read before the older key
	}
);

/* ---------- Library item: Dated ---------- */

add_action(
	'acf/render_field/key=field_aw_legal_msg_item',
	function () {
		global $post;
		if ( ! $post instanceof WP_Post || 'aw_legal' !== $post->post_type ) {
			return;
		}
		wp_nonce_field( 'aw_edit_fields_dated', 'aw_edit_fields_dated_nonce' );
		printf(
			'<p><label for="aw-legal-dated">%1$s</label><br><input type="date" id="aw-legal-dated" name="aw_legal_dated" value="%2$s"></p>',
			esc_html( 'Dated (the document\'s own date, when it differs from its publication date). Empty: none' ),
			esc_attr( (string) get_post_meta( $post->ID, 'aw_legal_dated', true ) )
		);
	},
	20
);

add_action(
	'save_post_aw_legal',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['aw_edit_fields_dated_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_edit_fields_dated_nonce'] ) ), 'aw_edit_fields_dated' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$v   = sanitize_text_field( wp_unslash( $_POST['aw_legal_dated'] ?? '' ) );
		$now = (string) get_post_meta( $post_id, 'aw_legal_dated', true );
		if ( $v === $now ) {
			return;
		}
		if ( '' === $v ) {
			delete_post_meta( $post_id, 'aw_legal_dated' );
		} elseif ( aw_edit_fields_is_day( $v ) ) {
			update_post_meta( $post_id, 'aw_legal_dated', $v );
		}
	}
);

/**
 * Whether a text is a real day as Y-m-d.
 *
 * @param string $v Text.
 */
function aw_edit_fields_is_day( string $v ): bool {
	$d = DateTime::createFromFormat( '!Y-m-d', $v );
	return $d && $d->format( 'Y-m-d' ) === $v;
}

/* ---------- Campaign: metrics ---------- */

/** The fields of one metric line, in order. */
const AW_EDIT_METRIC_FIELDS = array( 'key', 'label', 'value', 'count', 'threshold', 'verified', 'stale_days', 'attribution', 'source_label', 'source_url' );

/**
 * A campaign's metrics as lines: "key | label | value | count | threshold | verified | stale days | attribution |
 * source | source link".
 *
 * @param int $id Campaign ID.
 */
function aw_edit_fields_metric_lines( $id ): string {
	$list  = json_decode( (string) get_post_meta( $id, 'aw_campaign_metrics', true ), true );
	$lines = array();
	foreach ( is_array( $list ) ? $list : array() as $m ) {
		$lines[] = implode( ' | ', array_map( fn( $k ) => (string) ( ( (array) $m )[ $k ] ?? '' ), AW_EDIT_METRIC_FIELDS ) );
	}
	return implode( "\n", $lines );
}

/**
 * Lines back to the metric list, or null when a line cannot be read.
 *
 * @param string $text Posted lines.
 * @return array|null
 */
function aw_edit_fields_metric_list( string $text ) {
	$out = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) > 10 ) {
			return null;
		}
		$m = array_combine( AW_EDIT_METRIC_FIELDS, array_pad( $p, 10, '' ) );
		if ( '' === sanitize_key( $m['key'] ) || ( '' !== $m['verified'] && ! aw_edit_fields_is_day( $m['verified'] ) ) || ( '' !== $m['source_url'] && ! wp_http_validate_url( $m['source_url'] ) ) ) {
			return null;
		}
		$row = array();
		foreach ( AW_EDIT_METRIC_FIELDS as $k ) {
			if ( '' === $m[ $k ] ) {
				continue; // a field left empty is not stored, as before
			}
			if ( in_array( $k, array( 'threshold', 'stale_days' ), true ) ) {
				$row[ $k ] = (int) $m[ $k ];
			} elseif ( 'count' === $k ) {
				$row[ $k ] = 0 + (float) $m[ $k ];
			} elseif ( 'source_url' === $k ) {
				$row[ $k ] = esc_url_raw( $m[ $k ] );
			} else {
				$row[ $k ] = 'key' === $k ? sanitize_key( $m[ $k ] ) : sanitize_text_field( $m[ $k ] );
			}
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * The Metrics field, inside the Outcome box after Real-world impact (campaign-record.php aw_camp_record_box()).
 *
 * @param WP_Post $post Campaign.
 */
function aw_edit_fields_metrics_box( $post ): void {
	wp_nonce_field( 'aw_edit_fields_metrics', 'aw_edit_fields_metrics_nonce' );
	printf(
		'<p><label for="aw-camp-metrics">%1$s</label><br><textarea class="large-text code" rows="4" wrap="off" spellcheck="false" id="aw-camp-metrics" name="aw_campaign_metrics_lines">%2$s</textarea></p><p class="description">%3$s</p>',
		esc_html( 'Metrics' ),
		esc_textarea( aw_edit_fields_metric_lines( $post->ID ) ),
		esc_html( 'One per line: key | label | value | count | threshold | verified (YYYY-MM-DD) | stale after days | attribution | source | source link. A metric shows only once verified, and only when its count reaches the threshold. A line that cannot be read keeps all metrics as they were.' )
	);
}

add_action(
	'save_post_aw_campaign',
	function ( $id ) {
		if ( wp_is_post_revision( $id ) || ! isset( $_POST['aw_edit_fields_metrics_nonce'], $_POST['aw_campaign_metrics_lines'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_edit_fields_metrics_nonce'] ) ), 'aw_edit_fields_metrics' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$text = str_replace( "\r\n", "\n", (string) wp_unslash( $_POST['aw_campaign_metrics_lines'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is cleaned in aw_edit_fields_metric_list()
		if ( trim( $text ) === trim( aw_edit_fields_metric_lines( $id ) ) ) {
			return; // unchanged: the stored JSON stays byte for byte
		}
		$list = aw_edit_fields_metric_list( $text );
		if ( null === $list ) {
			return; // a line could not be read: nothing changes
		}
		$list ? update_post_meta( $id, 'aw_campaign_metrics', wp_slash( (string) wp_json_encode( $list ) ) ) : delete_post_meta( $id, 'aw_campaign_metrics' );
	},
	20
);
