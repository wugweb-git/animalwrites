<?php
/**
 * Back-compat aliases for the keyword engine (the aw_kw_ and aw_si_ helpers became insightweb_kw_ on 17 Nov 2026).
 *
 * The keyword table and its helpers are shared with SourceWeb (aw-sourcing), InsightWeb and RankWeb
 * (aw-seo-extras), which still call the old aw_ names. These thin shims keep them working until those
 * plugins migrate to the insightweb_kw_ names. Remove this file once no plugin references the aw_ names.
 *
 * The keyword table's physical name (prefix + aw_si_keywords / _kw_map) and the version option aw_kw_db
 * are DATA, not identifiers: they keep their stored names until the versioned DB migration renames them.
 *
 * Each constant alias is guarded on its source constant being defined, so the file is safe to load even
 * when only part of the engine (keyword-model.php without keyword-table.php) is present, as in unit tests.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'aw_si_table' ) ) {
	function aw_si_table( ...$args ) { return insightweb_kw_table( ...$args ); }
}
if ( ! function_exists( 'aw_si_norm' ) ) {
	function aw_si_norm( ...$args ) { return insightweb_kw_norm( ...$args ); }
}
if ( ! function_exists( 'aw_si_now' ) ) {
	function aw_si_now( ...$args ) { return insightweb_kw_now( ...$args ); }
}
if ( ! function_exists( 'aw_si_is_brand' ) ) {
	function aw_si_is_brand( ...$args ) { return insightweb_kw_is_brand( ...$args ); }
}
if ( ! function_exists( 'aw_si_lang' ) ) {
	function aw_si_lang( ...$args ) { return insightweb_kw_lang( ...$args ); }
}
if ( ! function_exists( 'aw_si_roles' ) ) {
	function aw_si_roles( ...$args ) { return insightweb_kw_roles( ...$args ); }
}
if ( ! function_exists( 'aw_si_reset_sql' ) ) {
	function aw_si_reset_sql( ...$args ) { return insightweb_kw_reset_sql( ...$args ); }
}
if ( ! function_exists( 'aw_kw_attach_maps' ) ) {
	function aw_kw_attach_maps( ...$args ) { return insightweb_kw_attach_maps( ...$args ); }
}
if ( ! function_exists( 'aw_kw_create' ) ) {
	function aw_kw_create( ...$args ) { return insightweb_kw_create( ...$args ); }
}
if ( ! function_exists( 'aw_kw_decode' ) ) {
	function aw_kw_decode( ...$args ) { return insightweb_kw_decode( ...$args ); }
}
if ( ! function_exists( 'aw_kw_get' ) ) {
	function aw_kw_get( ...$args ) { return insightweb_kw_get( ...$args ); }
}
if ( ! function_exists( 'aw_kw_id_by_norm' ) ) {
	function aw_kw_id_by_norm( ...$args ) { return insightweb_kw_id_by_norm( ...$args ); }
}
if ( ! function_exists( 'aw_kw_install' ) ) {
	function aw_kw_install( ...$args ) { return insightweb_kw_install( ...$args ); }
}
if ( ! function_exists( 'aw_kw_map_set' ) ) {
	function aw_kw_map_set( ...$args ) { return insightweb_kw_map_set( ...$args ); }
}
if ( ! function_exists( 'aw_kw_maps' ) ) {
	function aw_kw_maps( ...$args ) { return insightweb_kw_maps( ...$args ); }
}
if ( ! function_exists( 'aw_kw_ready' ) ) {
	function aw_kw_ready( ...$args ) { return insightweb_kw_ready( ...$args ); }
}
if ( ! function_exists( 'aw_kw_role_slugs' ) ) {
	function aw_kw_role_slugs( ...$args ) { return insightweb_kw_role_slugs( ...$args ); }
}
if ( ! function_exists( 'aw_kw_roles_check' ) ) {
	function aw_kw_roles_check( ...$args ) { return insightweb_kw_roles_check( ...$args ); }
}
if ( ! function_exists( 'aw_kw_roles_to_slugs' ) ) {
	function aw_kw_roles_to_slugs( ...$args ) { return insightweb_kw_roles_to_slugs( ...$args ); }
}
if ( ! function_exists( 'aw_kw_rows' ) ) {
	function aw_kw_rows( ...$args ) { return insightweb_kw_rows( ...$args ); }
}
if ( ! function_exists( 'aw_kw_set_roles' ) ) {
	function aw_kw_set_roles( ...$args ) { return insightweb_kw_set_roles( ...$args ); }
}
if ( ! function_exists( 'aw_kw_spellings' ) ) {
	function aw_kw_spellings( ...$args ) { return insightweb_kw_spellings( ...$args ); }
}
if ( ! function_exists( 'aw_kw_update' ) ) {
	function aw_kw_update( ...$args ) { return insightweb_kw_update( ...$args ); }
}
if ( ! function_exists( 'aw_kw_variants_list' ) ) {
	function aw_kw_variants_list( ...$args ) { return insightweb_kw_variants_list( ...$args ); }
}

defined( 'INSIGHTWEB_KW_DB_VERSION' ) && ! defined( 'AW_KW_DB_VERSION' ) && define( 'AW_KW_DB_VERSION', INSIGHTWEB_KW_DB_VERSION );
defined( 'INSIGHTWEB_KW_DB_OPTION' ) && ! defined( 'AW_KW_DB_OPTION' ) && define( 'AW_KW_DB_OPTION', INSIGHTWEB_KW_DB_OPTION );
defined( 'INSIGHTWEB_KW_ROLE_SOURCING' ) && ! defined( 'AW_SI_ROLE_SOURCING' ) && define( 'AW_SI_ROLE_SOURCING', INSIGHTWEB_KW_ROLE_SOURCING );
defined( 'INSIGHTWEB_KW_ROLE_FOCUS' ) && ! defined( 'AW_SI_ROLE_FOCUS' ) && define( 'AW_SI_ROLE_FOCUS', INSIGHTWEB_KW_ROLE_FOCUS );
defined( 'INSIGHTWEB_KW_ROLE_TRACKED' ) && ! defined( 'AW_SI_ROLE_TRACKED' ) && define( 'AW_SI_ROLE_TRACKED', INSIGHTWEB_KW_ROLE_TRACKED );
defined( 'INSIGHTWEB_KW_ROLE_BRAND' ) && ! defined( 'AW_SI_ROLE_BRAND' ) && define( 'AW_SI_ROLE_BRAND', INSIGHTWEB_KW_ROLE_BRAND );
defined( 'INSIGHTWEB_KW_ROLE_SEARCHED' ) && ! defined( 'AW_SI_ROLE_SEARCHED' ) && define( 'AW_SI_ROLE_SEARCHED', INSIGHTWEB_KW_ROLE_SEARCHED );
defined( 'INSIGHTWEB_KW_ROLE_INCLUDE' ) && ! defined( 'AW_KW_ROLE_INCLUDE' ) && define( 'AW_KW_ROLE_INCLUDE', INSIGHTWEB_KW_ROLE_INCLUDE );
defined( 'INSIGHTWEB_KW_ROLE_ANIMAL' ) && ! defined( 'AW_KW_ROLE_ANIMAL' ) && define( 'AW_KW_ROLE_ANIMAL', INSIGHTWEB_KW_ROLE_ANIMAL );
defined( 'INSIGHTWEB_KW_ROLE_CONTEXT' ) && ! defined( 'AW_KW_ROLE_CONTEXT' ) && define( 'AW_KW_ROLE_CONTEXT', INSIGHTWEB_KW_ROLE_CONTEXT );
defined( 'INSIGHTWEB_KW_ROLE_EXCLUDE' ) && ! defined( 'AW_KW_ROLE_EXCLUDE' ) && define( 'AW_KW_ROLE_EXCLUDE', INSIGHTWEB_KW_ROLE_EXCLUDE );
defined( 'INSIGHTWEB_KW_ROLE_SEARCH' ) && ! defined( 'AW_KW_ROLE_SEARCH' ) && define( 'AW_KW_ROLE_SEARCH', INSIGHTWEB_KW_ROLE_SEARCH );
defined( 'INSIGHTWEB_KW_ROLE_PRESS' ) && ! defined( 'AW_KW_ROLE_PRESS' ) && define( 'AW_KW_ROLE_PRESS', INSIGHTWEB_KW_ROLE_PRESS );
defined( 'INSIGHTWEB_KW_ROLE_CATEGORY' ) && ! defined( 'AW_KW_ROLE_CATEGORY' ) && define( 'AW_KW_ROLE_CATEGORY', INSIGHTWEB_KW_ROLE_CATEGORY );
defined( 'INSIGHTWEB_KW_SOURCING_ROLES' ) && ! defined( 'AW_KW_SOURCING_ROLES' ) && define( 'AW_KW_SOURCING_ROLES', INSIGHTWEB_KW_SOURCING_ROLES );
defined( 'INSIGHTWEB_KW_WORKER_ROLES' ) && ! defined( 'AW_KW_WORKER_ROLES' ) && define( 'AW_KW_WORKER_ROLES', INSIGHTWEB_KW_WORKER_ROLES );
defined( 'INSIGHTWEB_KW_OWNER_ROLES' ) && ! defined( 'AW_KW_OWNER_ROLES' ) && define( 'AW_KW_OWNER_ROLES', INSIGHTWEB_KW_OWNER_ROLES );
defined( 'INSIGHTWEB_KW_RESET_COLUMNS' ) && ! defined( 'AW_SI_RESET_COLUMNS' ) && define( 'AW_SI_RESET_COLUMNS', INSIGHTWEB_KW_RESET_COLUMNS );
defined( 'INSIGHTWEB_KW_BRAND' ) && ! defined( 'AW_SI_BRAND' ) && define( 'AW_SI_BRAND', INSIGHTWEB_KW_BRAND );
defined( 'INSIGHTWEB_KW_NOT_PLURAL' ) && ! defined( 'AW_SI_NOT_PLURAL' ) && define( 'AW_SI_NOT_PLURAL', INSIGHTWEB_KW_NOT_PLURAL );
