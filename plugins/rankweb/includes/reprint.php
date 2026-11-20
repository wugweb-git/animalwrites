<?php
/**
 * RankWeb Content Reprint, Syndication & Canonical Attribution Engine.
 *
 * Benchmark: Reprint / Yoast Canonical / Syndication.
 * Provides custom meta boxes and automated canonical URL / attribution management
 * for syndicated and republished articles.
 *
 * @package rankweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register reprint meta box on post edit screens.
 */
add_action(
	'add_meta_boxes',
	function () {
		$screens = array( 'post', 'page', 'aw_press' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'rankweb_reprint_meta',
				__( 'Reprint & Canonical Attribution (RankWeb)', 'rankweb' ),
				'rankweb_render_reprint_box',
				$screen,
				'normal',
				'default'
			);
		}
	}
);

/**
 * Render reprint meta box.
 *
 * @param WP_Post $post Current post.
 */
function rankweb_render_reprint_box( $post ) {
	wp_nonce_field( 'rankweb_reprint_save', 'rankweb_reprint_nonce' );

	$is_reprint   = (bool) get_post_meta( $post->ID, '_rankweb_is_reprint', true );
	$orig_url     = get_post_meta( $post->ID, '_rankweb_orig_url', true );
	$orig_source  = get_post_meta( $post->ID, '_rankweb_orig_source', true );
	$orig_date    = get_post_meta( $post->ID, '_rankweb_orig_date', true );
	$set_canon    = (bool) get_post_meta( $post->ID, '_rankweb_set_canonical', true );
	$append_badge = (bool) get_post_meta( $post->ID, '_rankweb_append_attribution', true );

	echo '<div style="padding:10px 0;">';
	echo '<p><label><input type="checkbox" name="rankweb_is_reprint" value="1" ' . checked( $is_reprint, true, false ) . '> <strong>' . esc_html__( 'This article was originally published elsewhere (Reprint / Syndicated)', 'rankweb' ) . '</strong></label></p>';

	echo '<div id="rankweb_reprint_fields" style="display:' . ( $is_reprint ? 'grid' : 'none' ) . ';grid-template-columns:1fr 1fr;gap:15px;margin-top:12px;background:#f8fafc;padding:15px;border-radius:6px;border:1px solid #e2e8f0;">';

	echo '<div>';
	echo '<label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Original Publication URL', 'rankweb' ) . '</label>';
	echo '<input type="url" name="rankweb_orig_url" value="' . esc_attr( $orig_url ) . '" placeholder="https://example.com/article" style="width:100%;">';
	echo '</div>';

	echo '<div>';
	echo '<label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Original Publisher / Publication Name', 'rankweb' ) . '</label>';
	echo '<input type="text" name="rankweb_orig_source" value="' . esc_attr( $orig_source ) . '" placeholder="The Hindu / Reuters" style="width:100%;">';
	echo '</div>';

	echo '<div>';
	echo '<label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html__( 'Original Publication Date', 'rankweb' ) . '</label>';
	echo '<input type="date" name="rankweb_orig_date" value="' . esc_attr( $orig_date ) . '" style="width:100%;">';
	echo '</div>';

	echo '<div style="display:flex;flex-direction:column;gap:8px;justify-content:center;">';
	echo '<label><input type="checkbox" name="rankweb_set_canonical" value="1" ' . checked( $set_canon, true, false ) . '> ' . esc_html__( 'Set rel="canonical" pointing to the original URL (SEO protection)', 'rankweb' ) . '</label>';
	echo '<label><input type="checkbox" name="rankweb_append_attribution" value="1" ' . checked( $append_badge, true, false ) . '> ' . esc_html__( 'Display formatted attribution badge at end of post', 'rankweb' ) . '</label>';
	echo '</div>';

	echo '</div>';
	echo '</div>';
	?>
	<script>
	jQuery(document).ready(function($){
		$('input[name="rankweb_is_reprint"]').on('change', function(){
			$('#rankweb_reprint_fields').toggle($(this).is(':checked'));
		});
	});
	</script>
	<?php
}

/**
 * Save reprint meta.
 *
 * @param int $post_id Post ID.
 */
