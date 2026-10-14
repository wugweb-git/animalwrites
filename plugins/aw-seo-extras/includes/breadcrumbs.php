<?php
/**
 * The site's breadcrumb trail (animal-writes-core breadcrumbs.php: Directory › State › District, Section › Item) is
 * built from places and hubs that Rank Math's breadcrumbs do not know. The label of an item in that trail is the
 * "Breadcrumb Title" typed in Rank Math (Advanced tab), when there is one. The trail goes into Rank Math's graph as
 * BreadcrumbList (schema.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'aw_breadcrumb_label',
	function ( $label, $id ) {
		$b = trim( (string) get_post_meta( (int) $id, 'rank_math_breadcrumb_title', true ) );
		return '' !== $b ? $b : $label;
	},
	20,
	2
);
