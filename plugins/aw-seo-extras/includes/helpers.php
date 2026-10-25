<?php
/**
 * Shared helpers: text cutting, page numbers, a page's own intro, the site's naming rules, and share images.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kits_term_url' ) ) { // Kits Core's helper (a term's address, '' for a WP_Error); here in case Kits Core is not loaded
	function kits_term_url( $term, $taxonomy = '' ) {
		$url = get_term_link( $term, $taxonomy );
		return is_wp_error( $url ) ? '' : (string) $url;
	}
}

/** Longest search description, in characters (155: Google's desktop snippet fits about 151 of ours, so more would be cut). */
const AW_SEO_DESC_MAX = 155;

/**
 * Cut text at a word boundary to at most $max characters, adding $more ("…") when cut.
 *
 * @param string $text Plain text.
 * @param int    $max  Maximum length.
 * @param string $more Added when the text was cut ('' for none).
 * @return string
 */
function aw_seo_cut( $text, $max, $more = '…' ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$room = $max - mb_strlen( $more );
	$cut  = mb_substr( $text, 0, $room );
	$sp   = mb_strrpos( $cut, ' ' );
	if ( ' ' !== mb_substr( $text, $room, 1 ) && false !== $sp && $sp > $max * 0.6 ) { // never end inside a word
		$cut = mb_substr( $cut, 0, $sp );
	}
	$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', $cut );
	if ( '' === $more && substr_count( $cut, '(' ) > substr_count( $cut, ')' ) ) {
		$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', mb_substr( $cut, 0, mb_strrpos( $cut, '(' ) ) ); // no half bracket
	}
	return $cut . $more;
}

/** Page number of a list view (hub Page, Directory place, category): 1 on the first page. */
function aw_seo_page_number() {
	return max( 1, (int) get_query_var( 'paged' ), is_singular() ? 1 : (int) get_query_var( 'page' ) );
}

/** " – Page N" for the second and later pages of a list, '' otherwise. */
function aw_seo_page_suffix() {
	$n = aw_seo_page_number();
	return $n > 1 ? ' – Page ' . $n : '';
}

/**
 * A title with its page number on later pages: " Page N" before " – Animal Writes" (the only separator a title has),
 * else at the end; unchanged when it already names the page.
 */
function aw_seo_title_with_page( $text ) {
	$n = aw_seo_page_number();
	if ( $n < 2 || false !== stripos( $text, 'Page ' . $n ) ) {
		return $text;
	}
	$brand = ' – ' . get_bloginfo( 'name' );
	return mb_substr( $text, -mb_strlen( $brand ) ) === $brand ? mb_substr( $text, 0, -mb_strlen( $brand ) ) . ' Page ' . $n . $brand : $text . ' Page ' . $n;
}

/**
 * A title without separators of its own (owner, 15 Oct 2026: the only separator in a search title is the " – " before
 * "Animal Writes"): a publisher's name appended to a headline is dropped; " | ", spaced dashes, a colon or semicolon
 * joining two parts become a space; brackets are opened ("Representative (AWBI)" → "Representative AWBI"). Hyphens
 * inside words, times (10:30) and commas inside a headline or a number (Rs 5,000) are kept.
 *
 * @param string $text      Title.
 * @param string $publisher Publisher name to drop when it is appended.
 * @return string
 */
function aw_seo_unseparate( $text, $publisher = '' ) {
	$t = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	$publisher = trim( (string) $publisher );
	if ( '' !== $publisher ) {
		$t = preg_replace( '/\s*(?:\||:|;|\s[–—-]|[–—])\s*' . preg_quote( $publisher, '/' ) . '\.?$/iu', '', $t );
	}
	$t = preg_replace( '/\s*\|\s*[\p{L}\p{N}&\']+(?:\s[\p{L}\p{N}&\']+)?\s*$/u', '', $t ); // a section label after the last pipe ("… | Trending", "… | India News")
	$t = preg_replace( '/\s*\|\s*/u', ' ', $t );
	$t = preg_replace( '/\s+[–—-]+\s+|(?<=\p{L})\s*[–—]\s*(?=\p{L})/u', ' ', $t ); // a range such as 2025–26 stays
	$t = preg_replace( '/(?<=\S)\s*[:;]\s+/u', ' ', $t );
	$t = preg_replace( '/[(\[]\s*([^()\[\]]*?)\s*[)\]]/u', '$1', $t );
	$t = preg_replace( '/\s+&\s+/u', ' and ', $t ); // "and", never "&" (owner, 15 Oct 2026)
	return (string) preg_replace( '/^[\s\-–—:;|,]+|[\s\-–—:;|,]+$/u', '', preg_replace( '/\s+/u', ' ', $t ) ); // not trim(): its byte list would eat the opening quote of "‘Supreme Court’…" (owner, 15 Oct 2026)
}

/**
 * A title without hyphens (owner, 18 Oct 2026, standing: no extra dashes or hyphens): a hyphen or dash joining two
 * words becomes a space ("Human-Elephant" → "Human Elephant", "WII-SACON" → "WII SACON", "Bhaniyawala–Jollygrant" →
 * "Bhaniyawala Jollygrant"); a year range becomes "2025 to 2026" (also "2025-26"), another number range "5 to 10".
 * With $case (a title already in Title Case) the word after the hyphen starts with a capital, unless it is a
 * connector or a unit ("Bull-and-Horse" → "Bull and Horse", "8.5-kg" → "8.5 kg"). The " – " before the site name, URLs
 * and the text of a slug are not touched (only a dash directly between two letters or digits is).
 *
 * @param string $text Title.
 * @param bool   $case Capitalise the word after a removed hyphen.
 * @return string
 */
function aw_seo_no_hyphens( $text, $case = true ) {
	$t = (string) $text;
	if ( ! preg_match( '/[\p{L}\p{N}]\p{Pd}|\p{Pd}\s*[\p{L}\p{N}]/u', $t ) ) {
		return $t;
	}
	$d = '[-\x{2010}-\x{2015}\x{2212}]';
	$t = preg_replace_callback( '/(?<![\p{L}\p{N}.])(\d{4})\s*' . $d . '\s*(\d{4})(?![\p{L}\p{N}])/u', fn( $m ) => $m[1] . ' to ' . $m[2], $t );
	$t = preg_replace_callback( '/(?<![\p{L}\p{N}.])(\d{4})\s*' . $d . '\s*(\d{2})(?![\p{L}\p{N}])/u', fn( $m ) => $m[1] . ' to ' . substr( $m[1], 0, 2 ) . $m[2], $t );
	$t = preg_replace( '/(?<![\p{L}\p{N}.])(\d+(?:\.\d+)?)\s*' . $d . '\s*(\d+(?:\.\d+)?)(?![\p{L}\p{N}])/u', '$1 to $2', $t );
	$units = array( 'kg', 'km', 'cm', 'mm', 'ml', 'mg', 'g', 'm', 'kw', 'mw' );
	$t     = preg_replace_callback(
		'/(?<=[\p{L}\p{N}])' . $d . '\s*(?=[\p{L}\p{N}])|(?<=\p{L})' . $d . '\s+(?=\p{L})/u',
		fn() => ' ',
		$case ? preg_replace_callback(
			'/(?<=[\p{L}\p{N}])' . $d . '(\p{Ll})([\p{L}\p{N}\x{2019}\x{27}]*)/u',
			function ( $m ) use ( $units ) {
				$word = $m[1] . $m[2];
				return '-' . ( in_array( $word, AW_SEO_SMALL_WORDS, true ) || in_array( $word, $units, true ) ? $word : mb_strtoupper( $m[1] ) . $m[2] );
			},
			$t
		) : $t
	);
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

/** A title (with or without " – Animal Writes" at the end) without hyphens; the separator before the site name stays. */
function aw_seo_no_hyphens_title( $title, $case = true ) {
	$brand = ' – ' . get_bloginfo( 'name' );
	$title = (string) $title;
	if ( mb_substr( $title, -mb_strlen( $brand ) ) === $brand ) {
		return aw_seo_no_hyphens( mb_substr( $title, 0, -mb_strlen( $brand ) ), $case ) . $brand;
	}
	return aw_seo_no_hyphens( $title, $case );
}

/** $text with the page suffix, unless it already names the page. */
function aw_seo_with_page( $text ) {
	$suffix = aw_seo_page_suffix();
	return ( '' === $suffix || false !== stripos( $text, 'Page ' . aw_seo_page_number() ) ) ? $text : $text . $suffix;
}

/**
 * The page's own intro: its first meaningful paragraph(s), skipping boilerplate ("Last updated…", short labels and
 * button text), headings and shortcodes. Empty when the page has no such paragraph.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function aw_seo_intro( $post ) {
	$content = (string) $post->post_content;
	$skip    = '/^(last\s+(updated|reviewed|modified)|updated( on)?|effective( from| date)?|published( on)?|date)\b/i';
	if ( false === strpos( $content, '<!-- wp:' ) && false === stripos( $content, '<p' ) ) {
		return trim( wp_strip_all_tags( strip_shortcodes( $content ) ) ); // plain text: all of it
	}
	$out = '';
	if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#si', strip_shortcodes( $content ), $m ) ) {
		foreach ( $m[1] as $para ) {
			$t = trim( html_entity_decode( wp_strip_all_tags( $para ), ENT_QUOTES, 'UTF-8' ) );
			if ( mb_strlen( $t ) < 40 || preg_match( $skip, $t ) ) {
				continue;
			}
			$out = trim( $out . ' ' . $t );
			if ( mb_strlen( $out ) >= 120 ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * What the editor typed in Rank Math for the current view: post or term meta "rank_math_{$key}" ('' when empty).
 *
 * @param string $key Field, e.g. title, description, canonical_url, facebook_image_id.
 * @return string
 */
/** A hub's place, type, name-search or filtered view (Directory state/district/type pages, ?tag=, ?category_name=, ?q=). */
function aw_seo_hub_subview() {
	if ( ( function_exists( 'aw_current_place' ) && aw_current_place() ) || ( function_exists( 'aw_current_type_name' ) && aw_current_type_name() ) || get_query_var( 'aw_dir_type' ) ) {
		return true;
	}
	return ! empty( $_GET['tag'] ) || ! empty( $_GET['category_name'] ) || ! empty( $_GET['q'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

function aw_seo_typed( $key ) {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return '';
	}
	if ( is_singular() ) {
		$typed = trim( (string) get_post_meta( $id, 'rank_math_' . $key, true ) );
		if ( '' !== $typed && in_array( $key, array( 'title', 'description' ), true ) && aw_seo_is_hub_page( $id ) && aw_seo_hub_subview() ) {
			return ''; // a hub's own title and description belong to its first view only, never to its place, type or filtered views
		}
		if ( '' !== $typed && in_array( $key, array( 'title', 'description' ), true ) && aw_seo_is_hub_page( $id ) ) {
			$auto = (array) json_decode( (string) get_post_meta( $id, '_aw_seo_auto', true ), true );
			if ( ( $auto[ $key ] ?? null ) === $typed ) {
				return ''; // a hub's stored copy of its own page-1 title (titles.php): built live, not typed
			}
		}
		return $typed;
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$typed = trim( (string) get_term_meta( $id, 'rank_math_' . $key, true ) );
		$auto  = (array) json_decode( (string) get_term_meta( $id, '_aw_seo_auto', true ), true );
		return ( '' !== $typed && ( $auto[ $key ] ?? null ) === $typed ) ? '' : $typed; // a category's stored copy (programmatic.php): built live, not typed
	}
	return '';
}

/**
 * Whether the current view is a list (a section hub Page, a Directory place or type page, an archive) rather than one
 * item. Items keep their title and description in Rank Math's own fields; lists are built here.
 *
 * @return bool
 */
function aw_seo_is_list_view() {
	return ! is_singular() || ( function_exists( 'aw_hub_type' ) && aw_hub_type() );
}

/** An item hidden from search engines (Rank Math's robots field). */
function aw_is_noindex_item( $id ) {
	return in_array( 'noindex', (array) get_post_meta( $id, 'rank_math_robots', true ), true );
}

/* ---------- Naming rules (SEO audits, 10 Oct 2026) ---------- */

/** "{Text} – Animal Writes". */
function aw_seo_branded( $text ) {
	return $text . ' – ' . get_bloginfo( 'name' );
}

/** Words a cut title must not end on ("… rescued in", "… rabies prevention &"). */
const AW_SEO_DANGLING = '&|\+|and|or|but|nor|of|the|a|an|to|in|on|at|by|for|with|from|into|onto|over|under|after|before|amid|as|via|per|than|that|which|who|whose|its|their|his|her|is|are|was|were|be|has|have|had|will|can|against|between|near|about|across|during|without|within|vs|v|every|each|all|some|any|this|these|those|our|your|my|what|how|why|when|where|whether|if|not|also|so|very|more|most|such|both|says|said';

/**
 * A title cut to at most $max characters at a word boundary that leaves a whole phrase: no dangling connector
 * ("and", "of", "to", "&" …), no trailing comma, colon or dash, no half bracket.
 */
function aw_seo_cut_title( $text, $max = 60 ) {
	$cut  = aw_seo_cut( $text, $max, '' );
	$full = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( $cut === $full ) {
		return $cut;
	}
	do {
		$before = $cut;
		$cut    = preg_replace( '/(?:\s+(?:' . AW_SEO_DANGLING . ')|[\s,;:|\/\x{2013}\x{2014}-]+)$/iu', '', $cut );
	} while ( $cut !== $before && mb_strlen( $cut ) > $max * 0.5 );
	if ( substr_count( $cut, '(' ) > substr_count( $cut, ')' ) ) {
		$cut = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', mb_substr( $cut, 0, mb_strrpos( $cut, '(' ) ) );
	}
	foreach ( array( '“' => '”', '‘' => '’' ) as $open => $close ) { // no half quote: an opening mark after the last closing one
		$o = mb_strrpos( $cut, $open );
		$c = mb_strrpos( $cut, $close );
		if ( false !== $o && ( false === $c || $o > $c ) ) {
			$cut = trim( mb_substr( $cut, 0, $o ) . mb_substr( $cut, $o + 1 ) );
		}
	}
	if ( 1 === substr_count( $cut, '"' ) % 2 ) {
		$o   = mb_strrpos( $cut, '"' );
		$cut = trim( mb_substr( $cut, 0, $o ) . mb_substr( $cut, $o + 1 ) );
	}
	return $cut;
}

/**
 * One rule for news, Library and Resources headlines, kept within 60 characters: "{Headline} – Animal Writes" when
 * that fits, the headline alone when only it fits, else the headline cut at a whole phrase (aw_seo_cut_title).
 */
function aw_seo_headline_title( $title ) {
	$t = aw_seo_headline_case( aw_seo_plain( $title ) ); // Title Case like every other title on the site
	return mb_strlen( aw_seo_branded( $t ) ) <= 60 ? aw_seo_branded( $t ) : $t; // over 60: no site name; a headline is never cut (owner, 15 Oct 2026)
}

/** Words a headline in Title Case keeps lower case (not first). */
const AW_SEO_SMALL_WORDS = array( 'a', 'an', 'the', 'and', 'or', 'nor', 'but', 'of', 'in', 'on', 'at', 'to', 'for', 'by', 'with', 'from', 'as', 'vs', 'via', 'per', 'into', 'onto' );

/**
 * A headline in Title Case: each word capitalised except short connectors; acronyms, numbers and words with capitals
 * inside (NHAI, CBI-DRI, iPhone) stay as written. A headline written all in capitals is lowered first.
 */
function aw_seo_headline_case( $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', aw_seo_no_hyphens( (string) $text ) ) ); // no hyphens in a title (owner, 18 Oct 2026)
	if ( ! preg_match( '/\p{Ll}/u', $text ) ) {
		$text = mb_strtolower( $text );
	}
	$words = explode( ' ', $text );
	foreach ( $words as $i => $w ) {
		$bare = mb_strtolower( trim( $w, '‘’“”"\'(),:;' ) );
		if ( $i > 0 && in_array( $bare, AW_SEO_SMALL_WORDS, true ) && ! preg_match( '/[:;–—-]$/u', $words[ $i - 1 ] ) ) {
			$words[ $i ] = preg_match( '/^[‘“"(]?\p{Lu}\p{Ll}*[,:;)]?$/u', $w ) ? mb_strtolower( $w ) : $w; // "Of" → "of"; "OR", "of" as written
			continue;
		}
		$words[ $i ] = preg_replace_callback( '/^([‘“"(]?)(\p{Ll})/u', fn( $m ) => $m[1] . mb_strtoupper( $m[2] ), $w );
	}
	return implode( ' ', $words );
}

/**
 * The search title of a Library item or a Resource, built from its own title (owner, 15 Oct 2026): Title Case, a
 * trailing "dated …" date dropped (the date is on the page), " – Animal Writes" when the whole fits within 60.
 * - Under 50 characters with the site name: the item's kind is added when that still fits ("… Circular",
 *   "… Research Summary"), without a separator.
 * - Over 60: the headline alone; then its first part (before ":", " – " or ";") when that part has at least 30
 *   characters; else the whole headline, never cut inside.
 * - No separator but the " – " before the site name (aw_seo_unseparate).
 *
 * @param WP_Post $post Library item or Resource.
 * @return string
 */
function aw_seo_resource_title( $post ) {
	$t = aw_seo_plain( $post->post_title );
	$t = preg_replace( '/[\s,(–—-]*(?:dated\b)?[\s:–—-]*\d{1,2}\.\d{1,2}\.\d{4}\)?\.?$/u', '', $t ); // "… dated - 11.08.2026"
	$t = preg_replace( '/[\s,;:.–—-]*\bdated$/u', '', $t );
	$t = trim( preg_replace( '/^[“"]\s*(.*?)\s*[”"]$/u', '$1', trim( $t ) ), " \t.,;:" );
	$t = aw_seo_headline_case( $t );
	$max = 60;
	if ( mb_strlen( aw_seo_branded( $t ) ) <= $max ) {
		$kind = aw_seo_resource_kind( $post );
		$t = aw_seo_unseparate( $t );
		if ( mb_strlen( aw_seo_branded( $t ) ) < 50 && '' !== $kind && false === mb_stripos( $t, $kind ) && mb_strlen( aw_seo_branded( $t . ' ' . $kind ) ) <= $max ) {
			return aw_seo_branded( $t . ' ' . $kind );
		}
		return aw_seo_branded( $t );
	}
	if ( mb_strlen( aw_seo_unseparate( $t ) ) <= $max ) {
		return aw_seo_unseparate( $t );
	}
	$parts = preg_split( '/\s*(?::|;|\s[–—-])\s+/u', $t );
	$first = aw_seo_unseparate( trim( (string) ( $parts[0] ?? '' ) ) );
	if ( count( $parts ) > 1 && mb_strlen( $first ) >= 30 ) {
		if ( mb_strlen( aw_seo_branded( $first ) ) <= $max ) {
			return aw_seo_branded( $first );
		}
		if ( mb_strlen( $first ) <= $max ) {
			return $first;
		}
	}
	return aw_seo_unseparate( $t ); // longer than 60 and no whole first part that fits: the name is never cut
}

/** What a Library item or Resource is, for its title: the Library type ("Circular"), "Research Summary", "Guide", "FAQ". */
function aw_seo_resource_kind( $post ) {
	$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'all' ) );
	$by   = is_wp_error( $tags ) ? array() : array_column( $tags, 'name', 'slug' );
	if ( 'post' === $post->post_type ) {
		foreach ( array( 'research' => 'Research Summary', 'faq' => 'FAQ', 'guide' => 'Guide' ) as $slug => $label ) {
			if ( isset( $by[ $slug ] ) ) {
				return $label;
			}
		}
		return '';
	}
	foreach ( array( 'act', 'rule', 'notification', 'circular', 'advisory', 'judgment', 'order', 'official-release', 'template' ) as $slug ) {
		if ( isset( $by[ $slug ] ) ) {
			return aw_seo_headline_case( $by[ $slug ] );
		}
	}
	return '';
}

/** The first of $titles within $max characters (the last one, cut at a whole phrase, when none fits). */
function aw_seo_fit( array $titles, $max = 60 ) {
	foreach ( $titles as $t ) {
		if ( mb_strlen( $t ) <= $max ) {
			return $t;
		}
	}
	return aw_seo_cut_title( (string) end( $titles ), $max );
}

/** "{Name} in {District}" or "{Name} in {State}" (no comma as a separator; the place is not repeated when the name has it). */
function aw_seo_listing_name( $id ) {
	$name = preg_replace( '/[\s,;:\x{2013}\x{2014}-]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) ); // organisation names keep their hyphens (owner, 18 Oct 2026)
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = $district ? $district : $state;
	return ( '' !== $where && false === mb_stripos( $name, $where ) ) ? $name . ' in ' . $where : $name;
}

/** Fixed titles of the section hubs (post type => title). */
const AW_HUB_TITLES = array(
	'aw_news'        => 'Animal Welfare News from India – Animal Writes',
	'aw_legal'       => 'Animal Laws and Court Orders in India – Animal Writes',
	'post'           => 'Animal Welfare Guides & Research in India – Animal Writes',
	'aw_listing'     => 'Directory of 5,000+ Animal Welfare NGOs in India',
	'aw_opportunity' => 'Animal Welfare Volunteering and Jobs in India',
	'aw_press'       => 'Animal Writes in the News',
	'aw_campaign'    => 'Animal Welfare Campaigns in India – Animal Writes',
);

/** Fixed descriptions of the section hubs that are not built from their intro. */
const AW_HUB_DESCRIPTIONS = array(
	'aw_opportunity' => 'Volunteer, foster, intern or find a job with animal welfare organisations across India. Each opportunity lists its role, location and how to apply.',
	'aw_press'       => 'Press coverage of Animal Writes campaigns for animals in India: news articles, videos and social posts.',
	'aw_listing'     => 'Find %s animal welfare NGOs, shelters, rescuers, Gaushalas and government vets in every state and district of India, with phone, address and services.',
);

/** Descriptions of the content types' own archives (post type => description). */
const AW_ARCHIVE_DESCRIPTIONS = array(
	'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
	'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
	'post'           => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
	'aw_campaign'    => 'Citizen campaigns for animals in India: what each one asks for, how to take part and what it achieved.',
	'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
	'aw_listing'     => 'Animal welfare organisations, Gaushalas and government vets across India.',
);

/** Plural of a listing type for a sentence: "Gaushalas" (always capitalised, owner 15 Oct 2026), "ABC centres", "veterinary dispensaries". */
function aw_seo_type_plural( $slug ) {
	$fixed = array(
		'veterinary'             => 'veterinary hospitals and dispensaries',
		'veterinary-office'      => 'government veterinary offices',
		'mobile-veterinary-unit' => 'mobile veterinary units',
		'wildlife-rescue'        => 'wildlife rescue groups',
		'emergency-rescue'       => 'emergency rescue groups',
		'legal-aid'              => 'legal aid groups',
	);
	if ( isset( $fixed[ $slug ] ) ) {
		return $fixed[ $slug ];
	}
	$name  = trim( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_listing_type_name( $slug ) ) );
	$words = array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', $name ) );
	$last  = (string) array_pop( $words );
	if ( preg_match( '/[^aeiou]y$/', $last ) ) {
		$last = substr( $last, 0, -1 ) . 'ies';
	} elseif ( preg_match( '/(s|x|ch|sh)$/', $last ) ) {
		$last .= 'es';
	} elseif ( '' !== $last ) {
		$last .= 's';
	}
	return trim( implode( ' ', $words ) . ' ' . $last );
}

/** The categories with the most published items of a type, as lower-case names (cached for 12 hours). */
function aw_seo_top_categories( $type ) {
	$key = 'aw_seo_categories_' . $type;
	$out = get_transient( $key );
	if ( false === $out ) {
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = %s JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = %s AND p.post_status = 'publish' GROUP BY t.term_id ORDER BY COUNT(*) DESC LIMIT 4", aw_seo_category_tax(), $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array_map( fn( $n ) => implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ) ) ) ), (array) $names );
		set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	}
	return (array) $out;
}

/**
 * Hub description built from its own intro (the hub Page's excerpt, as shown under its H1) and the list's facts:
 * "{intro} {N stories} on {category}, {category} and {category}." up to AW_SEO_DESC_MAX (155) characters.
 */
function aw_seo_hub_intro_description( $type ) {
	$page  = get_queried_object();
	$intro = aw_seo_plain( ( $page instanceof WP_Post && '' !== trim( $page->post_excerpt ) ) ? $page->post_excerpt : aw_hub_intro( $type ) );
	$count = 'post' === $type ? aw_seo_resources_count_label() : ''; // guides and research summaries counted apart
	$count = '' !== $count ? $count : ( function_exists( 'aw_hub_count_label' ) ? aw_hub_count_label( $type ) : '' );
	if ( '' === $count ) {
		return $intro;
	}
	$cats = array_values( array_filter( aw_seo_top_categories( $type ), fn( $t ) => false === strpos( $t, ' and ' ) ) ); // "laws and rules" would blur the list
	$best   = $intro . ' ' . $count . '.';
	for ( $n = 1; $n <= count( $cats ); $n++ ) {
		$list = array_slice( $cats, 0, $n );
		$last = array_pop( $list );
		$line = $intro . ' ' . $count . ' on ' . ( $list ? implode( ', ', $list ) . ' and ' : '' ) . $last . '.';
		if ( mb_strlen( $line ) > AW_SEO_DESC_MAX ) {
			break;
		}
		$best = $line;
	}
	return $best;
}

/** AWBI-recognised listing without a description of its own: one line of its facts ('' when it does not apply). */
function aw_seo_awbi_description( $id, $desc ) {
	$code = trim( (string) get_post_meta( $id, 'aw_awbi_code', true ) );
	if ( '' === $code || get_post_meta( $id, 'aw_awbi_dropped', true ) || has_excerpt( $id ) || ( '' !== trim( (string) $desc ) && ! preg_match( '/^Recognised by the Animal Welfare Board of India/i', trim( wp_strip_all_tags( (string) $desc ) ) ) ) ) {
		return '';
	}
	$types = function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
	if ( $types && count( $types ) > 1 ) { // the specific type (gaushala) before the general one
		usort( $types, fn( $a, $b ) => (int) ( 'animal-welfare-organisation' === $a->slug ) - (int) ( 'animal-welfare-organisation' === $b->slug ) );
	}
	$type  = $types ? implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', trim( preg_replace( '/\s*\(.*?\)/', '', $types[0]->name ) ) ) ) ) : 'organisation';
	list( $state, $district ) = aw_item_place_names( $id );
	$where = implode( ', ', array_unique( array_filter( array( $district, $state ) ) ) ); // Chandigarh, Delhi: the district and state share a name
	$name  = preg_replace( '/[\s,;:]+$/u', '', aw_seo_plain( get_post_field( 'post_title', $id ) ) );
	$line  = sprintf( '%1$s is an AWBI-recognised %2$s%3$s (AWBI code %4$s).', $name, $type, $where ? ' in ' . $where : '', $code );
	return mb_strlen( $line ) <= 120 ? $line . ' Address, contact and how to help.' : $line; // whole sentences only
}

/** "{Listing type} in {District}, {State}. {Services}. Contact details, location and how to help." ('' without type or place). */
function aw_listing_fact_description( $id ) {
	$types = function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array();
	$type  = $types ? $types[0]->name : '';
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = implode( ', ', array_unique( array_filter( array( $district, $state ) ) ) ); // Chandigarh, Delhi: the district and state share a name
	if ( '' === $type && '' === $where ) {
		return '';
	}
	$lead = $type ? $type . ( $where ? ' in ' . $where : '' ) : 'In ' . $where;
	$svc  = function_exists( 'aw_listing_service_names' ) ? aw_listing_service_names( $id ) : array();
	$svc  = $svc ? implode( ', ', array_slice( $svc, 0, 4 ) ) : '';
	return $lead . '. ' . ( $svc ? ucfirst( $svc ) . '. ' : '' ) . 'Contact details, location and how to help.';
}

/* ---------- Share images (og:image and the article schema use the same one) ---------- */

/**
 * [id, url, width, height] of an attachment at share size, or null.
 *
 * @param int    $att  Attachment ID.
 * @param string $size Image size.
 * @return array|null
 */
function aw_seo_attachment( $att, $size = 'large' ) {
	$src = $att ? wp_get_attachment_image_src( (int) $att, $size ) : false;
	if ( ! $src || empty( $src[0] ) ) {
		return null;
	}
	if ( empty( $src[1] ) || empty( $src[2] ) ) {
		$meta   = wp_get_attachment_metadata( (int) $att );
		$src[1] = (int) ( $meta['width'] ?? 0 );
		$src[2] = (int) ( $meta['height'] ?? 0 );
	}
	return array( 'id' => (int) $att, 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
}

/**
 * A share picture every app shows (owner, 19 + 21 Oct 2026: og:image and twitter:image are never AVIF, site-wide).
 * Uploads are turned into AVIF (Modern Image Formats), which Facebook, WhatsApp and LinkedIn don't reliably show, so an
 * AVIF picture from our uploads goes out as its original upload (the WebP, JPEG or PNG kept beside it); without one, a
 * 1200x630 JPEG is made once and kept beside it ({name}-og.jpg). Anything that is not an AVIF upload passes through.
 *
 * @param string $url Picture address.
 * @return array [url, width, height, mime]; width, height 0 and mime '' when unchanged.
 */
function aw_seo_share_safe_url( $url ) {
	$plain = strtok( (string) $url, '?' );
	if ( ! preg_match( '/\.avif$/i', (string) $plain ) ) {
		return array( $url, 0, 0, '' );
	}
	$up   = wp_get_upload_dir();
	$base = set_url_scheme( $up['baseurl'], 'https' );
	$abs  = set_url_scheme( $plain, 'https' );
	if ( 0 !== strpos( $abs, $base ) ) {
		return array( $url, 0, 0, '' );
	}
	$path = $up['basedir'] . substr( $abs, strlen( $base ) );
	$stem = preg_replace( '/(-\d+x\d+)?\.avif$/i', '', $path ); // the upload's own name, without a size or format
	$mime = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'png' => 'image/png' );
	foreach ( $mime as $ext => $type ) {
		if ( file_exists( $stem . '.' . $ext ) ) {
			$size = wp_getimagesize( $stem . '.' . $ext );
			return array( $base . substr( $stem, strlen( $up['basedir'] ) ) . '.' . $ext, (int) ( $size[0] ?? 0 ), (int) ( $size[1] ?? 0 ), $type );
		}
	}
	// Made at upload (wp_generate_attachment_metadata, below); built here only for older uploads, or if it failed then.
	$out = file_exists( $stem . '-og.jpg' ) ? $stem . '-og.jpg' : aw_seo_share_jpeg_build( $path );
	if ( '' === $out ) {
		return array( $url, 0, 0, '' );
	}
	$size = wp_getimagesize( $out );
	return array( $base . substr( $out, strlen( $up['basedir'] ) ), (int) ( $size[0] ?? 0 ), (int) ( $size[1] ?? 0 ), 'image/jpeg' );
}

/**
 * The 1200x630 share JPEG ({name}-og.jpg) of an AVIF upload with no original kept beside it.
 *
 * @param string $path AVIF file (the full upload or one of its sizes).
 * @return string The JPEG's path ('' when the file is not such an AVIF or the JPEG could not be made).
 */
function aw_seo_share_jpeg_build( $path ) {
	if ( ! preg_match( '/\.avif$/i', (string) $path ) || ! file_exists( $path ) ) {
		return '';
	}
	$stem = preg_replace( '/(-\d+x\d+)?\.avif$/i', '', $path );
	foreach ( array( 'jpg', 'jpeg', 'webp', 'png' ) as $ext ) {
		if ( file_exists( $stem . '.' . $ext ) ) {
			return ''; // the original is shared as it is
		}
	}
	$out = $stem . '-og.jpg';
	if ( file_exists( $out ) ) {
		return $out;
	}
	$ed = wp_get_image_editor( $path );
	if ( is_wp_error( $ed ) ) {
		return '';
	}
	$ed->resize( 1200, 630, true );
	$ed->set_quality( 85 );
	add_filter( 'image_editor_output_format', '__return_empty_array', 99 ); // or Modern Image Formats saves AVIF again
	$saved = $ed->save( $out, 'image/jpeg' );
	remove_filter( 'image_editor_output_format', '__return_empty_array', 99 );
	return is_wp_error( $saved ) || ! file_exists( $out ) ? '' : $out;
}

/**
 * Share JPEGs of an AVIF attachment: one per file name stem (the full upload, and its sizes, whose stem differs when
 * WordPress scaled a big upload to "-scaled"). Returns how many were made or already there.
 *
 * @param int        $att  Attachment ID.
 * @param array|null $meta Attachment metadata (read when null).
 * @return int
 */
function aw_seo_share_jpegs_for( $att, $meta = null ) {
	$file = (string) get_attached_file( (int) $att );
	if ( ! preg_match( '/\.avif$/i', $file ) ) {
		return 0;
	}
	$meta  = is_array( $meta ) ? $meta : (array) wp_get_attachment_metadata( (int) $att );
	$paths = array( $file );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$paths[] = path_join( dirname( $file ), (string) $size['file'] );
		}
	}
	$made = array();
	foreach ( $paths as $path ) {
		$out = aw_seo_share_jpeg_build( $path );
		if ( '' !== $out ) {
			$made[ $out ] = 1;
		}
	}
	return count( $made );
}

/**
 * The share JPEG is made when an AVIF picture is uploaded, not on the first share-preview request (audit item 14,
 * owner 25 Oct 2026). Runs after the sizes (and Modern Image Formats' AVIF) are made; never changes the metadata.
 */
add_filter(
	'wp_generate_attachment_metadata',
	function ( $meta, $att ) {
		aw_seo_share_jpegs_for( (int) $att, $meta );
		return $meta;
	},
	100,
	2
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** `wp aw-seo share-jpegs`: makes the share JPEG of every AVIF upload kept without its original (existing uploads). */
	WP_CLI::add_command(
		'aw-seo share-jpegs',
		function () {
			$n = 0;
			$f = 0;
			// Every picture: a converted upload may keep its original type while its file is AVIF; others are skipped.
			foreach ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids' ) ) as $att ) {
				$c  = aw_seo_share_jpegs_for( $att );
				$n += $c;
				$f += $c ? 1 : 0;
			}
			WP_CLI::success( "$f AVIF uploads have their share JPEG ($n files, made now or already there)." );
		}
	);
}

