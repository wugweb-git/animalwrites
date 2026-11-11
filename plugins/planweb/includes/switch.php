<?php
/**
 * The switch (Kits → Modules → PlanWeb) and the one-time setup once it is on: capabilities and the fixed list values.
 * Turning PlanWeb off hides it again and brings back Kits → Dates; entries and the Dates option are kept either way.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether PlanWeb is switched on (Kits → Modules → PlanWeb, or activating the plugin on its own). Off: PlanWeb adds
 * only its switch, and Kits → Dates works as before. On: the calendar moves here, and Dates is imported once.
 */
function planweb_on(): bool {
	return '1' === (string) get_option( 'planweb_on', '' );
}

/** Activating PlanWeb on its own switches it on. */
function planweb_activate() {
	update_option( 'planweb_on', '1', true );
}

/** Bump when the capabilities or the list values change, so planweb_setup() runs again. */
const PLANWEB_SETUP = '1';

// Kits → Modules (Animal Writes Core's Settings API screen), when it is there.
add_action(
	'admin_init',
	function () {
		register_setting(
			'aw_modules',
			'planweb_on',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => fn( $v ) => $v ? '1' : '',
				'show_in_rest'      => false,
			)
		);
		if ( ! function_exists( 'aw_modules_screen' ) ) {
			return;
		}
		add_settings_section(
			'planweb_module',
			__( 'PlanWeb', 'planweb' ),
			function () {
				echo '<p>' . esc_html__( 'The team\'s calendar as its own plugin. Nothing is posted or sent from it.', 'planweb' ) . '</p>';
			},
			'aw-modules'
		);
		add_settings_field(
			'planweb_on',
			__( 'Calendar in PlanWeb', 'planweb' ),
			function () {
				printf(
					'<input type="checkbox" id="planweb_on" name="planweb_on" value="1"%1$s> <span class="description">%2$s</span>',
					checked( '1', (string) get_option( 'planweb_on', '' ), false ),
					esc_html__( 'On: Kits → Dates moves to PlanWeb → Calendar, and every date in Dates is copied there once (the Dates list itself is kept unchanged as a backup). Off: Kits → Dates as before; entries are kept.', 'planweb' )
				);
			},
			'aw-modules',
			'planweb_module',
			array( 'label_for' => 'planweb_on' )
		);
	}
);

// Once PlanWeb is on: capabilities and list values (again when PLANWEB_SETUP changes). On init, so the taxonomies exist.
add_action(
	'init',
	function () {
		if ( planweb_on() && PLANWEB_SETUP !== (string) get_option( 'planweb_setup', '' ) ) {
			planweb_setup();
		}
	},
	20
);

/**
 * Capabilities: editors and administrators work with entries (as edit_others_posts did for Dates); only
 * administrators set Approved or Sent (planweb_approve). The fixed list values as terms.
 */
function planweb_setup() {
	$caps = array( 'edit_planweb_entries', 'edit_others_planweb_entries', 'edit_published_planweb_entries', 'edit_private_planweb_entries', 'publish_planweb_entries', 'read_private_planweb_entries', 'delete_planweb_entries', 'delete_others_planweb_entries', 'delete_published_planweb_entries', 'delete_private_planweb_entries' );
	foreach ( array( 'administrator', 'editor' ) as $name ) {
		$role = get_role( $name );
		if ( ! $role ) {
			continue;
		}
		foreach ( $caps as $cap ) {
			$role->add_cap( $cap );
		}
		if ( 'administrator' === $name ) {
			$role->add_cap( 'planweb_approve' );
		}
	}
	foreach ( PLANWEB_TAXONOMIES as $tax => $values ) {
		if ( ! taxonomy_exists( $tax ) ) {
			return; // Try again on the next request.
		}
		foreach ( $values as $slug => $label ) {
			if ( ! term_exists( $slug, $tax ) ) {
				wp_insert_term( $label, $tax, array( 'slug' => $slug ) );
			}
		}
	}
	update_option( 'planweb_setup', PLANWEB_SETUP, false );
}