add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['rankweb_reprint_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['rankweb_reprint_nonce'] ) ), 'rankweb_reprint_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_reprint   = ! empty( $_POST['rankweb_is_reprint'] );
		$orig_url     = isset( $_POST['rankweb_orig_url'] ) ? esc_url_raw( wp_unslash( $_POST['rankweb_orig_url'] ) ) : '';
		$orig_source  = isset( $_POST['rankweb_orig_source'] ) ? sanitize_text_field( wp_unslash( $_POST['rankweb_orig_source'] ) ) : '';
		$orig_date    = isset( $_POST['rankweb_orig_date'] ) ? sanitize_text_field( wp_unslash( $_POST['rankweb_orig_date'] ) ) : '';
		$set_canon    = ! empty( $_POST['rankweb_set_canonical'] );
		$append_badge = ! empty( $_POST['rankweb_append_attribution'] );

		update_post_meta( $post_id, '_rankweb_is_reprint', $is_reprint ? '1' : '' );
		update_post_meta( $post_id, '_rankweb_orig_url', $orig_url );
		update_post_meta( $post_id, '_rankweb_orig_source', $orig_source );
		update_post_meta( $post_id, '_rankweb_orig_date', $orig_date );
		update_post_meta( $post_id, '_rankweb_set_canonical', $set_canon ? '1' : '' );
		update_post_meta( $post_id, '_rankweb_append_attribution', $append_badge ? '1' : '' );
	}
);

/**
 * Filter canonical URL to point to original source when requested.
 */
add_filter(
	'wpseo_canonical',
	'rankweb_reprint_canonical',
	99
);
add_filter(
	'rank_math/frontend/canonical',
	'rankweb_reprint_canonical',
	99
);
add_filter(
	'get_canonical_url',
	'rankweb_reprint_canonical_core',
	99,
	2
);

/**
 * Canonical filter for Rank Math / Yoast.
 *
 * @param string $canonical Current canonical URL.
 * @return string
 */
function rankweb_reprint_canonical( $canonical ) {
	if ( ! is_singular() ) {
		return $canonical;
	}
	$post_id   = get_the_ID();
	$set_canon = get_post_meta( $post_id, '_rankweb_set_canonical', true );
	$orig_url  = get_post_meta( $post_id, '_rankweb_orig_url', true );
	if ( $set_canon && ! empty( $orig_url ) ) {
		return $orig_url;
	}
	return $canonical;
}

/**
 * Core canonical filter.
 *
 * @param string  $canonical Canonical URL.
 * @param WP_Post $post      Post object.
 * @return string
 */
function rankweb_reprint_canonical_core( $canonical, $post ) {
	if ( ! $post ) {
		return $canonical;
	}
	$set_canon = get_post_meta( $post->ID, '_rankweb_set_canonical', true );
	$orig_url  = get_post_meta( $post->ID, '_rankweb_orig_url', true );
	if ( $set_canon && ! empty( $orig_url ) ) {
		return $orig_url;
	}
	return $canonical;
}

/**
 * Append attribution badge to content.
 *
 * @param string $content Post content.
 * @return string
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! get_post_meta( $post_id, '_rankweb_append_attribution', true ) ) {
			return $content;
		}

		$orig_url    = get_post_meta( $post_id, '_rankweb_orig_url', true );
		$orig_source = get_post_meta( $post_id, '_rankweb_orig_source', true );
		$orig_date   = get_post_meta( $post_id, '_rankweb_orig_date', true );

		if ( empty( $orig_source ) && empty( $orig_url ) ) {
			return $content;
		}

		$source_link = ! empty( $orig_url ) ? '<a href="' . esc_url( $orig_url ) . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( $orig_source ?: $orig_url ) . '</a>' : esc_html( $orig_source );
		/* translators: %s: formatted publication date */
		$date_str    = ! empty( $orig_date ) ? ' ' . sprintf( esc_html__( 'on %s', 'rankweb' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $orig_date ) ) ) ) : '';

		$badge = '<div class="rankweb-reprint-badge" style="margin-top:24px;padding:12px 16px;background:#f8fafc;border-left:4px solid #3b82f6;border-radius:4px;font-size:14px;color:#475569;">'
			/* translators: 1: original source name or link, 2: original publication date */
			. '<em>' . sprintf( wp_kses_post( __( 'This article was originally published by %1$s%2$s and is republished here with attribution.', 'rankweb' ) ), $source_link, $date_str ) . '</em>'
			. '</div>';

		return $content . $badge;
	}
);
