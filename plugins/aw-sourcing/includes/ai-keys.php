<?php
/**
 * Sourcing → AI models (owner, 10 + 12 Oct 2026; a tab since 24 Oct 2026): paste a key; the site works out the provider and a model that
 * answers, and shows whether the key works. One row per key, tried top to bottom, each kept separately.
 * Option aw_ai_models = list of {key, provider, model, url, status, checked, label, workers} (not autoloaded).
 * label: the owner's note. workers: the worker IDs (workers-meta.php) the key is for; empty or missing = every worker.
 * The workers read the usable rows (GET /aw/v1/ai-keys; with ?worker=<id>, only the keys for that worker or for all)
 * and try them before their GitHub Actions secrets.
 * Administrators and the ingest user only.
 *
 * @package aw-sourcing
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
	'openrouter' => array( 'OpenRouter', array( 'sk-or-' ), 'https://openrouter.ai/api/v1', array( 'nvidia/nemotron-3-ultra-550b-a55b:free', 'google/gemma-4-31b-it:free', 'nvidia/nemotron-3-super-120b-a12b:free', 'thinkingmachines/inkling:free' ) ), // free models only, never a paid one (owner, 25 Oct 2026); minimax-m3.1-flash:free is gone from OpenRouter; inkling:free replaced laguna-s-2.1:free, which expires 31 Oct 2026
	'requesty'   => array( 'Requesty', array( 'rqst' ), 'https://router.requesty.ai/v1', array( 'google/gemma-4-31b-it', 'nvidia/nemotron-3-ultra-550b-a55b' ) ), // free models; paid ones need a top-up (22 Oct 2026)
	'cline'      => array( 'Cline', array( 'sk_' ), 'https://api.cline.bot/api/v1', array( 'nvidia/nemotron-3-ultra-550b-a55b:free', 'google/gemma-4-31b-it:free' ) ), // free models (22 Oct 2026); minimax-m3.1-flash:free is gone (25 Oct 2026)
	'commandcode' => array( 'Command Code', array( 'user_' ), 'https://api.commandcode.ai/provider/v1', array( 'reflection/beam:free', 'poolside/laguna-s-2.1-free', 'stealth/glyph-cluster:free' ) ), // needs the Provider plan's credit even for free models (22 Oct 2026)
	'nvidia'     => array( 'NVIDIA', array( 'nvapi-' ), 'https://integrate.api.nvidia.com/v1', array( 'nvidia/nemotron-3.5-lightning-30b-a3b', 'nvidia/nemotron-3-ultra-550b-a55b', 'meta/muse-glimmer-30b' ) ), // free developer access; about 20 s a call (22 Oct 2026)
	'vercel'     => array( 'Vercel AI Gateway', array( 'vck_' ), 'https://ai-gateway.vercel.sh/v1', array( 'poolside/laguna-s-2.1-free', 'reflection/beam-free', 'convaiinnovations/laya-free' ) ), // free models, but only once the team has a card on file (22 Oct 2026)
	'huggingface' => array( 'Hugging Face', array( 'hf_' ), 'https://router.huggingface.co/v1', array( 'openai/gpt-oss-120b', 'meta-llama/Llama-3.3-70B-Instruct', 'Qwen/Qwen3-32B' ) ), // small free credit each month (22 Oct 2026)
	'sambanova'  => array( 'SambaNova', array(), 'https://api.sambanova.ai/v1', array( 'Meta-Llama-3.3-70B-Instruct', 'DeepSeek-V3.1', 'gpt-oss-120b' ) ), // UUID keys; needs a payment method on file (22 Oct 2026)
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

/** Groups (owner, 22 Oct 2026): free and daily-quota keys only; paid providers are not used by the workers. */
const AW_AI_TIERS = array(
	'free'  => array( 'Free', 'No cost; only per-minute limits.' ),
	'quota' => array( 'Free daily quota', 'No cost up to a daily allowance per account; the workers spread it over the day.' ),
	'card'  => array( 'Free, needs a card or credit', 'Free models, but the provider asks for a card on file or a small credit first.' ),
	'paid'  => array( 'Paid', 'Costs money per call: not used by the workers.' ),
	'tool'  => array( 'Tool', 'Not an AI model.' ),
);

