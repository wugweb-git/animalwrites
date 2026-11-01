<?php
/**
 * Admin menu tidy (owner, 25 Oct 2026, from the admin screenshots): links that nobody here uses come out of the menus
 * and the admin bar, so what is left is what the team works with. Only the links go: every screen still opens at its
 * address, nothing is uninstalled and no plugin's own code is changed.
 *
 * Items are matched by their visible label under their menu (the plugins build their menus at run time, so labels are
 * steadier than internal slugs). Hidden:
 * - Tools: Available Tools (WordPress's old converters), Server-Timing (a Performance Lab developer tool), Scheduled
 *   Actions (FluentCRM's job queue; Animal Writes → Overview shows scheduled-task health).
 * - Settings: Discussion (comments are off site-wide), Connectors (unused AI/MCP connectors).
 * - Upsells and paid features (owner: no paid plans): Rank Math's Unlock PRO, AI Visibility, Content AI, AI Link Genius,
 *   Help & Support; Link Whisper's AI Subscription, Get Premium; Fluent Forms' Support; FluentCRM's Addons, SMTP (a
 *   FluentSMTP promotion: Mail sends the site's email), Help, Forms (FluentCRM's own forms: the site's forms are Fluent
 *   Forms), Recurring Campaigns and Email Sequences (not used).
 * - SCF (Secure Custom Fields' field-group editor: set up once; the fields themselves stay on every edit screen).
 * - Admin bar: Rank Math SEO and UpdraftPlus shortcuts.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Menu label => submenu labels to hide (a label matches when the item's text starts with it). */
const AW_ADMIN_HIDE = array(
	'Tools'         => array( 'Available Tools', 'Server-Timing', 'Scheduled Actions' ),
	'Settings'      => array( 'Discussion', 'Connectors' ),
	'Rank Math SEO' => array( 'Unlock PRO', 'AI Visibility', 'Content AI', 'AI Link Genius', 'Help & Support' ),
	'Link Whisper'  => array( 'AI Subscription', 'Get Premium' ),
	'Fluent Forms'  => array( 'Support' ),
	'FluentCRM'     => array( 'Addons', 'SMTP', 'Help', 'Forms', 'Recurring Campaigns', 'Email Sequences' ),
);

/** Top-level menus to hide, by label. */
const AW_ADMIN_HIDE_TOP = array( 'SCF' );

/** Admin bar shortcuts to hide, by label. */
const AW_ADMIN_BAR_HIDE = array( 'Rank Math SEO', 'UpdraftPlus' );

/** A menu item's plain label: no count bubbles, badges or icons. */
function aw_admin_label( $title ) {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '#<span[^>]*class="[^"]*(count|awaiting|update-plugins|badge)[^"]*"[^>]*>.*?</span>#s', '', (string) $title ) ) ) );
}

/** Whether a label starts with one of the given labels. */
function aw_admin_label_in( $label, $list ) {
	foreach ( $list as $l ) {
		if ( 0 === stripos( $label, $l ) ) {
			return true;
		}
	}
	return false;
}

add_action(
	'admin_menu',
	function () {
		global $menu, $submenu;
		foreach ( (array) $menu as $i => $m ) {
			$label = aw_admin_label( $m[0] ?? '' );
			$slug  = (string) ( $m[2] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			if ( aw_admin_label_in( $label, AW_ADMIN_HIDE_TOP ) && strlen( $label ) <= 4 ) { // "SCF" only, not "Scheduled…"
				unset( $menu[ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				continue;
			}
			$hide = AW_ADMIN_HIDE[ $label ] ?? null;
			if ( ! $hide || empty( $submenu[ $slug ] ) ) {
				continue;
			}
			foreach ( $submenu[ $slug ] as $j => $item ) {
				$sub = aw_admin_label( $item[0] ?? '' );
				// "Forms" under FluentCRM must not catch "Fluent Forms"-style labels elsewhere: exact match for one-word labels.
				$match = false;
				foreach ( $hide as $h ) {
					if ( false === strpos( $h, ' ' ) ? 0 === strcasecmp( $sub, $h ) : 0 === stripos( $sub, $h ) ) {
						$match = true;
						break;
					}
				}
				if ( $match ) {
					unset( $submenu[ $slug ][ $j ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				}
			}
		}
	},
	PHP_INT_MAX
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		foreach ( (array) $bar->get_nodes() as $node ) {
			if ( empty( $node->parent ) && aw_admin_label_in( aw_admin_label( $node->title ), AW_ADMIN_BAR_HIDE ) ) {
				$bar->remove_node( $node->id );
			}
		}
	},
	PHP_INT_MAX
);

		if ( ! $form_id || ! in_array( $form_id, aw_ff_autoread_ids(), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'fluentform_submissions', array( 'status' => 'read' ), array( 'id' => (int) $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	},
	99,
	3
);
