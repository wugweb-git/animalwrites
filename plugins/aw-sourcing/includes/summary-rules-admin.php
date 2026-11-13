<?php
/**
 * Summary rules for the workers: GET aw/v1/summary-rules, and the option's save check (the Rules screen saves them
 * through sourceweb/v1).
 * The rules themselves (option aw_summary_rules) and the site's own use of them stay in core (summary-rules.php).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/summary-rules',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'callback'            => fn() => new WP_REST_Response( aw_summary_rules_payload() ),
			)
		);
	}
);

/**
 * What GET summary-rules serves: the rules in force (sections by their old names, as every reader of before phase 3
 * reads them), and from phase 3 by_type, each section's rule by post type (rules.php).
 *
 * @return array
 */
function aw_summary_rules_payload() {
	$out = aw_summary_rules();
	if ( function_exists( 'aw_rules_live' ) && aw_rules_live() ) {
		$out['by_type'] = aw_rules_summary_by_type();
	}
	return $out;
}

/** Published summaries below their section's minimum (counted once an hour): section => [below, total]. */
function aw_summary_below_counts() {
	$c = get_transient( 'aw_summary_below' );
	if ( is_array( $c ) ) {
		return $c;
	}
	$rules = aw_summary_rules();
	$c     = array();
	$sets  = array(
		'news'     => array( 'post_type' => 'aw_news' ),
		'library'  => array( 'post_type' => 'aw_legal' ),
		'research' => array( 'post_type' => 'post', 'tag' => 'research' ),
	);
	foreach ( $sets as $name => $args ) {
		$ids  = get_posts( $args + array( 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		$min  = (int) $rules['sections'][ $name ]['min_words'];
		$below = 0;
		foreach ( $ids as $id ) {
			$below += aw_summary_words( str_replace( '[Needs legal review]', '', wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id, 'raw' ) ) ) ) < $min ? 1 : 0;
		}
		$c[ $name ] = array( $below, count( $ids ) );
	}
	set_transient( 'aw_summary_below', $c, HOUR_IN_SECONDS );
	return $c;
}

add_action(
	'admin_init',
	fn() => register_setting( 'aw_sourcing_summary', AW_SUMMARY_RULES_OPTION, array( 'type' => 'array', 'sanitize_callback' => 'aw_summary_rules_sanitize', 'show_in_rest' => false ) )
);

/**
 * Sanitize the rules posted by the tab (logged); any other save of the option passes through.
 *
 * @param mixed $in Posted rules with _aw_tab, or the option as the site saves it.
 * @return mixed
 */