/** Each provider's group and what it is good for. Every AI worker uses the same list, top down. */
const AW_AI_INFO = array(
	'nvidia'      => array( 'free', 'Background jobs (summary rewrites, focus keywords, research): good models, about 20 s a call' ),
	'groq'        => array( 'free', 'Quick checks (relevance, categories, titles): very fast' ),
	'cerebras'    => array( 'free', 'Quick checks and summaries: very fast' ),
	'requesty'    => array( 'free', 'Summaries and backup: 6 free models' ),
	'cline'       => array( 'free', 'Backup: 2 free models' ),
	'openrouter'  => array( 'quota', 'News summaries: 50 free requests a day per account' ),
	'gemini'      => array( 'quota', 'Summaries and relevance: daily limit per Google project' ),
	'sambanova'   => array( 'card', 'Fast large models once a payment method is on file' ),
	'huggingface' => array( 'quota', 'Backup: a small free credit each month, then nothing until it renews' ),
	'mistral'     => array( 'quota', 'Summaries: free Experiment plan, low per-minute limit' ),
	'vercel'      => array( 'card', 'Backup once a card is on file: 3 free models' ),
	'commandcode' => array( 'card', 'Backup once the Provider plan has credit' ),
	'anthropic'   => array( 'paid', '—' ),
	'openai'      => array( 'paid', '—' ),
	'deepseek'    => array( 'paid', '—' ),
	'xai'         => array( 'paid', '—' ),
	'moonshot'    => array( 'paid', '—' ),
	'minimax'     => array( 'paid', '—' ),
	'together'    => array( 'paid', '—' ),
	'haiku'       => array( 'paid', '—' ),
	'firecrawl'   => array( 'tool', 'Full article text when a news page hides it (Sourcing; 1,000 credits a month)' ),
	'tinyfish'    => array( 'tool', 'Web agent: not used yet' ),
);

/** What each key can do, as icons (owner, 22 Oct 2026: "label like directories do"). dashicon, label. */
const AW_AI_CAP_ICONS = array(
	'text'      => array( 'editor-paragraph', 'Text' ),
	'fast'      => array( 'performance', 'Fast' ),
	'reasoning' => array( 'lightbulb', 'Reasoning' ),
	'code'      => array( 'editor-code', 'Code' ),
	'vision'    => array( 'format-image', 'Images' ),
	'long'      => array( 'media-document', 'Long text' ),
	'slow'      => array( 'clock', 'Slow' ),
	'scrape'    => array( 'admin-site-alt3', 'Reads web pages' ),
	'search'    => array( 'search', 'Web search' ),
	'agent'     => array( 'admin-users', 'Web agent' ),
);
const AW_AI_CAPS = array(
	'nvidia'      => array( 'text', 'reasoning', 'slow' ),
	'groq'        => array( 'text', 'fast', 'code' ),
	'cerebras'    => array( 'text', 'fast', 'code' ),
	'requesty'    => array( 'text' ),
	'cline'       => array( 'text', 'code' ),
	'openrouter'  => array( 'text', 'reasoning', 'long' ),
	'gemini'      => array( 'text', 'vision', 'long' ),
	'mistral'     => array( 'text', 'code' ),
	'huggingface' => array( 'text' ),
	'sambanova'   => array( 'text', 'fast' ),
	'vercel'      => array( 'text' ),
	'commandcode' => array( 'text', 'code' ),
	'firecrawl'   => array( 'scrape', 'search' ),
	'tinyfish'    => array( 'agent' ),
);

/**
 * Free requests a day, per key ('key') or per account ('account'), from the providers' published free limits (about;
 * they change). null: no daily cap, only per-minute limits. 0: nothing until a card or credit is added.
 */
const AW_AI_DAILY = array(
	'openrouter'  => array( 50, 'account' ),
	'groq'        => array( 1000, 'key' ),
	'cerebras'    => array( 14400, 'key' ),
	'gemini'      => array( 250, 'key' ),
	'nvidia'      => array( null, 'key' ),
	'mistral'     => array( null, 'key' ),
	'requesty'    => array( null, 'key' ),
	'cline'       => array( null, 'key' ),
	'huggingface' => array( 0, 'key' ),
	'sambanova'   => array( 0, 'key' ),
	'vercel'      => array( 0, 'key' ),
	'commandcode' => array( 0, 'key' ),
);

function aw_ai_tier( string $provider ): string {
	return AW_AI_INFO[ $provider ][0] ?? ( '' === $provider ? '' : 'paid' );
}

function aw_ai_tier_label( string $provider ): string {
	$t = aw_ai_tier( $provider );
	return '' === $t ? '—' : AW_AI_TIERS[ $t ][0];
}

/** The provider's own site icon (owner, 22 Oct 2026: "use AI icons"). */
const AW_AI_STATUS = array(
	'card'       => 'Needs a card on file (free models)',
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
 * The AI models screen's address.
 *
 * @param array $args Extra query args.
 * @return string
 */
function aw_ai_keys_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=aw-sourcing&tab=ai' ) );
}

// Sourcing → AI models.
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		if ( current_user_can( 'manage_options' ) ) {
			$tabs['ai'] = array( 'AI models' );
		}
		return $tabs;
	}
);

