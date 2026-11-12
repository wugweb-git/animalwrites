<?php
/**
 * Names and site icons of the referring sources (Top referrers, AI referrals, the Pages columns).
 *
 * The icon comes from the publishers' icons (aw_favicon_url() in Kits Core) when the host is a known publisher, else
 * from option aw_si_ref_icons (host => attachment ID, 0 = tried and none found). That option fills on Kits Core's hourly
 * aw_fetch_favicons event, five hosts a run: Google's favicon service, then the host's /favicon.ico, saved once into
 * the Media Library as "Favicon: host". Nothing is fetched while a page is drawn; until then a core dashicon shows.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_REF_ICONS = 'aw_si_ref_icons';

/**
 * The site a GA4 source stands for: "ig" and l.instagram.com are Instagram, t.co is X, and so on. '' when the source is
 * not a site (direct, a campaign name).
 *
 * @param string $src GA4 session source.
 */
function aw_si_ref_host( string $src ): string {
	$src   = strtolower( trim( $src ) );
	$alias = array(
		'ig'               => 'instagram.com',
		'instagram'        => 'instagram.com',
		'l.instagram.com'  => 'instagram.com',
		'facebook'         => 'facebook.com',
		'm.facebook.com'   => 'facebook.com',
		'l.facebook.com'   => 'facebook.com',
		'lm.facebook.com'  => 'facebook.com',
		't.co'             => 'x.com',
		'twitter.com'      => 'x.com',
		'linkedin'         => 'linkedin.com',
		'lnkd.in'          => 'linkedin.com',
		'reddit'           => 'reddit.com',
		'out.reddit.com'   => 'reddit.com',
		'whatsapp'         => 'whatsapp.com',
		'wa.me'            => 'whatsapp.com',
		'web.whatsapp.com' => 'whatsapp.com',
		'telegram'         => 'telegram.org',
		't.me'             => 'telegram.org',
		'l.threads.com'    => 'threads.com',
		'l.threads.net'    => 'threads.com',
	);
	if ( isset( $alias[ $src ] ) ) {
		return $alias[ $src ];
	}
	$host = preg_replace( '/^www\./', '', $src );
	return preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', (string) $host ) ? (string) $host : '';
}

/**
 * The icon of a source as HTML (16 px, decorative: the name is printed beside it).
 *
 * @param string $src GA4 session source.
 */
function aw_si_ref_icon( string $src ): string {
	$host = aw_si_ref_host( $src );
	$url  = '' !== $host && function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
	if ( '' === $url && '' !== $host ) {
		$id  = (int) ( ( (array) get_option( AW_SI_REF_ICONS, array() ) )[ $host ] ?? 0 );
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
	}
	if ( '' === $url ) {
		return '<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>';
	}
	return '<img src="' . esc_url( $url ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">';
}

/**
 * The name a source is shown under: one name per site, so "ig" and l.instagram.com are both Instagram. A site without a
 * name of its own shows its host; a source that is not a site (direct, a campaign name) shows as GA4 gives it.
 *
 * @param string $src GA4 session source.
 */
function aw_si_referrer_label( string $src ): string {
	$host  = aw_si_ref_host( $src );
	$names = array(
		'instagram.com'         => 'Instagram',
		'facebook.com'          => 'Facebook',
		'x.com'                 => 'X',
		'linkedin.com'          => 'LinkedIn',
		'reddit.com'            => 'Reddit',
		'whatsapp.com'          => 'WhatsApp',
		'telegram.org'          => 'Telegram',
		'threads.com'           => 'Threads',
		'youtube.com'           => 'YouTube',
		'linktr.ee'             => 'Linktree',
		'chatgpt.com'           => 'ChatGPT',
		'chat.openai.com'       => 'ChatGPT',
		'perplexity.ai'         => 'Perplexity',
		'gemini.google.com'     => 'Gemini',
		'copilot.microsoft.com' => 'Copilot',
		'claude.ai'             => 'Claude',
		'google.com'            => 'Google',
		'bing.com'              => 'Bing',
		'duckduckgo.com'        => 'DuckDuckGo',
	);
	$names = (array) apply_filters( 'aw_si_referrer_names', $names );
	if ( '' === $host ) {
		return trim( $src );
	}
	return (string) ( $names[ $host ] ?? $host );
}

/**
 * Rows of one source each, merged into one row per name (aw_si_referrer_label()): the number fields are added up, the
 * earliest "since" is kept. Order: as the first row of each name came.
 *
 * @param object[] $rows   Rows with a txt field.
 * @param string[] $fields Number fields to add up.
 * @return object[] Rows with txt = the source of the biggest row and label = the name.
 */
function aw_si_ref_merge( array $rows, array $fields ): array {
	$out = array();
	foreach ( $rows as $r ) {
		$label = aw_si_referrer_label( (string) $r->txt );
		$key   = strtolower( $label );
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ]        = clone $r;
			$out[ $key ]->label = $label;
			continue;
		}
		foreach ( $fields as $f ) {
			$out[ $key ]->$f = (float) $out[ $key ]->$f + (float) $r->$f;
		}
		if ( isset( $r->since ) && (string) $r->since < (string) $out[ $key ]->since ) {
			$out[ $key ]->since = $r->since;
		}
	}
	return array_values( $out );
}

