<?php
/**
 * Breadcrumbs block (rankweb/breadcrumbs): the page's trail, once per page (rankweb_breadcrumbs_block()).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo rankweb_breadcrumbs_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url() and esc_html() parts in rankweb_breadcrumbs_html().
