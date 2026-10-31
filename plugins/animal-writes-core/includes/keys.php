<?php
/**
 * Animal Writes → Keys (owner, 31 Oct 2026): every service key the site's plugins use, set in WP admin. A wp-config.php
 * constant of the same name always wins. Values are kept in one option that is not autoloaded and are never shown
 * again: the screen only says whether a key is set and where. Administrators only.
 *
 * Plugins add their keys with the aw_keys filter (name => [label, where to find it, who uses it]) and read them with
 * aw_key( 'NAME' ). The AI provider keys stay where they are (owner's rule: AI keys are the owner's alone).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_KEYS_OPTION = 'aw_keys';

/** The keys: constant name => [label, where to find it, used by]. */
function aw_keys_list(): array {
	return (array) apply_filters( 'aw_keys', array() );
}

/**
 * A key's value: the wp-config.php constant if set, else the one saved under Animal Writes → Keys, else ''.
 *
 * @param string $name Constant name, e.g. AW_BING_WEBMASTER_KEY.
 */
function aw_key( string $name ): string {
	if ( defined( $name ) && '' !== (string) constant( $name ) ) {
		return (string) constant( $name );
	}
	$saved = get_option( AW_KEYS_OPTION, array() );
	return is_array( $saved ) ? (string) ( $saved[ $name ] ?? '' ) : '';
}

/**
 * Where a key is set: config, admin or ''.
 *
 * @param string $name Constant name.
 */
function aw_key_where( string $name ): string {
	if ( defined( $name ) && '' !== (string) constant( $name ) ) {
		return 'config';
	}
	return '' !== aw_key( $name ) ? 'admin' : '';
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_CORE_MENU, 'Keys', 'Keys', 'manage_options', 'aw-keys', 'aw_keys_screen' ); // Animal Writes → Keys
	},
	13 // after Contact links (12)
);

/** Animal Writes → Keys. */
function aw_keys_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>Keys</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$n = (int) $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 1 === $n ? '1 key changed.' : $n . ' keys changed.' ) . '</p></div>';
	}
	echo '<p>Service keys the site\'s plugins use. Paste a key and save; it is never shown again, only whether it is set. A key in wp-config.php always wins.</p>';
	$list = aw_keys_list();
	if ( ! $list ) {
		echo '<p>No plugin uses a key yet.</p></div>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_keys">';
	wp_nonce_field( 'aw_keys' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $list as $name => $k ) {
		$where = aw_key_where( $name );
		$state = 'config' === $where ? 'Set in wp-config.php (that one is used).' : ( 'admin' === $where ? 'Set here.' : 'Not set.' );
		echo '<tr><th scope="row"><label for="aw-key-' . esc_attr( $name ) . '">' . esc_html( (string) $k[0] ) . '</label><br><code>' . esc_html( $name ) . '</code></th><td>';
		echo '<p><span class="dashicons dashicons-' . ( $where ? 'yes-alt' : 'minus' ) . '" aria-hidden="true"></span> ' . esc_html( $state ) . '</p>';
		if ( 'config' !== $where ) {
			echo '<input type="password" class="regular-text" id="aw-key-' . esc_attr( $name ) . '" name="keys[' . esc_attr( $name ) . ']" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr( 'admin' === $where ? 'Saved: leave empty to keep it' : 'Paste the key' ) . '">';
			if ( 'admin' === $where ) {
				echo ' <label><input type="checkbox" name="clear[]" value="' . esc_attr( $name ) . '"> Remove</label>';
			}
		}
		echo '<p class="description">' . esc_html( (string) $k[1] ) . ( empty( $k[2] ) ? '' : ' Used by: ' . esc_html( (string) $k[2] ) . '.' ) . '</p></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Save keys' );
	echo '</form></div>';
}

add_action(
	'admin_post_aw_keys',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only administrators can change keys.', 403 );
		}
		check_admin_referer( 'aw_keys' );
		$saved = get_option( AW_KEYS_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$in    = isset( $_POST['keys'] ) ? (array) wp_unslash( $_POST['keys'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitised below.
		$clear = isset( $_POST['clear'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['clear'] ) ) : array();
		$n     = 0;
		foreach ( array_keys( aw_keys_list() ) as $name ) {
			if ( in_array( $name, $clear, true ) ) {
				unset( $saved[ $name ] );
				++$n;
				continue;
			}
			$v = preg_replace( '/\s+/', '', sanitize_text_field( (string) ( $in[ $name ] ?? '' ) ) );
			if ( '' !== $v ) {
				$saved[ $name ] = mb_substr( $v, 0, 512 );
				++$n;
			}
		}
		update_option( AW_KEYS_OPTION, $saved, false );
		wp_safe_redirect( admin_url( 'admin.php?page=aw-keys&saved=' . $n ) );
		exit;
	}
);
