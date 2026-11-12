<?php
/**
 * Server tab, daily checks (owner, 3 Nov 2026), in place of numbers typed from hPanel. Nothing is entered by hand.
 *
 * - Speed: PageSpeed Insights performance (0–100) of the home page, mobile and desktop, once a day, through the same
 *   free call as the weekly run (psi.php: Site Kit's module, else the public API with the free AW_PSI_KEY). Each
 *   strategy is its own cron event, as one call can take up to 40 seconds.
 * - AI visibility: the site asks itself, once a day, for the items hPanel scores. Score = items passed / items × 100.
 *   1. robots.txt has a Content-Signal line.
 *   2. robots.txt does not block the AI crawlers (their own group, else User-agent: *).
 *   3. The home page answers Markdown to Accept: text/markdown.
 *   4. The site script with the WebMCP tools is on the home page.
 *   5. The home page's JSON-LD decodes and has one Organization node with the site's @id, name and URL.
 *   6. llms.txt answers with text.
 *   The home page is asked with a query string, so the CDN's cached copy is not the one checked.
 * - Stored in option aw_si_srv_checks (Ymd => speed, ai), every day kept.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_CHK_SPEED = 'aw_si_srv_speed';
const AW_SI_CHK_AI    = 'aw_si_srv_ai';

/**
 * Store one day's result.
 *
 * @param string $part  speed or ai.
 * @param array  $value What to keep.
 */
function aw_si_chk_put( string $part, array $value ): void {
	$all = get_option( 'aw_si_srv_checks', array() );
	$all = is_array( $all ) ? $all : array();
	$ymd = wp_date( 'Ymd' );

	$all[ $ymd ]          = (array) ( $all[ $ymd ] ?? array() );
	$all[ $ymd ][ $part ] = 'speed' === $part ? array_merge( (array) ( $all[ $ymd ]['speed'] ?? array() ), $value ) : $value;
	krsort( $all );
	update_option( 'aw_si_srv_checks', $all, false );
}

/** The daily step: the checks, each in its own run a few minutes apart. */
function aw_si_chk_daily(): void {
	if ( ! wp_next_scheduled( AW_SI_CHK_AI ) ) {
		wp_schedule_single_event( time() + 60, AW_SI_CHK_AI );
	}
	foreach ( array(
		'mobile'  => 180,
		'desktop' => 480,
	) as $strategy => $in ) {
		if ( ! wp_next_scheduled( AW_SI_CHK_SPEED, array( $strategy ) ) ) {
			wp_schedule_single_event( time() + $in, AW_SI_CHK_SPEED, array( $strategy ) );
		}
	}
}
add_action( AW_SI_CRON_DAILY, 'aw_si_chk_daily', 22 );
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_CHK_SPEED, AW_SI_CHK_AI ) ) );

add_action(
	AW_SI_CHK_SPEED,
	function ( $strategy ) {
		$strategy = 'desktop' === $strategy ? 'desktop' : 'mobile';
		if ( ! function_exists( 'aw_si_psi_call' ) ) {
			return;
		}
		$fig = aw_si_psi_call( '/', $strategy );
		aw_si_chk_put( 'speed', array( $strategy => is_wp_error( $fig ) ? null : (int) $fig['score'] ) );
	}
);

/**
 * A page of the site, fresh (not the CDN's copy).
 *
 * @param string $path   Path.
 * @param array  $headers Request headers.
 * @param bool   $fresh  Add a query string.
 * @return array{code:int,type:string,body:string}
 */
function aw_si_chk_fetch( string $path, array $headers = array(), bool $fresh = false ): array {
	$url = home_url( $path );
	$url = $fresh ? add_query_arg( 'aw_check', time(), $url ) : $url;
	$r   = wp_remote_get(
		$url,
		array(
			'timeout' => 20,
			'headers' => $headers,
		)
	);
	return array(
		'code' => is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ),
		'type' => is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'content-type' ),
		'body' => is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r ),
	);
}

/**
 * Whether robots.txt blocks a crawler from the whole site (its own group, else User-agent: *).
 *
 * @param string $txt robots.txt.
 * @param string $bot Crawler name.
 */
function aw_si_chk_robots_blocks( string $txt, string $bot ): bool {
	$groups = array();
	$agents = array();
	$rules  = false;
	foreach ( preg_split( '/\R/', $txt ) as $line ) {
		$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
		if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
			continue;
		}
		$k = strtolower( $m[1] );
		if ( 'user-agent' === $k ) {
			if ( $rules ) {
				$agents = array();
				$rules  = false;
			}
			$agents[] = strtolower( trim( $m[2] ) );
			continue;
		}
		$rules = true;
		foreach ( $agents as $a ) {
			$groups[ $a ][] = array( $k, trim( $m[2] ) );
		}
	}
	$use = $groups[ strtolower( $bot ) ] ?? ( $groups['*'] ?? array() );
	foreach ( $use as $r ) {
		if ( 'disallow' === $r[0] && '/' === $r[1] ) {
			return true;
		}
	}
	return false;
}