// The old Settings → AI models address (bookmarks, links in notices and e-mails) opens the tab, with its query args.
// The page is no longer registered, so WordPress refuses it in wp-admin/menu.php before admin_init runs; the
// redirect hooks the refusal itself (admin_page_access_denied fires just before the "not allowed" screen).
add_action(
	'admin_page_access_denied',
	function () {
		global $pagenow;
		if ( 'options-general.php' === $pagenow && 'aw-ai-keys' === sanitize_key( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- a redirect only
			$args = array_map( 'sanitize_text_field', (array) wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification
			unset( $args['page'] );
			wp_safe_redirect( aw_ai_keys_url( array_map( 'rawurlencode', $args ) ), 301 );
			exit;
		}
	}
);

// Keys overview (&overview=1): help in the core Help tab.
add_action(
	'load-' . AW_SRC_SCREEN,
	function () {
		if ( 'ai' !== sanitize_key( $_GET['tab'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- which tab is shown
			return;
		}
		if ( ! isset( $_GET['overview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		get_current_screen()->add_help_tab(
			array(
				'id'      => 'aw-ai-overview',
				'title'   => 'Keys overview',
				'content' => '<p>A read-only view of every key saved on the AI models tab and of what each worker gets. Keys show only their last 4 characters. To change a key, use Edit on the AI models tab.</p>'
					. '<p><strong>Attached to</strong>: the workers a key is for. <em>All workers</em> means the key is attached to none, so every worker may use it, also workers added later.</p>'
					. '<p><strong>Usable keys per worker</strong>: what GET aw/v1/ai-keys serves that worker now: AI keys that work or are rate-limited for now, from free or free-tier providers (paid ones are never served), plus tool keys (such as Firecrawl). A key that is not checked yet, refused or out of credit is not counted.</p>',
			)
		);
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

/** The worker IDs a saved key is for; empty = every worker (owner, 24 Oct 2026; keys saved before have none). */
function aw_ai_row_workers( array $row ): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['workers'] ?? array() ) ) ) );
}

/** The key may be used by this worker: it is for every worker, or for this one. */
function aw_ai_key_for_worker( array $row, string $worker ): bool {
	$ws = aw_ai_row_workers( $row );
	return ! $ws || in_array( $worker, $ws, true );
}

/**
 * What GET aw/v1/ai-keys serves: the usable AI rows and the tool keys. Without a worker, every one (as before 24 Oct
 * 2026); with one, only the keys for that worker or for every worker.
 *
 * @param string $worker Worker ID ('' = all).
 * @return array{rows: array, tools: array}
 */
function aw_ai_served( string $worker = '' ): array {
	$out   = array();
	$tools = array(); // Firecrawl and the like: the workers pick them out by provider
	foreach ( aw_ai_rows() as $r ) {
		if ( '' !== $worker && ! aw_ai_key_for_worker( $r, $worker ) ) {
			continue;
		}
		if ( 'tool' === $r['status'] && isset( AW_AI_TOOLS[ $r['provider'] ] ) ) {
			$tools[] = array( 'provider' => $r['provider'], 'key' => $r['key'] );
			continue;
		}
		if ( empty( $r['provider'] ) || 'paid' === aw_ai_tier( $r['provider'] ) || ! in_array( $r['status'], array( 'works', 'limited' ), true ) ) {
			continue;
		}
		$out[] = array( 'provider' => $r['provider'], 'name' => $r['provider'], 'model' => $r['model'], 'url' => $r['url'] ? $r['url'] . '/chat/completions' : '', 'key' => $r['key'] );
	}
	return array( 'rows' => $out, 'tools' => $tools );
}

/** "…abcd (Groq, label)" for notices and the change log: never more of the key than its last 4. */
function aw_ai_key_short( array $row ): string {
	$p = (string) ( $row['provider'] ?? '' );
	$n = $p ? ( AW_AI_PROVIDERS[ $p ][0] ?? AW_AI_TOOLS[ $p ][0] ?? $p ) : 'provider not known yet';
	return '…' . substr( (string) $row['key'], -4 ) . ' (' . $n . ( ! empty( $row['label'] ) ? ', ' . $row['label'] : '' ) . ')';
}

/** "all workers" or the workers' names, for the change log. */
function aw_ai_workers_words( array $ids ): string {
	if ( ! $ids ) {
		return 'all workers';
	}
	return implode( ', ', array_map( fn( $id ) => function_exists( 'aw_worker' ) && aw_worker( $id ) ? aw_worker( $id )['name'] . ' (' . $id . ')' : $id, $ids ) );
}

/** The key's prefix names a provider or tool (a quick check); other keys are tried against every provider (slow). */
function aw_ai_is_uuid( string $key ): bool {
	return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key );
}

function aw_ai_prefix_known( string $key ): bool {
	if ( aw_ai_is_uuid( $key ) ) {
		return true;
	}
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
	$arg = array( 'timeout' => $chat ? 45 : 15 ); // some free models take 20 s or more to answer
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
		$open  = array_keys( array_filter( AW_AI_PROVIDERS, fn( $p, $id ) => ( ! $p[1] || in_array( 'sk-', $p[1], true ) || 'mistral' === $id ) && 'sambanova' !== $id, ARRAY_FILTER_USE_BOTH ) ); // older Mistral keys have no prefix
		$order = array_unique( array_merge( $order, $open ) );
		if ( aw_ai_is_uuid( $key ) ) { // SambaNova keys are plain UUIDs
			array_unshift( $order, 'sambanova' );
			$strong[] = 'sambanova';
		}
		$order = array_values( array_unique( $order ) );
	}
	foreach ( AW_AI_TOOLS as $id => $t ) {
		foreach ( $t[1] as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return array_merge( $row, array( 'provider' => $id, 'model' => '', 'url' => '', 'status' => 'tool', 'checked' => time() ) );
			}
		}
	}
	$row['checked'] = time();
	// Only the provider's listed (free) models are tried, in order; a model saved on the row is not, so a paid one can never come back (owner, 25 Oct 2026).
	foreach ( $order as $id ) {
		list( $code ) = aw_ai_call( $id, $key, '', false );
		if ( in_array( $code, array( 0, 400, 401, 403, 404 ), true ) && ! in_array( $id, $strong, true ) ) {
			continue; // Not this provider's key (or not reachable).
		}
		$row['provider'] = $id;
		$row['url']      = AW_AI_PROVIDERS[ $id ][2];
		$row['status']   = 'no-model';
		$models          = AW_AI_PROVIDERS[ $id ][3];
		$no_credit = false;
		foreach ( array_unique( $models ) as $model ) {
			list( $code, $body, $headers ) = aw_ai_call( $id, $key, $model );
			$row['model'] = $model;
			$row          = array_merge( $row, aw_ai_limits( $headers, $body, $code ) );
			if ( $code >= 200 && $code < 300 ) {
				$row['status']  = 'works';
				$row['last_ok'] = time();
				return $row;
			}
			if ( str_contains( $body, 'credit card' ) || str_contains( $body, 'card on file' ) || str_contains( $body, 'payment_method_required' ) ) {
				$row['status'] = 'card';
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
				$no_credit = true; // This model needs credit; a later (free) model may still work.
				continue;
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
		if ( $no_credit ) {
			$row['status'] = 'no-credit';
		}
		return $row;
	}
	$row['provider'] = '';
	$row['model']    = '';
	$row['status']   = 'unknown';
	return $row;
}

/** Test every saved key (owner, 12 Oct 2026): twice a day by WP-Cron, so the list and the workers only use keys that answer. */
function aw_ai_check_all( bool $new_only = false ): void {
	@set_time_limit( 1200 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$checked = array();
	foreach ( aw_ai_rows() as $r ) {
		if ( ! $new_only || 'unchecked' === $r['status'] || ! empty( $r['recheck'] ) ) { // recheck: "Check again" on the list
			$checked[ $r['key'] ] = aw_ai_check( $r );
			unset( $checked[ $r['key'] ]['recheck'] );
		}
	}
	// The checks take a while: write the results into the list as it is now, so a key removed meanwhile stays removed.
	$rows = aw_ai_rows_fresh();
	foreach ( $rows as &$r ) {
		if ( isset( $checked[ $r['key'] ] ) ) {
			// The check's results; the label and workers as they are now (edited while the check ran).
			$r = array_merge( $checked[ $r['key'] ], array_intersect_key( $r, array_flip( array( 'label', 'workers' ) ) ) );
		}
	}
	unset( $r );
	update_option( 'aw_ai_models', $rows, false );
}
add_action( 'aw_ai_check_all', 'aw_ai_check_all' );
add_action( 'aw_ai_check_all_now', 'aw_ai_check_all' );
add_action( 'aw_ai_check_new', fn() => aw_ai_check_all( true ) );
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
				'permission_callback' => fn() => current_user_can( 'manage_options' ) || current_user_can( 'aw_worker' ),
				// Optional ?worker=<id> (the workflow file without .yml; "sourcing.yml" works too): only that worker's keys.
				'args'                => array(
					'worker' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$worker = preg_replace( '/\.ya?ml$/i', '', (string) $req->get_param( 'worker' ) );
					return new WP_REST_Response( aw_ai_served( sanitize_key( $worker ) ) );
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
					// Free-model daily caps per account (owner, 22 Oct 2026), as the workers saw them at the start of this run.
					$caps = (array) get_option( 'aw_ai_free_caps', array() );
					foreach ( (array) $req->get_param( 'accounts' ) as $a ) {
						$acct = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $a['account'] ?? '' ) ), -4 );
						if ( '' === $acct ) {
							continue;
						}
						$caps[ $acct ] = array(
							'provider'  => sanitize_key( (string) ( $a['provider'] ?? '' ) ),
							'limit'     => max( 0, (int) ( $a['limit'] ?? 0 ) ),
							'remaining' => max( 0, (int) ( $a['remaining'] ?? 0 ) ),
							'share'     => max( 0, (int) ( $a['share'] ?? 0 ) ),
							'used'      => max( 0, (int) ( $a['used'] ?? 0 ) ),
							'keys'      => array_values( array_unique( array_merge( (array) ( $caps[ $acct ]['keys'] ?? array() ), array_map( fn( $k ) => substr( preg_replace( '/[^A-Za-z0-9_=-]/', '', (string) $k ), -4 ), (array) ( $a['keys'] ?? array() ) ) ) ) ),
							'at'        => $now,
							'workflow'  => $wf,
						);
					}
					update_option( 'aw_ai_free_caps', $caps, false );
					return new WP_REST_Response( array( 'keys' => count( (array) $req->get_param( 'keys' ) ) ) );
				},
			)
		);
	}
);

