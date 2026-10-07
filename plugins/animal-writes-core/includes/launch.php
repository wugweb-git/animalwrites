<?php
/**
 * One-time server setup after a move: write WordPress's rewrite rules and the launch redirects
 * into .htaccess from a normal web request (WP-CLI cannot detect mod_rewrite on this host).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_HTACCESS_VERSION = '2';

add_action(
	'init',
	function () {
		if ( AW_HTACCESS_VERSION === get_option( 'aw_htaccess_version' ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$file = ABSPATH . '.htaccess';
		if ( file_exists( $file ) && ! is_writable( $file ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$redirects = array(
			'RewriteEngine On',
			'RewriteCond %{HTTP_HOST} ^www\.animalwrites\.in$ [NC]',
			'RewriteRule ^(.*)$ https://animalwrites.in/$1 [R=301,L]',
			'RewriteRule ^campaign/?$ /campaigns/animal-writes-1-0/ [R=301,L]',
			'RedirectMatch 404 /\.git',
		);
		$wp_rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase /',
			'RewriteRule ^index\.php$ - [L]',
			'RewriteCond %{REQUEST_FILENAME} !-f',
			'RewriteCond %{REQUEST_FILENAME} !-d',
			'RewriteRule . /index.php [L]',
			'</IfModule>',
		);
		// Start from a clean file each time: drop earlier copies of our block and any unmarked duplicates.
		if ( file_exists( $file ) ) {
			$clean = preg_replace( '/# BEGIN Animal Writes redirects.*?# END Animal Writes redirects\n?/s', '', (string) file_get_contents( $file ) ); // phpcs:ignore
			$clean = str_replace( implode( "\n", $redirects ) . "\n", '', $clean );
			file_put_contents( $file, ltrim( $clean ) ); // phpcs:ignore
		}
		$ok = insert_with_markers( $file, 'Animal Writes redirects', $redirects )
			&& insert_with_markers( $file, 'WordPress', $wp_rules );
		if ( ! $ok ) {
			return;
		}
		// Redirects must run before the WordPress catch-all: put that section first.
		$body = (string) file_get_contents( $file ); // phpcs:ignore
		if ( preg_match( '/# BEGIN Animal Writes redirects.*?# END Animal Writes redirects\n?/s', $body, $m ) ) {
			$body = $m[0] . "\n" . str_replace( $m[0], '', $body );
			file_put_contents( $file, $body ); // phpcs:ignore
		}
		update_option( 'aw_htaccess_version', AW_HTACCESS_VERSION, false );
		flush_rewrite_rules( false );
	},
	99
);

/**
 * One-time media restore after the move: put back each attachment's original file (from the repo's
 * media-restore/ folder, or the official source for government PDFs), then let WordPress rebuild
 * thumbnails and PDF previews. A few files per page view, so no request runs long.
 */
const AW_MEDIA_RESTORE_VERSION = '4';

add_action(
	'init',
	function () {
		if ( AW_MEDIA_RESTORE_VERSION === get_option( 'aw_media_restore_version' ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || get_transient( 'aw_media_restore_lock' ) ) {
			return;
		}
		set_transient( 'aw_media_restore_lock', 1, MINUTE_IN_SECONDS );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$repo     = dirname( AW_CORE_DIR, 2 ) . '/media-restore/';
		$manifest = is_readable( $repo . 'manifest.json' ) ? (array) json_decode( (string) file_get_contents( $repo . 'manifest.json' ), true ) : array(); // phpcs:ignore
		$uploads  = wp_upload_dir();
		$done     = 0;
		$left     = 0;
		foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
			$dest = trailingslashit( $uploads['basedir'] ) . $rel;
			$orig = preg_replace( '/(-scaled)+\./', '.', $rel );
			if ( $orig !== $rel && isset( $manifest[ $orig ] ) && ! file_exists( trailingslashit( $uploads['basedir'] ) . $orig ) && $done < 4 ) {
				$full = trailingslashit( $uploads['basedir'] ) . $orig;
				if ( copy( $repo . basename( $manifest[ $orig ] ), $full ) ) {
					update_post_meta( $id, '_wp_attached_file', $orig );
					wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $full ) );
				}
				$done++;
				continue;
			}
			if ( ! $rel || file_exists( $dest ) || ( get_post_meta( $id, '_aw_restore_failed', true ) && ! isset( $manifest[ $rel ] ) ) ) {
				continue;
			}
			if ( $done >= 4 ) {
				$left++;
				continue;
			}
			wp_mkdir_p( dirname( $dest ) );
			$ok = false;
			if ( isset( $manifest[ $rel ] ) && is_readable( $repo . basename( $manifest[ $rel ] ) ) ) {
				$ok = copy( $repo . basename( $manifest[ $rel ] ), $dest );
			} elseif ( str_ends_with( strtolower( $rel ), '.pdf' ) ) {
				$owner = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => 'aw_file_id', 'meta_value' => $id, 'fields' => 'ids', 'numberposts' => 1 ) );
				$src   = $owner ? (string) get_post_meta( $owner[0], 'aw_source_url', true ) : '';
				$tmp   = $src ? download_url( $src, 30 ) : new WP_Error( 'nosrc' );
				if ( ! is_wp_error( $tmp ) ) {
					$ok = rename( $tmp, $dest ) || copy( $tmp, $dest );
				}
			}
			if ( $ok ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dest ) );
			} else {
				update_post_meta( $id, '_aw_restore_failed', 1 );
			}
			$done++;
		}
		delete_transient( 'aw_media_restore_lock' );
		if ( 0 === $left ) {
			update_option( 'aw_media_restore_version', AW_MEDIA_RESTORE_VERSION, false );
		}
	},
	100
);

