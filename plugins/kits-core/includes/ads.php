<?php
/**
 * Ad slots. Admins paste network code (e.g. AdSense) per slot in Settings > Ads; also serves /ads.txt.
 *
 * Two kinds of slot:
 * - Fixed positions ('block'): placed with the "Ad slot" block (kits/ad-slot, inserter group "Ads") in templates,
 *   template parts, patterns or pages. The Site Editor shows where each one sits.
 * - Counted positions ('auto'): after paragraph N of an article, after item N of a list. Inserted here; each has its
 *   own slot name and an On box in Settings > Ads.
 * A site adds its own slots with the kits_ad_slots filter. The older slot names (home, incontent, article, archive …)
 * still work: aw_ad('home') prints that code, and their codes are the fallbacks of the newer slots.
 *
 * An empty slot (no code here, nor in its fallback) prints a placeholder that takes no space for visitors; it shows only
 * in preview (html.kits-preview or body.kits-preview, preview.php), so the owner sees every position before the network
 * serves ads. An AdSense unit that reports "unfilled" also takes no space for visitors.
 *
 * Most ads per page (filled ones; placeholders do not count), kits_ads_max():
 * - articles (kits_ads_article_types): 3, e.g. in text 1, in text 2, end;
 * - list pages (archives, the blog, a theme's list Pages via kits_ads_is_list): 3, e.g. list card 1, card 2, above footer;
 * - the homepage: 2, e.g. top and middle;
 * - every other page (profiles, single items, plain pages): 2.
 * None at all where kits_ads_excluded() (forms, 404, search … via kits_ads_skip). Every slot sits in its own full-width
 * row with a margin, never inside a form or next to a button.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every slot: name => [WordPress label, where it sits, fallback slot ('' = none), kind: block | auto | head | legacy].
 *
 * @return array
 */
function kits_ad_slots() {
	return (array) apply_filters(
		'kits_ad_slots',
		array(
			'home-top'            => array( 'Homepage – top', 'Homepage, after the first sections (block)', 'home', 'block' ),
			'home-mid'            => array( 'Homepage – middle', 'Homepage, between the middle sections (block)', '', 'block' ),
			'home-bottom'         => array( 'Homepage – bottom', 'Homepage, before the last section (block)', '', 'block' ),
			'article-incontent-1' => array( 'Article – in text 1', 'Inside an article, after paragraph 3 (articles with 6+ paragraphs)', 'incontent', 'auto' ),
			'article-incontent-2' => array( 'Article – in text 2', 'Inside an article, after paragraph 9 (articles with 14+ paragraphs)', '', 'auto' ),
			'article-end'         => array( 'Article – end', 'End of an article, before its categories and share buttons', 'article', 'auto' ),
			'list-feed-1'         => array( 'List – card 1', 'Lists: a card after item 6', 'archive', 'auto' ),
			'list-feed-2'         => array( 'List – card 2', 'Lists: a card after item 18 (pages with 20+ items)', 'archive', 'auto' ),
			'sidebar'             => array( 'Sidebar', 'A sidebar column, in templates that have one (block)', '', 'block' ),
			'footer-above'        => array( 'Above the footer', 'Every page, above the footer; not on pages without ads (block)', '', 'block' ),
			'head'                => array( 'Head code', 'Site-wide code in <head> (network script, auto ads)', '', 'head' ),
			// Older names: still accepted by aw_ad() and the [aw_ad] shortcode; their codes back the newer slots.
			'home'                => array( 'Homepage (older slot)', 'Used by "Homepage – top" while its own box is empty', '', 'legacy' ),
			'incontent'           => array( 'In article (older slot)', 'Used by "Article – in text 1" while its own box is empty', '', 'legacy' ),
			'article'             => array( 'End of article (older slot)', 'Used by "Article – end" while its own box is empty', '', 'legacy' ),
			'archive'             => array( 'Lists (older slot)', 'Used by "List – card 1" and "List – card 2" while their boxes are empty', '', 'legacy' ),
		)
	);
}

/** Slots an editor can place with the Ad slot block (fixed positions). */
function kits_ad_block_slots() {
	return array_filter( kits_ad_slots(), fn( $s ) => 'block' === ( $s[3] ?? '' ) );
}

/** The label of a slot ('' for an unknown one). */
function kits_ad_label( $slot ) {
	return (string) ( kits_ad_slots()[ $slot ][0] ?? '' );
}

/** Is a slot switched on (Settings > Ads; every slot is on unless switched off)? */
function kits_ad_on( $slot ) {
	return ! in_array( $slot, (array) get_option( 'kits_ads_off', array() ), true );
}

