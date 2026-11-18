<?php
/**
 * RankWeb PRO, phase H: agent tools (docs/rankweb-pro-audit.md, H). RankWeb's work as abilities on the WordPress
 * Abilities API (6.9+); where the MCP adapter is installed it exposes them to AI agents over MCP.
 *
 * Read abilities (rankweb/get-seo, audit-summary, list-orphans) are registered when the module is on. The write
 * abilities (rankweb/set-seo, add-redirect, set-schema) are registered only when "Allow writes" is on
 * (RankWeb → Settings → Agent tools), and each still checks its capability — set-seo and set-schema need edit_post on
 * the item, the others manage_options. The Abilities API validates the input against the schema before the permission
 * check. If the Abilities API is not present (before WordPress 6.9, or without the feature plugin), nothing is
 * registered and nothing breaks.
 *
 * Module "agent" (RankWeb → Settings → Modules). No scheduled job.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-agent.php';

add_action(
	'wp_abilities_api_init',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			AW_Agent::CATEGORY,
			array(
				'label'       => __( 'RankWeb', 'aw-seo-extras' ),
				'description' => __( 'Read and change RankWeb\'s SEO data.', 'aw-seo-extras' ),
			)
		);
		$specs = aw_agent_specs();
		foreach ( AW_Agent::enabled( (bool) AW_AGENT_WRITES ) as $ability ) {
			$spec = $specs[ $ability['name'] ] ?? null;
			if ( ! $spec ) {
				continue;
			}
			wp_register_ability(
				$ability['name'],
				array(
					'label'               => $spec['label'],
					'description'         => $ability['summary'],
					'category'            => AW_Agent::CATEGORY,
					'input_schema'        => $spec['input'],
					'execute_callback'    => $spec['execute'],
					'permission_callback' => $spec['permission'],
					'meta'                => array( 'public' => true ),
				)
			);
		}
	}
);

/** One object input schema. */
function aw_agent_schema( array $properties, array $required = array() ): array {
	return array(
		'type'                 => 'object',
		'properties'           => $properties,
		'required'             => $required,
		'additionalProperties' => false,
	);
}

/**
 * Each ability's label, input schema, permission callback and execute callback, keyed by name.
 *
 * @return array<string,array>
 */
function aw_agent_specs(): array {
	$post_id   = array(
		'post_id' => array(
			'type'    => 'integer',
			'minimum' => 1,
		),
	);
	$can_edit  = fn( $input ) => current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
	$can_admin = fn() => current_user_can( 'manage_options' );
	return array(
		'rankweb/get-seo'       => array(
			'label'      => __( 'Get SEO', 'aw-seo-extras' ),
			'input'      => aw_agent_schema( $post_id, array( 'post_id' ) ),
			'permission' => $can_edit,
			'execute'    => fn( $input ) => aw_agent_get_seo( (int) $input['post_id'] ),
		),
		'rankweb/audit-summary' => array(
			'label'      => __( 'Internal links audit summary', 'aw-seo-extras' ),
			'input'      => aw_agent_schema( array() ),
			'permission' => $can_admin,
			'execute'    => fn() => function_exists( 'aw_link_audit_summary' ) ? aw_link_audit_summary() : new WP_Error( 'aw_agent', __( 'The internal links audit is not available.', 'aw-seo-extras' ) ),
		),
		'rankweb/list-orphans'  => array(
			'label'      => __( 'List orphans', 'aw-seo-extras' ),
			'input'      => aw_agent_schema(
				array(
					'limit' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 500,
					),
				)
			),
			'permission' => $can_admin,
			'execute'    => fn( $input ) => aw_agent_list_orphans( (int) ( $input['limit'] ?? 100 ) ),
		),
		'rankweb/set-seo'       => array(
			'label'      => __( 'Set SEO', 'aw-seo-extras' ),
			'input'      => aw_agent_schema(
				array(
					'post_id'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'title'         => array( 'type' => 'string' ),
					'description'   => array( 'type' => 'string' ),
					'focus_keyword' => array( 'type' => 'string' ),
					'link_keywords' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'canonical'     => array( 'type' => 'string' ),
				),
				array( 'post_id' )
			),
			'permission' => $can_edit,
			'execute'    => fn( $input ) => aw_agent_set_seo( (array) $input ),
		),
		'rankweb/add-redirect'  => array(
			'label'      => __( 'Add redirect', 'aw-seo-extras' ),
			'input'      => aw_agent_schema(
				array(
					'source'     => array( 'type' => 'string' ),
					'target'     => array( 'type' => 'string' ),
					'code'       => array( 'type' => 'integer' ),
					'comparison' => array(
						'type' => 'string',
						'enum' => array( 'exact', 'regex' ),
					),
				),
				array( 'source' )
			),
			'permission' => $can_admin,
			'execute'    => fn( $input ) => aw_agent_add_redirect( (array) $input ),
		),
		'rankweb/set-schema'    => array(
			'label'      => __( 'Set custom schema', 'aw-seo-extras' ),
			'input'      => aw_agent_schema(
				array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'jsonld'  => array( 'type' => 'string' ),
				),
				array( 'post_id', 'jsonld' )
			),
			'permission' => $can_edit,
			'execute'    => fn( $input ) => aw_agent_set_schema( (int) ( $input['post_id'] ?? 0 ), (string) ( $input['jsonld'] ?? '' ) ),
		),
	);
}

