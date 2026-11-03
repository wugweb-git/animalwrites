<?php
/**
 * Social posts (owner's plan, 1 Nov 2026; go 2 Nov 2026): other people's public posts about a campaign or an item
 * (activists' posts, reels, videos), kept as records a person reviews. Type aw_social, listed under News.
 *
 * Switches (Animal Writes → Modules, both off by default):
 * - aw_social_on: the type, its box, its intake (paste a link in Sourcing → To review, `wp aw social-import`, the
 *   workers' routes) and the campaign's hashtag box. Off: the type is not registered and nothing shows; records are
 *   kept; the routes answer 409 aw_social_off.
 * - aw_social_show: the "What people are saying" block on linked pages (owner's OK of the design and label first).
 *
 * Not public on its own: no single pages, no archive, not in any sitemap (public false, publicly_queryable false,
 * rewrite false), so nothing to index. Every record lands as pending; a person publishes it in Sourcing → To review.
 * Nothing is ever published automatically. A post's own text is never our summary: the summary is written by staff; the
 * text from an import is kept only in a read-only staff field (aw_social_text) and never shown on the site.
 *
 * Fields (one box, SCF group_aw_social): link (unique, duplicates refused), platform, person, handle, post date, summary,
 * format, numbers at import (views, reposts, likes, replies, date seen), poster image, related News / Library / listing
 * items. Campaigns: the same aw_campaign_link terms campaign items use (campaign-links.php). Each campaign: its hashtags
 * and search strings (aw_social_terms, SCF group_aw_campaign_social), read by the weekly collection and the workers.
 *
 * Routes (the workers' user, as aw/v1/search-insights): POST aw/v1/social/intake, GET aw/v1/social/campaigns.
 * Contract: docs/plugin-map.md ("Social posts routes").
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SOCIAL_TYPE = 'aw_social';

/** Platforms: key => label. */
const AW_SOCIAL_PLATFORMS = array(
	'x'         => 'X',
	'youtube'   => 'YouTube',
	'bluesky'   => 'Bluesky',
	'mastodon'  => 'Mastodon',
	'instagram' => 'Instagram',
	'facebook'  => 'Facebook',
	'linkedin'  => 'LinkedIn',
	'other'     => 'Other',
);

/** Formats: key => label. */
const AW_SOCIAL_FORMATS = array( 'video' => 'Video', 'picture' => 'Picture', 'text' => 'Text' );

/** Most items one intake call or one CSV row batch may carry. */
const AW_SOCIAL_MAX_ITEMS = 200;

/** Whether the Social posts module is on (Animal Writes → Modules). */
function aw_social_on(): bool {
	return '1' === (string) get_option( 'aw_social_on', '' );
}

/** Whether approved social posts show on linked pages (needs the module on too). */
function aw_social_show(): bool {
	return aw_social_on() && '1' === (string) get_option( 'aw_social_show', '' );
}

/* ---------- Animal Writes → Modules ---------- */

add_action(
	'admin_init',
	function () {
		$flag = array( 'type' => 'string', 'default' => '', 'sanitize_callback' => fn( $v ) => $v ? '1' : '', 'show_in_rest' => false );
		register_setting( 'aw_modules', 'aw_social_on', $flag );
		register_setting( 'aw_modules', 'aw_social_show', $flag );
		add_settings_section( 'aw_modules_social', 'Social posts', 'aw_social_settings_intro', 'aw-modules' );
		add_settings_field( 'aw_social_on', 'Social posts type', 'aw_social_settings_field', 'aw-modules', 'aw_modules_social', array( 'label_for' => 'aw_social_on', 'note' => 'The Social posts list under News, its intake (paste a link in Sourcing → To review, the CSV import, the workers\' routes) and the hashtag box on each campaign. Off: hidden; saved records are kept.' ) );
		add_settings_field( 'aw_social_show', 'Show on pages', 'aw_social_settings_field', 'aw-modules', 'aw_modules_social', array( 'label_for' => 'aw_social_show', 'note' => 'Approved social posts as "What people are saying" on the campaign, News, Library and Directory pages they are linked to. Needs the type on.' ) );
	}
);

add_action(
	'admin_menu',
	fn() => add_submenu_page( AW_CORE_MENU, 'Modules', 'Modules', 'manage_options', 'aw-modules', 'aw_modules_screen' ),
	13 // after Contact links (12)
);

/** Section text. */
function aw_social_settings_intro() {
	echo '<p>' . esc_html( 'Other people\'s public posts about a campaign or an item. Every record waits in Sourcing → To review; nothing is published on its own.' ) . '</p>';
}

/**
 * One on/off box.
 *
 * @param array $args label_for (the option), note.
 */
function aw_social_settings_field( $args ) {
	$id = (string) $args['label_for'];
	printf(
		'<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> <span class="description">%3$s</span>',
		esc_attr( $id ),
		checked( '1', (string) get_option( $id, '' ), false ),
		esc_html( (string) $args['note'] )
	);
}

/** The Modules screen (Settings API, core markup). */
function aw_modules_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'animal-writes-core' ), 403 );
	}
	echo '<div class="wrap"><h1>' . esc_html( 'Modules' ) . '</h1>';
	settings_errors( 'general' ); // options.php's "Settings saved." (core prints it only under the Settings menu)
	echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
	settings_fields( 'aw_modules' );
	do_settings_sections( 'aw-modules' );
	submit_button();
	echo '</form></div>';
}

/* ---------- Type, campaign links, meta ---------- */

add_action(
	'init',
	function () {
		if ( ! aw_social_on() ) {
			return;
		}
		register_post_type(
			AW_SOCIAL_TYPE,
			array(
				'labels'              => array(
					'name'               => 'Social posts',
					'singular_name'      => 'Social post',
					'menu_name'          => 'Social posts',
					'all_items'          => 'Social posts',
					'add_new'            => 'Add social post',
					'add_new_item'       => 'Add social post',
					'edit_item'          => 'Edit social post',
					'new_item'           => 'New social post',
					'search_items'       => 'Search social posts',
					'not_found'          => 'No social posts found.',
					'not_found_in_trash' => 'No social posts in Trash.',
				),
				'description'         => 'Other people\'s public posts about a campaign or an item. Shown only inside linked pages.',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=aw_news', // under News, no new top-level menu
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false, // classic edit screen; no public REST listing
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
			)
		);
		if ( defined( 'AW_CAMPAIGN_LINK' ) ) {
			register_taxonomy_for_object_type( AW_CAMPAIGN_LINK, AW_SOCIAL_TYPE ); // the same campaign links campaign items use
		}
	},
	11 // after the campaign link taxonomy (5) and the content types (10)
);

/**
 * Stored keys of a social post: key => [label, kind]. Kinds: url, text, key, date, int, id, ids, longtext.
 */
