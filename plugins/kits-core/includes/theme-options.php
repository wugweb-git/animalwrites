<?php
/**
 * Kits Theme Options: the site-wide choices Styles cannot hold (switches, links, text), on Kits → Theme Options and
 * Kits → Settings (admin-menu.php, admin-screens.php).
 *
 * Saved as one option, kits_theme_options, which travels in the Kits settings file (settings-transfer.php). Only values
 * that differ from the default are stored, so an empty option means every part works as it did before. A save changes
 * only the fields it sends (each screen and section sends its own).
 *
 * Every part reads a value through kits_option( $key, $post_id ):
 * the item's Page Options → its post type's default → Theme Options → theme.json settings.custom.kits → the default,
 * then the kits_option filter.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Share networks the bar can show, in today's order. */
function kits_theme_share_networks(): array {
	return array(
		'x'        => 'X',
		'facebook' => 'Facebook',
		'linkedin' => 'LinkedIn',
		'whatsapp' => 'WhatsApp',
		'email'    => 'Email',
	);
}

/**
 * The fields: key => [group, label, kind (switch|choice|text|url|share), choices, default, note]. Menus are WordPress's
 * (Appearance → Editor → Navigation): no menu fields here (owner, 11 Nov 2026: no copies of what WordPress does).
 *
 * @return array<string,array>
 */
function kits_theme_option_fields(): array {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$on_off = array(
		'on'  => 'On',
		'off' => 'Off',
	);
	$fields = array(
		'sticky_bar'          => array( 'Site parts', 'Phone bar', 'switch', $on_off, 'off', 'A bar fixed to the bottom of phone screens with one action. Campaign pages keep their own bar.' ),
		'sticky_href'         => array( 'Site parts', 'Phone bar link', 'url', array(), '', 'Where the bar\'s action goes, for example tel:5550100.' ),
		'sticky_label'        => array( 'Site parts', 'Phone bar text', 'text', array(), '', 'The action\'s words, for example Call us now.' ),
		'announce'            => array( 'Site parts', 'Announcement bar', 'switch', $on_off, 'on', 'The line above the header. Its text and link are edited in the header part (Editor → Patterns → Header).' ),
		'sticky_header'       => array( 'Site parts', 'Header stays at the top', 'switch', $on_off, 'on', 'The header stays in view while the page scrolls. Off: it scrolls away with the page.' ),
		'font_preload'        => array(
			'Site parts',
			'Font preload',
			'choice',
			array(
				'wide' => 'Wide screens only',
				'all'  => 'All screens',
				'off'  => 'Off',
			),
			'wide',
			'Asks for the body font file early so text shows sooner. The file is the first woff2 file of the body font in Styles, else the theme\'s Inter. Campaign pages also preload it on phones under Wide screens only.',
		),
		'share_networks'      => array( 'Share bar', 'Networks', 'share', array(), array(), 'Which buttons the share bar shows on each kind of item, wherever the bar appears today; untick all to hide that bar. Copy link always shows. Campaigns keep their own bar. Profiles for search engines stay in Rank Math → Titles & Meta → Social Meta.' ),
		'css_bundle'          => array( 'Speed', 'Joined stylesheet', 'switch', $on_off, 'on', 'Serves the theme\'s five stylesheets as one cached file, and the component stylesheets a page loads as a second one. Off: every file loads on its own, slower; only for finding a style problem.' ),
		'css_parts_cap'       => array(
			'Speed',
			'Joined files cap',
			'choice',
			array(
				'25'  => '25',
				'50'  => '50',
				'100' => '100',
				'200' => '200',
			),
			'50',
			'Most joined component files kept in uploads/kits-cache/parts/ besides the one per kind of page (front page, page, single item of each type, term, search…), which is always kept. Once it is reached, a page with an unusual mix of parts loads them separately. Appearance → Kits → Home → Joined files shows both counts.',
		),
		'defer_scripts'       => array( 'Speed', 'Deferred scripts', 'switch', $on_off, 'on', 'jQuery, the Fluent Forms submit script, wp-hooks, wp-i18n and the Accessibility Checker fixes load without holding up the page. Off: each holds the page while it loads; forms and the letter tool work either way.' ),
		'jquery_footer'       => array( 'Speed', 'jQuery at the end of the page', 'switch', $on_off, 'on', 'Moves jQuery out of the page head. Off: jQuery loads in the head again, which delays the first paint; forms and the letter tool work either way.' ),
		'no_emoji'            => array( 'Speed', 'No emoji script', 'switch', $on_off, 'on', 'Leaves out WordPress\'s emoji script; browsers draw emoji themselves. Off: the script loads on every page.' ),
		'form_css_lazy'       => array( 'Speed', 'Form styles only where forms are', 'switch', $on_off, 'on', 'On pages whose content has no form, Fluent Forms styles are added after the page loads (the footer sign-up still gets them). Off: they hold up the first paint on every page.' ),
		'inter_dedupe'        => array( 'Speed', 'One copy of Inter', 'switch', $on_off, 'on', 'Leaves out the duplicate Inter files from the Font Library. Off: both copies load.' ),
		'lcp_fix'             => array( 'Speed', 'Largest image preload fix', 'switch', $on_off, 'on', 'Keeps the main image\'s high priority with Optimization Detective. Off: on wide screens it has not measured yet, the main image may lose its high priority and load later.' ),
		'legacy_group_styles' => array( 'Older content', 'Old section classes', 'switch', $on_off, 'on', 'Old content that uses earlier group class names is drawn as Section and Head band styles, and the editor adds the style when such a block is opened. Off: such blocks lose their look until they are saved again.' ),
	);
	$list   = array();
	foreach ( $fields as $key => list( $group, $label, $kind, $choices, $default, $note ) ) {
		$list[ $key ] = compact( 'group', 'label', 'kind', 'choices', 'default', 'note' );
	}
	$out = (array) apply_filters( 'kits_theme_option_fields', $list );
	return $out;
}

