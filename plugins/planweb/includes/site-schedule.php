<?php
/**
 * The site's own schedule inside PlanWeb (owner, 14 Nov 2026: one schedule, not two). PlanWeb reads, live and read
 * only, what WordPress and FluentCRM already schedule, and shows it beside its own entries on the Dashboard, in the
 * month grid, in the WordPress Dashboard box and in the calendar feed. Nothing is copied into PlanWeb and PlanWeb runs
 * no scheduler of its own: dates are still set in the post editor (core's future status and its publish_future_post
 * event) and in FluentCRM.
 *
 *   Posts       WP_Query, post_status publish and future, by post_date (site time). A future post whose time has
 *               passed by more than 5 minutes is "Missed schedule" (core's cron did not publish it).
 *   Newsletters FluentCRM's campaigns table (fc_campaigns, type campaign) by scheduled_at: Scheduled, Sending, Paused,
 *               Sent. Titles only; never recipients.
 *
 * The whole module has its own switch (option planweb_site_on, on unless switched off): Kits → Modules → PlanWeb
 * and PlanWeb → Settings → Site schedule. Which sources show is the option planweb_site_types (post type slugs, and "fluentcrm"); by default every public post
 * type except the bulk imports (News, Directory) and attachments, plus FluentCRM when it is there.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option: the site schedule's own switch ('1' on, '' off). Not set: on. Kits → Modules → PlanWeb, and Settings. */
const PLANWEB_SITE_ON = 'planweb_site_on';

/** Option: the sources that show (post type slugs and "fluentcrm"). Not set: the defaults. */
const PLANWEB_SITE_TYPES = 'planweb_site_types';

/** Off by default: imported in bulk (hundreds or thousands a month), they would bury the plan. */
const PLANWEB_SITE_SKIP = array( 'aw_news', 'aw_listing' );

/** The FluentCRM source's slug in the option. */
const PLANWEB_SITE_FLUENT = 'fluentcrm';

/** Statuses of site items (not PlanWeb statuses: they are set by WordPress and FluentCRM). */
const PLANWEB_SITE_STATUSES = array(
	'scheduled' => 'Scheduled',
	'missed'    => 'Missed schedule',
	'published' => 'Published',
	'sending'   => 'Sending',
	'paused'    => 'Paused',
	'sent'      => 'Sent',
);

/** Most site items read for one range (a month grid, the feed: about 100 posts a month with News and Directory off). */
const PLANWEB_SITE_MAX = 1000;

/** Whether the site schedule shows at all. Off: PlanWeb shows only its own entries, as before. */
function planweb_site_on(): bool {
	return '1' === (string) get_option( PLANWEB_SITE_ON, '1' );
}

/**
 * Switch the site schedule on or off. Nothing is written when it is already so.
 *
 * @param bool $on On.
 * @return bool Whether the option was written.
 */
function planweb_site_on_save( bool $on ): bool {
	if ( planweb_site_on() === $on && null !== get_option( PLANWEB_SITE_ON, null ) ) {
		return false;
	}
	update_option( PLANWEB_SITE_ON, $on ? '1' : '', false );
	return true;
}

/** The FluentCRM campaigns table, or '' when FluentCRM is not there. */
function planweb_site_fluent_table(): string {
	global $wpdb;
	static $table = null;
	if ( null === $table ) {
		$name  = $wpdb->prefix . 'fc_campaigns';
		$table = $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ) ? $name : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- whether FluentCRM's table exists; once per request.
	}
	return $table;
}

/**
 * Every source that can show: slug => label.
 *
 * @return array<string, string>
 */
function planweb_site_choices(): array {
	$out = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $obj ) {
		if ( 'attachment' === $slug || PLANWEB_TYPE === $slug ) {
			continue;
		}
		$out[ $slug ] = (string) $obj->labels->name;
	}
	if ( '' !== planweb_site_fluent_table() ) {
		$out[ PLANWEB_SITE_FLUENT ] = __( 'Newsletters (FluentCRM)', 'planweb' );
	}
	return $out;
}

/**
 * The sources that show.
 *
 * @return string[]
 */
function planweb_site_types(): array {
	$choices = planweb_site_choices();
	$stored  = get_option( PLANWEB_SITE_TYPES, null );
	$picked  = is_array( $stored ) ? array_map( 'strval', $stored ) : array_diff( array_keys( $choices ), PLANWEB_SITE_SKIP );
	return array_values( array_intersect( array_keys( $choices ), $picked ) );
}

/**
 * Save the sources that show. Unknown slugs are dropped; nothing is written when nothing changes.
 *
 * @param string[] $types Slugs.
 * @return bool Whether the option was written.
 */
function planweb_site_types_save( array $types ): bool {
	$types = array_values( array_intersect( array_keys( planweb_site_choices() ), array_map( 'strval', $types ) ) );
	if ( planweb_site_types() === $types && null !== get_option( PLANWEB_SITE_TYPES, null ) ) {
		return false;
	}
	update_option( PLANWEB_SITE_TYPES, $types, false );
	return true;
}

/**
 * The site's schedule between two dates (inclusive, site time), oldest first, shaped like PlanWeb rows: source
 * (post or newsletter), uid, id, date, time, title, type (site or email), typeText, channels, status, statusText,
 * link (public address once published), editUrl (for someone who may edit it).
 *
 * @param string $from Y-m-d.
 * @param string $to   Y-m-d.
 * @return array[]
 */