/* ---------- The callbacks ---------- */

/**
 * An item's SEO fields, or a WP_Error.
 *
 * @param int $id Item ID.
 * @return array|WP_Error
 */
function aw_agent_get_seo( int $id ) {
	if ( ! get_post( $id ) ) {
		return new WP_Error( 'aw_agent', __( 'No such item.', 'aw-seo-extras' ) );
	}
	$links  = function_exists( 'aw_link_keywords' ) ? aw_link_keywords( $id ) : (array) get_post_meta( $id, '_aw_link_keywords', true );
	$robots = get_post_meta( $id, 'rank_math_robots', true );
	return array(
		'post_id'       => $id,
		'title'         => (string) get_post_meta( $id, 'rank_math_title', true ),
		'description'   => (string) get_post_meta( $id, 'rank_math_description', true ),
		'focus_keyword' => (string) get_post_meta( $id, 'rank_math_focus_keyword', true ),
		'link_keywords' => array_values( (array) $links ),
		'canonical'     => (string) get_post_meta( $id, 'rank_math_canonical_url', true ),
		'robots'        => is_array( $robots ) ? array_values( $robots ) : array(),
		'url'           => (string) get_permalink( $id ),
	);
}

/**
 * Set an item's SEO fields from the input, then return the new values.
 *
 * @param array $input The ability input.
 * @return array|WP_Error
 */
function aw_agent_set_seo( array $input ) {
	$id = (int) ( $input['post_id'] ?? 0 );
	if ( ! get_post( $id ) ) {
		return new WP_Error( 'aw_agent', __( 'No such item.', 'aw-seo-extras' ) );
	}
	$meta = array(
		'title'         => 'rank_math_title',
		'description'   => 'rank_math_description',
		'focus_keyword' => 'rank_math_focus_keyword',
		'canonical'     => 'rank_math_canonical_url',
	);
	foreach ( AW_Agent::writable_seo( $input ) as $key => $value ) {
		if ( 'link_keywords' === $key ) {
			$list = function_exists( 'aw_link_keywords_clean' ) ? aw_link_keywords_clean( (array) $value ) : array_map( 'sanitize_text_field', (array) $value );
			if ( $list ) {
				update_post_meta( $id, '_aw_link_keywords', $list );
			} else {
				delete_post_meta( $id, '_aw_link_keywords' );
			}
			continue;
		}
		$clean = 'description' === $key ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
		if ( '' === $clean ) {
			delete_post_meta( $id, $meta[ $key ] );
		} else {
			update_post_meta( $id, $meta[ $key ], $clean );
		}
	}
	if ( function_exists( 'aw_link_index_item' ) ) {
		aw_link_index_item( $id ); // a cleared focus keyword or a changed link keyword is not caught by the meta hooks
	}
	return aw_agent_get_seo( $id );
}

