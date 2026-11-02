<?php
/**
 * PageSpeed (plan §5.1): mobile and desktop for the key pages and every campaign page, weekly on Sunday 06:00 IST, through Site Kit's
 * pagespeed-insights module as its owner; when the module can't be used (no owner, not connected), the public PageSpeed
 * API directly, with the free AW_PSI_KEY (Animal Writes → Keys or wp-config.php) when set (keyless calls have a small shared quota). One PageSpeed call can take 10–40 seconds, so the 32 calls run as a queue: a few per cron run, the next run
 * five minutes later, until the queue is empty.
 *
 * Rows in obs: src psi, period day (the week's run date), page = path; metrics score-mobile, lcp-mobile, tbt-mobile,
 * cls-mobile and the same for desktop; flag = 1 when the mobile score is under 50.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_PSI_CRON   = 'aw_si_psi';
const AW_SI_PSI_MORE   = 'aw_si_psi_more';
const AW_SI_PSI_QUEUE  = 'aw_si_psi_queue';
const AW_SI_PSI_BUDGET = 25; // Seconds: no new call starts after this, so a run stays inside host time limits.
// AW_SI_PSI_FLAG: AW SEO Plus → Settings (settings.php).

/**
 * The key pages of the 20 Oct PageSpeed audit: the hubs, a state and a district page, the newest item of each type,
 * and every published campaign page (2 Nov 2026). Filter aw_si_psi_pages changes the list.
 *
 * @return string[] Site paths.
 */
function aw_si_psi_pages(): array {
	$paths = array( '/', '/news/', '/directory/', '/library/', '/resources/', '/opportunities/', '/campaigns/', '/about/' );
	if ( function_exists( 'aw_directory_url' ) ) {
		foreach ( array( 'kerala', 'pune' ) as $slug ) {
			$paths[] = aw_si_path( (string) aw_directory_url( $slug ) );
		}
	}
	// Every published campaign (a handful), so each campaign page gets LCP and TBT figures.
	$camps = get_posts(
		array(
			'post_type'      => 'aw_campaign',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	foreach ( $camps as $id ) {
		$paths[] = aw_si_object_path( 'post', (int) $id );
	}
	foreach ( array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_listing' ) as $type ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( $ids ) {
			$paths[] = aw_si_object_path( 'post', (int) $ids[0] );
		}
	}
	return array_values( array_unique( array_filter( (array) apply_filters( 'aw_si_psi_pages', $paths ) ) ) );
}

/**
 * Lab figures of one PageSpeed response: score (0–100), LCP and TBT (ms), CLS.
 *
 * @param mixed $resp Site Kit's response object, or the decoded JSON of the PSI API.
 * @return array{score: float|null, lcp: float|null, tbt: float|null, cls: float|null}
 */
function aw_si_psi_figures( $resp ): array {
	if ( is_object( $resp ) && method_exists( $resp, 'toSimpleObject' ) ) {
		$resp = json_decode( (string) wp_json_encode( $resp->toSimpleObject() ), true );
	} elseif ( is_object( $resp ) ) {
		$resp = json_decode( (string) wp_json_encode( $resp ), true );
	}
	$lh    = (array) ( $resp['lighthouseResult'] ?? array() );
	$audit = fn( $k ) => isset( $lh['audits'][ $k ]['numericValue'] ) ? (float) $lh['audits'][ $k ]['numericValue'] : null;
	$score = $lh['categories']['performance']['score'] ?? null;
	return array(
		'score' => null === $score ? null : round( (float) $score * 100 ),
		'lcp'   => $audit( 'largest-contentful-paint' ),
		'tbt'   => $audit( 'total-blocking-time' ),
		'cls'   => $audit( 'cumulative-layout-shift' ),
	);
}

/**
 * One PageSpeed call: Site Kit's module first, the AW_PSI_KEY API second.
 *
 * @param string $path     Site path.
 * @param string $strategy mobile or desktop.
 * @return array|WP_Error What aw_si_psi_figures() returns.
 */
function aw_si_psi_call( string $path, string $strategy ) {
	$url  = home_url( $path );
	$resp = aw_si_with_module(
		'pagespeed-insights',
		function ( $module ) use ( $url, $strategy ) {
			try {
				return $module->get_data(
					'pagespeed',
					array(
						'url'      => $url,
						'strategy' => $strategy,
					)
				);
			} catch ( Throwable $e ) {
				return new WP_Error( 'aw_si_psi', $e->getMessage() );
			}
		}
	);
	if ( is_wp_error( $resp ) ) {
		$query = array(
			'url'      => rawurlencode( $url ),
			'strategy' => $strategy,
			'category' => 'performance',
		);
		if ( '' !== aw_si_key( 'AW_PSI_KEY' ) ) {
			$query['key'] = aw_si_key( 'AW_PSI_KEY' );
		}
		$http = wp_remote_get(
			add_query_arg(
				$query,
				'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
			),
			array( 'timeout' => 60 )
		);
		if ( is_wp_error( $http ) ) {
			return $http;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $http ) ) {
			/* translators: 1: Site Kit's problem, 2: HTTP status. */
			return new WP_Error( 'aw_si_psi', sprintf( __( '%1$s The PageSpeed API answered %2$d.', 'aw-seo-extras' ), $resp->get_error_message(), (int) wp_remote_retrieve_response_code( $http ) ) );
		}
		$resp = json_decode( (string) wp_remote_retrieve_body( $http ), true );
	}
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$fig = aw_si_psi_figures( $resp );
	return null === $fig['score'] ? new WP_Error( 'aw_si_psi', __( 'PageSpeed returned no performance score.', 'aw-seo-extras' ) ) : $fig;
}

