<?php
/**
 * Animal Writes: the site's own styles, scripts, presets and head tags, over Kits by Wugweb. Loaded by the theme's
 * site layer (inc/site-layer.php) before the theme's own hooks, where a child theme's functions.php loads; the
 * templates, parts, patterns and theme.json in this folder are served by the site layer itself.
 *
 * This folder is the site's, not the theme's: the distributed theme leaves it out (tools/build-dist.py). It was
 * Animal Writes Core's theme/ folder (served by its theme-parts files) until 18 Nov 2026.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This site's campaign pages, Home sections and item heads (moved from Animal Writes Core, 19 Nov 2026; function, block,
// shortcode, post-type and option names unchanged). The campaign link engine and the letter statistics are Kits Core's; the campaign stylesheets and scripts are in campaign/.
if ( defined( 'KITS_CORE_LOADED' ) ) { // these files call Kits Core's functions at load: without Kits Core (deactivated, or not yet active) the site loads without them, and does not fatal
	// Site modules moved here from Kits Core (19 Nov 2026), in the order Kits Core loaded them: the short links (/lab,
	// /diwali), press coverage, social posts, the sponsored blocks (dormant) and the Library document viewer.
	foreach ( array( 'campaign-short', 'press', 'social-posts', 'sponsored-blocks', 'library-documents' ) as $kits_site_module ) {
		require_once __DIR__ . '/includes/' . $kits_site_module . '.php';
	}
	unset( $kits_site_module );
	foreach ( array( 'campaigns', 'campaign-tools', 'campaign-record', 'item-head', 'home' ) as $kits_site_file ) {
		require_once __DIR__ . '/includes/' . $kits_site_file . '.php';
	}
	unset( $kits_site_file );
}

/**
 * The site's content layer (19 Nov 2026; it was Animal Writes Core's includes/ until then): the content types and their
 * meta, tags and relations, the hub pages and the animal-writes/* blocks, the SCF field-group folder, bindings, related
 * lists, the Acts and Rules links and the rest, in the order Animal Writes Core loaded them. Only this site uses them,
 * so they live in the site folder, which the distributed theme leaves out (docs/awcore-retirement.md, section 5).
 */
if ( defined( 'KITS_CORE_LOADED' ) ) { // these files call Kits Core's functions at load: without Kits Core (deactivated, or not yet active) the site loads without them, and does not fatal
	foreach ( array( 'systems', 'content-types', 'category-moves', 'tags', 'meta', 'scf', 'summary-display', 'news-article', 'bindings', 'also-reported', 'display', 'sections', 'hubs', 'hub-blocks', 'term-page', 'faq-data', 'model', 'helpers-public', 'pages', 'block-states', 'related', 'law-links', 'near-opps', 'entity-links' ) as $kits_site_content_file ) {
		require_once __DIR__ . '/content/' . $kits_site_content_file . '.php';
	}
	unset( $kits_site_content_file );
}