function aw_social_keys(): array {
	return array(
		'aw_social_url'        => array( 'Post link', 'url' ),
		'aw_social_key'        => array( 'Post key (for duplicates; set by code)', 'text' ),
		'aw_social_platform'   => array( 'Platform', 'key' ),
		'aw_social_person'     => array( 'Person', 'text' ),
		'aw_social_handle'     => array( 'Handle', 'text' ),
		'aw_social_date'       => array( 'Post date', 'date' ),
		'aw_social_summary'    => array( 'Summary (one line, our words)', 'text' ),
		'aw_social_format'     => array( 'Format', 'key' ),
		'aw_social_views'      => array( 'Views at import', 'int' ),
		'aw_social_reposts'    => array( 'Reposts at import', 'int' ),
		'aw_social_likes'      => array( 'Likes at import', 'int' ),
		'aw_social_replies'    => array( 'Replies at import', 'int' ),
		'aw_social_seen_on'    => array( 'Numbers seen on', 'date' ),
		'aw_social_poster'     => array( 'Poster image', 'id' ),
		'aw_social_poster_src' => array( 'Poster source (from the platform; not downloaded)', 'url' ),
		'aw_social_related'    => array( 'Related News, Library and Directory items', 'ids' ),
		'aw_social_text'       => array( 'Post text at import (staff only; never shown)', 'longtext' ),
		'aw_social_hashtags'   => array( 'Hashtags at import (staff only)', 'text' ),
		'aw_social_source'     => array( 'Came in from (link, csv, workers)', 'key' ),
	);
}

/**
 * Sanitise a stored value by its kind.
 *
 * @param mixed  $v    Value.
 * @param string $kind Kind from aw_social_keys().
 */
function aw_social_clean( $v, string $kind ): string {
	$v = is_scalar( $v ) ? trim( (string) $v ) : '';
	switch ( $kind ) {
		case 'url':
			return esc_url_raw( $v, array( 'http', 'https' ) );
		case 'key':
			return sanitize_key( $v );
		case 'date':
			$v = preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $v, $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : $v;
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		case 'int':
			$v = str_replace( array( ',', ' ' ), '', $v );
			return ctype_digit( $v ) ? (string) (int) $v : '';
		case 'id':
			return absint( $v ) ? (string) absint( $v ) : '';
		case 'ids':
			return implode( ',', array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $v ) ) ) ) ), 0, 20 ) );
		case 'longtext':
			return sanitize_textarea_field( $v );
	}
	return sanitize_text_field( $v );
}

/** A campaign's hashtags and search strings: one per line, each a plain string, at most 50 of up to 100 characters. */
function aw_social_terms_clean( $v ): string {
	$lines = preg_split( '/\r\n|\r|\n/', is_scalar( $v ) ? (string) $v : '' );
	$out   = array();
	foreach ( $lines as $line ) {
		$line = mb_substr( trim( sanitize_text_field( $line ) ), 0, 100 );
		if ( '' !== $line && ! in_array( $line, $out, true ) ) {
			$out[] = $line;
		}
	}
	return implode( "\n", array_slice( $out, 0, 50 ) );
}

add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_posts' );
		foreach ( aw_social_keys() as $key => $def ) {
			register_post_meta(
				AW_SOCIAL_TYPE,
				$key,
				array(
					'type'              => 'string',
					'description'       => $def[0],
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => $auth,
					'sanitize_callback' => fn( $v ) => aw_social_clean( $v, $def[1] ),
				)
			);
		}
		register_post_meta(
			'aw_campaign',
			'aw_social_terms',
			array(
				'type'              => 'string',
				'description'       => 'Hashtags and search strings for social posts, one per line (weekly collection and the workers)',
				'single'            => true,
				'show_in_rest'      => false,
				'auth_callback'     => $auth,
				'sanitize_callback' => 'aw_social_terms_clean',
			)
		);
	}
);

/**
 * A campaign's hashtags and search strings as a list.
 *
 * @param int $id Campaign ID.
 * @return string[]
 */
function aw_social_campaign_terms( int $id ): array {
	$v = aw_social_terms_clean( (string) get_post_meta( $id, 'aw_social_terms', true ) );
	return '' === $v ? array() : explode( "\n", $v );
}

/**
 * A campaign's own campaign-link terms: the ones on the campaign itself and the ones whose campaign page it is.
 *
 * @param int $id Campaign ID.
 * @return WP_Term[] Its own term first.
 */
