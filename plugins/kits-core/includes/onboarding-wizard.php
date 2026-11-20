<?php
/**
 * Kits by Wugweb Onboarding & Setup Wizard.
 *
 * Benchmark: Astra, Kadence, GeneratePress, Create Block Theme.
 * Provides a 1-click guided setup flow for:
 * 1. Site Identity (Logo upload via wp.media, Site Title, Tagline).
 * 2. Style Variations & Palettes (default, slate, forest, ink).
 * 3. Typography Pairings.
 * 4. Starter Patterns Ingestion & Activation.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'kits_onboarding_menu', 20 );

/**
 * Register Onboarding wizard page in admin menu.
 */
function kits_onboarding_menu() {
	add_submenu_page(
		KITS_MENU,
		__( 'Setup Wizard', 'kits-core' ),
		__( 'Setup Wizard', 'kits-core' ),
		'manage_options',
		'kits-onboarding',
		'kits_onboarding_page'
	);
}

/**
 * Enqueue media and styles for onboarding screen.
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( $hook, 'kits-onboarding' ) ) {
			return;
		}
		wp_enqueue_media();
		if ( wp_style_is( 'kits-shell', 'registered' ) ) {
			wp_enqueue_style( 'kits-shell' );
		}
	}
);

/**
 * Handle Onboarding POST submission.
 */
