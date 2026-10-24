<?php
/**
 * Site Kit modules, each read as its own owner (plan §5.1). Site Kit lets Search Console, Analytics 4 and PageSpeed be
 * connected by different users; a module is built and called with the user in its own settings' ownerID, never another
 * module's owner. Same module layer as aw_sd_sitekit() (search-demand.php); Site Kit's tokens are never read here.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Site Kit's plugin context, or null. */
function aw_si_sitekit_context() {
	if ( defined( 'GOOGLESITEKIT_PLUGIN_MAIN_FILE' ) && class_exists( '\Google\Site_Kit\Context' ) ) {
		return new \Google\Site_Kit\Context( GOOGLESITEKIT_PLUGIN_MAIN_FILE );
	}
	if ( ! class_exists( '\Google\Site_Kit\Plugin' ) ) {
		return null;
	}
	$plugin = \Google\Site_Kit\Plugin::instance();
	$prop   = ( new ReflectionClass( $plugin ) )->getProperty( 'context' );
	$prop->setAccessible( true );
	return $prop->getValue( $plugin );
}

/**
 * Run $callback( $module ) as the owner of the Site Kit module $slug (search-console, analytics-4, pagespeed-insights).
 * The current user is restored afterwards, also when $callback throws.
 *
 * Tests and dry runs can supply their own module with the aw_si_sitekit_module filter (return an object with get_data()).
 *
 * @param string   $slug     Site Kit module slug.
 * @param callable $callback Receives the module.
 * @return mixed|WP_Error What $callback returned, or a WP_Error saying in plain words why the module cannot be used.
 */
function aw_si_with_module( string $slug, callable $callback ) {
	$fake = apply_filters( 'aw_si_sitekit_module', null, $slug );
	if ( $fake ) {
		return $callback( $fake );
	}
	if ( ! class_exists( '\Google\Site_Kit\Core\Modules\Modules' ) ) {
		return new WP_Error( 'aw_si_sitekit', __( 'Site Kit by Google is not active.', 'aw-seo-extras' ) );
	}
	$settings = get_option( 'googlesitekit_' . $slug . '_settings' );
	$owner    = is_array( $settings ) ? (int) ( $settings['ownerID'] ?? 0 ) : 0;
	if ( ! $owner || ! get_userdata( $owner ) ) {
		/* translators: %s: Site Kit module slug, e.g. search-console. */
		return new WP_Error( 'aw_si_sitekit', sprintf( __( 'Site Kit module %s has no owner. Connect it under Site Kit → Settings.', 'aw-seo-extras' ), $slug ) );
	}
	if ( 'search-console' === $slug && empty( $settings['propertyID'] ) ) {
		return new WP_Error( 'aw_si_sitekit', __( 'Search Console is not connected in Site Kit. Connect it under Site Kit → Settings.', 'aw-seo-extras' ) );
	}
	$was = get_current_user_id();
	wp_set_current_user( $owner ); // Site Kit binds the Google client to the current user when the module is built.
	try {
		$ctx = aw_si_sitekit_context();
		if ( ! $ctx ) {
			return new WP_Error( 'aw_si_sitekit', __( 'Site Kit by Google is not active.', 'aw-seo-extras' ) );
		}
		$module = ( new \Google\Site_Kit\Core\Modules\Modules( $ctx ) )->get_module( $slug );
		if ( method_exists( $module, 'is_connected' ) && ! $module->is_connected() ) {
			/* translators: %s: Site Kit module slug. */
			return new WP_Error( 'aw_si_sitekit', sprintf( __( 'Site Kit module %s is not connected.', 'aw-seo-extras' ), $slug ) );
		}
		return $callback( $module );
	} catch ( Throwable $e ) {
		/* translators: 1: Site Kit module slug, 2: error message. */
		return new WP_Error( 'aw_si_sitekit', sprintf( __( 'Site Kit module %1$s could not be used: %2$s', 'aw-seo-extras' ), $slug, wp_strip_all_tags( $e->getMessage() ) ) );
	} finally {
		wp_set_current_user( $was );
	}
}
