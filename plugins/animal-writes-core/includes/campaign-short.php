<?php
/**
 * Campaign short links (owner, 2 Nov 2026: "each campaign to have a dedicated short link — tap and you are on the
 * page"). A campaign's meta aw_campaign_short holds one path segment (lab, diwali, sc-dogs...); /{short} and /{short}/
 * answer with a 301 to the campaign's page while the campaign is published. The share buttons and Copy link hand out
 * the short link; canonical and og:url stay the full address.
 *
 * Only an address that WordPress cannot resolve is redirected (is_404() at template_redirect), so a page, post, term
 * or archive at the same address always wins, and so does a Rank Math redirection (it runs earlier, on wp). No rewrite
 * rules, so nothing to flush. The field (Letters and share box, campaign-tools.php) refuses a slug that clashes with a
 * page, a rewrite base or another campaign's short link.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A short link value as stored: lower-case letters, digits and hyphens, at most 40 characters; '' otherwise.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function aw_camp_short_clean( $value ) {
	$s = substr( trim( sanitize_title( (string) $value ), '-' ), 0, 40 );
	return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s ) ? $s : '';
}

/**
 * The published campaign that owns this short link, or 0.
 *
 * @param string $short Short link slug.
 * @param int    $not   A campaign to leave out (the one being saved).
 * @return int
 */
function aw_camp_by_short( $short, $not = 0 ) {
	$ids = get_posts(
		array(
			'post_type'        => 'aw_campaign',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'post__not_in'     => $not ? array( (int) $not ) : array(),
			'meta_key'         => 'aw_campaign_short', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $short, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * The campaign's short link (https://animalwrites.in/lab), or '' when it has none or is not published.
 *
 * @param int $id Campaign ID.
 * @return string
 */
function aw_camp_short_url( $id ) {
	$s = aw_camp_short_clean( get_post_meta( $id, 'aw_campaign_short', true ) );
	return $s && 'publish' === get_post_status( $id ) ? home_url( '/' . $s ) : '';
}

/**
 * Why this short link can't be used ('' when it can): a page at that address, the first part of a rewrite base
 * (campaigns, directory, news...), a WordPress path, or another campaign's short link.
 *
 * @param string $short Short link slug.
 * @param int    $id    The campaign being saved.
 * @return string
 */
function aw_camp_short_clash( $short, $id ) {
	$reserved = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login', 'feed', 'page', 'search', 'embed', 'comments', 'trackback', 'author', 'category', 'tag', 'sitemap', 'sitemap_index' );
	foreach ( array_merge( get_post_types( array( 'public' => true ), 'objects' ), get_taxonomies( array( 'public' => true ), 'objects' ) ) as $obj ) {
		if ( ! empty( $obj->rewrite['slug'] ) ) {
			$reserved[] = strtok( trim( (string) $obj->rewrite['slug'], '/' ), '/' );
		}
		if ( ! empty( $obj->has_archive ) && is_string( $obj->has_archive ) ) {
			$reserved[] = strtok( trim( $obj->has_archive, '/' ), '/' );
		}
	}
	if ( in_array( $short, $reserved, true ) ) {
		return 'it is the start of an existing address';
	}
	$page = get_page_by_path( $short, OBJECT, array( 'page', 'attachment' ) );
	if ( $page && 'trash' !== $page->post_status ) {
		return 'a page already has that address';
	}
	if ( aw_camp_by_short( $short, $id ) ) {
		return 'another campaign uses it';
	}
	return '';
}

// /{short} or /{short}/ to the campaign, keeping utm tags but not click ids; never cached (302, no-cache). GET and HEAD only; before WordPress's own
// canonical redirect (10) and the 410 for removed items (not-found.php, 99).
add_action(
	'template_redirect',
	function () {
		if ( ! is_404() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return;
		}
		$path = trim( (string) ( $GLOBALS['wp']->request ?? '' ), '/' );
		if ( '' === $path || aw_camp_short_clean( $path ) !== $path ) {
			return;
		}
		$id = aw_camp_by_short( $path );
		if ( ! $id ) {
			return;
		}
		$to    = (string) get_permalink( $id );
		$query = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		parse_str( $query, $args );
		// Click ids (fbclid, gclid...) are per person: never pass them on. utm tags stay.
		$args = array_diff_key( array_filter( $args, 'is_string' ), array_flip( array( 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'igshid', 'ttclid', 'twclid', 'li_fat_id', 'mc_eid', 'yclid', 'dclid' ) ) );
		if ( $args ) {
			$to = add_query_arg( array_map( 'rawurlencode', $args ), $to );
		}
		// The redirect depends on the query string, which the page cache may leave out of its key (LiteSpeed
		// ignores fbclid), so it must never be cached: a cached copy sent everyone one visitor's click id.
		do_action( 'litespeed_control_set_nocache', 'campaign short link redirect' );
		nocache_headers();
		wp_safe_redirect( $to, 302, 'Animal Writes' );
		exit;
	},
	1
);

// Saved with the campaign's other fields (same nonce as the Details save, campaign-tools.php). A clash keeps the old
// value and says why.
add_action(
	'save_post_aw_campaign',
	function ( $id ) {
		if ( ! isset( $_POST['aw_camp_details_nonce'], $_POST['aw_campaign_short'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_camp_details_nonce'] ), 'aw_camp_details' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$raw   = sanitize_text_field( wp_unslash( $_POST['aw_campaign_short'] ) );
		$short = aw_camp_short_clean( $raw );
		if ( '' === $short ) {
			delete_post_meta( $id, 'aw_campaign_short' );
			if ( '' !== trim( $raw ) ) {
				set_transient( 'aw_camp_short_notice_' . get_current_user_id(), 'Short link not saved: use letters, digits and hyphens.', 60 );
			}
			return;
		}
		$why = aw_camp_short_clash( $short, $id );
		if ( $why ) {
			set_transient( 'aw_camp_short_notice_' . get_current_user_id(), sprintf( 'Short link /%s not saved: %s.', $short, $why ), 60 );
			return;
		}
		update_post_meta( $id, 'aw_campaign_short', $short );
	},
	10
);

add_action(
	'admin_notices',
	function () {
		$key = 'aw_camp_short_notice_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}
);
