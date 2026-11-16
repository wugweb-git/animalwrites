<?php
/**
 * RankWeb PRO, phase F: redirect tools (docs/rankweb-pro-audit.md, F). Rank Math keeps CSV import/export, 404-log
 * export and .htaccess sync for Pro. The one redirect list stays Rank Math → Redirections (owner, 10 Oct 2026); this
 * screen reads and writes that list, it does not keep its own.
 *
 * Screen: RankWeb → Redirect tools (manage_options). Tools:
 * - Export the redirects as CSV (source, target, code, comparison).
 * - Import a CSV into Rank Math's list, with a dry run (the default) that writes nothing and reports each row. The code
 *   may be 301, 302, 307, 308, 410 or 451 (410/451 need no target). Adds through aw_rank_math_redirect(); an exact
 *   source already in the list is left alone.
 * - Export Rank Math's 404 log as CSV (reused, not a second monitor).
 * - The .htaccess block: download the Apache directives for the exact redirects, or (only when the setting is on and
 *   the server is Apache or LiteSpeed) write them into the site's .htaccess between RankWeb's markers, a backup first.
 *
 * Module "redirect-tools" (RankWeb → Settings → Modules). No scheduled job. Categories are not built: Rank Math's list
 * has no category field, and the owner's rule keeps redirects in that one list (noted in docs/rankweb-pro-audit.md).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-redirects.php';

const AW_HTACCESS_MARKER = 'RankWeb Redirects';

/** Whether Rank Math's redirections module is on. */
function aw_redirect_ready(): bool {
	return class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'redirections' );
}

/** The active redirects in Rank Math's list as rows (source, target, code, comparison). */
function aw_redirect_rows(): array {
	global $wpdb;
	$table = $wpdb->prefix . 'rank_math_redirections';
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT sources, url_to, header_code FROM %i WHERE status = 'active' ORDER BY id", $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Rank Math's table, an export the admin asked for
	$out   = array();
	foreach ( $rows as $r ) {
		foreach ( (array) maybe_unserialize( (string) $r['sources'] ) as $src ) {
			$out[] = array(
				'source'     => (string) ( $src['pattern'] ?? '' ),
				'target'     => (string) $r['url_to'],
				'code'       => (string) (int) $r['header_code'],
				'comparison' => (string) ( $src['comparison'] ?? 'exact' ),
			);
		}
	}
	return $out;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_SEO_MENU, __( 'Redirect tools', 'aw-seo-extras' ), __( 'Redirect tools', 'aw-seo-extras' ), 'manage_options', 'aw-redirects', 'aw_redirect_tools_screen' );
	}
);

/* ---------- Export redirects ---------- */