/**
 * Add New Key (owner, 24 Oct 2026): one key or several (spaces, commas or new lines between them), each with the same
 * label and workers (none = every worker). Keys already saved are skipped. New keys are checked in the background,
 * never during the save: one slow provider (NVIDIA, 18 s a call) times ten keys was enough for a 503 (owner, 22 Oct
 * 2026). Keys removed while this save ran stay removed. Used by POST sourceweb/v1/ai/keys/add.
 *
 * @param string     $pasted  The keys as pasted.
 * @param string     $label   Label (80 characters at most).
 * @param array      $workers Worker IDs (known ones, from aw_workers_posted_ids()).
 * @param array|null $rows    The rows to keep, in order (the older one-form list); null keeps the saved rows.
 * @param bool       $recheck Also check every saved key again.
 * @return array { added: string[] (each "…last4 (provider)"), dupes: int, saved: bool }
 */
function aw_ai_keys_add( string $pasted, string $label, array $workers, ?array $rows = null, bool $recheck = false ): array {
	$saved = aw_ai_rows();
	$keep  = null !== $rows;
	$rows  = $keep ? $rows : $saved;
	$have  = wp_list_pluck( $rows, 'key' );
	$label = mb_substr( $label, 0, 80 );
	$added = array();
	$dupes = 0;
	foreach ( preg_split( '/[\s,]+/', $pasted ) as $k ) {
		if ( '' === $k ) {
			continue;
		}
		if ( in_array( $k, $have, true ) ) {
			++$dupes;
			continue;
		}
		$row     = aw_ai_blank_row( $k ) + array(
			'label'   => $label,
			'workers' => $workers,
		);
		$rows[]  = $row;
		$have[]  = $k;
		$added[] = aw_ai_key_short( $row );
	}
	if ( ! $added && ! $keep && ! $recheck ) {
		return array(
			'added' => array(),
			'dupes' => $dupes,
			'saved' => false,
		);
	}
	$now  = wp_list_pluck( aw_ai_rows_fresh(), 'key' );
	$was  = wp_list_pluck( $saved, 'key' );
	$rows = array_values( array_filter( $rows, fn( $r ) => in_array( $r['key'], $now, true ) || ! in_array( $r['key'], $was, true ) ) );
	update_option( 'aw_ai_models', $rows, false );
	if ( $added ) {
		aw_src_log( 'ai', 'added key ' . implode( ', ', $added ) . ' for ' . aw_ai_workers_words( $workers ) );
	}
	$hook = $recheck ? 'aw_ai_check_all_now' : 'aw_ai_check_new'; // every key, or the new ones
	if ( ( 'aw_ai_check_all_now' === $hook || in_array( 'unchecked', wp_list_pluck( $rows, 'status' ), true ) ) && ! wp_next_scheduled( $hook ) ) {
		wp_schedule_single_event( time(), $hook );
		spawn_cron();
	}
	return array(
		'added' => $added,
		'dupes' => $dupes,
		'saved' => true,
	);
}

