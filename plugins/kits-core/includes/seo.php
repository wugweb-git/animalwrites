<?php
/**
 * Meta description, Open Graph and Twitter card tags.
 * Skipped automatically if a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) is active.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether another plugin already prints these tags.
 *
 * @return bool
 */
function aw_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/**
 * Cut text at a word boundary to at most $max characters, adding "…" when cut.
 *
 * @param string $text Plain text.
 * @param int    $max  Maximum length.
 * @return string
 */
function kits_seo_cut( $text, $max ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $max - 1 );
	$sp  = mb_strrpos( $cut, ' ' );
	if ( false !== $sp && $sp > $max * 0.6 ) {
		$cut = mb_substr( $cut, 0, $sp );
	}
	return rtrim( $cut, " \t,;:–—-" ) . '…';
}

/**
 * The page's own intro: its first meaningful paragraph(s), skipping boilerplate ("Last updated…", short labels and
 * button text), headings and shortcodes. Empty when the page has no such paragraph.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function kits_seo_intro( $post ) {
	$content = (string) $post->post_content;
	$skip    = (string) apply_filters( 'kits_seo_intro_skip', '/^(last\s+(updated|reviewed|modified)|updated( on)?|effective( from| date)?|published( on)?|date)\b/i' );
	if ( false === strpos( $content, '<!-- wp:' ) && false === stripos( $content, '<p' ) ) {
		return trim( wp_strip_all_tags( strip_shortcodes( $content ) ) ); // plain text: all of it
	}
	$out = '';
	if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#si', strip_shortcodes( $content ), $m ) ) {
		foreach ( $m[1] as $para ) {
			$t = trim( html_entity_decode( wp_strip_all_tags( $para ), ENT_QUOTES, 'UTF-8' ) );
			if ( mb_strlen( $t ) < 40 || preg_match( $skip, $t ) ) {
				continue;
			}
			$out = trim( $out . ' ' . $t );
			if ( mb_strlen( $out ) >= 120 ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * Description for the current view.
 *
 * @return string
 */
function aw_seo_description() {
	$desc = function_exists( 'kits_seo_override' ) ? kits_seo_override( 'kits_seo_desc' ) : '';
	if ( '' !== $desc ) {
		return $desc; // written by hand: used as is
	}
	if ( is_singular() ) {
		$post = get_queried_object();
		$desc = has_excerpt( $post ) ? $post->post_excerpt : kits_seo_intro( $post );
	} elseif ( is_post_type_archive() ) {
		$descs = (array) apply_filters( 'kits_seo_archive_descriptions', array() );
		$desc = $descs[ get_query_var( 'post_type' ) ] ?? '';
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$desc = term_description() ?: single_term_title( '', false ) . ' on ' . get_bloginfo( 'name' ) . '.';
	}
	$desc = (string) apply_filters( 'kits_seo_description', $desc ); // a site can describe its own views (e.g. a directory place)
	if ( '' === trim( $desc ) ) {
		$desc = get_bloginfo( 'description' );
	}
	return kits_seo_cut( html_entity_decode( wp_strip_all_tags( $desc ), ENT_QUOTES, 'UTF-8' ), 155 );
}

/**
 * Canonical URL of the current view; og:url uses it, so the two always match.
 * A site with its own canonical rules (paginated lists, place pages) answers through the kits_seo_canonical filter.
 *
 * @return string
 */
