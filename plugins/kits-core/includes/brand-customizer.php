<?php
/**
 * Kits Brand Customizer: tenant brand tokens UI and live CSS injection.
 *
 * Allows 1 default theme + 3 tenant configurations to brand their sites (colors, typography,
 * shape language / corner radii, and layout bounds) without touching code.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/brand-customizer-model.php';

const KITS_BRAND_OPTION = 'kits_brand_tokens';

/**
 * Returns saved brand tokens.
 *
 * @return array<string, string>
 */
function kits_get_brand_tokens(): array {
	$saved = get_option( KITS_BRAND_OPTION, array() );
	return is_array( $saved ) ? $saved : array();
}

/**
 * Injects brand tokens CSS in frontend <head>.
 */
add_action(
	'wp_head',
	function () {
		$tokens = kits_get_brand_tokens();
		$css    = kits_brand_compile_css( $tokens );
		if ( '' !== $css ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS compiled from sanitized token values.
			echo "\n<!-- Kits Brand Customizer Tokens -->\n<style id=\"kits-brand-tokens\">\n" . wp_strip_all_tags( $css ) . "\n</style>\n";
		}
	},
	100
);

/**
 * Injects brand tokens CSS in Block Editor (backend) so previews match frontend.
 */
add_action(
	'admin_head',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}
		$tokens = kits_get_brand_tokens();
		$css    = kits_brand_compile_css( $tokens );
		if ( '' !== $css ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS compiled from sanitized token values.
			echo "\n<!-- Kits Brand Customizer Tokens (Editor) -->\n<style id=\"kits-brand-tokens-editor\">\n.editor-styles-wrapper {\n" . wp_strip_all_tags( $css ) . "\n}\n</style>\n";
		}
	},
	100
);

/**
 * Registers Brand Customizer screen under Kits and Appearance menus.
 */
add_action(
	'admin_menu',
	function () {
		// Under Kits menu
		add_submenu_page(
			'kits',
			__( 'Brand Customizer', 'kits-core' ),
			__( 'Brand Customizer', 'kits-core' ),
			'manage_options',
			'kits-brand',
			'kits_brand_customizer_render_page'
		);

		// Also shortcut under Appearance
		add_theme_page(
			__( 'Brand Tokens', 'kits-core' ),
			__( 'Brand Tokens', 'kits-core' ),
			'manage_options',
			'kits-brand',
			'kits_brand_customizer_render_page'
		);
	},
	15
);

/**
 * Renders the Brand Customizer admin screen.
 */
