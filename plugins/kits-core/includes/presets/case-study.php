<?php
/**
 * Preset kits-case-study: problem, approach, outcome and the figures. Schema: Article.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'post_type'   => 'kits_case_study',
	'title'       => __( 'Case studies', 'kits-core' ),
	'description' => __( 'Case studies: the problem, the approach, the outcome and the figures.', 'kits-core' ),
	'args'        => array(
		'labels'    => array(
			'name'          => __( 'Case studies', 'kits-core' ),
			'singular_name' => __( 'Case study', 'kits-core' ),
			'add_new_item'  => __( 'Add case study', 'kits-core' ),
			'edit_item'     => __( 'Edit case study', 'kits-core' ),
			'view_item'     => __( 'View case study', 'kits-core' ),
			'search_items'  => __( 'Search case studies', 'kits-core' ),
			'not_found'     => __( 'No case studies found.', 'kits-core' ),
			'all_items'     => __( 'All case studies', 'kits-core' ),
		),
		'rewrite'   => array(
			'slug'       => 'case-studies',
			'with_front' => false,
		),
		'menu_icon' => 'dashicons-analytics',
	),
	'fields'      => array(
		'kits_case_study_problem'  => array(
			'label'  => __( 'Problem', 'kits-core' ),
			'format' => 'textarea',
		),
		'kits_case_study_approach' => array(
			'label'  => __( 'Approach', 'kits-core' ),
			'format' => 'textarea',
		),
		'kits_case_study_outcome'  => array(
			'label'  => __( 'Outcome', 'kits-core' ),
			'format' => 'textarea',
		),
		'kits_case_study_figures'  => array(
			'label'  => __( 'Figures', 'kits-core' ),
			'format' => 'textarea',
		),
	),
	'schema'      => array(
		'type'   => 'Article',
		'fields' => array(
			'abstract' => 'kits_case_study_outcome',
		),
	),
);