/** The site's default share image (Settings), as aw_seo_attachment(). */
function aw_seo_default_image() {
	return aw_seo_attachment( (int) get_option( 'aw_default_share_image' ), 'full' ); // made at share size (1200×630)
}

/** Share image of a listing without a logo of its own (owner, 15 Oct 2026): banner-aw_listing-wide.webp, the Directory band picture. */
const AW_SEO_LISTING_SHARE_IMAGE = 8814;

/** Post types whose share image is the publisher's own image, linked (News, Library, research, Opportunities). */
const AW_SEO_SOURCE_IMAGE_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity' );

/**
 * The item's own image (owner, 15 Oct 2026: ingested items use the publisher's image, linked; never a category photo
 * in place of the item's own):
 *  1. the share image chosen in Rank Math;
 *  2. News, Library, research and Opportunities: the publisher's image (aw_source_image, checked in cc0-images.php;
 *     read from the source page's og:image when ingest stored none);
 *  3. the featured image (a guide's photo, a listing's logo, an ingested item's own CC0 photo);
 *  4. Library: the hosted PDF's first page;
 *  5. Pages: the first picture in the page's own content (the Volunteer band photo, for example).
 * Null when the item has none (the site share image is used then).
 *
 * @param int $id Post ID.
 * @return array|null [id, url, width, height]; id 0 for a linked publisher image.
 */