/** Saved values (only those that differ from the default). */
function kits_theme_options_saved(): array {
	return (array) get_option( 'kits_theme_options', array() );
}

/** Post types the share bar can be set for: public, with a page of their own. */
function kits_theme_share_types(): array {
	$types = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
		if ( ( ( 'attachment' !== $type->name && $type->publicly_queryable ) || 'page' === $type->name ) && 'aw_campaign' !== $type->name ) { // Campaigns keep their own share bar.
			$types[ $type->name ] = $type->labels->name;
		}
	}
	return $types;
}

/**
 * Today's share bar: every network, wherever a bar shows.
 *
 * @return array<string,string> type => comma list of networks.
 */
function kits_theme_share_default(): array {
	return array_fill_keys( array_keys( kits_theme_share_types() ), implode( ',', array_keys( kits_theme_share_networks() ) ) );
}

/**
 * What a field is when Theme Options leave it alone: theme.json settings.custom.kits, else the field's default.
 *
 * @param string $key Field key.
 * @return mixed
 */
function kits_theme_option_inherited( string $key ) {
	static $json = null;
	if ( null === $json ) {
		$json = function_exists( 'wp_get_global_settings' ) ? (array) wp_get_global_settings( array( 'custom', 'kits' ) ) : array();
	}
	if ( isset( $json[ $key ] ) && is_scalar( $json[ $key ] ) && '' !== (string) $json[ $key ] ) {
		return (string) $json[ $key ];
	}
	$fields = kits_theme_option_fields();
	return isset( $fields[ $key ] ) ? $fields[ $key ]['default'] : '';
}

/**
 * One value, from the most specific place that sets it.
 *
 * @param string   $key     Field key (Theme Options or Page Options).
 * @param int|null $post_id Item, or null for the main item of the request.
 * @return mixed
 */
function kits_option( string $key, $post_id = null ) {
	$value   = '';
	$post_id = $post_id ? (int) ( is_object( $post_id ) ? $post_id->ID : $post_id ) : null;
	if ( function_exists( 'kits_page_option_fields' ) && isset( kits_page_option_fields()[ $key ] ) ) {
		$value = kits_page_option( $key, $post_id ); // The item, then its post type's default.
	}
	if ( '' === $value ) {
		$saved = kits_theme_options_saved();
		if ( isset( $saved[ $key ] ) ) {
			$value = $saved[ $key ];
		}
	}
	if ( '' === $value ) {
		$value = kits_theme_option_inherited( $key );
	}
	return apply_filters( 'kits_option', $value, $key, $post_id );
}

