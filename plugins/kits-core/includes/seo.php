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
		$desc = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
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
	$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
	return mb_strlen( $desc ) > 160 ? rtrim( mb_substr( $desc, 0, 157 ) ) . '…' : $desc;
}

add_action(
	'wp_head',
	function () {
		if ( aw_seo_plugin_active() || is_404() ) {
			return;
		}
		$desc  = aw_seo_description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		$image = '';
		if ( is_singular() && has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}
		if ( ! $image && is_singular() ) {
			$image = (string) apply_filters( 'kits_seo_fallback_image', '', get_queried_object_id() );
		}
		$og_id = function_exists( 'kits_seo_override' ) ? (int) kits_seo_override( 'kits_og_image' ) : 0;
		if ( $og_id && wp_get_attachment_image_url( $og_id, 'full' ) ) {
			$image = (string) wp_get_attachment_image_url( $og_id, 'full' );
		}
		$large = (bool) $image;
		if ( ! $image ) {
			$default = (int) get_option( 'aw_default_share_image' );
			$image   = $default ? (string) wp_get_attachment_image_url( $default, 'full' ) : '';
			$large   = (bool) $image;
		}
		if ( ! $image ) {
			$image = get_site_icon_url( 512 );
		}
		$type = is_singular( (array) apply_filters( 'kits_seo_article_types', array( 'post' ) ) ) ? 'article' : 'website';
		$tags = array(
			'<meta name="description" content="%s">'         => $desc,
			'<meta property="og:site_name" content="%s">'    => get_bloginfo( 'name' ),
			'<meta property="og:type" content="%s">'         => $type,
			'<meta property="og:title" content="%s">'        => ( function_exists( 'kits_seo_override' ) && kits_seo_override( 'kits_og_title' ) ) ? kits_seo_override( 'kits_og_title' ) : $title,
			'<meta property="og:description" content="%s">'  => ( function_exists( 'kits_seo_override' ) && kits_seo_override( 'kits_og_desc' ) ) ? kits_seo_override( 'kits_og_desc' ) : $desc,
			'<meta property="og:url" content="%s">'          => $url,
			'<meta property="og:image" content="%s">'        => $image,
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
