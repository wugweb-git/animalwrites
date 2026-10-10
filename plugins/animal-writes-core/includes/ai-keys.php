<?php
/**
 * Settings → AI keys (owner, 10 Oct 2026): a copy of the summary-service keys the news workers use.
 * The workers read their own copy from GitHub Actions secrets; this page is the owner's reference.
 * Stored in option aw_ai_keys (not autoloaded). Administrators only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_AI_PROVIDERS = array(
	'openrouter' => 'OpenRouter',
	'gemini'     => 'Gemini / Google',
	'groq'       => 'Groq',
	'deepseek'   => 'DeepSeek',
	'openai'     => 'OpenAI',
	'xai'        => 'xAI',
	'moonshot'   => 'Moonshot',
	'minimax'    => 'MiniMax',
	'anthropic'  => 'Anthropic (Claude)',
	'cline'      => 'Cline',
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'AI keys', 'AI keys', 'manage_options', 'aw-ai-keys', 'aw_ai_keys_screen' );
	}
);

add_action(
	'admin_post_aw_ai_keys',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_ai_keys' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$in   = (array) wp_unslash( $_POST['aw_ai_keys'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$keys = array();
		foreach ( array_keys( AW_AI_PROVIDERS ) as $p ) {
			$keys[ $p ] = sanitize_textarea_field( (string) ( $in[ $p ] ?? '' ) );
		}
		update_option( 'aw_ai_keys', $keys, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-ai-keys&saved=1' ) );
		exit;
	}
);

function aw_ai_keys_screen() {
	$keys = (array) get_option( 'aw_ai_keys', array() );
	echo '<div class="wrap"><h1>AI keys</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<p>Keys for the summary services, one per line. The news workers use their own copy in GitHub (Actions secrets) — change both together.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_ai_keys">';
	wp_nonce_field( 'aw_ai_keys' );
	echo '<table class="form-table">';
	foreach ( AW_AI_PROVIDERS as $p => $label ) {
		echo '<tr><th><label for="aw-ai-' . esc_attr( $p ) . '">' . esc_html( $label ) . '</label></th><td><textarea id="aw-ai-' . esc_attr( $p ) . '" name="aw_ai_keys[' . esc_attr( $p ) . ']" rows="3" class="large-text code" autocomplete="off" spellcheck="false">' . esc_textarea( (string) ( $keys[ $p ] ?? '' ) ) . '</textarea></td></tr>';
	}
	echo '</table>';
	submit_button( 'Save keys' );
	echo '</form></div>';
}