/** Start this week's queue: every key page, mobile and desktop. */
function aw_si_psi_start(): void {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return;
	}
	$q = get_option( AW_SI_PSI_QUEUE );
	if ( is_array( $q ) && ! empty( $q['todo'] ) && ( $q['d'] ?? '' ) >= wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) {
		aw_si_psi_step(); // A run is still in progress: carry on with it instead of starting over.
		return;
	}
	$todo = array();
	foreach ( aw_si_psi_pages() as $path ) {
		$todo[] = array( $path, 'mobile' );
		$todo[] = array( $path, 'desktop' );
	}
	update_option(
		AW_SI_PSI_QUEUE,
		array(
			'd'      => wp_date( 'Y-m-d' ),
			'todo'   => $todo,
			'done'   => 0,
			'errors' => array(),
		),
		false
	);
	aw_si_run_start( 'psi' );
	aw_si_psi_step();
}

/**
 * Work through the queue for up to AW_SI_PSI_BUDGET seconds; schedule the next step when calls are left.
 *
 * @return array The queue as it stands.
 */
function aw_si_psi_step(): array {
	$q = get_option( AW_SI_PSI_QUEUE );
	if ( ! is_array( $q ) || empty( $q['todo'] ) || get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return is_array( $q ) ? $q : array();
	}
	// The next step is booked before any call (25 Oct 2026): a step the host's time limit cuts off mid-call never reached
	// the booking after the loop, so the queue stopped until someone pressed Start PageSpeed again. The call it was making
	// is still in the saved queue and is tried again.
	if ( ! wp_next_scheduled( AW_SI_PSI_MORE ) ) {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, AW_SI_PSI_MORE );
	}
	$start = microtime( true );
	while ( $q['todo'] && microtime( true ) - $start < AW_SI_PSI_BUDGET ) {
		list( $path, $strategy ) = array_shift( $q['todo'] );
		$fig                     = aw_si_psi_call( $path, $strategy );
		if ( is_wp_error( $fig ) ) {
			$q['errors'][] = $path . ' (' . $strategy . '): ' . $fig->get_error_message();
		} else {
			foreach ( $fig as $metric => $value ) {
				if ( null !== $value ) {
					aw_si_obs_put( 0, $path, 'psi', $metric . '-' . $strategy, 'day', $q['d'], array( 'value' => (float) $value ) );
				}
			}
			if ( 'mobile' === $strategy ) {
				aw_si_obs_put( 0, $path, 'psi', 'flag', 'day', $q['d'], array( 'value' => $fig['score'] < AW_SI_PSI_FLAG ? 1.0 : 0.0 ) );
			}
			++$q['done'];
		}
		update_option( AW_SI_PSI_QUEUE, $q, false ); // Saved after each call, so a run cut short loses nothing.
	}
	if ( $q['todo'] ) {
		return $q; // The next step is already booked (above).
	}
	wp_clear_scheduled_hook( AW_SI_PSI_MORE ); // Done: no further step.
	// Finished: good when at least one call worked; every error is listed either way.
	if ( $q['done'] ) {
		aw_si_run_ok(
			'psi',
			array(
				'rows'   => $q['done'],
				'up_to'  => $q['d'],
				'errors' => array_slice( $q['errors'], 0, 10 ),
			)
		);
	} else {
		/* translators: %s: first error. */
		aw_si_run_fail( 'psi', sprintf( __( 'No PageSpeed call worked: %s', 'aw-seo-extras' ), (string) ( $q['errors'][0] ?? '' ) ) );
	}
	return $q;
}
add_action( AW_SI_PSI_CRON, 'aw_si_psi_start' );
add_action( AW_SI_PSI_MORE, 'aw_si_psi_step' );
