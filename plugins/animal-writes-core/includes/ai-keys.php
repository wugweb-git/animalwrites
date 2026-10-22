<?php
/**
 * Settings → AI models (owner, 10 + 12 Oct 2026): paste a key; the site works out the provider and a model that
 * answers, and shows whether the key works. One row per key, tried top to bottom, each kept separately.
 * Option aw_ai_models = list of {key, provider, model, url, status, checked} (not autoloaded).
 * The workers read the usable rows (GET /aw/v1/ai-keys) and try them before their GitHub Actions secrets.
 * Administrators and the ingest user only.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Known providers: label, key prefixes, API base ('' = own client), models to try in order.
 * A key whose prefix matches is tried against those providers first, then every other one.
 */
const AW_AI_PROVIDERS = array(
	'anthropic'  => array( 'Anthropic (Claude)', array( 'sk-ant-' ), '', array( 'claude-haiku-4-5', 'claude-sonnet-4-5' ) ),
	'openrouter' => array( 'OpenRouter', array( 'sk-or-' ), 'https://openrouter.ai/api/v1', array( 'minimax/minimax-m3.1-flash:free', 'openai/gpt-4o-mini', 'google/gemini-2.5-flash' ) ), // the free model first (owner, 22 Oct 2026)
	'requesty'   => array( 'Requesty', array( 'rqst' ), 'https://router.requesty.ai/v1', array( 'google/gemma-4-31b-it', 'nvidia/nemotron-3-ultra-550b-a55b' ) ), // free models; paid ones need a top-up (22 Oct 2026)
	'cline'      => array( 'Cline', array( 'sk_' ), 'https://api.cline.bot/api/v1', array( 'minimax/minimax-m3.1-flash:free', 'nvidia/nemotron-3-ultra-550b-a55b:free' ) ), // free models (22 Oct 2026)
	'commandcode' => array( 'Command Code', array( 'user_' ), 'https://api.commandcode.ai/provider/v1', array( 'reflection/beam:free', 'poolside/laguna-s-2.1-free', 'stealth/glyph-cluster:free' ) ), // needs the Provider plan's credit even for free models (22 Oct 2026)
	'groq'       => array( 'Groq', array( 'gsk_' ), 'https://api.groq.com/openai/v1', array( 'openai/gpt-oss-120b', 'llama-3.3-70b-versatile' ) ),
	'xai'        => array( 'xAI', array( 'xai-' ), 'https://api.x.ai/v1', array( 'grok-3-mini', 'grok-4-fast' ) ),
	'gemini'     => array( 'Google Gemini', array( 'AIza', 'AQ.' ), '', array( 'gemini-flash-latest', 'gemini-3-flash-preview', 'gemini-2.5-flash' ) ),
	'openai'     => array( 'OpenAI', array( 'sk-proj-', 'sk-svcacct-', 'sk-' ), 'https://api.openai.com/v1', array( 'gpt-4o-mini', 'gpt-4.1-mini' ) ),
	'deepseek'   => array( 'DeepSeek', array( 'sk-' ), 'https://api.deepseek.com', array( 'deepseek-chat' ) ),
	'moonshot'   => array( 'Moonshot', array( 'sk-' ), 'https://api.moonshot.ai/v1', array( 'moonshot-v1-8k', 'kimi-k2-turbo-preview' ) ),
	'minimax'    => array( 'MiniMax', array( 'sk-api-', 'eyJ' ), 'https://api.minimax.io/v1', array( 'MiniMax-M2' ) ),
	'mistral'    => array( 'Mistral', array( 'mstr' ), 'https://api.mistral.ai/v1', array( 'mistral-small-latest' ) ),
	'together'   => array( 'Together', array( 'tgp_' ), 'https://api.together.xyz/v1', array( 'meta-llama/Llama-3.3-70B-Instruct-Turbo' ) ),
	'cerebras'   => array( 'Cerebras', array( 'csk-' ), 'https://api.cerebras.ai/v1', array( 'gpt-oss-120b', 'llama-3.3-70b' ) ),
);

