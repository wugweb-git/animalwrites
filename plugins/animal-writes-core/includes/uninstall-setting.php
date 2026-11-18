<?php
/**
 * Kits → Modules → Animal Writes Core data: whether deleting this plugin also deletes its own settings and tables
 * (uninstall.php). Off by default. The rest of the Modules form is Kits Core's (admin-layer/site-options.php, since
 * 18 Nov 2026); this box stays with the plugin it is about and goes when the plugin goes.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// On init, so the REST API has it too.
add_action(
	'init',
	function () {
		register_setting(
			'aw_modules',
			'aw_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
	}
);

// Its box, last in the Kits → Modules form.
add_action(
	'admin_init',
	function () {
		add_settings_section( 'aw_modules_uninstall', 'Animal Writes Core data', '__return_null', 'aw-modules' );
		add_settings_field( 'aw_delete_data_on_uninstall', 'Delete this plugin\'s data when it is deleted', 'aw_site_options_uninstall', 'aw-modules', 'aw_modules_uninstall', array( 'label_for' => 'aw_delete_data_on_uninstall' ) );
	}
);

// "Reset this section" puts it back to off.
add_filter( 'kits_site_reset_sections', fn( $s ) => array_merge( (array) $s, array( 'aw_modules_uninstall' => array( 'aw_delete_data_on_uninstall' ) ) ) );

/** Delete the data on uninstall: on/off. */
function aw_site_options_uninstall(): void {
	printf(
		'<input type="checkbox" id="aw_delete_data_on_uninstall" name="aw_delete_data_on_uninstall" value="1"%1$s> <p class="description">%2$s</p>',
		checked( (bool) get_option( 'aw_delete_data_on_uninstall', false ), true, false ),
		esc_html( 'Off: everything stays in the database if the plugin is deleted.' )
	);
}
