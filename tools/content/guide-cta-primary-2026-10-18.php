<?php
/**
 * Guide calls to action: Brand (yellow) → Primary (ink) (owner, 18 Oct 2026: "Primary (dark)").
 *
 * In every post whose "Guide call to action" group (aw-guide-cta) holds a button styled is-style-kits-primary (the old
 * name, which renders as Brand) or is-style-kits-brand, the button loses that style and becomes the default, Primary.
 * Only the button inside the aw-guide-cta group changes; the rest of the post is left as written. A revision is saved.
 *
 * Run on the server: wp eval-file guide-cta-primary-2026-10-18.php dry|apply
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode = $args[0] ?? 'dry';
global $wpdb;
$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision') AND post_status IN ('publish','draft','private','pending','future') AND post_content LIKE '%aw-guide-cta%'" );

$changed = 0;
foreach ( $ids as $id ) {
	$old = (string) get_post_field( 'post_content', $id );
	$new = preg_replace_callback(
		'#<!-- wp:group [^\n]*aw-guide-cta[^\n]*-->.*?<!-- /wp:buttons --></div>\s*<!-- /wp:group -->#s',
		function ( $m ) {
			$g = $m[0];
			$g = str_replace( array( '<!-- wp:button {"className":"is-style-kits-primary"} -->', '<!-- wp:button {"className":"is-style-kits-brand"} -->' ), '<!-- wp:button -->', $g );
			$g = str_replace( array( '<div class="wp-block-button is-style-kits-primary">', '<div class="wp-block-button is-style-kits-brand">' ), '<div class="wp-block-button">', $g );
			return $g;
		},
		$old
	);
	if ( $new === $old ) {
		continue;
	}
	++$changed;
	WP_CLI::log( sprintf( '%s %d %s', 'apply' === $mode ? 'changed' : 'would change', $id, get_the_title( $id ) ) );
	if ( 'apply' === $mode ) {
		wp_update_post( wp_slash( array( 'ID' => (int) $id, 'post_content' => $new ) ) );
	}
}
WP_CLI::success( sprintf( '%d of %d posts with a guide call to action %s.', $changed, count( $ids ), 'apply' === $mode ? 'switched to Primary' : 'would switch (dry run)' ) );