/** Keys for tools the workers use that are not AI models (owner, 22 Oct 2026): recognised by prefix, never tested as a model. */
const AW_AI_TOOLS = array(
	'firecrawl' => array( 'Firecrawl (article text)', array( 'fc-' ) ),
	'tinyfish'  => array( 'TinyFish (web agent)', array( 'sk-tinyfish-' ) ),
);

const AW_AI_STATUS = array(
	'works'      => 'Works',
	'tool'       => 'Tool key, not an AI model',
	'no-credit'  => 'No credit',
	'limited'    => 'Rate-limited for now',
	'invalid'    => 'Key refused',
	'unknown'    => 'Provider not recognised',
	'unchecked'  => 'Not checked yet',
	'no-model'   => 'No model answered',
	'workspace'  => 'Key not tied to a workspace: create the key inside a workspace',
);

/**
 * A key's status as a coloured icon and label (owner, 15 Oct 2026): green = works, amber = wait or check again,
 * red = broken (safe to remove). Colours are WordPress admin's own success, warning and error colours.
 */
function aw_ai_status_badge( $status ) {
	$tone = in_array( $status, array( 'works', 'tool' ), true ) ? 'ok' : ( in_array( $status, array( 'limited', 'unchecked' ), true ) ? 'wait' : 'bad' );
	$icon = array( 'ok' => 'yes-alt', 'wait' => 'clock', 'bad' => 'dismiss' )[ $tone ];
	return '<span class="aw-ai-status aw-ai-status--' . $tone . '"><span class="dashicons dashicons-' . $icon . '" aria-hidden="true"></span>'
		. esc_html( AW_AI_STATUS[ $status ] ?? $status ) . '</span>';
}

add_action(
	'admin_head',
	function () {
		if ( 'settings_page_aw-ai-keys' !== ( get_current_screen()->id ?? '' ) ) {
			return;
		}
		echo '<style>.aw-ai-status{display:inline-flex;align-items:center;gap:4px;padding:2px 8px 2px 4px;border-radius:999px;font-weight:600}'
			. '.aw-ai-status .dashicons{font-size:18px;width:18px;height:18px}'
			. '.aw-ai-status--ok{color:#007017;background:#edfaef}.aw-ai-status--wait{color:#996800;background:#fcf9e8}.aw-ai-status--bad{color:#b32d2e;background:#fcf0f1}</style>';
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( 'AI models', 'AI models', 'manage_options', 'aw-ai-keys', 'aw_ai_keys_screen' );
	}
);

/** Saved rows. First read converts the older per-provider keys (aw_ai_keys) into unchecked rows. */
function aw_ai_rows(): array {
	$rows = get_option( 'aw_ai_models', null );
	if ( is_array( $rows ) ) {
		$rows = array_values( array_filter( $rows, fn( $r ) => is_array( $r ) && ! empty( $r['key'] ) ) );
		return array_map( fn( $r ) => isset( $r['status'] ) ? $r : aw_ai_blank_row( $r['key'] ), $rows );
	}
	$rows = array();
	foreach ( (array) get_option( 'aw_ai_keys', array() ) as $v ) {
		foreach ( (array) ( is_array( $v ) ? $v : preg_split( '/[\s,]+/', (string) $v ) ) as $k ) {
			if ( '' !== trim( (string) $k ) ) {
				$rows[] = aw_ai_blank_row( trim( (string) $k ) );
			}
		}
	}
	update_option( 'aw_ai_models', $rows, false );
	return $rows;
}

/** The saved rows as they are in the database now (skips this request's cached copy: a check or save may have run meanwhile). */
function aw_ai_rows_fresh(): array {
	wp_cache_delete( 'aw_ai_models', 'options' );
	return aw_ai_rows();
}