/**
 * Component stylesheets (css/aw-*.css in this folder): each loads only on the pages that print its component, joined
 * into one file per page by Kits (kits_component_styles, Kits functions.php). handle => [
 *   'file'   => path relative to assets/site/; the .min.css next to it is served when it exists,
 *   'when'   => optional callable, no arguments: a template condition, e.g. 'is_front_page',
 *   'blocks' => optional [ block name => class prefix or list of prefixes ]: stored content no PHP prints, e.g. [ 'core/group' => 'aw-about-' ],
 * ]. With no 'when', the file loads on pages whose PHP called kits_use_component_style( $handle ) while printing.
 * The order here is the order in the joined file. Editors load every file (below). For example:
 *
 *     'aw-home'     => array( 'file' => 'css/aw-home.css', 'when' => 'is_front_page' ),
 *     'aw-type-icons' => array( 'file' => 'css/aw-type-icons.css' ),
 *
 * tools/ci/css_min.py writes and checks the .min.css of every css/aw*.css here.
 *
 * In every joined file (11 Nov 2026): the small files (under about 1 KB gzipped each, 6.9 KB together) and aw-hubs,
 * which most list pages print, are in every page's joined file ('when' => '__return_true'). Keyed by what each page
 * printed, they made 37 joined files in one day; the larger page-type files (aw-home, aw-type-icons, aw-single, aw-lists,
 * aw-directory, aw-listing, aw-forms) still decide which file a page gets. All their rules are scoped to their own
 * component classes, and they were all in aw.css on every page before phase 2. aw.css itself was retired on
 * 18 Nov 2026: its site-wide rules are in the Kits theme's kits.css, the rules only Animal Writes uses are in the
 * theme's site file (assets/site/site.css), which the theme joins first, before these files.
 *
 * One file per kind of page (11 Nov 2026): Kits keys the joined file on the kind of page (front, page, page-{template},
 * single-{type}, term-{taxonomy}, search, 404...), not on the exact list, and the Directory views are their own kind
 * (kits_site_page_kind below), so pages of one kind share one file.
 *
 * @return array<string,array>
 */
