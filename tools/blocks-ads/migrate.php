<?php
/**
 * One-off (owner, 12 Oct 2026): blocks in three inserter groups (System / Ads / Sponsored) and preview-only setup.
 * Run once on the server after the code is deployed and the database is backed up:
 *   wp eval-file wp-content/plugins/../aw_deploy/tools/blocks-ads/migrate.php        (path as deployed)
 * Idempotent: a second run changes nothing.
 *
 * 1. Synced patterns ("System"): Volunteer banner, Need help now, Helplines strip, Newsletter box, Claim your listing.
 *    The first two wrap the theme patterns, so their output is unchanged; the other three reuse the footer's markup
 *    and labels. Every synced pattern gets the "System" pattern category.
 * 2. Pages that use the volunteer banner or "Need help now" pattern point at the synced pattern instead (same HTML;
 *    checked here before saving). Hub page hashes follow, so hubs.php still treats them as unedited.
 * 3. Ad slot blocks: Home (top = where the [aw_ad slot="home"] shortcode was, middle, bottom) and the Resources page.
 * 4. Cached counts cleared (the sample listing leaves them).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$log = function ( $m ) {
	echo $m . "\n"; // phpcs:ignore
};

/* 1. Synced patterns */
$footer = (string) file_get_contents( get_stylesheet_directory() . '/parts/footer.html' );
preg_match( '#<ul class="kit-footer-helplines">.*?</ul>#s', $footer, $m );
$helplines = $m[0] ?? '';
$dark      = '{"align":"full","className":"kit-dark kit-footer","backgroundColor":"secondary","textColor":"background","layout":{"type":"constrained","contentSize":"1200px"}}';
$dark_cls  = 'wp-block-group alignfull kit-dark kit-footer has-background-color has-secondary-background-color has-text-color has-background';
$system    = array(
	'volunteer-banner' => array( 'Volunteer banner', '<!-- wp:pattern {"slug":"animal-writes/volunteer-banner"} /-->' ),
	'help-now'         => array( 'Need help now', '<!-- wp:pattern {"slug":"animal-writes/help-now"} /-->' ),
	'helplines'        => array(
		'Helplines strip',
		'<!-- wp:group ' . $dark . ' --><div class="' . $dark_cls . '"><!-- wp:html --><nav class="kit-footer-helprow" aria-label="Helplines"><p class="kit-footer-head"><span class="kit-ico kit-ico--helpline" aria-hidden="true"></span>Helplines</p>' . $helplines . '</nav><!-- /wp:html --></div><!-- /wp:group -->',
	),
	'newsletter'       => array(
		'Newsletter box',
		'<!-- wp:group ' . $dark . ' --><div class="' . $dark_cls . '"><!-- wp:html --><section class="kit-footer-newsletter" aria-label="Newsletter"><p class="kit-footer-nl-head">Newsletter</p>[aw_newsletter]</section><!-- /wp:html --></div><!-- /wp:group -->',
	),
	'claim'            => array(
		'Claim your listing',
		'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/claim/">Claim your listing</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	),
);
$made = (array) get_option( 'aw_system_blocks', array() );
foreach ( $system as $key => $s ) {
	if ( ! empty( $made[ $key ] ) && get_post( (int) $made[ $key ] ) ) {
		continue;
	}
	$id = wp_insert_post( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => $s[0], 'post_content' => $s[1] ), true );
	if ( is_wp_error( $id ) ) {
		$log( 'FAILED ' . $s[0] . ': ' . $id->get_error_message() );
		continue;
	}
	$made[ $key ] = (int) $id;
	$log( "synced pattern {$s[0]}: $id" );
}
update_option( 'aw_system_blocks', $made, false );

if ( ! term_exists( 'system', 'wp_pattern_category' ) ) {
	wp_insert_term( 'System', 'wp_pattern_category', array( 'slug' => 'system' ) );
}
foreach ( get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $bid ) {
	wp_set_object_terms( $bid, 'system', 'wp_pattern_category', true );
}
$log( 'System category on every synced pattern' );

/* 2 + 3. Pages */
$norm = function ( $html ) {
	$html = preg_replace( '#<aside class="kit-ad.*?</aside>#s', '', $html ); // ad slots count per request
	return preg_replace( '/(aw-local|wp-block|aw-nl|kit-dd)-[a-z-]*\d+/', '$1-N', $html );
};
$hashes = (array) get_option( 'aw_hub_hashes', array() );
$hubs   = (array) get_option( 'aw_hub_pages', array() );
$change = array(
	'<!-- wp:pattern {"slug":"animal-writes/volunteer-banner"} /-->' => '<!-- wp:block {"ref":' . (int) $made['volunteer-banner'] . '} /-->',
	'<!-- wp:pattern {"slug":"animal-writes/help-now"} /-->'         => '<!-- wp:block {"ref":' . (int) $made['help-now'] . '} /-->',
);
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $p ) {
	$old = $p->post_content;
	$new = strtr( $old, $change );
	$home = 'home' === $p->post_name;
	$res  = 'resources' === $p->post_name;
	if ( $new !== $old && $norm( do_blocks( $old ) ) !== $norm( do_blocks( $new ) ) ) {
		$log( "SKIPPED {$p->post_name}: output would change" );
		continue;
	}
	if ( $home && false === strpos( $new, 'wp:kits/ad-slot' ) ) {
		$new = str_replace( "<!-- wp:shortcode -->\n[aw_ad slot=\"home\"]\n<!-- /wp:shortcode -->", '<!-- wp:kits/ad-slot {"slot":"home-top"} /-->', $new );
		$new = str_replace( '<!-- wp:pattern {"slug":"animal-writes/learn"} /-->', "<!-- wp:pattern {\"slug\":\"animal-writes/learn\"} /-->\n\n<!-- wp:kits/ad-slot {\"slot\":\"home-mid\"} /-->", $new );
		$vb  = $change['<!-- wp:pattern {"slug":"animal-writes/volunteer-banner"} /-->'];
		$new = str_replace( $vb, "<!-- wp:kits/ad-slot {\"slot\":\"home-bottom\"} /-->\n\n" . $vb, $new );
	}
	if ( $res && false === strpos( $new, 'wp:kits/ad-slot' ) ) {
		$new = rtrim( $new ) . "\n\n<!-- wp:kits/ad-slot {\"slot\":\"resources-hub\"} /-->\n";
	}
	if ( $new === $old ) {
		continue;
	}
	wp_update_post( array( 'ID' => $p->ID, 'post_content' => $new ) );
	foreach ( $hubs as $type => $h ) {
		if ( (int) ( $h['page'] ?? 0 ) === $p->ID && md5( $old ) === ( $hashes[ 'g' . $type ] ?? '' ) ) {
			$hashes[ 'g' . $type ] = md5( get_post_field( 'post_content', $p->ID ) );
		}
	}
	$log( "page {$p->post_name} ({$p->ID}) updated" );
}
update_option( 'aw_hub_hashes', $hashes, false );

/* 4. Cached counts (the preview IPs are set in Settings > Ads) */
if ( function_exists( 'aw_place_counts_reset' ) ) {
	aw_place_counts_reset();
}
delete_transient( 'aw_verified_count' );
$log( 'done' );
