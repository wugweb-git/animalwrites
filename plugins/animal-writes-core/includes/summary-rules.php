<?php
/**
 * Settings → Sourcing → Summary rules (owner, 15 Oct 2026): the one rule set for the summaries of News, the Library
 * and Resources research, kept in one place.
 *
 * - Option aw_summary_rules: the owner's edits over the defaults (data/summary-rules-default.json, the same file as
 *   the workers' aw_workers/summary_rules.json). Lengths per section, structure, attribution, quotes, wording, the
 *   searched words, the linkable entities.
 * - GET aw/v1/summary-rules serves them to the workers, which build their prompts and checks from them (summarise.py,
 *   merge.py, fold_existing.py) and use the same defaults when the site cannot be reached.
 * - The site applies them too: auto-review holds a summary over its section's maximum, or not plain text
 *   (aw_summary_problem); a summary below the minimum is never held (owner, 15 Oct 2026: the workers re-read the source
 *   and other coverage of the same story, and publish the best rule-following summary); the search description is the summary's lead (aw-seo-extras titles.php); an item's own page
 *   shows the whole summary; the entities a summary names are stored per item (aw_entities) for internal linking.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SUMMARY_RULES_OPTION = 'aw_summary_rules';

/** The defaults (the workers keep the same file). */
function aw_summary_rules_defaults() {
	static $d = null;
	if ( null === $d ) {
		$d = json_decode( (string) file_get_contents( AW_CORE_DIR . 'data/summary-rules-default.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$d = is_array( $d ) ? $d : array( 'sections' => array() );
	}
	return $d;
}

/** The rules in force: the saved values over the defaults. */
function aw_summary_rules() {
	$out   = aw_summary_rules_defaults();
	$saved = get_option( AW_SUMMARY_RULES_OPTION );
	if ( ! is_array( $saved ) ) {
		return $out;
	}
	foreach ( $saved as $k => $v ) {
		if ( 'sections' === $k && is_array( $v ) ) {
			foreach ( $v as $name => $sec ) {
				if ( is_array( $sec ) && isset( $out['sections'][ $name ] ) ) {
					$out['sections'][ $name ] = array_merge( $out['sections'][ $name ], array_filter( $sec, fn( $x ) => '' !== $x && array() !== $x ) );
				}
			}
		} elseif ( array_key_exists( $k, $out ) && gettype( $v ) === gettype( $out[ $k ] ) && '' !== $v && array() !== $v ) {
			$out[ $k ] = $v;
		}
	}
	foreach ( $out['sections'] as &$sec ) {
		$sec['min_words'] = min( (int) $sec['min_words'], (int) $sec['max_words'] );
	}
	return $out;
}

/** Which rules an item follows: news, library, research, or '' (no summary rules). */
function aw_summary_section( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( 'aw_news' === $post->post_type ) {
		return 'news';
	}
	if ( 'aw_legal' === $post->post_type ) {
		return 'library';
	}
	if ( 'post' === $post->post_type && ( has_term( 'research', 'post_tag', $post ) || ( taxonomy_exists( 'aw_resource_type' ) && has_term( 'research', 'aw_resource_type', $post ) ) ) ) { // research is a tag since 15 Oct 2026
		return 'research';
	}
	return '';
}

/** Words as the workers count them. */
function aw_summary_words( $text ) {
	return preg_match_all( "/[\\p{L}\\p{N}_'’-]+/u", (string) $text );
}

/** Sentences as the workers count them. */
function aw_summary_sentences( $text ) {
	return count( array_filter( preg_split( '/(?<=[.!?])\s+(?=["“‘(]?[A-Z0-9])/u', trim( (string) $text ) ) ) );
}

/**
 * Why a summary breaks the length or plain-text rules of its section, or ''.
 *
 * @param string $text     The summary.
 * @param string $section  news|library|research.
 * @param bool   $short_ok Below the minimum words or sentences is not a problem (auto-review: never held for it).
 * @return string
 */
function aw_summary_problem( $text, $section, $short_ok = false ) {
	$rules = aw_summary_rules();
	$sec   = $rules['sections'][ $section ] ?? null;
	$text  = trim( str_replace( '[Needs legal review]', '', (string) $text ) );
	if ( ! $sec ) {
		return '';
	}
	if ( preg_match( '#<[a-z/][^>]*>|https?://|www\.|\[[^\]]+\]\([^)]+\)|\*\*|\n#i', $text ) ) {
		return 'summary is not plain text (link, HTML, markup or line break)';
	}
	$w = aw_summary_words( $text );
	if ( $w < (int) $sec['min_words'] && ! $short_ok ) {
		return sprintf( 'summary below the minimum (%d words; %s needs %d)', $w, $sec['label'], (int) $sec['min_words'] );
	}
	if ( $w > (int) $sec['max_words'] ) {
		return sprintf( 'summary over the maximum (%d words; %s allows %d)', $w, $sec['label'], (int) $sec['max_words'] );
	}
	$s = aw_summary_sentences( $text );
	if ( ( $s < (int) $sec['min_sentences'] && ! $short_ok ) || $s > (int) $sec['max_sentences'] ) {
		return sprintf( 'summary has %d sentences (%s: %d to %d)', $s, $sec['label'], (int) $sec['min_sentences'], (int) $sec['max_sentences'] );
	}
	return '';
}

/**
 * The search description from a summary: its lead, or the first sentences that fit whole; only a lead longer than the
 * limit is cut at a word.
 *
 * @param string $text The summary.
 * @param int    $max  Characters (the rules' description_max_chars when 0).
 * @return string
 */
function aw_summary_description( $text, $max = 0 ) {
	$max  = $max ? $max : (int) ( aw_summary_rules()['description_max_chars'] ?? 160 );
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	$out  = '';
	foreach ( preg_split( '/(?<=[.!?])\s+(?=["“‘(]?[A-Z0-9])/u', $text ) as $s ) {
		$next = '' === $out ? $s : $out . ' ' . $s;
		if ( mb_strlen( $next ) > $max ) {
			break;
		}
		$out = $next;
	}
	return '' !== $out ? $out : ( function_exists( 'aw_seo_cut' ) ? aw_seo_cut( $text, $max ) : mb_substr( $text, 0, $max ) );
}

/* ---------- Linkable entities (internal linking, Phase 7) ---------- */

/** Entity lines: [[canonical, alias, ...], ...]. */
function aw_summary_entity_lines() {
	$out = array();
	foreach ( (array) ( aw_summary_rules()['entities'] ?? array() ) as $line ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( '|', (string) $line ) ) ) );
		if ( $parts ) {
			$out[] = $parts;
		}
	}
	return $out;
}

