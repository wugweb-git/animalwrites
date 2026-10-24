<?php
/**
 * Settings → Sourcing → AI models: each provider's own site icon (owner, 24 Oct 2026).
 *
 * Shown with the site's favicon helper (aw_favicon_url() in Kits Core, attribution.php) when the provider's host is a
 * known publisher; otherwise from option aw_ai_provider_icons (host => attachment ID, 0 = tried and none found), which
 * fills the same way Kits Core fills the publishers' icons: on its hourly aw_fetch_favicons event, a few hosts a run,
 * Google's favicon service then the host's /favicon.ico, saved once into the media library. Until then, and when
 * none is found, a core dashicon. Nothing is fetched while a page is drawn.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The site of each provider and tool in AW_AI_PROVIDERS and AW_AI_TOOLS. */
const AW_AI_HOSTS = array(
	'anthropic'   => 'anthropic.com',
	'openrouter'  => 'openrouter.ai',
	'requesty'    => 'requesty.ai',
	'cline'       => 'cline.bot',
	'commandcode' => 'commandcode.ai',
	'nvidia'      => 'build.nvidia.com',
	'vercel'      => 'vercel.com',
	'huggingface' => 'huggingface.co',
	'sambanova'   => 'sambanova.ai',
	'groq'        => 'groq.com',
	'xai'         => 'x.ai',
	'gemini'      => 'ai.google.dev',
	'openai'      => 'openai.com',
	'deepseek'    => 'deepseek.com',
	'moonshot'    => 'moonshot.ai',
	'minimax'     => 'minimax.io',
	'mistral'     => 'mistral.ai',
	'together'    => 'together.ai',
	'cerebras'    => 'cerebras.ai',
	'firecrawl'   => 'firecrawl.dev',
	'tinyfish'    => 'tinyfish.ai',
);

/** The provider's icon (16 px) as HTML: its site icon, or a core dashicon. */
function aw_ai_provider_icon( $provider ) {
	$host = AW_AI_HOSTS[ $provider ] ?? '';
	if ( '' === $host ) {
		return '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>';
	}
	$url = function_exists( 'aw_favicon_url' ) ? aw_favicon_url( $host ) : '';
	if ( '' === $url ) {
		$id  = (int) ( ( (array) get_option( 'aw_ai_provider_icons', array() ) )[ $host ] ?? 0 );
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
	}
	if ( '' === $url ) {
		return '<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>';
	}
	return '<img src="' . esc_url( $url ) . '" alt="" width="16" height="16" loading="lazy" decoding="async">';
}

/** Icon and name of a provider, as HTML. */
function aw_ai_provider_html( $provider ) {
	$label = $provider ? ( AW_AI_PROVIDERS[ $provider ][0] ?? AW_AI_TOOLS[ $provider ][0] ?? $provider ) : '—';
	return ( $provider ? aw_ai_provider_icon( $provider ) . ' ' : '' ) . esc_html( $label );
}

// Kits Core's hourly favicon run: also fetch the icons of the providers that have a saved key (five a run at most).
add_action(
	'aw_fetch_favicons',
	function () {
		$have = (array) get_option( 'aw_ai_provider_icons', array() );
		$want = array();
		foreach ( (array) get_option( 'aw_ai_models', array() ) as $r ) {
			$host = AW_AI_HOSTS[ $r['provider'] ?? '' ] ?? '';
			if ( '' !== $host && ! array_key_exists( $host, $have ) && ( ! function_exists( 'aw_favicon_url' ) || '' === aw_favicon_url( $host ) ) ) {
				$want[ $host ] = true;
			}
		}
		if ( ! $want ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		foreach ( array_slice( array_keys( $want ), 0, 5 ) as $host ) {
			$att = 0;
			foreach ( array( 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode( $host ), 'https://' . $host . '/favicon.ico' ) as $src ) {
				$tmp = download_url( $src, 15 );
				if ( is_wp_error( $tmp ) ) {
					continue;
				}
				$type = wp_get_image_mime( $tmp );
				if ( ! $type || filesize( $tmp ) < 100 ) {
					wp_delete_file( $tmp );
					continue;
				}
				$ext = array(
					'image/png'                => 'png',
					'image/x-icon'             => 'ico',
					'image/vnd.microsoft.icon' => 'ico',
					'image/jpeg'               => 'jpg',
					'image/gif'                => 'gif',
					'image/webp'               => 'webp',
				)[ $type ] ?? 'png';
				$id  = media_handle_sideload(
					array(
						'name'     => 'favicon-' . sanitize_title( $host ) . '.' . $ext,
						'tmp_name' => $tmp,
					),
					0,
					'Favicon: ' . $host
				);
				if ( ! is_wp_error( $id ) ) {
					update_post_meta( $id, '_aw_favicon_host', $host );
					$att = (int) $id;
					break;
				}
			}
			$have[ $host ] = $att;
		}
		update_option( 'aw_ai_provider_icons', $have, false );
	},
	20
);