function kits_site_component_files(): array {
	return array(
		// Campaign blocks and campaign pages: first, so they print right after aw.css, where their rules were.
		'aw-countdown' => array( // [aw_countdown]: the Home hero, the Supreme Court card, campaign heroes.
			'file' => 'css/aw-countdown.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-campaign-page' => array( // Campaign pages; the steps list wherever it is placed.
			'file' => 'css/aw-campaign-page.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-camp-band-aw' => array( // The campaign band's overrides, where the Home campaign hero or the open band prints.
			'file' => 'css/aw-camp-band-aw.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-sc-card' => array( // The Supreme Court campaign card.
			'file' => 'css/aw-sc-card.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-camp-story' => array( // The Community dogs story band.
			'file' => 'css/aw-camp-story.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-festive' => array( // The festive card.
			'file' => 'css/aw-festive.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		// These four follow, where their rules were in aw.css: aw-meta must come before aw-listing (.aw-claim-meta).
		'aw-meta' => array( // An item's meta row and the claim head; places and News card rows by flag.
			'file' => 'css/aw-meta.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-volunteer' => array( // The volunteer page; the volunteer form and banner wherever they print.
			'file' => 'css/aw-volunteer.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-contact' => array( // The contact page; the Elsewhere list by flag.
			'file' => 'css/aw-contact.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-search' => array( // The search page (?s= redirects to /search/?q=).
			'file' => 'css/aw-search.css',
			'when' => '__return_true', // In every joined file (see above).
		),
		'aw-home' => array( // Home sections: the front page, plus the Home section patterns and blocks wherever they are placed.
			'file'   => 'css/aw-home.css',
			'when'   => 'is_front_page',
			'blocks' => array( 'core/group' => array( 'aw-ed-', 'aw-pair' ) ), // help-now, find-help, animal-law, learn, whats-happening, aw-pair.
		),
		'aw-type-icons' => array( // Type icons (kit-ico--type-{slug}): wherever aw_type_icon() prints one, the Directory map's too.
			'file' => 'css/aw-type-icons.css',
		),
		'aw-single' => array( // Single items, plus a flag from the code that prints More, Sources, share, take-part, find-help and the rest elsewhere.
			'file' => 'css/aw-single.css',
			'when' => fn() => is_singular( array( 'post', 'aw_news', 'aw_legal', 'aw_listing', 'aw_opportunity' ) ),
		),
		'aw-forms' => array( // Fluent Forms skin of the site's own forms (aw_ff_form()) and the form-for line; the newsletter keeps its skin in aw.css.
			'file' => 'css/aw-forms.css',
		),
		'aw-lists' => array( // News, hub and related lists, hub groups, tasks, story and sponsored cards, the verified mark.
			'file'   => 'css/aw-lists.css',
			'when'   => 'is_front_page', // Home's lists are stored blocks
			'blocks' => array(
				'core/query'         => 'aw-hub-list', // hub list patterns and stored pages
				'core/post-template' => array( 'aw-news-list', 'aw-tasks', 'aw-news-grid' ),
			),
		),
		'aw-directory' => array( // The Directory: search, filters, organisation rows, helplines, local cards; flags print it elsewhere.
			'file'   => 'css/aw-directory.css',
			'when'   => fn() => function_exists( 'aw_is_directory_view' ) && aw_is_directory_view(), // /directory/ and its place and type URLs
			'blocks' => array(
				'core/post-template' => 'aw-orgs', // the Directory list pattern, placed elsewhere
			),
		),
		'aw-listing' => array( // Directory listing pages and the claim page.
			'file' => 'css/aw-listing.css',
			'when' => fn() => is_singular( 'aw_listing' ) || is_page( 'claim' ),
		),
		'aw-hubs' => array( // Hub pages, the category hub and index, term chips, the adoption page; flags print it elsewhere.
			'file' => 'css/aw-hubs.css',
			'when' => '__return_true', // In every joined file (see above).
		),
	);
}

/**
 * The Directory views (hub, place, place × type) are pages, but load the Directory files: their own kind of joined file.
 *
 * @param string $kind Kind from Kits.
 */
function kits_site_page_kind( $kind ) {
	return function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() ? 'directory' : $kind;
}
add_filter( 'kits_parts_page_kind', 'kits_site_page_kind' );

/**
 * The component files, through the Kits registry (kits_component_styles), after the tokens and the Kits components.
 *
 * @param array $styles kits_component_styles entries.
 * @return array
 */
function kits_site_component_styles( $styles ) {
	if ( ! is_array( $styles ) ) {
		return $styles;
	}
	foreach ( kits_site_component_files() as $handle => $c ) {
		if ( empty( $c['file'] ) ) {
			continue;
		}
		$file              = kits_site_css_served( (string) $c['file'] );
		$styles[ $handle ] = array(
			'src'    => kits_site_uri( $file ),
			'file'   => kits_site_path( $file ),
			'deps'   => array( 'aw-tokens', 'kits-components' ),
			'when'   => $c['when'] ?? null,
			'blocks' => $c['blocks'] ?? array(),
		);
	}
	return $styles;
}
add_filter( 'kits_component_styles', 'kits_site_component_styles' );

/** Design tokens and interactions, over the Kits ones (the component files come through the Kits registry, above). */
function kits_site_enqueue() {
	$v = fn( $f ) => (string) filemtime( kits_site_path( $f ) );
	wp_enqueue_style( 'aw-tokens', kits_site_uri( 'css/tokens.css' ), array( 'kits-tokens' ), $v( 'css/tokens.css' ) ); // Brand values over the Kits defaults.
	wp_enqueue_script(
		'aw-interactions',
		kits_site_uri( 'js/aw.js' ),
		array( 'kits-interactions' ),
		$v( 'js/aw.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'kits_site_enqueue', 20 );

/** Reusable sections (patterns) appear under their own category in the block inserter. */
function kits_site_pattern_category() {
	register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes', 'kits-by-wugweb' ) ) );
}
add_action( 'init', 'kits_site_pattern_category' );

/** Home page: the hero photograph (hero pattern) is the largest thing on screen; the browser starts on it at once. */
function kits_site_hero_preload() {
	if ( ! is_front_page() || ( function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign() ) ) { // The campaign hero has its own artwork.
		return;
	}
	$base = '/wp-content/uploads/2026/10/aw1-im5-';
	$set  = implode( ', ', array_map( fn( $s ) => esc_url( home_url( $base . $s[0] . '.jpeg' ) ) . ' ' . $s[1] . 'w', array( array( '768x433', 768 ), array( '1024x578', 1024 ), array( '1536x866', 1536 ), array( '2048x1155', 2048 ) ) ) );
	// With Optimization Detective on, it already preloads the hero for the widths it has measured (up to 480px
	// and over 600px), so this one covers only the widths in between.
	$media = function_exists( 'od_get_breakpoint_max_widths' ) ? ' media="screen and (480px &lt; width) and (width &lt;= 600px)"' : '';
	echo '<link rel="preload" as="image" href="' . esc_url( home_url( $base . '1536x866.jpeg' ) ) . '" imagesrcset="' . esc_attr( $set ) . '" imagesizes="100vw" fetchpriority="high"' . $media . '>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'wp_head', 'kits_site_hero_preload', 1 );

/**
 * tokens.css is a few custom properties: printed inline in its link's place on every page. Same position in the cascade.
 *
 * @param string $tag    The link tag.
 * @param string $handle Style handle.
 */
function kits_site_tokens_inline( $tag, $handle ) {
	if ( 'aw-tokens' !== $handle ) {
		return $tag;
	}
	$file = kits_site_path( 'css/tokens.css' );
	return ! is_admin() && is_readable( $file ) ? '<style id="aw-tokens-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
}
add_filter( 'style_loader_tag', 'kits_site_tokens_inline', 10, 2 );

/**
 * The editors (post editor, Site Editor, classic editor) show content with the site's tokens and every component
 * file (sources, not minified), after the Kits styles.
 */
function kits_site_editor_styles() {
	foreach ( array_merge( array( 'css/tokens.css' ), array_column( kits_site_component_files(), 'file' ) ) as $f ) {
		add_editor_style( 'assets/site/' . $f );
	}
}
add_action( 'after_setup_theme', 'kits_site_editor_styles', 11 );

/**
 * Kits Core prints the relative time (aw-rel-time) in its post-date filter (priority 20); Kits names no Animal Writes
 * handle, so the site records aw-lists after it.
 *
 * @param string $html Block HTML.
 */
function kits_site_post_date_style( $html ) {
	if ( false !== strpos( (string) $html, 'aw-rel-time' ) ) {
		kits_use_component_style( 'aw-lists' );
	}
	return $html;
}
add_filter( 'render_block_core/post-date', 'kits_site_post_date_style', 21 );

/**
 * A template part block saved while the child theme was active can carry "theme":"animal-writes"; core renders a part
 * only for the active theme, so it is read as the active theme's part.
 *
 * @param array $block Parsed block.
 */
function kits_site_part_block_theme( $block ) {
	if ( 'core/template-part' === ( $block['blockName'] ?? '' ) && 'animal-writes' === ( $block['attrs']['theme'] ?? '' ) ) {
		$block['attrs']['theme'] = get_stylesheet();
	}
	return $block;
}
add_filter( 'render_block_data', 'kits_site_part_block_theme', 1 );


/**
 * One date format sitewide, Indian day-month-year (owner, 16 Oct 2026): "8 Oct 2026". WordPress's own date format
 * (core Post Date blocks, kits_date() with no format, date_i18n( get_option( 'date_format' ) )) follows it too, whatever
 * Settings → General says. It was a filter in Animal Writes Core (systems.php); it is the site's, so it lives here and
 * is never in a distributed theme. The constant keeps its name until Animal Writes Core's own callers are gone.
 */
if ( ! defined( 'AW_DATE_FORMAT' ) ) {
	define( 'AW_DATE_FORMAT', 'j M Y' );
}
add_filter( 'pre_option_date_format', fn() => AW_DATE_FORMAT );

/*
 * The site's configuration for Kits Core, moved from Animal Writes Core on 19 Nov 2026: analytics and sharing values
 * (config.php), the admin menus, labels, Overview and edit-screen map (admin.php), the values Kits Core's site
 * operations read (ops.php), the staging site's rules (staging.php) and the Developer: theme parts screen.
 */
foreach ( array( 'config', 'admin', 'ops', 'staging', 'theme-parts-screen' ) as $kits_site_file ) {
	require_once __DIR__ . '/' . $kits_site_file . '.php';
}
unset( $kits_site_file );
