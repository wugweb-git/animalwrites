<?php
/**
 * Switching from the Animal Writes child theme to Kits by Wugweb alone (4 Nov 2026).
 *
 * The child's saved Styles (its wp_global_styles post: fonts, colours, any Site Editor changes) and its theme mods are
 * kept per theme, so Kits would start without them. They are copied to Kits just before core writes the new
 * `stylesheet` option (pre_update_option_stylesheet, inside switch_theme()), so no request ever sees Kits active
 * without them:
 * - Styles: only when Kits has no saved Styles of its own, or only core's empty default post. Revisions stay on the
 *   child's post;
 * - theme mods: only the keys Kits does not already have; a positive custom_css_post_id is not copied (that
 *   Additional CSS post belongs to the child).
 * The child's records are never changed, so switching back to it finds them as they were. Each run is recorded in
 * option aw_theme_switch_log (last 10) and shown on Animal Writes → Check site → Theme switch.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_THEME_SWITCH_FROM = 'animal-writes';
const AW_THEME_SWITCH_TO   = 'kits-by-wugweb';

/**
 * A theme's saved Styles post, found the way core finds it, or null.
 *
 * @param string $stylesheet Theme folder name.
 */
function aw_theme_switch_styles_post( string $stylesheet ): ?WP_Post {
	$theme = wp_get_theme( $stylesheet );
	if ( ! $theme->exists() || ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		return null;
	}
	$row = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( $theme, false );
	return ! empty( $row['ID'] ) ? get_post( (int) $row['ID'] ) : null;
}

/**
 * Whether a saved Styles post holds nothing but core's default (version and flag only).
 *
 * @param WP_Post $post Styles post.
 */
function aw_theme_switch_styles_empty( WP_Post $post ): bool {
	$data = json_decode( (string) $post->post_content, true );
	if ( ! is_array( $data ) ) {
		return true;
	}
	unset( $data['version'], $data['isGlobalStylesUserThemeJSON'] );
	return empty( array_filter( $data ) );
}

/**
 * Copies the child's saved Styles and theme mods to Kits when the site is about to switch from the child to Kits.
 * Filter callback: always returns the new value unchanged.
 *
 * @param mixed $value     New `stylesheet` value.
 * @param mixed $old_value Current `stylesheet` value.
 * @return mixed
 */