/**
 * One-time: the homepage's repeatable sections become references to the theme patterns that now
 * hold them (themes/animal-writes/patterns/*.php), so one edit updates every page that uses them.
 * A section is swapped only if the page still contains it exactly as the pattern file has it.
 */
add_action(
	'init',
	function () {
		if ( (int) get_option( 'aw_home_patterns' ) >= 1 || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$home = (int) get_option( 'page_on_front' );
		$post = $home ? get_post( $home ) : null;
		if ( ! $post ) {
			return;
		}
		$content = $post->post_content;
		$swapped = 0;
		foreach ( array( 'help-finder', 'latest-decisions-news', 'campaign-photo-band', 'section-index', 'lend-a-hand' ) as $slug ) {
			$file = get_stylesheet_directory() . "/patterns/{$slug}.php";
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$body = trim( (string) preg_replace( '/^<\?php.*?\?>\s*/s', '', (string) file_get_contents( $file ) ) ); // phpcs:ignore
			if ( $body && false !== strpos( $content, $body ) ) {
				$content = str_replace( $body, '<!-- wp:pattern {"slug":"animal-writes/' . $slug . '"} /-->', $content );
				$swapped++;
			}
		}
		if ( $swapped ) {
			// Direct write: a visitor's request has no unfiltered_html, so wp_update_post would run kses over the page.
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $home ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $home );
		}
		update_option( 'aw_home_patterns', '1', false );
	},
	101
);

/**
 * One-time (v2): the homepage hero becomes the animal-writes/hero pattern (English-only headline).
 * Replaces the aw-hero group, which runs from its opening comment to the help-finder pattern reference.
 */
add_action(
	'init',
	function () {
		if ( (int) get_option( 'aw_home_patterns' ) >= 2 || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$home = (int) get_option( 'page_on_front' );
		$post = $home ? get_post( $home ) : null;
		if ( ! $post ) {
			return;
		}
		$c     = $post->post_content;
		$start = strpos( $c, '<!-- wp:group {"align":"full","className":"aw-hero"' );
		$end   = strpos( $c, '<!-- wp:pattern {"slug":"animal-writes/help-finder"} /-->' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$c = substr( $c, 0, $start ) . "<!-- wp:pattern {\"slug\":\"animal-writes/hero\"} /-->\n\n" . substr( $c, $end );
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => $home ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $home );
		}
		update_option( 'aw_home_patterns', '2', false );
	},
	102
);

