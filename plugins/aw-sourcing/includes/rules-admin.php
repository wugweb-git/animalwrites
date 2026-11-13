<?php
/**
 * Sourcing → Rules (sourcing restructure phase 3, owner 25 Oct 2026): one tab in three parts, from the
 * phase 3 migration on. The old Sections, Summary rules and Directory enrichment tabs redirect here.
 *
 * - Section rules (&part=sections): each section's categories and tags (sourcing-terms-admin.php) and the tag rules
 *   (aw_sources['topic_tags']).
 * - Summary rules (&part=summary): summary-rules-admin.php, one rule per section.
 * - Directory enrichment (&part=enrich): the trust order and Field rules, one per enrichment field and per listing
 *   field (the ACF groups group_aw_listing*).
 *
 * The screen is the Kits shell's (sourceweb-ui.php; its routes in sourceweb-rest-pages.php). This file has the saves
 * those routes call, the old tabs' redirect and the help tabs. Every add, edit and delete is in the Change log with its
 * value before and after.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The parts of the Rules tab: slug => label. */
const AW_RULES_PARTS = array(
	'sections' => 'Section rules',
	'summary'  => 'Summary rules',
	'enrich'   => 'Directory enrichment',
);

// Rules, right after Keywords (the menu order is in menu-model.php).
add_filter(
	'kits_sourcing_tabs',
	fn( $tabs ) => array( 'rules' => array( 'Rules' ) ) + $tabs,
	99
);

