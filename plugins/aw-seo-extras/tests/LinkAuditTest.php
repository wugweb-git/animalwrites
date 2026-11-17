<?php
/**
 * PHPUnit tests for the link audit (includes/class-aw-link-audit.php): a small sample site in, findings out. No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/LinkAuditTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this)
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // the audit is pure PHP; the constant only satisfies its direct-access guard
}

require_once dirname( __DIR__ ) . '/includes/class-aw-link-audit.php';

/** The link audit. */
final class LinkAuditTest extends PHPUnit\Framework\TestCase {

	/** Items 1 to 6, none with a canonical elsewhere. */
	private function items( array $more = array() ): array {
		$items = array();
		foreach ( range( 1, 6 ) as $id ) {
			$items[ $id ] = array(
				'url'       => 'https://example.org/p' . $id . '/',
				'canonical' => '',
			);
		}
		return $more + $items;
	}

	private function link( int $from, int $to, array $extra = array() ): array {
		return $extra + array(
			'source' => $from,
			'target' => $to,
			'url'    => 'https://example.org/p' . $to . '/',
			'rel'    => '',
			'status' => '',
		);
	}

	public function testClicksFromHomeAreFoundBreadthFirst(): void {
		$edges = array( $this->link( 1, 2 ), $this->link( 2, 3 ), $this->link( 3, 4 ), $this->link( 1, 4 ) );
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ) );
		$this->assertSame( 1, $r[1]['depth'] );
		$this->assertSame( 2, $r[2]['depth'] );
		$this->assertSame( 3, $r[3]['depth'] );
		$this->assertSame( 2, $r[4]['depth'], 'the short way wins' );
	}

	public function testAnItemMoreClicksAwayThanTheLimitIsDeep(): void {
		$edges = array( $this->link( 1, 2 ), $this->link( 2, 3 ), $this->link( 3, 4 ), $this->link( 4, 5 ) );
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ), array( 'max_depth' => 3 ) );
		$this->assertTrue( in_array( 'deep', $r[4]['flags'], true ) );
		$this->assertTrue( in_array( 'deep', $r[5]['flags'], true ) );
		$this->assertFalse( in_array( 'deep', $r[3]['flags'], true ) );
	}

	public function testAnItemNothingLinksToIsAnOrphanNotAlsoDeep(): void {
		$r = AW_Link_Audit::analyze( $this->items(), array( $this->link( 1, 2 ) ), array( 1 ) );
		$this->assertSame( array( 'orphan' ), $r[6]['flags'] );
		$this->assertSame( null, $r[6]['depth'] );
		$this->assertSame( 0, $r[6]['in'] );
	}

	public function testALinkFromAMenuCountsAsAnInLink(): void {
		$r = AW_Link_Audit::analyze( $this->items(), array(), array( 5 ) );
		$this->assertSame( 1, $r[5]['in'] );
		$this->assertFalse( in_array( 'orphan', $r[5]['flags'], true ) );
	}

	public function testAnIslandWithLinksButNoWayInIsDeep(): void {
		$edges = array( $this->link( 5, 6 ), $this->link( 6, 5 ), $this->link( 1, 2 ) );
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ) );
		$this->assertSame( array( 'deep' ), $r[5]['flags'] );
		$this->assertSame( null, $r[5]['depth'] );
	}

	public function testALinkToItselfIsNotALinkIn(): void {
		$r = AW_Link_Audit::analyze( $this->items(), array( $this->link( 3, 3 ) ), array( 1 ) );
		$this->assertSame( 0, $r[3]['in'] );
		$this->assertSame( 1, $r[3]['out'] );
		$this->assertTrue( in_array( 'orphan', $r[3]['flags'], true ) );
	}

	public function testTooManyLinksOutIsFlaggedAboveTheLimit(): void {
		$edges = array();
		foreach ( range( 1, 4 ) as $i ) {
			$edges[] = $this->link( 1, 2, array( 'url' => 'https://example.org/p2/#' . $i ) );
		}
		$r = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ), array( 'max_links' => 3 ) );
		$this->assertTrue( in_array( 'too_many', $r[1]['flags'], true ) );
		$r = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ), array( 'max_links' => 4 ) );
		$this->assertFalse( in_array( 'too_many', $r[1]['flags'], true ), 'at the limit is allowed' );
	}

	public function testBrokenAndRedirectedLinksAreFlaggedOnTheItemThatHasThem(): void {
		$edges = array(
			$this->link(
				1,
				0,
				array(
					'url'    => 'https://example.org/gone/',
					'status' => 'broken',
				)
			),
			$this->link(
				1,
				2,
				array(
					'url'    => 'https://example.org/old-p2/',
					'status' => 'redirect',
				)
			),
			$this->link( 1, 3, array( 'status' => 'ok' ) ),
		);
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ) );
		$this->assertSame( array( 'broken', 'redirect' ), $r[1]['flags'] );
		$this->assertSame( array( 'https://example.org/gone/' ), $r[1]['detail']['broken'] );
		$this->assertSame( array(), $r[2]['flags'], 'the target is not flagged' );
	}

	public function testNofollowIsFoundAmongOtherRelValues(): void {
		$edges = array( $this->link( 1, 2, array( 'rel' => 'noopener NoFollow' ) ), $this->link( 1, 3, array( 'rel' => 'noopener' ) ) );
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ) );
		$this->assertTrue( in_array( 'nofollow', $r[1]['flags'], true ) );
		$this->assertSame( array( 'https://example.org/p2/' ), $r[1]['detail']['nofollow'] );
	}

	public function testALinkToAnItemWhoseCanonicalPointsElsewhereIsFlagged(): void {
		$items                 = $this->items();
		$items[2]['canonical'] = 'https://example.org/other/';
		$r                     = AW_Link_Audit::analyze( $items, array( $this->link( 1, 2 ) ), array( 1 ) );
		$this->assertTrue( in_array( 'canonical', $r[1]['flags'], true ) );
	}

	public function testAddressesOnTheDoNotLinkListAreFlaggedIgnoringCase(): void {
		$edges = array( $this->link( 1, 0, array( 'url' => 'https://example.org/Thank-You/' ) ), $this->link( 1, 2 ) );
		$r     = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ), array( 'avoid' => array( 'thank-you', 'download' ) ) );
		$this->assertSame( array( 'avoided' ), $r[1]['flags'] );
		$this->assertSame( array( 'https://example.org/Thank-You/' ), $r[1]['detail']['avoided'] );
	}

	public function testLinksFromAnItemOutsideTheAuditAreIgnored(): void {
		$r = AW_Link_Audit::analyze( $this->items(), array( $this->link( 99, 2 ) ), array( 1 ) );
		$this->assertSame( 0, $r[2]['in'] );
	}

	public function testDetailKeepsFiveAddressesAtMostAndNoRepeats(): void {
		$edges = array();
		foreach ( range( 1, 9 ) as $i ) {
			$edges[] = $this->link(
				1,
				0,
				array(
					'url'    => 'https://example.org/x' . $i . '/',
					'status' => 'broken',
				)
			);
		}
		$edges[] = $this->link(
			1,
			0,
			array(
				'url'    => 'https://example.org/x1/',
				'status' => 'broken',
			)
		);
		$r       = AW_Link_Audit::analyze( $this->items(), $edges, array( 1 ) );
		$this->assertCount( 5, $r[1]['detail']['broken'] );
		$this->assertSame( 10, $r[1]['out'] );
	}

	public function testSummaryCountsItemsAndFindings(): void {
		$r = AW_Link_Audit::analyze( $this->items(), array( $this->link( 1, 2 ), $this->link( 2, 3 ) ), array( 1 ) );
		$s = AW_Link_Audit::summary( $r );
		$this->assertSame( 6, $s['items'] );
		$this->assertSame( 3, $s['by']['orphan'], 'items 4, 5 and 6' );
		$this->assertSame( 3, $s['flagged'] );
	}
}
