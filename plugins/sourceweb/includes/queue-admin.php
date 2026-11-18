<?php
/**
 * Review and Feeds admin screens. Both are core WP_List_Table screens. Every state-changing action (approve,
 * reject, bulk approve/reject, fetch a feed, fetch all feeds) is guarded by a nonce and the plugin capability.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Review submenu (with the pending count in the label) and the Feeds submenu.
 *
 * @return void
 */
function sourceweb_review_menu() {
	$count = sourceweb_queue_pending_count();
	$label = __( 'Review', 'sourceweb' );
	if ( $count > 0 ) {
		$label .= ' <span class="awaiting-mod">' . esc_html( (string) $count ) . '</span>';
	}
	add_submenu_page(
		'sourceweb',
		__( 'Review', 'sourceweb' ),
		$label,
		SOURCEWEB_CAP,
		'sourceweb-review',
		'sourceweb_review_page'
	);
	add_submenu_page(
		'sourceweb',
		__( 'Feeds', 'sourceweb' ),
		__( 'Feeds', 'sourceweb' ),
		SOURCEWEB_CAP,
		'sourceweb-feeds',
		'sourceweb_feeds_page'
	);
}
add_action( 'admin_menu', 'sourceweb_review_menu' );

/**
 * Handle "Fetch feeds now": run the ingest across all configured feeds (admin-post.php).
 *
 * @return void
 */
function sourceweb_fetch_now_handle() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		wp_die( esc_html__( 'You are not allowed to fetch feeds.', 'sourceweb' ) );
	}
	check_admin_referer( 'sourceweb_fetch_now' );

	$totals = sourceweb_ingest_all();

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'       => 'sourceweb-review',
				'sw_fetched' => (int) $totals['fetched'],
				'sw_queued'  => (int) $totals['queued'],
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_sourceweb_fetch_now', 'sourceweb_fetch_now_handle' );

/**
 * Process a single row action (approve/reject) or a bulk action on the Review screen, then redirect with a
 * notice flag. Returns true when a redirect was issued (the caller should stop).
 *
 * @return void
 */
