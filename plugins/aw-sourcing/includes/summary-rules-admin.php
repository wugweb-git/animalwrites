<?php
/**
 * Summary rules for the workers and the owner: GET aw/v1/summary-rules and the Settings → Sourcing → Summary rules tab.
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
				'callback'            => fn() => new WP_REST_Response( aw_summary_rules() ),
			)
		);
	}
);

/* ---------- Settings → Sourcing → Summary rules ---------- */

add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs['summary'] = array( 'Summary rules', 'aw_summary_rules_tab', true ); // Settings API form (options.php)
		return $tabs;
	}
);

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

/** One textarea of the rules (Settings API field): a list (one entry per line) or a text. */
function aw_summary_rules_field( $args ) {
	$r   = aw_summary_rules();
	$val = isset( $args['section'] ) ? $r['sections'][ $args['section'] ][ $args['key'] ] : $r[ $args['key'] ];
	aw_sourcing_textarea_field( $args + array( 'value' => $val, 'class_attr' => 'large-text' ) );
}

add_action(
	'admin_init',
	function () {
		register_setting( 'aw_sourcing_summary', AW_SUMMARY_RULES_OPTION, array( 'type' => 'array', 'sanitize_callback' => 'aw_summary_rules_sanitize', 'show_in_rest' => false ) );
		if ( 'aw-sourcing' !== sanitize_key( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the form is drawn only on Settings → Sourcing
			return;
		}
		$page = 'aw-sourcing-summary';
		$opt  = AW_SUMMARY_RULES_OPTION;
		$r    = aw_summary_rules_defaults(); // the sections and fields only; values are read when shown
		add_settings_section(
			'aw_sr_intro',
			'',
			function () {
				aw_sourcing_settings_marker( AW_SUMMARY_RULES_OPTION, 'summary' );
				echo '<p>One rule set for every summary on the site: the workers write and check new summaries and same-story rewrites from these rules (GET aw/v1/summary-rules), and the site holds an item whose summary is over the maximum or not plain text in Inbox → To review. A short summary is never held: the workers read the source again, then other coverage of the same story, and publish the best summary that follows the rules. A summary is never shorter than the search description, which is its lead sentence (at most ' . (int) aw_summary_rules()['description_max_chars'] . ' characters).</p>';
			},
			$page
		);
		add_settings_section( 'aw_sr_length', 'Length per section', 'aw_summary_rules_length_table', $page );
		add_settings_section( 'aw_sr_quotes', 'Quotes and the lead', '__return_false', $page );
		foreach ( array(
			'lead_max_words'        => 'Lead sentence, about this many words or fewer',
			'max_quotes'            => 'Direct quotes per summary, at most',
			'max_quote_words'       => 'Words in a quote, at most',
			'max_copied_run'        => 'Words copied in a row outside a quote, at most',
			'description_max_chars' => 'Search description, characters',
		) as $f => $label ) {
			add_settings_field(
				'aw_sr_' . $f,
				$label,
				function () use ( $f, $opt ) {
					echo '<input type="number" min="1" max="400" class="small-text" id="aw-sr-' . esc_attr( $f ) . '" name="' . esc_attr( $opt . '[' . $f . ']' ) . '" value="' . (int) aw_summary_rules()[ $f ] . '">';
				},
				$page,
				'aw_sr_quotes',
				array( 'label_for' => 'aw-sr-' . $f )
			);
		}
		foreach ( $r['sections'] as $name => $sec ) {
			add_settings_section( 'aw_sr_sec_' . $name, $sec['label'], '__return_false', $page );
			foreach ( array( 'structure' => array( 'Structure', 4 ), 'attribution' => array( 'Attribution', 3 ) ) as $f => $d ) {
				add_settings_field( 'aw_sr_' . $name . '_' . $f, $d[0], 'aw_summary_rules_field', $page, 'aw_sr_sec_' . $name, array( 'label_for' => 'aw-sr-' . $name . '-' . $f, 'name' => $opt . '[sections][' . $name . '][' . $f . ']', 'section' => $name, 'key' => $f, 'rows' => $d[1] ) );
			}
		}
		$groups = array(
			'aw_sr_every'    => array(
				'Every summary',
				array(
					'general'        => array( 'Rules', 12, 'One rule per line. The facts-only, date, length, plain-text, no-AI-marker, English-only and searched-words rules are here.' ),
					'stock_phrases'  => array( 'Banned stock and praise wording', 6, 'One per line. A summary using any of them is written again.' ),
					'ai_markers'     => array( 'AI markers', 4, 'One per line. A summary containing any of them is written again.' ),
					'searched_terms' => array( 'Searched words', 5, 'One group per line, words separated by |. When the source names one, the summary keeps one of the group.' ),
					'description'    => array( 'Search description and summary', 3, '' ),
					'card'           => array( 'Card excerpt', 2, '' ),
					'merge'          => array( 'Same-story merges', 3, '' ),
					'fallback'       => array( 'Short fallback', 3, '' ),
				),
			),
			'aw_sr_entities' => array(
				'Linkable entities',
				array(
					'entities_note' => array( 'How summaries name things', 4, '' ),
					'entities'      => array( 'Names', 12, 'One entity per line: the site\'s name first, then other spellings, separated by |. A summary using another spelling is written again. The names each summary uses are stored on the item (aw_entities: laws and organisations with their page, places with their LGD codes) for internal linking.' ),
				),
			),
		);
		foreach ( $groups as $sid => $g ) {
			add_settings_section( $sid, $g[0], '__return_false', $page );
			foreach ( $g[1] as $f => $d ) {
				add_settings_field( 'aw_sr_' . $f, $d[0], 'aw_summary_rules_field', $page, $sid, array( 'label_for' => 'aw-sr-' . str_replace( '_', '-', $f ), 'name' => $opt . '[' . $f . ']', 'key' => $f, 'rows' => $d[1], 'help' => $d[2] ) );
			}
		}
		add_settings_section( 'aw_sr_reset', 'Defaults', '__return_false', $page );
		add_settings_field(
			'aw_sr_reset',
			'Reset',
			function () use ( $opt ) {
				echo '<label><input type="checkbox" id="aw-sr-reset" name="' . esc_attr( $opt ) . '[_reset]" value="1"> Go back to the default rules</label>';
			},
			$page,
			'aw_sr_reset'
		);
	}
);

/** Length per section: the word limits per section (no sentence count, owner 25 Oct 2026) and the published summaries below the minimum. */
function aw_summary_rules_length_table() {
	$r     = aw_summary_rules();
	$below = aw_summary_below_counts();
	$cols  = array( 'label' => 'Section', 'min_words' => 'Minimum words', 'max_words' => 'Maximum words', 'below' => 'Published summaries below the minimum' );
	$rows  = array();
	foreach ( $r['sections'] as $name => $sec ) {
		$row = array( 'label' => esc_html( $sec['label'] ) );
		foreach ( array( 'min_words', 'max_words' ) as $f ) {
			$row[ $f ] = '<input type="number" min="1" max="400" class="small-text" name="' . esc_attr( AW_SUMMARY_RULES_OPTION . '[sections][' . $name . '][' . $f . ']' ) . '" value="' . (int) $sec[ $f ] . '" aria-label="' . esc_attr( $sec['label'] . ' ' . str_replace( '_', ' ', $f ) ) . '">';
		}
		$b            = $below[ $name ] ?? array( 0, 0 );
		$row['below'] = (int) $b[0] . ' of ' . (int) $b[1];
		$rows[]       = $row;
	}
	aw_sourcing_simple_table( 'lengths', $cols, $rows ); // inside the settings form: the table prints no nonce of its own
}

/** The tab. */
function aw_summary_rules_tab() {
	aw_sourcing_settings_form( 'aw_sourcing_summary', 'aw-sourcing-summary', 'Save summary rules' );
}

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
	$new  = array( 'sections' => array() );
	$list = fn( $v ) => array_values( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( (string) $v ) ) ) ) );
	foreach ( (array) ( $in['sections'] ?? array() ) as $name => $sec ) {
		$name = sanitize_key( $name );
		if ( ! isset( $old['sections'][ $name ] ) || ! is_array( $sec ) ) {
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
	foreach ( $now['sections'] as $name => $sec ) {
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
		}
	}
	foreach ( array( 'lead_max_words', 'max_quotes', 'max_quote_words', 'max_copied_run', 'description_max_chars', 'description', 'card', 'merge', 'fallback', 'entities_note' ) as $f ) {
		if ( $now[ $f ] !== $old[ $f ] ) {
			$what[] = str_replace( '_', ' ', $f ) . ' changed';
		}
	}
	kits_sourcing_log( 'summary', $what ? implode( ' · ', $what ) : 'summary rules saved (no change)' );
	return $what ? $new : get_option( AW_SUMMARY_RULES_OPTION ); // no change: the stored rules stay exactly as they are
}
