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

/**
 * Deactivating PlanWeb switches it off. Animal Writes Core still loads it, so without this the calendar would stay on
 * and Kits → Dates hidden. Entries and the Dates option are kept.
 */
function planweb_deactivate() {
	update_option( 'planweb_on', '', true );
}

// Switching off (Kits → Modules or deactivation): the capabilities come off the roles, and setup runs again next time.
add_action(
	'update_option_planweb_on',
	function ( $old, $value ) {
		if ( '1' !== (string) $value ) {
			planweb_remove_caps();
		}
	},
	10,
	2
);

/** Bump when the capabilities or the list values change, so planweb_setup() runs again. */
const PLANWEB_SETUP = '2'; // 2: Threads and Bluesky channels (14 Nov 2026).

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
				'sanitize_callback' => 'planweb_on_clean',
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'aw_modules',
			'planweb_site_on',
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => 'planweb_on_clean',
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
					esc_html__( 'On: Appearance → Kits → Dates moves to Plugins → PlanWeb → Calendar, and every date in Dates is copied there once (the Dates list itself is kept unchanged as a backup). Off: Appearance → Kits → Dates as before; entries are kept.', 'planweb' )
				);
			},
			'aw-modules',
			'planweb_module',
			array( 'label_for' => 'planweb_on' )
		);
		add_settings_field(
			'planweb_site_on',
			__( 'Site schedule in PlanWeb', 'planweb' ),
			function () {
				printf(
					'<input type="checkbox" id="planweb_site_on" name="planweb_site_on" value="1"%1$s> <span class="description">%2$s</span>',
					checked( planweb_site_on(), true, false ),
					esc_html__( 'On: published, scheduled and missed posts and FluentCRM newsletters show beside the entries (read only; nothing is copied). Off: PlanWeb shows only its own entries.', 'planweb' )
				);
			},
			'aw-modules',
			'planweb_module',
			array( 'label_for' => 'planweb_site_on' )
		);
		add_settings_field(
			'planweb_delete_data_on_uninstall',
			__( 'Delete this plugin\'s data when it is deleted', 'planweb' ),
			function () {
				printf(
					'<input type="checkbox" id="planweb_delete_data_on_uninstall" name="planweb_delete_data_on_uninstall" value="1"%1$s> <p class="description">%2$s</p>',
					checked( (bool) get_option( 'planweb_delete_data_on_uninstall', false ), true, false ),
					esc_html__( 'Off: everything stays in the database if the plugin is deleted.', 'planweb' )
				);
			},
			'aw-modules',
			'planweb_module',
			array( 'label_for' => 'planweb_delete_data_on_uninstall' )
		);
	}
);

// Whether deleting PlanWeb also deletes its own settings (uninstall.php). Off by default. On init, so the REST API has
// it too; its box is in the Kits → Modules form above, under the switch.
add_action(
	'init',
	function () {
		register_setting(
			'aw_modules',
			'planweb_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
	}
);

/** Whether PlanWeb's data goes when the plugin is deleted. */
function planweb_delete_data_on(): bool {
	return (bool) get_option( 'planweb_delete_data_on_uninstall', false );
}

/**
 * Save "Delete this plugin's data when it is deleted". Nothing is written when it is already so.
 *
 * @param bool $on On.
 * @return bool Whether the option was written.
 */
function planweb_delete_data_save( bool $on ): bool {
	if ( planweb_delete_data_on() === $on ) {
		return false;
	}
	update_option( 'planweb_delete_data_on_uninstall', $on );
	return true;
}

/**
 * "Reset this section" (PlanWeb → Settings → PlanWeb, and Kits → Modules → PlanWeb): "Delete this plugin's data" back
 * to its registered default (off). The switch (planweb_on) is left as it is: it says whether PlanWeb is in use (on when
 * the plugin is activated), and its default, off, would take these screens away and move the calendar back.
 *
 * @return bool Whether anything was written.
 */
function planweb_settings_reset(): bool {
	$default = (bool) ( get_registered_settings()['planweb_delete_data_on_uninstall']['default'] ?? false );
	return planweb_delete_data_save( $default );
}

// Kits → Modules → PlanWeb gets "Reset this section" (Animal Writes Core's aw_core_reset_sections()).
add_filter(
	'aw_core_reset_sections',
	function ( $sections ) {
		$sections                   = (array) $sections;
		$sections['planweb_module'] = 'planweb_settings_reset';
		return $sections;
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

/** The entry capabilities given to editors and administrators. */
const PLANWEB_CAPS = array( 'edit_planweb_entries', 'edit_others_planweb_entries', 'edit_published_planweb_entries', 'edit_private_planweb_entries', 'publish_planweb_entries', 'read_private_planweb_entries', 'delete_planweb_entries', 'delete_others_planweb_entries', 'delete_published_planweb_entries', 'delete_private_planweb_entries' );

/** Take PlanWeb's capabilities off every role, so they are not left behind while it is off. */
function planweb_remove_caps() {
	foreach ( wp_roles()->role_objects as $role ) {
		foreach ( array_merge( PLANWEB_CAPS, array( 'planweb_approve' ) ) as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
	delete_option( 'planweb_setup' );
}

/**
 * Capabilities: editors and administrators work with entries (as edit_others_posts did for Dates); only
 * administrators set Approved or Sent (planweb_approve). The fixed list values as terms.
 */
function planweb_setup() {
	foreach ( array( 'administrator', 'editor' ) as $name ) {
		$role = get_role( $name );
		if ( ! $role ) {
			continue;
		}
		foreach ( PLANWEB_CAPS as $cap ) {
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
