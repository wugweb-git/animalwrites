<?php
/**
 * Admin: a standalone top-level menu and a Settings screen built on the Settings API — no external admin shell.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the top-level menu. The landing screen is the Overview dashboard; Settings is a submenu.
 */
function sourceweb_admin_menu() {
	add_menu_page(
		__( 'SourceWeb', 'sourceweb' ),
		__( 'SourceWeb', 'sourceweb' ),
		SOURCEWEB_CAP,
		'sourceweb',
		'sourceweb_dashboard_page',
		'dashicons-rss',
		58
	);
	add_submenu_page(
		'sourceweb',
		__( 'Overview', 'sourceweb' ),
		__( 'Overview', 'sourceweb' ),
		SOURCEWEB_CAP,
		'sourceweb',
		'sourceweb_dashboard_page',
		0
	);
	add_submenu_page(
		'sourceweb',
		__( 'Settings', 'sourceweb' ),
		__( 'Settings', 'sourceweb' ),
		SOURCEWEB_CAP,
		'sourceweb-settings',
		'sourceweb_settings_page',
		80
	);
}
add_action( 'admin_menu', 'sourceweb_admin_menu' );

/**
 * Enqueue the Overview dashboard stylesheet (layout only — the charts are server-rendered inline SVG, so no
 * script is needed). Loaded only on the dashboard screen.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function sourceweb_dashboard_assets( $hook ) {
	if ( 'toplevel_page_sourceweb' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'sourceweb-admin', SOURCEWEB_URL . 'assets/css/sourceweb-admin.css', array(), SOURCEWEB_VERSION );
}
add_action( 'admin_enqueue_scripts', 'sourceweb_dashboard_assets' );

/**
 * Render one stat tile (headline number + optional period-over-period delta).
 *
 * @param string   $label The tile label.
 * @param int      $value The headline value.
 * @param int|null $delta The change versus the previous period, or null to omit.
 * @return void
 */