/**
 * Edit a saved key: its label and the workers it is for (none = every worker). The key itself is never edited. Writes
 * and logs only on a change. Used by POST sourceweb/v1/ai/key/save.
 *
 * @param string $id      Key id (aw_ai_key_id()).
 * @param string $label   Label.
 * @param array  $workers Worker IDs (known ones).
 * @return int|null 1 saved, 0 nothing changed, null no such key.
 */
function aw_ai_key_edit_save( string $id, string $label, array $workers ): ?int {
	$rows  = aw_ai_rows_fresh();
	$label = mb_substr( $label, 0, 80 );
	foreach ( $rows as $i => $r ) {
		if ( aw_ai_key_id( $r['key'] ) !== $id ) {
			continue;
		}
		$was  = aw_ai_row_workers( $r );
		$what = array();
		if ( (string) ( $r['label'] ?? '' ) !== $label ) {
			$what[] = 'label "' . ( $r['label'] ?? '' ) . '" → "' . $label . '"';
		}
		$a = $was;
		$b = $workers;
		sort( $a );
		sort( $b );
		if ( $a !== $b ) {
			$what[] = 'used by ' . aw_ai_workers_words( $was ) . ' → ' . aw_ai_workers_words( $workers );
		}
		if ( ! $what ) {
			return 0;
		}
		$rows[ $i ]['label']   = $label;
		$rows[ $i ]['workers'] = $workers;
		update_option( 'aw_ai_models', $rows, false );
		aw_src_log( 'ai', 'key ' . aw_ai_key_short( $r ) . ': ' . implode( '; ', $what ) );
		return 1;
	}
	return null;
}

