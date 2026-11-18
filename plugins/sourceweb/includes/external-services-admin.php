<?php
/**
 * Admin disclosure screen for the external-services registry: lists every registered service (code-registered
 * ones read-only, the administrator's own ones editable) and lets the administrator add or remove custom
 * endpoints with a per-service opt-in. Built on the Settings API.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the `sourceweb_services` option (the administrator's own entries).
 *
 * @return void
 */
function sourceweb_register_services_setting() {
	register_setting(
		'sourceweb_services',
		'sourceweb_services',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sourceweb_sanitize_services',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'sourceweb_register_services_setting' );
add_filter( 'option_page_capability_sourceweb_services', 'sourceweb_option_page_capability' );

/**
 * Sanitise the submitted service rows into the stored shape (keyed by id). Empty rows are dropped.
 *
 * @param mixed $value Submitted rows.
 * @return array<string,array>
 */
function sourceweb_sanitize_services( $value ) {
	$out = array();
	foreach ( (array) $value as $row ) {
		if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
			continue;
		}
		$svc = sourceweb_normalize_service(
			(string) ( $row['id'] ?? '' ),
			$row,
			'admin'
		);
		if ( null !== $svc ) {
			$out[ $svc['id'] ] = $svc;
		}
	}
	return $out;
}

/**
 * Register the submenu page under the SourceWeb menu.
 *
 * @return void
 */
function sourceweb_services_menu() {
	add_submenu_page(
		'sourceweb',
		__( 'External services', 'sourceweb' ),
		__( 'External services', 'sourceweb' ),
		SOURCEWEB_CAP,
		'sourceweb-services',
		'sourceweb_services_page'
	);
}
add_action( 'admin_menu', 'sourceweb_services_menu' );

/**
 * Render the External services screen.
 *
 * @return void
 */
function sourceweb_services_page() {
	if ( ! current_user_can( SOURCEWEB_CAP ) ) {
		return;
	}
	$all   = sourceweb_external_services();
	$admin = array();
	$code  = array();
	foreach ( $all as $svc ) {
		if ( 'admin' === $svc['origin'] ) {
			$admin[] = $svc;
		} else {
			$code[] = $svc;
		}
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'External services', 'sourceweb' ); ?></h1>
		<p><?php echo esc_html__( 'Every third-party host SourceWeb may contact is declared here. Outbound requests are blocked unless the host is listed and enabled. This list also generates the readme disclosure.', 'sourceweb' ); ?></p>

		<?php if ( $code ) : ?>
			<h2><?php echo esc_html__( 'Provided by the plugin and its modules', 'sourceweb' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Service', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Host', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Purpose', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Data sent', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Policies', 'sourceweb' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $code as $svc ) : ?>
					<tr>
						<td><?php echo esc_html( $svc['id'] ); ?></td>
						<td><code><?php echo esc_html( implode( ', ', (array) $svc['hosts'] ) ); ?></code></td>
						<td><?php echo esc_html( $svc['purpose'] ); ?></td>
						<td><?php echo esc_html( $svc['data_sent'] ); ?></td>
						<td>
							<?php
							if ( $svc['terms_url'] ) :
								?>
								<a href="<?php echo esc_url( $svc['terms_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Terms', 'sourceweb' ); ?></a><?php endif; ?>
							<?php
							if ( $svc['privacy_url'] ) :
								?>
								· <a href="<?php echo esc_url( $svc['privacy_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Privacy', 'sourceweb' ); ?></a><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php echo esc_html__( 'Your endpoints', 'sourceweb' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'sourceweb_services' ); ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Enabled', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Id', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Hosts (comma-separated)', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Purpose', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Data sent', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Terms URL', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Privacy URL', 'sourceweb' ); ?></th>
					<th><?php echo esc_html__( 'Remove', 'sourceweb' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				// Existing admin rows, then two blank rows for adding.
				$rows   = $admin;
				$rows[] = array();
				$rows[] = array();
				foreach ( $rows as $i => $svc ) :
					?>
					<tr>
						<td><input type="checkbox" name="sourceweb_services[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( ! empty( $svc['enabled'] ) ); ?> /></td>
						<td><input type="text" name="sourceweb_services[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( $svc['id'] ?? '' ); ?>" /></td>
						<td><input type="text" name="sourceweb_services[<?php echo (int) $i; ?>][hosts]" value="<?php echo esc_attr( isset( $svc['hosts'] ) ? implode( ', ', (array) $svc['hosts'] ) : '' ); ?>" placeholder="api.example.com, cdn.example.com" /></td>
						<td><input type="text" name="sourceweb_services[<?php echo (int) $i; ?>][purpose]" value="<?php echo esc_attr( $svc['purpose'] ?? '' ); ?>" /></td>
						<td><input type="text" name="sourceweb_services[<?php echo (int) $i; ?>][data_sent]" value="<?php echo esc_attr( $svc['data_sent'] ?? '' ); ?>" /></td>
						<td><input type="url" name="sourceweb_services[<?php echo (int) $i; ?>][terms_url]" value="<?php echo esc_attr( $svc['terms_url'] ?? '' ); ?>" /></td>
						<td><input type="url" name="sourceweb_services[<?php echo (int) $i; ?>][privacy_url]" value="<?php echo esc_attr( $svc['privacy_url'] ?? '' ); ?>" /></td>
						<td>
						<?php
						if ( ! empty( $svc['id'] ) ) :
							?>
							<input type="checkbox" name="sourceweb_services[<?php echo (int) $i; ?>][remove]" value="1" /><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php echo esc_html__( 'A host is contacted only when it is listed here and Enabled is ticked. Tick Remove to delete a row on save.', 'sourceweb' ); ?></p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
