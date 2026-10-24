<?php
/**
 * Settings → Sourcing, the one place for what the workers fetch (S3, 8 Oct 2026). Kits Core gives the screen with
 * Publishers (whose articles are accepted), Keywords (the one word list) and the Change log; this adds News feeds (RSS and Google News queries), Library sources
 * (AWBI, PIB, Gazette, courts), Opportunity sources, Press-discovery queries and Categories (keyword → category).
 *
 * The lists live in the option aw_sources, in the same shape as the workers' sources.yaml (defaults, keywords,
 * sources, categories, press), seeded once from it (data/sources-seed.json). The workers read them at the start of every
 * run from the private, authenticated policy endpoint (aw/v1/sourcing-policy) once "Workers use these lists" is on;
 * until then, and whenever the site cannot be reached, they use sources.yaml. API keys stay in GitHub secrets.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seed (once): blocked publishers from the owner's list (S3.5). */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'aw_sourcing_seed_s35' ) || ! function_exists( 'aw_publishers' ) ) {
			return;
		}
		$pubs = aw_publishers();
		foreach ( array( 'bollyspice.com', 'storyboard18.com', 'in.eventfaqs.com' ) as $h ) {
			$pubs[ $h ] = array_merge( array( 'name' => '', 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '' ), $pubs[ $h ] ?? array(), array( 'status' => 'block' ) );
		}
		update_option( AW_PUB_OPTION, $pubs, false );
		aw_sources_config();
		update_option( 'aw_sourcing_seed_s35', '1', false );
	}
);

/** Google News RSS URL for a search query (India, English). */
function aw_gnews_url( $query ) {
	return 'https://news.google.com/rss/search?q=' . rawurlencode( trim( $query ) ) . '&hl=en-IN&gl=IN&ceid=IN:en';
}

/** The query inside a Google News RSS URL, or ''. */
function aw_gnews_query( $url ) {
	if ( false === strpos( (string) $url, 'news.google.com/rss/search' ) ) {
		return '';
	}
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	return (string) ( $q['q'] ?? '' );
}

/** Tab → source type in the lists. */
const AW_SOURCE_TABS = array(
	'feeds'         => array( 'News feeds', 'news' ),
	'library'       => array( 'Library sources', 'legal' ),
	'opportunities' => array( 'Opportunity sources', 'opportunity' ),
);

add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		foreach ( AW_SOURCE_TABS as $slug => $t ) {
			$tabs[ $slug ] = array( $t[0], fn() => aw_sources_tab( $slug ) );
		}
		$tabs['press']  = array( 'Press queries', 'aw_sources_press_tab' );
		$tabs['categories'] = array( 'Categories', 'aw_sources_categories_tab' );
		$tabs['sections']   = array( 'Sections', 'aw_sources_sections_tab' );
		return $tabs;
	}
);

/** "Workers use these lists" switch, on every tab. */
add_action(
	'kits_sourcing_page_top',
	function () {
		$c = aw_sources_config();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'aw_sourcing' );
		echo '<input type="hidden" name="action" value="aw_sourcing"><input type="hidden" name="tab" value="live">'
			. '<p><strong>Workers use these lists:</strong> ' . ( empty( $c['live'] ) ? 'off (they use sources.yaml in the workers repository)' : 'on (read at the start of every run)' ) . ' '
			. '<button class="button" name="live" value="' . ( empty( $c['live'] ) ? '1' : '0' ) . '">' . ( empty( $c['live'] ) ? 'Turn on' : 'Turn off' ) . '</button></p></form>';
	}
);
add_action(
	'kits_sourcing_save_live',
	function () {
		$c         = aw_sources_config();
		$c['live'] = ! empty( $_POST['live'] ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'log', 'workers use these lists: ' . ( $c['live'] ? 'on' : 'off' ) );
	}
);

