<?php
/**
 * Kits Core: Native A/B and Multivariate Experimentation Engine.
 *
 * Lightweight, zero-CLS, cache-friendly A/B testing for WordPress.
 * Supports deterministic cookie-based variant assignment, query-string QA overrides,
 * automatic GA4/GTM dataLayer pushes, and Microsoft Clarity integration.
 *
 * Benchmark: Google Optimize, PostHog, Split.io (zero-dependency native PHP/JS implementation).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of active experiments evaluated during the current request.
 *
 * @var array<string, array{experiment: string, variant: string, variants: array<string>}>
 */
global $kits_active_experiments;
$kits_active_experiments = array();

/**
 * Get or assign the variant for an experiment.
 *
 * @param string        $experiment_id Unique identifier for the experiment (e.g. 'hero_cta_test').
 * @param array<string> $variants      List of variant keys (defaults to ['A', 'B']).
 * @param array<int>    $weights       Optional percentage weights per variant (must sum to 100).
 * @return string The selected variant key.
 */
function kits_ab_variant( string $experiment_id, array $variants = array( 'A', 'B' ), array $weights = array() ): string {
	global $kits_active_experiments;

	$experiment_id = sanitize_key( $experiment_id );
	if ( empty( $experiment_id ) || empty( $variants ) ) {
		return 'A';
	}

	// 1. Check for developer / QA query parameter override (e.g., ?kits_ab_hero_cta_test=B).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only QA preview parameter.
	if ( isset( $_GET[ 'kits_ab_' . $experiment_id ] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$forced = sanitize_text_field( wp_unslash( $_GET[ 'kits_ab_' . $experiment_id ] ) );
		if ( in_array( $forced, $variants, true ) ) {
			$kits_active_experiments[ $experiment_id ] = array(
				'experiment' => $experiment_id,
				'variant'    => $forced,
				'variants'   => $variants,
			);
			return $forced;
		}
	}

	// 2. Check for existing cookie.
	$cookie_name = 'kits_ab_' . $experiment_id;
	if ( isset( $_COOKIE[ $cookie_name ] ) ) {
		$cookie_val = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		if ( in_array( $cookie_val, $variants, true ) ) {
			$kits_active_experiments[ $experiment_id ] = array(
				'experiment' => $experiment_id,
				'variant'    => $cookie_val,
				'variants'   => $variants,
			);
			return $cookie_val;
		}
	}

	// 3. Deterministic pseudo-random bucketing based on client IP / user agent if cookie not yet set.
	$hash_seed = ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) .
				( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ) .
				$experiment_id;

	$bucket = hexdec( substr( md5( $hash_seed ), 0, 4 ) ) % 100;

	$selected = $variants[0];
	if ( ! empty( $weights ) && count( $weights ) === count( $variants ) ) {
		$cum = 0;
		foreach ( $variants as $i => $v ) {
			$cum += (int) $weights[ $i ];
			if ( $bucket < $cum ) {
				$selected = $v;
				break;
			}
		}
	} else {
		$idx      = (int) floor( ( $bucket / 100 ) * count( $variants ) );
		$selected = $variants[ min( $idx, count( $variants ) - 1 ) ];
	}

	// Set cookie on client headers if not already sent (valid for 30 days).
	if ( ! headers_sent() ) {
		$expire = time() + ( 30 * DAY_IN_SECONDS );
		$path   = COOKIEPATH ? COOKIEPATH : '/';
		$domain = COOKIE_DOMAIN;
		$secure = is_ssl();
		setcookie( $cookie_name, $selected, $expire, $path, $domain, $secure, false );
	}

	/**
	 * Filter the assigned variant for custom programmatic overrides.
	 *
	 * @param string        $selected      Assigned variant.
	 * @param string        $experiment_id Experiment identifier.
	 * @param array<string> $variants      Available variants.
	 */
	$selected = apply_filters( 'kits_ab_variant', $selected, $experiment_id, $variants );

	$kits_active_experiments[ $experiment_id ] = array(
		'experiment' => $experiment_id,
		'variant'    => $selected,
		'variants'   => $variants,
	);

	return $selected;
}

/**
 * Check whether a specific variant is active for an experiment.
 *
 * @param string $experiment_id Experiment ID.
 * @param string $variant_id    Variant key to check.
 * @return bool True if active.
 */
function kits_ab_is( string $experiment_id, string $variant_id ): bool {
	return kits_ab_variant( $experiment_id ) === $variant_id;
}

/**
 * Push active experiment assignments to client-side analytics (GTM, GA4, Clarity).
 */
function kits_ab_output_analytics_events(): void {
	global $kits_active_experiments;

	if ( empty( $kits_active_experiments ) ) {
		return;
	}

	$payload = array_values( $kits_active_experiments );
	?>
	<script id="kits-ab-analytics">
	(function() {
		var experiments = <?php echo wp_json_encode( $payload ); ?>;
		if (!Array.isArray(experiments) || !experiments.length) return;

		window.dataLayer = window.dataLayer || [];
		experiments.forEach(function(exp) {
			window.dataLayer.push({
				event: 'kits_experiment_impression',
				experiment_id: exp.experiment,
				variant_id: exp.variant
			});

			// Microsoft Clarity custom tag integration if active.
			if (typeof window.clarity === 'function') {
				window.clarity('set', 'exp_' + exp.experiment, exp.variant);
			}
		});
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'kits_ab_output_analytics_events', 25 );

/**
 * Shortcode to render variant-specific content.
 *
 * Usage:
 * [kits_ab id="hero_title" variant="A"]Headline A[/kits_ab]
 * [kits_ab id="hero_title" variant="B"]Headline B[/kits_ab]
 *
 * @param array<string, mixed> $atts    Shortcode attributes.
 * @param string|null          $content Enclosed content.
 * @return string Rendered content if variant matches, empty string otherwise.
 */
function kits_ab_shortcode( $atts, ?string $content = null ): string {
	$a = shortcode_atts(
		array(
			'id'      => '',
			'variant' => 'A',
		),
		$atts,
		'kits_ab'
	);

	if ( empty( $a['id'] ) || empty( $content ) ) {
		return '';
	}

	$active = kits_ab_variant( $a['id'] );
	return ( $active === $a['variant'] ) ? do_shortcode( $content ) : '';
}
add_shortcode( 'kits_ab', 'kits_ab_shortcode' );