function planweb_site_items( string $from, string $to ): array {
	if ( ! planweb_site_on() ) {
		return array(); // Switched off: nothing from the site anywhere (Dashboard, calendar, WP box, feed).
	}
	$types = planweb_site_types();
	$posts = array_values( array_diff( $types, array( PLANWEB_SITE_FLUENT ) ) );
	$items = array();
	$now   = time();
	if ( $posts ) {
		$q = new WP_Query(
			array(
				'post_type'              => $posts,
				'post_status'            => array( 'publish', 'future' ),
				'date_query'             => array(
					array(
						'after'     => $from . ' 00:00:00',
						'before'    => $to . ' 23:59:59',
						'inclusive' => true,
						'column'    => 'post_date',
					),
				),
				'posts_per_page'         => PLANWEB_SITE_MAX,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- the schedule as stored: no front-end re-sorting (aw_news).
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $q->posts as $p ) {
			$type    = get_post_type_object( $p->post_type );
			$future  = 'future' === $p->post_status;
			$late    = $future && strtotime( $p->post_date_gmt . ' UTC' ) < $now - 5 * MINUTE_IN_SECONDS;
			$status  = $late ? 'missed' : ( $future ? 'scheduled' : 'published' );
			$items[] = array(
				'source'     => 'post',
				'uid'        => 'planweb-site-post-' . $p->ID,
				'id'         => (int) $p->ID,
				'date'       => substr( $p->post_date, 0, 10 ),
				'time'       => substr( $p->post_date, 11, 5 ),
				'title'      => wp_strip_all_tags( $p->post_title ),
				'type'       => 'site',
				'typeText'   => $type ? (string) $type->labels->singular_name : $p->post_type,
				'channels'   => array( 'site' ),
				'status'     => $status,
				'statusText' => PLANWEB_SITE_STATUSES[ $status ],
				'link'       => $future ? '' : (string) get_permalink( $p ),
				'editUrl'    => (string) get_edit_post_link( $p->ID, 'raw' ),
				'modified'   => (string) $p->post_modified_gmt,
			);
		}
	}
	$table = in_array( PLANWEB_SITE_FLUENT, $types, true ) ? planweb_site_fluent_table() : '';
	if ( '' !== $table ) {
		global $wpdb;
		$map  = array(
			'scheduled' => 'scheduled',
			'pending'   => 'scheduled',
			'working'   => 'sending',
			'paused'    => 'paused',
			'archived'  => 'sent',
		);
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- FluentCRM's own table, read only; a month at most.
			$wpdb->prepare(
				'SELECT id, title, status, scheduled_at, updated_at FROM %i WHERE type = %s AND scheduled_at BETWEEN %s AND %s AND status IN (' . implode( ',', array_fill( 0, count( $map ), '%s' ) ) . ') ORDER BY scheduled_at ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
				array_merge( array( $table, 'campaign', $from . ' 00:00:00', $to . ' 23:59:59' ), array_keys( $map ), array( PLANWEB_SITE_MAX ) )
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$status  = $map[ $r['status'] ] ?? 'scheduled';
			$items[] = array(
				'source'     => 'newsletter',
				'uid'        => 'planweb-site-newsletter-' . (int) $r['id'],
				'id'         => (int) $r['id'],
				'date'       => substr( (string) $r['scheduled_at'], 0, 10 ),
				'time'       => substr( (string) $r['scheduled_at'], 11, 5 ),
				'title'      => wp_strip_all_tags( (string) $r['title'] ),
				'type'       => 'email',
				'typeText'   => __( 'Newsletter', 'planweb' ),
				'channels'   => array( 'newsletter' ),
				'status'     => $status,
				'statusText' => PLANWEB_SITE_STATUSES[ $status ],
				'link'       => '',
				'editUrl'    => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=fluentcrm-admin#/email/campaigns/' . (int) $r['id'] . '/view' ) : '',
				'modified'   => (string) get_gmt_from_date( (string) $r['updated_at'] ),
			);
		}
	}
	usort( $items, fn( $a, $b ) => strcmp( $a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time'] ) );
	return $items;
}

/**
 * The Dashboard's site counts (the background job's work): per range, what is scheduled from today; the missed ones in
 * the last 30 days; the next one.
 *
 * @param string $today Y-m-d.
 * @return array{ahead: array<int, int>, missed: int, next: array|null}
 */
function planweb_site_counts( string $today ): array {
	$items = planweb_site_items( planweb_add_days( $today, -30 ), planweb_add_days( $today, max( PLANWEB_RANGES ) - 1 ) );
	$c     = array(
		'ahead'  => array_fill_keys( PLANWEB_RANGES, 0 ),
		'missed' => 0,
		'next'   => null,
	);
	foreach ( $items as $i ) {
		if ( 'missed' === $i['status'] ) {
			++$c['missed'];
			continue;
		}
		if ( $i['date'] < $today || ! in_array( $i['status'], array( 'scheduled', 'paused' ), true ) ) {
			continue;
		}
		foreach ( PLANWEB_RANGES as $r ) {
			if ( $i['date'] <= planweb_add_days( $today, $r - 1 ) ) {
				++$c['ahead'][ $r ];
			}
		}
		if ( null === $c['next'] ) {
			$c['next'] = array_intersect_key( $i, array_flip( array( 'date', 'time', 'title', 'typeText' ) ) );
		}
	}
	return $c;
}