function aw_theme_switch_copy( $value, $old_value ) {
	if ( AW_THEME_SWITCH_FROM !== $old_value || AW_THEME_SWITCH_TO !== $value || ! wp_get_theme( AW_THEME_SWITCH_TO )->exists() ) {
		return $value;
	}
	$user  = wp_get_current_user();
	$entry = array(
		'time'   => time(),
		'user'   => $user->exists() ? $user->user_login : '',
		'from'   => AW_THEME_SWITCH_FROM,
		'to'     => AW_THEME_SWITCH_TO,
		'styles' => '',
		'mods'   => '',
	);

	// Saved Styles.
	$src = aw_theme_switch_styles_post( AW_THEME_SWITCH_FROM );
	$dst = aw_theme_switch_styles_post( AW_THEME_SWITCH_TO );
	if ( ! $src ) {
		$entry['styles'] = 'The child theme has no saved Styles; nothing copied.';
	} elseif ( $dst && ! aw_theme_switch_styles_empty( $dst ) ) {
		$entry['styles'] = sprintf( 'Kits already has its own saved Styles (#%d); left as they are. The child\'s are #%d.', $dst->ID, $src->ID );
	} elseif ( $dst ) {
		$id              = wp_update_post(
			array(
				'ID'           => $dst->ID,
				'post_content' => wp_slash( $src->post_content ),
			),
			true
		);
		$entry['styles'] = is_wp_error( $id ) ? 'Copy failed: ' . $id->get_error_message() : sprintf( 'Copied the child\'s saved Styles (#%d) into Kits\' empty Styles post (#%d).', $src->ID, $dst->ID );
	} else {
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_global_styles',
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles', // As core names it (not translated).
				'post_name'    => sprintf( 'wp-global-styles-%s', urlencode( AW_THEME_SWITCH_TO ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- core's own name for the post
				'post_content' => wp_slash( $src->post_content ),
			),
			true
		);
		if ( ! is_wp_error( $id ) ) {
			$terms = wp_set_object_terms( $id, AW_THEME_SWITCH_TO, 'wp_theme' );
			if ( is_wp_error( $terms ) ) {
				$id = $terms;
			}
		}
		$entry['styles'] = is_wp_error( $id ) ? 'Copy failed: ' . $id->get_error_message() : sprintf( 'Copied the child\'s saved Styles (#%d) to a new Kits Styles post (#%d).', $src->ID, $id );
	}

	// Theme mods: keys Kits does not have yet. The child's own Additional CSS post stays the child's.
	$from_mods = get_option( 'theme_mods_' . AW_THEME_SWITCH_FROM );
	$to_mods   = get_option( 'theme_mods_' . AW_THEME_SWITCH_TO );
	$to_mods   = is_array( $to_mods ) ? $to_mods : array();
	if ( ! is_array( $from_mods ) || ! $from_mods ) {
		$entry['mods'] = 'The child theme has no theme mods; nothing copied.';
	} else {
		if ( isset( $from_mods['custom_css_post_id'] ) && (int) $from_mods['custom_css_post_id'] > 0 ) {
			unset( $from_mods['custom_css_post_id'] );
		}
		$add = array_diff_key( $from_mods, $to_mods );
		if ( $add ) {
			update_option( 'theme_mods_' . AW_THEME_SWITCH_TO, array_merge( $to_mods, $add ) );
		}
		$entry['mods'] = $add ? 'Copied theme mods: ' . implode( ', ', array_map( 'strval', array_keys( $add ) ) ) . '.' : 'Kits already has every theme mod the child has; nothing copied.';
	}

	// Built from the theme in use: drop what was cached under the child.
	if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
		WP_Theme_JSON_Resolver::clean_cached_data();
	}
	if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
		wp_clean_theme_json_cache();
	}
	delete_site_transient( 'aw_core_theme_patterns' );

	$log   = get_option( 'aw_theme_switch_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = $entry;
	update_option( 'aw_theme_switch_log', array_slice( $log, -10 ), false );
	return $value;
}
add_filter( 'pre_update_option_stylesheet', 'aw_theme_switch_copy', 10, 2 );

/** Animal Writes → Check site → Theme switch: the last theme switch from the child to Kits, if there was one. */
function aw_theme_switch_site_check() {
	if ( ! current_user_can( 'switch_themes' ) ) {
		return;
	}
	$log = get_option( 'aw_theme_switch_log', array() );
	echo '<h2>' . esc_html( 'Theme switch' ) . '</h2>';
	if ( ! is_array( $log ) || ! $log ) {
		echo '<p>' . esc_html( 'The site has not switched from the Animal Writes child theme to Kits by Wugweb yet.' ) . '</p>';
		return;
	}
	$last = end( $log );
	$time = isset( $last['time'] ) ? wp_date( 'j M Y, H:i', (int) $last['time'] ) : '';
	$who  = '' !== (string) ( $last['user'] ?? '' ) ? ' by ' . (string) $last['user'] : '';
	echo '<p>' . esc_html( sprintf( 'Last switch from %1$s to %2$s: %3$s%4$s.', (string) ( $last['from'] ?? '' ), (string) ( $last['to'] ?? '' ), $time, $who ) ) . '</p><ul>';
	echo '<li>' . esc_html( 'Styles: ' . (string) ( $last['styles'] ?? '' ) ) . '</li>';
	echo '<li>' . esc_html( 'Theme mods: ' . (string) ( $last['mods'] ?? '' ) ) . '</li>';
	echo '</ul>';
}
add_action( 'aw_site_check_after', 'aw_theme_switch_site_check', 30 );
