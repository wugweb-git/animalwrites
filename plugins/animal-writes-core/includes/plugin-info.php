<?php
/**
 * Animal Writes Core on the Plugins screen: its icon, banner and a "View details" window (description and changelog
 * from its readme.txt), through WordPress's own hooks for a plugin with an Update URI: update_plugins_wugweb.com
 * (WordPress 5.8+) and plugins_api. It reports the installed version, so WordPress never offers an update from here; nothing is
 * downloaded and no other site is asked.
 *
 * The art is in assets/: icon.svg, icon-128x128.png, icon-256x256.png, banner-772x250.png and banner-1544x500.png,
 * from the Animal Writes mark (quill and paw) in black, white and greys; menu-icon.svg is the mark in one colour. The
 * plugin has no menu of its own (its screens are in Kits), so the menu mark is not used by WordPress here.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'update_plugins_wugweb.com', 'aw_core_plugin_update_row', 10, 3 );
add_filter( 'plugins_api', 'aw_core_plugin_details', 10, 3 );

// Kits screens that show a product's icon by its name (kits_product_icon()) find Animal Writes Core's here.
add_filter(
	'kits_product_folders',
	function ( $folders ) {
		$folders                       = (array) $folders;
		$folders['Animal Writes Core'] = array( 'AW_CORE_DIR', 'animal-writes-core' );
		return $folders;
	}
);

/**
 * Animal Writes Core's icon and banner addresses.
 *
 * @return array{icons: array<string, string>, banners: array<string, string>}
 */
function aw_core_plugin_art(): array {
	$base = plugins_url( 'assets/', AW_CORE_DIR . 'animal-writes-core.php' );
	return array(
		'icons'   => array(
			'1x'  => $base . 'icon-128x128.png',
			'2x'  => $base . 'icon-256x256.png',
			'svg' => $base . 'icon.svg',
		),
		'banners' => array(
			'low'  => $base . 'banner-772x250.png',
			'high' => $base . 'banner-1544x500.png',
		),
	);
}

/**
 * The row WordPress keeps for Animal Writes Core when it checks for updates: the installed version, so it is "up to
 * date".
 *
 * @param array|false $update      What another filter returned, or false.
 * @param array       $plugin_data The plugin's header.
 * @param string      $plugin_file The plugin's file, relative to the plugins folder.
 * @return array|false
 */
function aw_core_plugin_update_row( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( AW_CORE_DIR . 'animal-writes-core.php' ) !== $plugin_file ) {
		return $update;
	}
	return array_merge(
		array(
			'id'           => (string) ( $plugin_data['UpdateURI'] ?? '' ),
			'slug'         => 'animal-writes-core',
			'version'      => (string) ( $plugin_data['Version'] ?? '' ),
			'url'          => 'https://wugweb.com',
			'package'      => '',
			'requires_php' => (string) ( $plugin_data['RequiresPHP'] ?? '' ),
		),
		aw_core_plugin_art()
	);
}

/**
 * The "View details" window for Animal Writes Core.
 *
 * @param false|object|array $result What another filter returned.
 * @param string             $action The plugins_api action.
 * @param object             $args   Its arguments (slug).
 * @return false|object|array
 */
function aw_core_plugin_details( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || 'animal-writes-core' !== ( $args->slug ?? '' ) ) {
		return $result;
	}
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$head   = get_plugin_data( AW_CORE_DIR . 'animal-writes-core.php', false, false );
	$readme = (string) @file_get_contents( dirname( AW_CORE_DIR . 'animal-writes-core.php' ) . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- the plugin's own readme; none is fine.
	$parts  = array();
	foreach ( preg_split( '/^==\s*(.+?)\s*==\s*$/m', $readme, -1, PREG_SPLIT_DELIM_CAPTURE ) as $i => $chunk ) {
		if ( $i % 2 ) {
			$name = strtolower( trim( $chunk ) );
		} elseif ( isset( $name ) ) {
			$parts[ $name ] = aw_core_plugin_readme_html( $chunk );
		}
	}
	$tested = preg_match( '/^Tested up to:\s*(\S+)/mi', $readme, $m ) ? $m[1] : '';
	$art    = aw_core_plugin_art();
	return (object) array(
		'name'          => 'Animal Writes Core',
		'slug'          => 'animal-writes-core',
		'version'       => (string) $head['Version'],
		'author'        => '<a href="https://wugweb.com">Wugweb</a>',
		'homepage'      => 'https://wugweb.com',
		'requires'      => (string) $head['RequiresWP'],
		'requires_php'  => (string) $head['RequiresPHP'],
		'tested'        => $tested,
		'sections'      => array_filter(
			array(
				'description' => $parts['description'] ?? esc_html( (string) $head['Description'] ),
				'changelog'   => $parts['changelog'] ?? '',
			)
		),
		'banners'       => $art['banners'],
		'icons'         => $art['icons'],
		'download_link' => '',
	);
}

/**
 * A readme.txt section as HTML: "= Heading =", "* item" lists, `code` and paragraphs; everything escaped.
 *
 * @param string $text The section's text.
 */
function aw_core_plugin_readme_html( string $text ): string {
	$html = '';
	$list = false;
	foreach ( preg_split( '/\R/', trim( $text ) ) as $line ) {
		$line = trim( $line );
		$safe = preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( $line ) );
		if ( preg_match( '/^\*\s+(.*)$/', $line ) ) {
			$html .= ( $list ? '' : '<ul>' ) . '<li>' . substr( $safe, 2 ) . '</li>';
			$list  = true;
			continue;
		}
		if ( $list ) {
			$html .= '</ul>';
			$list  = false;
		}
		if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $h ) ) {
			$html .= '<h4>' . esc_html( $h[1] ) . '</h4>';
		} elseif ( '' !== $line ) {
			$html .= '<p>' . $safe . '</p>';
		}
	}
	return $html . ( $list ? '</ul>' : '' );
}