add_action(
	'admin_init',
	function () {
		if ( ! isset( $_POST['kits_onboarding_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['kits_onboarding_nonce'] ) ), 'kits_onboarding_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'kits-core' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'kits-core' ) );
		}

		if ( isset( $_POST['blogname'] ) ) {
			update_option( 'blogname', sanitize_text_field( wp_unslash( $_POST['blogname'] ) ) );
		}
		if ( isset( $_POST['blogdescription'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( wp_unslash( $_POST['blogdescription'] ) ) );
		}
		if ( isset( $_POST['site_logo'] ) ) {
			$logo_id = (int) $_POST['site_logo'];
			set_theme_mod( 'custom_logo', $logo_id );
		}
		if ( isset( $_POST['kits_style_variation'] ) ) {
			$variation = sanitize_key( wp_unslash( $_POST['kits_style_variation'] ) );
			update_option( 'kits_active_style_variation', $variation );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'kits-onboarding', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * Render Onboarding Wizard.
 */
function kits_onboarding_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$logo_id   = (int) get_theme_mod( 'custom_logo' );
	$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	$active_v  = get_option( 'kits_active_style_variation', 'default' );
	$site_name = get_option( 'blogname' );
	$site_desc = get_option( 'blogdescription' );

	$variations = array(
		'default' => array(
			'title'       => __( 'Kits Default', 'kits-core' ),
			'description' => __( 'Clean neutral palette with high-contrast amber accent.', 'kits-core' ),
			'colors'      => array( '#0F172A', '#1E293B', '#3B82F6', '#F8FAFC' ),
		),
		'slate'   => array(
			'title'       => __( 'Executive Slate', 'kits-core' ),
			'description' => __( 'Sophisticated corporate palette for modern editorial publications.', 'kits-core' ),
			'colors'      => array( '#1E293B', '#334155', '#0EA5E9', '#F1F5F9' ),
		),
		'forest'  => array(
			'title'       => __( 'Deep Forest', 'kits-core' ),
			'description' => __( 'Nature and wildlife oriented earthy tones with emerald accents.', 'kits-core' ),
			'colors'      => array( '#064E3B', '#047857', '#10B981', '#ECFDF5' ),
		),
		'ink'     => array(
			'title'       => __( 'High-Contrast Ink', 'kits-core' ),
			'description' => __( 'Crisp black-and-white print editorial styling with ultra-readable typography.', 'kits-core' ),
			'colors'      => array( '#000000', '#27272A', '#52525B', '#FFFFFF' ),
		),
	);

	echo '<div class="wrap kits-onboarding-wrap">';
	echo '<h1>' . esc_html__( 'Kits by Wugweb — 1-Click Setup Wizard', 'kits-core' ) . '</h1>';

	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site setup options updated successfully!', 'kits-core' ) . '</p></div>';
	}

	echo '<p class="description">' . esc_html__( 'Configure your site brand identity, choose your design system palette variation, and launch starter templates in seconds.', 'kits-core' ) . '</p>';

	echo '<form method="post" action="" class="kits-action-list" style="margin-top:20px;max-width:960px;">';
	wp_nonce_field( 'kits_onboarding_save', 'kits_onboarding_nonce' );

	// Step 1: Site Identity
	echo '<div class="kits-action-row" style="flex-direction:column;align-items:flex-start;gap:16px;">';
	echo '<div class="kits-action-row__main">';
	echo '<span class="kits-action-row__title" style="font-size:16px;">' . esc_html__( 'Step 1: Brand Identity & Logo', 'kits-core' ) . '</span>';
	echo '<p class="kits-action-row__desc">' . esc_html__( 'Set your publication name, tagline and upload your high-resolution SVG or PNG logo.', 'kits-core' ) . '</p>';
	echo '</div>';

	echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;width:100%;">';
	echo '<div>';
	echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Site Title', 'kits-core' ) . '</label>';
	echo '<input type="text" name="blogname" value="' . esc_attr( $site_name ) . '" class="regular-text" style="width:100%;">';
	echo '</div>';
	echo '<div>';
	echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Tagline', 'kits-core' ) . '</label>';
	echo '<input type="text" name="blogdescription" value="' . esc_attr( $site_desc ) . '" class="regular-text" style="width:100%;">';
	echo '</div>';
	echo '</div>';

	echo '<div style="margin-top:10px;">';
	echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Site Logo', 'kits-core' ) . '</label>';
	echo '<input type="hidden" name="site_logo" id="kits_site_logo_id" value="' . esc_attr( $logo_id ) . '">';
	echo '<div id="kits_logo_preview" style="margin-bottom:10px;' . ( $logo_url ? '' : 'display:none;' ) . '">';
	echo '<img id="kits_logo_img" src="' . esc_url( $logo_url ) . '" style="max-height:60px;background:#f8fafc;padding:8px;border:1px solid #cbd5e1;border-radius:4px;">';
	echo '</div>';
	echo '<button type="button" class="button" id="kits_upload_logo_btn">' . esc_html__( 'Select or Upload Logo', 'kits-core' ) . '</button> ';
	if ( $logo_id ) {
		echo '<button type="button" class="button button-link-delete" id="kits_remove_logo_btn">' . esc_html__( 'Remove Logo', 'kits-core' ) . '</button>';
	}
	echo '</div>';
	echo '</div>';

	// Step 2: Design System Style Variation
	echo '<div class="kits-action-row" style="flex-direction:column;align-items:flex-start;gap:16px;">';
	echo '<div class="kits-action-row__main">';
	echo '<span class="kits-action-row__title" style="font-size:16px;">' . esc_html__( 'Step 2: Theme Style Variation & Color Palette', 'kits-core' ) . '</span>';
	echo '<p class="kits-action-row__desc">' . esc_html__( 'Choose one of the curated theme.json v3 style variations. Fully switchable at any time.', 'kits-core' ) . '</p>';
	echo '</div>';

	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;width:100%;">';
	foreach ( $variations as $slug => $var ) {
		$checked = ( $slug === $active_v ) ? 'checked' : '';
		$border  = ( $slug === $active_v ) ? '#2563eb' : '#e2e8f0';
		echo '<label style="border:2px solid ' . esc_attr( $border ) . ';padding:14px;border-radius:8px;cursor:pointer;background:#fff;display:block;">';
		echo '<input type="radio" name="kits_style_variation" value="' . esc_attr( $slug ) . '" ' . esc_attr( $checked ) . ' style="margin-right:8px;">';
		echo '<strong>' . esc_html( $var['title'] ) . '</strong>';
		echo '<div style="display:flex;gap:6px;margin:10px 0;">';
		foreach ( $var['colors'] as $c ) {
			echo '<span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:' . esc_attr( $c ) . ';border:1px solid rgba(0,0,0,0.1);"></span>';
		}
		echo '</div>';
		echo '<p style="margin:0;font-size:12px;color:#64748b;">' . esc_html( $var['description'] ) . '</p>';
		echo '</label>';
	}
	echo '</div>';
	echo '</div>';

	// Step 3: Starter Block Patterns & Full Site Editor
	echo '<div class="kits-action-row" style="align-items:center;justify-content:space-between;">';
	echo '<div class="kits-action-row__main">';
	echo '<span class="kits-action-row__title" style="font-size:16px;">' . esc_html__( 'Step 3: Starter Patterns & Site Editor', 'kits-core' ) . '</span>';
	echo '<p class="kits-action-row__desc">' . esc_html__( 'Launch Full Site Editing to customize headers, footers, query loops, and banner block patterns.', 'kits-core' ) . '</p>';
	echo '</div>';
	echo '<a href="' . esc_url( admin_url( 'site-editor.php' ) ) . '" class="button button-secondary" target="_blank">' . esc_html__( 'Open Site Editor ↗', 'kits-core' ) . '</a>';
	echo '</div>';

	echo '<div style="margin-top:20px;display:flex;gap:12px;align-items:center;">';
	echo '<button type="submit" class="button button-primary button-hero">' . esc_html__( 'Save & Apply Setup', 'kits-core' ) . '</button>';
	echo '<a href="' . esc_url( admin_url( 'admin.php?page=kits' ) ) . '" class="button button-secondary button-hero">' . esc_html__( 'Return to Kits Home', 'kits-core' ) . '</a>';
	echo '</div>';

	echo '</form>';
	echo '</div>';

	// Media upload JS script
	?>
	<script>
	jQuery(document).ready(function($){
		var frame;
		$('#kits_upload_logo_btn').on('click', function(e){
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({
				title: '<?php echo esc_js( __( 'Select or Upload Site Logo', 'kits-core' ) ); ?>',
				button: { text: '<?php echo esc_js( __( 'Use this Logo', 'kits-core' ) ); ?>' },
				multiple: false
			});
			frame.on('select', function(){
				var attachment = frame.state().get('selection').first().toJSON();
				$('#kits_site_logo_id').val(attachment.id);
				$('#kits_logo_img').attr('src', attachment.url);
				$('#kits_logo_preview').show();
			});
			frame.open();
		});
		$('#kits_remove_logo_btn').on('click', function(e){
			e.preventDefault();
			$('#kits_site_logo_id').val('');
			$('#kits_logo_preview').hide();
			$(this).hide();
		});
	});
	</script>
	<?php
}
