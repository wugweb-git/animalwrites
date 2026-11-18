<?php
/**
 * The site's WP-CLI commands for Kits → Help → Docs, read from WP-CLI's own registry instead of a list kept by hand
 * (the hand-kept list had drifted: commands missing, commands that no longer exist).
 *
 * WP-CLI registers commands only while it runs, so a web request cannot ask it. Instead, whenever any wp command runs
 * on the server, the commands whose name starts with one of the site's prefixes (filter kits_cli_command_prefixes)
 * are read with their one-line descriptions and kept in the option kits_cli_commands (not autoloaded; written only
 * when the list changed). The Help page shows that list. Nothing to register by hand: a new command shows after the
 * next wp run.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The command names the list keeps: a prefix itself or prefix-…, e.g. kits, kits-x (filter kits_cli_command_prefixes). */
function kits_cli_command_prefixes(): array {
	/**
	 * Filters the WP-CLI command prefixes listed on Kits → Help → Docs.
	 *
	 * @param string[] $prefixes Top-level command names.
	 */
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'kits_cli_command_prefixes', array( 'kits', 'mailweb', 'planweb', 'directoryweb', 'sourceweb', 'insightweb', 'rankweb' ) ) ) ) );
}

/**
 * Whether a top-level command name is one of the site's.
 *
 * @param string   $name     Top-level command name.
 * @param string[] $prefixes kits_cli_command_prefixes().
 */
function kits_cli_command_ours( string $name, array $prefixes ): bool {
	foreach ( $prefixes as $p ) {
		if ( $name === $p || 0 === strpos( $name, $p . '-' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The commands of a registry tree, one row per command that runs (a group's subcommands each get a row; a group with
 * none gets its own): [ 'name' => 'aw crm-setup', 'what' => one-line description ], sorted by name.
 *
 * @param array    $tree     name => [ 'what' => string, 'subs' => tree ].
 * @param string[] $prefixes kits_cli_command_prefixes(); only top-level names are checked.
 * @param string   $parent   The parent's full name (recursion).
 */
function kits_cli_command_rows( array $tree, array $prefixes, string $parent = '' ): array {
	$rows = array();
	foreach ( $tree as $name => $node ) {
		$name = (string) $name;
		if ( '' === $parent && ! kits_cli_command_ours( $name, $prefixes ) ) {
			continue;
		}
		$full = ltrim( $parent . ' ' . $name );
		$subs = (array) ( $node['subs'] ?? array() );
		if ( $subs ) {
			$rows = array_merge( $rows, kits_cli_command_rows( $subs, $prefixes, $full ) );
			continue;
		}
		$rows[] = array(
			'name' => $full,
			'what' => trim( (string) ( $node['what'] ?? '' ) ),
		);
	}
	if ( '' === $parent ) {
		usort( $rows, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	}
	return $rows;
}

/**
 * WP-CLI's registry as a tree (kits_cli_command_rows()); only inside a wp run.
 *
 * @param object|null $cmd A WP-CLI command (root when null).
 */
function kits_cli_registry_tree( $cmd = null ): array {
	if ( null === $cmd ) {
		if ( ! class_exists( 'WP_CLI' ) || ! method_exists( 'WP_CLI', 'get_root_command' ) ) {
			return array();
		}
		$cmd = WP_CLI::get_root_command();
	}
	$tree = array();
	foreach ( (array) $cmd->get_subcommands() as $name => $sub ) {
		$tree[ (string) $name ] = array(
			'what' => method_exists( $sub, 'get_shortdesc' ) ? (string) $sub->get_shortdesc() : '',
			'subs' => method_exists( $sub, 'can_have_subcommands' ) && $sub->can_have_subcommands() ? kits_cli_registry_tree( $sub ) : array(),
		);
	}
	return $tree;
}

/** The commands last seen in a wp run (kits_cli_command_rows() rows), plus any a site adds (filter kits_cli_commands). */
function kits_cli_commands(): array {
	$rows = get_option( 'kits_cli_commands', array() );
	/**
	 * Filters the commands listed on Kits → Help → Docs.
	 *
	 * @param array $rows [ 'name' => 'kits export', 'what' => string ] rows.
	 */
	return (array) apply_filters( 'kits_cli_commands', is_array( $rows ) ? $rows : array() );
}

// Any wp run: keep the list in step with what WP-CLI registered (all plugins are loaded by then).
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_hook(
		'before_run_command',
		function () {
			if ( ! function_exists( 'update_option' ) ) {
				return; // A command that runs without WordPress.
			}
			$rows = kits_cli_command_rows( kits_cli_registry_tree(), kits_cli_command_prefixes() );
			if ( $rows && get_option( 'kits_cli_commands' ) !== $rows ) {
				update_option( 'kits_cli_commands', $rows, false );
			}
		}
	);
}
