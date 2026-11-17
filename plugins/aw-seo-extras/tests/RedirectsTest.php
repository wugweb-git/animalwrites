<?php
/**
 * PHPUnit tests for the redirect CSV, validation and the .htaccess block (includes/class-aw-redirects.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/RedirectsTest.php
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

require_once dirname( __DIR__ ) . '/includes/class-aw-redirects.php';

/** Redirect CSV, validation and the .htaccess block. */
final class RedirectsTest extends PHPUnit\Framework\TestCase {

	public function testCsvRoundTrip(): void {
		$rows = array(
			array(
				'source'     => '/old/',
				'target'     => '/new/',
				'code'       => '301',
				'comparison' => 'exact',
			),
			array(
				'source'     => '^campaign/(.+)$',
				'target'     => '/campaigns/$1/',
				'code'       => '301',
				'comparison' => 'regex',
			),
		);
		$csv  = AW_Redirects::to_csv( $rows );
		$this->assertTrue( str_starts_with( $csv, 'source,target,code,comparison' ) );
		$back = AW_Redirects::from_csv( $csv );
		$this->assertSame( '', $back['error'] );
		$this->assertCount( 2, $back['rows'] );
		$this->assertSame( '/campaigns/$1/', $back['rows'][1]['target'] );
	}

	public function testFromCsvNeedsASourceColumnAndKeepsKnownColumns(): void {
		$this->assertNotSame( '', AW_Redirects::from_csv( "target,code\n/x/,301\n" )['error'] );
		$back = AW_Redirects::from_csv( "source,target,note\n/a/,/b/,hello\n" );
		$this->assertSame(
			array(
				'source' => '/a/',
				'target' => '/b/',
			),
			$back['rows'][0]
		);
	}

	public function testValidateDefaultsCodeAndComparison(): void {
		$v = AW_Redirects::validate(
			array(
				'source' => '/a/',
				'target' => '/b/',
			)
		);
		$this->assertSame( '', $v['error'] );
		$this->assertSame( 301, $v['code'] );
		$this->assertSame( 'exact', $v['comparison'] );
	}

	public function testValidateRejectsAnUnknownCodeAndAMissingTarget(): void {
		$this->assertNotSame(
			'',
			AW_Redirects::validate(
				array(
					'source' => '/a/',
					'target' => '/b/',
					'code'   => '404',
				)
			)['error']
		);
		$this->assertNotSame(
			'',
			AW_Redirects::validate(
				array(
					'source' => '/a/',
					'code'   => '302',
				)
			)['error']
		);
	}

	public function testGoneCodesNeedNoTarget(): void {
		$v = AW_Redirects::validate(
			array(
				'source' => '/gone/',
				'code'   => '410',
			)
		);
		$this->assertSame( '', $v['error'] );
		$this->assertSame( 410, $v['code'] );
	}

	public function testValidateRejectsAnEmptySource(): void {
		$this->assertNotSame(
			'',
			AW_Redirects::validate(
				array(
					'source' => '  ',
					'target' => '/b/',
				)
			)['error']
		);
	}

	public function testHtaccessLinesForExactRedirects(): void {
		$lines = AW_Redirects::htaccess_lines(
			array(
				array(
					'source'     => 'old-page',
					'target'     => '/new-page/',
					'code'       => 301,
					'comparison' => 'exact',
				),
				array(
					'source'     => '/temp',
					'target'     => 'https://example.org/t/',
					'code'       => 302,
					'comparison' => 'exact',
				),
			)
		);
		$this->assertSame( 'Redirect 301 /old-page /new-page/', $lines[0] );
		$this->assertSame( 'Redirect 302 /temp https://example.org/t/', $lines[1] );
	}

	public function testHtaccessUsesPlainRedirectForGoneAndSkipsRegex(): void {
		$lines = AW_Redirects::htaccess_lines(
			array(
				array(
					'source'     => '/gone/',
					'target'     => '',
					'code'       => 410,
					'comparison' => 'exact',
				),
				array(
					'source'     => '^campaign/(.+)$',
					'target'     => '/c/$1/',
					'code'       => 301,
					'comparison' => 'regex',
				),
			)
		);
		$this->assertCount( 1, $lines, 'the regex rule is left out' );
		$this->assertSame( 'Redirect 410 /gone', $lines[0], 'a gone rule is a plain Redirect, never a regex' );
	}

	public function testHtaccessEscapesSpacesAndStripsNewlines(): void {
		$lines = AW_Redirects::htaccess_lines(
			array(
				array(
					'source'     => "/a b\n",
					'target'     => '/c d/',
					'code'       => 301,
					'comparison' => 'exact',
				),
			)
		);
		$this->assertSame( 'Redirect 301 /a%20b /c%20d/', $lines[0] );
	}
}
