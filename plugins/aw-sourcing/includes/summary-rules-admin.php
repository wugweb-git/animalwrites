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
		$tabs['summary'] = array( 'Summary rules', 'aw_summary_rules_tab' );
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

/** One textarea: a list (one entry per line) or a text. */
function aw_summary_rules_textarea( $name, $label, $value, $rows = 4, $help = '' ) {
	$v  = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
	$id = 'aw-sr-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
	echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label>' . ( $help ? '<br><span class="description">' . esc_html( $help ) . '</span>' : '' )
		. '<br><textarea class="large-text" rows="' . (int) $rows . '" id="' . esc_attr( $id ) . '" name="sr[' . esc_attr( $name ) . ']">' . esc_textarea( $v ) . '</textarea></p>';
}

/** The tab. */
function aw_summary_rules_tab() {
	$r     = aw_summary_rules();
	$below = aw_summary_below_counts();
	echo '<p>One rule set for every summary on the site: the workers write and check new summaries and same-story rewrites from these rules (GET aw/v1/summary-rules), and the site holds an item whose summary is over the maximum or not plain text in Inbox → To review. A short summary is never held: the workers read the source again, then other coverage of the same story, and publish the best summary that follows the rules. A summary is never shorter than the search description, which is its lead sentence (at most ' . (int) $r['description_max_chars'] . ' characters).</p>';
	echo '<h2>Length per section</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Section</th><th>Minimum words</th><th>Maximum words</th><th>Minimum sentences</th><th>Maximum sentences</th><th>Published summaries below the minimum</th></tr></thead><tbody>';
	foreach ( $r['sections'] as $name => $sec ) {
		echo '<tr><td>' . esc_html( $sec['label'] ) . '</td>';
		foreach ( array( 'min_words', 'max_words', 'min_sentences', 'max_sentences' ) as $f ) {
			echo '<td><input type="number" min="1" max="400" class="small-text" name="sr[sections][' . esc_attr( $name ) . '][' . esc_attr( $f ) . ']" value="' . (int) $sec[ $f ] . '" aria-label="' . esc_attr( $sec['label'] . ' ' . str_replace( '_', ' ', $f ) ) . '"></td>';
		}
		$b = $below[ $name ] ?? array( 0, 0 );
		echo '<td>' . (int) $b[0] . ' of ' . (int) $b[1] . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<h2>Quotes and the lead</h2><p>';
	foreach ( array(
		'lead_max_words'        => 'Lead sentence, about this many words or fewer',
		'max_quotes'            => 'Direct quotes per summary, at most',
		'max_quote_words'       => 'Words in a quote, at most',
		'max_copied_run'        => 'Words copied in a row outside a quote, at most',
		'description_max_chars' => 'Search description, characters',
	) as $f => $label ) {
		echo '<label style="display:inline-block;margin-right:16px">' . esc_html( $label ) . ' <input type="number" min="1" max="400" class="small-text" name="sr[' . esc_attr( $f ) . ']" value="' . (int) $r[ $f ] . '"></label>';
	}
	echo '</p>';
	foreach ( $r['sections'] as $name => $sec ) {
		echo '<h2>' . esc_html( $sec['label'] ) . '</h2>';
		aw_summary_rules_textarea( 'sections][' . $name . '][structure', 'Structure', $sec['structure'], 4 );
		aw_summary_rules_textarea( 'sections][' . $name . '][attribution', 'Attribution', $sec['attribution'], 3 );
	}
	echo '<h2>Every summary</h2>';
	aw_summary_rules_textarea( 'general', 'Rules', $r['general'], 12, 'One rule per line. The facts-only, date, length, plain-text, no-AI-marker, English-only and searched-words rules are here.' );
	aw_summary_rules_textarea( 'stock_phrases', 'Banned stock and praise wording', $r['stock_phrases'], 6, 'One per line. A summary using any of them is written again.' );
	aw_summary_rules_textarea( 'ai_markers', 'AI markers', $r['ai_markers'], 4, 'One per line. A summary containing any of them is written again.' );
	aw_summary_rules_textarea( 'searched_terms', 'Searched words', $r['searched_terms'], 5, 'One group per line, words separated by |. When the source names one, the summary keeps one of the group.' );
	aw_summary_rules_textarea( 'description', 'Search description and summary', $r['description'], 3 );
	aw_summary_rules_textarea( 'card', 'Card excerpt', $r['card'], 2 );
	aw_summary_rules_textarea( 'merge', 'Same-story merges', $r['merge'], 3 );
	aw_summary_rules_textarea( 'fallback', 'Short fallback', $r['fallback'], 3 );
	echo '<h2>Linkable entities</h2>';
	aw_summary_rules_textarea( 'entities_note', 'How summaries name things', $r['entities_note'], 4 );
	aw_summary_rules_textarea( 'entities', 'Names', $r['entities'], 12, 'One entity per line: the site\'s name first, then other spellings, separated by |. A summary using another spelling is written again. The names each summary uses are stored on the item (aw_entities: laws and organisations with their page, places with their LGD codes) for internal linking.' );
	echo '<p><label><input type="checkbox" name="sr_reset" value="1"> Go back to the default rules</label></p>';
	submit_button( 'Save summary rules' );
}

add_action(
	'kits_sourcing_save_summary',
	function () {
		$old = aw_summary_rules();
		if ( ! empty( $_POST['sr_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
			delete_option( AW_SUMMARY_RULES_OPTION );
			delete_transient( 'aw_summary_below' );
			kits_sourcing_log( 'summary', 'summary rules back to the defaults' );
			return;
		}
		$in   = (array) wp_unslash( $_POST['sr'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$new  = array( 'sections' => array() );
		$list = fn( $v ) => array_values( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( (string) $v ) ) ) ) );
		foreach ( (array) ( $in['sections'] ?? array() ) as $name => $sec ) {
			$name = sanitize_key( $name );
			if ( ! isset( $old['sections'][ $name ] ) || ! is_array( $sec ) ) {
				continue;
			}
			foreach ( array( 'min_words', 'max_words', 'min_sentences', 'max_sentences' ) as $f ) {
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
		update_option( AW_SUMMARY_RULES_OPTION, $new, false );
		delete_transient( 'aw_summary_below' );
		$now  = aw_summary_rules();
		$what = array();
		foreach ( $now['sections'] as $name => $sec ) {
			foreach ( array( 'min_words', 'max_words', 'min_sentences', 'max_sentences' ) as $f ) {
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
	}
);