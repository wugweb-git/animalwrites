<?php
/**
 * Breadcrumbs: RankWeb owns them end to end (aw-seo-extras/includes/breadcrumbs.php builds Rank Math's trail and
 * breadcrumbs-render.php shows it above the page title). This file is the fallback for when RankWeb is not active:
 * it loads RankWeb's renderer from the sibling plugin folder, so the section, hub and form bands keep their visible
 * trail (Rank Math's own). With no RankWeb folder there is no trail; the bands check
 * rankweb_breadcrumbs_claim() exists.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'plugins_loaded',
	function () {
		if ( function_exists( 'rankweb_breadcrumbs' ) ) {
			return; // RankWeb is active and loaded it.
		}
		$file = dirname( AW_CORE_DIR ) . '/aw-seo-extras/includes/breadcrumbs-render.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
