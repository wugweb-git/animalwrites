<?php
/**
 * Sourcing → Rules (sourcing restructure phase 3, owner 25 Oct 2026): one tab in three parts, from the
 * phase 3 migration on. The old Sections, Summary rules and Directory enrichment tabs redirect here.
 *
 * - Section rules (&part=sections): one row per section, the site's content types (AW_Sourcing_Sections_Table); Edit
 *   opens the section's category and tag checklists (sourcing-terms-admin.php). Below it the tag rules (today's topic
 *   tags, aw_sources['topic_tags']) in the edit-tags layout: "Add New Rule" on the left, the list on the right.
 * - Summary rules (&part=summary): the Settings API form of summary-rules-admin.php, one row per section.
 * - Directory enrichment (&part=enrich): the source switches, Google key and cap (enrichment-admin.php), the trust order,
 *   and Field rules: one row per enrichment field and per listing field (the ACF groups group_aw_listing*), each with
 *   its rule; Edit (&field=<key>) is a form-table screen.
 *
 * Core parts only: WP_List_Table (AW_Sourcing_Sections_Table, AW_Sourcing_Simple_Table), form-table forms posting to
 * admin-post.php with a nonce, core notices and help tabs. Every add, edit and delete is in the Change log with its
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

// From phase 3: Rules in place of Sections, Summary rules and Directory enrichment. The page puts Sources and Keywords
// first and these tabs after them (sourcing-policy.php aw_sourcing_page), so the first of them is right after Keywords.
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		if ( ! aw_rules_live() ) {
			return $tabs;
		}
		unset( $tabs['sections'], $tabs['summary'], $tabs['enrich'] );
		return array( 'rules' => array( 'Rules', 'aw_rules_tab', true ) ) + $tabs;
	},
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

/** The part of the Rules tab shown. */
function aw_rules_part() {
	$p = sanitize_key( $_GET['part'] ?? 'sections' ); // phpcs:ignore WordPress.Security.NonceVerification -- which part to show
	return isset( AW_RULES_PARTS[ $p ] ) ? $p : 'sections';
}