/**
 * A list action on saved keys, straight away (owner, 24 Oct 2026): Move up / down (one key), Remove, Check again,
 * Attach to / Detach from a worker. A key keeps at least one worker: detaching its last one is refused. Writes only on
 * a change. Used by POST sourceweb/v1/ai/keys/decide.
 *
 * @param string $op     up, down, remove, recheck, attach or detach.
 * @param array  $ids    Key ids.
 * @param string $worker Worker ID for attach / detach.
 * @return array { op: the action, or 'noworker' when attach / detach has no known worker; n: keys done; skip: keys not detached }
 */
function aw_ai_keys_list_op( string $op, array $ids, string $worker = '' ): array {
	$worker = '' !== $worker && function_exists( 'aw_worker' ) && aw_worker( $worker ) ? $worker : '';
	if ( in_array( $op, array( 'attach', 'detach' ), true ) && '' === $worker ) {
		$op = 'noworker';
	}
	$ids  = array_map( 'sanitize_key', $ids );
	$rows = aw_ai_rows_fresh();
	$orig = $rows;
	$n    = 0;
	$skip = 0;
	$gone = array();
	$did  = array();
	foreach ( $rows as $i => $r ) {
		if ( ! in_array( aw_ai_key_id( $r['key'] ), $ids, true ) ) {
			continue;
		}
		if ( 'attach' === $op || 'detach' === $op ) {
			// Attach: the key is for this worker (a key for every worker becomes this worker's only). Detach: no longer
			// for this worker (a key for every worker becomes every other worker's).
			$ws = aw_ai_row_workers( $r );
			if ( 'attach' === $op ) {
				if ( in_array( $worker, $ws, true ) ) {
					continue;
				}
				$new = array_merge( $ws, array( $worker ) );
			} else {
				if ( $ws && ! in_array( $worker, $ws, true ) ) {
					continue;
				}
				$new = array_values( array_diff( $ws ? $ws : array_keys( aw_workers_all() ), array( $worker ) ) );
				if ( ! $new ) {
					++$skip;
					continue;
				}
			}
			$rows[ $i ]['workers'] = $new;
			$did[]                 = aw_ai_key_short( $r );
		} elseif ( 'remove' === $op ) {
			$gone[] = '…' . substr( $r['key'], -4 ) . ' (' . ( $r['provider'] ? ( AW_AI_PROVIDERS[ $r['provider'] ][0] ?? AW_AI_TOOLS[ $r['provider'] ][0] ?? $r['provider'] ) : 'unknown provider' ) . ')';
			unset( $rows[ $i ] );
		} elseif ( 'recheck' === $op ) {
			$rows[ $i ]['recheck'] = 1; // checked in the background by aw_ai_check_new; the key keeps its status (and stays in use) meanwhile
		} elseif ( ( 'up' === $op && $i > 0 ) || ( 'down' === $op && $i < count( $rows ) - 1 ) ) {
			$j          = 'up' === $op ? $i - 1 : $i + 1;
			$tmp        = $rows[ $j ];
			$rows[ $j ] = $r;
			$rows[ $i ] = $tmp;
			$did[]      = aw_ai_key_short( $r );
			++$n;
			break;
		} else {
			continue;
		}
		++$n;
	}
	$rows = array_values( $rows );
	if ( $rows !== $orig ) {
		update_option( 'aw_ai_models', $rows, false );
	}
	if ( $gone && function_exists( 'aw_src_log' ) ) {
		aw_src_log( 'AI models', 'removed key ' . implode( ', ', $gone ) );
	}
	if ( $did ) {
		$words = array(
			'attach' => 'attached to ' . aw_ai_workers_words( array( $worker ) ),
			'detach' => 'detached from ' . aw_ai_workers_words( array( $worker ) ),
			'up'     => 'moved up',
			'down'   => 'moved down',
		);
		aw_src_log( 'ai', 'key ' . implode( ', ', $did ) . ': ' . ( $words[ $op ] ?? $op ) );
	}
	if ( 'recheck' === $op && $n && ! wp_next_scheduled( 'aw_ai_check_new' ) ) {
		wp_schedule_single_event( time(), 'aw_ai_check_new' );
		spawn_cron();
	}
	if ( in_array( $op, array( 'attach', 'detach' ), true ) ) {
		$n = count( $did );
	}
	return array(
		'op'   => $op,
		'n'    => $n,
		'skip' => $skip,
	);
}

