<?php
/**
 * Opportunity sources the site fetches itself, once a day. Used where a source blocks the GitHub worker's servers
 * (Wildlife SOS sits behind Cloudflare, which refuses GitHub Actions but answers this host).
 *
 * Same rules as the worker (animal-writes-workers, sources.yaml): factual listing only (title, organisation,
 * place, link), published directly, duplicates skipped by source URL. Items go through aw_ingest().
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Site-fetched sources. */
function aw_site_opportunity_sources() {
	return array(
		array(
			'name' => 'Wildlife SOS careers',
			'org'  => 'Wildlife SOS',
			'url'  => 'https://wildlifesos.org/about-us/careers/',
			'kind' => 'sjm', // WordPress "Simple Job Manager" accordion: .sjm-item > .sjm-title, .sjm-location
			'type' => 'job',
		),
	);
}

/**
 * Roles on a Simple Job Manager page.
 *
 * @param string $html Page HTML.
 * @return array<int, array{title: string, place: string}>
 */
function aw_parse_sjm( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	$xp    = new DOMXPath( $doc );
	$roles = array();
	foreach ( $xp->query( "//div[contains(concat(' ', normalize-space(@class), ' '), ' sjm-item ')]" ) as $item ) {
		$t = $xp->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' sjm-title ')]", $item )->item( 0 );
		$p = $xp->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' sjm-location ')]", $item )->item( 0 );
		$title = $t ? trim( preg_replace( '/\s+/u', ' ', $t->textContent ) ) : '';
		if ( '' !== $title ) {
			$roles[] = array( 'title' => $title, 'place' => $p ? trim( preg_replace( '/\s+/u', ' ', $p->textContent ) ) : '' );
		}
	}
	return $roles;
}

/** Fetch every site source and ingest new roles. Returns counts per source. */
function aw_fetch_site_opportunities() {
	$report = array();
	foreach ( aw_site_opportunity_sources() as $src ) {
		$res = wp_remote_get( $src['url'], array( 'timeout' => 30, 'user-agent' => 'AnimalWritesWorkers/1.0 (+https://animalwrites.in)' ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			$report[ $src['name'] ] = 'fetch failed';
			continue;
		}
		$roles = 'sjm' === $src['kind'] ? aw_parse_sjm( wp_remote_retrieve_body( $res ) ) : array();
		$new   = 0;
		foreach ( $roles as $r ) {
			$summary = $src['org'] . ': ' . $r['title'] . '.' . ( $r['place'] ? ' Location: ' . $r['place'] . '.' : '' ) . " Details and how to apply are on the organisation's page.";
			$req     = new WP_REST_Request( 'POST', '/aw/v1/ingest' );
			$req->set_body_params(
				array(
					'type'             => 'opportunity',
					'title'            => $r['title'],
					'summary'          => $summary,
					'source_url'       => strtok( $src['url'], '#' ) . '#' . sanitize_title( $r['title'] ),
					'source_name'      => $src['name'],
					'source_date'      => wp_date( 'Y-m-d' ),
					'org_name'         => $src['org'],
					'place'            => $r['place'],
					'opportunity_type' => $src['type'],
				)
			);
			$out = aw_ingest( $req );
			if ( $out instanceof WP_REST_Response && 'created' === ( $out->get_data()['status'] ?? '' ) ) {
				$new++;
			}
		}
		$report[ $src['name'] ] = count( $roles ) . " roles, $new new";
	}
	update_option( 'aw_site_opportunities_last', array( 'at' => wp_date( 'c' ), 'report' => $report ), false );
	return $report;
}
// Not fetched (owner, 9 Oct 2026): add_action( 'aw_daily', 'aw_fetch_site_opportunities' ); turns it back on.