/**
 * One-time: lead photos for guides, opportunities and resources.
 * Openly licensed photos (media-restore/photos/photos.json) become attachments with a credit caption;
 * Animal Writes' own campaign photos are reused by attachment ID. Posts that already have an image are left alone.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_lead_photos' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$dir  = dirname( AW_CORE_DIR, 2 ) . '/media-restore/photos/';
		$plan = is_readable( $dir . 'photos.json' ) ? json_decode( (string) file_get_contents( $dir . 'photos.json' ), true ) : null; // phpcs:ignore
		if ( ! $plan ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		foreach ( (array) $plan['own'] as $post_id => $att ) {
			if ( ! has_post_thumbnail( (int) $post_id ) && wp_attachment_is_image( (int) $att ) ) {
				set_post_thumbnail( (int) $post_id, (int) $att );
			}
		}
		foreach ( (array) $plan['photos'] as $post_id => $p ) {
			$post_id = (int) $post_id;
			if ( has_post_thumbnail( $post_id ) || ! is_readable( $dir . basename( $p['file'] ) ) ) {
				continue;
			}
			$tmp = wp_tempnam( $p['file'] );
			copy( $dir . basename( $p['file'] ), $tmp );
			$name = sanitize_title( get_the_title( $post_id ) ) . '.jpg';
			$att  = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $post_id, get_the_title( $post_id ) );
			if ( is_wp_error( $att ) ) {
				continue;
			}
			$credit = sprintf(
				'Photo: <a href="%1$s">%2$s</a> by %3$s, <a href="%4$s">%5$s</a>',
				esc_url( $p['source'] ),
				esc_html( $p['title'] ),
				esc_html( $p['creator'] ),
				esc_url( $p['license_url'] ),
				esc_html( $p['license'] )
			);
			wp_update_post( array( 'ID' => $att, 'post_excerpt' => $credit ) );
			update_post_meta( $att, '_wp_attachment_image_alt', '' );
			set_post_thumbnail( $post_id, $att );
		}
		update_option( 'aw_lead_photos', '1', false );
	},
	103
);

/** On an item's own page, show its photo credit (attachment caption) under the lead image. */
add_filter(
	'render_block_core/post-featured-image',
	function ( $html, $block, $instance ) {
		$post_id = $instance->context['postId'] ?? 0;
		if ( ! $html || ! is_singular() || (int) $post_id !== get_queried_object_id() ) {
			return $html;
		}
		$caption = wp_get_attachment_caption( (int) get_post_thumbnail_id( $post_id ) );
		if ( ! $caption ) {
			return $html;
		}
		return str_replace( '</figure>', '<figcaption class="aw-credit">' . wp_kses( $caption, array( 'a' => array( 'href' => true ) ) ) . '</figcaption></figure>', $html );
	},
	20,
	3
);

/**
 * One-time (v3): the homepage becomes the editorial sequence HELP → KNOW → ACT → FOLLOW.
 * The previous content is kept as a revision-style backup in the aw_home_backup option.
 */
add_action(
	'init',
	function () {
		if ( (int) get_option( 'aw_home_patterns' ) >= 3 || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$home = (int) get_option( 'page_on_front' );
		$post = $home ? get_post( $home ) : null;
		if ( ! $post ) {
			return;
		}
		add_option( 'aw_home_backup', $post->post_content, '', false );
		$seq = array( 'hero', 'help-now', 'whats-happening', '#ad', 'animal-law', 'find-help', 'learn', 'take-action', 'aw1-story', '#testimonials' );
		$out = '';
		foreach ( $seq as $s ) {
			if ( '#ad' === $s ) {
				$out .= "<!-- wp:shortcode -->\n[aw_ad slot=\"home\"]\n<!-- /wp:shortcode -->\n\n";
			} elseif ( '#testimonials' === $s ) {
				$out .= "<!-- wp:shortcode -->\n[aw_testimonials count=\"3\"]\n<!-- /wp:shortcode -->\n";
			} else {
				$out .= '<!-- wp:pattern {"slug":"animal-writes/' . $s . "\"} /-->\n\n";
			}
		}
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $out ), array( 'ID' => $home ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $home );
		update_option( 'aw_home_patterns', '3', false );
	},
	104
);

/**
 * One-time: news and legal slugs that end with the publisher are cleaned. For published items WordPress records
 * the old slug, so the old URL 301s to the new one (wp_old_slug_redirect). Runs as an administrator so
 * wp_update_post does not filter the content.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_slugs_clean' ) || ! function_exists( 'aw_clean_news_slug' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		update_option( 'aw_slugs_clean', '1', false ); // set first: never loops
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$prev   = get_current_user_id();
		wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
		$changed = array();
		foreach ( get_posts( array( 'post_type' => array( 'aw_news', 'aw_legal' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
			if ( ! $p->post_name ) {
				continue;
			}
			$clean = aw_clean_news_slug( $p->post_name, $p->ID );
			if ( $clean !== $p->post_name ) {
				wp_update_post( array( 'ID' => $p->ID, 'post_name' => $clean ) );
				$changed[] = $p->post_name . ' -> ' . get_post_field( 'post_name', $p->ID );
			}
		}
		wp_set_current_user( $prev );
		update_option( 'aw_slugs_clean_log', $changed, false );
	},
	105
);

// Impact figure: 1.0 proof-of-posting submissions, counted before the receipt records were deleted (2026-10-07).
add_action( 'init', fn() => add_option( 'aw_impact_aw1_receipts', 16754, '', false ) );
