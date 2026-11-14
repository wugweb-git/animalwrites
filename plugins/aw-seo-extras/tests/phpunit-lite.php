<?php
/**
 * Runs RankWeb's PHPUnit test classes without PHPUnit (the Deploy check has PHP but no Composer packages; owner rule:
 * no local WordPress). A small stand-in for PHPUnit\Framework\TestCase with the assertions the tests use; with real
 * PHPUnit installed, `phpunit plugins/aw-seo-extras/tests/` runs the same files.
 *
 * Usage: php plugins/aw-seo-extras/tests/phpunit-lite.php
 *
 * @package aw-seo-extras
 */

// phpcs:disable -- a command-line test runner.

namespace PHPUnit\Framework;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! class_exists( TestCase::class ) ) {
	/** Stand-in for PHPUnit's TestCase. */
	abstract class TestCase {
		public static int $assertions = 0;

		private static function fail_with( string $msg ): void {
			throw new \RuntimeException( $msg );
		}

		public function assertSame( $expected, $actual, string $msg = '' ): void {
			++self::$assertions;
			if ( $expected !== $actual ) {
				self::fail_with( ( $msg ? $msg . "\n" : '' ) . "expected: " . var_export( $expected, true ) . "\n  actual: " . var_export( $actual, true ) );
			}
		}

		public function assertCount( int $n, $list, string $msg = '' ): void {
			$this->assertSame( $n, count( $list ), $msg ?: 'count' );
		}

		public function assertTrue( $v, string $msg = '' ): void {
			$this->assertSame( true, $v, $msg );
		}

		public function assertFalse( $v, string $msg = '' ): void {
			$this->assertSame( false, $v, $msg );
		}
	}
}

$fails = 0;
$count = 0;
foreach ( glob( __DIR__ . '/*Test.php' ) as $file ) {
	$before = get_declared_classes();
	require_once $file;
	foreach ( array_diff( get_declared_classes(), $before ) as $class ) {
		if ( ! is_subclass_of( $class, TestCase::class ) ) {
			continue;
		}
		foreach ( get_class_methods( $class ) as $method ) {
			if ( 0 !== strpos( $method, 'test' ) ) {
				continue;
			}
			++$count;
			try {
				( new $class() )->$method();
			} catch ( \Throwable $e ) {
				++$fails;
				echo "FAIL {$class}::{$method}\n  " . $e->getMessage() . "\n";
			}
		}
	}
}
echo ( $fails ? 'FAILED' : 'OK' ) . " ({$count} tests, " . TestCase::$assertions . " assertions, {$fails} failed)\n";
exit( $fails ? 1 : 0 );
