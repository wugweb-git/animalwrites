<?php
/**
 * PHPUnit tests for custom JSON-LD parsing and the small builders (includes/class-aw-schema.php). No WordPress.
 * dataset_template() is left out: it uses wp_json_encode and is a fixed string.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/SchemaTest.php
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

require_once dirname( __DIR__ ) . '/includes/class-aw-schema.php';

/** Custom JSON-LD parsing and builders. */
final class SchemaTest extends PHPUnit\Framework\TestCase {

	public function testEmptyInputIsGoodWithNoNodes(): void {
		$r = AW_Schema::parse( '   ' );
		$this->assertSame( '', $r['error'] );
		$this->assertSame( array(), $r['nodes'] );
	}

	public function testParsesASingleObject(): void {
		$r = AW_Schema::parse( '{"@context":"https://schema.org","@type":"HowTo","name":"Bathe a dog"}' );
		$this->assertSame( '', $r['error'] );
		$this->assertCount( 1, $r['nodes'] );
		$this->assertSame( 'HowTo', $r['nodes'][0]['@type'] );
		$this->assertFalse( isset( $r['nodes'][0]['@context'] ), 'the node @context is dropped (the graph has one)' );
	}

	public function testParsesAListAndAGraphWrapper(): void {
		$list = AW_Schema::parse( '[{"@type":"Thing","name":"A"},{"@type":"Thing","name":"B"}]' );
		$this->assertCount( 2, $list['nodes'] );
		$graph = AW_Schema::parse( '{"@context":"https://schema.org","@graph":[{"@type":"Thing","name":"A"},{"@type":"Thing","name":"B"}]}' );
		$this->assertCount( 2, $graph['nodes'] );
		$this->assertSame( 'B', $graph['nodes'][1]['name'] );
	}

	public function testBrokenJsonReturnsAnError(): void {
		$r = AW_Schema::parse( '{ not json ' );
		$this->assertCount( 0, $r['nodes'] );
		$this->assertNotSame( '', $r['error'] );
	}

	public function testAnObjectWithoutATypeIsRejected(): void {
		$r = AW_Schema::parse( '{"name":"no type here"}' );
		$this->assertNotSame( '', $r['error'] );
		$this->assertCount( 0, $r['nodes'] );
	}

	public function testANodeInAListWithoutATypeIsRejected(): void {
		$r = AW_Schema::parse( '[{"@type":"Thing","name":"A"},{"name":"B"}]' );
		$this->assertNotSame( '', $r['error'] );
	}

	public function testAnArrayTypeIsAllowed(): void {
		$r = AW_Schema::parse( '{"@type":["Thing","Product"],"name":"X"}' );
		$this->assertSame( '', $r['error'] );
		$this->assertSame( array( 'Thing', 'Product' ), $r['nodes'][0]['@type'] );
	}

	public function testNodesAreCappedAtTheMaximum(): void {
		$items = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$items[] = '{"@type":"Thing","name":"n' . $i . '"}';
		}
		$r = AW_Schema::parse( '[' . implode( ',', $items ) . ']' );
		$this->assertCount( AW_Schema::MAX_NODES, $r['nodes'] );
	}

	public function testExtractFromHtmlReadsEveryLdJsonBlock(): void {
		$html  = '<html><head>'
			. '<script type="application/ld+json">{"@type":"Organization","name":"A"}</script>'
			. '<script type="application/javascript">var x = 1;</script>'
			. '<script type="application/ld+json">{"@graph":[{"@type":"WebSite","name":"S"}]}</script>'
			. '</head><body>x</body></html>';
		$nodes = AW_Schema::extract_from_html( $html );
		$this->assertCount( 2, $nodes );
		$this->assertSame( 'Organization', $nodes[0]['@type'] );
		$this->assertSame( 'WebSite', $nodes[1]['@type'] );
	}

	public function testExtractFromHtmlIgnoresPagesWithNoLdJson(): void {
		$this->assertSame( array(), AW_Schema::extract_from_html( '<p>Nothing here.</p>' ) );
	}

	public function testWithIdsAddsAStableIdOnlyWhenMissing(): void {
		$nodes = AW_Schema::with_ids(
			array(
				array(
					'@type' => 'Thing',
					'name'  => 'A',
				),
				array(
					'@type' => 'Thing',
					'@id'   => 'https://example.org/#kept',
				),
			),
			'https://example.org/p/'
		);
		$this->assertSame( 'https://example.org/p/#aw-custom-1', $nodes[0]['@id'] );
		$this->assertSame( 'https://example.org/#kept', $nodes[1]['@id'], 'an existing @id is kept' );
	}

	public function testSpeakableFromSelectorsOrNull(): void {
		$this->assertSame( null, AW_Schema::speakable( array( '', '  ' ) ) );
		$s = AW_Schema::speakable( array( '.intro', ' h1 ', '' ) );
		$this->assertSame( 'SpeakableSpecification', $s['@type'] );
		$this->assertSame( array( '.intro', 'h1' ), $s['cssSelector'] );
	}

	public function testThingsTurnUrlsIntoSameAsAndTextIntoNames(): void {
		$things = AW_Schema::things( array( 'Stray dogs', 'https://en.wikipedia.org/wiki/Street_dog', '' ) );
		$this->assertCount( 2, $things );
		$this->assertSame(
			array(
				'@type' => 'Thing',
				'name'  => 'Stray dogs',
			),
			$things[0]
		);
		$this->assertSame( 'https://en.wikipedia.org/wiki/Street_dog', $things[1]['sameAs'] );
	}
}