function aw_social_campaign_link_terms( int $id ): array {
	if ( ! defined( 'AW_CAMPAIGN_LINK' ) ) {
		return array();
	}
	$own  = get_terms( array( 'taxonomy' => AW_CAMPAIGN_LINK, 'hide_empty' => false, 'meta_key' => 'aw_campaign_page', 'meta_value' => (string) $id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$out  = array();
	foreach ( array_merge( is_array( $own ) ? $own : array(), function_exists( 'aw_campaign_links' ) ? aw_campaign_links( $id ) : array() ) as $t ) {
		$out[ $t->term_id ] = $t;
	}
	return array_values( $out );
}

// The campaign's hashtag box only while the module is on.
add_action(
	'add_meta_boxes_aw_campaign',
	function () {
		if ( ! aw_social_on() ) {
			remove_meta_box( 'acf-group_aw_campaign_social', 'aw_campaign', 'normal' );
		}
	},
	99 // after SCF adds its boxes
);

/* ---------- Links: platform, key for duplicates ---------- */

/**
 * Read a post link: the clean link, the key that finds duplicates, the platform and (YouTube) the video ID.
 * X and Twitter links are one key per post ID; YouTube one per video; Bluesky one per author and post; other links
 * their host and path (tracking parameters removed).
 *
 * @param string $url A post's link.
 * @return array{url:string,key:string,platform:string,video:string,handle:string} Empty url when it is not a link.
 */
function aw_social_parse_url( string $url ): array {
	$out = array( 'url' => '', 'key' => '', 'platform' => 'other', 'video' => '', 'handle' => '' );
	$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
	$p   = $url ? wp_parse_url( $url ) : false;
	if ( ! $p || empty( $p['host'] ) ) {
		return $out;
	}
	$host = strtolower( preg_replace( '/^(www|m|mobile)\./i', '', (string) $p['host'] ) );
	$path = (string) ( $p['path'] ?? '' );
	parse_str( (string) ( $p['query'] ?? '' ), $q );
	if ( in_array( $host, array( 'x.com', 'twitter.com' ), true ) && preg_match( '#^/([A-Za-z0-9_]{1,15})/status(?:es)?/(\d+)#', $path, $m ) ) {
		return array( 'url' => 'https://x.com/' . $m[1] . '/status/' . $m[2], 'key' => 'x:' . $m[2], 'platform' => 'x', 'video' => '', 'handle' => '@' . $m[1] );
	}
	if ( in_array( $host, array( 'youtube.com', 'youtu.be', 'youtube-nocookie.com', 'music.youtube.com' ), true ) ) {
		$vid = 'youtu.be' === $host ? ltrim( $path, '/' ) : (string) ( $q['v'] ?? '' );
		if ( '' === $vid && preg_match( '#^/(?:shorts|live|embed)/([A-Za-z0-9_-]{6,})#', $path, $m ) ) {
			$vid = $m[1];
		}
		$vid = preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $vid ) ? $vid : '';
		if ( $vid ) {
			$short = false !== strpos( $path, '/shorts/' );
			return array( 'url' => $short ? 'https://www.youtube.com/shorts/' . $vid : 'https://www.youtube.com/watch?v=' . $vid, 'key' => 'youtube:' . $vid, 'platform' => 'youtube', 'video' => $vid, 'handle' => '' );
		}
	}
	if ( 'bsky.app' === $host && preg_match( '#^/profile/([^/]+)/post/([A-Za-z0-9]+)#', $path, $m ) ) {
		$actor = strtolower( rawurldecode( $m[1] ) );
		return array( 'url' => 'https://bsky.app/profile/' . rawurlencode( $actor ) . '/post/' . $m[2], 'key' => 'bluesky:' . $actor . '/' . $m[2], 'platform' => 'bluesky', 'video' => '', 'handle' => 0 === strpos( $actor, 'did:' ) ? '' : '@' . $actor );
	}
	$platform = 'other';
	foreach ( array( 'instagram' => '/(^|\.)instagram\.com$/', 'facebook' => '/(^|\.)(facebook\.com|fb\.watch|fb\.com)$/', 'linkedin' => '/(^|\.)linkedin\.com$/', 'mastodon' => '/(^|\.)(mastodon\.[a-z.]+|mstdn\.[a-z.]+)$/' ) as $k => $re ) {
		if ( preg_match( $re, $host ) ) {
			$platform = $k;
			break;
		}
	}
	// Keep a query only where it names the post (Facebook permalink.php?story_fbid=…&id=…); drop tracking.
	$keep = array_intersect_key( $q, array_flip( array( 'story_fbid', 'id', 'fbid', 'v', 'p' ) ) );
	$keep = 'facebook' === $platform ? $keep : array();
	ksort( $keep );
	$path  = '/' === $path ? '' : rtrim( $path, '/' );
	$clean = 'https://' . ( 'other' === $platform ? (string) $p['host'] : $host ) . $path . ( $keep ? '?' . http_build_query( $keep ) : '' );
	return array( 'url' => esc_url_raw( $clean ), 'key' => 'url:' . $host . $path . ( $keep ? '?' . http_build_query( $keep ) : '' ), 'platform' => $platform, 'video' => '', 'handle' => '' );
}

/**
 * The record that already has this key (any status, Trash included, so a rejected post never comes back).
 *
 * @param string $key     aw_social_key.
 * @param int    $exclude A record to leave out (the one being edited).
 */
function aw_social_find( string $key, int $exclude = 0 ): int {
	if ( '' === $key ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'        => AW_SOCIAL_TYPE,
			'post_status'      => array( 'publish', 'pending', 'draft', 'private', 'future', 'trash' ),
			'meta_key'         => 'aw_social_key', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'           => 'ids',
			'numberposts'      => 2,
			'post__not_in'     => $exclude ? array( $exclude ) : array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams
			'suppress_filters' => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/** An admin title for a record (never shown on the site): "Person (@handle), Platform, 1 Nov 2026". */
function aw_social_title( string $person, string $handle, string $platform, string $date ): string {
	$who  = trim( $person . ( '' !== $handle && $handle !== $person ? ' (' . $handle . ')' : '' ) );
	$bits = array_filter( array( '' !== $who ? $who : 'Unknown', AW_SOCIAL_PLATFORMS[ $platform ] ?? 'Other', $date ? wp_date( 'j M Y', (int) strtotime( $date . ' 12:00:00' ) ) : '' ) );
	return mb_substr( implode( ', ', $bits ), 0, 120 );
}

/* ---------- Auto-fill from the platforms' free embed data (no key) ---------- */

/**
 * GET a JSON document (10 s timeout).
 *
 * @param string $url Address.
 * @return array Decoded body, or [] on any failure.
 */
function aw_social_get_json( string $url ): array {
	$res = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 3, 'headers' => array( 'Accept' => 'application/json' ), 'user-agent' => 'AnimalWrites/2.0 (+' . home_url( '/' ) . ')' ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}
	$d = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return is_array( $d ) ? $d : array();
}

/**
 * What a post's platform says about it, from free sources with no key: X and YouTube oEmbed, Bluesky's public API.
 * The post's own text is not kept here.
 *
 * @param array $link aw_social_parse_url() result.
 * @return array{person?:string,handle?:string,date?:string,format?:string,poster_src?:string,ok:bool}
 */
function aw_social_lookup( array $link ): array {
	$out = array( 'ok' => false );
	if ( 'x' === $link['platform'] ) {
		$d = aw_social_get_json( 'https://publish.twitter.com/oembed?omit_script=1&dnt=true&url=' . rawurlencode( $link['url'] ) );
		if ( $d ) {
			$out['ok']     = true;
			$out['person'] = (string) ( $d['author_name'] ?? '' );
			if ( preg_match( '#(?:twitter|x)\.com/([A-Za-z0-9_]{1,15})/?$#', (string) ( $d['author_url'] ?? '' ), $m ) ) {
				$out['handle'] = '@' . $m[1];
			}
			// The date is the last link of the embed: "— Name (@handle) <a href="…">November 1, 2026</a></blockquote>".
			if ( preg_match_all( '#<a [^>]*>([A-Z][a-z]+ \d{1,2}, \d{4})</a>#', (string) ( $d['html'] ?? '' ), $m ) ) {
				$ts          = strtotime( (string) end( $m[1] ) );
				$out['date'] = $ts ? gmdate( 'Y-m-d', $ts ) : '';
			}
		}
	} elseif ( 'youtube' === $link['platform'] ) {
		$d = aw_social_get_json( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( $link['url'] ) );
		if ( $d ) {
			$out['ok']         = true;
			$out['person']     = (string) ( $d['author_name'] ?? '' );
			$out['format']     = 'video';
			$out['poster_src'] = (string) ( $d['thumbnail_url'] ?? '' );
			if ( preg_match( '#youtube\.com/(@[A-Za-z0-9._-]+)#', (string) ( $d['author_url'] ?? '' ), $m ) ) {
				$out['handle'] = $m[1];
			}
		}
	} elseif ( 'bluesky' === $link['platform'] && preg_match( '#^bluesky:([^/]+)/([A-Za-z0-9]+)$#', $link['key'], $m ) ) {
		$api = 'https://public.api.bsky.app/xrpc/';
		$did = $m[1];
		if ( 0 !== strpos( $did, 'did:' ) ) {
			$did = (string) ( aw_social_get_json( $api . 'com.atproto.identity.resolveHandle?handle=' . rawurlencode( $did ) )['did'] ?? '' );
		}
		$post = $did ? ( aw_social_get_json( $api . 'app.bsky.feed.getPosts?uris=' . rawurlencode( 'at://' . $did . '/app.bsky.feed.post/' . $m[2] ) )['posts'][0] ?? array() ) : array();
		if ( is_array( $post ) && $post ) {
			$out['ok']     = true;
			$out['person'] = (string) ( $post['author']['displayName'] ?? '' );
			$out['handle'] = ! empty( $post['author']['handle'] ) ? '@' . $post['author']['handle'] : '';
			$ts            = strtotime( (string) ( $post['record']['createdAt'] ?? '' ) );
			$out['date']   = $ts ? wp_date( 'Y-m-d', $ts ) : '';
			$embed         = (string) ( $post['embed']['$type'] ?? '' ) . ' ' . (string) ( $post['embed']['media']['$type'] ?? '' );
			$out['format'] = false !== strpos( $embed, 'video' ) ? 'video' : ( false !== strpos( $embed, 'images' ) ? 'picture' : 'text' );
		}
	}
	return $out;
}

/* ---------- Creating records (every intake) ---------- */

/** Platform / format words from imports and workers → our keys. */
function aw_social_format_key( string $v ): string {
	$v = strtolower( trim( $v ) );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/video|reel|clip|short/', $v ) ) {
		return 'video';
	}
	if ( preg_match( '/pic|photo|image|img/', $v ) ) {
		return 'picture';
	}
	return 'text';
}

/**
 * Create one pending social post, or say why not. Duplicates (by key, any status) are refused.
 *
 * @param array  $item   url (required), platform, person, handle, date (Y-m-d or a time), format, campaign (slug or list),
 *                       metrics {views, reposts, likes, replies, seen_on}, related (IDs), text, hashtags, lookup (bool: ask the platform).
 * @param string $source link | csv | workers.
 * @param bool   $dry    Count only; write nothing.
 * @param array  $seen   Keys already met in this batch (duplicates inside one import).
 * @return array{status:string,id:int,url:string,message?:string} status created | would_create | duplicate | invalid.
 */
function aw_social_create( array $item, string $source, bool $dry = false, array &$seen = array() ): array {
	$link = aw_social_parse_url( (string) ( $item['url'] ?? '' ) );
	if ( '' === $link['url'] ) {
		return array( 'status' => 'invalid', 'id' => 0, 'url' => '', 'message' => 'url is missing or not a link' );
	}
	if ( isset( $seen[ $link['key'] ] ) ) {
		return array( 'status' => 'duplicate', 'id' => (int) $seen[ $link['key'] ], 'url' => $link['url'] );
	}
	$dupe = aw_social_find( $link['key'] );
	if ( $dupe ) {
		$seen[ $link['key'] ] = $dupe;
		return array( 'status' => 'duplicate', 'id' => $dupe, 'url' => $link['url'] );
	}
	$seen[ $link['key'] ] = 0;
	if ( $dry ) {
		return array( 'status' => 'would_create', 'id' => 0, 'url' => $link['url'] );
	}

	$found    = ! empty( $item['lookup'] ) ? aw_social_lookup( $link ) : array( 'ok' => false );
	$given    = sanitize_key( (string) ( $item['platform'] ?? '' ) );
	$platform = 'other' !== $link['platform'] ? $link['platform'] : ( isset( AW_SOCIAL_PLATFORMS[ $given ] ) ? $given : 'other' );
	$person   = sanitize_text_field( (string) ( $item['person'] ?? '' ) );
	$person   = '' !== $person ? $person : sanitize_text_field( (string) ( $found['person'] ?? '' ) );
	$handle   = sanitize_text_field( (string) ( $item['handle'] ?? '' ) );
	$handle   = '' !== $handle ? $handle : sanitize_text_field( (string) ( $found['handle'] ?? $link['handle'] ) );
	$handle   = '' !== $handle && '@' !== $handle[0] && ! preg_match( '#[/\s]#', $handle ) ? '@' . $handle : $handle;
	$date     = (string) ( $item['date'] ?? '' );
	if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$ts   = strtotime( $date . ( preg_match( '/[zZ]|[+-]\d{2}:?\d{2}$|UTC|GMT/', $date ) ? '' : ' UTC' ) ); // the CSV's time_utc
		$date = $ts ? wp_date( 'Y-m-d', $ts ) : '';
	}
	$date   = aw_social_clean( '' !== $date ? $date : (string) ( $found['date'] ?? '' ), 'date' );
	$format = aw_social_format_key( (string) ( $item['format'] ?? '' ) );
	$format = '' !== $format ? $format : (string) ( $found['format'] ?? ( 'youtube' === $platform ? 'video' : '' ) );

	$id = wp_insert_post(
		array(
			'post_type'   => AW_SOCIAL_TYPE,
			'post_status' => 'pending', // never published here: a person approves it in Sourcing → To review
			'post_title'  => aw_social_title( $person, $handle, $platform, $date ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return array( 'status' => 'invalid', 'id' => 0, 'url' => $link['url'], 'message' => $id->get_error_message() );
	}
	$m       = is_array( $item['metrics'] ?? null ) ? $item['metrics'] : array();
	$numbers = array();
	foreach ( array( 'views', 'reposts', 'likes', 'replies' ) as $n ) {
		$numbers[ 'aw_social_' . $n ] = aw_social_clean( $m[ $n ] ?? '', 'int' );
	}
	$seen_on = aw_social_clean( (string) ( $m['seen_on'] ?? '' ), 'date' );
	$meta    = array_merge(
		array(
			'aw_social_url'        => $link['url'],
			'aw_social_key'        => $link['key'],
			'aw_social_platform'   => $platform,
			'aw_social_person'     => $person,
			'aw_social_handle'     => $handle,
			'aw_social_date'       => $date,
			'aw_social_format'     => isset( AW_SOCIAL_FORMATS[ $format ] ) ? $format : '',
			'aw_social_poster_src' => (string) ( $found['poster_src'] ?? '' ),
			'aw_social_related'    => implode( ',', array_map( 'absint', (array) ( $item['related'] ?? array() ) ) ),
			'aw_social_text'       => (string) ( $item['text'] ?? '' ), // staff review only; never the summary, never shown
			'aw_social_hashtags'   => (string) ( $item['hashtags'] ?? '' ),
			'aw_social_source'     => $source,
		),
		$numbers,
		array( 'aw_social_seen_on' => '' !== $seen_on ? $seen_on : ( array_filter( $numbers, 'strlen' ) ? wp_date( 'Y-m-d' ) : '' ) )
	);
	foreach ( $meta as $k => $v ) {
		$v = aw_social_clean( $v, aw_social_keys()[ $k ][1] );
		if ( '' !== $v ) {
			update_post_meta( $id, $k, $v );
		}
	}
	// Summary stays empty: staff write it in our words (owner's plan).
	$from = array( 'link' => 'a pasted link', 'csv' => 'the CSV import', 'workers' => 'the workers' );
	update_post_meta( $id, 'aw_review', 'social post from ' . ( $from[ $source ] ?? sanitize_key( $source ) ) ); // "Why it is waiting" in To review
	if ( function_exists( 'aw_set_campaign_links' ) ) {
		aw_set_campaign_links( $id, (array) ( $item['campaign'] ?? array() ) );
	}
	$seen[ $link['key'] ] = (int) $id;
	return array( 'status' => 'created', 'id' => (int) $id, 'url' => $link['url'] );
}

/* ---------- Edit screen: duplicates refused, key and title kept ---------- */

// The post link must be a link and must not be another record's post (SCF shows the message on the field).
add_filter(
	'acf/validate_value/name=aw_social_url',
	function ( $valid, $value ) {
		if ( true !== $valid || ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return $valid;
		}
		$link = aw_social_parse_url( (string) $value );
		if ( '' === $link['url'] ) {
			return 'Use the full link to the post (https://…).';
		}
		$self = absint( wp_unslash( $_POST['post_ID'] ?? ( $_POST['post_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- SCF checks its own nonce before validating
		$dupe = aw_social_find( $link['key'], $self );
		return $dupe ? sprintf( 'This post is already saved: "%1$s" (ID %2$d).', get_the_title( $dupe ), $dupe ) : $valid;
	},
	10,
	2
);

// After SCF saved the box: the clean link, its key, the platform from the link, and a title when there is none.
add_action(
	'acf/save_post',
	function ( $post_id ) {
		static $busy = false;
		if ( $busy || ! is_numeric( $post_id ) || AW_SOCIAL_TYPE !== get_post_type( (int) $post_id ) ) {
			return;
		}
		$id   = (int) $post_id;
		$link = aw_social_parse_url( (string) get_post_meta( $id, 'aw_social_url', true ) );
		if ( '' !== $link['url'] && ! aw_social_find( $link['key'], $id ) ) {
			update_post_meta( $id, 'aw_social_url', $link['url'] );
			update_post_meta( $id, 'aw_social_key', $link['key'] );
			if ( 'other' !== $link['platform'] ) {
				update_post_meta( $id, 'aw_social_platform', $link['platform'] );
			}
		}
		if ( '' === trim( (string) get_post_field( 'post_title', $id ) ) ) {
			$busy = true;
			wp_update_post( array( 'ID' => $id, 'post_title' => aw_social_title( (string) get_post_meta( $id, 'aw_social_person', true ), (string) get_post_meta( $id, 'aw_social_handle', true ), (string) get_post_meta( $id, 'aw_social_platform', true ), (string) get_post_meta( $id, 'aw_social_date', true ) ) ) );
			$busy = false;
		}
	},
	20 // after SCF's own save (10)
);

/* ---------- List screen columns (core) ---------- */

add_filter(
	'manage_aw_social_posts_columns',
	fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Post', 'aw_platform' => 'Platform', 'aw_date' => 'Post date', 'aw_summary' => 'Summary', 'aw_link' => 'Link' ) + array_diff_key( $c, array( 'cb' => 1, 'title' => 1, 'date' => 1 ) ) + array( 'date' => $c['date'] ?? 'Date' )
);
add_action(
	'manage_aw_social_posts_custom_column',
	function ( $col, $id ) {
		switch ( $col ) {
			case 'aw_platform':
				echo esc_html( AW_SOCIAL_PLATFORMS[ (string) get_post_meta( $id, 'aw_social_platform', true ) ] ?? '—' );
				break;
			case 'aw_date':
				echo esc_html( (string) get_post_meta( $id, 'aw_social_date', true ) );
				break;
			case 'aw_summary':
				$s = (string) get_post_meta( $id, 'aw_social_summary', true );
				echo '' !== $s ? esc_html( $s ) : '—';
				break;
			case 'aw_link':
				$u = (string) get_post_meta( $id, 'aw_social_url', true );
				echo $u ? '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener noreferrer">Open<span class="screen-reader-text"> (opens in a new tab)</span></a>' : '';
				break;
		}
	},
	10,
	2
);

/* ---------- Intake (a): paste a link in Sourcing → To review ---------- */

add_action(
	'aw_review_intake',
	function () {
		if ( ! aw_social_on() || ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$done = sanitize_key( wp_unslash( $_GET['aw_social'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a result notice only
		$id   = absint( wp_unslash( $_GET['aw_social_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'created' === $done && $id ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'Social post added as pending: ' ) . '<a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>. ' . esc_html( 'Write its summary, then publish it.' ) . '</p></div>';
		} elseif ( 'duplicate' === $done && $id ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( 'That post is already saved: ' ) . '<a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a> (' . esc_html( (string) get_post_status( $id ) ) . ').</p></div>';
		} elseif ( 'invalid' === $done ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( 'Nothing added: paste the full link to the post (https://…).' ) . '</p></div>';
		}
		$terms = defined( 'AW_CAMPAIGN_LINK' ) ? get_terms( array( 'taxonomy' => AW_CAMPAIGN_LINK, 'hide_empty' => false ) ) : array();
		echo '<h2>' . esc_html( 'Add a social post from its link' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_social_add' );
		echo '<input type="hidden" name="action" value="aw_social_add"><table class="form-table" role="presentation"><tr><th scope="row"><label for="aw-social-url">' . esc_html( 'Post link' ) . '</label></th><td>';
		echo '<input type="url" class="large-text" id="aw-social-url" name="aw_social_url" required placeholder="https://x.com/…/status/…">';
		echo '<p class="description">' . esc_html( 'X, YouTube and Bluesky links fill the person, handle and date from the platform (no key). Other links are saved as they are. It is added as pending; nothing is published.' ) . '</p></td></tr>';
		if ( is_array( $terms ) && $terms ) {
			echo '<tr><th scope="row"><label for="aw-social-campaign">' . esc_html( 'Linked campaign' ) . '</label></th><td><select id="aw-social-campaign" name="aw_social_campaign"><option value="">' . esc_html( '—' ) . '</option>';
			foreach ( $terms as $t ) {
				echo '<option value="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</table>';
		submit_button( 'Add social post', 'secondary', 'submit', false );
		echo '</form><hr>';
	}
);

add_action(
	'admin_post_aw_social_add',
	function () {
		if ( ! aw_social_on() || ! current_user_can( 'edit_others_posts' ) || ! check_admin_referer( 'aw_social_add' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$r    = aw_social_create(
			array(
				'url'      => (string) wp_unslash( $_POST['aw_social_url'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- parsed and cleaned by aw_social_parse_url()
				'campaign' => sanitize_title( wp_unslash( $_POST['aw_social_campaign'] ?? '' ) ),
				'lookup'   => true,
			),
			'link'
		);
		$back = add_query_arg( array( 'aw_social' => $r['status'], 'aw_social_id' => $r['id'] ), admin_url( 'admin.php?page=aw-review&type=' . AW_SOCIAL_TYPE ) );
		wp_safe_redirect( $back );
		exit;
	}
);
add_filter( 'removable_query_args', fn( $args ) => array_merge( $args, array( 'aw_social', 'aw_social_id' ) ) );

/* ---------- Intake (b): WP-CLI CSV import ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Import social posts from the weekly browser collection's CSV as pending records (duplicates by link skipped).
	 * Columns: time_utc,account,post_url,views,reposts,likes,replies,picture_or_video,hashtags,links,text. The text is
	 * kept only in the staff field "Post text at import" (never shown, never the summary).
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : The CSV file.
	 *
	 * [--campaign=<slug>]
	 * : Link every record to this campaign (an aw_campaign_link slug, e.g. campaign-lab-animals-2026).
	 *
	 * [--dry-run]
	 * : Count what would be created; write nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aw social-import lab-x-2026-11-02.csv --campaign=campaign-lab-animals-2026 --dry-run
	 */
	WP_CLI::add_command(
		'aw social-import',
		function ( $args, $assoc ) {
			if ( ! aw_social_on() ) {
				WP_CLI::error( 'Social posts are switched off (Animal Writes → Modules).' );
			}
			$file = (string) ( $args[0] ?? '' );
			if ( '' === $file || ! is_readable( $file ) ) {
				WP_CLI::error( 'CSV file not found: ' . $file );
			}
			$camp = sanitize_title( (string) ( $assoc['campaign'] ?? '' ) );
			if ( '' !== $camp && ( 0 !== strpos( $camp, 'campaign-' ) || ! term_exists( $camp, AW_CAMPAIGN_LINK ) ) ) {
				WP_CLI::error( 'No linked campaign with the slug ' . $camp . ' (wp term list aw_campaign_link).' );
			}
			$dry = ! empty( $assoc['dry-run'] );
			$fh  = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$head = $fh ? fgetcsv( $fh, 0, ',', '"', '\\' ) : false;
			if ( ! $head ) {
				WP_CLI::error( 'The CSV has no header row.' );
			}
			$head = array_map( fn( $h ) => strtolower( trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ), $head );
			if ( ! in_array( 'post_url', $head, true ) ) {
				WP_CLI::error( 'The CSV has no post_url column.' );
			}
			$count = array( 'created' => 0, 'would_create' => 0, 'duplicate' => 0, 'invalid' => 0 );
			$seen  = array();
			$line  = 1;
			while ( false !== ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
				++$line;
				if ( array( null ) === $row ) {
					continue; // blank line
				}
				$c       = array_combine( $head, array_pad( array_slice( $row, 0, count( $head ) ), count( $head ), '' ) );
				$account = trim( (string) ( $c['account'] ?? '' ) );
				$handle  = preg_match( '/@[A-Za-z0-9_.-]+/', $account, $m ) ? $m[0] : '';
				$person  = trim( str_replace( $handle, '', $account ), " \t()·|-" );
				$r       = aw_social_create(
					array(
						'url'      => (string) ( $c['post_url'] ?? '' ),
						'person'   => $person,
						'handle'   => '' !== $handle ? $handle : ( '' !== $account && ! preg_match( '/\s/', $account ) ? $account : '' ),
						'date'     => (string) ( $c['time_utc'] ?? '' ),
						'format'   => (string) ( $c['picture_or_video'] ?? '' ),
						'campaign' => '' !== $camp ? array( $camp ) : array(),
						'metrics'  => array( 'views' => $c['views'] ?? '', 'reposts' => $c['reposts'] ?? '', 'likes' => $c['likes'] ?? '', 'replies' => $c['replies'] ?? '' ),
						'text'     => (string) ( $c['text'] ?? '' ),
						'hashtags' => (string) ( $c['hashtags'] ?? '' ),
					),
					'csv',
					$dry,
					$seen
				);
				++$count[ $r['status'] ];
				if ( 'invalid' === $r['status'] ) {
					WP_CLI::warning( 'Line ' . $line . ': ' . ( $r['message'] ?? 'not imported' ) );
				}
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			WP_CLI::success(
				( $dry ? 'Dry run: ' . $count['would_create'] . ' would be created' : $count['created'] . ' created as pending' )
				. ', ' . $count['duplicate'] . ' already saved, ' . $count['invalid'] . ' not a post link.'
			);
		}
	);
}

/* ---------- Intake (c): the workers' routes ---------- */

/** The workers' user (aw_worker) or an administrator, as aw/v1/search-insights. */
function aw_social_api_allowed(): bool {
	return current_user_can( 'manage_options' ) || current_user_can( 'aw_worker' );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/social/intake',
			array(
				'methods'             => 'POST',
				'callback'            => 'aw_social_rest_intake',
				'permission_callback' => 'aw_social_api_allowed',
			)
		);
		register_rest_route(
			'aw/v1',
			'/social/campaigns',
			array(
				'methods'             => 'GET',
				'callback'            => 'aw_social_rest_campaigns',
				'permission_callback' => 'aw_social_api_allowed',
			)
		);
	}
);

/** The routes answer 409 while the module is off. */
function aw_social_rest_off() {
	return new WP_Error( 'aw_social_off', 'Social posts are switched off on the site.', array( 'status' => 409 ) );
}

/**
 * POST aw/v1/social/intake {"items":[{url, platform, author, handle, date, format, campaign, metrics}], "dry_run": bool}.
 * Creates pending records (never published), skips duplicates, returns counts and one result per item.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response|WP_Error
 */
function aw_social_rest_intake( WP_REST_Request $req ) {
	if ( ! aw_social_on() ) {
		return aw_social_rest_off();
	}
	$items = $req->get_param( 'items' );
	if ( ! is_array( $items ) || ! $items ) {
		return new WP_Error( 'aw_social_items', 'items must be a non-empty list.', array( 'status' => 400 ) );
	}
	if ( count( $items ) > AW_SOCIAL_MAX_ITEMS ) {
		return new WP_Error( 'aw_social_items', 'At most ' . AW_SOCIAL_MAX_ITEMS . ' items per call.', array( 'status' => 400 ) );
	}
	$dry   = rest_sanitize_boolean( $req->get_param( 'dry_run' ) );
	$count = array( 'created' => 0, 'would_create' => 0, 'duplicate' => 0, 'invalid' => 0 );
	$out   = array();
	$seen  = array();
	foreach ( array_values( $items ) as $it ) {
		$it = is_array( $it ) ? $it : array();
		$r  = aw_social_create(
			array(
				'url'      => is_scalar( $it['url'] ?? null ) ? (string) $it['url'] : '',
				'platform' => is_scalar( $it['platform'] ?? null ) ? (string) $it['platform'] : '',
				'person'   => is_scalar( $it['author'] ?? null ) ? (string) $it['author'] : '',
				'handle'   => is_scalar( $it['handle'] ?? null ) ? (string) $it['handle'] : '',
				'date'     => is_scalar( $it['date'] ?? null ) ? (string) $it['date'] : '',
				'format'   => is_scalar( $it['format'] ?? null ) ? (string) $it['format'] : '',
				'campaign' => array_filter( (array) ( $it['campaign'] ?? array() ), 'is_scalar' ),
				'metrics'  => is_array( $it['metrics'] ?? null ) ? array_filter( $it['metrics'], 'is_scalar' ) : array(),
				'text'     => is_scalar( $it['text'] ?? null ) ? sanitize_textarea_field( (string) $it['text'] ) : '',
				'hashtags' => is_array( $it['hashtags'] ?? null ) ? implode( ' ', array_filter( $it['hashtags'], 'is_scalar' ) ) : ( is_scalar( $it['hashtags'] ?? null ) ? (string) $it['hashtags'] : '' ),
				'related'  => array_filter( (array) ( $it['related'] ?? array() ), 'is_scalar' ),
			),
			'workers',
			$dry,
			$seen
		);
		++$count[ $r['status'] ];
		$out[] = $r;
	}
	return new WP_REST_Response( array_merge( array( 'dry_run' => $dry, 'received' => count( $items ) ), $count, array( 'items' => $out ) ), $dry ? 200 : ( $count['created'] ? 201 : 200 ) );
}

/**
 * GET aw/v1/social/campaigns: [{slug, title, terms}] for each published campaign with hashtags or search strings.
 * slug is the campaign's own aw_campaign_link term (to send back as "campaign"); '' when it has none.
 *
 * @return WP_REST_Response|WP_Error
 */
function aw_social_rest_campaigns() {
	if ( ! aw_social_on() ) {
		return aw_social_rest_off();
	}
	$ids = get_posts(
		array(
			'post_type'   => 'aw_campaign',
			'post_status' => 'publish',
			'numberposts' => 100,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => 'aw_social_terms', 'value' => '', 'compare' => '!=' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		$terms = aw_social_campaign_terms( (int) $id );
		if ( ! $terms ) {
			continue;
		}
		$links = aw_social_campaign_link_terms( (int) $id );
		$out[] = array(
			'slug'  => $links ? (string) $links[0]->slug : '',
			'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			'terms' => $terms,
		);
	}
	return new WP_REST_Response( $out, 200 );
}

/* ---------- Display: "What people are saying" ---------- */

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['social-posts'] = array( 'What people are saying', 'aw_block_social_posts', array() );
		return $blocks;
	}
);

/**
 * Approved social posts linked to an item: on a campaign, the ones with its campaign links; on News, Library and
 * Directory items, the ones that name it as related. Newest post first, one per person, at most six.
 *
 * @param int $id Item.
 * @return WP_Post[]
 */
function aw_social_for_item( int $id ): array {
	$type = get_post_type( $id );
	$args = array( 'post_type' => AW_SOCIAL_TYPE, 'post_status' => 'publish', 'numberposts' => 50, 'no_found_rows' => true );
	if ( 'aw_campaign' === $type ) {
		$terms = wp_list_pluck( aw_social_campaign_link_terms( $id ), 'term_id' );
		if ( ! $terms ) {
			return array();
		}
		$args['tax_query'] = array( array( 'taxonomy' => AW_CAMPAIGN_LINK, 'terms' => array_map( 'intval', $terms ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	} elseif ( in_array( $type, array( 'aw_news', 'aw_legal', 'aw_listing' ), true ) ) {
		$args['meta_query'] = array( array( 'key' => 'aw_social_related', 'value' => '(^|,)' . (int) $id . '(,|$)', 'compare' => 'REGEXP' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	} else {
		return array();
	}
	$posts = get_posts( $args );
	usort( $posts, fn( $a, $b ) => strcmp( (string) get_post_meta( $b->ID, 'aw_social_date', true ), (string) get_post_meta( $a->ID, 'aw_social_date', true ) ) );
	$seen = array(); // one card per person: their newest post (owner, 3 Nov 2026)
	$out  = array();
	foreach ( $posts as $p ) {
		$who = strtolower( trim( (string) get_post_meta( $p->ID, 'aw_social_platform', true ) . ':' . ltrim( (string) get_post_meta( $p->ID, 'aw_social_handle', true ), '@' ) ) );
		if ( ':' === $who ) {
			$who = 'id:' . $p->ID;
		}
		if ( isset( $seen[ $who ] ) ) {
			continue;
		}
		$seen[ $who ] = true;
		$out[]        = $p;
	}
	return array_slice( $out, 0, 6 );
}

/**
 * One card: the Kits card (kit-card--block-md, no picture) in the More list: date first, then platform and handle;
 * the person as the title (a link to the post); our summary. X and YouTube posts get a button that loads the post
 * here; nothing from the platform loads before that click.
 *
 * @param WP_Post $p Social post.
 */
function aw_social_card( WP_Post $p ): string {
	$url      = (string) get_post_meta( $p->ID, 'aw_social_url', true );
	$link     = aw_social_parse_url( $url );
	$platform = (string) get_post_meta( $p->ID, 'aw_social_platform', true );
	$person   = (string) get_post_meta( $p->ID, 'aw_social_person', true );
	$handle   = (string) get_post_meta( $p->ID, 'aw_social_handle', true );
	$summary  = (string) get_post_meta( $p->ID, 'aw_social_summary', true );
	$date     = (string) get_post_meta( $p->ID, 'aw_social_date', true );
	$name     = '' !== $person ? $person : ( '' !== $handle ? $handle : ( AW_SOCIAL_PLATFORMS[ $platform ] ?? '' ) );
	$meta     = ( $date && function_exists( 'aw_date' ) ? aw_date( $date . ' 12:00:00' ) : '' ) // the date always first in a meta row (owner)
		. '<span class="kit-meta-item">' . esc_html( 'on ' . ( AW_SOCIAL_PLATFORMS[ $platform ] ?? 'Other' ) ) . '</span>'
		. ( '' !== $handle && $handle !== $name ? '<span class="kit-meta-item">' . esc_html( $handle ) . '</span>' : '' );
	$embed    = '';
	if ( in_array( $link['platform'], array( 'x', 'youtube' ), true ) ) {
		$box   = 'aw-social-embed-' . (int) $p->ID;
		$embed = '<div class="aw-opp-actions"><button type="button" class="kit-btn kit-btn--secondary kit-btn--sm kit-btn--label" data-aw-social-embed="' . esc_attr( $link['platform'] ) . '" data-url="' . esc_url( $link['url'] ) . '" data-id="' . esc_attr( $link['video'] ) . '" data-title="' . esc_attr( $name ) . '" aria-expanded="false" aria-controls="' . esc_attr( $box ) . '"' . ( '' !== $name ? ' aria-label="' . esc_attr( 'Show the post by ' . $name ) . '"' : '' ) . '>' . esc_html( 'Show the post' ) . '</button>'
			. '<div id="' . esc_attr( $box ) . '" style="flex-basis:100%" hidden></div></div>'; // the embed takes the row under the button
		aw_social_embed_script();
	}
	return '<li><article class="kit-card kit-card--block-md aw-social-card"><div class="kit-card-body">'
		. '<p class="kit-card-meta kit-meta-row">' . $meta . '</p>'
		. '<h3 class="kit-card-title"><a href="' . esc_url( $url ) . '" rel="nofollow ugc noopener">' . esc_html( $name ) . '</a></h3>'
		. ( '' !== $summary ? '<p class="kit-card-sum">' . esc_html( $summary ) . '</p>' : '' )
		. $embed . '</div></article></li>';
}

/**
 * The block: approved social posts of the item on its own page; nothing when the display switch is off or none are
 * approved. In the editor: a one-line note of what it shows.
 *
 * @param array $a  Attributes (none).
 * @param int   $id Item (block context or the current post).
 */
function aw_block_social_posts( $a = array(), $id = 0 ) {
	static $done = array();
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'What people are saying: approved social posts linked to this page (Animal Writes → Modules → Show on pages)' ) : '';
	}
	$id = (int) ( $id ? $id : get_the_ID() );
	if ( ! aw_social_show() || ! $id || isset( $done[ $id ] ) || ! is_singular() || get_queried_object_id() !== $id || 'publish' !== get_post_status( $id ) ) {
		return '';
	}
	$posts = aw_social_for_item( $id );
	if ( ! $posts ) {
		return '';
	}
	$done[ $id ] = true;
	$hid         = 'aw-more-social';
	return '<section class="aw-more-group" aria-labelledby="' . $hid . '"><h2 class="aw-more-head has-x-large-font-size" id="' . $hid . '">' . esc_html( 'What people are saying' ) . '</h2>'
		. '<ul class="aw-more-list">' . implode( '', array_map( 'aw_social_card', $posts ) ) . '</ul></section>';
}

// News, Library and Directory pages: the block before the share row, inside the main column (item-head.php's rows).
// A campaign page places the block itself (animal-writes/social-posts in its content), once its design is approved.
add_filter(
	'render_block_animal-writes/item-share',
	function ( $html ) {
		$id = get_queried_object_id();
		if ( ! aw_social_show() || ! function_exists( 'aw_item_here' ) || ! aw_item_here( $id ) || ! in_array( get_post_type( $id ), array( 'aw_news', 'aw_legal', 'aw_listing' ), true ) ) {
			return $html;
		}
		return aw_block_social_posts( array(), $id ) . $html;
	}
);

/**
 * The click-to-load script, once per page, only when a card has an X or YouTube post. X: the widgets script is
 * fetched on the first click. YouTube: the privacy-enhanced player (youtube-nocookie.com) in core's 16:9 embed frame.
 */
function aw_social_embed_script() {
	static $on = false;
	if ( $on ) {
		return;
	}
	$on = true;
	wp_enqueue_style( 'wp-block-embed' ); // core's embed frame (16:9) for the YouTube player
	wp_register_script( 'aw-social-embed', false, array(), AW_CORE_VERSION, true );
	wp_enqueue_script( 'aw-social-embed' );
	wp_add_inline_script(
		'aw-social-embed',
		"(function(){var xs=false;function x(box){if(window.twttr&&window.twttr.widgets){window.twttr.widgets.load(box);return;}if(xs){return;}xs=true;var s=document.createElement('script');s.src='https://platform.twitter.com/widgets.js';s.async=true;s.charset='utf-8';document.head.appendChild(s);}"
		. "document.addEventListener('click',function(e){var b=e.target&&e.target.closest?e.target.closest('button[data-aw-social-embed]'):null;if(!b){return;}var box=document.getElementById(b.getAttribute('aria-controls'));if(!box){return;}"
		. "if(!box.firstChild){var k=b.getAttribute('data-aw-social-embed');if(k==='x'){var q=document.createElement('blockquote');q.className='twitter-tweet';q.setAttribute('data-dnt','true');var a=document.createElement('a');a.href=b.getAttribute('data-url');a.textContent=a.href;q.appendChild(a);box.appendChild(q);box.hidden=false;x(box);}"
		. "else if(k==='youtube'){var f=document.createElement('figure');f.className='wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio';var w=document.createElement('div');w.className='wp-block-embed__wrapper';var i=document.createElement('iframe');i.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(b.getAttribute('data-id'));i.title=b.getAttribute('data-title')||'YouTube';i.width='560';i.height='315';i.setAttribute('allow','encrypted-media; picture-in-picture');i.setAttribute('allowfullscreen','');w.appendChild(i);f.appendChild(w);box.appendChild(f);}}"
		. "var open=b.getAttribute('aria-expanded')==='true';b.setAttribute('aria-expanded',open?'false':'true');box.hidden=open;});})();"
	);
}

/* ---------- Animal Writes → Edit screens map ---------- */

add_filter(
	'aw_edit_screen_boxes',
	function ( $boxes ) {
		$boxes['acf-group_aw_social']          = array( 'title' => 'Social post', 'source' => 'Secure Custom Fields', 'plugin' => 'Animal Writes core', 'purpose' => 'Details', 'file' => 'acf-json/group_aw_social.json; core/social-posts.php', 'screen' => 'post', 'types' => array( AW_SOCIAL_TYPE ), 'runtime' => 'scf', 'notes' => 'Only while Animal Writes → Modules → Social posts type is on. The link must be unique (acf/validate_value).' );
		$boxes['acf-group_aw_campaign_social'] = array( 'title' => 'Social posts', 'source' => 'Secure Custom Fields', 'plugin' => 'Animal Writes core', 'purpose' => 'Sourcing', 'file' => 'acf-json/group_aw_campaign_social.json; core/social-posts.php', 'screen' => 'post', 'types' => array( 'aw_campaign' ), 'runtime' => 'scf', 'notes' => 'Removed from the screen while the Social posts type is off.' );
		return $boxes;
	}
);
add_filter(
	'aw_edit_screen_map',
	function ( $keys ) {
		$file = 'core/social-posts.php';
		foreach ( aw_social_keys() as $key => $def ) {
			$uses = array( array( 'kind' => 'admin', 'where' => 'Social posts list, To review', 'file' => $file ) );
			if ( in_array( $key, array( 'aw_social_url', 'aw_social_platform', 'aw_social_person', 'aw_social_handle', 'aw_social_date', 'aw_social_summary' ), true ) ) {
				$uses[] = array( 'kind' => 'display', 'where' => 'What people are saying card', 'file' => $file );
			}
			if ( 'aw_social_related' === $key ) {
				$uses[] = array( 'kind' => 'display', 'where' => 'which News, Library and Directory pages show the post', 'file' => $file );
			}
			$keys[ $key ] = array( 'store' => 'post_meta', 'label' => $def[0], 'type' => $def[1], 'boxes' => in_array( $key, array( 'aw_social_key', 'aw_social_source', 'aw_social_poster_src' ), true ) ? array() : array( 'acf-group_aw_social' ), 'types' => array( AW_SOCIAL_TYPE ), 'uses' => $uses );
		}
		$keys['aw_social_terms'] = array( 'store' => 'post_meta', 'label' => 'Hashtags and search strings', 'type' => 'lines', 'boxes' => array( 'acf-group_aw_campaign_social' ), 'types' => array( 'aw_campaign' ), 'uses' => array( array( 'kind' => 'sourcing', 'where' => 'GET aw/v1/social/campaigns (workers, weekly collection)', 'file' => $file ) ) );
		return $keys;
	}
);