function aw_summary_rules_sanitize( $in ) {
	if ( ! aw_sourcing_settings_tab( $in, 'summary' ) ) {
		return $in;
	}
	$old = aw_summary_rules();
	delete_transient( 'aw_summary_below' );
	aw_sourcing_settings_saved();
	if ( ! empty( $in['_reset'] ) ) {
		kits_sourcing_log( 'summary', 'summary rules back to the defaults' );
		return array(); // no saved rules: the defaults apply (aw_summary_rules)
	}
	$new     = array( 'sections' => array() );
	$list    = fn( $v ) => array_values( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( (string) $v ) ) ) ) );
	$by_type = function_exists( 'aw_rules_live' ) && aw_rules_live();
	$types   = $by_type ? aw_rules_section_labels() : array();
	$old_bt  = $by_type ? aw_rules_summary_by_type() : array();
	foreach ( (array) ( $in['sections'] ?? array() ) as $name => $sec ) {
		$name = sanitize_key( $name );
		if ( $by_type ) { // phase 3: by post type; the old sections keep their old name as alias
			if ( ! isset( $types[ $name ] ) || ! is_array( $sec ) ) {
				continue;
			}
			if ( isset( AW_RULES_SUMMARY_ALIAS[ $name ] ) ) {
				$new['sections'][ $name ]['alias'] = AW_RULES_SUMMARY_ALIAS[ $name ];
			} elseif ( ! empty( $sec['inherit'] ) ) {
				continue; // inherits News: no rule of its own is stored
			} else {
				$new['sections'][ $name ] = array( 'label' => $types[ $name ], 'inherit' => '' );
			}
		} elseif ( ! isset( $old['sections'][ $name ] ) || ! is_array( $sec ) ) {
			continue;
		}
		foreach ( array( 'min_words', 'max_words' ) as $f ) {
			if ( isset( $sec[ $f ] ) && (int) $sec[ $f ] > 0 ) {
				$new['sections'][ $name ][ $f ] = (int) $sec[ $f ];
			}
		}
		foreach ( array( 'structure', 'attribution' ) as $f ) {
			if ( isset( $sec[ $f ] ) ) {
				$new['sections'][ $name ][ $f ] = trim( sanitize_textarea_field( $sec[ $f ] ) );
			}
		}
	}
	if ( $by_type ) { // a section's own rule that was not on the form (a section added since the page was opened) stays
		$stored = get_option( AW_SUMMARY_RULES_OPTION );
		foreach ( is_array( $stored ) ? (array) ( $stored['sections'] ?? array() ) : array() as $pt => $entry ) {
			if ( ! isset( $in['sections'][ $pt ] ) && ! isset( $new['sections'][ $pt ] ) && is_array( $entry ) ) {
				$new['sections'][ $pt ] = $entry;
			}
		}
	}
	foreach ( array( 'lead_max_words', 'max_quotes', 'max_quote_words', 'max_copied_run', 'description_max_chars' ) as $f ) {
		if ( isset( $in[ $f ] ) && (int) $in[ $f ] > 0 ) {
			$new[ $f ] = (int) $in[ $f ];
		}
	}
	foreach ( array( 'general', 'stock_phrases', 'ai_markers', 'searched_terms', 'entities' ) as $f ) {
		if ( isset( $in[ $f ] ) ) {
			$new[ $f ] = $list( $in[ $f ] );
		}
	}
	foreach ( array( 'description', 'card', 'merge', 'fallback', 'entities_note' ) as $f ) {
		if ( isset( $in[ $f ] ) ) {
			$new[ $f ] = trim( sanitize_textarea_field( $in[ $f ] ) );
		}
	}
	// What changed, read through aw_summary_rules() as the site will (the defaults fill what is empty).
	add_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek = fn() => $new );
	$now = aw_summary_rules();
	remove_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek );
	$what = array();
	$log  = array(); // each change with its value before and after (phase 3)
	if ( $by_type ) {
		$saved_new = $new;
		add_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek = fn() => $saved_new );
		$now_bt = aw_rules_summary_by_type();
		remove_filter( 'pre_option_' . AW_SUMMARY_RULES_OPTION, $peek );
		foreach ( $now_bt as $pt => $sec ) {
			$was = $old_bt[ $pt ] ?? array();
			if ( (string) ( $was['inherit'] ?? '' ) !== (string) ( $sec['inherit'] ?? '' ) ) {
				$what[] = $sec['label'] . ( '' === (string) ( $sec['inherit'] ?? '' ) ? ' has its own rule' : ' inherits News' );
				$log[]  = array( 'summary', $sec['label'] . ' summary rule', '' === (string) ( $was['inherit'] ?? '' ) ? 'its own' : 'inherits News', '' === (string) ( $sec['inherit'] ?? '' ) ? 'its own' : 'inherits News' );
			}
			foreach ( array( 'min_words', 'max_words', 'structure', 'attribution' ) as $f ) {
				if ( isset( $was[ $f ] ) && $was[ $f ] !== $sec[ $f ] && '' === (string) ( $sec['inherit'] ?? '' ) ) {
					$what[] = $sec['label'] . ' ' . str_replace( '_', ' ', $f ) . ( is_int( $sec[ $f ] ) ? ' ' . $was[ $f ] . ' → ' . $sec[ $f ] : ' edited' );
					$log[]  = array( 'summary', $sec['label'] . ' ' . str_replace( '_', ' ', $f ), $was[ $f ], $sec[ $f ] );
				}
			}
		}
	}
	foreach ( $by_type ? array() : $now['sections'] as $name => $sec ) {
		foreach ( array( 'min_words', 'max_words' ) as $f ) {
			if ( (int) $sec[ $f ] !== (int) $old['sections'][ $name ][ $f ] ) {
				$what[] = $sec['label'] . ' ' . str_replace( '_', ' ', $f ) . ' ' . (int) $old['sections'][ $name ][ $f ] . ' → ' . (int) $sec[ $f ];
			}
		}
		foreach ( array( 'structure', 'attribution' ) as $f ) {
			if ( $sec[ $f ] !== $old['sections'][ $name ][ $f ] ) {
				$what[] = $sec['label'] . ' ' . $f . ' edited';
			}
		}
	}
	foreach ( array( 'general', 'stock_phrases', 'ai_markers', 'searched_terms', 'entities' ) as $f ) {
		$d = kits_sourcing_diff( str_replace( '_', ' ', ucfirst( $f ) ), (array) $old[ $f ], (array) $now[ $f ] );
		if ( $d ) {
			$what[] = $d;
			$log[]  = array( 'summary', $d, array_values( array_diff( (array) $old[ $f ], (array) $now[ $f ] ) ), array_values( array_diff( (array) $now[ $f ], (array) $old[ $f ] ) ) );
		}
	}
	foreach ( array( 'lead_max_words', 'max_quotes', 'max_quote_words', 'max_copied_run', 'description_max_chars', 'description', 'card', 'merge', 'fallback', 'entities_note' ) as $f ) {
		if ( $now[ $f ] !== $old[ $f ] ) {
			$what[] = str_replace( '_', ' ', $f ) . ' changed';
			$log[]  = array( 'summary', str_replace( '_', ' ', $f ), $old[ $f ], $now[ $f ] );
		}
	}
	if ( $by_type && $log ) {
		kits_sourcing_log_many( $log ); // phase 3: each change with its value before and after
	} elseif ( $what ) { // a save with no change writes nothing, not even a log entry (round-trip rule)
		kits_sourcing_log( 'summary', implode( ' · ', $what ) );
	}
	return $what ? $new : get_option( AW_SUMMARY_RULES_OPTION ); // no change: the stored rules stay exactly as they are
}
