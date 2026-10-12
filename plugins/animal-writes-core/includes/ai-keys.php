<?php
/**
 * Settings → AI models (owner, 10 + 12 Oct 2026): the AI services the news workers use, one row per key.
 * Each row = provider + model + key (+ API address for any other OpenAI-compatible service). Rows are tried
 * top to bottom; any number of rows per provider, each kept separately. Option aw_ai_models (not autoloaded).
 * The workers read the rows (GET /aw/v1/ai-keys) and use them before their GitHub Actions secrets.
 * Administrators and the ingest user only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Known providers: label, default model, chat-completions address ('' = the workers' own client). */
const AW_AI_PROVIDERS = array(
	'gemini'     => array( 'Google Gemini', 'gemini-flash-latest', '' ),
	'groq'       => array( 'Groq', 'openai/gpt-oss-120b', 'https://api.groq.com/openai/v1/chat/completions' ),
	'openrouter' => array( 'OpenRouter', 'openai/gpt-4o-mini', 'https://openrouter.ai/api/v1/chat/completions' ),
	'deepseek'   => array( 'DeepSeek', 'deepseek-chat', 'https://api.deepseek.com/chat/completions' ),
	'openai'     => array( 'OpenAI', 'gpt-4o-mini', 'https://api.openai.com/v1/chat/completions' ),
	'xai'        => array( 'xAI', 'grok-3-mini', 'https://api.x.ai/v1/chat/completions' ),
	'moonshot'   => array( 'Moonshot', 'moonshot-v1-8k', 'https://api.moonshot.ai/v1/chat/completions' ),
	'minimax'    => array( 'MiniMax', 'MiniMax-M2', 'https://api.minimax.io/v1/chat/completions' ),
	'anthropic'  => array( 'Anthropic (Claude)', 'claude-haiku-4-5', '' ),
	'other'      => array( 'Other (OpenAI-compatible)', '', '' ),
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'AI models', 'AI models', 'manage_options', 'aw-ai-keys', 'aw_ai_keys_screen' );
	}
);

/** Saved rows: list of {provider, name, model, url, key, on}. First read converts the older per-provider keys. */
function aw_ai_rows(): array {
	$rows = get_option( 'aw_ai_models', null );
	if ( is_array( $rows ) ) {
		return $rows;
	}
	$rows = array();
	foreach ( (array) get_option( 'aw_ai_keys', array() ) as $p => $v ) {
		foreach ( (array) ( is_array( $v ) ? $v : preg_split( '/[\s,]+/', (string) $v ) ) as $k ) {
			$k = trim( (string) $k );
			if ( '' === $k ) {
				continue;
			}
			$known  = isset( AW_AI_PROVIDERS[ $p ] ) && 'other' !== $p;
			$rows[] = array(
				'provider' => $known ? $p : 'other',
				'name'     => $known ? '' : ucfirst( (string) $p ),
				'model'    => '',
				'url'      => '',
				'key'      => $k,
				'on'       => true,
			);
		}
	}
	update_option( 'aw_ai_models', $rows, false );
	return $rows;
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
				'callback'            => function () {
					$out = array();
					foreach ( aw_ai_rows() as $r ) {
						$p = AW_AI_PROVIDERS[ $r['provider'] ] ?? null;
						if ( ! $p || empty( $r['on'] ) || '' === $r['key'] ) {
							continue;
						}
						$out[] = array(
							'provider' => $r['provider'],
							'name'     => '' !== $r['name'] ? $r['name'] : $r['provider'],
							'model'    => '' !== $r['model'] ? $r['model'] : $p[1],
							'url'      => '' !== $r['url'] ? $r['url'] : $p[2],
							'key'      => $r['key'],
						);
					}
					return new WP_REST_Response( array( 'rows' => $out ) );
				},
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
		$in   = (array) wp_unslash( $_POST['aw_ai'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rows = array();
		foreach ( (array) ( $in['key'] ?? array() ) as $i => $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' === $key ) {
				continue; // An empty key removes the row.
			}
			$p      = sanitize_key( (string) ( $in['provider'][ $i ] ?? '' ) );
			$rows[] = array(
				'provider' => isset( AW_AI_PROVIDERS[ $p ] ) ? $p : 'other',
				'name'     => sanitize_text_field( (string) ( $in['name'][ $i ] ?? '' ) ),
				'model'    => sanitize_text_field( (string) ( $in['model'][ $i ] ?? '' ) ),
				'url'      => esc_url_raw( (string) ( $in['url'][ $i ] ?? '' ) ),
				'key'      => $key,
				'on'       => ! empty( $in['on'][ $i ] ),
			);
		}
		update_option( 'aw_ai_models', $rows, false );
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-ai-keys&saved=1' ) );
		exit;
	}
);

