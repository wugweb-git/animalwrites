<?php
/**
 * InsightWeb's external-service declarations, registered with the shared suite registry (external-services.php). The
 * readme "== External services ==" block and the in-admin disclosure are generated from these, so the plugin never
 * uses an outside service it has not declared here.
 *
 * Today the public edition reads Google Search Console and GA4 through the Site Kit by Google plugin and makes no
 * direct outbound request of its own, so that data flow is the one declaration. PageSpeed Insights, Microsoft Clarity,
 * Bing Webmaster and the DB-IP country file each register from their own module as it is ported.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'insightweb_register_services' );

/**
 * Declare InsightWeb's external services.
 *
 * @return void
 */
function insightweb_register_services(): void {
	if ( ! class_exists( 'Wugweb_External_Services' ) ) {
		return;
	}
	Wugweb_External_Services::register(
		'google-site-kit',
		array(
			'name'    => __( 'Google Search Console and Google Analytics 4 (via Site Kit)', 'insightweb' ),
			'summary' => __( "InsightWeb reads your site's Search Console and Analytics figures from Google through the Site Kit by Google plugin, which holds the Google connection. InsightWeb reads only and stores no Google credentials of its own.", 'insightweb' ),
			'hosts'   => array(),
			'terms'   => 'https://policies.google.com/terms',
			'privacy' => 'https://policies.google.com/privacy',
		)
	);
}
