<?php
/**
 * RankWeb's routes for its screens (docs/rankweb-plan.md §2–§4), under rankweb/v1:
 *
 *   GET  dashboard           manage_categories  the tiles, from the background counts (rankweb-dashboard.php)
 *   POST dashboard/count     manage_categories  ask for a count now (one background event)
 *   GET  settings            manage_options     every group, module and field with today's values
 *   POST settings/<group>    manage_options     save one group (aw_seo_settings_save(), the same as the form); reset: true
 *                                               puts the group's registered settings back to their defaults
 *                                               (aw_seo_settings_reset()); Modules also takes delete_data
 *   POST settings/faq-types  manage_options     "FAQ box on" (aw_faq_off_types, the same as RankWeb → FAQ)
 *   POST settings/keep-listings manage_options  listings kept indexed with no contact (aw_seo_keep_listings)
 *   POST settings/share-image manage_options    the site's default share image (aw_default_share_image)
 *   GET  services            manage_options     the connected services (Rank Math only)
 *
 * Every route checks the capability and, for a signed-in browser, the wp_rest nonce (X-WP-Nonce); an Application
 * Password request has no nonce and is checked by capability only. Signed out: 401. Signed in without the right: 403.
 * The aw/v1 routes the workers use are not touched.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const RANKWEB_REST = 'rankweb/v1';

/**
 * Permission check for a route: signed in, the nonce (for a browser), then the capability.
 *
 * @param string $cap Capability.
 * @return callable
 */
function rankweb_rest_can( string $cap ): callable {
	return function ( WP_REST_Request $request ) use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'aw-seo-extras' ), array( 'status' => 401 ) );
		}
		$app = function_exists( 'rest_get_authenticated_app_password' ) && null !== rest_get_authenticated_app_password();
		if ( ! $app ) {
			$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?? $request->get_param( '_wpnonce' ) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'rest_cookie_invalid_nonce', __( 'The page has expired. Reload it and try again.', 'aw-seo-extras' ), array( 'status' => 403 ) );
			}
		}
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'aw-seo-extras' ), array( 'status' => 403 ) );
		}
		return true;
	};
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			RANKWEB_REST,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( RANKWEB_CAP ),
				'callback'            => fn() => rest_ensure_response( rankweb_dashboard_payload() ),
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/dashboard/count',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( RANKWEB_CAP ),
				'callback'            => fn() => rest_ensure_response( array( 'waiting' => rankweb_health_request() ) ),
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/settings',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'callback'            => fn() => rest_ensure_response( rankweb_settings_payload() ),
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/settings/faq-types',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'args'                => array(
					'on' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
				'callback'            => 'rankweb_rest_save_faq_types',
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/settings/keep-listings',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
				'callback'            => 'rankweb_rest_save_keep_listings',
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/settings/share-image',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'minimum'  => 0,
						'required' => true,
					),
				),
				'callback'            => 'rankweb_rest_save_share_image',
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/settings/(?P<group>[a-z-]+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'args'                => array(
					'group'       => array(
						'type' => 'string',
						'enum' => array_keys( aw_seo_setting_tabs() ),
					),
					'fields'      => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
						'default'              => array(),
					),
					'on'          => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'boolean' ),
						'default'              => array(),
					),
					'reset'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'delete_data' => array(
						'type' => 'boolean',
					),
				),
				'callback'            => 'rankweb_rest_save_group',
			)
		);
		register_rest_route(
			RANKWEB_REST,
			'/services',
			array(
				'methods'             => 'GET',
				'permission_callback' => rankweb_rest_can( 'manage_options' ),
				'callback'            => fn() => rest_ensure_response( array( 'services' => rankweb_services() ) ),
			)
		);
	}
);

/* ---------- Dashboard ---------- */

/**
 * A post type's plural name.
 *
 * @param string $type Post type.
 */
function rankweb_type_label( string $type ): string {
	$o = get_post_type_object( $type );
	return $o ? (string) $o->labels->name : $type;
}

/**
 * The Dashboard: tiles from the stored counts (formatting only, nothing is counted here), the day log for the time
 * range, the attention list and "Where things are".
 */
