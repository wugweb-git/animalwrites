<?php
/**
 * Preset kits-event: start, end, place and an online address. Dates are shown with the site's date format; templates
 * print them with kits_date() / kits_datecard(). Schema: Event.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_event',
	'title'       => __( 'Events', 'kits-core' ),
	'description' => __( 'Events: start, end, place and an online address.', 'kits-core' ),
	'args'        => array(
		'labels'    => array(
			'name'          => __( 'Events', 'kits-core' ),
			'singular_name' => __( 'Event', 'kits-core' ),
			'add_new_item'  => __( 'Add event', 'kits-core' ),
			'edit_item'     => __( 'Edit event', 'kits-core' ),
			'view_item'     => __( 'View event', 'kits-core' ),
			'search_items'  => __( 'Search events', 'kits-core' ),
			'not_found'     => __( 'No events found.', 'kits-core' ),
			'all_items'     => __( 'All events', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'events',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-calendar-alt',
	),
	'fields'      => array(
		'kits_event_start'      => array(
			'label'  => __( 'Start (YYYY-MM-DD, optional HH:MM)', 'kits-core' ),
			'format' => 'datetime',
		),
		'kits_event_end'        => array(
			'label'  => __( 'End (YYYY-MM-DD, optional HH:MM)', 'kits-core' ),
			'format' => 'datetime',
		),
		'kits_event_place'      => array(
			'label'  => __( 'Place', 'kits-core' ),
			'format' => 'text',
		),
		'kits_event_online_url' => array(
			'label'  => __( 'Online address', 'kits-core' ),
			'format' => 'url',
		),
	),
	'schema'      => array(
		'type'     => 'Event',
		'fields'   => array(
			'startDate' => 'kits_event_start',
			'endDate'   => 'kits_event_end',
			'location'  => 'kits_event_place',
			'url'       => 'kits_event_online_url',
		),
		'requires' => array( 'startDate' ),
	),
);
