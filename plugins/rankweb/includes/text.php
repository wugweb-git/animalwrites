<?php
/**
 * Plain-text helpers every RankWeb file uses, loaded first and always (not a module).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain text of a title or name: tags removed, entities decoded, spaces collapsed. The same as Animal Writes Core's
 * aw_seo_plain(), which RankWeb used before 18 Nov 2026.
 *
 * @param string $text Text or HTML.
 * @return string
 */
function rankweb_plain_text( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}
