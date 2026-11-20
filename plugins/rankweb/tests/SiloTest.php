<?php
/**
 * PHPUnit tests for the silo rules (includes/class-aw-silo.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/SiloTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this)
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // the rules are pure PHP; the constant only satisfies the direct-access guard
}

require_once dirname( __DIR__ ) . '/includes/class-aw-silo.php';
require_once dirname( __DIR__ ) . '/includes/class-aw-link-audit.php';

/** The silo rules. */
final class SiloTest extends PHPUnit\Framework\TestCase {

	public function testVirtualSiblingsLinkToTheHubAndTheOthersStartingAfterThemselves(): void {
		$this->assertSame( array( 1, 12, 13, 10 ), AW_Silo::links( 'virtual', 1, array( 10, 11, 12, 13 ), 11, 4 ) );
		$this->assertSame( array( 1, 11, 12, 13 ), AW_Silo::links( 'virtual', 1, array( 10, 11, 12, 13 ), 10, 4 ) );
	}

	public function testVirtualLinksAreCappedAndTheHubLinksToItsItems(): void {
		$this->assertSame( array( 1, 11, 12 ), AW_Silo::links( 'virtual', 1, array( 10, 11, 12, 13 ), 10, 3 ) );
		$this->assertSame( array( 10, 11 ), AW_Silo::links( 'virtual', 1, array( 10, 11, 12, 13 ), 1, 2 ) );
	}

	public function testCircleLinksToTheNextAndBackToTheHub(): void {
		$items = array( 10, 11, 12 );
		$this->assertSame( array( 10 ), AW_Silo::links( 'circle', 1, $items, 1 ), 'the hub starts the circle' );
		$this->assertSame( array( 11, 1 ), AW_Silo::links( 'circle', 1, $items, 10 ) );
		$this->assertSame( array( 1 ), AW_Silo::links( 'circle', 1, $items, 12 ), 'the last one has no next' );
	}

	public function testAnItemOutsideTheSiloGetsNoSiloLinks(): void {
		$this->assertSame( array(), AW_Silo::links( 'virtual', 1, array( 10 ), 99 ) );
		$this->assertSame( array(), AW_Silo::links( 'circle', 1, array( 10 ), 99 ) );
	}

	public function testAnEmptySiloLinksNowhereFromTheHub(): void {
		$this->assertSame( array(), AW_Silo::links( 'circle', 1, array(), 1 ) );
		$this->assertSame( array(), AW_Silo::links( 'virtual', 1, array(), 1 ) );
	}

	public function testRepeatedItemsCountOnce(): void {
		$this->assertSame( array( 1, 11 ), AW_Silo::links( 'virtual', 1, array( 10, 11, 11, 10 ), 10, 5 ) );
	}

	private function map(): array {
		return array(
			1  => array(
				'silo' => 1,
				'hub'  => true,
			),
			10 => array(
				'silo' => 1,
				'hub'  => false,
			),
			2  => array(
				'silo' => 2,
				'hub'  => true,
			),
			20 => array(
				'silo' => 2,
				'hub'  => false,
			),
		);
	}

	public function testAnItemMayLinkInsideItsSiloAndToAnotherSilosHubOnly(): void {
		$map = $this->map();
		$this->assertTrue( AW_Silo::may_link( $map, 10, 1 ) );
		$this->assertTrue( AW_Silo::may_link( $map, 10, 2 ), 'another silo\'s hub' );
		$this->assertFalse( AW_Silo::may_link( $map, 10, 20 ), 'another silo\'s supporting item' );
		$this->assertFalse( AW_Silo::may_link( $map, 1, 20 ), 'a hub too' );
	}

	public function testItemsOutsideEverySiloAreNotRestricted(): void {
		$map = $this->map();
		$this->assertTrue( AW_Silo::may_link( $map, 99, 20 ) );
		$this->assertTrue( AW_Silo::may_link( $map, 10, 99 ) );
	}

	public function testDepthCountsPathFoldersAfterTheSitePath(): void {
		$this->assertSame( 3, AW_Silo::depth( 'https://example.org/library/guides/stray-dogs/', 'https://example.org' ) );
		$this->assertSame( 1, AW_Silo::depth( 'https://example.org/sub/page/', 'https://example.org/sub' ) );
		$this->assertSame( 0, AW_Silo::depth( 'https://example.org/', 'https://example.org' ) );
	}

	public function testCheckFlagsFewItemsAndDeepAddresses(): void {
		$urls = array(
			1  => 'https://example.org/a/',
			10 => 'https://example.org/a/b/c/d/',
			11 => 'https://example.org/a/b/',
		);
		$c    = AW_Silo::check( $urls, 'https://example.org', 5, 3 );
		$this->assertSame( 3, $c['few'] );
		$this->assertSame( array( 10 ), $c['deep'] );
		$urls += array(
			12 => 'https://example.org/a/e/',
			13 => 'https://example.org/a/f/',
		);
		$this->assertSame( 0, AW_Silo::check( $urls, 'https://example.org', 5, 3 )['few'], 'five is enough' );
	}

	public function testTheAuditFlagsALinkIntoAnotherSilosSupportingItem(): void {
		$items = array();
		foreach ( array( 1, 10, 2, 20 ) as $id ) {
			$items[ $id ] = array(
				'url'       => 'https://example.org/p' . $id . '/',
				'canonical' => '',
			);
		}
		$edges = array(
			array(
				'source' => 10,
				'target' => 20,
				'url'    => 'https://example.org/p20/',
				'rel'    => '',
				'status' => '',
			),
			array(
				'source' => 10,
				'target' => 2,
				'url'    => 'https://example.org/p2/',
				'rel'    => '',
				'status' => '',
			),
			array(
				'source' => 10,
				'target' => 1,
				'url'    => 'https://example.org/p1/',
				'rel'    => '',
				'status' => '',
			),
		);
		$r     = AW_Link_Audit::analyze( $items, $edges, array( 1, 2 ), array( 'silo' => $this->map() ) );
		$this->assertTrue( in_array( 'silo', $r[10]['flags'], true ) );
		$this->assertSame( array( 'https://example.org/p20/' ), $r[10]['detail']['silo'], 'only the supporting item, not the hubs' );
		$this->assertFalse( in_array( 'silo', $r[1]['flags'], true ) );
	}
}