/** The code for a slot: its own box, else its fallback's (followed up to three steps). */
function kits_ad_code( $slot ) {
	$slots = kits_ad_slots();
	for ( $i = 0; $i < 4 && '' !== $slot; $i++ ) {
		$code = trim( (string) get_option( 'aw_ad_' . $slot, '' ) );
		if ( '' !== $code ) {
			return $code;
		}
		$slot = (string) ( $slots[ $slot ][2] ?? '' );
	}
	return '';
}

/**
 * One ad slot. Filled: the labelled network code (counts towards the page's maximum). Empty: a placeholder shown only in
 * preview. Nothing on excluded pages, for switched-off slots, or once the page has its maximum of filled ads.
 */
function aw_ad( $slot ) {
	static $shown = 0;
	$slot = sanitize_key( (string) $slot );
	if ( 'head' === $slot || '' === $slot || kits_ads_excluded( $slot ) || $shown >= kits_ads_max() || ! kits_ad_on( $slot ) ) {
		return '';
	}
	$code  = kits_ad_code( $slot );
	$label = kits_ad_label( $slot );
	$badge = '<p class="kit-ad-label"><span class="kit-badge kit-badge--ad">Ad</span></p>';
	if ( '' === $code ) {
		return '<aside class="kit-ad kit-ad--empty aw-ad-' . esc_attr( $slot ) . '" aria-hidden="true">' . $badge . '<div class="kit-ad-unit aw-ad-unit" data-slot-label="' . esc_attr( $label ? $label : $slot ) . '"></div></aside>';
	}
	++$shown;
	// One line inside a block element, so wpautop in list shortcodes adds no <p>/<br> around the network code.
	$code = (string) preg_replace( '/\s*[\r\n]+\s*/', ' ', $code );
	return '<aside class="kit-ad aw-ad-' . esc_attr( $slot ) . '" aria-label="Advertisement">' . $badge . '<div class="kit-ad-unit aw-ad-unit">' . $code . '</div></aside>';
}

/** No ads on this page at all (also no head code and no auto ads): forms, 404, search, crisis pages… (kits_ads_skip). */
function kits_ads_excluded( $slot = 'head' ) {
	return is_admin() || is_404() || (bool) apply_filters( 'kits_ads_skip', false, $slot );
}

/** Most filled ads one page may carry: articles 3, lists 3, the homepage 2, everything else 2 (see the file header). */
function kits_ads_max() {
	if ( is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ) {
		$max = 3;
	} elseif ( is_front_page() ) {
		$max = 2;
	} else {
		$max = kits_ads_is_list() ? 3 : 2;
	}
	return (int) apply_filters( 'kits_ads_max', $max );
}

/** Is this a list page (archives, the blog, or a theme's own list Pages)? */
function kits_ads_is_list() {
	return (bool) apply_filters( 'kits_ads_is_list', is_archive() || is_home() );
}

/** The counted list positions on this page: item number => slot (kits_ads_list_slots). */
function kits_ads_list_slots() {
	return (array) apply_filters( 'kits_ads_list_slots', array( 6 => 'list-feed-1', 18 => 'list-feed-2' ) );
}

/** The first list ad, once per page (themes with their own list markup call this at the end of the list). */
function kits_list_ad() {
	static $done = false;
	$slots = kits_ads_list_slots();
	if ( $done || is_admin() || ! kits_ads_is_list() || ! $slots ) {
		return '';
	}
	$done = true;
	return aw_ad( (string) reset( $slots ) );
}

add_shortcode( 'aw_ad', fn( $a ) => aw_ad( sanitize_key( $a['slot'] ?? 'home' ) ) );

/* ---------- The "Ad slot" block (inserter group "Ads") ---------- */

add_filter(
	'block_categories_all',
	function ( $cats ) {
		$have = wp_list_pluck( $cats, 'slug' );
		foreach ( array( 'kits-ads' => 'Ads', 'kits-sponsored' => 'Sponsored' ) as $slug => $title ) {
			if ( ! in_array( $slug, $have, true ) ) {
				$cats[] = array( 'slug' => $slug, 'title' => $title, 'icon' => null );
			}
		}
		// Synced patterns (content repeated across pages) are the "System" group.
		foreach ( $cats as &$c ) {
			if ( 'reusable' === $c['slug'] ) {
				$c['title'] = 'System';
			}
		}
		return $cats;
	},
	20
);