function aw_seo_item_image( $id ) {
	$img = aw_seo_attachment( (int) get_post_meta( $id, 'rank_math_facebook_image_id', true ) );
	if ( $img ) {
		return $img;
	}
	$type = get_post_type( $id );
	if ( in_array( $type, AW_SEO_SOURCE_IMAGE_TYPES, true ) && function_exists( 'aw_publisher_image' ) ) {
		$url = aw_publisher_image( $id );
		if ( '' !== $url ) {
			return array( 'id' => 0, 'url' => $url, 'width' => (int) get_post_meta( $id, 'aw_source_image_w', true ), 'height' => (int) get_post_meta( $id, 'aw_source_image_h', true ) );
		}
	}
	$cands = array( (int) get_post_thumbnail_id( $id ) );
	if ( 'aw_legal' === $type ) {
		$cands[] = (int) get_post_meta( $id, 'aw_file_id', true );
		$cands[] = (int) get_post_meta( $id, 'aw_doc_preview', true );
	}
	if ( 'page' === $type && preg_match( '/<!-- wp:(?:image|cover|media-text) \{[^}]*?"(?:id|mediaId)":(\d+)/', (string) get_post_field( 'post_content', $id ), $m ) ) {
		$cands[] = (int) $m[1];
	}
	foreach ( $cands as $att ) {
		$img = aw_seo_attachment( $att );
		if ( $img ) {
			return $img;
		}
	}
	return null;
}

