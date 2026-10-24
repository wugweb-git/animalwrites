<?php
/**
 * Deploy check (owner, 24 Oct 2026): the site's copy of the workers registry (plugins/aw-sourcing/includes/
 * workers-registry.php, AW_WORKERS) must equal docs/workers.json on the workers repository's main branch, so Next due,
 * late limits and Run now never drift from the workflows. Both repositories are public: read raw, no token.
 * When a change touches the registry in both repositories, merge the workers PR first (the site only displays it), then
 * ship the site; raw.githubusercontent.com may serve the old file for up to 5 minutes.
 * Usage: php tools/ci/workers_registry_check.php [path-or-url-of-workers.json]
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/plugins/aw-sourcing/includes/workers-registry.php';

$src  = $argv[1] ?? 'https://raw.githubusercontent.com/wugweb-git/animal-writes-workers/main/docs/workers.json';
$raw  = false;
for ( $i = 0; $i < 3 && false === $raw; $i++ ) { // raw.githubusercontent.com: retry a blip before failing the deploy
	$raw = @file_get_contents( $src );
	if ( false === $raw ) {
		sleep( 5 );
	}
}
$json = is_string( $raw ) ? json_decode( $raw, true ) : null;
if ( ! is_array( $json ) || empty( $json['workers'] ) ) {
	fwrite( STDERR, "Could not read the workers registry from $src\n" );
	exit( 1 );
}
$want = $json['workers'];
$have = AW_WORKERS;
$diff = array();
foreach ( array_unique( array_merge( array_keys( $want ), array_keys( $have ) ) ) as $file ) {
	if ( ! isset( $have[ $file ] ) ) {
		$diff[] = "$file: in docs/workers.json, missing from AW_WORKERS";
		continue;
	}
	if ( ! isset( $want[ $file ] ) ) {
		$diff[] = "$file: in AW_WORKERS, missing from docs/workers.json";
		continue;
	}
	foreach ( array_unique( array_merge( array_keys( $want[ $file ] ), array_keys( $have[ $file ] ) ) ) as $k ) {
		$a = $want[ $file ][ $k ] ?? null;
		$b = $have[ $file ][ $k ] ?? null;
		if ( $a !== $b ) {
			$diff[] = sprintf( '%s %s: workers repo %s, site %s', $file, $k, json_encode( $a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), json_encode( $b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		}
	}
}
if ( $diff ) {
	echo "The site's workers registry differs from docs/workers.json in animal-writes-workers. Copy it across (plugins/aw-sourcing/includes/workers-registry.php):\n- " . implode( "\n- ", $diff ) . "\n";
	exit( 1 );
}
echo 'Workers registry: ' . count( $have ) . " workflows, same as animal-writes-workers docs/workers.json\n";
