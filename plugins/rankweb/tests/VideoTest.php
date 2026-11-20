<?php
/**
 * PHPUnit tests for video detection and the VideoObject node (includes/class-aw-video.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/VideoTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this)
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // the class is pure PHP; the constant only satisfies the direct-access guard
}

require_once dirname( __DIR__ ) . '/includes/class-aw-video.php';

/** Video detection and the VideoObject node. */
final class VideoTest extends PHPUnit\Framework\TestCase {

	public function testParsesEveryShapeOfYouTubeUrlToTheSameId(): void {
		foreach ( array(
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			'https://youtu.be/dQw4w9WgXcQ',
			'https://www.youtube.com/embed/dQw4w9WgXcQ',
			'https://www.youtube.com/shorts/dQw4w9WgXcQ',
			'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
			'https://www.youtube.com/watch?list=PL1&v=dQw4w9WgXcQ&t=3',
		) as $url ) {
			$v = AW_Video::parse_url( $url );
			$this->assertSame( 'youtube', $v['provider'], $url );
			$this->assertSame( 'dQw4w9WgXcQ', $v['id'], $url );
			$this->assertSame( 'https://www.youtube.com/embed/dQw4w9WgXcQ', $v['embed'], $url );
		}
	}

	public function testParsesVimeoWatchAndPlayerUrls(): void {
		foreach ( array( 'https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789' ) as $url ) {
			$v = AW_Video::parse_url( $url );
			$this->assertSame( 'vimeo', $v['provider'], $url );
			$this->assertSame( '123456789', $v['id'], $url );
			$this->assertSame( 'https://player.vimeo.com/video/123456789', $v['embed'], $url );
		}
	}

	public function testNonVideoUrlsAreNull(): void {
		$this->assertSame( null, AW_Video::parse_url( 'https://example.org/watch?v=nope' ) );
		$this->assertSame( null, AW_Video::parse_url( 'https://vimeo.com/channels/staffpicks' ) );
		$this->assertSame( null, AW_Video::parse_url( 'https://youtube.com/@channel' ) );
	}

	public function testFindsVideosInAnEmbedBlockAndABareUrlWithoutRepeats(): void {
		$html  = '<p>Watch this.</p>'
			. '<figure class="wp-block-embed"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0"></iframe></figure>'
			. '<p>https://vimeo.com/123456789</p>'
			. '<p>and again https://youtu.be/dQw4w9WgXcQ</p>';
		$found = AW_Video::find( $html );
		$this->assertCount( 2, $found );
		$this->assertSame( 'youtube', $found[0]['provider'] );
		$this->assertSame( 'dQw4w9WgXcQ', $found[0]['id'] );
		$this->assertSame( 'vimeo', $found[1]['provider'] );
	}

	public function testFindsNothingInPlainContent(): void {
		$this->assertSame( array(), AW_Video::find( '<p>No videos here, just a link to https://example.org/.</p>' ) );
		$this->assertSame( array(), AW_Video::find( '' ) );
	}

	public function testDecodesEntityEncodedUrls(): void {
		$found = AW_Video::find( '<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;t=10">x</a>' );
		$this->assertCount( 1, $found );
		$this->assertSame( 'dQw4w9WgXcQ', $found[0]['id'] );
	}

	public function testDurationIsIso8601(): void {
		$this->assertSame( 'PT1M30S', AW_Video::duration( 90 ) );
		$this->assertSame( 'PT2H3M4S', AW_Video::duration( 7384 ) );
		$this->assertSame( 'PT45S', AW_Video::duration( 45 ) );
		$this->assertSame( 'PT1H', AW_Video::duration( 3600 ) );
		$this->assertSame( '', AW_Video::duration( 0 ) );
		$this->assertSame( '', AW_Video::duration( -5 ) );
	}

	public function testNodeIsNullUntilAVideoHasBeenFetched(): void {
		$this->assertSame(
			null,
			AW_Video::node(
				array(
					'provider' => 'youtube',
					'id'       => 'x',
					'embed'    => 'e',
					'watch'    => 'w',
				)
			)
		);
	}

	public function testNodeUsesTheFallbackDescriptionWhenTheVideoHasNone(): void {
		$node = AW_Video::node(
			array(
				'name'  => 'A rescue story',
				'embed' => 'https://www.youtube.com/embed/x',
				'watch' => 'https://youtu.be/x',
			),
			'The item summary.'
		);
		$this->assertSame( 'VideoObject', $node['@type'] );
		$this->assertSame( 'A rescue story', $node['name'] );
		$this->assertSame( 'The item summary.', $node['description'] );
		$this->assertSame( 'https://www.youtube.com/embed/x', $node['embedUrl'] );
		$this->assertFalse( isset( $node['uploadDate'] ), 'no date was fetched' );
		$this->assertFalse( isset( $node['duration'] ), 'no duration was fetched' );
	}

	public function testNodeKeepsEveryFetchedField(): void {
		$node = AW_Video::node(
			array(
				'name'        => 'Vimeo film',
				'description' => 'Its own description.',
				'thumbnail'   => 'https://i.vimeocdn.com/x.jpg',
				'upload_date' => '2026-03-01',
				'duration'    => 125,
				'embed'       => 'https://player.vimeo.com/video/1',
				'watch'       => 'https://vimeo.com/1',
			)
		);
		$this->assertSame( 'Its own description.', $node['description'] );
		$this->assertSame( 'https://i.vimeocdn.com/x.jpg', $node['thumbnailUrl'] );
		$this->assertSame( '2026-03-01', $node['uploadDate'] );
		$this->assertSame( 'PT2M5S', $node['duration'] );
		$this->assertSame( 'https://vimeo.com/1', $node['url'] );
	}
}