/** The share image of a category page: its category photo (term meta aw_category_image), or null. */
function aw_seo_term_image() {
	if ( ! aw_seo_is_category_page() || ! function_exists( 'aw_category_image_id' ) ) {
		return null;
	}
	$att = (int) get_term_meta( get_queried_object_id(), 'rank_math_facebook_image_id', true );
	return aw_seo_attachment( $att ? $att : (int) aw_category_image_id( get_queried_object() ) );
}

/**
 * The share image of the current view (og:image and article schema): the item's own image when it is landscape (or of
 * unknown shape), a category page's category photo, for a listing the Directory band picture, else the site share image,
 * else the site icon. Other tags keep the site share image; Directory type tags have their own, set in Rank Math's
 * Social tab (20 Oct 2026), which Rank Math prints (opengraph.php).
 *
 * @return array|null
 */
function aw_seo_image() {
	static $img = false;
	if ( false !== $img ) {
		return $img;
	}
	$img = is_singular() ? aw_seo_item_image( get_queried_object_id() ) : aw_seo_term_image();
	if ( $img && $img['height'] > $img['width'] && ! ( is_singular() && get_post_meta( get_queried_object_id(), 'rank_math_facebook_image_id', true ) ) ) {
		$img = null; // portrait: share cards are landscape, so the site image is shared instead (the page keeps its photo)
	}
	if ( ! $img && is_singular( 'aw_listing' ) ) {
		$img = aw_seo_attachment( AW_SEO_LISTING_SHARE_IMAGE, 'full' ); // a listing without its own logo: the Directory's wide band picture
	}
	if ( ! $img ) {
		$img = aw_seo_default_image();
	}
	if ( ! $img ) {
		$img = aw_seo_attachment( (int) get_option( 'site_icon' ) );
	}
	if ( $img ) {
		list( $url, $w, $h, $type ) = aw_seo_share_safe_url( $img['url'] );
		if ( $url !== $img['url'] ) {
			$img = array_merge( $img, array( 'url' => $url, 'type' => $type ), $w ? array( 'width' => $w, 'height' => $h ) : array() );
		}
	}
	return $img;
}