/** A short id for a key, used by the Remove button so the key itself never goes into the page. */
function aw_ai_key_id( string $key ): string {
	return substr( hash( 'sha256', $key ), 0, 16 );
}

function aw_ai_blank_row( string $key ): array {
	return array( 'key' => $key, 'provider' => '', 'model' => '', 'url' => '', 'status' => 'unchecked', 'checked' => 0 );
}

/** The key's prefix names a provider or tool (a quick check); other keys are tried against every provider (slow). */
function aw_ai_prefix_known( string $key ): bool {
	foreach ( array_merge( array_values( AW_AI_PROVIDERS ), array_values( AW_AI_TOOLS ) ) as $p ) {
		foreach ( $p[1] as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
	}
	return false;
}

/** HTTP status of a short test request ($chat = false: list models only). */
function aw_ai_call( string $provider, string $key, string $model = '', bool $chat = true ): array {
	$p   = AW_AI_PROVIDERS[ $provider ];
	$arg = array( 'timeout' => 12 );
	if ( 'anthropic' === $provider ) {
		$arg['headers'] = array( 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' );
		$url            = 'https://api.anthropic.com/v1/' . ( $chat ? 'messages' : 'models' );
		$body           = array( 'model' => $model, 'max_tokens' => 5, 'messages' => array( array( 'role' => 'user', 'content' => 'Say ok' ) ) );
	} elseif ( 'gemini' === $provider ) {
		$arg['headers'] = array( 'x-goog-api-key' => $key, 'content-type' => 'application/json' );
		$url            = 'https://generativelanguage.googleapis.com/v1beta/models' . ( $chat ? '/' . $model . ':generateContent' : '' );
		$body           = array( 'contents' => array( array( 'parts' => array( array( 'text' => 'Say ok' ) ) ) ) );
	} else {
		$arg['headers'] = array( 'Authorization' => 'Bearer ' . $key, 'content-type' => 'application/json' );
		$url            = $p[2] . ( $chat ? '/chat/completions' : ( 'openrouter' === $provider ? '/key' : '/models' ) ); // OpenRouter's model list is public.
		$body           = array( 'model' => $model, 'max_tokens' => 5, 'messages' => array( array( 'role' => 'user', 'content' => 'Say ok' ) ) );
	}
	$r = $chat ? wp_remote_post( $url, $arg + array( 'body' => wp_json_encode( $body ) ) ) : wp_remote_get( $url, $arg );
	if ( is_wp_error( $r ) ) {
		return array( 0, '', array() );
	}
	$hd      = wp_remote_retrieve_headers( $r );
	$headers = array_change_key_case( is_object( $hd ) && method_exists( $hd, 'getAll' ) ? $hd->getAll() : (array) $hd, CASE_LOWER );
	return array( (int) wp_remote_retrieve_response_code( $r ), strtolower( substr( wp_remote_retrieve_body( $r ), 0, 3000 ) ), $headers );
}

/** Limits a provider reports on a test call (OpenAI-style and Anthropic headers, Retry-After, Gemini's retryDelay). */
function aw_ai_limits( array $h, string $body, int $code ): array {
	$get   = fn( $k ) => isset( $h[ $k ] ) ? ( is_array( $h[ $k ] ) ? reset( $h[ $k ] ) : $h[ $k ] ) : null;
	$parts = array();
	foreach ( array( 'requests', 'tokens' ) as $what ) {
		$left  = $get( "x-ratelimit-remaining-$what" ) ?? $get( "anthropic-ratelimit-$what-remaining" );
		$limit = $get( "x-ratelimit-limit-$what" ) ?? $get( "anthropic-ratelimit-$what-limit" );
		$reset = $get( "x-ratelimit-reset-$what" ) ?? $get( "anthropic-ratelimit-$what-reset" );
		if ( null !== $left && null !== $limit ) {
			$parts[] = ucfirst( $what ) . ': ' . number_format_i18n( (float) $left ) . ' of ' . number_format_i18n( (float) $limit ) . ' left' . ( $reset ? ' (resets ' . ( is_numeric( $reset ) ? $reset . 's' : ( strtotime( $reset ) ? wp_date( 'H:i', strtotime( $reset ) ) : $reset ) ) . ')' : '' );
		}
	}
	$wait = null;
	if ( 429 === $code ) {
		$ra = $get( 'retry-after' );
		if ( is_numeric( $ra ) ) {
			$wait = (int) $ra;
		} elseif ( preg_match( '/"retrydelay":\s*"(\d+)s"/', $body, $m ) ) {
			$wait = (int) $m[1];
		} elseif ( preg_match( '/try again in (?:(\d+)m)?([\d.]+)s/', $body, $m ) ) {
			$wait = (int) $m[1] * 60 + (int) ceil( (float) $m[2] );
		}
	}
	return array( 'limits' => implode( ' · ', $parts ), 'cooldown' => null !== $wait ? time() + $wait : 0 );
}

/** Work out a key's provider (prefix first, then every provider) and the first model that answers. */
function aw_ai_check( array $row ): array {
	$key   = $row['key'];
	$order  = array();
	$strong = array(); // Providers whose own distinctive prefix the key carries: theirs even if the model list refuses.
	if ( ! empty( $row['provider'] ) && isset( AW_AI_PROVIDERS[ $row['provider'] ] ) ) {
		$order[]  = $row['provider']; // Already known: just re-test it.
		$strong[] = $row['provider'];
	} else {
		foreach ( AW_AI_PROVIDERS as $id => $p ) {
			foreach ( $p[1] as $prefix ) {
				if ( str_starts_with( $key, $prefix ) ) {
					$order[] = $id;
					if ( 'sk-' !== $prefix ) {
						$strong[] = $id;
					}
					break;
				}
			}
		}
		// Then only providers whose keys have no prefix of their own (or a plain sk-): a provider with a distinctive
		// prefix (Cline sk_, Requesty rqst ...) cannot own a key without it, and some list their models to anyone,
		// which made unrelated keys look like theirs (22 Oct 2026).
		$open  = array_keys( array_filter( AW_AI_PROVIDERS, fn( $p, $id ) => ! $p[1] || in_array( 'sk-', $p[1], true ) || 'mistral' === $id, ARRAY_FILTER_USE_BOTH ) ); // older Mistral keys have no prefix
		$order = array_unique( array_merge( $order, $open ) );
	}
	foreach ( AW_AI_TOOLS as $id => $t ) {
		foreach ( $t[1] as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return array_merge( $row, array( 'provider' => $id, 'model' => '', 'url' => '', 'status' => 'tool', 'checked' => time() ) );
			}
		}
	}
	$row['checked'] = time();
	$was            = array( $row['provider'] ?? '', $row['model'] ?? '' ); // the model saved on the row is tried first, so a check keeps the owner's choice (22 Oct 2026)
	foreach ( $order as $id ) {
		list( $code ) = aw_ai_call( $id, $key, '', false );
		if ( in_array( $code, array( 0, 400, 401, 403, 404 ), true ) && ! in_array( $id, $strong, true ) ) {
			continue; // Not this provider's key (or not reachable).
		}
		$row['provider'] = $id;
		$row['url']      = AW_AI_PROVIDERS[ $id ][2];
		$row['status']   = 'no-model';
		$models          = AW_AI_PROVIDERS[ $id ][3];
		if ( $id === $was[0] && '' !== $was[1] ) {
			array_unshift( $models, $was[1] );
		}
		foreach ( array_unique( $models ) as $model ) {
			list( $code, $body, $headers ) = aw_ai_call( $id, $key, $model );
			$row['model'] = $model;
			$row          = array_merge( $row, aw_ai_limits( $headers, $body, $code ) );
			if ( $code >= 200 && $code < 300 ) {
				$row['status']  = 'works';
				$row['last_ok'] = time();
				return $row;
			}
			if ( str_contains( $body, 'not scoped to a workspace' ) ) {
				$row['status'] = 'workspace';
				return $row;
			}
			if ( 429 === $code && ! str_contains( $body, 'insufficient_quota' ) ) { // A free tier's daily/minute limit, not an empty account.
				$row['status'] = 'limited';
				return $row;
			}
			if ( 402 === $code || preg_match( '/credit|balance|insufficient|billing|quota exceeded/', $body ) ) {
				$row['status'] = 'no-credit';
				return $row;
			}
			if ( 429 === $code ) {
				$row['status'] = 'limited';
				return $row;
			}
			if ( 401 === $code || 403 === $code ) {
				$row['status'] = 'invalid';
				return $row;
			}
		}
		return $row;
	}
	$row['provider'] = '';
	$row['model']    = '';
	$row['status']   = 'unknown';
	return $row;
}

/** Test every saved key (owner, 12 Oct 2026): twice a day by WP-Cron, so the list and the workers only use keys that answer. */
function aw_ai_check_all(): void {
	@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$checked = array();
	foreach ( aw_ai_rows() as $r ) {
		$checked[ $r['key'] ] = aw_ai_check( $r );
	}
	// The checks take a while: write the results into the list as it is now, so a key removed meanwhile stays removed.
	$rows = aw_ai_rows_fresh();
	foreach ( $rows as &$r ) {
		if ( isset( $checked[ $r['key'] ] ) ) {
			$r = $checked[ $r['key'] ];
		}
	}
	unset( $r );
	update_option( 'aw_ai_models', $rows, false );
}
add_action( 'aw_ai_check_all', 'aw_ai_check_all' );
add_action( 'aw_ai_check_all_now', 'aw_ai_check_all' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_ai_check_all' ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', 'aw_ai_check_all' );
		}
	}
);

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
					$out   = array();
					$tools = array(); // Firecrawl and the like: the workers pick them out by provider
					foreach ( aw_ai_rows() as $r ) {
						if ( 'tool' === $r['status'] && isset( AW_AI_TOOLS[ $r['provider'] ] ) ) {
							$tools[] = array( 'provider' => $r['provider'], 'key' => $r['key'] );
							continue;
						}
						if ( empty( $r['provider'] ) || ! in_array( $r['status'], array( 'works', 'limited' ), true ) ) {
							continue;
						}
						$out[] = array( 'provider' => $r['provider'], 'name' => $r['provider'], 'model' => $r['model'], 'url' => $r['url'] ? $r['url'] . '/chat/completions' : '', 'key' => $r['key'] );
					}
					return new WP_REST_Response( array( 'rows' => $out, 'tools' => $tools ) );
				},
			)
		);
	}
);

