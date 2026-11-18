<?php
/**
 * Preset kits-event: start, end, place and an online address. Dates are shown as d-m-y H:i (kits_preset_date_format); templates
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
			'name'                     => __( 'Events', 'kits-core' ),
			'singular_name'            => __( 'Event', 'kits-core' ),
			'add_new_item'             => __( 'Add event', 'kits-core' ),
			'edit_item'                => __( 'Edit event', 'kits-core' ),
			'view_item'                => __( 'View event', 'kits-core' ),
			'search_items'             => __( 'Search events', 'kits-core' ),
			'not_found'                => __( 'No events found.', 'kits-core' ),
			'all_items'                => __( 'All events', 'kits-core' ),
			'add_new'                  => __( 'Add event', 'kits-core' ),
			'new_item'                 => __( 'New event', 'kits-core' ),
			'view_items'               => __( 'View events', 'kits-core' ),
			'not_found_in_trash'       => __( 'No events found in the Trash.', 'kits-core' ),
			'archives'                 => __( 'Event archives', 'kits-core' ),
			'attributes'               => __( 'Event attributes', 'kits-core' ),
			'insert_into_item'         => __( 'Insert into event', 'kits-core' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this event', 'kits-core' ),
			'filter_items_list'        => __( 'Filter events list', 'kits-core' ),
			'items_list_navigation'    => __( 'Events list navigation', 'kits-core' ),
			'items_list'               => __( 'Events list', 'kits-core' ),
			'item_published'           => __( 'Event published.', 'kits-core' ),
			'item_published_privately' => __( 'Event published privately.', 'kits-core' ),
			'item_reverted_to_draft'   => __( 'Event reverted to draft.', 'kits-core' ),
			'item_scheduled'           => __( 'Event scheduled.', 'kits-core' ),
			'item_updated'             => __( 'Event updated.', 'kits-core' ),
			'item_link'                => __( 'Event link', 'kits-core' ),
			'item_link_description'    => __( 'A link to an event.', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'events',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-calendar-alt',
	),
	'fields'      => array(
		'kits_event_start'      => array(
			'label'  => __( 'Start (DD-MM-YY, optional HH:MM)', 'kits-core' ),
			'format' => 'datetime',
		),
		'kits_event_end'        => array(
			'label'  => __( 'End (DD-MM-YY, optional HH:MM)', 'kits-core' ),
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