add_action(
	'admin_post_aw_redirect_export',
	function () {
		check_admin_referer( 'aw_redirect_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$csv = AW_Redirects::to_csv( aw_redirect_rows() );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="rankweb-redirects-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download built by AW_Redirects::to_csv()
		exit;
	}
);

/* ---------- Import redirects ---------- */

add_action(
	'admin_post_aw_redirect_import',
	function () {
		check_admin_referer( 'aw_redirect_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$dry    = ! isset( $_POST['aw_apply'] );
		$report = array(
			'kind'    => 'import',
			'dry'     => $dry,
			'added'   => 0,
			'lines'   => array(),
			'skipped' => array(),
			'error'   => '',
		);
		if ( ! aw_redirect_ready() ) {
			$report['error'] = __( 'Rank Math\'s Redirections module is off.', 'aw-seo-extras' );
		} elseif ( empty( $_FILES['aw_csv']['tmp_name'] ) || ! is_uploaded_file( sanitize_text_field( wp_unslash( $_FILES['aw_csv']['tmp_name'] ) ) ) ) {
			$report['error'] = __( 'Choose a CSV file.', 'aw-seo-extras' );
		} elseif ( absint( wp_unslash( $_FILES['aw_csv']['size'] ?? 0 ) ) > 5 * MB_IN_BYTES ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- absint() sanitises the size
			$report['error'] = __( 'The file is larger than 5 MB.', 'aw-seo-extras' );
		} else {
			$text   = (string) file_get_contents( sanitize_text_field( wp_unslash( $_FILES['aw_csv']['tmp_name'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- an uploaded CSV in the PHP temp dir
			$parsed = AW_Redirects::from_csv( $text );
			if ( '' !== $parsed['error'] ) {
				$report['error'] = $parsed['error'];
			} else {
				$n = 0;
				foreach ( $parsed['rows'] as $row ) {
					++$n;
					$v = AW_Redirects::validate( $row );
					if ( '' !== $v['error'] ) {
						/* translators: 1: row number, 2: the reason. */
						$report['skipped'][] = sprintf( __( 'Row %1$d: %2$s', 'aw-seo-extras' ), $n, $v['error'] );
						continue;
					}
					$target = in_array( $v['code'], AW_Redirects::GONE, true ) ? '' : aw_redirect_abs( $v['target'] );
					if ( $dry ) {
						++$report['added'];
					} else {
						$pattern = 'regex' === $v['comparison'] ? $v['source'] : trim( $v['source'], '/' );
						$id      = aw_rank_math_redirect( $pattern, $target, $v['comparison'], $v['code'] );
						if ( $id ) {
							++$report['added'];
						} else {
							/* translators: %d: row number. */
							$report['skipped'][] = sprintf( __( 'Row %d: already in the list or could not be saved.', 'aw-seo-extras' ), $n );
							continue;
						}
					}
					if ( count( $report['lines'] ) < 100 ) {
						$report['lines'][] = $v['source'] . ' → ' . ( '' !== $target ? $target : (string) $v['code'] );
					}
				}
			}
		}
		set_transient( 'aw_seo_redirect_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=aw-redirects' ) );
		exit;
	}
);

/** A target made absolute: a path on this site becomes a full URL; a full URL is kept. */
function aw_redirect_abs( string $target ): string {
	$target = trim( $target );
	if ( '' === $target ) {
		return '';
	}
	return preg_match( '~^https?://~i', $target ) ? $target : home_url( '/' . ltrim( $target, '/' ) );
}

/* ---------- 404 log export ---------- */

add_action(
	'admin_post_aw_redirect_404export',
	function () {
		check_admin_referer( 'aw_redirect_404export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'rank_math_404_logs';
		$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-off check for Rank Math's 404 table
		$rows   = $exists === $table ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT uri, times_accessed, accessed, referer, user_agent FROM %i ORDER BY accessed DESC LIMIT 5000', $table ), ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Rank Math's 404 table, an export the admin asked for
		$fh     = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream
		fputcsv( $fh, array( 'uri', 'hits', 'last_seen', 'referer', 'user_agent' ), ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory
		foreach ( $rows as $r ) {
			fputcsv( $fh, array( aw_redirect_csv_safe( (string) $r['uri'] ), (string) (int) $r['times_accessed'], (string) $r['accessed'], aw_redirect_csv_safe( (string) $r['referer'] ), aw_redirect_csv_safe( (string) $r['user_agent'] ) ), ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory
		}
		rewind( $fh );
		$csv = (string) stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="rankweb-404-log-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV download escaped per field above
		exit;
	}
);

/* ---------- .htaccess ---------- */

/** The .htaccess directive lines for the exact redirects in the list. */
function aw_redirect_htaccess_lines(): array {
	$valid = array();
	foreach ( aw_redirect_rows() as $row ) {
		$v = AW_Redirects::validate( $row );
		if ( '' === $v['error'] ) {
			$valid[] = $v;
		}
	}
	return AW_Redirects::htaccess_lines( $valid );
}

/** Whether the server is one whose .htaccess is read (Apache or LiteSpeed). */
function aw_redirect_apache(): bool {
	$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
	return false !== strpos( $server, 'apache' ) || false !== strpos( $server, 'litespeed' );
}

add_action(
	'admin_post_aw_redirect_htaccess_download',
	function () {
		check_admin_referer( 'aw_redirect_htaccess' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$lines = aw_redirect_htaccess_lines();
		$block = '# BEGIN ' . AW_HTACCESS_MARKER . "\n" . implode( "\n", $lines ) . "\n# END " . AW_HTACCESS_MARKER . "\n";
		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="rankweb-htaccess-redirects.txt"' );
		echo $block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a plain-text download; each line was built by AW_Redirects
		exit;
	}
);

add_action(
	'admin_post_aw_redirect_htaccess_write',
	function () {
		check_admin_referer( 'aw_redirect_htaccess' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$remove = isset( $_POST['aw_remove'] );
		$msg    = aw_redirect_htaccess_apply( $remove );
		set_transient(
			'aw_seo_redirect_report_' . get_current_user_id(),
			array(
				'kind'  => 'htaccess',
				'error' => $msg['error'],
				'ok'    => $msg['ok'],
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'admin.php?page=aw-redirects' ) );
		exit;
	}
);

/**
 * Write (or remove) RankWeb's block in the site's .htaccess, a backup first.
 *
 * @param bool $remove Remove the block instead of writing it.
 * @return array{error: string, ok: string}
 */
function aw_redirect_htaccess_apply( bool $remove ): array {
	if ( ! AW_REDIRECT_HTACCESS ) {
		return array(
			'error' => __( 'The .htaccess option is off (RankWeb → Settings → Redirect tools).', 'aw-seo-extras' ),
			'ok'    => '',
		);
	}
	if ( ! aw_redirect_apache() ) {
		return array(
			'error' => __( 'This server does not read .htaccess (not Apache or LiteSpeed).', 'aw-seo-extras' ),
			'ok'    => '',
		);
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$path = get_home_path() . '.htaccess';
	global $wp_filesystem;
	if ( ! WP_Filesystem() || ! $wp_filesystem ) {
		return array(
			'error' => __( 'The .htaccess file could not be opened.', 'aw-seo-extras' ),
			'ok'    => '',
		);
	}
	if ( $wp_filesystem->exists( $path ) ) {
		if ( ! $wp_filesystem->is_writable( $path ) ) {
			return array(
				'error' => __( 'The .htaccess file is not writable.', 'aw-seo-extras' ),
				'ok'    => '',
			);
		}
		$current = $wp_filesystem->get_contents( $path );
		if ( false === $current || ! $wp_filesystem->put_contents( $path . '.rankweb-bak', (string) $current, FS_CHMOD_FILE ) ) {
			return array( 'error' => __( 'A backup of .htaccess could not be written, so nothing was changed.', 'aw-seo-extras' ), 'ok' => '' );
		}
	} elseif ( $remove ) {
		return array(
			'error' => '',
			'ok'    => __( 'There was nothing to remove.', 'aw-seo-extras' ),
		);
	}
	$lines = $remove ? array() : aw_redirect_htaccess_lines();
	if ( ! insert_with_markers( $path, AW_HTACCESS_MARKER, $lines ) ) {
		return array(
			'error' => __( 'The .htaccess file could not be written.', 'aw-seo-extras' ),
			'ok'    => '',
		);
	}
	return array(
		'error' => '',
		/* translators: %d: number of redirects. */
		'ok'    => $remove ? __( 'RankWeb\'s .htaccess block was removed (a backup is at .htaccess.rankweb-bak).', 'aw-seo-extras' ) : sprintf( __( '%d redirects written to .htaccess (a backup is at .htaccess.rankweb-bak).', 'aw-seo-extras' ), count( $lines ) ),
	);
}

/**
 * Neutralise a spreadsheet formula in a CSV cell (the 404 log's referer and user agent are attacker-set headers): a
 * value that begins with =, +, -, @ or a control character is prefixed with a single quote so a spreadsheet treats it
 * as text, not a formula.
 *
 * @param string $value A cell value.
 */
function aw_redirect_csv_safe( string $value ): string {
	return '' !== $value && 1 === preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
}

/* ---------- The screen ---------- */

/** RankWeb → Redirect tools. */
function aw_redirect_tools_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to see this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
	}
	$post = admin_url( 'admin-post.php' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Redirect tools', 'aw-seo-extras' ) . '</h1>';
	echo '<p>' . esc_html__( 'Redirects live in Rank Math → Redirections. These tools read and write that one list.', 'aw-seo-extras' ) . '</p>';
	if ( ! aw_redirect_ready() ) {
		wp_admin_notice( esc_html__( 'Rank Math\'s Redirections module is off, so these tools do nothing.', 'aw-seo-extras' ), array( 'type' => 'warning' ) );
	}
	$report = get_transient( 'aw_seo_redirect_report_' . get_current_user_id() );
	if ( is_array( $report ) ) {
		delete_transient( 'aw_seo_redirect_report_' . get_current_user_id() );
		aw_redirect_report( $report );
	}

	echo '<h2>' . esc_html__( 'Export and import', 'aw-seo-extras' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( $post ) . '" style="display:inline-block;margin-right:1em"><input type="hidden" name="action" value="aw_redirect_export">';
	wp_nonce_field( 'aw_redirect_export' );
	submit_button( __( 'Export redirects CSV', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '</form>';
	echo '<form method="post" action="' . esc_url( $post ) . '" style="display:inline-block"><input type="hidden" name="action" value="aw_redirect_404export">';
	wp_nonce_field( 'aw_redirect_404export' );
	submit_button( __( 'Export 404 log CSV', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '</form>';
	echo '<p>' . esc_html__( 'Import a CSV with a "source" column, and target, code (301, 302, 307, 308, 410, 451) and comparison (exact or regex). The dry run reports what would be added and writes nothing.', 'aw-seo-extras' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="aw_redirect_import">';
	wp_nonce_field( 'aw_redirect_import' );
	echo '<p><input type="file" name="aw_csv" accept=".csv,text/csv" required></p>';
	echo '<p><label><input type="checkbox" name="aw_apply" value="1"> ' . esc_html__( 'Add the redirects (otherwise it is a dry run)', 'aw-seo-extras' ) . '</label></p>';
	submit_button( __( 'Import redirects', 'aw-seo-extras' ), 'primary', 'submit', false );
	echo '</form>';

	echo '<h2 style="margin-top:2em">' . esc_html__( 'Apache .htaccess', 'aw-seo-extras' ) . '</h2>';
	$count = count( aw_redirect_htaccess_lines() );
	/* translators: %d: number of redirects. */
	echo '<p>' . esc_html( sprintf( __( '%d exact redirects can go in .htaccess (regex rules stay in Rank Math).', 'aw-seo-extras' ), $count ) ) . '</p>';
	echo '<form method="post" action="' . esc_url( $post ) . '" style="display:inline-block;margin-right:1em"><input type="hidden" name="action" value="aw_redirect_htaccess_download">';
	wp_nonce_field( 'aw_redirect_htaccess' );
	submit_button( __( 'Download the .htaccess block', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '</form>';
	if ( AW_REDIRECT_HTACCESS && aw_redirect_apache() ) {
		echo '<form method="post" action="' . esc_url( $post ) . '" style="display:inline-block;margin-right:1em"><input type="hidden" name="action" value="aw_redirect_htaccess_write">';
		wp_nonce_field( 'aw_redirect_htaccess' );
		submit_button( __( 'Write to .htaccess now', 'aw-seo-extras' ), 'delete', 'submit', false );
		echo '</form>';
		echo '<form method="post" action="' . esc_url( $post ) . '" style="display:inline-block"><input type="hidden" name="action" value="aw_redirect_htaccess_write"><input type="hidden" name="aw_remove" value="1">';
		wp_nonce_field( 'aw_redirect_htaccess' );
		submit_button( __( 'Remove the .htaccess block', 'aw-seo-extras' ), 'link', 'submit', false );
		echo '</form>';
	} else {
		echo '<p class="description">' . esc_html__( 'Writing to .htaccess is off, or this server is not Apache or LiteSpeed. Turn it on in RankWeb → Settings → Redirect tools. The downloaded block can be pasted by hand.', 'aw-seo-extras' ) . '</p>';
	}
	echo '</div>';
}

/**
 * Show a redirect-tool report.
 *
 * @param array $report The report.
 */
function aw_redirect_report( array $report ): void {
	if ( '' !== (string) $report['error'] ) {
		wp_admin_notice( esc_html( (string) $report['error'] ), array( 'type' => 'error' ) );
		return;
	}
	if ( 'htaccess' === ( $report['kind'] ?? '' ) ) {
		wp_admin_notice( esc_html( (string) ( $report['ok'] ?? '' ) ), array( 'type' => 'success' ) );
		return;
	}
	$head = $report['dry']
		/* translators: %d: number of redirects. */
		? sprintf( __( 'Dry run: %d redirects would be added. Nothing was written.', 'aw-seo-extras' ), (int) $report['added'] )
		/* translators: %d: number of redirects. */
		: sprintf( __( '%d redirects added.', 'aw-seo-extras' ), (int) $report['added'] );
	wp_admin_notice( esc_html( $head ), array( 'type' => $report['dry'] ? 'info' : 'success' ) );
	foreach ( array(
		'lines'   => __( 'Redirects', 'aw-seo-extras' ),
		'skipped' => __( 'Skipped', 'aw-seo-extras' ),
	) as $key => $label ) {
		if ( ! empty( $report[ $key ] ) ) {
			echo '<details open><summary><strong>' . esc_html( $label ) . '</strong> (' . esc_html( number_format_i18n( count( $report[ $key ] ) ) ) . ')</summary><ul style="list-style:disc;margin-left:2em">';
			foreach ( (array) $report[ $key ] as $line ) {
				echo '<li>' . esc_html( (string) $line ) . '</li>';
			}
			echo '</ul></details>';
		}
	}
}