/**
 * The two names that sent most, each with its icon, as HTML (the Pages columns).
 *
 * @param array $by Source => sessions.
 */
function aw_si_ref_top( array $by ): string {
	$n   = array();
	$src = array();
	foreach ( $by as $s => $v ) {
		$label         = aw_si_referrer_label( (string) $s );
		$n[ $label ]   = ( $n[ $label ] ?? 0 ) + (int) $v;
		$src[ $label ] = $src[ $label ] ?? (string) $s;
	}
	arsort( $n );
	$out = array();
	foreach ( array_slice( array_keys( $n ), 0, 2 ) as $label ) {
		$out[] = aw_si_ref_icon( $src[ $label ] ) . ' ' . esc_html( (string) $label );
	}
	return implode( ', ', $out );
}

/**
 * A source with its icon and its name, as HTML.
 *
 * @param string $src GA4 session source.
 */
function aw_si_ref_html( string $src ): string {
	return aw_si_ref_icon( $src ) . ' ' . esc_html( aw_si_referrer_label( $src ) );
}

/** The hosts the Overview shows (the 90 days of referral and AI rows) that have no icon yet and were not tried. */
function aw_si_ref_icons_wanted(): array {
	global $wpdb;
	if ( ! function_exists( 'aw_si_ready' ) || ! aw_si_ready() ) {
		return array();
	}
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-90 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$srcs = (array) $wpdb->get_col( $wpdb->prepare( "SELECT txt FROM {$o} WHERE src = 'ga4' AND metric IN ('ref', 'ai') AND period = 'day' AND d >= %s GROUP BY txt ORDER BY SUM(value) DESC LIMIT 60", $since ) );
	$have = (array) get_option( AW_SI_REF_ICONS, array() );
	$want = array();
	foreach ( $srcs as $src ) {
		$host = aw_si_ref_host( (string) $src );
		if ( '' !== $host && ! array_key_exists( $host, $have ) && ( ! function_exists( 'aw_favicon_url' ) || '' === aw_favicon_url( $host ) ) ) {
			$want[ $host ] = true;
		}
	}
	return array_keys( $want );
}

// Kits Core's hourly favicon run: also fetch the icons of the referring sites (five a run at most).
add_action(
	'aw_fetch_favicons',
	function () {
		$want = array_slice( aw_si_ref_icons_wanted(), 0, 5 );
		if ( ! $want ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$have  = (array) get_option( AW_SI_REF_ICONS, array() );
		$types = array(
			'image/png'                => 'png',
			'image/x-icon'             => 'ico',
			'image/vnd.microsoft.icon' => 'ico',
			'image/jpeg'               => 'jpg',
			'image/gif'                => 'gif',
			'image/webp'               => 'webp',
		);
		foreach ( $want as $host ) {
			$att = 0;
			foreach ( array( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $host ), 'https://' . $host . '/favicon.ico' ) as $from ) {
				$tmp = download_url( $from, 15 );
				if ( is_wp_error( $tmp ) ) {
					continue;
				}
				$type = wp_get_image_mime( $tmp );
				if ( ! $type || filesize( $tmp ) < 100 ) {
					wp_delete_file( $tmp );
					continue;
				}
				$id = media_handle_sideload(
					array(
						'name'     => 'favicon-' . sanitize_title( $host ) . '.' . ( $types[ $type ] ?? 'png' ),
						'tmp_name' => $tmp,
					),
					0,
					'Favicon: ' . $host
				);
				if ( is_wp_error( $id ) ) {
					wp_delete_file( $tmp );
					continue;
				}
				update_post_meta( $id, '_aw_favicon_host', $host );
				$att = (int) $id;
				break;
			}
			$have[ $host ] = $att;
		}
		update_option( AW_SI_REF_ICONS, $have, false );
	},
	30
);
