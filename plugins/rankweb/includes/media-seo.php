<?php
/**
 * RankWeb PRO, phase E: Image SEO — find and replace across media alt/title/caption, and fill missing captions
 * (docs/rankweb-pro-audit.md, E). Rank Math keeps Image SEO for Pro.
 *
 * Screen: RankWeb → Images. Two tools, each with a dry run that writes nothing:
 * - Find and replace in the alt text, title and caption of attachments (plain text or a regular expression, optionally
 *   case-insensitive), using AW_Bulk::replace. A dry run lists the before and after of the first matches.
 * - Fill a missing caption from a pattern ({title}, {filename}, {sitename}); existing captions are never touched.
 *
 * Module "media" (RankWeb → Settings → Modules). No scheduled job. Each run is capped (AW_MEDIA_BATCH) so one screen
 * load never walks the whole library.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-bulk.php';

const AW_MEDIA_BATCH = 500; // attachments touched in one run

/** The three fields and how they are stored. */
function aw_media_fields(): array {
	return array(
		'alt'     => __( 'Alt text', 'aw-seo-extras' ),
		'title'   => __( 'Title', 'aw-seo-extras' ),
		'caption' => __( 'Caption', 'aw-seo-extras' ),
	);
}

/** Read one field of an attachment. */
function aw_media_get( int $id, string $field ): string {
	if ( 'alt' === $field ) {
		return (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	}
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	return 'title' === $field ? (string) $post->post_title : (string) $post->post_excerpt;
}

/** Write one field of an attachment. */
function aw_media_set( int $id, string $field, string $value ): void {
	if ( 'alt' === $field ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $value );
		return;
	}
	wp_update_post(
		array(
			'ID' => $id,
			'title' === $field ? 'post_title' : 'post_excerpt' => $value,
		)
	);
}

/** Attachment IDs, newest first, capped. */
function aw_media_ids(): array {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => AW_MEDIA_BATCH,
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'suppress_filters' => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- admin/background query over post IDs; filters suppressed so other plugins do not alter this internal set (capped or cached, never a front-end query).
			)
		)
	);
}

/** The caption from the pattern for an attachment. */
function aw_media_caption_for( int $id ): string {
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$file = (string) get_post_meta( $id, '_wp_attached_file', true );
	$name = '' !== $file ? pathinfo( $file, PATHINFO_FILENAME ) : $post->post_name;
	$name = trim( str_replace( array( '-', '_' ), ' ', (string) $name ) );
	return trim(
		strtr(
			(string) AW_MEDIA_CAPTION,
			array(
				'{title}'    => (string) $post->post_title,
				'{filename}' => $name,
				'{sitename}' => (string) get_bloginfo( 'name' ),
			)
		)
	);
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_SEO_MENU, __( 'Images', 'aw-seo-extras' ), __( 'Images', 'aw-seo-extras' ), 'manage_options', 'aw-images', 'aw_media_screen' );
	}
);

/* ---------- Find and replace ---------- */

