<?php
/**
 * The agent abilities' manifest and the pure input handling. No WordPress, so tests/AgentTest.php runs it.
 * agent-tools.php registers these with the WordPress Abilities API (6.9+) and the callbacks read and write through the
 * existing RankWeb functions; this class only names the abilities and tidies their input.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The agent abilities. */
final class AW_Agent {

	/** The ability category id (registered before the abilities). */
	const CATEGORY = 'rankweb';

	/** The capability each ability checks: 'read' is edit_post on the item, the others are their own. */
	const CAPS = array( 'read', 'edit_post', 'manage_options' );

	/** The SEO fields an agent may set, and whether each is a single string or a list. */
	const SEO_FIELDS = array(
		'title'         => 'string',
		'description'   => 'string',
		'focus_keyword' => 'string',
		'link_keywords' => 'list',
		'canonical'     => 'string',
	);

	/**
	 * The manifest: each ability's short name (within the rankweb/ namespace), what it does, the capability it needs,
	 * and whether it writes. agent-tools.php turns these into wp_register_ability() calls.
	 *
	 * @return array<int,array{name:string,writes:bool,cap:string,summary:string}>
	 */
	public static function abilities(): array {
		return array(
			array(
				'name'    => 'rankweb/get-seo',
				'writes'  => false,
				'cap'     => 'edit_post',
				'summary' => 'Read an item\'s SEO title, description, focus keyword, link keywords and canonical.',
			),
			array(
				'name'    => 'rankweb/audit-summary',
				'writes'  => false,
				'cap'     => 'manage_options',
				'summary' => 'The internal-links audit summary: items, items with a finding, and the counts per finding.',
			),
			array(
				'name'    => 'rankweb/list-orphans',
				'writes'  => false,
				'cap'     => 'manage_options',
				'summary' => 'Items the last audit found with no internal links in (orphans).',
			),
			array(
				'name'    => 'rankweb/set-seo',
				'writes'  => true,
				'cap'     => 'edit_post',
				'summary' => 'Set an item\'s SEO title, description, focus keyword, link keywords or canonical.',
			),
			array(
				'name'    => 'rankweb/add-redirect',
				'writes'  => true,
				'cap'     => 'manage_options',
				'summary' => 'Add a redirect to Rank Math\'s list (source, target, status code).',
			),
			array(
				'name'    => 'rankweb/set-schema',
				'writes'  => true,
				'cap'     => 'edit_post',
				'summary' => 'Set an item\'s custom JSON-LD (checked before it is saved).',
			),
		);
	}

	/**
	 * The abilities to register: all reads, plus the writes only when they are allowed.
	 *
	 * @param bool $writes Register the write abilities too.
	 * @return array<int,array>
	 */
	public static function enabled( bool $writes ): array {
		return array_values( array_filter( self::abilities(), fn( $a ) => $writes || ! $a['writes'] ) );
	}

	/**
	 * Whether a name is a valid ability name: the rankweb/ namespace, then lowercase letters, digits and dashes.
	 *
	 * @param string $name The ability name.
	 */
	public static function valid_name( string $name ): bool {
		return 1 === preg_match( '~^rankweb/[a-z0-9]+(?:-[a-z0-9]+)*$~', $name );
	}

	/**
	 * The SEO fields in an agent's input, kept to the known keys and typed (strings trimmed, link_keywords a list of
	 * trimmed strings). The caller applies WordPress sanitisers before storing.
	 *
	 * @param array $input The ability input.
	 * @return array Only the keys that were given, typed.
	 */
	public static function writable_seo( array $input ): array {
		$out = array();
		foreach ( self::SEO_FIELDS as $key => $type ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue; // a field not given is left unchanged
			}
			if ( 'list' === $type ) {
				$list        = is_array( $input[ $key ] ) ? $input[ $key ] : preg_split( '/[\r\n,|]+/', (string) $input[ $key ] );
				$out[ $key ] = array_values( array_filter( array_map( fn( $v ) => trim( (string) $v ), (array) $list ), 'strlen' ) );
			} else {
				$out[ $key ] = trim( (string) $input[ $key ] );
			}
		}
		return $out;
	}
}