/** One editable row; $i is its position (checkbox values carry it so unticked rows stay aligned). */
function aw_ai_row_html( array $r, $i ): string {
	$opts = '';
	foreach ( AW_AI_PROVIDERS as $id => $p ) {
		$opts .= '<option value="' . esc_attr( $id ) . '" data-model="' . esc_attr( $p[1] ) . '" data-url="' . esc_attr( $p[2] ) . '"' . selected( $r['provider'], $id, false ) . '>' . esc_html( $p[0] ) . '</option>';
	}
	$def = AW_AI_PROVIDERS[ $r['provider'] ] ?? AW_AI_PROVIDERS['other'];
	return '<tr data-aw-row>'
		. '<td><select name="aw_ai[provider][]" aria-label="Provider" data-aw-provider>' . $opts . '</select>'
		. '<input type="text" name="aw_ai[name][]" value="' . esc_attr( $r['name'] ) . '" placeholder="Name" aria-label="Name" class="regular-text"' . ( 'other' === $r['provider'] ? '' : ' hidden' ) . ' data-aw-name></td>'
		. '<td><input type="text" name="aw_ai[model][]" value="' . esc_attr( $r['model'] ) . '" placeholder="' . esc_attr( $def[1] ? $def[1] : 'Model name' ) . '" aria-label="Model" class="regular-text code"></td>'
		. '<td><input type="text" name="aw_ai[key][]" value="' . esc_attr( $r['key'] ) . '" placeholder="Key" aria-label="Key" class="regular-text code" autocomplete="off" spellcheck="false">'
		. '<input type="url" name="aw_ai[url][]" value="' . esc_attr( $r['url'] ) . '" placeholder="' . esc_attr( $def[2] ? $def[2] : 'API address (…/chat/completions)' ) . '" aria-label="API address" class="regular-text code"' . ( '' === $def[2] && 'other' !== $r['provider'] ? ' hidden' : '' ) . ' data-aw-url></td>'
		. '<td><input type="checkbox" name="aw_ai[on][' . esc_attr( (string) $i ) . ']" value="1" aria-label="Use"' . checked( ! empty( $r['on'] ), true, false ) . '></td>'
		. '<td><button type="button" class="button" data-aw-up aria-label="Move up">↑</button> <button type="button" class="button" data-aw-down aria-label="Move down">↓</button> <button type="button" class="button-link" data-aw-remove>Remove</button></td>'
		. '</tr>';
}

function aw_ai_keys_screen() {
	$rows  = aw_ai_rows();
	$blank = array( 'provider' => 'gemini', 'name' => '', 'model' => '', 'url' => '', 'key' => '', 'on' => true );
	echo '<div class="wrap"><h1>AI models</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<p>One row per key. The news workers try the rows from the top down, then the keys in their GitHub secrets. Leave Model empty for the default shown. For a service not listed, choose "Other" and give its API address.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_ai_keys">';
	wp_nonce_field( 'aw_ai_keys' );
	echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Model</th><th>Key</th><th>Use</th><th></th></tr></thead><tbody data-aw-rows>';
	foreach ( $rows as $i => $r ) {
		echo aw_ai_row_html( $r, $i ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	echo '<template data-aw-blank>' . aw_ai_row_html( $blank, '__i__' ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><button type="button" class="button" data-aw-add>Add a model</button></p>';
	submit_button( 'Save' );
	echo '</form></div>';
	?>
	<script>
	(function () {
		var body = document.querySelector('[data-aw-rows]');
		function renumber() { // Keep each "Use" checkbox tied to its row's position.
			body.querySelectorAll('[data-aw-row]').forEach(function (tr, i) {
				tr.querySelector('input[type=checkbox]').name = 'aw_ai[on][' + i + ']';
			});
		}
		document.querySelector('[data-aw-add]').addEventListener('click', function () {
			var t = document.querySelector('[data-aw-blank]').content.firstElementChild.cloneNode(true);
			body.appendChild(t);
			renumber();
			t.querySelector('select').focus();
		});
		body.addEventListener('click', function (e) {
			var tr = e.target.closest('[data-aw-row]');
			if (!tr) return;
			if (e.target.matches('[data-aw-remove]')) tr.remove();
			if (e.target.matches('[data-aw-up]') && tr.previousElementSibling) body.insertBefore(tr, tr.previousElementSibling);
			if (e.target.matches('[data-aw-down]') && tr.nextElementSibling) body.insertBefore(tr.nextElementSibling, tr);
			renumber();
		});
		body.addEventListener('change', function (e) {
			if (!e.target.matches('[data-aw-provider]')) return;
			var tr = e.target.closest('[data-aw-row]'), o = e.target.selectedOptions[0], other = e.target.value === 'other';
			tr.querySelector('[data-aw-name]').hidden = !other;
			tr.querySelector('input[name="aw_ai[model][]"]').placeholder = o.dataset.model || 'Model name';
			var url = tr.querySelector('[data-aw-url]');
			url.hidden = !other && !o.dataset.url;
			url.placeholder = o.dataset.url || 'API address (…/chat/completions)';
		});
	})();
	</script>
	<?php
}
