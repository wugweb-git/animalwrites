<?php
/**
 * PHPUnit tests for the CSV format and find-and-replace (includes/class-aw-bulk.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/BulkTest.php
 * Without PHPUnit: php plugins/aw-seo-extras/tests/phpunit-lite.php (the Deploy check runs this)
 *
 * @package aw-seo-extras
 */

// phpcs:disable WordPress.Files.FileName, WordPress.NamingConventions.ValidFunctionName, Squiz.Commenting.FunctionComment.Missing -- PHPUnit naming.

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // pure PHP; the constant only satisfies the direct-access guard
}

require_once dirname( __DIR__ ) . '/includes/class-aw-bulk.php';

/** CSV and find-and-replace. */
final class BulkTest extends PHPUnit\Framework\TestCase {

	public function testCsvRoundTripKeepsValues(): void {
		$rows = array(
			array(
				'id'            => '12',
				'title'         => 'Stray dogs',
				'description'   => 'A, with a comma',
				'focus_keyword' => 'stray dogs',
				'link_keywords' => 'stray dogs|street dogs',
				'canonical'     => '',
			),
			array(
				'id'            => '13',
				'title'         => 'Line\nbreak',
				'description'   => 'He said "hi"',
				'focus_keyword' => '',
				'link_keywords' => '',
				'canonical'     => 'https://example.org/x/',
			),
		);
		$csv  = AW_Bulk::to_csv( $rows );
		$this->assertTrue( str_starts_with( $csv, 'id,title,description,focus_keyword,link_keywords,canonical' ) );
		$back = AW_Bulk::from_csv( $csv );
		$this->assertSame( '', $back['error'] );
		$this->assertCount( 2, $back['rows'] );
		$this->assertSame( 'A, with a comma', $back['rows'][0]['description'] );
		$this->assertSame( 'He said "hi"', $back['rows'][1]['description'] );
		$this->assertSame( 'stray dogs|street dogs', $back['rows'][0]['link_keywords'] );
	}

	public function testFromCsvDropsUnknownColumnsAndKeepsKnownOnes(): void {
		$csv  = "id,title,colour,focus_keyword\n5,Hello,red,dogs\n";
		$back = AW_Bulk::from_csv( $csv );
		$this->assertSame( '', $back['error'] );
		$this->assertSame(
			array(
				'id'            => '5',
				'title'         => 'Hello',
				'focus_keyword' => 'dogs',
			),
			$back['rows'][0]
		);
	}

	public function testFromCsvNeedsAnIdColumn(): void {
		$back = AW_Bulk::from_csv( "title,description\nx,y\n" );
		$this->assertNotSame( '', $back['error'] );
		$this->assertCount( 0, $back['rows'] );
	}

	public function testFromCsvStripsABomAndSkipsBlankLinesAndRowsWithNoId(): void {
		$csv  = "\xEF\xBB\xBFid,title\n7,Seven\n\n,NoId\n8,Eight\n";
		$back = AW_Bulk::from_csv( $csv );
		$this->assertSame( '', $back['error'] );
		$this->assertCount( 2, $back['rows'] );
		$this->assertSame( '7', $back['rows'][0]['id'] );
		$this->assertSame( '8', $back['rows'][1]['id'] );
	}

	public function testFromCsvHandlesAQuotedFieldWithANewline(): void {
		$csv  = "id,title,description\n9,\"Two\nlines\",plain\n";
		$back = AW_Bulk::from_csv( $csv );
		$this->assertSame( "Two\nlines", $back['rows'][0]['title'] );
		$this->assertSame( 'plain', $back['rows'][0]['description'] );
	}

	public function testEmptyCsvIsAnError(): void {
		$this->assertNotSame( '', AW_Bulk::from_csv( '   ' )['error'] );
	}

	public function testPlainReplaceCountsAndReplaces(): void {
		$r = AW_Bulk::replace( 'Dog dog DOG', 'dog', 'cat' );
		$this->assertSame( 'Dog cat DOG', $r['text'] );
		$this->assertSame( 1, $r['count'] );
	}

	public function testPlainReplaceCaseInsensitive(): void {
		$r = AW_Bulk::replace( 'Dog dog DOG', 'dog', 'cat', false, true );
		$this->assertSame( 'cat cat cat', $r['text'] );
		$this->assertSame( 3, $r['count'] );
	}

	public function testRegexReplaceWithGroups(): void {
		$r = AW_Bulk::replace( 'IMG_1234.jpg', '^IMG_(\d+)', 'photo-$1', true );
		$this->assertSame( 'photo-1234.jpg', $r['text'] );
		$this->assertSame( 1, $r['count'] );
	}

	public function testRegexReplaceCaseInsensitiveAndUnicode(): void {
		$r = AW_Bulk::replace( 'CAT and Cat', 'cat', 'dog', true, true );
		$this->assertSame( 'dog and dog', $r['text'] );
		$this->assertSame( 2, $r['count'] );
	}

	public function testABadRegexReturnsAnErrorNotAFatal(): void {
		$r = AW_Bulk::replace( 'x', '(', 'y', true );
		$this->assertNotSame( '', $r['error'] );
		$this->assertSame( 'x', $r['text'] );
	}

	public function testAPatternContainingTheDelimiterStillWorks(): void {
		$r = AW_Bulk::replace( 'a~b~c', '~', '-', true );
		$this->assertSame( 'a-b-c', $r['text'] );
		$this->assertSame( 2, $r['count'] );
	}

	public function testAnEmptySearchIsRefused(): void {
		$r = AW_Bulk::replace( 'text', '', 'x' );
		$this->assertNotSame( '', $r['error'] );
		$this->assertSame( 0, $r['count'] );
	}
}