add_action(
	'init',
	function () {
		wp_register_script( 'kits-ad-slot', plugins_url( 'assets/js/ad-slot.js', KITS_CORE_DIR . 'kits-core.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ), (string) filemtime( KITS_CORE_DIR . 'assets/js/ad-slot.js' ), true );
		$opts = array();
		foreach ( kits_ad_block_slots() as $name => $s ) {
			$opts[] = array( 'value' => $name, 'label' => $s[0], 'where' => $s[1] );
		}
		wp_add_inline_script( 'kits-ad-slot', 'window.kitsAdSlots = ' . wp_json_encode( $opts ) . ';', 'before' );
		register_block_type(
			'kits/ad-slot',
			array(
				'api_version'           => 3,
				'title'                 => 'Ad slot',
				'category'              => 'kits-ads',
				'description'           => 'A labelled ad position. The code comes from Settings > Ads.',
				'attributes'            => array( 'slot' => array( 'type' => 'string', 'default' => 'home-mid' ) ),
				'render_callback'       => fn( $a ) => isset( kits_ad_block_slots()[ $a['slot'] ?? '' ] ) ? aw_ad( (string) $a['slot'] ) : '',
				'editor_script_handles' => array( 'kits-ad-slot' ),
				'supports'              => array( 'html' => false, 'multiple' => true, 'reusable' => false ),
			)
		);
	}
);

/* ---------- Head code ---------- */

add_action(
	'wp_head',
	function () {
		$code = (string) get_option( 'aw_ad_head', '' );
		if ( '' !== trim( $code ) && ! kits_ads_excluded() ) {
			echo $code . "\n"; // phpcs:ignore -- admin-entered ad network code.
		}
	},
	5
);

/* ---------- Counted positions ---------- */

// Inside an article: after paragraph 3 (6+ paragraphs) and after paragraph 9 (14+ paragraphs).
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ) {
			return $content;
		}
		$done  = true;
		$paras = substr_count( $content, '</p>' );
		$at    = array();
		foreach ( array( 'article-incontent-1' => array( 3, 6 ), 'article-incontent-2' => array( 9, 14 ) ) as $slot => $rule ) {
			if ( $paras >= $rule[1] ) {
				$at[ $rule[0] ] = aw_ad( $slot );
			}
		}
		foreach ( array_reverse( array_filter( $at ), true ) as $n => $ad ) {
			$pos = 0;
			for ( $i = 0; $i < $n; $i++ ) {
				$pos = strpos( $content, '</p>', $pos ) + 4;
			}
			$content = substr( $content, 0, $pos ) . $ad . substr( $content, $pos );
		}
		return $content;
	},
	30
);

// End of an article: before its categories and share buttons (kits_endbar), or after the text where there is no end bar.
add_filter( 'kits_endbar_before', fn( $html ) => $html . ( is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ? aw_ad( 'article-end' ) : '' ) );
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular( (array) apply_filters( 'kits_ads_end_after_content_types', array() ) ) ) {
			return $content;
		}
		$done = true;
		return $content . aw_ad( 'article-end' );
	},
	40
);

// Lists: a card after item 6 and after item 18 (pages with 20+ items); a site sets other positions (kits_ads_list_slots).
add_filter(
	'render_block_core/post-template',
	function ( $content ) {
		if ( is_admin() || ! kits_ads_is_list() ) {
			return $content;
		}
		$slots = kits_ads_list_slots();
		$first = $slots ? (int) array_key_first( $slots ) : 0;
		$parts = explode( '</li>', $content );
		$items = count( $parts ) - 1;
		krsort( $slots );
		foreach ( $slots as $n => $slot ) {
			$n = (int) $n;
			if ( $items <= $n || ( $n !== $first && $items < $n + 2 ) ) {
				continue;
			}
			$ad = aw_ad( (string) $slot );
			if ( $ad ) {
				$parts[ $n - 1 ] .= '</li><li class="kit-ad-item' . ( false !== strpos( $ad, 'kit-ad--empty' ) ? ' kit-ad-item--empty' : '' ) . '">' . $ad;
			}
		}
		return implode( '</li>', $parts );
	}
);

/* ---------- /ads.txt ---------- */

add_action(
	'init',
	function () {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore
		if ( '/ads.txt' === $path ) {
			$txt = (string) get_option( 'aw_ads_txt', '' );
			if ( '' !== trim( $txt ) ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo $txt; // phpcs:ignore
				exit;
			}
		}
	}
);

