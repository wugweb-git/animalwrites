<?php
/**
 * Settings → AI keys (owner, 10 Oct 2026): the summary-service keys the news workers use.
 * Each key is kept separately (owner, 12 Oct 2026): option aw_ai_keys = provider => list of keys (not autoloaded).
 * The workers use these together with their GitHub Actions secrets, one backend per key (GET /aw/v1/ai-keys).
 * Administrators and the ingest user only.
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

/** Saved keys, provider => list of keys. Older saves held one text per provider (one key per line). */
function aw_ai_keys(): array {
	$out = array();
	foreach ( (array) get_option( 'aw_ai_keys', array() ) as $p => $v ) {
		$list = is_array( $v ) ? $v : preg_split( '/[\s,]+/', (string) $v );
		$out[ $p ] = array_values( array_unique( array_filter( array_map( 'trim', (array) $list ) ) ) );
	}
	return $out;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/ai-keys',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'manage_options' ) || 'ingest' === wp_get_current_user()->user_login,
				'callback'            => fn() => new WP_REST_Response( aw_ai_keys() ),
			)
		);
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
			$list       = array_map( fn( $k ) => sanitize_text_field( (string) $k ), (array) ( $in[ $p ] ?? array() ) );
			$keys[ $p ] = array_values( array_unique( array_filter( $list ) ) );
		}
		update_option( 'aw_ai_keys', $keys, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-ai-keys&saved=1' ) );
		exit;
	}
);

function aw_ai_keys_screen() {
	$keys = aw_ai_keys();
	echo '<div class="wrap"><h1>AI keys</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<p>One key per box; each key is used separately. Empty a box to remove that key. The news workers use these keys and their own copy in GitHub (Actions secrets).</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_ai_keys">';
	wp_nonce_field( 'aw_ai_keys' );
	echo '<table class="form-table">';
	foreach ( AW_AI_PROVIDERS as $p => $label ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td data-provider="' . esc_attr( $p ) . '">';
		foreach ( array_merge( $keys[ $p ] ?? array(), array( '' ) ) as $i => $k ) {
			echo '<p><input type="text" name="aw_ai_keys[' . esc_attr( $p ) . '][]" value="' . esc_attr( $k ) . '" class="large-text code" autocomplete="off" spellcheck="false" aria-label="' . esc_attr( $label . ' key ' . ( $i + 1 ) ) . '"' . ( '' === $k ? ' placeholder="Add a key"' : '' ) . '></p>';
		}
		echo '<p><button type="button" class="button" data-aw-add>Add another key</button></p></td></tr>';
	}
	echo '</table>';
	submit_button( 'Save keys' );
	echo '</form></div>';
	echo "<script>document.querySelectorAll('[data-aw-add]').forEach(function(b){b.addEventListener('click',function(){var p=b.parentNode,c=p.previousElementSibling.cloneNode(true),f=c.querySelector('input');f.value='';f.placeholder='Add a key';p.parentNode.insertBefore(c,p);f.focus();});});</script>";
}