function sourceweb_dashboard_tile( $label, $value, $delta = null ) {
	?>
	<div class="sourceweb-tile">
		<p class="sourceweb-tile__label"><?php echo esc_html( $label ); ?></p>
		<p class="sourceweb-tile__value"><?php echo esc_html( number_format_i18n( (int) $value ) ); ?></p>
		<?php if ( null !== $delta ) : ?>
			<p class="sourceweb-tile__delta">
				<?php
				$sign = $delta > 0 ? '+' : ( $delta < 0 ? '−' : '±' );
				echo esc_html(
					sprintf(
						/* translators: 1: signed change, 2: absolute change. */
						__( '%1$s%2$s vs previous 30 days', 'sourceweb' ),
						$sign,
						number_format_i18n( abs( (int) $delta ) )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Build MiniChart points for a daily metric over the window: one bar per day, labelled by short date.
 *
 * @param array  $stats  Stored stats.
 * @param string $metric Counter key.
 * @param int    $days   Window length.
 * @param string $today  End date (Y-m-d).
 * @return array<int,array{value:float,label:string}>
 */
function sourceweb_dashboard_day_points( $stats, $metric, $days, $today ) {
	$points = array();
	foreach ( sourceweb_stats_series( $stats, $metric, $days, $today ) as $p ) {
		$points[] = array(
			'value' => (float) $p['value'],
			'label' => date_i18n( 'M j', strtotime( $p['date'] . ' 00:00:00' ) ),
		);
	}
	return $points;
}

/**
 * Render the Overview dashboard: stat tiles plus four MiniCharts (pipeline funnel, feed throughput,
 * near-duplicates over time, review-queue size over time).
 *
 * @return void
 */
function sourceweb_dashboard_page() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		return;
	}
	$days    = 30;
	$short   = 14;
	$today   = gmdate( 'Y-m-d' );
	$stats   = sourceweb_stats_get();
	$totals  = sourceweb_stats_totals( $stats, $days, $today );
	$pending = function_exists( 'sourceweb_queue_pending_count' ) ? sourceweb_queue_pending_count() : 0;
	$dedupe  = sourceweb_stats_dedupe_rate( $stats, $days, $today );

	$d_fetched  = sourceweb_stats_period_delta( $stats, 'fetched', $days, $today );
	$d_approved = sourceweb_stats_period_delta( $stats, 'approved', $days, $today );

	// Pipeline funnel (window totals): ingested → reviewed → approved; the last bar (Approved) is the attention bar.
	$pipeline = array(
		array(
			'value' => (float) $totals['fetched'],
			'label' => __( 'Fetched', 'sourceweb' ),
		),
		array(
			'value' => (float) $totals['queued'],
			'label' => __( 'Queued', 'sourceweb' ),
		),
		array(
			'value' => (float) $totals['approved'],
			'label' => __( 'Approved', 'sourceweb' ),
		),
	);

	// Feed throughput: items queued at each feed's last run (one bar per feed host).
	$throughput = array();
	if ( function_exists( 'sourceweb_feeds' ) && function_exists( 'sourceweb_feed_runs' ) ) {
		$runs = sourceweb_feed_runs();
		foreach ( array_slice( sourceweb_feeds(), 0, 12 ) as $url ) {
			$host         = sourceweb_service_host( $url );
			$throughput[] = array(
				'value' => isset( $runs[ $url ]['queued'] ) ? (float) $runs[ $url ]['queued'] : 0.0,
				'label' => '' !== $host ? $host : $url,
			);
		}
	}

	// Review-queue size over time: only days that carry a snapshot, the most recent up to $short of them.
	$queue_points = array();
	$snaps        = array();
	foreach ( ( isset( $stats['days'] ) && is_array( $stats['days'] ) ? $stats['days'] : array() ) as $date => $bucket ) {
		if ( isset( $bucket['queue_size'] ) ) {
			$snaps[ $date ] = (int) $bucket['queue_size'];
		}
	}
	ksort( $snaps );
	foreach ( array_slice( $snaps, -$short, $short, true ) as $date => $size ) {
		$queue_points[] = array(
			'value' => (float) $size,
			'label' => date_i18n( 'M j', strtotime( $date . ' 00:00:00' ) ),
		);
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'SourceWeb — Overview', 'sourceweb' ); ?></h1>
		<p class="description"><?php echo esc_html__( 'How the curation pipeline has behaved over the last 30 days.', 'sourceweb' ); ?></p>

		<h2 class="sourceweb-section-title"><?php echo esc_html__( 'At a glance', 'sourceweb' ); ?></h2>
		<div class="sourceweb-tiles">
			<?php
			sourceweb_dashboard_tile( __( 'In review now', 'sourceweb' ), $pending );
			sourceweb_dashboard_tile( __( 'Fetched (30d)', 'sourceweb' ), $totals['fetched'], $d_fetched['change'] );
			sourceweb_dashboard_tile( __( 'Approved (30d)', 'sourceweb' ), $totals['approved'], $d_approved['change'] );
			?>
			<div class="sourceweb-tile">
				<p class="sourceweb-tile__label"><?php echo esc_html__( 'Same-story dedupe rate', 'sourceweb' ); ?></p>
				<p class="sourceweb-tile__value"><?php echo esc_html( number_format_i18n( $dedupe * 100, 1 ) . '%' ); ?></p>
				<p class="sourceweb-tile__delta"><?php echo esc_html__( 'Candidates dropped as near-duplicates', 'sourceweb' ); ?></p>
			</div>
		</div>

		<h2 class="sourceweb-section-title"><?php echo esc_html__( 'Over time', 'sourceweb' ); ?></h2>
		<div class="sourceweb-charts">
			<div class="sourceweb-card">
				<h3><?php echo esc_html__( 'Pipeline (30 days)', 'sourceweb' ); ?></h3>
				<?php
				sourceweb_minichart(
					array(
						'label'  => __( 'Fetched → queued → approved', 'sourceweb' ),
						'points' => $pipeline,
					)
				);
				?>
			</div>
			<div class="sourceweb-card">
				<h3><?php echo esc_html__( 'Feed throughput', 'sourceweb' ); ?></h3>
				<?php
				sourceweb_minichart(
					array(
						'label'  => __( 'Queued at last fetch, by feed', 'sourceweb' ),
						'points' => $throughput,
					)
				);
				?>
			</div>
			<div class="sourceweb-card">
				<h3><?php echo esc_html__( 'Near-duplicates dropped', 'sourceweb' ); ?></h3>
				<?php
				sourceweb_minichart(
					array(
						'label'  => __( 'Same-story items dropped per day', 'sourceweb' ),
						'points' => sourceweb_dashboard_day_points( $stats, 'skipped_dedupe', $short, $today ),
					)
				);
				?>
			</div>
			<div class="sourceweb-card">
				<h3><?php echo esc_html__( 'Review queue size', 'sourceweb' ); ?></h3>
				<?php
				sourceweb_minichart(
					array(
						'label'  => __( 'Items awaiting review', 'sourceweb' ),
						'points' => $queue_points,
						'unit'   => __( 'items', 'sourceweb' ),
					)
				);
				?>
			</div>
		</div>

		<p>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sourceweb-review' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary"><?php echo esc_html__( 'Go to review queue', 'sourceweb' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sourceweb-feeds' ), admin_url( 'admin.php' ) ) ); ?>" class="button"><?php echo esc_html__( 'Feeds', 'sourceweb' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * Render the Settings screen.
 */
