<?php
/**
 * Media & PR list and banner slots (restructure Phase 10).
 *
 * - [aw_media_list]: coverage of Animal Writes (outlet, date, headline, link, optional logo), edited in
 *   WP Admin > Pages > Media & PR list. Seeded with the 1.0 coverage list.
 * - Banners: an optional image + text strip on section hubs, topic hubs, About and Media & PR, edited in
 *   WP Admin > Settings > Banners. Hidden when empty. 16:5 on wide screens, 4:3 on phones, fixed dimensions,
 *   preloaded, alt text required.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Media & PR list ---------- */

function aw_press_items() {
	return array_values( array_filter( (array) get_option( 'aw_press', array() ), fn( $r ) => ! empty( $r['headline'] ) && ! empty( $r['url'] ) ) );
}

add_shortcode(
	'aw_media_list',
	function () {
		$rows = aw_press_items();
		if ( ! $rows ) {
			return '';
		}
		$html = '<ul class="aw-press">';
		foreach ( $rows as $r ) {
			$logo  = ! empty( $r['logo'] ) ? wp_get_attachment_image( (int) $r['logo'], array( 120, 40 ), false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
			$date  = ! empty( $r['date'] ) ? '<time datetime="' . esc_attr( $r['date'] ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $r['date'] ) ) ) . '</time>' : '';
			$html .= '<li>' . $logo . '<a href="' . esc_url( $r['url'] ) . '" rel="noopener">' . esc_html( $r['headline'] ) . '</a>'
				. '<span class="aw-press-meta">' . esc_html( $r['outlet'] ?? '' ) . ( $date ? ' · ' . $date : '' ) . '</span></li>';
		}
		return $html . '</ul>';
	}
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=page', 'Media & PR list', 'Media & PR list', 'edit_pages', 'aw-press', 'aw_press_screen' );
		add_options_page( 'Banners', 'Banners', 'edit_pages', 'aw-banners', 'aw_banners_screen' );
	}
);