function rankweb_dashboard_payload(): array {
	$c        = get_transient( RANKWEB_HEALTH );
	$counting = ! is_array( $c );
	if ( $counting ) {
		rankweb_health_request(); // The first view after a deploy: count in the background.
		$c = array();
	}
	$admin    = current_user_can( 'manage_options' );
	$settings = fn( string $group ) => $admin ? add_query_arg( 'page', 'aw-seo-settings', admin_url( 'admin.php' ) ) . '#' . $group : '';
	$num      = fn( $n ) => number_format_i18n( (int) $n );
	$items    = array_sum( (array) ( $c['items'] ?? array() ) );
	$most     = fn( array $by ) => $by ? rankweb_type_label( (string) array_key_first( $by ) ) : '';
	$list     = fn( array $by ) => $by ? add_query_arg( 'post_type', (string) array_key_first( $by ), admin_url( 'edit.php' ) ) : '';
	$tiles    = array();

	$t       = (array) ( $c['titles'] ?? array() );
	$tiles[] = array(
		'key'    => 'titles',
		'label'  => __( 'Search titles missing', 'aw-seo-extras' ),
		'value'  => $num( $t['total'] ?? 0 ),
		'raw'    => (int) ( $t['total'] ?? 0 ),
		/* translators: 1: number of items, 2: post type with the most missing. */
		'sub'    => ! empty( $t['by'] ) ? sprintf( __( 'of %1$s items; most in %2$s', 'aw-seo-extras' ), $num( $items ), $most( $t['by'] ) ) : sprintf( __( 'of %s items', 'aw-seo-extras' ), $num( $items ) ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- one placeholder, the number of items
		'href'   => $list( (array) ( $t['by'] ?? array() ) ),
		'tone'   => ! empty( $t['total'] ) ? 'warn' : '',
		'metric' => 'titles',
	);
	$d       = (array) ( $c['descs'] ?? array() );
	$tiles[] = array(
		'key'    => 'descs',
		'label'  => __( 'Descriptions missing', 'aw-seo-extras' ),
		'value'  => $num( $d['total'] ?? 0 ),
		'raw'    => (int) ( $d['total'] ?? 0 ),
		/* translators: %s: number of items. */
		'sub'    => sprintf( __( '%s filled by the daily job', 'aw-seo-extras' ), $num( $c['filled'] ?? 0 ) ),
		'href'   => $list( (array) ( $d['by'] ?? array() ) ),
		'tone'   => ! empty( $d['total'] ) ? 'warn' : '',
		'metric' => 'descs',
	);
	$s       = (array) ( $c['schema'] ?? array() );
	if ( isset( $s['on'] ) && ! $s['on'] ) {
		$sv = __( 'Off', 'aw-seo-extras' );
		$ss = __( 'Rank Math schema entries is switched off', 'aw-seo-extras' );
	} elseif ( ! isset( $s['gaps'] ) || null === $s['gaps'] ) {
		$sv = '';
		/* translators: %s: number of items checked so far. */
		$ss = sprintf( __( 'First pass under way: %s items checked', 'aw-seo-extras' ), $num( $s['part'] ?? 0 ) );
	} else {
		$sv = $num( $s['gaps'] );
		/* translators: %s: number of items. */
		$ss = sprintf( __( '%s items the schema command would write', 'aw-seo-extras' ), $num( $s['gaps'] ) );
	}
	$tiles[] = array(
		'key'      => 'schema',
		'label'    => __( 'Schema entries to write', 'aw-seo-extras' ),
		'value'    => $sv,
		'raw'      => isset( $s['gaps'] ) ? (int) $s['gaps'] : null,
		'counting' => ! $counting && '' === $sv,
		'sub'      => $ss,
		'href'     => $settings( 'schema' ),
		'tone'     => ! empty( $s['gaps'] ) ? 'warn' : '',
		'metric'   => 'schema',
	);
	$f       = (array) ( $c['focus'] ?? array() );
	$tiles[] = array(
		'key'    => 'focus',
		'label'  => __( 'Focus keywords missing', 'aw-seo-extras' ),
		'value'  => $num( $f['total'] ?? 0 ),
		'raw'    => (int) ( $f['total'] ?? 0 ),
		'sub'    => __( 'Filled by the workers’ focus-keyword job', 'aw-seo-extras' ),
		'href'   => $list( (array) ( $f['by'] ?? array() ) ),
		'tone'   => '',
		'metric' => 'focus',
	);
	$q       = (array) ( $c['faq'] ?? array() );
	$faq_url = add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) );
	$tiles[] = array(
		'key'    => 'faq',
		'label'  => __( 'Pages with an FAQ', 'aw-seo-extras' ),
		'value'  => isset( $q['on'] ) && ! $q['on'] ? __( 'Off', 'aw-seo-extras' ) : $num( $q['pages'] ?? 0 ),
		'raw'    => (int) ( $q['pages'] ?? 0 ),
		/* translators: %s: number of questions. */
		'sub'    => ! empty( $q['drafts'] ) ? sprintf( _n( '%s draft question waiting', '%s draft questions waiting', (int) $q['drafts'], 'aw-seo-extras' ), $num( $q['drafts'] ) ) : __( 'No drafts waiting', 'aw-seo-extras' ),
		'href'   => ! empty( $q['drafts'] ) ? add_query_arg( 'view', 'waiting', $faq_url ) : $faq_url,
		'tone'   => '',
		'metric' => 'faq',
	);
	$n       = (array) ( $c['noindex'] ?? array() );
	$tiles[] = array(
		'key'    => 'noindex',
		'label'  => __( 'Kept out of search', 'aw-seo-extras' ),
		'value'  => $num( $n['total'] ?? 0 ),
		'raw'    => (int) ( $n['total'] ?? 0 ),
		/* translators: %s: number of pages. */
		'sub'    => sprintf( __( '%s kept indexed on purpose', 'aw-seo-extras' ), $num( $n['kept'] ?? 0 ) ),
		'href'   => $settings( 'indexing' ),
		'tone'   => '',
		'metric' => 'noindex',
		'detail' => array(
			/* translators: %s: number. */
			sprintf( __( '%s listings with no way to reach them', 'aw-seo-extras' ), $num( $n['listings'] ?? 0 ) ),
			/* translators: %s: number. */
			sprintf( __( '%s thin Directory pages', 'aw-seo-extras' ), $num( $n['thin'] ?? 0 ) ),
			/* translators: %s: number. */
			sprintf( __( '%s category lists below the minimum', 'aw-seo-extras' ), $num( $n['filtered'] ?? 0 ) ),
		),
	);
	// The last two are stored results already (the hourly check and the plugin state): read, not counted.
	$last = (array) get_option( 'aw_seo_endpoints_last', array() );
	if ( ! aw_seo_module_on( 'health' ) ) {
		$ev = __( 'Off', 'aw-seo-extras' );
		$es = __( 'The hourly check is switched off', 'aw-seo-extras' );
		$et = '';
	} elseif ( empty( $last['at'] ) ) {
		$ev = __( 'Not checked', 'aw-seo-extras' );
		$es = __( 'The hourly check has not run yet', 'aw-seo-extras' );
		$et = '';
	} elseif ( ! empty( $last['problems'] ) ) {
		$ev = __( 'Not answering', 'aw-seo-extras' );
		$es = implode( ' ', (array) $last['problems'] );
		$et = 'error';
	} else {
		$ev = __( 'Answering', 'aw-seo-extras' );
		/* translators: %s: date and time. */
		$es = sprintf( __( 'Both checked %s', 'aw-seo-extras' ), wp_date( 'j M, g:i a', (int) $last['at'] ) );
		$et = '';
	}
	$tiles[] = array(
		'key'   => 'endpoints',
		'label' => __( 'Sitemap and llms.txt', 'aw-seo-extras' ),
		'value' => $ev,
		'sub'   => $es,
		'href'  => home_url( '/sitemap_index.xml' ),
		'tone'  => $et,
	);
	$off     = count( array_filter( array_keys( aw_seo_modules() ), fn( $k ) => ! aw_seo_module_on( $k ) ) );
	$tiles[] = array(
		'key'   => 'rankmath',
		'label' => __( 'Rank Math', 'aw-seo-extras' ),
		'value' => aw_seo_extras_rank_math() ? __( 'Active', 'aw-seo-extras' ) : __( 'Not active', 'aw-seo-extras' ),
		/* translators: 1: Rank Math version, 2: number of modules. */
		'sub'   => sprintf( _n( 'Version %1$s; %2$s RankWeb module off', 'Version %1$s; %2$s RankWeb modules off', $off, 'aw-seo-extras' ), defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '–', $num( $off ) ),
		'href'  => $settings( 'modules' ),
		'tone'  => aw_seo_extras_rank_math() ? '' : 'error',
	);

	return array(
		'counting' => $counting,
		'at'       => (int) ( $c['at'] ?? 0 ),
		'atText'   => ! empty( $c['at'] ) ? wp_date( 'j M Y, g:i a', (int) $c['at'] ) : '',
		'tiles'    => $tiles,
		'log'      => (object) get_option( RANKWEB_HEALTH_LOG, array() ),
		'today'    => wp_date( 'Y-m-d' ),
		'where'    => rankweb_where_things_are(),
	);
}