/*
 * Work done per key (owner, 20 Oct 2026): each worker run reports, per key, its calls, answers, rate-limited and failed
 * calls (POST aw/v1/ai-usage, key id = aw_ai_key_id, never the key). Kept: each key's last run and its runs of the
 * last 24 hours. Shown on this screen, keys from the GitHub secrets included.
 */

/** Usage by key id: [ last => run, runs => [ {at, workflow, calls, ok, limited, failed} ... (24 h) ], provider, last4 ]. */
function aw_ai_usage(): array {
	return (array) get_option( 'aw_ai_usage', array() );
}

/** "Sourcing workers, 20 Oct 06:17: 14 calls (12 answered, 2 rate-limited)". */
function aw_ai_usage_line( array $u ): string {
	$last = (array) ( $u['last'] ?? array() );
	if ( ! $last ) {
		return 'No run reported yet';
	}
	$bits = array( (int) $last['ok'] . ' answered' );
	if ( (int) $last['limited'] ) {
		$bits[] = (int) $last['limited'] . ' rate-limited';
	}
	if ( (int) $last['failed'] ) {
		$bits[] = (int) $last['failed'] . ' failed';
	}
	$day = array( 'calls' => 0, 'ok' => 0 );
	foreach ( (array) ( $u['runs'] ?? array() ) as $r ) {
		$day['calls'] += (int) $r['calls'];
		$day['ok']    += (int) $r['ok'];
	}
	return sprintf( '%s, %s%s: %d calls (%s) · 24 h: %d calls, %d answered', $last['workflow'], wp_date( 'j M, H:i', (int) $last['at'] ), empty( $last['dry_run'] ) ? '' : ' (dry run)', (int) $last['calls'], implode( ', ', $bits ), $day['calls'], $day['ok'] );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/ai-usage',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => current_user_can( 'edit_posts' ) && did_action( 'application_password_did_authenticate' ),
				'callback'            => function ( WP_REST_Request $req ) {
					$now   = time();
					$usage = aw_ai_usage();
					$wf    = sanitize_text_field( (string) $req->get_param( 'workflow' ) );
					$link  = esc_url_raw( (string) $req->get_param( 'link' ) );
					$dry   = (bool) $req->get_param( 'dry_run' );
					foreach ( (array) $req->get_param( 'keys' ) as $k ) {
						$id = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) ( $k['id'] ?? '' ) ) );
						if ( 16 !== strlen( $id ) ) {
							continue;
						}
						$n = array( 'calls' => 0, 'ok' => 0, 'limited' => 0, 'failed' => 0 );
						foreach ( $n as $f => $_ ) {
							$n[ $f ] = max( 0, (int) ( $k[ $f ] ?? 0 ) );
						}
						$models = array();
						foreach ( (array) ( $k['models'] ?? array() ) as $m => $c ) {
							$models[ sanitize_text_field( (string) $m ) ] = max( 0, (int) ( $c['calls'] ?? 0 ) );
						}
						$run  = array( 'at' => $now, 'workflow' => $wf, 'link' => $link, 'dry_run' => $dry, 'models' => $models ) + $n;
						$u    = (array) ( $usage[ $id ] ?? array() );
						$runs = array_values( array_filter( (array) ( $u['runs'] ?? array() ), fn( $r ) => $now - (int) $r['at'] < DAY_IN_SECONDS ) );
						$runs[] = array( 'at' => $now, 'workflow' => $wf ) + $n;
						$usage[ $id ] = array(
							'provider' => sanitize_key( (string) ( $k['provider'] ?? '' ) ),
							'last4'    => substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $k['last4'] ?? '' ) ), -4 ),
							'last'     => $run,
							'runs'     => array_slice( $runs, -200 ),
						);
					}
					update_option( 'aw_ai_usage', $usage, false );
					return new WP_REST_Response( array( 'keys' => count( (array) $req->get_param( 'keys' ) ) ) );
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
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- several test calls per key.
		$saved = aw_ai_rows();
		$rows  = array();
		foreach ( (array) ( $_POST['aw_ai_order'] ?? array() ) as $i ) { // Kept rows, in the order shown.
			if ( isset( $saved[ (int) $i ] ) ) {
				$rows[] = $saved[ (int) $i ];
			}
		}
		$have = wp_list_pluck( $rows, 'key' );
		foreach ( preg_split( '/[\s,]+/', sanitize_textarea_field( wp_unslash( $_POST['aw_ai_new'] ?? '' ) ) ) as $k ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( '' !== $k && ! in_array( $k, $have, true ) ) {
				$rows[] = aw_ai_blank_row( $k );
				$have[] = $k;
			}
		}
		// Only the keys just added are checked here, and only when their prefix names the provider: re-testing every key
		// that is not working took minutes and the save ended in a 503 (owner, 22 Oct 2026). The rest are checked twice a
		// day (aw_ai_check_all), or all at once with "Check again".
		foreach ( $rows as &$r ) {
			if ( 'unchecked' === $r['status'] && aw_ai_prefix_known( $r['key'] ) ) {
				$r = aw_ai_check( $r );
			}
		}
		unset( $r );
		// Keys removed while this save was checking stay removed.
		$now  = wp_list_pluck( aw_ai_rows_fresh(), 'key' );
		$was  = wp_list_pluck( $saved, 'key' );
		$rows = array_values( array_filter( $rows, fn( $r ) => in_array( $r['key'], $now, true ) || ! in_array( $r['key'], $was, true ) ) );
		update_option( 'aw_ai_models', $rows, false );
		if ( ! empty( $_POST['aw_ai_recheck'] ) && ! wp_next_scheduled( 'aw_ai_check_all_now' ) ) { // every key: in the background
			wp_schedule_single_event( time(), 'aw_ai_check_all_now' );
			spawn_cron();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-ai-keys&saved=1' ) );
		exit;
	}
);

