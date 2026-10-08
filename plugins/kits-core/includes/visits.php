<?php
/**
 * Honest visitor counter: one count per browser session, bots excluded, nothing personal stored.
 * Counting runs in the browser (so cached pages still count). Shown in the footer.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_BOTS = '/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse|facebookexternalhit|whatsapp|pingdom|uptime/i';

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/visit',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => function ( WP_REST_Request $req ) {
						$ua = (string) $req->get_header( 'user_agent' );
						if ( '' === $ua || preg_match( AW_BOTS, $ua ) ) {
							return array( 'counted' => false );
						}
						// Throttle per connection: at most 30 counts per hour (hashed, never stored raw).
						$key = 'aw_vis_' . md5( wp_salt() . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
						$n   = (int) get_transient( $key );
						if ( $n >= 30 ) {
							return array( 'counted' => false );
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
						return array( 'counted' => true );
					},
				),
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => function () {
						$r = new WP_REST_Response( array( 'total' => (int) get_option( 'aw_visits_total', 0 ), 'since' => (string) get_option( 'aw_visits_since' ) ) );
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

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || is_user_logged_in() ) {
			return; // Editors browsing the site are not visitors.
		}
		$api = esc_url_raw( rest_url( 'aw/v1/visit' ) );
		?>
<script>
(function(){try{
  if(!sessionStorage.getItem('aw_v')){sessionStorage.setItem('aw_v','1');
    fetch(<?php echo wp_json_encode( $api ); ?>,{method:'POST',credentials:'omit',keepalive:true});}
}catch(e){}
fetch(<?php echo wp_json_encode( $api ); ?>).then(function(r){return r.json()}).then(function(d){
  var el=document.querySelectorAll('.kit-visits'); if(!d||!d.total) return;
  var since=d.since?new Date(d.since+'T00:00:00').toLocaleDateString('en-IN',{day:'numeric',month:'long',year:'numeric'}):'';
  el.forEach(function(x){x.textContent=d.total.toLocaleString('en-IN')+' visits'+(since?' since '+since:'');});
}).catch(function(){});
})();
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
			}
		);
	}
);
