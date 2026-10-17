<?php
/**
 * Honest visitor counter: one count per browser session, bots excluded, nothing personal stored.
 * Counting runs in the browser (so cached pages still count), once the page is shown (not while the browser only
 * prerenders it). The footer reads the total from a static file (uploads/kits-visits.json), so a page view starts no
 * PHP; only the one count per session does, through the lightest endpoint the site offers (kits_visit_endpoint).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_BOTS = '/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse|facebookexternalhit|whatsapp|pingdom|uptime/i';

/** The public total (baseline + counted) and its start date. */
function kits_visits_public() {
	$base  = (int) get_option( 'aw_visits_baseline', 0 ); // visits before this counter started (GA or the 1.0 site)
	$since = (string) get_option( 'aw_visits_baseline_since' );
	return array(
		'total' => $base + (int) get_option( 'aw_visits_total', 0 ),
		'since' => $base && $since ? $since : (string) get_option( 'aw_visits_since' ),
	);
}

/** The static copy of the total, read by pages without PHP. */
function kits_visits_file() {
	return WP_CONTENT_DIR . '/uploads/kits-visits.json';
}

/**
 * Rewrite the static total, at most once a minute unless forced.
 *
 * @param bool $force Write now.
 */
function kits_visits_write( $force = false ) {
	$file = kits_visits_file();
	if ( ! $force && file_exists( $file ) && filemtime( $file ) > time() - 60 ) {
		return;
	}
	@file_put_contents( $file, (string) json_encode( kits_visits_public() ), LOCK_EX ); // phpcs:ignore -- a cache file; a failed write only delays the footer
}

/**
 * Count one visit. Uses only what WordPress loads with SHORTINIT (options, transients, $wpdb), so the lightweight
 * endpoint and the REST route share it. Not counted: empty or bot user agents, prefetch and prerender requests
 * (Sec-Purpose / Purpose), more than 30 an hour from one connection (hashed, never stored raw).
 *
 * @param string $ua      User agent.
 * @param string $purpose Sec-Purpose or Purpose header.
 * @param string $ip      Client address.
 * @return bool Counted.
 */
function kits_visit_count( $ua, $purpose, $ip ) {
	if ( '' === $ua || preg_match( AW_BOTS, $ua ) || preg_match( '/prefetch|prerender/i', $purpose ) ) {
		return false;
	}
	$key = 'aw_vis_' . md5( ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' ) . $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 30 ) {
		return false;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", 'aw_visits_total' ) ); // phpcs:ignore
	$day = 'aw_visits_' . gmdate( 'Ymd' );
	if ( false === get_option( $day ) ) {
		add_option( $day, 0, '', false );
	}
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $day ) ); // phpcs:ignore
	wp_cache_delete( 'aw_visits_total', 'options' );
	kits_visits_write();
	return true;
}

if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
	return; // the lightweight endpoint needs only the functions above
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/visit',
			array(
				array( // the fallback, and pages cached before the lightweight endpoint
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => function ( WP_REST_Request $req ) {
						$purpose = (string) $req->get_header( 'sec_purpose' ) . ' ' . (string) $req->get_header( 'purpose' );
						return array( 'counted' => kits_visit_count( (string) $req->get_header( 'user_agent' ), $purpose, (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ); // phpcs:ignore
					},
				),
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => function () {
						$r = new WP_REST_Response( kits_visits_public() );
						$r->header( 'Cache-Control', 'public, max-age=60' );
						return $r;
					},
				),
			)
		);
	}
);

add_action(
	'init',
	function () {
		if ( false === get_option( 'aw_visits_total' ) ) {
			add_option( 'aw_visits_total', 0, '', false );
			add_option( 'aw_visits_since', gmdate( 'Y-m-d' ), '', false );
		}
	}
);