// Remove one key straight away (owner, 15 Oct 2026): no Save, no re-checking of the other keys.
add_action(
	'admin_post_aw_ai_key_remove',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aw_ai_key_remove' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$id      = sanitize_key( wp_unslash( $_POST['aw_ai_remove'] ?? '' ) );
		$rows    = aw_ai_rows_fresh();
		$removed = '';
		foreach ( $rows as $i => $r ) {
			if ( '' !== $id && aw_ai_key_id( $r['key'] ) === $id ) {
				$removed = substr( $r['key'], -4 );
				$label   = $r['provider'] ? ( AW_AI_PROVIDERS[ $r['provider'] ][0] ?? AW_AI_TOOLS[ $r['provider'] ][0] ?? $r['provider'] ) : 'unknown provider';
				unset( $rows[ $i ] );
				update_option( 'aw_ai_models', array_values( $rows ), false );
				if ( function_exists( 'kits_sourcing_log' ) ) {
					kits_sourcing_log( 'AI models', 'removed key …' . $removed . ' (' . $label . ')' );
				}
				break;
			}
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=aw-ai-keys&' . ( $removed ? 'removed=' . rawurlencode( $removed ) : 'notfound=1' ) ) );
		exit;
	}
);

function aw_ai_keys_screen() {
	$rows = aw_ai_rows();
	echo '<div class="wrap"><h1>AI models</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Saved and checked.</p></div>';
	}
	if ( isset( $_GET['removed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Key …' . esc_html( substr( sanitize_text_field( wp_unslash( $_GET['removed'] ) ), 0, 4 ) ) . ' removed. The news workers stop using it from their next run. It is noted in Settings → Sourcing → Change log.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( isset( $_GET['notfound'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-warning"><p>That key was already gone; nothing changed.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_ai_keys">';
	wp_nonce_field( 'aw_ai_keys' );
	echo '<p><label for="aw-ai-new"><strong>Add keys</strong></label><br>Paste one or more keys, one per line. The provider and model are worked out when you save.</p>';
	echo '<p><textarea id="aw-ai-new" name="aw_ai_new" rows="3" class="large-text code" autocomplete="off" spellcheck="false"></textarea></p>';
	echo '<p>The news workers try the working keys from the top down, then the keys in their GitHub secrets. Every key is tested again twice a day.</p>';
	echo '<table class="widefat striped"><thead><tr><th>Key</th><th>Provider</th><th>Model</th><th>Status</th><th>Last worked</th><th>Limit / cooldown</th><th>Work in the last run</th><th>Checked</th><th></th></tr></thead><tbody data-aw-rows>';
	if ( ! $rows ) {
		echo '<tr><td colspan="9">No keys yet.</td></tr>';
	}
	$usage = aw_ai_usage();
	$shown = array();
	foreach ( $rows as $i => $r ) {
		$uid     = aw_ai_key_id( $r['key'] );
		$shown[] = $uid;
		$label = $r['provider'] ? AW_AI_PROVIDERS[ $r['provider'] ][0] ?? AW_AI_TOOLS[ $r['provider'] ][0] ?? $r['provider'] : '—';
		echo '<tr data-aw-row><td><input type="hidden" name="aw_ai_order[]" value="' . esc_attr( (string) $i ) . '"><code>…' . esc_html( substr( $r['key'], -4 ) ) . '</code></td>'
			. '<td>' . esc_html( $label ) . '</td><td><code>' . esc_html( $r['model'] ? $r['model'] : '—' ) . '</code></td>'
			. '<td>' . aw_ai_status_badge( $r['status'] ) . '</td>'
			. '<td>' . esc_html( ! empty( $r['last_ok'] ) ? wp_date( 'j M, H:i', $r['last_ok'] ) : 'Never' ) . '</td>'
			. '<td>' . esc_html( ! empty( $r['cooldown'] ) && $r['cooldown'] > time() ? 'Cooldown until ' . wp_date( 'j M, H:i', $r['cooldown'] ) . ( $r['limits'] ? ' · ' . $r['limits'] : '' ) : ( ! empty( $r['limits'] ) ? $r['limits'] : 'Not reported' ) ) . '</td>'
			. '<td>' . esc_html( aw_ai_usage_line( (array) ( $usage[ $uid ] ?? array() ) ) ) . '</td>'
			. '<td>' . esc_html( $r['checked'] ? wp_date( 'j M, H:i', $r['checked'] ) : '—' ) . '</td>'
			. '<td><button type="button" class="button" data-aw-up aria-label="Move up">↑</button> <button type="button" class="button" data-aw-down aria-label="Move down">↓</button> <button type="submit" form="aw-ai-remove" name="aw_ai_remove" value="' . esc_attr( aw_ai_key_id( $r['key'] ) ) . '" class="button-link button-link-delete" data-aw-remove="…' . esc_attr( substr( $r['key'], -4 ) ) . '">Remove</button></td></tr>';
	}
	echo '</tbody></table>';
	// Keys the workers also use from their GitHub secrets (not saved here).
	$other = array_diff_key( $usage, array_flip( $shown ) );
	if ( $other ) {
		echo '<h2>Keys in the workers\' GitHub secrets</h2><table class="widefat striped"><thead><tr><th>Key</th><th>Provider</th><th>Work in the last run</th></tr></thead><tbody>';
		uasort( $other, fn( $a, $b ) => (int) ( $b['last']['at'] ?? 0 ) <=> (int) ( $a['last']['at'] ?? 0 ) );
		foreach ( $other as $u ) {
			echo '<tr><td><code>…' . esc_html( (string) ( $u['last4'] ?? '' ) ) . '</code></td><td>' . esc_html( AW_AI_PROVIDERS[ $u['provider'] ?? '' ][0] ?? (string) ( $u['provider'] ?? '' ) ) . '</td><td>' . esc_html( aw_ai_usage_line( $u ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p><label><input type="checkbox" name="aw_ai_recheck" value="1"> Check every key again (runs in the background; reload in a few minutes)</label></p>';
	submit_button( 'Save and check' );
	echo '</form>';
	echo '<form id="aw-ai-remove" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_ai_key_remove">';
	wp_nonce_field( 'aw_ai_key_remove' );
	echo '</form></div>';
	?>
	<script>
	document.querySelector('[data-aw-rows]').addEventListener('click', function (e) {
		var tr = e.target.closest('[data-aw-row]'), body = this;
		if (!tr) return;
		if (e.target.matches('[data-aw-remove]') && !confirm('Remove key ' + e.target.getAttribute('data-aw-remove') + '? This takes effect straight away.')) e.preventDefault();
		if (e.target.matches('[data-aw-up]') && tr.previousElementSibling) body.insertBefore(tr, tr.previousElementSibling);
		if (e.target.matches('[data-aw-down]') && tr.nextElementSibling) body.insertBefore(tr.nextElementSibling, tr);
	});
	</script>
	<?php
}