function sourceweb_review_dispatch() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- each state-changing branch verifies its own nonce below before acting.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 'bulk-approve' === $action || 'bulk-reject' === $action ) {
		check_admin_referer( 'bulk-sourceweb_items' );
		$ids     = isset( $_REQUEST['item'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['item'] ) ) : array();
		$approve = ( 'bulk-approve' === $action );
		$done    = 0;
		foreach ( $ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$ok = $approve ? ( sourceweb_queue_approve( $id ) > 0 ) : sourceweb_queue_reject( $id );
			if ( $ok ) {
				++$done;
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sourceweb-review',
					'sw_done' => $approve ? 'approved' : 'rejected',
					'sw_n'    => $done,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	if ( 'approve' === $action || 'reject' === $action ) {
		$id = isset( $_REQUEST['item'] ) ? absint( wp_unslash( $_REQUEST['item'] ) ) : 0;
		check_admin_referer( 'sourceweb-item_' . $id );
		$ok = 'approve' === $action ? ( sourceweb_queue_approve( $id ) > 0 ) : sourceweb_queue_reject( $id );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sourceweb-review',
					'sw_done' => $ok ? ( 'approve' === $action ? 'approved' : 'rejected' ) : 'error',
					'sw_n'    => $ok ? 1 : 0,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

/**
 * Render the Review screen: a single-item detail view, or the review queue list table.
 *
 * @return void
 */
function sourceweb_review_page() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		return;
	}
	sourceweb_review_dispatch();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display routing and read-only notice flags.
	$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	$item_id = isset( $_REQUEST['item'] ) && ! is_array( $_REQUEST['item'] ) ? absint( wp_unslash( $_REQUEST['item'] ) ) : 0;
	$done    = isset( $_GET['sw_done'] ) ? sanitize_key( wp_unslash( $_GET['sw_done'] ) ) : '';
	$n       = isset( $_GET['sw_n'] ) ? absint( wp_unslash( $_GET['sw_n'] ) ) : 0;
	$fetched = isset( $_GET['sw_fetched'] ) ? absint( wp_unslash( $_GET['sw_fetched'] ) ) : -1;
	$queued  = isset( $_GET['sw_queued'] ) ? absint( wp_unslash( $_GET['sw_queued'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'view' === $action && $item_id > 0 ) {
		sourceweb_review_detail( $item_id );
		return;
	}

	require_once SOURCEWEB_DIR . 'includes/class-sourceweb-review-table.php';
	$table = new SourceWeb_Review_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html__( 'Review', 'sourceweb' ); ?></h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:1em">
			<?php wp_nonce_field( 'sourceweb_fetch_now' ); ?>
			<input type="hidden" name="action" value="sourceweb_fetch_now" />
			<button type="submit" class="page-title-action"><?php echo esc_html__( 'Fetch feeds now', 'sourceweb' ); ?></button>
		</form>
		<hr class="wp-header-end" />

		<?php if ( $fetched >= 0 ) : ?>
			<div class="notice notice-info is-dismissible"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: items fetched, 2: items queued. */
						__( 'Fetched %1$d item(s); %2$d added to the review queue.', 'sourceweb' ),
						$fetched,
						$queued
					)
				);
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( 'approved' === $done || 'rejected' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				echo esc_html(
					'approved' === $done
						/* translators: %d: number of items. */
						? sprintf( _n( '%d item approved — a draft was created for it.', '%d items approved — drafts were created.', max( 1, $n ), 'sourceweb' ), $n )
						/* translators: %d: number of items. */
						: sprintf( _n( '%d item rejected.', '%d items rejected.', max( 1, $n ), 'sourceweb' ), $n )
				);
				?>
			</p></div>
		<?php elseif ( 'error' === $done ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'That item could not be processed.', 'sourceweb' ); ?></p></div>
		<?php endif; ?>

		<form method="get">
			<input type="hidden" name="page" value="sourceweb-review" />
			<?php
			$table->search_box( esc_html__( 'Search queue', 'sourceweb' ), 'sourceweb-item' );
			?>
		</form>
		<form method="post">
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}

/**
 * Render the single-item detail view.
 *
 * @param int $id Queue item id.
 * @return void
 */
function sourceweb_review_detail( $id ) {
	$item = get_post( $id );
	$back = add_query_arg( array( 'page' => 'sourceweb-review' ), admin_url( 'admin.php' ) );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html__( 'Review item', 'sourceweb' ); ?></h1>
		<a href="<?php echo esc_url( $back ); ?>" class="page-title-action"><?php echo esc_html__( '← Back to queue', 'sourceweb' ); ?></a>
		<hr class="wp-header-end" />
		<?php if ( ! $item || 'sourceweb_item' !== $item->post_type ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html__( 'That item is no longer in the queue.', 'sourceweb' ); ?></p></div>
		<?php else : ?>
			<?php
			$url     = (string) get_post_meta( $id, '_sourceweb_source_url', true );
			$name    = (string) get_post_meta( $id, '_sourceweb_source_name', true );
			$approve = wp_nonce_url(
				add_query_arg(
					array(
						'page'   => 'sourceweb-review',
						'action' => 'approve',
						'item'   => $id,
					),
					admin_url( 'admin.php' )
				),
				'sourceweb-item_' . $id
			);
			$reject  = wp_nonce_url(
				add_query_arg(
					array(
						'page'   => 'sourceweb-review',
						'action' => 'reject',
						'item'   => $id,
					),
					admin_url( 'admin.php' )
				),
				'sourceweb-item_' . $id
			);
			?>
			<h2><?php echo esc_html( $item->post_title ); ?></h2>
			<table class="widefat striped" style="max-width:48em">
				<tbody>
					<tr>
						<th scope="row" style="width:10em"><?php echo esc_html__( 'Source', 'sourceweb' ); ?></th>
						<td>
							<?php if ( '' !== $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $name ? $name : $url ); ?></a>
							<?php else : ?>
								<?php echo esc_html( '' !== $name ? $name : '—' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Added', 'sourceweb' ); ?></th>
						<td><?php echo esc_html( get_the_date( '', $item ) . ' ' . get_the_time( '', $item ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Summary', 'sourceweb' ); ?></th>
						<td><?php echo esc_html( (string) $item->post_content ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<a href="<?php echo esc_url( $approve ); ?>" class="button button-primary"><?php echo esc_html__( 'Approve (create draft)', 'sourceweb' ); ?></a>
				<a href="<?php echo esc_url( $reject ); ?>" class="button"><?php echo esc_html__( 'Reject', 'sourceweb' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the Feeds screen (a list table of configured feeds and their last-run status).
 *
 * @return void
 */
function sourceweb_feeds_page() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		return;
	}

	// Per-feed "Fetch now".
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified immediately below before any action.
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	if ( 'fetch' === $action ) {
		$index = isset( $_GET['feed'] ) ? absint( wp_unslash( $_GET['feed'] ) ) : -1;
		check_admin_referer( 'sourceweb-feed_' . $index );
		$feeds = sourceweb_feeds();
		if ( isset( $feeds[ $index ] ) ) {
			$one = sourceweb_ingest_feed( $feeds[ $index ] );
			if ( function_exists( 'sourceweb_stats_snapshot_queue' ) ) {
				sourceweb_stats_snapshot_queue( sourceweb_queue_pending_count() );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'sourceweb-feeds',
						'sw_fetched' => (int) $one['fetched'],
						'sw_queued'  => (int) $one['queued'],
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice flags set by our own redirect.
	$fetched = isset( $_GET['sw_fetched'] ) ? absint( wp_unslash( $_GET['sw_fetched'] ) ) : -1;
	$queued  = isset( $_GET['sw_queued'] ) ? absint( wp_unslash( $_GET['sw_queued'] ) ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	require_once SOURCEWEB_DIR . 'includes/class-sourceweb-feeds-table.php';
	$table = new SourceWeb_Feeds_Table();
	$table->prepare_items();
	$settings = add_query_arg( array( 'page' => 'sourceweb-settings' ), admin_url( 'admin.php' ) );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html__( 'Feeds', 'sourceweb' ); ?></h1>
		<a href="<?php echo esc_url( $settings ); ?>" class="page-title-action"><?php echo esc_html__( 'Edit feeds in Settings', 'sourceweb' ); ?></a>
		<hr class="wp-header-end" />
		<?php if ( $fetched >= 0 ) : ?>
			<div class="notice notice-info is-dismissible"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: items fetched, 2: items queued. */
						__( 'Fetched %1$d item(s); %2$d added to the review queue.', 'sourceweb' ),
						$fetched,
						$queued
					)
				);
				?>
			</p></div>
		<?php endif; ?>
		<p class="description"><?php echo esc_html__( 'Each feed host must be a disclosed, enabled external service before it can be fetched — add feeds under Settings, and they are disclosed automatically.', 'sourceweb' ); ?></p>
		<?php $table->display(); ?>
	</div>
	<?php
}