/** The Rules tab. */
function aw_rules_tab() {
	$part = aw_rules_part();
	$last = array_key_last( AW_RULES_PARTS );
	echo '<ul class="subsubsub">';
	foreach ( AW_RULES_PARTS as $k => $label ) {
		echo '<li><a href="' . esc_url( aw_rules_url( $k ) ) . '"' . ( $part === $k ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>' . ( $k === $last ? '' : ' | ' ) . '</li>';
	}
	echo '</ul><br class="clear">';
	aw_rules_notice();
	// phpcs:disable WordPress.Security.NonceVerification -- which screen to show, nothing saved.
	if ( 'summary' === $part ) {
		aw_summary_rules_tab();
		return;
	}
	if ( 'enrich' === $part ) {
		$field = sanitize_key( $_GET['field'] ?? '' );
		if ( '' !== $field ) {
			aw_rules_field_form( $field );
			return;
		}
		aw_enrich_settings_tab();
		aw_rules_priority_form();
		aw_rules_field_rules_table();
		return;
	}
	$edit = sanitize_key( $_GET['edit'] ?? '' );
	$tag  = sanitize_title( wp_unslash( $_GET['tagrule'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification
	if ( '' !== $edit ) {
		aw_sourcing_terms_notice();
		aw_sourcing_section_form( $edit );
		return;
	}
	if ( '' !== $tag ) {
		aw_rules_tag_form( $tag );
		return;
	}
	aw_sourcing_terms_notice();
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-sections-table.php';
	$table = new AW_Sourcing_Sections_Table();
	$table->prepare_items();
	$table->display();

	echo '<h2>Tag rules</h2>';
	echo '<div id="col-container" class="wp-clearfix"><div id="col-left"><div class="col-wrap"><div class="form-wrap">';
	echo '<h2>Add New Rule</h2>';
	aw_rules_tag_fields( '', array() );
	echo '</div></div></div><div id="col-right"><div class="col-wrap">';
	aw_rules_tag_table();
	echo '</div></div></div>';
}

/** After a Rules save: what happened, as a core notice. */
function aw_rules_notice() {
	// phpcs:disable WordPress.Security.NonceVerification -- a notice only.
	$done = sanitize_key( $_GET['aw_rules_done'] ?? '' );
	$for  = sanitize_text_field( wp_unslash( $_GET['aw_rules_for'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification
	$msg = array(
		'tag_added'   => array( 'success', 'Tag rule %s added. The workers use it from their next run.' ),
		'tag_saved'   => array( 'success', 'Tag rule %s saved. The workers use it from their next run.' ),
		'tag_deleted' => array( 'success', 'Tag rule %s deleted.' ),
		'tag_exists'  => array( 'error', 'A tag rule for %s exists already: edit it instead.' ),
		'tag_empty'   => array( 'error', 'Choose a tag and give at least one phrase.' ),
		'tag_badre'   => array( 'error', 'Not saved: a hold pattern is not a valid regular expression (%s).' ),
		'field_saved' => array( 'success', 'Field rule %s saved. The enrichment worker uses it from its next run.' ),
		'order_saved' => array( 'success', 'Trust order saved: %s.' ),
		'none'        => array( 'info', 'Nothing changed%s.' ),
	);
	if ( isset( $msg[ $done ] ) ) {
		$for = 'none' === $done ? ( '' !== $for ? ' for ' . $for : '' ) : $for;
		echo '<div class="notice notice-' . esc_attr( $msg[ $done ][0] ) . ' is-dismissible"><p>' . esc_html( sprintf( $msg[ $done ][1], $for ) ) . '</p></div>';
	}
}
add_filter( 'removable_query_args', fn( $args ) => array_merge( $args, array( 'aw_rules_done', 'aw_rules_for' ) ) );

/**
 * Back to a part of Rules with a notice.
 *
 * @param string $part Part.
 * @param string $done Notice key.
 * @param string $for  What it was about.
 * @param array  $args More query arguments.
 */
function aw_rules_back( $part, $done, $for = '', array $args = array() ) {
	wp_safe_redirect(
		aw_rules_url(
			$part,
			$args + array(
				'aw_rules_done' => $done,
				'aw_rules_for'  => rawurlencode( $for ),
			)
		)
	);
	exit;
}

/* ---------- Section rules → Tag rules ---------- */

/** The tag rules (aw_sources['topic_tags']): tag slug => rule. */
function aw_rules_tag_rules() {
	return (array) ( aw_sources_config()['topic_tags'] ?? array() );
}

/** The tag rules as a core list table. */
function aw_rules_tag_table() {
	$secs = (array) ( aw_sources_config()['sections'] ?? array() );
	$labs = aw_rules_section_labels();
	$rows = array();
	foreach ( aw_rules_tag_rules() as $slug => $r ) {
		$slug = (string) $slug;
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		if ( ! $term && 0 === strpos( $slug, 'campaign-' ) && taxonomy_exists( 'aw_campaign_link' ) ) { // a campaign slug: ingest links it through Linked campaigns, not a tag
			$term = get_term_by( 'slug', $slug, 'aw_campaign_link' );
		}
		$edit = aw_rules_url( 'sections', array( 'tagrule' => $slug ) );
		$del  = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'aw_rules_tag_delete',
					'tag'    => rawurlencode( $slug ),
				),
				admin_url( 'admin-post.php' )
			),
			'aw_rules_tag_delete_' . $slug
		);
		$in   = array();
		foreach ( $secs as $t => $sec ) {
			if ( in_array( $slug, array_map( 'strval', (array) ( $sec['tags'] ?? array() ) ), true ) ) {
				$in[] = $labs[ $t ] ?? $t;
			}
		}
		$any    = (array) ( $r['any'] ?? array() );
		$rows[] = array(
			'tag'     => '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $term ? $term->name : urldecode( $slug ) . ' (no such tag)' ) . '</a></strong> <code>' . esc_html( urldecode( $slug ) ) . '</code>'
				. '<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '">Edit</a> | </span><span class="delete"><a class="submitdelete" href="' . esc_url( $del ) . '">Delete</a></span></div>',
			'any'     => esc_html( implode( ', ', $any ) ),
			'hold'    => empty( $r['hold'] ) ? '—' : '<code>' . implode( '</code>, <code>', array_map( 'esc_html', (array) $r['hold'] ) ) . '</code>',
			'unless'  => esc_html( implode( ', ', (array) ( $r['unless'] ?? array() ) ) ),
			'reason'  => esc_html( (string) ( $r['hold_reason'] ?? '' ) ),
			'section' => $in ? esc_html( implode( ', ', $in ) ) : 'No section allows this tag yet',
			'_find'   => $slug . ' ' . implode( ' ', $any ),
		);
	}
	aw_sourcing_simple_table(
		'tagrules',
		array(
			'tag'     => 'Tag',
			'any'     => 'Phrases',
			'hold'    => 'Hold when',
			'unless'  => 'Unless',
			'reason'  => 'Hold reason',
			'section' => 'Sections that may give it',
		),
		$rows,
		array( 'empty' => 'No tag rules yet.' )
	);
}

/**
 * The tag rule fields (Add New Rule on the left, or the edit screen).
 *
 * @param string $slug The rule's tag ('' for a new rule).
 * @param array  $r    The rule.
 */
function aw_rules_tag_fields( $slug, array $r ) {
	$lines = fn( $k ) => implode( "\n", array_map( 'strval', (array) ( $r[ $k ] ?? array() ) ) );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_rules_tag_save"><input type="hidden" name="original" value="' . esc_attr( $slug ) . '">';
	wp_nonce_field( 'aw_rules_tag_save' );
	$edit = '' !== $slug;
	echo $edit ? '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="aw-tr-tag">Tag</label></th><td>' : '<div class="form-field form-required"><label for="aw-tr-tag">Tag</label>';
	wp_dropdown_categories(
		array(
			'taxonomy'          => 'post_tag',
			'hide_empty'        => 0,
			'name'              => 'tag',
			'id'                => 'aw-tr-tag',
			'value_field'       => 'slug',
			'orderby'           => 'name',
			'selected'          => $slug,
			'show_option_none'  => $edit ? '' : 'Choose a tag',
			'option_none_value' => '',
		)
	);
	echo '<p class="description" id="aw-tr-tag-description">The tag an item gets when its title or text has one of the phrases (only in a section that allows the tag).</p>';
	echo $edit ? '</td></tr>' : '</div>';
	$fields = array(
		'any'         => array( 'Phrases', 5, 'One per line. An item with any of them gets the tag.' ),
		'hold'        => array( 'Hold when', 2, 'One regular expression per line (case does not matter). An item that also matches one waits for a person.' ),
		'unless'      => array( 'Unless', 3, 'One per line. An item with any of these words is not held.' ),
		'hold_reason' => array( 'Hold reason', 2, 'What the person checking a held item should look at.' ),
	);
	foreach ( $fields as $k => $f ) {
		$val = 'hold_reason' === $k ? (string) ( $r['hold_reason'] ?? '' ) : $lines( $k );
		$id  = 'aw-tr-' . str_replace( '_', '-', $k );
		$box = '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $k ) . '" rows="' . (int) $f[1] . '" class="large-text code" aria-describedby="' . esc_attr( $id ) . '-description">' . esc_textarea( $val ) . '</textarea><p class="description" id="' . esc_attr( $id ) . '-description">' . esc_html( $f[2] ) . '</p>';
		if ( $edit ) {
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label></th><td>' . $box . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above
		} else {
			echo '<div class="form-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label>' . $box . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above
		}
	}
	if ( $edit ) {
		echo '</tbody></table>';
		submit_button( 'Update', 'primary', 'submit', false );
		echo ' <a href="' . esc_url( aw_rules_url( 'sections' ) ) . '" class="button">Cancel</a>';
	} else {
		submit_button( 'Add New Rule' );
	}
	echo '</form>';
}

/**
 * One tag rule's edit screen.
 *
 * @param string $slug Tag slug.
 */
function aw_rules_tag_form( $slug ) {
	$rules = aw_rules_tag_rules();
	if ( ! isset( $rules[ $slug ] ) ) {
		echo '<div class="notice notice-error inline"><p>No such tag rule. <a href="' . esc_url( aw_rules_url( 'sections' ) ) . '">Back to Section rules</a></p></div>';
		return;
	}
	echo '<h2>' . esc_html( 'Edit tag rule: ' . urldecode( $slug ) ) . '</h2>';
	aw_rules_tag_fields( $slug, (array) $rules[ $slug ] );
}

/** Save a tag rule (add or edit; a changed tag renames the rule in its place). */
add_action(
	'admin_post_aw_rules_tag_save',
	function () {
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'aw_rules_tag_save' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$tag      = sanitize_title( wp_unslash( $_POST['tag'] ?? '' ) );
		$original = sanitize_title( wp_unslash( $_POST['original'] ?? '' ) );
		$in       = array();
		foreach ( array( 'any', 'unless', 'hold_reason' ) as $k ) {
			$in[ $k ] = sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) );
		}
		// Regular expressions: sanitize_textarea_field would strip a < or a %-sequence. Each line is checked to compile
		// (aw_rules_tag_rule_clean), escaped wherever it is shown, and only ever used as a pattern by the workers.
		$hold               = wp_unslash( $_POST['hold'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above
		$in['hold']         = is_string( $hold ) ? wp_check_invalid_utf8( $hold ) : '';
		list( $rule, $bad ) = aw_rules_tag_rule_clean( $in );
		$back               = '' !== $original ? array( 'tagrule' => $original ) : array();
		if ( '' === $tag || ! $rule['any'] ) {
			aw_rules_back( 'sections', 'tag_empty', '', $back );
		}
		if ( $bad ) {
			aw_rules_back( 'sections', 'tag_badre', implode( ', ', $bad ), $back );
		}
		$c     = aw_sources_config();
		$rules = (array) ( $c['topic_tags'] ?? array() );
		if ( $tag !== $original && isset( $rules[ $tag ] ) ) {
			aw_rules_back( 'sections', 'tag_exists', $tag, $back );
		}
		$old = '' !== $original ? ( $rules[ $original ] ?? null ) : null;
		if ( null !== $old && $tag === $original && $old === $rule ) {
			aw_rules_back( 'sections', 'none', $tag, $back );
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
		kits_sourcing_log( 'sections', null === $old ? "Tag rule $tag added" : ( $tag === $original ? "Tag rule $tag edited" : "Tag rule $original moved to the tag $tag and edited" . ( $moved ? ' (allowed tag changed with it in ' . implode( ', ', $moved ) . ')' : '' ) ), null === $old ? '' : array( $original => $old ), array( $tag => $rule ) );
		aw_rules_back( 'sections', null === $old ? 'tag_added' : 'tag_saved', $tag );
	}
);

/** Delete a tag rule (the tag itself stays; items keep the tags they have). */
add_action(
	'admin_post_aw_rules_tag_delete',
	function () {
		$tag = sanitize_title( wp_unslash( $_GET['tag'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- the nonce is per tag, checked next
		if ( ! current_user_can( AW_SRC_CAP ) || '' === $tag || ! check_admin_referer( 'aw_rules_tag_delete_' . $tag ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$c = aw_sources_config();
		if ( ! isset( $c['topic_tags'][ $tag ] ) ) {
			aw_rules_back( 'sections', 'none', $tag );
		}
		$old = $c['topic_tags'][ $tag ];
		unset( $c['topic_tags'][ $tag ] );
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'sections', "Tag rule $tag deleted", array( $tag => $old ), '' );
		aw_rules_back( 'sections', 'tag_deleted', $tag );
	}
);

/* ---------- Directory enrichment → trust order and field rules ---------- */

/** The stored option, or the seed when it is not stored yet. */
function aw_rules_enrich_option() {
	$o = get_option( AW_ENRICH_RULES_OPTION );
	return is_array( $o ) && ! empty( $o['fields'] ) ? $o : aw_enrich_field_rules_seed( array_keys( aw_enrich_fields() ) );
}

/** The trust order as a form-table: a position per source. */
function aw_rules_priority_form() {
	$order = aw_enrich_field_rules()['priority'];
	echo '<h2>Trust order</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_rules_priority">';
	wp_nonce_field( 'aw_rules_priority' );
	echo '<p>When sources give different values for a field, the organisation\'s own claim wins first, then the value most sources agree on, then the source higher in this order. 1 is trusted most. Leave a source empty to rank it after every numbered one.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( aw_enrich_sources() as $k => $s ) {
		$pos = array_search( $k, $order, true );
		echo '<tr><th scope="row"><label for="aw-rp-' . esc_attr( $k ) . '">' . esc_html( $s[0] ) . '</label></th><td><input type="number" min="1" max="99" class="small-text" id="aw-rp-' . esc_attr( $k ) . '" name="pos[' . esc_attr( $k ) . ']" value="' . ( false === $pos ? '' : (int) $pos + 1 ) . '"> <code>' . esc_html( $k ) . '</code></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Save trust order', 'secondary' );
	echo '</form>';
}

add_action(
	'admin_post_aw_rules_priority',
	function () {
		if ( ! current_user_can( AW_SRC_CAP ) || ! check_admin_referer( 'aw_rules_priority' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$pos = array();
		foreach ( (array) wp_unslash( $_POST['pos'] ?? array() ) as $k => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key and number is sanitized here
			$pos[ sanitize_key( $k ) ] = '' === trim( (string) $v ) ? '' : absint( $v );
		}
		$opt = aw_rules_enrich_option();
		$old = aw_enrich_field_rules()['priority'];
		$new = aw_enrich_priority_from_positions( $pos, aw_enrich_source_keys(), $old );
		if ( $new === $old ) {
			aw_rules_back( 'enrich', 'none', 'the trust order' );
		}
		$opt['priority'] = $new;
		update_option( AW_ENRICH_RULES_OPTION, $opt, false );
		kits_sourcing_log( 'enrich', 'Trust order changed', $old, $new );
		aw_rules_back( 'enrich', 'order_saved', implode( ', ', $new ) );
	}
);

/** Every Field rules row: the enrichment fields and the listing fields (rules-model.php aw_enrich_field_rule_rows). */
function aw_rules_field_rows() {
	return aw_enrich_field_rule_rows( aw_enrich_schema(), aw_enrich_fields(), aw_enrich_field_rules()['fields'] );
}

/** Field rules as a core list table. */
function aw_rules_field_rules_table() {
	$names = array_map( fn( $s ) => $s[0], aw_enrich_sources() );
	$yes   = fn( $b ) => $b ? 'Yes' : '—';
	$rows  = array();
	foreach ( aw_rules_field_rows() as $k => $row ) {
		$r      = $row['rule'];
		$edit   = aw_rules_url( 'enrich', array( 'field' => $k ) );
		$rows[] = array(
			'field'    => '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( $row['label'] ) . '</a></strong> <code>' . esc_html( $k ) . '</code>'
				. ( $row['set'] || isset( aw_enrich_fields()[ $k ] ) ? '' : '<br><span class="description">Added in the listing field group: off until its rule is set.</span>' )
				. '<div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '">Edit</a></span></div>',
			'group'    => esc_html( $row['group'] ),
			'store'    => '<code>' . esc_html( $row['store'] ) . '</code>' . ( $row['acf'] ? '' : ' <span class="description">(kept by code)</span>' ),
			'on'       => '' !== ( $row['why'] ?? '' ) ? esc_html( 'Off: ' . $row['why'] ) : ( $r['on'] ? 'On' : 'Off' ),
			'sources'  => $r['sources'] ? esc_html( implode( ', ', array_map( fn( $s ) => $names[ $s ] ?? $s, $r['sources'] ) ) ) : 'Any',
			'multi'    => $r['multi'] ? esc_html( sprintf( 'Up to %d', (int) $r['multi'] ) ) : 'One',
			'official' => '' !== $r['official'] ? esc_html( $names[ $r['official'] ] ?? $r['official'] ) : '—',
			'ai'       => $yes( $r['ai'] ),
			'invite'   => $yes( $r['invite'] ),
			'apply'    => esc_html( ucfirst( (string) $r['apply'] ) ),
			'_find'    => $k . ' ' . $row['label'] . ' ' . $row['group'] . ' ' . $row['store'],
		);
	}
	echo '<h2>Field rules</h2>';
	aw_sourcing_simple_table(
		'fieldrules',
		array(
			'field'    => 'Field',
			'group'    => 'Group',
			'store'    => 'Stored in',
			'on'       => 'On',
			'sources'  => 'May be filled by',
			'multi'    => 'Values',
			'official' => 'Official source',
			'ai'       => 'Read by the AI',
			'invite'   => 'Invite to claim',
			'apply'    => 'Apply',
		),
		$rows,
		array(
			'sortable' => array( 'group', 'on', 'apply' ),
			'search'   => 'Search fields',
			'hidden'   => array(
				'page' => 'aw-sourcing',
				'tab'  => 'rules',
				'part' => 'enrich',
			),
			'empty'    => 'No fields.',
		)
	);
}

/**
 * One field's rule as a form-table screen.
 *
 * @param string $field Field key.
 */
function aw_rules_field_form( $field ) {
	$rows = aw_rules_field_rows();
	$back = aw_rules_url( 'enrich' );
	if ( ! isset( $rows[ $field ] ) ) {
		echo '<div class="notice notice-error inline"><p>No such field. <a href="' . esc_url( $back ) . '">Back to Directory enrichment</a></p></div>';
		return;
	}
	$row  = $rows[ $field ];
	$r    = $row['rule'];
	$srcs = aw_enrich_sources();
	echo '<h2>' . esc_html( 'Edit field rule: ' . $row['label'] ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_rules_field"><input type="hidden" name="field" value="' . esc_attr( $field ) . '">';
	wp_nonce_field( 'aw_rules_field_' . $field );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">Field</th><td>' . esc_html( $row['label'] ) . ' <code>' . esc_html( $field ) . '</code> · ' . esc_html( $row['group'] ) . ' · stored in <code>' . esc_html( $row['store'] ) . '</code></td></tr>';
	$why = (string) ( $row['why'] ?? '' );
	echo '<tr><th scope="row">On</th><td><label><input type="checkbox" name="rule[on]" value="1"' . checked( $r['on'], true, false ) . disabled( '' !== $why, true, false ) . '> The worker may propose values for this field</label>' . ( '' !== $why ? '<p class="description">' . esc_html( ucfirst( $why ) . '.' ) . '</p>' : '' ) . '</td></tr>';
	echo '<tr><th scope="row">May be filled by</th><td><fieldset><legend class="screen-reader-text">May be filled by</legend>';
	foreach ( $srcs as $k => $s ) {
		echo '<label><input type="checkbox" name="rule[sources][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $r['sources'], true ), true, false ) . '> ' . esc_html( $s[0] ) . '</label><br>';
	}
	echo '<p class="description">None ticked: any source.</p></fieldset></td></tr>';
	echo '<tr><th scope="row"><label for="aw-fr-multi">Values</label></th><td><input type="number" min="0" max="5" class="small-text" id="aw-fr-multi" name="rule[multi]" value="' . (int) $r['multi'] . '" aria-describedby="aw-fr-multi-description"><p class="description" id="aw-fr-multi-description">0: one value, and sources that disagree make it low confidence. A number: up to that many different values, never low for disagreeing (phone numbers).</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-fr-official">Official source</label></th><td><select id="aw-fr-official" name="rule[official]"><option value="">None</option>';
	foreach ( $srcs as $k => $s ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['official'], $k, false ) . '>' . esc_html( $s[0] ) . '</option>';
	}
	echo '</select><p class="description">Its value alone is high confidence (the AWBI list for the address and PIN code).</p></td></tr>';
	echo '<tr><th scope="row">Read by the AI</th><td><label><input type="checkbox" name="rule[ai]" value="1"' . checked( $r['ai'], true, false ) . '> Values the AI reads from pages: at most medium confidence unless the organisation said so</label></td></tr>';
	echo '<tr><th scope="row">Invite to claim</th><td><label><input type="checkbox" name="rule[invite]" value="1"' . checked( $r['invite'], true, false ) . '> When only Google has it, the listing is flagged "Invite to claim"</label></td></tr>';
	echo '<tr><th scope="row">Profile link</th><td><label><input type="checkbox" name="rule[social]" value="1"' . checked( $r['social'], true, false ) . '> A social profile: the website linking to it and the page preview count as one source</label></td></tr>';
	echo '<tr><th scope="row"><label for="aw-fr-apply">Apply</label></th><td><select id="aw-fr-apply" name="rule[apply]">';
	foreach ( AW_ENRICH_APPLY as $k => $label ) {
		if ( ( 'both' === $k && ! in_array( $field, AW_ENRICH_TWO_FIELDS, true ) ) || ( 'review' !== $k && in_array( $field, AW_ENRICH_REVIEW_FIELDS, true ) ) ) {
			continue; // two values only where a field can hold them; closed and merge_into always to a person
		}
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['apply'], $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select><p class="description">Claimed and verified listings, and low-confidence values, always wait for review.</p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Update', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a></form>';
}

add_action(
	'admin_post_aw_rules_field',
	function () {
		$field = sanitize_key( $_POST['field'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- the nonce is per field, checked next
		if ( ! current_user_can( AW_SRC_CAP ) || '' === $field || ! check_admin_referer( 'aw_rules_field_' . $field ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$rows = aw_rules_field_rows();
		if ( ! isset( $rows[ $field ] ) ) {
			wp_die( 'No such field.', 400 );
		}
		$raw = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is checked against what it may be in aw_enrich_rule_clean
		$in  = array(
			'on'       => ! empty( $raw['on'] ),
			'ai'       => ! empty( $raw['ai'] ),
			'invite'   => ! empty( $raw['invite'] ),
			'social'   => ! empty( $raw['social'] ),
			'multi'    => absint( $raw['multi'] ?? 0 ),
			'official' => sanitize_key( $raw['official'] ?? '' ),
			'sources'  => array_map( 'sanitize_key', (array) ( $raw['sources'] ?? array() ) ),
			'apply'    => sanitize_key( $raw['apply'] ?? '' ),
		);
		if ( '' !== ( $rows[ $field ]['why'] ?? '' ) ) {
			$in['on'] = false; // a field type enrichment cannot write stays off
		}
		$old = $rows[ $field ]['rule'];
		$new = aw_enrich_rule_clean( $field, $in, aw_enrich_source_keys(), $old );
		if ( $new === $old && $rows[ $field ]['set'] ) {
			aw_rules_back( 'enrich', 'none', $rows[ $field ]['label'], array( 'field' => $field ) );
		}
		$opt                     = aw_rules_enrich_option();
		$opt['fields'][ $field ] = $new;
		update_option( AW_ENRICH_RULES_OPTION, $opt, false );
		kits_sourcing_log( 'enrich', 'Field rule ' . $rows[ $field ]['label'] . ' (' . $field . ')' . ( $rows[ $field ]['set'] ? ' edited' : ' set' ), $old, $new );
		aw_rules_back( 'enrich', 'field_saved', $rows[ $field ]['label'] );
	}
);

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
			'aw-rules-summary'  => array( 'Summary rules', '<p>One rule set for every summary on the site: the workers write and check new summaries and same-story rewrites from these rules (GET aw/v1/summary-rules), and the site holds a News, Library or research item whose summary is over the maximum or not plain text in Sourcing → To review (the rule of any other section is read by the workers). A short summary is never held: the workers read the source again, then other coverage of the same story, and publish the best summary that follows the rules. A summary is never shorter than the search description, which is its lead sentence.</p><p>Each section has its rule. News, Library and Resources (research) have their own; any other section inherits News until it is given its own (untick "Inherits News").</p>' ),
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
