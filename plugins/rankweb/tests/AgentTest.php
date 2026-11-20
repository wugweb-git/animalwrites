<?php
/**
 * PHPUnit tests for the agent manifest and input handling (includes/class-aw-agent.php). No WordPress.
 *
 * With PHPUnit:    phpunit plugins/aw-seo-extras/tests/AgentTest.php
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

require_once dirname( __DIR__ ) . '/includes/class-aw-agent.php';

/** The agent manifest and input handling. */
final class AgentTest extends PHPUnit\Framework\TestCase {

	public function testEveryAbilityNameIsValidAndNamespaced(): void {
		foreach ( AW_Agent::abilities() as $a ) {
			$this->assertTrue( AW_Agent::valid_name( $a['name'] ), $a['name'] );
			$this->assertTrue( in_array( $a['cap'], AW_Agent::CAPS, true ), $a['cap'] );
		}
	}

	public function testValidNameRejectsBadNames(): void {
		$this->assertFalse( AW_Agent::valid_name( 'getseo' ), 'no namespace' );
		$this->assertFalse( AW_Agent::valid_name( 'rankweb/Get_SEO' ), 'uppercase and underscore' );
		$this->assertFalse( AW_Agent::valid_name( 'rankweb/' ), 'empty name' );
		$this->assertFalse( AW_Agent::valid_name( 'other/get-seo' ), 'wrong namespace' );
		$this->assertTrue( AW_Agent::valid_name( 'rankweb/set-seo' ) );
	}

	public function testReadsAreAlwaysEnabledAndWritesAreOptIn(): void {
		$reads = AW_Agent::enabled( false );
		$this->assertCount( 3, $reads );
		foreach ( $reads as $a ) {
			$this->assertFalse( $a['writes'] );
		}
		$all = AW_Agent::enabled( true );
		$this->assertCount( 6, $all );
	}

	public function testWritableSeoKeepsOnlyKnownKeys(): void {
		$out = AW_Agent::writable_seo(
			array(
				'title'   => '  Hello  ',
				'unknown' => 'x',
				'robots'  => 'noindex',
			)
		);
		$this->assertSame( array( 'title' => 'Hello' ), $out );
	}

	public function testWritableSeoLeavesOutFieldsNotGiven(): void {
		$out = AW_Agent::writable_seo( array( 'focus_keyword' => 'stray dogs' ) );
		$this->assertSame( array( 'focus_keyword' => 'stray dogs' ), $out );
		$this->assertFalse( isset( $out['title'] ) );
	}

	public function testWritableSeoNormalisesLinkKeywordsToAList(): void {
		$out = AW_Agent::writable_seo( array( 'link_keywords' => "stray dogs\nstreet dogs|cattle, cows" ) );
		$this->assertSame( array( 'stray dogs', 'street dogs', 'cattle', 'cows' ), $out['link_keywords'] );
		$out = AW_Agent::writable_seo( array( 'link_keywords' => array( ' a ', '', 'b' ) ) );
		$this->assertSame( array( 'a', 'b' ), $out['link_keywords'] );
	}

	public function testAnEmptyStringFieldIsKeptAsAClear(): void {
		$out = AW_Agent::writable_seo( array( 'description' => '   ' ) );
		$this->assertSame( array( 'description' => '' ), $out, 'given but blank clears the field' );
	}
}