add_action(
	'admin_post_aw_media_replace',
	function () {
		check_admin_referer( 'aw_media_replace' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$search  = isset( $_POST['search'] ) ? (string) wp_unslash( $_POST['search'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a search string; used only as a needle/pattern in AW_Bulk::replace, never echoed unescaped
		$replace = isset( $_POST['replace'] ) ? (string) wp_unslash( $_POST['replace'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a replacement string, stored via wp_update_post/update_post_meta and escaped on output
		$fields  = array_intersect( array_map( 'sanitize_key', (array) ( $_POST['fields'] ?? array() ) ), array_keys( aw_media_fields() ) );
		$regex   = isset( $_POST['regex'] );
		$ci      = isset( $_POST['ci'] );
		$dry     = ! isset( $_POST['aw_apply'] );
		$report  = array(
			'dry'     => $dry,
			'updated' => 0,
			'changes' => array(),
			'error'   => '',
		);
		if ( '' === $search ) {
			$report['error'] = __( 'Enter something to search for.', 'aw-seo-extras' );
		} elseif ( ! $fields ) {
			$report['error'] = __( 'Choose at least one field.', 'aw-seo-extras' );
		} else {
			foreach ( aw_media_ids() as $id ) {
				foreach ( $fields as $field ) {
					$old = aw_media_get( $id, $field );
					if ( '' === $old ) {
						continue;
					}
					$res = AW_Bulk::replace( $old, $search, $replace, $regex, $ci );
					if ( '' !== $res['error'] ) {
						$report['error'] = $res['error'];
						break 2;
					}
					if ( $res['count'] > 0 && $res['text'] !== $old ) {
						++$report['updated'];
						if ( count( $report['changes'] ) < 50 ) {
							$report['changes'][] = array(
								'id'    => $id,
								'field' => $field,
								'from'  => $old,
								'to'    => $res['text'],
							);
						}
						if ( ! $dry ) {
							aw_media_set( $id, $field, $res['text'] );
						}
					}
				}
			}
		}
		set_transient( 'aw_seo_media_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'done', '1', admin_url( 'admin.php?page=aw-images' ) ) );
		exit;
	}
);

/* ---------- Fill missing captions ---------- */

add_action(
	'admin_post_aw_media_captions',
	function () {
		check_admin_referer( 'aw_media_captions' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
		}
		$dry    = ! isset( $_POST['aw_apply'] );
		$report = array(
			'dry'     => $dry,
			'updated' => 0,
			'changes' => array(),
			'error'   => '',
		);
		foreach ( aw_media_ids() as $id ) {
			if ( '' !== aw_media_get( $id, 'caption' ) ) {
				continue; // never touch an existing caption
			}
			$caption = aw_media_caption_for( $id );
			if ( '' === $caption ) {
				continue;
			}
			++$report['updated'];
			if ( count( $report['changes'] ) < 50 ) {
				$report['changes'][] = array(
					'id'    => $id,
					'field' => 'caption',
					'from'  => '',
					'to'    => $caption,
				);
			}
			if ( ! $dry ) {
				aw_media_set( $id, 'caption', $caption );
			}
		}
		set_transient( 'aw_seo_media_report_' . get_current_user_id(), $report, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'done', '1', admin_url( 'admin.php?page=aw-images' ) ) );
		exit;
	}
);

/* ---------- The screen ---------- */

/** RankWeb → Images. */
function aw_media_screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to see this.', 'aw-seo-extras' ), '', array( 'response' => 403 ) );
	}
	$post = admin_url( 'admin-post.php' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Image SEO', 'aw-seo-extras' ) . '</h1>';
	echo '<p>' . esc_html( sprintf( /* translators: %s: a number. */ __( 'Each run works on the %s most recent attachments.', 'aw-seo-extras' ), number_format_i18n( AW_MEDIA_BATCH ) ) ) . '</p>';
	$report = get_transient( 'aw_seo_media_report_' . get_current_user_id() );
	if ( is_array( $report ) ) {
		delete_transient( 'aw_seo_media_report_' . get_current_user_id() );
		aw_media_report( $report );
	}

	echo '<h2>' . esc_html__( 'Find and replace', 'aw-seo-extras' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="aw_media_replace">';
	wp_nonce_field( 'aw_media_replace' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-media-search">' . esc_html__( 'Find', 'aw-seo-extras' ) . '</label></th><td><input type="text" id="aw-media-search" name="search" class="regular-text"></td></tr>';
	echo '<tr><th scope="row"><label for="aw-media-replace">' . esc_html__( 'Replace with', 'aw-seo-extras' ) . '</label></th><td><input type="text" id="aw-media-replace" name="replace" class="regular-text"></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Fields', 'aw-seo-extras' ) . '</th><td>';
	foreach ( aw_media_fields() as $key => $label ) {
		echo '<label style="margin-right:1em"><input type="checkbox" name="fields[]" value="' . esc_attr( $key ) . '"' . checked( 'caption' !== $key, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Options', 'aw-seo-extras' ) . '</th><td><label style="margin-right:1em"><input type="checkbox" name="regex" value="1"> ' . esc_html__( 'Regular expression', 'aw-seo-extras' ) . '</label><label><input type="checkbox" name="ci" value="1"> ' . esc_html__( 'Ignore case', 'aw-seo-extras' ) . '</label></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Apply', 'aw-seo-extras' ) . '</th><td><label><input type="checkbox" name="aw_apply" value="1"> ' . esc_html__( 'Apply the changes (otherwise it is a dry run)', 'aw-seo-extras' ) . '</label></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Find and replace', 'aw-seo-extras' ), 'primary', 'submit', false );
	echo '</form>';

	echo '<h2 style="margin-top:2em">' . esc_html__( 'Fill missing captions', 'aw-seo-extras' ) . '</h2>';
	echo '<p>' . esc_html( sprintf( /* translators: %s: the caption pattern. */ __( 'Fills the caption of attachments that have none, from the pattern: %s. Existing captions are left alone. Change the pattern in RankWeb → Settings → Images.', 'aw-seo-extras' ), (string) AW_MEDIA_CAPTION ) ) . '</p>';
	echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="aw_media_captions">';
	wp_nonce_field( 'aw_media_captions' );
	echo '<p><label><input type="checkbox" name="aw_apply" value="1"> ' . esc_html__( 'Apply the changes (otherwise it is a dry run)', 'aw-seo-extras' ) . '</label></p>';
	submit_button( __( 'Fill captions', 'aw-seo-extras' ), 'secondary', 'submit', false );
	echo '</form></div>';
}

/**
 * Show a media tool's report.
 *
 * @param array $report The report.
 */
function aw_media_report( array $report ): void {
	if ( '' !== (string) $report['error'] ) {
		wp_admin_notice( esc_html( (string) $report['error'] ), array( 'type' => 'error' ) );
		return;
	}
	$head = $report['dry']
		/* translators: %d: number of attachments. */
		? sprintf( __( 'Dry run: %d attachments would change. Nothing was written.', 'aw-seo-extras' ), (int) $report['updated'] )
		/* translators: %d: number of attachments. */
		: sprintf( __( '%d attachments updated.', 'aw-seo-extras' ), (int) $report['updated'] );
	wp_admin_notice( esc_html( $head ), array( 'type' => $report['dry'] ? 'info' : 'success' ) );
	if ( empty( $report['changes'] ) ) {
		return;
	}
	echo '<table class="widefat striped" style="max-width:60em"><thead><tr><th>' . esc_html__( 'Attachment', 'aw-seo-extras' ) . '</th><th>' . esc_html__( 'Field', 'aw-seo-extras' ) . '</th><th>' . esc_html__( 'From', 'aw-seo-extras' ) . '</th><th>' . esc_html__( 'To', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	$fields = aw_media_fields();
	foreach ( (array) $report['changes'] as $c ) {
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( (int) $c['id'] ) ) . '">' . esc_html( '#' . (int) $c['id'] ) . '</a></td><td>' . esc_html( $fields[ $c['field'] ] ?? $c['field'] ) . '</td><td>' . esc_html( (string) $c['from'] ) . '</td><td>' . esc_html( (string) $c['to'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}
