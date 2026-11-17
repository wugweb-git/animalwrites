<?php
/**
 * PHPUnit tests for the anchor type sorter and the anchor mix (includes/class-aw-anchor.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/AnchorTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this)
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // the sorter is pure PHP; the constant only satisfies its direct-access guard
}

require_once dirname( __DIR__ ) . '/includes/class-aw-anchor.php';

/** The anchor types and the mix. */
final class AnchorTest extends PHPUnit\Framework\TestCase {

	private function ctx( array $more = array() ): array {
		return $more + array(
			'keywords'   => array( 'stray dogs', 'street dog care' ),
			'brand'      => AW_Anchor::normalize_list( array( 'Animal Writes' ) ),
			'generic'    => AW_Anchor::normalize_list( array( 'click here', 'Read more', 'this page' ) ),
			'long_words' => 6,
		);
	}

	public function testTheMainKeywordIsExactIgnoringCaseAndSpacing(): void {
		$this->assertSame( 'exact', AW_Anchor::type( 'Stray  Dogs', $this->ctx() ) );
		$this->assertSame( 'exact', AW_Anchor::type( 'stray dogs', $this->ctx() ) );
	}

	public function testAnotherKeywordOfTheTargetIsPartialNotExact(): void {
		$this->assertSame( 'partial', AW_Anchor::type( 'street dog care', $this->ctx() ) );
	}

	public function testAnAnchorWithAKeywordWordIsPartial(): void {
		$this->assertSame( 'partial', AW_Anchor::type( 'helping dogs in need', $this->ctx() ) );
	}

	public function testShortWordsOfAKeywordDoNotMakeItPartial(): void {
		$ctx = $this->ctx( array( 'keywords' => array( 'law of cruelty' ) ) );
		$this->assertSame( 'other', AW_Anchor::type( 'what of it', $ctx ), '"of" is under 3 letters' );
	}

	public function testBrandedMatchesTheBrandAsWholeWords(): void {
		$this->assertSame( 'branded', AW_Anchor::type( 'Animal Writes guide', $this->ctx() ) );
		$this->assertSame( 'other', AW_Anchor::type( 'animal writesy', $this->ctx() ) );
	}

	public function testGenericIsAnEditableList(): void {
		$this->assertSame( 'generic', AW_Anchor::type( 'Click here', $this->ctx() ) );
		$this->assertSame( 'generic', AW_Anchor::type( 'read more', $this->ctx() ) );
		$this->assertSame( 'other', AW_Anchor::type( 'read more about it', $this->ctx() ) );
	}

	public function testNakedUrlsAreFoundWithAndWithoutScheme(): void {
		$this->assertSame( 'naked', AW_Anchor::type( 'https://example.org/stray-dogs/', $this->ctx() ) );
		$this->assertSame( 'naked', AW_Anchor::type( 'www.example.org', $this->ctx() ) );
		$this->assertSame( 'naked', AW_Anchor::type( 'example.org/dogs', $this->ctx() ) );
		$this->assertSame( 'other', AW_Anchor::type( 'dogs. Then', $this->ctx( array( 'keywords' => array() ) ) ) );
	}

	public function testAnImageLinkIsImageWhateverItsAltSays(): void {
		$this->assertSame( 'image', AW_Anchor::type( 'stray dogs', $this->ctx( array( 'is_image' => true ) ) ) );
	}

	public function testMoreWordsThanTheLimitIsLongTail(): void {
		$this->assertSame( 'long_tail', AW_Anchor::type( 'how to help stray dogs in your own street today', $this->ctx() ) );
		$this->assertSame( 'partial', AW_Anchor::type( 'how to help stray dogs today', $this->ctx() ), 'six words is not long' );
		$this->assertSame( 'long_tail', AW_Anchor::type( 'how to help stray dogs today', $this->ctx( array( 'long_words' => 5 ) ) ) );
	}

	public function testHindiAnchorsAreSortedByTheirWords(): void {
		$ctx = $this->ctx( array( 'keywords' => array( 'आवारा कुत्ते' ) ) );
		$this->assertSame( 'exact', AW_Anchor::type( 'आवारा कुत्ते', $ctx ) );
		$this->assertSame( 'partial', AW_Anchor::type( 'आवारा कुत्ते और गाय', $ctx ) );
	}

	public function testAnEmptyAnchorIsOther(): void {
		$this->assertSame( 'other', AW_Anchor::type( '  ', $this->ctx() ) );
	}

	public function testMixGivesSharesAndWarnsOnlyFromTheMinimumNumberOfLinks(): void {
		$m = AW_Anchor::mix(
			array(
				'exact'   => 2,
				'partial' => 1,
			)
		);
		$this->assertSame( 3, $m['total'] );
		$this->assertSame( array(), $m['warn'], 'three links say nothing' );
		$m = AW_Anchor::mix(
			array(
				'exact'   => 2,
				'partial' => 3,
			)
		);
		$this->assertSame( 40.0, $m['pct']['exact'] );
		$this->assertSame( array( 'exact' ), $m['warn'] );
	}

	public function testMixWarnsWhenAnyOtherTypeIsOverItsLimit(): void {
		$m = AW_Anchor::mix(
			array(
				'exact'   => 1,
				'generic' => 8,
				'partial' => 1,
			)
		);
		$this->assertSame( array( 'generic' ), $m['warn'] );
		$m = AW_Anchor::mix(
			array(
				'exact'   => 1,
				'generic' => 8,
				'partial' => 1,
			),
			array( 'type_max' => 90 )
		);
		$this->assertSame( array(), $m['warn'] );
	}

	public function testRoomSaysWhetherOneMoreLinkOfATypeStaysInsideTheLimit(): void {
		$counts = array(
			'exact'   => 1,
			'partial' => 2,
			'generic' => 2,
		); // 20% exact
		$this->assertFalse( AW_Anchor::room( $counts, 'exact' ), 'a second exact would be 33%' );
		$this->assertTrue( AW_Anchor::room( $counts, 'partial' ) );
		$this->assertTrue( AW_Anchor::room( array( 'exact' => 1 ), 'exact' ), 'under the minimum nothing is refused' );
	}
}