/** Dashboard → Where things are: the edit-screen boxes, the commands, and what stays in Rank Math. */
function rankweb_where_things_are(): array {
	$rm = fn( string $label, string $page ) => array(
		'label' => $label,
		'url'   => add_query_arg( 'page', $page, admin_url( 'admin.php' ) ),
	);
	return array(
		'boxes'    => array(
			array( __( 'Posts and Pages (Resources guides, hub Pages)', 'aw-seo-extras' ), __( 'FAQ box: the page’s own questions (draft or approved).', 'aw-seo-extras' ) ),
			array( __( 'Library documents', 'aw-seo-extras' ), __( 'FAQ box: the questions. Library item box: Short name, Issued under, Key sections (document fields the questions use).', 'aw-seo-extras' ) ),
			array( __( 'Tags, categories, organisation types, services', 'aw-seo-extras' ), __( 'FAQ box on the term screen: automatic questions (tags) and the term’s own questions.', 'aw-seo-extras' ) ),
		),
		'commands' => array(
			array( 'wp aw-faq', __( 'report, check, import, convert-guides, undo-guide', 'aw-seo-extras' ) ),
			array( 'wp aw-seo rank-math-schema', __( 'Write Rank Math’s schema entries for published items (--dry-run, --type)', 'aw-seo-extras' ) ),
			array( 'wp aw-seo share-jpegs', __( 'Make JPEG copies of pictures for share images', 'aw-seo-extras' ) ),
			array( 'wp aw redirects-list', __( 'List the active redirects in Rank Math’s Redirections', 'aw-seo-extras' ) ),
		),
		'rankMath' => array(
			array_merge( $rm( __( 'Titles & Meta', 'aw-seo-extras' ), 'rank-math-options-titles' ), array( 'what' => __( 'Title and description templates for single items', 'aw-seo-extras' ) ) ),
			array_merge( $rm( __( 'Sitemap Settings', 'aw-seo-extras' ), 'rank-math-options-sitemap' ), array( 'what' => __( 'Which types are in the sitemap', 'aw-seo-extras' ) ) ),
			array_merge( $rm( __( 'General Settings', 'aw-seo-extras' ), 'rank-math-options-general' ), array( 'what' => __( 'Breadcrumbs, and Edit llms.txt', 'aw-seo-extras' ) ) ),
			array_merge( $rm( __( 'Redirections', 'aw-seo-extras' ), 'rank-math-redirections' ), array( 'what' => __( 'The one list of redirects (forms, merges and imports add to it)', 'aw-seo-extras' ) ) ),
		),
	);
}