/** One list of sources (News feeds, Library sources or Opportunity sources): the list, or one source to edit or add. */
function aw_sources_tab( $slug ) {
	$type = AW_SOURCE_TABS[ $slug ][1];
	$edit = sanitize_key( $_GET['edit'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $edit ) {
		aw_sources_edit_form( $slug, $edit );
		return;
	}
	$intro = 'news' === $type ? 'RSS feeds and Google News searches.' : ( 'legal' === $type ? 'Where Library documents and legal news come from. Gazette, AWBI and court-judgment sources need code to read; only their name and on/off can change here.' : 'Where opportunities come from. Only their name and on/off can change here; new kinds of site need code.' );
	echo '<p>' . esc_html( $intro ) . ' "Keyword check" keeps only items with a word from the Keywords tab (an include word or an animal word).'
		. ( 'opportunity' !== $type ? ' <a href="' . esc_url( admin_url( 'options-general.php?page=aw-sourcing&tab=' . $slug . '&edit=new' ) ) . '" class="page-title-action">Add a source</a>' : '' ) . '</p>';
	if ( ! class_exists( 'WP_List_Table' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}
	require_once __DIR__ . '/class-aw-sourcing-sources-table.php';
	$table = new AW_Sourcing_Sources_Table( $slug );
	$table->prepare_items();
	$table->views();
	// Search: a GET form outside this POST form (printed at the top of the screen); the fields point at it.
	$find = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	echo '<p class="search-box"><label class="screen-reader-text" for="aw-src-s">Search sources:</label><input type="search" id="aw-src-s" name="s" value="' . esc_attr( $find ) . '" form="aw-src-search"> <input type="submit" class="button" value="Search sources" form="aw-src-search"></p>';
	$table->display();
}

/** Edit one source, or add one (edit=new), in the core form-table layout. Saves through the tab's own save hook. */
function aw_sources_edit_form( $slug, $id ) {
	$type = AW_SOURCE_TABS[ $slug ][1];
	$src  = null;
	foreach ( (array) aw_sources_config()['sources'] as $s ) {
		if ( ( $s['id'] ?? '' ) === $id && ( $s['type'] ?? '' ) === $type ) {
			$src = $s;
		}
	}
	$new = 'new' === $id;
	if ( ! $new && ! $src ) {
		echo '<div class="notice notice-error inline"><p>No such source.</p></div>';
		return;
	}
	if ( $new && 'opportunity' === $type ) {
		echo '<div class="notice notice-warning inline"><p>New kinds of opportunity site need code.</p></div>';
		return;
	}
	$src  = $src ? $src : array( 'id' => '', 'name' => '', 'kind' => 'rss', 'url' => '', 'on' => true, 'filter' => true );
	$rss  = 'rss' === $src['kind'];
	$q    = aw_gnews_query( $src['url'] );
	$back = admin_url( 'options-general.php?page=aw-sourcing&tab=' . $slug );
	echo '<h2>' . esc_html( $new ? 'Add a source' : 'Edit ' . $src['name'] ) . '</h2>';
	echo '<input type="hidden" name="src[0][id]" value="' . esc_attr( $src['id'] ) . '"><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="aw-src-name">Name</label></th><td><input type="text" id="aw-src-name" class="regular-text" name="src[0][name]" value="' . esc_attr( $src['name'] ) . '" required></td></tr>';
	echo '<tr><th scope="row"><label for="aw-src-url">Feed URL or Google News search</label></th><td>'
		. ( $rss ? '<input type="text" id="aw-src-url" class="large-text" name="src[0][url]" value="' . esc_attr( $q ? $q : $src['url'] ) . '" placeholder="https://… feed, or Google News search words"' . ( $new ? ' required' : '' ) . '><p class="description">For a Google News search, write only the search words.</p>' : '<code>' . esc_html( $src['url'] ) . '</code><p class="description">' . esc_html( $src['kind'] ) . ': needs code to change.</p>' )
		. '</td></tr>';
	echo '<tr><th scope="row">Status</th><td><label><input type="checkbox" name="src[0][on]" value="1"' . checked( ! empty( $src['on'] ), true, false ) . '> On</label></td></tr>';
	if ( $rss ) {
		echo '<tr><th scope="row">Keyword check</th><td><label><input type="checkbox" name="src[0][filter]" value="1"' . checked( ! empty( $src['filter'] ), true, false ) . '> Keep only items with a word from the Keywords tab</label></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( $new ? 'Add source' : 'Save source', 'primary', 'submit', false );
	echo ' <a href="' . esc_url( $back ) . '" class="button">Cancel</a>';
}

// The sources search form, outside the screen's POST form (the search fields in the tab point at it with form=).
add_action(
	'kits_sourcing_page_top',
	function () {
		$tab = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( AW_SOURCE_TABS[ $tab ] ) ) {
			echo '<form id="aw-src-search" method="get"><input type="hidden" name="page" value="aw-sourcing"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"></form>';
		}
	}
);

/**
 * Row and bulk actions of a sources list: on / off and keyword check on / off. Returns true when it handled the
 * request (the edit form's rows are then not read).
 */
function aw_sources_list_action( $type, $tab ) {
	$row = sanitize_text_field( wp_unslash( $_POST['src_row'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
	if ( false !== strpos( $row, ':' ) ) {
		list( $op, $one ) = explode( ':', $row, 2 );
		$ids              = array( sanitize_key( $one ) );
	} else {
		$op  = sanitize_key( $_POST['src_bulk'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$op  = '' !== $op ? $op : sanitize_key( $_POST['src_bulk2'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids = array_map( 'sanitize_key', (array) wp_unslash( $_POST['ids'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	$op = sanitize_key( $op );
	if ( ! in_array( $op, array( 'on', 'off', 'filter_on', 'filter_off' ), true ) ) {
		return isset( $_POST['src_bulk'] ) && ! isset( $_POST['src'] ); // phpcs:ignore WordPress.Security.NonceVerification -- the list without a chosen action: nothing to do
	}
	$c    = aw_sources_config();
	$what = array();
	foreach ( $c['sources'] as &$s ) {
		if ( ( $s['type'] ?? '' ) !== $type || ! in_array( $s['id'], $ids, true ) ) {
			continue;
		}
		if ( 'on' === $op || 'off' === $op ) {
			if ( ( 'on' === $op ) !== (bool) $s['on'] ) {
				$s['on'] = 'on' === $op;
				$what[]  = $s['name'] . ' ' . $op;
			}
		} elseif ( 'rss' === ( $s['kind'] ?? '' ) && ( 'filter_on' === $op ) !== (bool) $s['filter'] ) {
			$s['filter'] = 'filter_on' === $op;
			$what[]      = $s['name'] . ' keyword check ' . ( $s['filter'] ? 'on' : 'off' );
		}
	}
	unset( $s );
	update_option( 'aw_sources', $c, false );
	kits_sourcing_log( $tab, implode( ' · ', $what ) );
	return true;
}

foreach ( array_keys( AW_SOURCE_TABS ) as $aw_tab ) {
	add_action(
		'kits_sourcing_save_' . $aw_tab,
		function () use ( $aw_tab ) {
			if ( aw_sources_list_action( AW_SOURCE_TABS[ $aw_tab ][1], $aw_tab ) ) {
				return; // a row or bulk action of the list
			}
			$c     = aw_sources_config();
			$type  = AW_SOURCE_TABS[ $aw_tab ][1];
			$rows  = (array) wp_unslash( $_POST['src'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
			$byid  = array();
			$what  = array();
			foreach ( $c['sources'] as $n => $s ) {
				$byid[ $s['id'] ] = $n;
			}
			foreach ( $rows as $r ) {
				$id   = sanitize_key( $r['id'] ?? '' );
				$name = sanitize_text_field( $r['name'] ?? '' );
				$url  = trim( sanitize_text_field( $r['url'] ?? '' ) );
				$on   = ! empty( $r['on'] );
				$flt  = ! empty( $r['filter'] );
				if ( '' === $id ) { // a new row
					if ( '' === $name || '' === $url ) {
						continue;
					}
					$url = preg_match( '#^https?://#', $url ) ? esc_url_raw( $url ) : aw_gnews_url( $url );
					$new = array( 'id' => sanitize_title( ( false !== strpos( $url, 'news.google.com' ) ? 'gnews-' : '' ) . $name ), 'name' => $name, 'kind' => 'rss', 'type' => $type, 'url' => $url, 'on' => $on, 'filter' => $flt );
					if ( 'legal' === $type ) {
						$new['source_type'] = 'auto-court';
					}
					$c['sources'][] = $new;
					$what[]         = 'added ' . $name;
					continue;
				}
				if ( ! isset( $byid[ $id ] ) ) {
					continue;
				}
				$s = &$c['sources'][ $byid[ $id ] ];
				if ( $s['on'] !== $on ) {
					$what[] = $s['name'] . ( $on ? ' on' : ' off' );
				}
				$s['on'] = $on;
				if ( '' !== $name && $name !== $s['name'] ) {
					$what[]    = $s['name'] . ' renamed ' . $name;
					$s['name'] = $name;
				}
				if ( 'rss' === $s['kind'] && '' !== $url ) {
					$url = preg_match( '#^https?://#', $url ) ? esc_url_raw( $url ) : aw_gnews_url( $url );
					if ( $url !== $s['url'] ) {
						$what[]   = $s['name'] . ' address changed';
						$s['url'] = $url;
					}
					if ( (bool) $s['filter'] !== $flt ) {
						$what[]      = $s['name'] . ' keyword check ' . ( $flt ? 'on' : 'off' );
						$s['filter'] = $flt;
					}
				}
				unset( $s );
			}
			update_option( 'aw_sources', $c, false );
			kits_sourcing_log( $aw_tab, implode( ' · ', $what ) );
		}
	);
}

/** Press-discovery queries (Media & PR). */
function aw_sources_press_tab() {
	$p = aw_sources_config()['press'];
	echo '<p>Searches for coverage of Animal Writes (Brave Search, weekly). Each query runs on each site below and on the open web. Results wait in Inbox → Press to review.</p>';
	echo '<div class="aw-src-lists"><p><label for="aw-pq"><strong>Queries</strong> (one per line)</label><br><textarea id="aw-pq" name="queries">' . esc_textarea( implode( "\n", (array) $p['queries'] ) ) . '</textarea></p>'
		. '<p><label for="aw-ps"><strong>Sites</strong> (one per line)</label><br><textarea id="aw-ps" name="sites">' . esc_textarea( implode( "\n", (array) $p['sites'] ) ) . '</textarea></p></div>';
	echo '<p><label>Coverage from <input type="date" name="date_floor" value="' . esc_attr( $p['date_floor'] ) . '"></label></p>';
	submit_button( 'Save' );
}
add_action(
	'kits_sourcing_save_press',
	function () {
		$c    = aw_sources_config();
		$old  = $c['press'];
		$list = fn( $k ) => array_values( array_unique( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) ) ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$c['press']['queries'] = $list( 'queries' );
		$c['press']['sites']   = $list( 'sites' );
		$d                     = sanitize_text_field( wp_unslash( $_POST['date_floor'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			$c['press']['date_floor'] = $d;
		}
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'press', implode( ' · ', array_filter( array( kits_sourcing_diff( 'Queries', $old['queries'], $c['press']['queries'] ), kits_sourcing_diff( 'Sites', $old['sites'], $c['press']['sites'] ), $old['date_floor'] !== $c['press']['date_floor'] ? 'from ' . $c['press']['date_floor'] : '' ) ) ) );
	}
);

/** Categories: words that file an item under a category. */
function aw_sources_categories_tab() {
	$c = aw_sources_config();
	echo '<p>An item gets every category whose words appear in its title or summary. One category per line: <code>category-slug: word, word, word</code>. Hindi works.</p>';
	$lines = array();
	foreach ( (array) $c['categories'] as $slug => $words ) {
		$lines[] = $slug . ': ' . implode( ', ', (array) $words );
	}
	echo '<textarea name="categories" class="large-text code" rows="22">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
	submit_button( 'Save' );
}
add_action(
	'kits_sourcing_save_categories',
	function () {
		$c      = aw_sources_config();
		$old  = $c['categories'];
		$cats = array();
		foreach ( explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['categories'] ?? '' ) ) ) as $line ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( preg_match( '/^\s*([a-z0-9-]+)\s*:\s*(.+)$/u', $line, $m ) ) {
				$cats[ $m[1] ] = array_values( array_filter( array_map( 'trim', explode( ',', $m[2] ) ) ) );
			}
		}
		$what = array();
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $cats ) ) ) as $slug ) {
			$what[] = kits_sourcing_diff( $slug, $old[ $slug ] ?? array(), $cats[ $slug ] ?? array() );
		}
		$c['categories'] = $cats;
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'categories', implode( ' · ', array_filter( $what ) ) );
	}
);

/** Sections: the categories and tags each section may use (the workers classify only within these; hub filters show only these). */
function aw_sources_sections_tab() {
	$c = aw_sources_config();
	echo '<p>Which categories and tags each section uses. One slug per line. The workers pick only from these lists, and each list\'s filters show only these. An empty Categories list means every category.</p>';
	echo '<table class="form-table">';
	foreach ( aw_post_types() as $type => $def ) {
		$l = (array) ( $c['sections'][ $type ] ?? array() );
		echo '<tr><th scope="row">' . esc_html( $def[1] ) . '</th><td style="display:flex;gap:1em;flex-wrap:wrap">';
		foreach ( array( 'categories' => 'Allowed categories', 'tags' => 'Allowed tags' ) as $k => $label ) {
			echo '<label style="flex:1;min-width:16em">' . esc_html( $label ) . '<textarea name="sections[' . esc_attr( $type ) . '][' . esc_attr( $k ) . ']" class="large-text code" rows="8">' . esc_textarea( implode( "\n", (array) ( $l[ $k ] ?? array() ) ) ) . '</textarea></label>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	submit_button( 'Save' );
}
add_action(
	'kits_sourcing_save_sections',
	function () {
		$c    = aw_sources_config();
		$old  = $c['sections'];
		$in   = (array) wp_unslash( $_POST['sections'] ?? array() ); // phpcs:ignore WordPress.Security.NonceVerification
		$new  = array();
		$what = array();
		foreach ( array_keys( aw_post_types() ) as $type ) {
			foreach ( array( 'categories', 'tags' ) as $k ) {
				$new[ $type ][ $k ] = array_values( array_unique( array_filter( array_map( 'sanitize_title', preg_split( '/[\s,]+/', (string) ( $in[ $type ][ $k ] ?? '' ) ) ) ) ) );
				$what[]             = kits_sourcing_diff( $type . ' ' . $k, (array) ( $old[ $type ][ $k ] ?? array() ), $new[ $type ][ $k ] );
			}
		}
		$c['sections'] = $new;
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'sections', implode( ' · ', array_filter( $what ) ) );
	}
);

/**
 * The workers' copy of the lists (same shape as sources.yaml), when "Workers use these lists" is on. Sources that are
 * off are left out; a source with "Keyword check" gets the feed keywords.
 */
add_filter(
	'kits_sourcing_policy',
	function ( $data ) {
		$c = aw_sources_config();
		if ( empty( $c['live'] ) ) {
			return $data;
		}
		$rel     = function_exists( 'aw_relevance' ) ? aw_relevance() : array();
		$words   = array_values( array_unique( array_merge( (array) ( $rel['strong'] ?? array() ), (array) ( $rel['animal'] ?? array() ) ) ) );
		$sources = array();
		foreach ( (array) $c['sources'] as $s ) {
			if ( empty( $s['on'] ) ) {
				continue;
			}
			if ( ! empty( $s['filter'] ) ) {
				$s['keywords'] = $words; // one keyword list: the Keywords tab's include and animal words
			}
			unset( $s['on'], $s['filter'] );
			$sources[] = $s;
		}
		// Search words: the Keywords tab's Include list. Each English one is a Google News search per run; the animal words
		// (dog, cat, pet ...) are for checking feed items, never searches (77 words would be 71 searches, 20 Oct 2026).
		$search = array_values( array_unique( (array) ( $rel['strong'] ?? array() ) ) );
		$data['config'] = array( 'defaults' => $c['defaults'], 'keywords' => $search, 'sources' => $sources, 'categories' => $c['categories'], 'sections' => $c['sections'], 'press' => $c['press'], 'topic_tags' => (array) ( $c['topic_tags'] ?? array() ) );
		return $data;
	}
);