function kits_seo_canonical() {
	$url = (string) apply_filters( 'kits_seo_canonical', '' );
	if ( '' !== $url ) {
		return $url;
	}
	if ( is_singular() ) {
		$c = wp_get_canonical_url();
		return $c ? $c : (string) get_permalink();
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return home_url( user_trailingslashit( $path ) );
}

/**
 * [url, width, height] of an attachment at share size, or null.
 *
 * @param int $att Attachment ID.
 * @return array|null
 */
function kits_seo_attachment( $att ) {
	$src = $att ? wp_get_attachment_image_src( (int) $att, 'large' ) : false;
	if ( ! $src || empty( $src[0] ) ) {
		return null;
	}
	if ( empty( $src[1] ) || empty( $src[2] ) ) {
		$meta = wp_get_attachment_metadata( (int) $att );
		$src[1] = (int) ( $meta['width'] ?? 0 );
		$src[2] = (int) ( $meta['height'] ?? 0 );
	}
	return array( 'id' => (int) $att, 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
}

/** The site's default share image (Settings), as kits_seo_attachment(). */
function kits_seo_default_image() {
	return kits_seo_attachment( (int) get_option( 'aw_default_share_image' ) );
}

/**
 * The image of an item, from the site's own media library only: the share image chosen in the SEO box, the featured
 * image, then what the site supplies (kits_seo_image_id). Never a remote URL.
 *
 * @param int $id Post ID.
 * @return array|null
 */
function kits_seo_item_image( $id ) {
	$cands = array(
		(int) get_post_meta( $id, 'kits_og_image', true ),
		(int) get_post_thumbnail_id( $id ),
		(int) apply_filters( 'kits_seo_image_id', 0, $id ),
	);
	foreach ( $cands as $att ) {
		$img = kits_seo_attachment( $att );
		if ( $img ) {
			return $img;
		}
	}
	return null;
}

/**
 * The share image of the current view (og:image and article schema): the item's own stored image when it is landscape,
 * else the site share image, else the site icon. [url, width, height] or null.
 *
 * @return array|null
 */
function kits_seo_image() {
	static $img = false;
	if ( false !== $img ) {
		return $img;
	}
	$img = is_singular() ? kits_seo_item_image( get_queried_object_id() ) : null;
	if ( $img && $img['height'] > $img['width'] && ! get_post_meta( get_queried_object_id(), 'kits_og_image', true ) ) {
		$img = null; // portrait: share cards are landscape, so the site image is shared instead (the page keeps its photo)
	}
	if ( ! $img ) {
		$img = kits_seo_default_image();
	}
	if ( ! $img ) {
		$img = kits_seo_attachment( (int) get_option( 'site_icon' ) );
	}
	return $img;
}

/* ---------- Long headlines: shorter <title> ---------- */

add_filter(
	'document_title_parts',
	function ( $parts ) {
		$types = (array) apply_filters( 'kits_seo_short_title_types', array() );
		if ( ! $types || ! is_singular( $types ) || empty( $parts['title'] ) ) {
			return $parts;
		}
		$t = trim( html_entity_decode( wp_strip_all_tags( $parts['title'] ), ENT_QUOTES, 'UTF-8' ) );
		if ( mb_strlen( $t ) > 55 ) {
			unset( $parts['site'], $parts['tagline'] ); // the headline alone
			$parts['title'] = kits_seo_cut( $t, 64 );
		}
		return $parts;
	},
	99
);

/* ---------- Every published item has a slug ---------- */

// wp_publish_post() and direct status changes do not make a slug; an item published that way had an empty post_name.
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'publish' !== $new || '' !== (string) $post->post_name || ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) || 'attachment' === $post->post_type ) {
			return;
		}
		$base = sanitize_title( $post->post_title );
		$base = '' !== $base ? $base : $post->post_type . '-' . $post->ID;
		if ( function_exists( 'aw_clean_news_slug' ) && function_exists( 'kits_sourced_types' ) && in_array( $post->post_type, kits_sourced_types(), true ) ) {
			$base = aw_clean_news_slug( $base, $post->ID );
		}
		$slug = wp_unique_post_slug( $base, $post->ID, 'publish', $post->post_type, (int) $post->post_parent );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $post->ID );
		$post->post_name = $slug;
	},
	1,
	3
);

// Guard: lists, search and the sitemap on the site never show an item without a slug.
add_filter(
	'posts_where',
	function ( $where, $q ) {
		if ( is_admin() || $q->is_singular() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return $where;
		}
		$status = (array) $q->get( 'post_status' );
		if ( $status && array( '' ) !== $status && ! in_array( 'publish', $status, true ) ) {
			return $where; // drafts and other non-public lists
		}
		global $wpdb;
		return $where . " AND {$wpdb->posts}.post_name <> ''";
	},
	10,
	2
);