/* ---------- Settings ---------- */

/**
 * One field for the screen: its text now and its default's text.
 *
 * @param string $module Module key.
 * @param string $const  Constant.
 * @param array  $f      Field.
 * @param array  $saved  Stored settings.
 */
function rankweb_settings_field( string $module, string $const, array $f, array $saved ): array {
	$has   = array_key_exists( $const, $saved[ $module ] ?? array() );
	$value = $has ? $saved[ $module ][ $const ] : $f['default'];
	return array(
		'const'       => $const,
		'type'        => $f['type'],
		'label'       => $f['label'],
		'help'        => $f['help'],
		'text'        => aw_seo_field_text( $f['type'], $value ),
		'defaultText' => aw_seo_field_text( $f['type'], $f['default'] ),
		'changed'     => $has && $value !== $f['default'],
	);
}

/**
 * A group as the screen shows it: one block per module of the tab, then fields of other modules shown on the tab
 * (the share images under General), in the order of the module list (aw_seo_tab_fields()).
 *
 * @param string $tab Tab key.
 */
function rankweb_settings_group( string $tab ): array {
	$tabs   = aw_seo_setting_tabs();
	$mods   = aw_seo_modules();
	$saved  = aw_seo_saved();
	$group  = array(
		'slug'   => $tab,
		'label'  => $tabs[ $tab ]['label'],
		'blocks' => array(),
	);
	// Modules whose other half is a Rank Math screen (the Overview's "where to set it up"): "Change in …" links.
	$rm     = fn( string $label, string $page ) => array(
		'label' => $label,
		'url'   => add_query_arg( 'page', $page, admin_url( 'admin.php' ) ),
	);
	$elses  = array(
		'titles'      => $rm( __( 'Rank Math → Titles & Meta', 'aw-seo-extras' ), 'rank-math-options-titles' ),
		'breadcrumbs' => $rm( __( 'Rank Math → General Settings', 'aw-seo-extras' ), 'rank-math-options-general' ),
		'sitemap'     => $rm( __( 'Rank Math → Sitemap Settings', 'aw-seo-extras' ), 'rank-math-options-sitemap' ),
		'redirects'   => $rm( __( 'Rank Math → Redirections', 'aw-seo-extras' ), 'rank-math-redirections' ),
		'llms'        => $rm( __( 'Rank Math → General Settings', 'aw-seo-extras' ), 'rank-math-options-general' ),
	);
	$module = fn( string $key, bool $own ) => array(
		'key'      => $key,
		'label'    => $mods[ $key ]['label'],
		'desc'     => $own ? $mods[ $key ]['desc'] : '',
		'switch'   => ! empty( $mods[ $key ]['switch'] ),
		'on'       => aw_seo_module_on( $key ),
		'fields'   => array(),
		'changeIn' => $own && aw_seo_extras_rank_math() ? ( $elses[ $key ] ?? null ) : null,
	);
	if ( 'modules' === $tab ) {
		foreach ( $mods as $key => $mod ) {
			$b                 = $module( $key, true );
			$b['settings']     = aw_seo_module_tab( $key );
			$group['blocks'][] = $b;
		}
		$group['deleteData'] = aw_seo_delete_data_on();
		return $group;
	}
	$fields = aw_seo_tab_fields( $tab );
	foreach ( $tabs[ $tab ]['modules'] as $key ) {
		$b = $module( $key, true );
		foreach ( $fields[ $key ] ?? array() as $const => $f ) {
			$b['fields'][] = rankweb_settings_field( $key, $const, $f, $saved );
		}
		$group['blocks'][] = $b;
	}
	foreach ( $fields as $key => $list ) {
		if ( in_array( $key, $tabs[ $tab ]['modules'], true ) ) {
			continue;
		}
		$b = $module( $key, false );
		foreach ( $list as $const => $f ) {
			$b['fields'][] = rankweb_settings_field( $key, $const, $f, $saved );
		}
		$group['blocks'][] = $b;
	}
	if ( 'schema' === $tab && function_exists( 'aw_faq_type_choices' ) ) {
		// The option itself, not aw_faq_off_types(): that keeps a per-request copy, stale right after a save here.
		$stored            = get_option( 'aw_faq_off_types', null );
		$off               = array_map( 'strval', null === $stored ? aw_faq_off_types() : (array) $stored );
		$choices           = aw_faq_type_choices();
		$group['faqTypes'] = array();
		foreach ( array( 'post', 'term' ) as $store ) {
			foreach ( $choices[ $store ] as $slug => $name ) {
				$group['faqTypes'][] = array(
					'slug'  => (string) $slug,
					'label' => (string) $name,
					'store' => $store,
					'on'    => ! in_array( (string) $slug, $off, true ),
				);
			}
		}
	}
	if ( 'indexing' === $tab ) {
		$keep  = array_map( 'intval', (array) get_option( 'aw_seo_keep_listings', array() ) );
		$names = array();
		foreach ( array_slice( $keep, 0, 10 ) as $id ) {
			$names[] = array(
				'name' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'url'  => (string) get_edit_post_link( $id, 'raw' ),
			);
		}
		$group['keepListings'] = array(
			'count' => count( $keep ),
			'first' => $names,
			'ids'   => $keep,
		);
	}
	if ( 'share' === $tab ) {
		$id                  = (int) get_option( 'aw_default_share_image', 0 );
		$group['shareImage'] = array(
			'id'  => $id,
			'url' => $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '',
		);
	}
	return $group;
}