if ( ! function_exists( 'aw_seo_category_tax' ) ) {
	/**
	 * The taxonomy that holds the site's categories: WordPress's own category (since 15 Oct 2026).
	 *
	 * @return string
	 */
	function aw_seo_category_tax() {
		return 'category';
	}
}

/** Whether the current view is a category page. */
function aw_seo_is_category_page() {
	$tax = aw_seo_category_tax();
	return 'category' === $tax ? is_category() : is_tax( $tax );
}

/** The page that lists every category (/categories/), or ''. */
function aw_seo_categories_url() {
	foreach ( array( 'categories' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
	}
	return '';
}

/* ---------- Image alt and title: never the page title ---------- */

/*
 * Rank Math's Image SEO (General → Images, "add missing alt / title") fills every empty alt and title in the page
 * with the page's title: card pictures read "Library" or "Media & PR", with titles "Media & PR 1", "Media & PR 2".
 * Our empty alts are deliberate (a decorative picture beside its own linked title); pictures that carry information
 * get their true alt in the markup, and every image in the media library has its own alt. So Rank Math's two
 * attribute filters are taken off after it adds them (it hooks 'wp' at 9999); it never changed a non-empty alt.
 */
add_action(
	'wp',
	function () {
		foreach ( array( 'the_content', 'post_thumbnail_html', 'woocommerce_single_product_image_thumbnail_html' ) as $hook ) {
			global $wp_filter;
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $cb ) {
					$fn = $cb['function'];
					if ( is_array( $fn ) && is_object( $fn[0] ) && 'add_img_attributes' === $fn[1] && false !== stripos( get_class( $fn[0] ), 'RankMath' ) ) {
						remove_filter( $hook, $fn, $priority );
					}
				}
			}
		}
	},
	10000
);