function sourceweb_settings_page() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		return;
	}
	$keywords = sourceweb_keywords();
	$chosen   = sourceweb_post_types();
	$public   = get_post_types( array( 'public' => true ), 'objects' );
	$feeds    = sourceweb_feeds();
	$summary  = sourceweb_summary_rules();
	$textarea = static function ( array $terms ) {
		return esc_textarea( implode( "\n", array_map( 'strval', $terms ) ) );
	};
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'SourceWeb', 'sourceweb' ); ?></h1>
		<p><?php echo esc_html__( 'Choose which content SourceWeb curates, and the keywords that decide relevance.', 'sourceweb' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'sourceweb' ); ?>

			<h2><?php echo esc_html__( 'Post types to curate', 'sourceweb' ); ?></h2>
			<fieldset>
				<?php foreach ( $public as $type ) : ?>
					<label style="display:block;margin:.3em 0;">
						<input type="checkbox" name="sourceweb_post_types[]"
							value="<?php echo esc_attr( $type->name ); ?>"
							<?php checked( in_array( $type->name, $chosen, true ) ); ?> />
						<?php echo esc_html( $type->labels->singular_name . ' (' . $type->name . ')' ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<h2><?php echo esc_html__( 'Relevance', 'sourceweb' ); ?></h2>
			<label>
				<input type="checkbox" name="sourceweb_keywords[enabled]" value="1" <?php checked( ! empty( $keywords['enabled'] ) ); ?> />
				<?php echo esc_html__( 'Filter incoming items by the keyword lists below (exclude terms always apply).', 'sourceweb' ); ?>
			</label>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sourceweb-strong"><?php echo esc_html__( 'Include (strong) terms', 'sourceweb' ); ?></label></th>
					<td><textarea id="sourceweb-strong" name="sourceweb_keywords[strong]" rows="5" class="large-text code"><?php echo $textarea( $keywords['strong'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_textarea applied in the closure ?></textarea>
						<p class="description"><?php echo esc_html__( 'One per line. Any match keeps the item.', 'sourceweb' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sourceweb-animal"><?php echo esc_html__( 'Subject terms', 'sourceweb' ); ?></label></th>
					<td><textarea id="sourceweb-animal" name="sourceweb_keywords[animal]" rows="5" class="large-text code"><?php echo $textarea( $keywords['animal'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_textarea applied in the closure ?></textarea>
						<p class="description"><?php echo esc_html__( 'One per line. Kept only together with a context term.', 'sourceweb' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sourceweb-context"><?php echo esc_html__( 'Context terms', 'sourceweb' ); ?></label></th>
					<td><textarea id="sourceweb-context" name="sourceweb_keywords[context]" rows="5" class="large-text code"><?php echo $textarea( $keywords['context'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_textarea applied in the closure ?></textarea>
						<p class="description"><?php echo esc_html__( 'One per line.', 'sourceweb' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="sourceweb-exclude"><?php echo esc_html__( 'Exclude terms', 'sourceweb' ); ?></label></th>
					<td><textarea id="sourceweb-exclude" name="sourceweb_keywords[exclude]" rows="5" class="large-text code"><?php echo $textarea( $keywords['exclude'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_textarea applied in the closure ?></textarea>
						<p class="description"><?php echo esc_html__( 'One per line. Any match rejects the item, always.', 'sourceweb' ); ?></p></td>
				</tr>
			</table>

			<h2><?php echo esc_html__( 'Feeds', 'sourceweb' ); ?></h2>
			<textarea id="sourceweb-feeds" name="sourceweb_feeds" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $feeds ) ); ?></textarea>
			<p class="description"><?php echo esc_html__( 'One feed URL per line (http/https). Each feed host is disclosed and gated under External services automatically.', 'sourceweb' ); ?></p>

			<h2><?php echo esc_html__( 'Summary rules', 'sourceweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sourceweb-maxwords"><?php echo esc_html__( 'Trim summaries to', 'sourceweb' ); ?></label></th>
					<td>
						<input type="number" min="0" id="sourceweb-maxwords" name="sourceweb_summary[max_words]" value="<?php echo esc_attr( (string) $summary['max_words'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'words (0 = no limit). Trimming ends on the last complete sentence within the limit.', 'sourceweb' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sourceweb-strip"><?php echo esc_html__( 'Strip phrases', 'sourceweb' ); ?></label></th>
					<td><textarea id="sourceweb-strip" name="sourceweb_summary[strip]" rows="4" class="large-text code"><?php echo $textarea( $summary['strip'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_textarea applied in the closure ?></textarea>
						<p class="description"><?php echo esc_html__( 'One phrase per line; each is removed from incoming summaries (case-insensitive).', 'sourceweb' ); ?></p></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