/**
 * Clean a submitted form: known keys, allowed values; values equal to the default are not stored. Fields that are not
 * in the input keep what is stored.
 *
 * @param mixed $input Submitted value.
 */
function kits_theme_options_clean( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = kits_theme_options_saved();
	foreach ( kits_theme_option_fields() as $key => $f ) {
		if ( ! array_key_exists( $key, $input ) && ! ( 'share' === $f['kind'] && isset( $input['share_seen'] ) ) ) {
			continue; // An unticked share form sends only share_seen.
		}
		$raw = $input[ $key ] ?? array();
		switch ( $f['kind'] ) {
			case 'switch':
			case 'choice':
				$v = is_scalar( $raw ) && isset( $f['choices'][ (string) $raw ] ) ? (string) $raw : '';
				break;
			case 'url':
				$v = is_scalar( $raw ) ? esc_url_raw( trim( (string) $raw ), array( 'http', 'https', 'tel', 'mailto' ) ) : '';
				break;
			case 'text':
				$v = is_scalar( $raw ) ? mb_substr( sanitize_text_field( (string) $raw ), 0, 60 ) : '';
				break;
			case 'share':
				$v    = is_array( $out[ $key ] ?? null ) ? $out[ $key ] : array(); // Types not on this form stay.
				$nets = array_keys( kits_theme_share_networks() );
				$def  = kits_theme_share_default();
				foreach ( array_keys( kits_theme_share_types() ) as $type ) {
					if ( ! isset( $raw[ $type ] ) && ! isset( $input['share_seen'][ $type ] ) ) {
						continue;
					}
					$sel = $raw[ $type ] ?? array();
					if ( is_string( $sel ) ) { // Already clean (a second pass, or an imported file): 'x,email' or 'none'.
						$sel = 'none' === $sel ? array() : explode( ',', $sel );
					}
					$picked = array_values( array_intersect( $nets, array_map( 'strval', (array) $sel ) ) );
					$csv    = $picked ? implode( ',', $picked ) : 'none';
					if ( ( $def[ $type ] ?? 'none' ) !== $csv ) {
						$v[ $type ] = $csv;
					} else {
						unset( $v[ $type ] );
					}
				}
				break;
			default:
				$v = '';
		}
		if ( '' !== $v && array() !== $v && kits_theme_option_inherited( $key ) !== $v ) {
			$out[ $key ] = $v;
		} else {
			unset( $out[ $key ] );
		}
	}
	return $out;
}

// Theme Options: Header stays at the top, off.
add_filter( 'body_class', fn( $c ) => 'off' === kits_option( 'sticky_header' ) ? array_merge( $c, array( 'kits-header-static' ) ) : $c );

/**
 * JSON schema of kits_theme_options for the REST settings route: one property per field.
 *
 * @return array
 */
function kits_theme_options_schema(): array {
	$props = array();
	foreach ( kits_theme_option_fields() as $key => $f ) {
		if ( 'share' === $f['kind'] ) {
			$props[ $key ] = array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'string' ),
			);
		} else {
			$props[ $key ] = array( 'type' => 'string' );
		}
	}
	return array(
		'type'                 => 'object',
		'properties'           => $props,
		'additionalProperties' => true, // Keys a site no longer has stay readable; the clean-up drops them on save.
	);
}

add_action(
	'init',
	function () {
		register_setting(
			'kits_options', // Kits → Theme Options and Core Settings save both options through one group.
			'kits_theme_options',
			array(
				'type'              => 'object', // Matches the REST schema; 'array' would need list items.
				'default'           => array(),
				'sanitize_callback' => 'kits_theme_options_clean',
				'show_in_rest'      => array( 'schema' => kits_theme_options_schema() ), // Kits → Theme Options (new screen) saves through /wp/v2/settings.
			)
		);
	},
	20
);

// The settings file carries Theme Options.
add_filter(
	'kits_settings_transfer_options',
	function ( $names ) {
		$names   = (array) $names;
		$names[] = 'kits_theme_options';
		return array_values( array_unique( $names ) );
	}
);
