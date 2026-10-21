<?php
/**
 * The site's Fluent Forms forms, versioned in /forms/*.json (owner, 21 Oct 2026: forms are built in Fluent Forms, not
 * generated in code). Each file is Fluent Forms' own export format (Fluent Forms → Tools → Export) for one form.
 *
 *   wp eval-file tools/forms/forms.php export        # every site form from the database into /forms/<id>-<slug>.json
 *   wp eval-file tools/forms/forms.php dry           # what an import would change; changes nothing
 *   wp eval-file tools/forms/forms.php import        # each /forms/*.json onto the form with the same ID
 *   wp eval-file tools/forms/forms.php import 4      # one form
 *
 * Import keeps the form's ID, so its entries, its page and its FluentCRM link stay where they are (Fluent Forms' own
 * importer would make a new form). It writes the form's fields and settings and the metas in the file (form settings,
 * the FluentCRM link, the email settings); metas not in the file (view counts and the like) are left alone. Before
 * anything is written the form as it was is saved to ~/backups/forms-before-<date>/<id>.json.
 *
 * @package animal-writes
 */

global $wpdb;

$mode  = $args[0] ?? 'dry';
$only  = isset( $args[1] ) ? (int) $args[1] : 0;
$dir   = dirname( __DIR__, 2 ) . '/forms';
$ids   = array_filter( array_merge( array_values( (array) get_option( 'aw_ff_forms', array() ) ), array( (int) get_option( 'aw_nl_ff_form' ) ) ) );
$skip  = array( '_total_views' );
$forms = $wpdb->prefix . 'fluentform_forms';
$metas = $wpdb->prefix . 'fluentform_form_meta';

/** One form in Fluent Forms' export shape. */
$export_one = function ( $id ) use ( $wpdb, $forms, $metas, $skip ) {
	$f = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$forms} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
	if ( ! $f ) {
		return null;
	}
	$f['form_fields'] = json_decode( (string) $f['form_fields'], true );
	$f['metas']       = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, value FROM {$metas} WHERE form_id = %d ORDER BY meta_key", $id ), ARRAY_A ) as $m ) { // phpcs:ignore
		if ( ! in_array( $m['meta_key'], $skip, true ) ) {
			$f['metas'][] = array( 'meta_key' => $m['meta_key'], 'value' => $m['value'] );
		}
	}
	unset( $f['created_at'], $f['updated_at'] );
	return $f;
};

if ( 'export' === $mode ) {
	wp_mkdir_p( $dir );
	foreach ( $ids as $id ) {
		if ( $only && $only !== (int) $id ) {
			continue;
		}
		$f = $export_one( (int) $id );
		if ( ! $f ) {
			WP_CLI::warning( "Form {$id} not found" );
			continue;
		}
		$file = $dir . '/' . (int) $id . '-' . sanitize_title( $f['title'] ) . '.json';
		file_put_contents( $file, wp_json_encode( array( $f ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); // phpcs:ignore
		WP_CLI::log( 'Exported ' . basename( $file ) );
	}
	return;
}

$files = glob( $dir . '/*.json' );
$back  = getenv( 'HOME' ) . '/backups/forms-before-' . gmdate( 'Y-m-d-His' );
foreach ( $files as $file ) {
	$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore
	$f    = is_array( $data ) ? ( $data[0] ?? null ) : null;
	if ( ! $f || empty( $f['id'] ) || empty( $f['form_fields'] ) ) {
		WP_CLI::warning( basename( $file ) . ': not a Fluent Forms export' );
		continue;
	}
	$id = (int) $f['id'];
	if ( $only && $only !== $id ) {
		continue;
	}
	$was    = $export_one( $id );
	$fields = wp_json_encode( $f['form_fields'] );
	$same   = $was && wp_json_encode( $was['form_fields'] ) === $fields;
	$keys   = wp_list_pluck( (array) $f['metas'], 'meta_key' );
	WP_CLI::log( sprintf( '%s form %d "%s": fields %s; metas %s', $was ? 'Update' : 'New', $id, $f['title'], $same ? 'unchanged' : 'changed', implode( ', ', $keys ) ) );
	if ( 'import' !== $mode ) {
		continue;
	}
	if ( $was ) {
		wp_mkdir_p( $back );
		file_put_contents( $back . '/' . $id . '.json', wp_json_encode( array( $was ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore
	}
	$row = array(
		'title'               => (string) $f['title'],
		'status'              => (string) ( $f['status'] ?? 'published' ),
		'appearance_settings' => $f['appearance_settings'] ?? null,
		'form_fields'         => $fields,
		'has_payment'         => (int) ( $f['has_payment'] ?? 0 ),
		'type'                => (string) ( $f['type'] ?? 'form' ),
		'conditions'          => $f['conditions'] ?? null,
		'updated_at'          => current_time( 'mysql' ),
	);
	if ( $was ) {
		$wpdb->update( $forms, $row, array( 'id' => $id ) ); // phpcs:ignore
	} else {
		$wpdb->insert( $forms, $row + array( 'id' => $id, 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ) ); // phpcs:ignore
	}
	foreach ( (array) $f['metas'] as $m ) {
		$wpdb->delete( $metas, array( 'form_id' => $id, 'meta_key' => $m['meta_key'] ) ); // phpcs:ignore
		$wpdb->insert( $metas, array( 'form_id' => $id, 'meta_key' => $m['meta_key'], 'value' => is_string( $m['value'] ) ? $m['value'] : wp_json_encode( $m['value'] ) ) ); // phpcs:ignore
	}
}
if ( 'import' === $mode ) {
	wp_cache_flush();
	WP_CLI::success( 'Imported. The forms as they were: ' . $back );
}
