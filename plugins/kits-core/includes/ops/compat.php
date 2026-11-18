<?php
/**
 * The ops functions' older names (Animal Writes Core, before 19 Nov 2026), as thin wrappers over the kits_ functions,
 * for callers that still use them: the rest of Animal Writes Core until its files move (content types, hubs,
 * campaigns), the server's cron line (`wp eval 'aw_dispatch_workflow(...)'`) and code on open branches. Every caller
 * in the plugins SourceWeb, RankWeb, InsightWeb and DirectoryWeb uses the new names. Each wrapper goes once nothing
 * calls it (docs/awcore-retirement.md, section 6).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- the older names, kept for their callers.

if ( ! function_exists( 'aw_dispatch_workflow' ) ) {
	/**
	 * Older name of kits_dispatch_workflow().
	 *
	 * @param string $file   Workflow file.
	 * @param array  $inputs Inputs.
	 * @return true|WP_Error
	 */
	function aw_dispatch_workflow( $file, $inputs = array() ) {
		return kits_dispatch_workflow( $file, $inputs );
	}
}

if ( ! function_exists( 'aw_cron_last' ) ) {
	/** Older name of kits_cron_last(). */
	function aw_cron_last() {
		return kits_cron_last();
	}
}

if ( ! function_exists( 'aw_cron_span' ) ) {
	/**
	 * Older name of kits_cron_span().
	 *
	 * @param int $s Seconds.
	 */
	function aw_cron_span( $s ) {
		return kits_cron_span( $s );
	}
}

if ( ! function_exists( 'aw_keep_modified' ) ) {
	/**
	 * Older name of kits_keep_modified().
	 *
	 * @param callable $save The save.
	 * @return mixed
	 */
	function aw_keep_modified( callable $save ) {
		return kits_keep_modified( $save );
	}
}

if ( ! function_exists( 'aw_retention_state' ) ) {
	/**
	 * Older name of kits_retention_state().
	 *
	 * @param int|WP_Post $post Post.
	 */
	function aw_retention_state( $post ) {
		return kits_retention_state( $post );
	}
}

if ( ! function_exists( 'aw_open_opportunities_meta_query' ) ) {
	/** Older name of kits_open_opportunities_meta_query(). */
	function aw_open_opportunities_meta_query() {
		return kits_open_opportunities_meta_query();
	}
}

if ( ! function_exists( 'aw_core_simple_table' ) ) {
	/**
	 * Older name of kits_simple_table().
	 *
	 * @param string $plural Table name.
	 * @param array  $cols   Columns.
	 * @param array  $rows   Rows.
	 * @param array  $opts   Options.
	 */
	function aw_core_simple_table( $plural, array $cols, array $rows, array $opts = array() ) {
		kits_simple_table( $plural, $cols, $rows, $opts );
	}
}

if ( ! function_exists( 'aw_use_component_style' ) ) {
	/**
	 * Older name of the theme's kits_use_component_style(): this page loads that component's stylesheet.
	 *
	 * @param string $handle Component handle.
	 */
	function aw_use_component_style( string $handle ): void {
		if ( function_exists( 'kits_use_component_style' ) ) {
			kits_use_component_style( $handle );
		}
	}
}

if ( ! function_exists( 'aw_theme_file_uri' ) ) {
	/**
	 * Older name of the theme's kits_site_uri(): the address of a site file, given by its old path below the theme root.
	 *
	 * @param string $file Old path relative to the theme root (e.g. 'assets/img/google-maps.png').
	 */
	function aw_theme_file_uri( string $file ): string {
		$file = (string) preg_replace( '#^assets/#', '', ltrim( $file, '/' ) );
		return function_exists( 'kits_site_uri' ) ? kits_site_uri( $file ) : '';
	}
}