add_action(
	'wp_head',
	function () {
		if ( aw_seo_plugin_active() || is_404() ) {
			return;
		}
		$desc  = aw_seo_description();
		$title = wp_get_document_title();
		$url   = kits_seo_canonical();
		$img   = kits_seo_image();
		$type  = is_singular( (array) apply_filters( 'kits_seo_article_types', array( 'post' ) ) ) ? 'article' : 'website';
		$large = $img && $img['width'] >= 600;
		$tags  = array(
			'<meta name="description" content="%s">'         => $desc,
			'<meta property="og:site_name" content="%s">'    => get_bloginfo( 'name' ),
			'<meta property="og:type" content="%s">'         => $type,
			'<meta property="og:title" content="%s">'        => ( function_exists( 'kits_seo_override' ) && kits_seo_override( 'kits_og_title' ) ) ? kits_seo_override( 'kits_og_title' ) : $title,
			'<meta property="og:description" content="%s">'  => ( function_exists( 'kits_seo_override' ) && kits_seo_override( 'kits_og_desc' ) ) ? kits_seo_override( 'kits_og_desc' ) : $desc,
			'<meta property="og:url" content="%s">'          => $url,
			'<meta property="og:image" content="%s">'        => $img ? $img['url'] : '',
			'<meta property="og:image:width" content="%s">'  => $img && $img['width'] ? (string) $img['width'] : '',
			'<meta property="og:image:height" content="%s">' => $img && $img['height'] ? (string) $img['height'] : '',
			'<meta property="og:locale" content="%s">'       => (string) apply_filters( 'kits_seo_locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) ),
			'<meta name="twitter:card" content="%s">'        => $large ? 'summary_large_image' : 'summary',
			'<meta name="twitter:site" content="%s">'        => (string) apply_filters( 'kits_seo_twitter_site', '' ),
		);
		echo '<!-- ' . esc_html( get_bloginfo( 'name' ) ) . " SEO -->\n";
		foreach ( $tags as $format => $value ) {
			if ( $value ) {
				printf( $format . "\n", esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	},
	2
);

/* ---------- Settings → SEO: search engine verification (Bing Webmaster Tools, Yandex Webmaster) ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'SEO',
			'SEO',
			'manage_options',
			'kits-seo',
			function () {
				if ( isset( $_POST['kits_seo_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kits_seo_nonce'] ) ), 'kits_seo' ) && current_user_can( 'manage_options' ) ) {
					foreach ( array( 'kits_verify_bing', 'kits_verify_yandex' ) as $k ) {
						update_option( $k, preg_replace( '/[^A-Za-z0-9_\-]/', '', kits_verify_code( wp_unslash( $_POST[ $k ] ?? '' ) ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					}
					echo '<div class="notice notice-success"><p>Saved.</p></div>';
				}
				echo '<div class="wrap"><h1>SEO</h1><form method="post">' . wp_nonce_field( 'kits_seo', 'kits_seo_nonce', true, false ) . '<table class="form-table">';
				foreach ( array( 'kits_verify_bing' => array( 'Bing Webmaster Tools', 'msvalidate.01' ), 'kits_verify_yandex' => array( 'Yandex Webmaster', 'yandex-verification' ) ) as $k => $f ) {
					echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $f[0] ) . '</label></th><td><input class="regular-text code" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) get_option( $k ) ) . '"><p class="description">Paste the code, or the whole &lt;meta name="' . esc_html( $f[1] ) . '"&gt; tag.</p></td></tr>';
				}
				echo '</table>';
				submit_button();
				echo '</form></div>';
			}
		);
	}
);

/** The code from a pasted value: a bare code, or the content of a pasted <meta> tag. */
function kits_verify_code( $raw ) {
	$raw = trim( (string) $raw );
	return preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', $raw, $m ) ? $m[1] : $raw;
}

add_action(
	'wp_head',
	function () {
		foreach ( array( 'kits_verify_bing' => 'msvalidate.01', 'kits_verify_yandex' => 'yandex-verification' ) as $k => $name ) {
			$code = (string) get_option( $k );
			if ( '' !== $code && ( is_front_page() ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $code ) . '">' . "\n";
			}
		}
	},
	2
);
