<?php
/**
 * Back-compat aliases for the sourcing / attribution helpers (renamed aw_* -> kits_* on 17 Nov 2026).
 *
 * The tenant and the sibling plugins (SourceWeb/aw-sourcing, InsightWeb, RankWeb) still call the old aw_ names.
 * Every alias is guarded on function_exists so it never redeclares aw_host/aw_publishers, which SourceWeb also
 * defines (the "whichever loads first wins" pair in sourcing.php). Remove this file once no plugin calls the aw_ names.
 *
 * The option VALUES (aw_publishers, aw_relevance, option aw_sources) and the source post-meta (aw_source_url/_name
 * /_date, _aw_favicon_host) are stored DATA, not identifiers: they are read via dual-read / kept constants and keep
 * their names until the owner-gated migration and the separate workers-writer rename.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aw_host' ) ) {
	function aw_host( ...$args ) { return kits_host( ...$args ); }
}
if ( ! function_exists( 'aw_publishers' ) ) {
	function aw_publishers( ...$args ) { return kits_publishers( ...$args ); }
}
if ( ! function_exists( 'aw_favicon_url' ) ) {
	function aw_favicon_url( ...$args ) { return kits_favicon_url( ...$args ); }
}
if ( ! function_exists( 'aw_sources_config' ) ) {
	function aw_sources_config( ...$args ) { return kits_sources_config( ...$args ); }
}
if ( ! function_exists( 'aw_publisher_of' ) ) {
	function aw_publisher_of( ...$args ) { return kits_publisher_of( ...$args ); }
}
if ( ! function_exists( 'aw_source_row_html' ) ) {
	function aw_source_row_html( ...$args ) { return kits_source_row_html( ...$args ); }
}
if ( ! function_exists( 'aw_rel_time_html' ) ) {
	function aw_rel_time_html( ...$args ) { return kits_rel_time_html( ...$args ); }
}
if ( ! function_exists( 'aw_published_ts' ) ) {
	function aw_published_ts( ...$args ) { return kits_published_ts( ...$args ); }
}