/** Every group, in the order of today's tabs. */
function rankweb_settings_payload(): array {
	$groups = array();
	foreach ( aw_seo_setting_tabs() as $tab => $t ) {
		if ( empty( $t['hidden'] ) ) {
			$groups[] = rankweb_settings_group( $tab );
		}
	}
	return array( 'groups' => $groups );
}

/**
 * Save one group. Fields not sent keep their value (the form always sends them all; this route does not have to).
 *
 * @param WP_REST_Request $request Request.
 */
function rankweb_rest_save_group( WP_REST_Request $request ) {
	$tab = (string) $request['group'];
	if ( ! isset( aw_seo_setting_tabs()[ $tab ] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'No such group.', 'aw-seo-extras' ), array( 'status' => 400 ) );
	}
	$reset = (bool) $request['reset'];
	if ( $reset ) {
		aw_seo_settings_reset( $tab ); // "Reset this section": the group's registered defaults
	} elseif ( 'modules' === $tab ) {
		if ( null !== $request['delete_data'] ) {
			aw_seo_delete_data_save( (bool) $request['delete_data'] );
		}
		$on = array();
		foreach ( aw_seo_modules() as $key => $mod ) {
			if ( ! empty( $mod['switch'] ) ) {
				$sent       = (array) $request['on'];
				$on[ $key ] = array_key_exists( $key, $sent ) ? ! empty( $sent[ $key ] ) : aw_seo_module_on( $key );
			}
		}
		aw_seo_settings_save( 'modules', array(), false, $on );
	} else {
		$sent   = (array) $request['fields'];
		$saved  = aw_seo_saved();
		$posted = array();
		foreach ( aw_seo_tab_fields( $tab ) as $key => $list ) {
			foreach ( $list as $const => $f ) {
				if ( array_key_exists( $const, $sent ) ) {
					$posted[ $const ] = (string) $sent[ $const ];
				} else {
					$posted[ $const ] = aw_seo_field_text( $f['type'], array_key_exists( $const, $saved[ $key ] ?? array() ) ? $saved[ $key ][ $const ] : $f['default'] );
				}
			}
		}
		aw_seo_settings_save( $tab, $posted );
	}
	return rest_ensure_response(
		array(
			'group'   => rankweb_settings_group( $tab ),
			'message' => $reset ? __( 'Defaults restored.', 'aw-seo-extras' ) : ( 'modules' === $tab ? __( 'Saved. A module switched on or off takes effect on the next page load.', 'aw-seo-extras' ) : __( 'Saved.', 'aw-seo-extras' ) ),
		)
	);
}

