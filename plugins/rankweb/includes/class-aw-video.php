<?php
/**
 * Video detection and the VideoObject node: pure PHP, no WordPress, so tests/VideoTest.php runs it on sample HTML.
 * video.php feeds it the content and the stored oEmbed data.
 *
 * It finds YouTube and Vimeo videos in content, whether written as a bare URL on its own line (the WordPress embed),
 * a <figure>/<iframe> block, or a youtu.be/youtube-nocookie link, and gives each one a provider and an id. video.php
 * then fetches each one's title, thumbnail and (Vimeo) duration and upload date from the provider's public oEmbed
 * endpoint, and this class turns the stored data into a schema.org VideoObject.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Video detection and the VideoObject node. */
final class AW_Video {

	/**
	 * The videos in some HTML, in order, no repeats: array( provider 'youtube'|'vimeo', id, embed, watch ).
	 *
	 * @param string $html Content HTML.
	 * @return array[]
	 */
	public static function find( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		$out  = array();
		$seen = array();
		// Every http(s) URL in the text or in an attribute (src, href), plus bare youtu.be / youtube / vimeo tokens.
		if ( preg_match_all( '~https?://[^\s"\'<>)]+~i', $html, $m ) ) {
			foreach ( $m[0] as $url ) {
				$v = self::parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( $v && ! isset( $seen[ $v['provider'] . ':' . $v['id'] ] ) ) {
					$seen[ $v['provider'] . ':' . $v['id'] ] = true;
					$out[]                                   = $v;
				}
			}
		}
		return $out;
	}

	/**
	 * A YouTube or Vimeo video from one URL, or null. Accepts watch, short, embed, nocookie and player URLs.
	 *
	 * @param string $url A URL.
	 * @return array{provider:string,id:string,embed:string,watch:string}|null
	 */
	public static function parse_url( string $url ): ?array {
		$url = trim( $url );
		// YouTube: watch?v=, youtu.be/, /embed/, /shorts/, youtube-nocookie.com/embed/.
		if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $m[1],
				'embed'    => 'https://www.youtube.com/embed/' . $m[1],
				'watch'    => 'https://www.youtube.com/watch?v=' . $m[1],
			);
		}
		// Vimeo: vimeo.com/<digits>, player.vimeo.com/video/<digits>.
		if ( preg_match( '~(?:player\.)?vimeo\.com/(?:video/)?(\d{6,})~i', $url, $m ) ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $m[1],
				'embed'    => 'https://player.vimeo.com/video/' . $m[1],
				'watch'    => 'https://vimeo.com/' . $m[1],
			);
		}
		return null;
	}

	/**
	 * A schema.org VideoObject from one stored video, or null when it has no name (nothing was fetched).
	 *
	 * @param array  $v        Stored video: provider, id, embed, watch, name, description, thumbnail, upload_date (ISO
	 *                         8601 date or datetime), duration (whole seconds).
	 * @param string $fallback A description to use when the video has none (the item's own).
	 * @return array|null
	 */
	public static function node( array $v, string $fallback = '' ): ?array {
		$name = trim( (string) ( $v['name'] ?? '' ) );
		if ( '' === $name ) {
			return null;
		}
		$node = array(
			'@type' => 'VideoObject',
			'name'  => $name,
		);
		$desc = trim( (string) ( $v['description'] ?? '' ) );
		$desc = '' !== $desc ? $desc : trim( $fallback );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}
		$thumb = trim( (string) ( $v['thumbnail'] ?? '' ) );
		if ( '' !== $thumb ) {
			$node['thumbnailUrl'] = $thumb;
		}
		$date = trim( (string) ( $v['upload_date'] ?? '' ) );
		if ( '' !== $date ) {
			$node['uploadDate'] = $date;
		}
		$dur = self::duration( (int) ( $v['duration'] ?? 0 ) );
		if ( '' !== $dur ) {
			$node['duration'] = $dur;
		}
		if ( ! empty( $v['embed'] ) ) {
			$node['embedUrl'] = (string) $v['embed'];
		}
		if ( ! empty( $v['watch'] ) ) {
			$node['url'] = (string) $v['watch'];
		}
		return $node;
	}

	/**
	 * Whole seconds as an ISO 8601 duration (90 => PT1M30S). '' for nothing.
	 *
	 * @param int $seconds Seconds.
	 */
	public static function duration( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return '';
		}
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s ? $s . 'S' : '' );
	}
}
