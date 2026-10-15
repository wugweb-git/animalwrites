<?php
/**
 * Settings → Sourcing, the one place for what the workers fetch (S3, 8 Oct 2026). Kits Core gives the screen with
 * Publishers (whose articles are accepted), Keywords (the one word list) and the Change log; this adds News feeds (RSS and Google News queries), Library sources
 * (AWBI, PIB, Gazette, courts), Opportunity sources, Press-discovery queries and Topics (keyword → topic).
 *
 * The lists live in the option aw_sources, in the same shape as the workers' sources.yaml (defaults, keywords,
 * sources, topics, press), seeded once from it (data/sources-seed.json). The workers read them at the start of every
 * run from the private, authenticated policy endpoint (aw/v1/sourcing-policy) once "Workers use these lists" is on;
 * until then, and whenever the site cannot be reached, they use sources.yaml. API keys stay in GitHub secrets.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The lists (seeded once from data/sources-seed.json). */
function aw_sources_config() {
	$c = get_option( 'aw_sources' );
	if ( ! is_array( $c ) ) {
		$c         = json_decode( (string) file_get_contents( AW_CORE_DIR . 'data/sources-seed.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$c['live'] = false; // the owner switches the workers over after checking
		update_option( 'aw_sources', $c, false );
	}
	return $c;
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
		$tabs['topics'] = array( 'Categories', 'aw_sources_topics_tab' );
		return $tabs;
	}
);

/** "Workers use these lists" switch, on every tab. */
add_action(
	'kits_sourcing_page_top',
	function () {
		$c = aw_sources_config();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0 12px">';
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

/** One list of sources (News feeds, Library sources or Opportunity sources). */
function aw_sources_tab( $slug ) {
	$c    = aw_sources_config();
	$type = AW_SOURCE_TABS[ $slug ][1];
	echo '<p>' . ( 'news' === $type ? 'RSS feeds and Google News searches. For a Google News search, write only the search words.' : ( 'legal' === $type ? 'Where Library documents and legal news come from. Gazette, AWBI and court-judgment sources need code to read; only their name and on/off can change here.' : 'Where opportunities come from. Only their name and on/off can change here; new kinds of site need code.' ) )
		. ' "Keyword check" keeps only items with a word from the Keywords tab (an include word or an animal word).</p>';
	echo '<table class="widefat striped"><thead><tr><th style="width:60px">On</th><th>Name</th><th>Feed URL or Google News search</th><th style="width:120px">Keyword check</th></tr></thead><tbody>';
	$i = 0;
	if ( 'opportunity' !== $type ) {
		for ( $k = 0; $k < 1; $k++, $i++ ) { // an empty row to add a source, first
			echo '<tr><td><input type="hidden" name="src[' . $i . '][id]" value=""><input type="checkbox" name="src[' . $i . '][on]" value="1" checked aria-label="On"></td><td><input type="text" class="regular-text" name="src[' . $i . '][name]" placeholder="New source name"></td><td><input type="text" class="large-text" name="src[' . $i . '][url]" placeholder="https://… feed, or Google News search words"></td><td><input type="checkbox" name="src[' . $i . '][filter]" value="1" checked aria-label="Keyword check"></td></tr>';
		}
	}

	foreach ( (array) $c['sources'] as $s ) {
		if ( ( $s['type'] ?? '' ) !== $type ) {
			continue;
		}
		$q        = aw_gnews_query( $s['url'] ?? '' );
		$editable = 'rss' === ( $s['kind'] ?? '' );
		echo '<tr><td><input type="hidden" name="src[' . $i . '][id]" value="' . esc_attr( $s['id'] ) . '"><input type="checkbox" name="src[' . $i . '][on]" value="1"' . checked( ! empty( $s['on'] ), true, false ) . ' aria-label="On"></td>'
			. '<td><input type="text" class="regular-text" name="src[' . $i . '][name]" value="' . esc_attr( $s['name'] ) . '"></td>'
			. '<td>' . ( $editable ? '<input type="text" class="large-text" name="src[' . $i . '][url]" value="' . esc_attr( $q ? $q : $s['url'] ) . '">' . ( $q ? '<span class="description">Google News search</span>' : '' ) : '<code>' . esc_html( $s['url'] ) . '</code> <span class="description">(' . esc_html( $s['kind'] ) . ')</span>' ) . '</td>'
			. '<td>' . ( $editable ? '<input type="checkbox" name="src[' . $i . '][filter]" value="1"' . checked( ! empty( $s['filter'] ), true, false ) . ' aria-label="Keyword check">' : '—' ) . '</td></tr>';
		$i++;
	}
	echo '</tbody></table>';
	submit_button( 'Save' );
}

foreach ( array_keys( AW_SOURCE_TABS ) as $aw_tab ) {
	add_action(
		'kits_sourcing_save_' . $aw_tab,
		function () use ( $aw_tab ) {
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

/** Topics: words that tag an item with a topic. */
function aw_sources_topics_tab() {
	$c = aw_sources_config();
	echo '<p>An item gets every category whose words appear in its title or summary. One category per line: <code>category-slug: word, word, word</code>. Hindi works.</p>';
	$lines = array();
	foreach ( (array) $c['topics'] as $slug => $words ) {
		$lines[] = $slug . ': ' . implode( ', ', (array) $words );
	}
	echo '<textarea name="topics" class="large-text code" rows="22">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
	submit_button( 'Save' );
}
add_action(
	'kits_sourcing_save_topics',
	function () {
		$c      = aw_sources_config();
		$old    = $c['topics'];
		$topics = array();
		foreach ( explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['topics'] ?? '' ) ) ) as $line ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( preg_match( '/^\s*([a-z0-9-]+)\s*:\s*(.+)$/u', $line, $m ) ) {
				$topics[ $m[1] ] = array_values( array_filter( array_map( 'trim', explode( ',', $m[2] ) ) ) );
			}
		}
		$what = array();
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $topics ) ) ) as $slug ) {
			$what[] = kits_sourcing_diff( $slug, $old[ $slug ] ?? array(), $topics[ $slug ] ?? array() );
		}
		$c['topics'] = $topics;
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'topics', implode( ' · ', array_filter( $what ) ) );
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
		$data['config'] = array( 'defaults' => $c['defaults'], 'keywords' => $words, 'sources' => $sources, 'topics' => $c['topics'], 'press' => $c['press'] );
		return $data;
	}
);