/**
 * Every key in one list (owner, 24 Oct 2026): the saved ones in the order the workers try them, then the ones in the
 * workers' GitHub secrets (from their own reports; changed in GitHub). Never more of a key than its last 4 characters:
 * a key is its id (aw_ai_key_id()). Used by GET sourceweb/v1/ai/keys.
 *
 * @return array[] { pos, id, last4, provider, model, label, workers, status, last_ok, limits, usage, checked, recheck, secret }
 */
function aw_ai_key_records(): array {
	$out   = array();
	$usage = aw_ai_usage();
	$shown = array();
	foreach ( aw_ai_rows() as $i => $r ) {
		$uid     = aw_ai_key_id( $r['key'] );
		$shown[] = $uid;
		$out[]   = array(
			'pos'      => $i,
			'id'       => $uid,
			'last4'    => substr( $r['key'], -4 ),
			'provider' => (string) $r['provider'],
			'model'    => (string) $r['model'],
			'label'    => (string) ( $r['label'] ?? '' ),
			'workers'  => aw_ai_row_workers( $r ),
			'status'   => (string) $r['status'],
			'last_ok'  => (int) ( $r['last_ok'] ?? 0 ),
			'limits'   => ! empty( $r['cooldown'] ) && $r['cooldown'] > time() ? 'Cooldown until ' . wp_date( 'j M, H:i', $r['cooldown'] ) . ( ! empty( $r['limits'] ) ? ' · ' . $r['limits'] : '' ) : ( ! empty( $r['limits'] ) ? $r['limits'] : 'Not reported' ),
			'usage'    => aw_ai_usage_line( (array) ( $usage[ $uid ] ?? array() ) ),
			'checked'  => (int) $r['checked'],
			'recheck'  => ! empty( $r['recheck'] ),
			'secret'   => false,
		);
	}
	// The workers' GitHub secrets: write-only in GitHub, so shown from the workers' reports.
	$other = array_diff_key( $usage, array_flip( $shown ) );
	uasort( $other, fn( $a, $b ) => (int) ( $b['last']['at'] ?? 0 ) <=> (int) ( $a['last']['at'] ?? 0 ) );
	foreach ( $other as $uid => $u ) {
		$prov  = (string) ( $u['provider'] ?? '' );
		$last  = (array) ( $u['last'] ?? array() );
		$out[] = array(
			'pos'      => PHP_INT_MAX,
			'id'       => (string) $uid,
			'last4'    => (string) ( $u['last4'] ?? '' ),
			'provider' => $prov,
			'model'    => implode( ', ', array_keys( (array) ( $last['models'] ?? array() ) ) ),
			'label'    => '',
			'workers'  => array(),
			'status'   => 'paid' === aw_ai_tier( $prov ) ? 'paid' : ( ! empty( $last['ok'] ) ? 'works' : ( ! empty( $last['limited'] ) ? 'limited' : 'failed' ) ),
			'last_ok'  => 0,
			'limits'   => '',
			'usage'    => aw_ai_usage_line( $u ),
			'checked'  => (int) ( $last['at'] ?? 0 ),
			'recheck'  => false,
			'secret'   => true,
		);
	}
	return $out;
}

/**
 * A key's state for the views and badges: ok (works, tool, paid secret), wait (rate-limited, not checked, needs a
 * card) or bad.
 *
 * @param string $status Status.
 */
function aw_ai_key_tone( string $status ): string {
	if ( in_array( $status, array( 'works', 'tool', 'paid' ), true ) ) {
		return 'ok';
	}
	return in_array( $status, array( 'limited', 'unchecked', 'card' ), true ) ? 'wait' : 'bad';
}

/**
 * The free-model accounts the workers reported, as plain text: provider, account (its last characters), daily limit,
 * left today, the last run's share and use, its keys (last 4 each), when.
 */
function aw_ai_free_caps_rows(): array {
	$out = array();
	foreach ( (array) get_option( 'aw_ai_free_caps', array() ) as $acct => $c ) {
		$fresh = gmdate( 'Y-m-d', (int) $c['at'] ) === gmdate( 'Y-m-d' );
		$out[] = array(
			'provider' => (string) ( AW_AI_PROVIDERS[ $c['provider'] ][0] ?? $c['provider'] ),
			'account'  => (string) $acct,
			'limit'    => (int) $c['limit'],
			'left'     => $fresh ? (string) max( 0, $c['remaining'] - $c['used'] ) : 'Full again (reset since the last run)',
			'share'    => (int) $c['share'],
			'used'     => (int) $c['used'],
			'keys'     => implode( ' ', array_map( fn( $k ) => '…' . $k, (array) $c['keys'] ) ),
			'count'    => count( (array) $c['keys'] ),
			'at'       => wp_date( 'j M, H:i', (int) $c['at'] ) . ( $c['workflow'] ? ' · ' . $c['workflow'] : '' ),
		);
	}
	return $out;
}

/**
 * The At a glance numbers (owner, 22 Oct 2026: "keep it simple"): keys saved and working, the free daily supply, the
 * last 24 h's demand, the workers that used AI, and per worker the keys it can use now.
 *
 * @param array $rows Saved rows.
 * @return array { keys, working, daily, nocap, ok, miss, pct, used: worker id => true, workers: [ { id, name, planned, can, all, today } ] }
 */