add_action(
	AW_SI_CHK_AI,
	function () {
		$robots = aw_si_chk_fetch( '/robots.txt' );
		$home   = aw_si_chk_fetch( '/', array(), true );
		$md     = aw_si_chk_fetch( '/', array( 'Accept' => 'text/markdown' ), true );
		$llms   = aw_si_chk_fetch( '/llms.txt' );

		$blocked = array();
		foreach ( array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot-Extended' ) as $bot ) {
			if ( 200 !== $robots['code'] || aw_si_chk_robots_blocks( $robots['body'], $bot ) ) {
				$blocked[] = $bot;
			}
		}

		$webmcp = false;
		if ( preg_match( '#<script[^>]+src="([^"]*/assets/js/aw\.js[^"]*)"#', $home['body'], $m ) ) {
			$js     = wp_remote_get( html_entity_decode( $m[1] ), array( 'timeout' => 20 ) );
			$js     = is_wp_error( $js ) ? '' : (string) wp_remote_retrieve_body( $js );
			$webmcp = false !== strpos( $js, 'modelContext' ) && false !== strpos( $js, 'registerTool' );
		}

		$org = false;
		if ( preg_match_all( '#<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>#s', $home['body'], $mm ) ) {
			$ok    = true;
			$found = 0;
			foreach ( $mm[1] as $block ) {
				$d = json_decode( $block, true );
				if ( ! is_array( $d ) ) {
					$ok = false;
					continue;
				}
				foreach ( isset( $d['@graph'] ) ? (array) $d['@graph'] : array( $d ) as $node ) {
					$type = (array) ( $node['@type'] ?? array() );
					if ( in_array( 'Organization', $type, true ) && home_url( '/#organization' ) === ( $node['@id'] ?? '' ) && ! empty( $node['name'] ) && ! empty( $node['url'] ) ) {
						++$found;
					}
				}
			}
			$org = $ok && 1 === $found;
		}

		$items = array(
			'content_signal' => 200 === $robots['code'] && (bool) preg_match( '/^\s*Content-Signal\s*:/mi', $robots['body'] ),
			'ai_allowed'     => ! $blocked,
			'markdown'       => 200 === $md['code'] && 0 === stripos( $md['type'], 'text/markdown' ) && 0 === strpos( ltrim( $md['body'] ), '# ' ),
			'webmcp'         => $webmcp,
			'organization'   => $org,
			'llms'           => 200 === $llms['code'] && '' !== trim( $llms['body'] ) && 0 === stripos( $llms['type'], 'text/' ),
		);
		aw_si_chk_put(
			'ai',
			array(
				'items'   => $items,
				'blocked' => $blocked,
				'score'   => (int) round( 100 * count( array_filter( $items ) ) / count( $items ) ),
			)
		);
	}
);

/** Item labels. */
function aw_si_chk_labels(): array {
	return array(
		'content_signal' => __( 'robots.txt has a Content-Signal line', 'insightweb' ),
		'ai_allowed'     => __( 'robots.txt lets the AI crawlers in', 'insightweb' ),
		'markdown'       => __( 'Markdown answered for Accept: text/markdown', 'insightweb' ),
		'webmcp'         => __( 'WebMCP tools on the home page', 'insightweb' ),
		'organization'   => __( 'Organization JSON-LD valid', 'insightweb' ),
		'llms'           => __( 'llms.txt present', 'insightweb' ),
	);
}

/** The checks section of the Server tab: the last 7 days, and the newest AI check item by item. */
function aw_si_chk_section(): void {
	$all = get_option( 'aw_si_srv_checks', array() );
	$all = is_array( $all ) ? $all : array();
	krsort( $all );
	echo '<h2>' . esc_html__( 'Speed and AI visibility', 'insightweb' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Checked every morning: PageSpeed performance of the home page, and the site asking itself for the AI visibility items.', 'insightweb' ) . '</p>';
	if ( ! $all ) {
		echo '<p>' . esc_html__( 'The first check runs tomorrow morning.', 'insightweb' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Day', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Speed, mobile', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Speed, desktop', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'AI visibility', 'insightweb' ) . '</th></tr></thead><tbody>';
	$cell = fn( $v ) => null === $v || '' === $v ? '—' : number_format_i18n( (int) $v );
	foreach ( array_slice( $all, 0, 7, true ) as $ymd => $d ) {
		$t = strtotime( (string) $ymd );
		echo '<tr><td>' . esc_html( $t ? wp_date( 'j M Y', $t ) : (string) $ymd ) . '</td><td>' . esc_html( $cell( $d['speed']['mobile'] ?? null ) ) . '</td><td>' . esc_html( $cell( $d['speed']['desktop'] ?? null ) ) . '</td><td>' . esc_html( $cell( $d['ai']['score'] ?? null ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$last = null;
	foreach ( $all as $d ) {
		if ( isset( $d['ai']['items'] ) ) {
			$last = $d['ai'];
			break;
		}
	}
	if ( $last ) {
		echo '<ul>';
		foreach ( aw_si_chk_labels() as $k => $label ) {
			$pass = ! empty( $last['items'][ $k ] );
			$more = 'ai_allowed' === $k && ! empty( $last['blocked'] ) ? ' (' . implode( ', ', (array) $last['blocked'] ) . ')' : '';
			echo '<li>' . esc_html( ( $pass ? __( 'Pass', 'insightweb' ) : __( 'Fail', 'insightweb' ) ) . ': ' . $label . $more ) . '</li>';
		}
		echo '</ul>';
	}
}