function kits_brand_customizer_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'kits-core' ) );
	}

	$notices = array();

	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

	// Handle form submissions: Save, Reset, Import, Preset Apply.
	if ( 'POST' === $request_method && check_admin_referer( 'kits_brand_tokens_save', 'kits_brand_nonce' ) ) {
		$action = isset( $_POST['kits_brand_action'] ) ? sanitize_text_field( wp_unslash( $_POST['kits_brand_action'] ) ) : 'save';

		if ( 'reset' === $action ) {
			delete_option( KITS_BRAND_OPTION );
			if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_purge_all' );
			}
			$notices[] = array( 'type' => 'success', 'msg' => __( 'All brand tokens have been reset to theme defaults.', 'kits-core' ) );
		} elseif ( 'apply_preset' === $action ) {
			$preset_key = isset( $_POST['preset_key'] ) ? sanitize_key( wp_unslash( $_POST['preset_key'] ) ) : '';
			$presets    = kits_brand_presets();
			if ( isset( $presets[ $preset_key ] ) ) {
				update_option( KITS_BRAND_OPTION, $presets[ $preset_key ]['tokens'] );
				if ( function_exists( 'do_action' ) ) {
					do_action( 'litespeed_purge_all' );
				}
				$notices[] = array( 'type' => 'success', 'msg' => sprintf( __( 'Preset "%s" applied successfully!', 'kits-core' ), esc_html( $presets[ $preset_key ]['name'] ) ) );
			}
		} elseif ( 'import_json' === $action ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON decoded and each token sanitized by kits_brand_sanitize_tokens().
			$json_raw = isset( $_POST['import_json_data'] ) ? trim( (string) wp_unslash( $_POST['import_json_data'] ) ) : '';
			$decoded  = json_decode( $json_raw, true );
			if ( is_array( $decoded ) ) {
				$sanitized = kits_brand_sanitize_tokens( $decoded );
				update_option( KITS_BRAND_OPTION, $sanitized );
				if ( function_exists( 'do_action' ) ) {
					do_action( 'litespeed_purge_all' );
				}
				$notices[] = array( 'type' => 'success', 'msg' => __( 'Brand tokens imported successfully!', 'kits-core' ) );
			} else {
				$notices[] = array( 'type' => 'error', 'msg' => __( 'Invalid JSON format for import.', 'kits-core' ) );
			}
		} else {
			// Standard Save
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Per-token sanitization by kits_brand_sanitize_tokens().
			$raw_tokens = isset( $_POST['tokens'] ) && is_array( $_POST['tokens'] ) ? wp_unslash( $_POST['tokens'] ) : array();
			$sanitized  = kits_brand_sanitize_tokens( (array) $raw_tokens );
			update_option( KITS_BRAND_OPTION, $sanitized );
			if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_purge_all' );
			}
			$notices[] = array( 'type' => 'success', 'msg' => __( 'Brand tokens updated successfully. Live cache purged.', 'kits-core' ) );
		}
	}

	$saved   = kits_get_brand_tokens();
	$defs    = kits_brand_token_definitions();
	$presets = kits_brand_presets();

	// Group tokens by category
	$categories = array();
	foreach ( $defs as $key => $def ) {
		$cat = $def['category'];
		if ( ! isset( $categories[ $cat ] ) ) {
			$categories[ $cat ] = array();
		}
		$categories[ $cat ][ $key ] = $def;
	}

	$compiled_css = kits_brand_compile_css( $saved );
	$current_json = wp_json_encode( $saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	?>
	<div class="wrap" style="max-width: 1040px; margin-top: 20px;">
		<h1 style="display: flex; align-items: center; gap: 10px;">
			<span class="dashicons dashicons-art" style="font-size: 32px; width: 32px; height: 32px;"></span>
			<?php esc_html_e( 'Kits Brand Customizer (Multi-Tenant Tokens)', 'kits-core' ); ?>
		</h1>
		<p class="description" style="font-size: 14px; margin-bottom: 24px;">
			<?php esc_html_e( 'Configure unique brand tokens for this tenant installation. Overrides apply across both frontend rendering and block editor canvas with zero theme modifications.', 'kits-core' ); ?>
		</p>

		<?php foreach ( $notices as $n ) : ?>
			<div class="notice notice-<?php echo esc_attr( $n['type'] ); ?> is-dismissible">
				<p><strong><?php echo esc_html( $n['msg'] ); ?></strong></p>
			</div>
		<?php endforeach; ?>

		<!-- 1. Quick Presets Card -->
		<div class="postbox" style="padding: 20px; background: #fff; border-radius: 8px; border: 1px solid #ccd0d4; margin-bottom: 24px;">
			<h2 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-admin-appearance"></span>
				<?php esc_html_e( '1-Click Tenant Presets', 'kits-core' ); ?>
			</h2>
			<p><?php esc_html_e( 'Select a pre-engineered tenant brand profile to instantly populate theme tokens:', 'kits-core' ); ?></p>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 12px;">
				<?php foreach ( $presets as $pkey => $preset ) : ?>
					<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #fafafa; display: flex; flex-direction: column; justify-content: space-between;">
						<div>
							<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
								<span style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; background: <?php echo esc_attr( $preset['tokens']['accent'] ); ?>; border: 1px solid rgba(0,0,0,0.15);"></span>
								<strong style="font-size: 13px;"><?php echo esc_html( $preset['name'] ); ?></strong>
							</div>
							<p style="font-size: 12px; color: #64748b; margin: 4px 0 12px; min-height: 34px;"><?php echo esc_html( $preset['description'] ); ?></p>
						</div>
						<form method="post" action="">
							<?php wp_nonce_field( 'kits_brand_tokens_save', 'kits_brand_nonce' ); ?>
							<input type="hidden" name="kits_brand_action" value="apply_preset">
							<input type="hidden" name="preset_key" value="<?php echo esc_attr( $pkey ); ?>">
							<button type="submit" class="button button-secondary" style="width: 100%;"><?php esc_html_e( 'Apply Preset', 'kits-core' ); ?></button>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- 2. Detailed Token Customizer Form -->
		<form method="post" action="">
			<?php wp_nonce_field( 'kits_brand_tokens_save', 'kits_brand_nonce' ); ?>
			<input type="hidden" name="kits_brand_action" value="save">

			<?php foreach ( $categories as $category_name => $tokens_in_cat ) : ?>
				<div class="postbox" style="padding: 20px; background: #fff; border-radius: 8px; border: 1px solid #ccd0d4; margin-bottom: 24px;">
					<h2 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px;">
						<?php if ( 'Colors' === $category_name ) : ?>
							<span class="dashicons dashicons-color-picker"></span>
						<?php elseif ( 'Typography' === $category_name ) : ?>
							<span class="dashicons dashicons-editor-textcolor"></span>
						<?php elseif ( 'Shape' === $category_name ) : ?>
							<span class="dashicons dashicons-forms"></span>
						<?php else : ?>
							<span class="dashicons dashicons-screenoptions"></span>
						<?php endif; ?>
						<?php echo esc_html( $category_name ); ?> <?php esc_html_e( 'Tokens', 'kits-core' ); ?>
					</h2>

					<table class="form-table" role="presentation">
						<tbody>
						<?php foreach ( $tokens_in_cat as $key => $def ) :
							$current_val = $saved[ $key ] ?? $def['default'];
						?>
							<tr>
								<th scope="row" style="width: 240px;">
									<label for="token_<?php echo esc_attr( $key ); ?>">
										<strong><?php echo esc_html( $def['label'] ); ?></strong>
									</label>
									<code style="display: block; font-size: 11px; color: #64748b; margin-top: 4px;"><?php echo esc_html( $def['css_var'] ); ?></code>
								</th>
								<td>
									<?php if ( 'color' === $def['type'] ) : ?>
										<div style="display: flex; align-items: center; gap: 10px;">
											<input type="color"
												id="color_picker_<?php echo esc_attr( $key ); ?>"
												value="<?php echo esc_attr( ( 0 === strpos( $current_val, '#' ) && 7 === strlen( $current_val ) ) ? $current_val : '#000000' ); ?>"
												style="width: 44px; height: 34px; padding: 0; border: 1px solid #ccd0d4; border-radius: 4px; cursor: pointer;"
												onchange="document.getElementById('token_<?php echo esc_attr( $key ); ?>').value = this.value;">
											<input type="text"
												id="token_<?php echo esc_attr( $key ); ?>"
												name="tokens[<?php echo esc_attr( $key ); ?>]"
												value="<?php echo esc_attr( $current_val ); ?>"
												class="regular-text"
												style="max-width: 140px; font-family: monospace;"
												onchange="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('color_picker_<?php echo esc_attr( $key ); ?>').value = this.value;">
											<button type="button" class="button button-small" onclick="document.getElementById('token_<?php echo esc_attr( $key ); ?>').value='<?php echo esc_js( $def['default'] ); ?>'; if(/^#[0-9A-Fa-f]{6}$/.test('<?php echo esc_js( $def['default'] ); ?>')) document.getElementById('color_picker_<?php echo esc_attr( $key ); ?>').value='<?php echo esc_js( $def['default'] ); ?>';">
												<?php esc_html_e( 'Reset', 'kits-core' ); ?>
											</button>
										</div>
									<?php else : ?>
										<div style="display: flex; align-items: center; gap: 10px;">
											<input type="text"
												id="token_<?php echo esc_attr( $key ); ?>"
												name="tokens[<?php echo esc_attr( $key ); ?>]"
												value="<?php echo esc_attr( $current_val ); ?>"
												class="large-text"
												style="max-width: 420px; font-family: monospace;">
											<button type="button" class="button button-small" onclick="document.getElementById('token_<?php echo esc_attr( $key ); ?>').value='<?php echo esc_js( $def['default'] ); ?>';">
												<?php esc_html_e( 'Reset', 'kits-core' ); ?>
											</button>
										</div>
									<?php endif; ?>
									<p class="description" style="margin-top: 4px; font-size: 12px;">
										<?php echo esc_html( $def['description'] ); ?>
										<span style="color: #94a3b8; margin-left: 6px;">(Default: <code><?php echo esc_html( $def['default'] ); ?></code>)</span>
									</p>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

			<p class="submit" style="display: flex; gap: 12px; align-items: center;">
				<button type="submit" class="button button-primary button-hero">
					<span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
					<?php esc_html_e( 'Save Brand Tokens', 'kits-core' ); ?>
				</button>
			</p>
		</form>

		<!-- 3. Export / Import / Raw CSS Diagnostics -->
		<div class="postbox" style="padding: 20px; background: #fff; border-radius: 8px; border: 1px solid #ccd0d4; margin-top: 32px;">
			<h2 style="margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-database-export"></span>
				<?php esc_html_e( 'Tenant Token Backup & Migration (Export / Import)', 'kits-core' ); ?>
			</h2>
			<p><?php esc_html_e( 'Export your token JSON to replicate this brand across other tenant sites, or import an existing configuration file.', 'kits-core' ); ?></p>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;">
				<div>
					<label for="export_json"><strong><?php esc_html_e( 'Active Tenant JSON (Copy/Export)', 'kits-core' ); ?>:</strong></label>
					<textarea id="export_json" readonly rows="8" class="large-text" style="font-family: monospace; font-size: 12px; background: #f8fafc; margin-top: 6px;"><?php echo esc_textarea( $current_json ); ?></textarea>
				</div>
				<div>
					<form method="post" action="">
						<?php wp_nonce_field( 'kits_brand_tokens_save', 'kits_brand_nonce' ); ?>
						<input type="hidden" name="kits_brand_action" value="import_json">
						<label for="import_json_data"><strong><?php esc_html_e( 'Import Configuration (Paste JSON)', 'kits-core' ); ?>:</strong></label>
						<textarea id="import_json_data" name="import_json_data" rows="8" class="large-text" placeholder='{ "accent": "#2563EB", ... }' style="font-family: monospace; font-size: 12px; margin-top: 6px;"></textarea>
						<button type="submit" class="button button-secondary" style="margin-top: 8px;">
							<?php esc_html_e( 'Import & Apply Configuration', 'kits-core' ); ?>
						</button>
					</form>
				</div>
			</div>

			<?php if ( '' !== $compiled_css ) : ?>
				<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
					<label><strong><?php esc_html_e( 'Generated Active CSS Overrides', 'kits-core' ); ?>:</strong></label>
					<pre style="background: #1e293b; color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 11px; overflow-x: auto; margin-top: 6px;"><?php echo esc_html( $compiled_css ); ?></pre>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