/**
 * "FAQ box on": the ticked types. Stored as the unticked ones, as RankWeb → FAQ does, so a type registered later has
 * the box; nothing is written when nothing changed.
 *
 * @param WP_REST_Request $request Request.
 */
function rankweb_rest_save_faq_types( WP_REST_Request $request ) {
	if ( ! function_exists( 'aw_faq_type_choices' ) ) {
		return new WP_Error( 'rest_no_route', __( 'The FAQ module is switched off.', 'aw-seo-extras' ), array( 'status' => 404 ) );
	}
	$on      = array_map( 'sanitize_key', (array) $request['on'] );
	$choices = aw_faq_type_choices();
	$all     = array_merge( array_keys( $choices['post'] ), array_keys( $choices['term'] ) );
	$off     = array_values( array_diff( $all, $on ) );
	$now     = aw_faq_off_types();
	$a       = $off;
	$b       = $now;
	sort( $a );
	sort( $b );
	if ( $a !== $b ) {
		update_option( 'aw_faq_off_types', $off, false );
		delete_transient( 'aw_faq_index' );
	}
	return rest_ensure_response(
		array(
			'group'   => rankweb_settings_group( 'schema' ),
			'message' => __( 'Saved.', 'aw-seo-extras' ),
		)
	);
}

/**
 * Listings kept indexed though they have no contact (robots.php): listing IDs, in the order given. A new ID must be a
 * Directory listing; an ID already on the list stays as it is (a Save with no change writes nothing).
 *
 * @param WP_REST_Request $request Request.
 */
