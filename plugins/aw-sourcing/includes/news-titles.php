<?php
/**
 * News headlines in sentence case (owner, 20 Oct 2026): letter case only, never a word changed. Press coverage titles
 * follow the same rule (owner, 20 Oct 2026). New titles are cased by the workers on ingest (aw_workers/headline_case.py;
 * a title they could not case safely keeps its original and carries aw_title_case_flag). Existing titles change only
 * from a CSV the owner has reviewed:
 *
 *   wp aw news-titles apply <file.csv> [--dry-run] [--lead-ins] [--type=aw_press]
 *
 * CSV columns: id, before, after (also status, lead_in, without_lead_in from the dry run). A row is applied only when the
 * item is published News (with --type=aw_press: a published press item), its title is still "before", and "after" differs from it in letter case only. The slug and
 * URL never change. With --lead-ins, a row whose without_lead_in the owner approved (non-empty, not "needs rewording")
 * gets that title instead (words removed: only with this flag). Library titles are never touched.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when $after differs from $before in letter case only. */
function aw_news_title_case_only( $before, $after ) {
	return '' !== $after && mb_strlen( $before ) === mb_strlen( $after ) && mb_strtolower( $before ) === mb_strtolower( $after );
}

/**
 * A News headline without a trailing section label or publisher tail (owner, 20 Oct 2026), applied on ingest:
 * "| India News", "| Mumbai news", "| Watch", "| Videos", "| Trending", "- Blog", "| SCC Times", "| Akashvani News",
 * the publisher's own name after " | " or " - ", and after " | " a tail that starts with it ("| DIPR Nagaland
 * Department of …"). A real headline part ("– go vegan", "| World Farm Animals Day 2026") stays. The workers do the same
 * (titles.no_section) and fetch the full headline when a feed cut it off with "…".
 *
 * @param string $title     Headline.
 * @param string $publisher Publisher or source name.
 * @return string
 */
function aw_news_title_tail( $title, $publisher = '' ) {
	$t   = trim( (string) $title );
	$key = static fn( $s ) => preg_replace( '/[^a-z0-9]/', '', preg_replace( '/^the(?=.{4})/', '', strtolower( (string) $s ) ) );
	$pub = $key( $publisher );
	do {
		$before = $t;
		$t      = rtrim( preg_replace( '/\s+[|\-\x{2013}\x{2014}]\s+(?:(?:India|World|City|Mumbai|Delhi|Bengaluru|Kolkata|Chennai|Hyderabad|Pune|Lucknow|Latest|Breaking|Akashvani)\s+news|news|watch|videos?|trending|blogs?|photos?|scc times)\s*$/iu', '', $t ) );
		if ( strlen( $pub ) >= 4 && preg_match( '/^(.{20,}?)\s+([|\-\x{2013}\x{2014}])\s+([^|\-\x{2013}\x{2014}]+)$/u', $t, $m ) ) {
			$tail = $key( $m[3] );
			if ( $tail === $pub || ( '|' === $m[2] && 0 === strpos( $tail, $pub ) ) ) {
				$t = rtrim( $m[1] );
			}
		}
	} while ( $t !== $before );
	return '' !== $t ? $t : trim( (string) $title );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'aw news-titles',
		function ( $args, $assoc ) {
			if ( 'apply' !== ( $args[0] ?? '' ) || empty( $args[1] ) || ! is_readable( $args[1] ) ) {
				WP_CLI::error( 'Usage: wp aw news-titles apply <file.csv> [--dry-run] [--lead-ins] [--type=aw_press]' );
			}
			$type = (string) ( $assoc['type'] ?? 'aw_news' );
			if ( ! in_array( $type, array( 'aw_news', 'aw_press' ), true ) ) {
				WP_CLI::error( '--type is aw_news or aw_press' );
			}
			$dry  = ! empty( $assoc['dry-run'] );
			$lead = ! empty( $assoc['lead-ins'] );
			$fh   = fopen( $args[1], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$head = fgetcsv( $fh );
			$n    = array( 'applied' => 0, 'skipped' => 0 );
			while ( ( $row = fgetcsv( $fh ) ) !== false ) {
				$r      = array_combine( $head, array_pad( $row, count( $head ), '' ) );
				$id     = (int) $r['id'];
				$post   = get_post( $id );
				$before = (string) $r['before'];
				$after  = trim( (string) $r['after'] );
				$strip  = trim( (string) ( $r['without_lead_in'] ?? '' ) );
				$why    = '';
				if ( ! $post || $type !== $post->post_type || 'publish' !== $post->post_status ) {
					$why = 'aw_news' === $type ? 'not published News' : 'not a published press item';
				} elseif ( $post->post_title !== $before ) {
					$why = 'title changed since the CSV';
				} elseif ( $lead && '' !== $strip && 'needs rewording' !== $strip ) {
					$after = $strip; // the owner approved removing the lead-in
				} elseif ( $after === $before ) {
					$why = 'no change';
				} elseif ( ! aw_news_title_case_only( $before, $after ) ) {
					$why = 'more than letter case';
				}
				if ( $why ) {
					++$n['skipped'];
					WP_CLI::log( "skip $id: $why" );
					continue;
				}
				if ( ! $dry ) {
					global $wpdb;
					$save = fn() => wp_update_post( array( 'ID' => $id, 'post_title' => $after, 'post_name' => $post->post_name ) );
					function_exists( 'aw_keep_modified' ) ? aw_keep_modified( $save ) : $save(); // letter case only: the modified date stays
					if ( get_post_field( 'post_name', $id ) !== $post->post_name ) { // a save hook trimmed it (a publisher suffix, 20 Oct 2026): the URL stays
						$wpdb->update( $wpdb->posts, array( 'post_name' => $post->post_name ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						delete_post_meta( $id, '_wp_old_slug', $post->post_name );
						clean_post_cache( $id );
					}
					delete_post_meta( $id, 'aw_title_case_flag' );
				}
				++$n['applied'];
				WP_CLI::log( ( $dry ? 'would set ' : 'set ' ) . "$id: $after" );
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			WP_CLI::success( ( $dry ? 'Dry run: ' : '' ) . $n['applied'] . ' titles ' . ( $dry ? 'would change' : 'changed' ) . ', ' . $n['skipped'] . ' skipped.' );
		}
	);
}