/**
 * Orphan items from the last audit.
 *
 * @param int $limit How many at most.
 * @return array|WP_Error
 */
function aw_agent_list_orphans( int $limit ) {
	global $wpdb;
	if ( ! function_exists( 'aw_link_table' ) || get_option( 'aw_link_audit_db' ) !== '1' ) {
		return new WP_Error( 'aw_agent', __( 'The internal links audit has not run.', 'aw-seo-extras' ) );
	}
	$limit = max( 1, min( 500, $limit ) );
	$ids   = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM %i WHERE flags LIKE %s ORDER BY post_id LIMIT %d', aw_link_table( 'audit' ), '%,orphan,%', $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- RankWeb's own table, read for an agent
	$out   = array();
	foreach ( array_map( 'intval', $ids ) as $id ) {
		$out[] = array(
			'post_id' => $id,
			'title'   => (string) get_the_title( $id ),
			'url'     => (string) get_permalink( $id ),
		);
	}
	return array(
		'count' => count( $out ),
		'items' => $out,
	);
}

/**
 * Add a redirect to Rank Math's list.
 *
 * @param array $input The ability input.
 * @return array|WP_Error
 */
function aw_agent_add_redirect( array $input ) {
	if ( ! function_exists( 'aw_rank_math_redirect' ) ) {
		return new WP_Error( 'aw_agent', __( 'Redirects are not available.', 'aw-seo-extras' ) );
	}
	require_once __DIR__ . '/class-aw-redirects.php';
	$v = AW_Redirects::validate(
		array(
			'source'     => (string) ( $input['source'] ?? '' ),
			'target'     => (string) ( $input['target'] ?? '' ),
			'code'       => (string) ( $input['code'] ?? 301 ),
			'comparison' => (string) ( $input['comparison'] ?? 'exact' ),
		)
	);
	if ( '' !== $v['error'] ) {
		return new WP_Error( 'aw_agent', $v['error'] );
	}
	$target  = in_array( $v['code'], AW_Redirects::GONE, true ) ? '' : ( preg_match( '~^https?://~i', $v['target'] ) ? $v['target'] : home_url( '/' . ltrim( $v['target'], '/' ) ) );
	$pattern = 'regex' === $v['comparison'] ? $v['source'] : trim( $v['source'], '/' );
	$id      = aw_rank_math_redirect( $pattern, $target, $v['comparison'], $v['code'] );
	return $id ? array( 'id' => (int) $id ) : new WP_Error( 'aw_agent', __( 'The redirect was already in the list or could not be saved.', 'aw-seo-extras' ) );
}

/**
 * Set an item's custom JSON-LD (checked first).
 *
 * @param int    $id     Item ID.
 * @param string $jsonld The JSON-LD.
 * @return array|WP_Error
 */
function aw_agent_set_schema( int $id, string $jsonld ) {
	if ( ! class_exists( 'AW_Schema' ) ) {
		require_once __DIR__ . '/class-aw-schema.php';
	}
	if ( ! get_post( $id ) ) {
		return new WP_Error( 'aw_agent', __( 'No such item.', 'aw-seo-extras' ) );
	}
	$parsed = AW_Schema::parse( $jsonld );
	if ( '' !== $parsed['error'] ) {
		return new WP_Error( 'aw_agent', $parsed['error'] );
	}
	if ( '' === trim( $jsonld ) ) {
		delete_post_meta( $id, '_aw_jsonld' );
	} else {
		update_post_meta( $id, '_aw_jsonld', $jsonld );
	}
	return array(
		'post_id' => $id,
		'nodes'   => count( $parsed['nodes'] ),
	);
}