// The old tabs' addresses (bookmarks, links in notes and in the Change log) go to their part of Rules.
add_action(
	'admin_init',
	function () {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a GET redirect, nothing saved; each value is sanitized below.
		if ( ! aw_rules_live() || 'admin.php' !== $pagenow || 'aw-sourcing' !== sanitize_key( $_GET['page'] ?? '' ) || 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$tab = sanitize_key( $_GET['tab'] ?? '' );
		if ( ! isset( AW_RULES_PARTS[ $tab ] ) ) {
			return;
		}
		$q = array();
		foreach ( wp_unslash( $_GET ) as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$q[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		unset( $q['page'], $q['tab'] );
		wp_safe_redirect( aw_rules_url( $tab, array_map( 'rawurlencode', $q ) ) ); // 302: a rollback brings the old tabs back
		exit;
	}
);

/* ---------- Section rules → Tag rules ---------- */

/** The tag rules (aw_sources['topic_tags']): tag slug => rule. */
function aw_rules_tag_rules() {
	return (array) ( aw_sources_config()['topic_tags'] ?? array() );
}

/**
 * Save a tag rule (add or edit; a changed tag renames the rule in its place, and the sections that allow the old tag
 * allow the new one). POST sourceweb/v1/rules/tag/save.
 *
 * @param string $tag      Tag slug.
 * @param string $original The rule's tag before ('' for a new rule).
 * @param array  $in       any, unless, hold_reason (sanitized text), hold (regular expressions, one per line).
 * @return array{0: string, 1: string} Notice key (tag_added, tag_saved, tag_exists, tag_empty, tag_badre, none) and what it is about.
 */
function aw_rules_tag_save( $tag, $original, array $in ) {
	list( $rule, $bad ) = aw_rules_tag_rule_clean( $in );
	if ( '' === $tag || ! $rule['any'] ) {
		return array( 'tag_empty', '' );
	}
	if ( $bad ) {
		return array( 'tag_badre', implode( ', ', $bad ) );
	}
	$c     = aw_sources_config();
	$rules = (array) ( $c['topic_tags'] ?? array() );
	if ( $tag !== $original && isset( $rules[ $tag ] ) ) {
		return array( 'tag_exists', $tag );
	}
	$old = '' !== $original ? ( $rules[ $original ] ?? null ) : null;
	if ( null !== $old && $tag === $original && $old === $rule ) {
		return array( 'none', $tag );
	}
	$out   = array();
	$moved = array(); // sections whose allowed tags follow a rule to its new tag
	if ( null === $old ) {
		$out = $rules + array( $tag => $rule ); // a new rule goes last
	} else {
		foreach ( $rules as $k => $v ) { // an edited rule keeps its place, under its (new) tag
			$out[ $k === $original ? $tag : $k ] = $k === $original ? $rule : $v;
		}
		if ( $tag !== $original ) {
			foreach ( (array) ( $c['sections'] ?? array() ) as $t => $sec ) {
				$list = array_map( 'strval', (array) ( $sec['tags'] ?? array() ) );
				$at   = array_search( $original, $list, true );
				if ( false !== $at ) {
					$list[ $at ]                 = $tag;
					$c['sections'][ $t ]['tags'] = array_values( array_unique( $list ) );
					$moved[]                     = $t;
				}
			}
		}
	}
	$c['topic_tags'] = $out;
	update_option( 'aw_sources', $c, false );
	aw_src_log( 'sections', null === $old ? "Tag rule $tag added" : ( $tag === $original ? "Tag rule $tag edited" : "Tag rule $original moved to the tag $tag and edited" . ( $moved ? ' (allowed tag changed with it in ' . implode( ', ', $moved ) . ')' : '' ) ), null === $old ? '' : array( $original => $old ), array( $tag => $rule ) );
	return array( null === $old ? 'tag_added' : 'tag_saved', $tag );
}

/**
 * Delete a tag rule (the tag itself stays; items keep the tags they have).
 *
 * @param string $tag Tag slug.
 * @return array{0: string, 1: string} tag_deleted or none, and the tag.
 */
function aw_rules_tag_delete( $tag ) {
	$c = aw_sources_config();
	if ( '' === $tag || ! isset( $c['topic_tags'][ $tag ] ) ) {
		return array( 'none', $tag );
	}
	$old = $c['topic_tags'][ $tag ];
	unset( $c['topic_tags'][ $tag ] );
	update_option( 'aw_sources', $c, false );
	aw_src_log( 'sections', "Tag rule $tag deleted", array( $tag => $old ), '' );
	return array( 'tag_deleted', $tag );
}

/* ---------- Directory enrichment → trust order and field rules ---------- */

/** The stored option, or the seed when it is not stored yet. */
function aw_rules_enrich_option() {
	$o = get_option( AW_ENRICH_RULES_OPTION );
	return is_array( $o ) && ! empty( $o['fields'] ) ? $o : aw_enrich_field_rules_seed( array_keys( aw_enrich_fields() ) );
}

/**
 * Put one field's rule back to the built-in one (the saved rule is dropped; the Change log keeps it).
 *
 * @param string $field Field key.
 * @return array{0: string, 1: string} field_reset, none or nosuch, and the field.
 */
function aw_rules_field_reset( $field ) {
	$rows = aw_rules_field_rows();
	if ( ! isset( $rows[ $field ] ) ) {
		return array( 'nosuch', $field );
	}
	$opt = aw_rules_enrich_option();
	if ( ! isset( $opt['fields'][ $field ] ) ) {
		return array( 'none', $rows[ $field ]['label'] );
	}
	$old = $opt['fields'][ $field ];
	unset( $opt['fields'][ $field ] );
	update_option( AW_ENRICH_RULES_OPTION, $opt, false );
	aw_src_log( 'enrich', 'Field rule ' . $rows[ $field ]['label'] . ' (' . $field . ') put back to the built-in rule', $old, '' );
	return array( 'field_reset', $rows[ $field ]['label'] );
}

/**
 * Save the trust order from positions (source => 1…99, or '' for after every numbered one).
 *
 * @param array $pos Source key => position.
 * @return array{0: string, 1: string} order_saved or none, and the order in words.
 */
function aw_rules_priority_save( array $pos ) {
	$opt = aw_rules_enrich_option();
	$old = aw_enrich_field_rules()['priority'];
	$new = aw_enrich_priority_from_positions( $pos, aw_enrich_source_keys(), $old );
	if ( $new === $old ) {
		return array( 'none', 'the trust order' );
	}
	$opt['priority'] = $new;
	update_option( AW_ENRICH_RULES_OPTION, $opt, false );
	aw_src_log( 'enrich', 'Trust order changed', $old, $new );
	return array( 'order_saved', implode( ', ', $new ) );
}

/** Every Field rules row: the enrichment fields and the listing fields (rules-model.php aw_enrich_field_rule_rows). */
function aw_rules_field_rows() {
	return aw_enrich_field_rule_rows( aw_enrich_schema(), aw_enrich_fields(), aw_enrich_field_rules()['fields'] );
}

/**
 * Save one field's rule.
 *
 * @param string $field Field key (one of aw_rules_field_rows()).
 * @param array  $in    on, ai, invite, social (bool), multi (int), official (key), sources (keys), apply (key).
 * @return array{0: string, 1: string} field_saved or none, and the field's label.
 */
function aw_rules_field_save( $field, array $in ) {
	$rows = aw_rules_field_rows();
	if ( ! isset( $rows[ $field ] ) ) {
		return array( 'nosuch', $field );
	}
	if ( '' !== ( $rows[ $field ]['why'] ?? '' ) ) {
		$in['on'] = false; // a field type enrichment cannot write stays off
	}
	$old = $rows[ $field ]['rule'];
	$new = aw_enrich_rule_clean( $field, $in, aw_enrich_source_keys(), $old );
	if ( $new === $old && $rows[ $field ]['set'] ) {
		return array( 'none', $rows[ $field ]['label'] );
	}
	$opt                     = aw_rules_enrich_option();
	$opt['fields'][ $field ] = $new;
	update_option( AW_ENRICH_RULES_OPTION, $opt, false );
	aw_src_log( 'enrich', 'Field rule ' . $rows[ $field ]['label'] . ' (' . $field . ')' . ( $rows[ $field ]['set'] ? ' edited' : ' set' ), $old, $new );
	return array( 'field_saved', $rows[ $field ]['label'] );
}

/* ---------- Help tabs ---------- */

add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		if ( ! aw_rules_live() || 'rules' !== sanitize_key( $_GET['tab'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- which help to show
			return;
		}
		$s    = get_current_screen();
		$tabs = array(
			'aw-rules-overview' => array( 'Rules', '<p>One place for the rules the workers follow: <strong>Section rules</strong> (which categories and tags each section uses, and tag rules), <strong>Summary rules</strong> (length, structure and wording of summaries, per section) and <strong>Directory enrichment</strong> (the sources, the trust order and a rule per listing field). Every change is in the Change log with its value before and after. The workers read the rules at the start of every run; Sources sync copies them into the workers repository as their fallback.</p>' ),
			'aw-rules-sections' => array( 'Section rules', '<p>A section is one of the site\'s content types: every public post type (not pages or media) and the ones that opt in (Media &amp; PR). A new content type shows here by itself, with every category allowed and the News summary rule.</p><p>Which categories and tags each section uses: the workers pick only from these, and each section\'s filters show only these. A section with no category ticked uses every category.</p><p><strong>Tag rules</strong> give an item a tag when its title or text has one of the phrases, in a section that allows the tag. An item that also matches a "Hold when" pattern, and has none of the "Unless" words, waits for a person with the hold reason.</p>' ),
			'aw-rules-summary'  => array( 'Summary rules', '<p>One rule set for every summary on the site: the workers write and check new summaries and same-story rewrites from these rules (GET aw/v1/summary-rules), and the site holds a News, Library or research item whose summary is over the maximum or not plain text in Plugins → SourceWeb → To review (the rule of any other section is read by the workers). A short summary is never held: the workers read the source again, then other coverage of the same story, and publish the best summary that follows the rules. A summary is never shorter than the search description, which is its lead sentence.</p><p>Each section has its rule. News, Library and Resources (research) have their own; any other section inherits News until it is given its own (untick "Inherits News").</p>' ),
			'aw-rules-enrich'   => array( 'Directory enrichment', '<p>The enrichment worker fills empty listing details from the sources switched on here. The <strong>trust order</strong> decides between sources that disagree. <strong>Field rules</strong> list every listing field: the enrichment fields and the fields of the listing field group (Organisation details); a field added to that group in wp-admin shows here with its rule off until it is set.</p><p>A claimed or verified listing is never changed, and a low-confidence value always waits for review; otherwise each field follows its Apply rule.</p>' ),
		);
		foreach ( $tabs as $id => $t ) {
			$s->add_help_tab(
				array(
					'id'      => $id,
					'title'   => $t[0],
					'content' => $t[1],
				)
			);
		}
	}
);