/* ---------- Settings > Ads (administrators only) ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Ads',
			'Ads',
			'manage_options',
			'aw-ads',
			function () {
				$slots = kits_ad_slots();
				if ( isset( $_POST['aw_ads_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_ads_nonce'] ) ), 'aw_ads' ) && current_user_can( 'unfiltered_html' ) ) {
					$off = array();
					foreach ( $slots as $s => $def ) {
						update_option( 'aw_ad_' . $s, wp_unslash( $_POST[ 'aw_ad_' . $s ] ?? '' ) ); // phpcs:ignore -- raw ad code by admins.
						if ( 'auto' === $def[3] && empty( $_POST[ 'kits_ad_on_' . $s ] ) ) {
							$off[] = $s;
						}
					}
					update_option( 'kits_ads_off', $off, false );
					update_option( 'aw_ads_txt', sanitize_textarea_field( wp_unslash( $_POST['aw_ads_txt'] ?? '' ) ) );
					do_action( 'kits_ads_settings_save' );
					echo '<div class="updated"><p>Saved.</p></div>';
				}
				echo '<div class="wrap"><h1>Ads</h1><p>Paste the code from your ad network. Every ad is labelled "Ad". An empty slot shows only in preview (administrators, preview IP addresses and preview devices); visitors see nothing there.</p><form method="post">';
				wp_nonce_field( 'aw_ads', 'aw_ads_nonce' );
				$groups = array(
					'block'  => 'Fixed positions (placed with the "Ad slot" block)',
					'auto'   => 'Counted positions (placed automatically)',
					'head'   => 'Head code',
					'legacy' => 'Older slots (fallback codes)',
				);
				foreach ( $groups as $kind => $title ) {
					echo '<h2 class="title" style="margin-top:2em">' . esc_html( $title ) . '</h2>';
					foreach ( $slots as $s => $def ) {
						if ( $kind !== $def[3] ) {
							continue;
						}
						$fb = $def[2] ? ' Empty: uses "' . kits_ad_label( $def[2] ) . '".' : '';
						echo '<h3>' . esc_html( $def[0] ) . ' <code>' . esc_html( $s ) . '</code></h3><p class="description">' . esc_html( $def[1] . '.' . $fb ) . '</p>';
						if ( 'auto' === $kind ) {
							echo '<p><label><input type="checkbox" name="kits_ad_on_' . esc_attr( $s ) . '" value="1"' . checked( kits_ad_on( $s ), true, false ) . '> On</label></p>';
						}
						echo '<textarea name="aw_ad_' . esc_attr( $s ) . '" rows="4" class="large-text code">' . esc_textarea( (string) get_option( 'aw_ad_' . $s, '' ) ) . '</textarea>';
					}
				}
				do_action( 'kits_ads_settings_fields' );
				echo '<h2 class="title" style="margin-top:2em">ads.txt</h2><p>Served at <code>/ads.txt</code>.</p><textarea name="aw_ads_txt" rows="4" class="large-text code">' . esc_textarea( (string) get_option( 'aw_ads_txt', '' ) ) . '</textarea>';
				submit_button();
				echo '</form>';
				do_action( 'kits_ads_settings_after' );
				echo '</div>';
			}
		);
	}
);

/**
 * Page speed: AdSense (Site Kit) starts after the page has loaded, not during it, so its script and ad frames no longer
 * hold back the page. The account stays verified through Google's meta tag in the head. Auto ads work as before.
 */
function kits_adsense_client() {
	$s = (array) get_option( 'googlesitekit_adsense_settings', array() );
	return ! empty( $s['useSnippet'] ) && preg_match( '/^ca-pub-\d+$/', (string) ( $s['clientID'] ?? '' ) ) ? (string) $s['clientID'] : '';
}
add_filter( 'googlesitekit_adsense_tag_blocked', fn( $b ) => is_admin() || '' === kits_adsense_client() ? $b : true ); // our loader below replaces it
add_action(
	'wp_head',
	function () {
		$c = kits_adsense_client();
		if ( '' === $c || kits_ads_excluded() ) {
			return;
		}
		$src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode( $c ) . '&host=ca-host-pub-2644536267352236';
		echo '<meta name="google-adsense-account" content="' . esc_attr( $c ) . '">' . "\n";
		echo '<script>(function(){function go(){var s=document.createElement("script");s.async=true;s.crossOrigin="anonymous";s.src=' . wp_json_encode( $src ) . ';document.head.appendChild(s);}function later(){("requestIdleCallback" in window)?requestIdleCallback(go,{timeout:3000}):setTimeout(go,1500);}if(document.readyState==="complete"){later();}else{window.addEventListener("load",later,{once:true});}})();</script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
	},
	5
);
