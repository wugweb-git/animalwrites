<?php
/**
 * Database half of rename_classes.py: the same aw-* → kit-* names in saved content (Pages, synced patterns, campaigns).
 * Each change is saved as a normal update, so the previous text stays in the item's revisions.
 *
 *     wp eval-file rename_classes_db.php names.txt
 */
$names = array_filter( array_map( 'trim', file( $args[0] ) ), fn( $n ) => 0 === strpos( $n, 'aw-' ) );
usort( $names, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
$pat = '/(?<![a-zA-Z0-9_-])(' . implode( '|', array_map( fn( $n ) => preg_quote( $n, '/' ), $names ) ) . ')(?=--|[^a-zA-Z0-9_-]|$)/';
global $wpdb;
$rows = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ('inherit','auto-draft') AND post_content LIKE '%aw-%'" );
foreach ( $rows as $r ) {
	$c = get_post_field( 'post_content', $r->ID, 'raw' );
	$n = preg_replace_callback( $pat, fn( $m ) => 'kit-' . substr( $m[1], 3 ), $c );
	do {
		$p = $n;
		$n = preg_replace( '/(?<![a-zA-Z0-9_-])(kit-[a-z0-9-]+) \1(?![a-zA-Z0-9_-])/', '$1', $n );
	} while ( $p !== $n );
	if ( $n !== $c ) {
		wp_update_post( array( 'ID' => $r->ID, 'post_content' => wp_slash( $n ) ) );
		echo $r->ID, ' ', get_post_type( $r->ID ), "\n";
	}
}