/** Where the browser posts a visit: the REST route, or a lighter endpoint a site plugin offers. */
function kits_visit_endpoint() {
	return (string) apply_filters( 'kits_visit_endpoint', rest_url( 'aw/v1/visit' ) );
}

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || is_user_logged_in() ) {
			return; // Editors browsing the site are not visitors.
		}
		$post  = esc_url_raw( kits_visit_endpoint() );
		$total = esc_url_raw( content_url( 'uploads/kits-visits.json' ) );
		$rest  = esc_url_raw( rest_url( 'aw/v1/visit' ) );
		?>
<script>
(function(d){
function count(){try{if(navigator.webdriver||sessionStorage.getItem('aw_v'))return;sessionStorage.setItem('aw_v','1');
  fetch(<?php echo wp_json_encode( $post ); ?>,{method:'POST',credentials:'omit',keepalive:true});}catch(e){}}
function show(){if(!d.querySelector('.kit-visits'))return;
  fetch(<?php echo wp_json_encode( $total ); ?>+'?t='+Math.floor(Date.now()/3e5)).then(function(r){if(!r.ok)throw 0;return r.json();})
  .catch(function(){return fetch(<?php echo wp_json_encode( $rest ); ?>).then(function(r){return r.json();});}).then(function(v){
  if(!v||!v.total)return;var since=v.since?new Date(v.since+'T00:00:00').toLocaleDateString('en-IN',{day:'numeric',month:'long',year:'numeric'}):'';
  d.querySelectorAll('.kit-visits').forEach(function(x){x.textContent=v.total.toLocaleString('en-IN')+' visits'+(since?' since '+since:'');});}).catch(function(){});}
function go(){count();show();}
if(d.prerendering){d.addEventListener('prerenderingchange',go,{once:true});}else{go();}
})(document);
</script>
		<?php
	},
	30
);

// Admin dashboard widget: totals and the last 14 days.
add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget(
			'aw_visits',
			get_bloginfo( 'name' ) . ' visits',
			function () {
				echo '<p><strong>' . esc_html( number_format_i18n( (int) get_option( 'aw_visits_total', 0 ) ) ) . '</strong> visits since ' . esc_html( (string) get_option( 'aw_visits_since' ) ) . '</p><table class="widefat striped"><tbody>';
				for ( $i = 0; $i < 14; $i++ ) {
					$d = gmdate( 'Ymd', time() - $i * DAY_IN_SECONDS );
					echo '<tr><td>' . esc_html( wp_date( 'j M', strtotime( $d ) ) ) . '</td><td>' . esc_html( number_format_i18n( (int) get_option( 'aw_visits_' . $d, 0 ) ) ) . '</td></tr>';
				}
				echo '</tbody></table><p class="description">One count per browser session; bots and logged-in editors excluded. Google Analytics remains the detailed source.</p>';
				if ( current_user_can( 'manage_options' ) ) { // the count from day 0: add the visits before this counter started
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_visits_baseline">' . wp_nonce_field( 'aw_visits_baseline', '_wpnonce', true, false )
						. '<p><label>Visits before ' . esc_html( (string) get_option( 'aw_visits_since' ) ) . ' (from Google Analytics or the 1.0 site)<br><input type="number" min="0" name="base" value="' . (int) get_option( 'aw_visits_baseline', 0 ) . '"></label> '
						. '<label>counted since<br><input type="date" name="since" value="' . esc_attr( (string) get_option( 'aw_visits_baseline_since' ) ) . '"></label></p><p><button class="button">Save</button></p></form>';
				}
			}
		);
	}
);

add_action(
	'admin_post_aw_visits_baseline',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_visits_baseline' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		update_option( 'aw_visits_baseline', absint( $_POST['base'] ?? 0 ) );
		$s = sanitize_text_field( wp_unslash( $_POST['since'] ?? '' ) );
		update_option( 'aw_visits_baseline_since', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '' );
		kits_visits_write( true );
		wp_safe_redirect( admin_url() );
		exit;
	}
);