function rankweb_rest_save_keep_listings( WP_REST_Request $request ) {
	$now     = array_map( 'intval', (array) get_option( 'aw_seo_keep_listings', array() ) );
	$ids     = array();
	$skipped = 0;
	foreach ( (array) $request['ids'] as $id ) {
		$id = absint( $id );
		if ( ! $id || in_array( $id, $ids, true ) ) {
			continue;
		}
		if ( in_array( $id, $now, true ) || 'aw_listing' === get_post_type( $id ) ) {
			$ids[] = $id;
		} else {
			++$skipped;
		}
	}
	if ( $now !== $ids ) {
		update_option( 'aw_seo_keep_listings', $ids, false );
	}
	return rest_ensure_response(
		array(
			'group'   => rankweb_settings_group( 'indexing' ),
			'message' => $skipped > 0
				/* translators: %d: number of IDs left out. */
				? sprintf( _n( 'Saved. %d ID is not a Directory listing and was left out.', 'Saved. %d IDs are not Directory listings and were left out.', $skipped, 'aw-seo-extras' ), $skipped )
				: __( 'Saved.', 'aw-seo-extras' ),
		)
	);
}

/**
 * The site's default share image (helpers.php aw_seo_default_image()): an image from the media library, or 0 for none.
 *
 * @param WP_REST_Request $request Request.
 */
function rankweb_rest_save_share_image( WP_REST_Request $request ) {
	$id = absint( $request['id'] );
	if ( $id && ! wp_attachment_is_image( $id ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'Choose an image from the media library.', 'aw-seo-extras' ), array( 'status' => 400 ) );
	}
	if ( (int) get_option( 'aw_default_share_image', 0 ) !== $id ) {
		update_option( 'aw_default_share_image', $id );
	}
	return rest_ensure_response(
		array(
			'group'   => rankweb_settings_group( 'share' ),
			'message' => __( 'Saved.', 'aw-seo-extras' ),
		)
	);
}

/* ---------- Connected services ---------- */

/** Connected services: Rank Math only (keys stay in Kits → Keys, owner 11 Nov 2026). */
function rankweb_services(): array {
	$active  = aw_seo_extras_rank_math();
	$mods    = (array) get_option( 'rank_math_modules', array() );
	$general = (array) get_option( 'rank-math-options-general', array() );
	$feature = array(
		array( __( 'Sitemap', 'aw-seo-extras' ), in_array( 'sitemap', $mods, true ) ),
		array( __( 'Redirections', 'aw-seo-extras' ), in_array( 'redirections', $mods, true ) ),
		array( __( 'llms.txt', 'aw-seo-extras' ), in_array( 'llms-txt', $mods, true ) ),
		array( __( 'Breadcrumbs', 'aw-seo-extras' ), 'on' === ( $general['breadcrumbs'] ?? '' ) ),
	);
	$on      = count( array_filter( $feature, fn( $x ) => $x[1] ) );
	return array(
		array(
			'slug'     => 'rank-math',
			'name'     => __( 'Rank Math SEO', 'aw-seo-extras' ),
			/* translators: %s: version. */
			'status'   => $active ? sprintf( __( 'Active, %s', 'aw-seo-extras' ), defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '' ) : __( 'Not active', 'aw-seo-extras' ),
			'tone'     => $active ? '' : 'error',
			/* translators: 1: features on, 2: features RankWeb relies on. */
			'summary'  => $active ? sprintf( __( '%1$d of the %2$d Rank Math features RankWeb relies on are on.', 'aw-seo-extras' ), $on, count( $feature ) ) : __( 'RankWeb does nothing until Rank Math SEO is active.', 'aw-seo-extras' ),
			'features' => array_map(
				fn( $x ) => array(
					'label' => $x[0],
					'on'    => (bool) $x[1],
				),
				$feature
			),
			'links'    => $active
				? array(
					array(
						'label' => __( 'Rank Math modules', 'aw-seo-extras' ),
						'url'   => add_query_arg(
							array(
								'page' => 'rank-math',
								'view' => 'modules',
							),
							admin_url( 'admin.php' )
						),
					),
					array(
						'label' => __( 'General Settings (breadcrumbs, llms.txt)', 'aw-seo-extras' ),
						'url'   => add_query_arg( 'page', 'rank-math-options-general', admin_url( 'admin.php' ) ),
					),
				)
				: array(
					array(
						'label' => __( 'Plugins', 'aw-seo-extras' ),
						'url'   => admin_url( 'plugins.php' ),
					),
				),
			'note'     => __( 'Read only: RankWeb changes nothing in Rank Math from here. No key is stored.', 'aw-seo-extras' ),
		),
	);
}