function aw_press_screen() {
	if ( isset( $_POST['aw_press'] ) && check_admin_referer( 'aw_press' ) ) {
		$rows = array();
		foreach ( (array) wp_unslash( $_POST['aw_press'] ) as $r ) {
			$row = array(
				'outlet'   => sanitize_text_field( $r['outlet'] ?? '' ),
				'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $r['date'] ?? '' ) ) ? $r['date'] : '',
				'headline' => sanitize_text_field( $r['headline'] ?? '' ),
				'url'      => esc_url_raw( $r['url'] ?? '' ),
				'logo'     => absint( $r['logo'] ?? 0 ),
			);
			if ( $row['headline'] && $row['url'] ) {
				$rows[] = $row;
			}
		}
		update_option( 'aw_press', $rows, false );
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	$rows = array_merge( (array) get_option( 'aw_press', array() ), array_fill( 0, 5, array() ) );
	echo '<div class="wrap"><h1>Media &amp; PR list</h1><p>Shown on <a href="' . esc_url( home_url( '/media/' ) ) . '" target="_blank">Media &amp; PR</a>, newest first as listed here. Clear a headline to remove a row. Logo: a media library ID (optional).</p><form method="post">';
	wp_nonce_field( 'aw_press' );
	echo '<table class="widefat striped"><thead><tr><th>Outlet</th><th>Date (YYYY-MM-DD)</th><th>Headline</th><th>Link</th><th>Logo ID</th></tr></thead><tbody>';
	foreach ( $rows as $i => $r ) {
		echo '<tr>';
		foreach ( array( 'outlet' => 14, 'date' => 10, 'headline' => 40, 'url' => 30, 'logo' => 5 ) as $k => $size ) {
			echo '<td><input type="text" size="' . (int) $size . '" name="aw_press[' . (int) $i . '][' . esc_attr( $k ) . ']" value="' . esc_attr( (string) ( $r[ $k ] ?? '' ) ) . '"></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}

/* ---------- Banners ---------- */

/** Banner locations: key => label. Topics are added as topic:{slug}. */
function aw_banner_locations() {
	$loc = array(
		'home'           => 'Home',
		'aw_listing'     => 'Directory',
		'aw_news'        => 'News',
		'aw_guide'       => 'Resources',
		'aw_legal'       => 'Library',
		'aw_opportunity' => 'Opportunities',
		'aw_campaign'    => 'Campaigns',
		'page:about'     => 'About',
		'page:media'     => 'Media & PR',
	);
	foreach ( get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$loc[ 'topic:' . $t->slug ] = 'Topic: ' . $t->name;
	}
	return $loc;
}

/** The banner key for the current page, or ''. */
function aw_banner_key() {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_tax( 'aw_topic' ) ) {
		return 'topic:' . get_queried_object()->slug;
	}
	if ( is_page( array( 'about', 'media' ) ) ) {
		return 'page:' . get_post_field( 'post_name', get_queried_object_id() );
	}
	foreach ( array_keys( aw_banner_locations() ) as $k ) {
		if ( 0 === strpos( $k, 'aw_' ) && is_post_type_archive( $k ) ) {
			return $k;
		}
	}
	return '';
}

/** A complete banner (needs a desktop image, a mobile image and alt text), or null. */
function aw_banner( $key ) {
	$b = ( (array) get_option( 'aw_banners', array() ) )[ $key ] ?? array();
	return ( ! empty( $b['desktop'] ) && ! empty( $b['mobile'] ) && '' !== trim( (string) ( $b['alt'] ?? '' ) ) ) ? $b : null;
}

function aw_banner_html( $key ) {
	$b = $key ? aw_banner( $key ) : null;
	if ( ! $b ) {
		return '';
	}
	$d = wp_get_attachment_image_src( (int) $b['desktop'], 'full' );
	$m = wp_get_attachment_image_src( (int) $b['mobile'], 'full' );
	if ( ! $d || ! $m ) {
		return '';
	}
	return '<div class="aw-banner"><picture>'
		. '<source media="(max-width: 767px)" srcset="' . esc_url( $m[0] ) . '" width="' . (int) $m[1] . '" height="' . (int) $m[2] . '">'
		. '<img src="' . esc_url( $d[0] ) . '" width="' . (int) $d[1] . '" height="' . (int) $d[2] . '" alt="' . esc_attr( $b['alt'] ) . '" fetchpriority="high" decoding="async">'
		. '</picture>' . ( ! empty( $b['text'] ) ? '<div class="aw-banner-text">' . wp_kses_post( $b['text'] ) . '</div>' : '' )
		. '</div>';
}

// Preload the banner image the visitor will see.
add_action(
	'wp_head',
	function () {
		$b = aw_banner( aw_banner_key() );
		if ( ! $b ) {
			return;
		}
		$d = wp_get_attachment_image_url( (int) $b['desktop'], 'full' );
		$m = wp_get_attachment_image_url( (int) $b['mobile'], 'full' );
		echo '<link rel="preload" as="image" href="' . esc_url( $m ) . '" media="(max-width: 767px)">' . "\n";
		echo '<link rel="preload" as="image" href="' . esc_url( $d ) . '" media="(min-width: 768px)">' . "\n";
	},
	2
);

// Where it shows: above section and topic headers, and above the title of About and Media & PR.
add_filter(
	'do_shortcode_tag',
	fn( $html, $tag ) => 'aw_section_head' === $tag ? aw_banner_html( aw_banner_key() ) . $html : $html,
	5,
	2
);
add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		static $done = false;
		if ( $done || ! is_page( array( 'about', 'media' ) ) || 1 !== (int) ( $block['attrs']['level'] ?? 2 ) ) {
			return $html;
		}
		$done = true;
		return aw_banner_html( aw_banner_key() ) . $html;
	},
	5,
	2
);

function aw_banners_screen() {
	$all = (array) get_option( 'aw_banners', array() );
	if ( isset( $_POST['aw_banners'] ) && check_admin_referer( 'aw_banners' ) ) {
		$notes = array();
		foreach ( (array) wp_unslash( $_POST['aw_banners'] ) as $key => $b ) {
			$key = sanitize_text_field( $key );
			$row = array(
				'desktop' => absint( $b['desktop'] ?? 0 ),
				'mobile'  => absint( $b['mobile'] ?? 0 ),
				'alt'     => sanitize_text_field( $b['alt'] ?? '' ),
				'text'    => wp_kses_post( $b['text'] ?? '' ),
				'credit'     => sanitize_text_field( $b['credit'] ?? '' ),
				'credit_url' => esc_url_raw( $b['credit_url'] ?? '' ),
			);
			if ( ( $row['desktop'] || $row['mobile'] ) && '' === $row['alt'] ) {
				$notes[] = $key;
			}
			$all[ $key ] = $row;
		}
		update_option( 'aw_banners', $all, false );
		echo '<div class="notice notice-success"><p>Saved.' . ( $notes ? ' These need alt text before they show: ' . esc_html( implode( ', ', $notes ) ) . '.' : '' ) . '</p></div>';
	}
	echo '<div class="wrap"><h1>Banners</h1><p>Optional banner at the top of each place. It shows only when it has a wide image (16:5, e.g. 1600×500), a phone image (4:3, e.g. 800×600) and alt text. Use WebP or AVIF. Images: media library IDs.</p><form method="post">';
	wp_nonce_field( 'aw_banners' );
	echo '<table class="widefat striped"><thead><tr><th>Place</th><th>Wide image ID</th><th>Phone image ID</th><th>Alt text</th><th>Text on the banner (optional)</th><th>Photographer</th><th>Photo page</th></tr></thead><tbody>';
	foreach ( aw_banner_locations() as $key => $label ) {
		$b = $all[ $key ] ?? array();
		echo '<tr><td>' . esc_html( $label ) . '</td>'
			. '<td><input type="number" min="0" name="aw_banners[' . esc_attr( $key ) . '][desktop]" value="' . esc_attr( (string) ( $b['desktop'] ?? '' ) ) . '" style="width:7em"></td>'
			. '<td><input type="number" min="0" name="aw_banners[' . esc_attr( $key ) . '][mobile]" value="' . esc_attr( (string) ( $b['mobile'] ?? '' ) ) . '" style="width:7em"></td>'
			. '<td><input type="text" name="aw_banners[' . esc_attr( $key ) . '][alt]" value="' . esc_attr( (string) ( $b['alt'] ?? '' ) ) . '"></td>'
			. '<td><input type="text" class="regular-text" name="aw_banners[' . esc_attr( $key ) . '][text]" value="' . esc_attr( (string) ( $b['text'] ?? '' ) ) . '"></td>'
			. '<td><input type="text" name="aw_banners[' . esc_attr( $key ) . '][credit]" value="' . esc_attr( (string) ( $b['credit'] ?? '' ) ) . '"></td>'
			. '<td><input type="url" name="aw_banners[' . esc_attr( $key ) . '][credit_url]" value="' . esc_attr( (string) ( $b['credit_url'] ?? '' ) ) . '"></td></tr>';
	}
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}

add_shortcode( 'aw_banner', fn() => aw_banner_html( aw_banner_key() ) );
