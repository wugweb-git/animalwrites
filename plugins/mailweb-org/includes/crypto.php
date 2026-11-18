<?php
/**
 * Stored secrets (API token, webhook secrets, SMTP password) are encrypted in the database with a key made from the
 * site's own salts (wp-config). Values set in wp-config or the server environment are never stored. If the salts
 * change, saved secrets can no longer be read and must be entered again.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAILWEB_CRYPT_PREFIX = 'mwenc1:';

/** The encryption key (32 bytes) from the site's salts. */
function mailweb_crypt_key() {
	return hash( 'sha256', wp_salt( 'auth' ) . '|mailweb', true );
}

/**
 * Encrypt a secret for storage. Returns the value unchanged when it is empty, already encrypted, or OpenSSL is missing.
 *
 * @param string $plain Secret.
 * @return string
 */
function mailweb_encrypt( $plain ) {
	$plain = (string) $plain;
	if ( '' === $plain || str_starts_with( $plain, MAILWEB_CRYPT_PREFIX ) || ! function_exists( 'openssl_encrypt' ) ) {
		return $plain;
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$enc = openssl_encrypt( $plain, 'aes-256-gcm', mailweb_crypt_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return false === $enc ? $plain : MAILWEB_CRYPT_PREFIX . base64_encode( $iv . $tag . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

/**
 * Read a stored secret: decrypted when it was encrypted, as is otherwise ('' when it cannot be decrypted).
 *
 * @param string $stored Stored value.
 * @return string
 */
function mailweb_decrypt( $stored ) {
	$stored = (string) $stored;
	if ( ! str_starts_with( $stored, MAILWEB_CRYPT_PREFIX ) ) {
		return $stored;
	}
	$raw = base64_decode( substr( $stored, strlen( MAILWEB_CRYPT_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	if ( false === $raw || strlen( $raw ) < 29 || ! function_exists( 'openssl_decrypt' ) ) {
		return '';
	}
	$dec = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', mailweb_crypt_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
	return false === $dec ? '' : $dec;
}