function aw_ai_overview_data( array $rows ): array {
	$workers = aw_workers_all();
	$working = 0;
	$daily   = 0;
	$nocap   = 0;
	$seen    = array();
	$caps    = (array) get_option( 'aw_ai_free_caps', array() );
	foreach ( $rows as $r ) {
		$p = (string) $r['provider'];
		if ( ! in_array( $r['status'], array( 'works', 'limited' ), true ) || 'paid' === aw_ai_tier( $p ) ) {
			continue;
		}
		++$working;
		$d = AW_AI_DAILY[ $p ] ?? array( null, 'key' );
		if ( null === $d[0] ) {
			++$nocap;
		} elseif ( 'key' === $d[1] ) {
			$daily += $d[0];
		} elseif ( empty( $seen[ $p ] ) ) { // per account: OpenRouter's accounts as the workers reported them
			$seen[ $p ] = true;
			$n          = count( array_filter( $caps, fn( $c ) => ( $c['provider'] ?? '' ) === $p ) );
			$daily     += $d[0] * max( 1, $n );
		}
	}
	$calls = 0;
	$ok    = 0;
	$miss  = 0;
	$wf    = array(); // worker ID (or the reported name of a workflow not in the registry) => true
	foreach ( aw_ai_usage() as $u ) {
		foreach ( (array) ( $u['runs'] ?? array() ) as $run ) {
			if ( time() - (int) $run['at'] < DAY_IN_SECONDS ) {
				$calls += (int) ( $run['calls'] ?? 0 );
				$ok    += (int) ( $run['ok'] ?? 0 );
				$miss  += (int) ( $run['limited'] ?? 0 ) + (int) ( $run['failed'] ?? 0 );
				if ( ! empty( $run['workflow'] ) ) {
					$wf[ aw_ai_usage_worker( (string) $run['workflow'] ) ] = true;
				}
			}
		}
	}
	$all_rows = count( aw_ai_served()['rows'] );
	$list     = array();
	foreach ( $workers as $id => $w ) {
		$list[] = array(
			'id'      => (string) $id,
			'name'    => (string) $w['name'],
			'planned' => ! empty( $w['planned'] ),
			'can'     => count( aw_ai_served( $id )['rows'] ),
			'all'     => $all_rows,
			'today'   => isset( $wf[ $id ] ),
		);
	}
	return array(
		'keys'    => count( $rows ),
		'working' => $working,
		'daily'   => $daily,
		'nocap'   => $nocap,
		'ok'      => $ok,
		'miss'    => $miss,
		'pct'     => $daily ? min( 100, (int) round( 100 * $ok / $daily ) ) : 0,
		'used'    => $wf,
		'workers' => $list,
	);
}

/**
 * The worker ID of a usage report: the workers report their GitHub workflow name (Sourcing workers …), which is the
 * registry's name; a name not in the registry stays as it is.
 */
function aw_ai_usage_worker( string $name ): string {
	foreach ( AW_WORKERS as $file => $w ) {
		if ( $w['name'] === $name ) {
			return aw_worker_id( $file );
		}
	}
	return $name;
}

/**
 * Keys per group and provider (owner, 22 Oct 2026), as plain text.
 *
 * @param array $rows Saved rows.
 * @return array [ { group, means, providers } ]
 */
function aw_ai_tier_groups( array $rows ): array {
	$g = array();
	foreach ( $rows as $r ) {
		$t = aw_ai_tier( (string) $r['provider'] );
		$t = '' === $t ? 'unknown' : $t;
		$n = AW_AI_PROVIDERS[ $r['provider'] ][0] ?? AW_AI_TOOLS[ $r['provider'] ][0] ?? 'Not recognised';
		$g[ $t ][ $n ]['all']   = ( $g[ $t ][ $n ]['all'] ?? 0 ) + 1;
		$g[ $t ][ $n ]['works'] = ( $g[ $t ][ $n ]['works'] ?? 0 ) + ( in_array( $r['status'], array( 'works', 'tool' ), true ) ? 1 : 0 );
	}
	$out = array();
	foreach ( array_merge( array_keys( AW_AI_TIERS ), array( 'unknown' ) ) as $t ) {
		if ( empty( $g[ $t ] ) ) {
			continue;
		}
		$parts = array();
		foreach ( $g[ $t ] as $n => $c ) {
			$parts[] = $n . ' ' . $c['works'] . ' / ' . $c['all'];
		}
		$out[] = array(
			'group'     => (string) ( AW_AI_TIERS[ $t ][0] ?? 'Not recognised' ),
			'means'     => (string) ( AW_AI_TIERS[ $t ][1] ?? 'Tell Claude where the key is from.' ),
			'providers' => implode( ' · ', $parts ),
		);
	}
	return $out;
}