/** True when $text names $phrase as a whole phrase (an all-capitals alias must match its case). */
function aw_summary_names( $text, $phrase ) {
	$flags = preg_match( '/^[A-Z0-9 ]+$/', $phrase ) ? 'u' : 'iu';
	return (bool) preg_match( '/(?<![\p{L}\p{N}-])' . str_replace( '\ ', '\s+', preg_quote( $phrase, '/' ) ) . '(?![\p{L}\p{N}-])/' . $flags, (string) $text );
}

/** A published page titled with an entity's name (Library first, then the Directory): [post type, ID] or null. */
function aw_summary_entity_page( $name ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('aw_legal','aw_listing') AND (post_title = %s OR post_title = %s OR post_title LIKE %s OR post_title LIKE %s OR post_title LIKE %s) ORDER BY post_type = 'aw_legal' DESC, LENGTH(post_title) ASC LIMIT 1", $name, 'The ' . $name, $wpdb->esc_like( $name ) . ' (%', 'The ' . $wpdb->esc_like( $name ) . ' (%', '% - ' . $wpdb->esc_like( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $row ? array( $row->post_type, (int) $row->ID ) : null;
}

/**
 * The linkable entities a summary names: laws (Library), organisations and bodies (Directory), places (LGD codes),
 * and named entities with no page yet.
 *
 * @param string $text The summary.
 * @return array
 */
function aw_summary_entities( $text ) {
	$out = array( 'laws' => array(), 'organisations' => array(), 'places' => array(), 'other' => array() );
	foreach ( aw_summary_entity_lines() as $parts ) {
		$hit = false;
		foreach ( $parts as $p ) {
			$hit = $hit || aw_summary_names( $text, $p );
		}
		if ( ! $hit ) {
			continue;
		}
		$page = aw_summary_entity_page( $parts[0] );
		if ( ! $page ) {
			$out['other'][] = array( 'name' => $parts[0] );
		} else {
			$out[ 'aw_legal' === $page[0] ? 'laws' : 'organisations' ][] = array( 'name' => $parts[0], 'id' => $page[1] );
		}
	}
	if ( function_exists( 'aw_lgd' ) ) {
		$lgd    = aw_lgd();
		$states = array();
		foreach ( $lgd['states'] as $s ) {
			if ( aw_summary_names( $text, $s->name ) ) {
				$states[ $s->code ]  = true;
				$out['places'][]     = array( 'name' => $s->name, 'state' => $s->code, 'district' => 0 );
			}
		}
		$seen = array();
		foreach ( $lgd['districts'] as $d ) {
			if ( strlen( $d->name ) > 3 && preg_match( '/^\p{Lu}/u', $d->name ) && preg_match( '/(?<![\p{L}-])' . preg_quote( $d->name, '/' ) . '(?![\p{L}-])/u', (string) $text ) ) {
				$seen[ $d->name ][] = $d;
			}
		}
		foreach ( $seen as $name => $hits ) {
			$in = array_values( array_filter( $hits, fn( $d ) => isset( $states[ $d->state ] ) ) );
			$d  = 1 === count( $hits ) ? $hits[0] : ( 1 === count( $in ) ? $in[0] : null ); // a name in two states: only with its state named
			if ( $d ) {
				$out['places'][] = array( 'name' => $d->name, 'state' => $d->state, 'district' => $d->code );
			}
		}
	}
	return $out;
}

/** Store the entities an item's summary names (meta aw_entities, JSON). */
function aw_summary_store_entities( $post_id ) {
	if ( ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal', 'post' ), true ) ) {
		return;
	}
	$found = aw_summary_entities( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $post_id, 'raw' ) ) );
	update_post_meta( $post_id, 'aw_entities', wp_slash( wp_json_encode( $found ) ) );
}

add_action(
	'wp_after_insert_post',
	function ( $post_id, $post ) {
		if ( in_array( $post->post_type, array( 'aw_news', 'aw_legal', 'post' ), true ) && 'trash' !== $post->post_status && ! wp_is_post_revision( $post_id ) ) {
			aw_summary_store_entities( $post_id );
		}
	},
	20,
	2
);

/* ---------- The item's own page shows the whole summary ---------- */

add_filter(
	'render_block_data',
	function ( $block ) {
		// The post-excerpt block cuts at 55 words by default; on a News or Library page the summary is shown whole.
		if ( 'core/post-excerpt' === ( $block['blockName'] ?? '' ) && ! isset( $block['attrs']['excerptLength'] ) && is_singular( array( 'aw_news', 'aw_legal' ) ) ) {
			$block['attrs']['excerptLength'] = 1000;
		}
		return $block;
	}
);

/* ---------- Workers ---------- */

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
