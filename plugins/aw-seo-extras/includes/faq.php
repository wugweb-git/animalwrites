<?php
/**
 * FAQ system (owner-approved spec, 20 Oct 2026). One FAQ per page, 3 to 6 questions, or none.
 *
 * Two kinds of question:
 * - Data-driven (Directory listings, Library documents): a registry in code, aw_faq_registry(). Each entry is a question
 *   template, the fields it needs, an answer built from those fields, a priority (Search Insights demand order) and the search
 *   pattern it targets. Nothing is stored per page; the answers are the page's own fields.
 * - Topic (organisation types, distinct services, categories; the Directory, Volunteer, Claim and Opportunities pages):
 *   FAQ items stored on the term or page (meta aw_faq, JSON): question, answer, sources, target keyword, status
 *   (draft / approved) and last reviewed. Only approved items are shown; drafts wait in Tools → FAQ for the owner.
 *
 * aw_faq_build(): candidates (registry + approved items) → keep those whose fields have values → dedupe by the fact
 * they state → sort by priority → first 6 → fewer than 3: no FAQ and no schema. The accordion (H2 "Questions about …"
 * at h4 size; buttons with aria-expanded; answers in the HTML) is rendered once per request, and the FAQPage node
 * (aw-seo-extras/schema.php) is parsed from that HTML, so the schema text is the visible text word for word.
 *
 * Positions: Library item: last in Content, before Sources (single.html); listing: after Support, before Sources
 * (single-aw_listing.html); term pages: after the list, before Share (term-page.php); hubs: after the list (the end of
 * the page's content). No FAQ on News, Research, Opportunity items, format tags, About, Contact, policies, Home or the
 * News and Resources pages.
 *
 * Experiment (spec §8): 10% of eligible listings and Library documents (stable by ID) get no FAQ until the owner rolls
 * out to 100% (Tools → FAQ). The page context (kits_page_context) carries faq_group = faq | holdout | none for GA4.
 *
 * WP-CLI: wp aw-faq report | check | holdout | import <file>. docs/faq.md describes it all.
 *
 * Lives in the AW SEO plugin (owner, 21 Oct 2026: the advanced layer on free Rank Math; nothing SEO stays in core or the theme).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-table.php'; // aw_seo_extras_simple_table() for AW SEO Plus → FAQ

const AW_FAQ_MIN   = 3;

/**
 * Fewest questions a page shows (and marks up): 3, except Resources guides, where 2 is enough (owner, 21 Oct 2026 for
 * World Rabies Day; 25 Oct 2026 for all guides, so the Rank Math conversion loses none).
 *
 * @param array|null $ctx aw_faq_context().
 * @return int
 */
function aw_faq_min( $ctx ) {
	return is_array( $ctx ) && 'guide' === ( $ctx['kind'] ?? '' ) ? 2 : AW_FAQ_MIN;
}
const AW_FAQ_MAX   = 6;
const AW_FAQ_WORDS = 50;

/** Tags allowed in a topic answer (the page and the schema print the same HTML). */
const AW_FAQ_ANSWER_HTML = array(
	'a'      => array( 'href' => true ),
	'strong' => array(),
	'em'     => array(),
);

/** Hub pages with a topic FAQ: page slug => the H2's subject. */
const AW_FAQ_HUBS = array(
	'directory'     => 'the Directory',
	'volunteer'     => 'volunteering',
	'claim'         => 'claiming a listing',
	'opportunities' => 'Opportunities',
);

/* ---------- Experiment (holdout) ---------- */

/** The experiment's settings: start (Y-m-d), weeks, pct, salt, on. Created on first use, starting that day. */
function aw_faq_experiment() {
	$e = get_option( 'aw_faq_experiment' );
	if ( ! is_array( $e ) || empty( $e['start'] ) ) {
		$e = array( 'start' => wp_date( 'Y-m-d' ), 'weeks' => 6, 'pct' => 10, 'salt' => 'aw-faq-2026-10', 'on' => true );
		update_option( 'aw_faq_experiment', $e, false );
	}
	return $e;
}

/** Whether a listing or Library document is in the holdout (no FAQ): random, stable by ID. */
function aw_faq_in_holdout( $id ) {
	$e = aw_faq_experiment();
	return ! empty( $e['on'] ) && ( crc32( $e['salt'] . ':' . (int) $id ) % 100 ) < (int) $e['pct'];
}

/* ---------- Small helpers ---------- */

/** Plain text of a title, for a sentence. */
function aw_faq_plain( $s ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/** A list in a sentence: "a, b and c". */
function aw_faq_and( array $items ) {
	$items = array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
	if ( count( $items ) < 2 ) {
		return (string) ( $items[0] ?? '' );
	}
	$last = array_pop( $items );
	return implode( ', ', $items ) . ' and ' . $last;
}

/** A label in a sentence: first letter lower case unless it starts an acronym ("Sterilisation (ABC)" → "sterilisation (ABC)"). */
function aw_faq_lc( $s ) {
	$s = trim( (string) $s );
	return preg_match( '/^\p{Lu}\p{Ll}/u', $s ) ? mb_strtolower( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 ) : $s;
}

/** A link in an answer. */
function aw_faq_a( $url, $text ) {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
}

/** Words in an answer (tags left out). */
function aw_faq_word_count( $html ) {
	return count( preg_split( '/\s+/u', aw_faq_plain( $html ), -1, PREG_SPLIT_NO_EMPTY ) );
}

/** A document title for a sentence: "The Transport of Animals Rules, 1978" → "Transport of Animals Rules, 1978". */
function aw_faq_doc_name( $id ) {
	return preg_replace( '/^The\s+/u', '', aw_faq_plain( get_the_title( $id ) ) );
}

/** Whether a document name is plural ("ABC Rules 2023", "… Amendment Rules, 2026"): "Are … in force?", "were notified". */
function aw_faq_plural( $name ) {
	return (bool) preg_match( '/\b(Rules|Acts|Regulations|Guidelines|Orders|Judgments)\b/iu', (string) $name );
}

/** "is"/"are", "was"/"were" for a document name. */
function aw_faq_be( $name, $past = false ) {
	return aw_faq_plural( $name ) ? ( $past ? 'were' : 'are' ) : ( $past ? 'was' : 'is' );
}

/**
 * A document's short name, the way people search it (owner, 20 Oct 2026): the "Short name" field ("PCA Act 1960", "ABC
 * Rules 2023"), else the primary focus keyphrase, else the title. Questions use it; the first answer gives the full
 * official title once.
 */
function aw_faq_doc_short( $id ) {
	$s = aw_faq_plain( get_post_meta( $id, 'aw_short_name', true ) );
	if ( '' === $s ) {
		$s = trim( explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) )[0] );
	}
	return '' !== $s ? $s : aw_faq_doc_name( $id );
}

/* ---------- A. Directory listings ---------- */

/** "Monday to Saturday 9:00 am – 5:00 pm; Sunday closed" from aw_listing_hours(). */
function aw_faq_hours_summary( array $rows ) {
	$fmt  = fn( $t ) => wp_date( 'g:i a', strtotime( '2000-01-01 ' . $t . ':00 UTC' ), new DateTimeZone( 'UTC' ) );
	$days = array();
	foreach ( $rows as $r ) {
		$days[ $r['day'] ][] = $r['closed'] ? 'closed' : ( '00:00' === $r['open'] && '23:59' === $r['close'] ? 'open 24 hours' : $fmt( $r['open'] ) . ' – ' . $fmt( $r['close'] ) );
	}
	$runs = array();
	foreach ( AW_WEEKDAYS as $d ) {
		if ( ! isset( $days[ $d ] ) ) {
			continue;
		}
		$v    = implode( ', ', array_unique( $days[ $d ] ) );
		$last = count( $runs ) - 1;
		if ( $last >= 0 && $runs[ $last ]['v'] === $v && array_search( $runs[ $last ]['to'], AW_WEEKDAYS, true ) === array_search( $d, AW_WEEKDAYS, true ) - 1 ) {
			$runs[ $last ]['to'] = $d;
		} else {
			$runs[] = array( 'from' => $d, 'to' => $d, 'v' => $v );
		}
	}
	return implode( '; ', array_map( fn( $r ) => ( $r['from'] === $r['to'] ? $r['from'] : $r['from'] . ' to ' . $r['to'] ) . ' ' . $r['v'], $runs ) );
}

/** A section of the listing's own page (the H2 ids in listing-template.php). */
function aw_faq_section_url( $id, $slug ) {
	return get_permalink( $id ) . '#aw-' . $slug . '-h';
}

/**
 * The question registry: post type => entries. Each entry: key, priority (1 = most searched), q (question template,
 * %s = the entity), needs (fields; documentation and the report), pattern (the search it answers), answer (callable:
 * ($id, $name) → [answer HTML, fact key] or null when the fields are empty). Filterable (aw_faq_registry).
 */
function aw_faq_registry() {
	$r = array(
		'aw_listing' => array(
			array( 'key' => 'phone', 'priority' => 1, 'q' => 'What is the contact number of %s?', 'needs' => array( 'aw_phones' ), 'pattern' => '<org> contact number / helpline number', 'answer' => 'aw_faq_listing_phone' ),
			array( 'key' => 'location', 'priority' => 2, 'q' => 'Where is %s located?', 'needs' => array( 'aw_address', 'district' ), 'pattern' => '<org> <city> / address', 'answer' => 'aw_faq_listing_location' ),
			array( 'key' => 'services', 'priority' => 3, 'q' => 'What services does %s offer?', 'needs' => array( 'aw_services', 'type' ), 'pattern' => '<org> services / adoption / rescue', 'answer' => 'aw_faq_listing_services' ),
			array( 'key' => 'awbi', 'priority' => 4, 'q' => 'Is %s recognised by the Animal Welfare Board of India?', 'needs' => array( 'aw_awbi_code' ), 'pattern' => '<org> awbi', 'answer' => 'aw_faq_listing_awbi' ),
			array( 'key' => 'volunteer', 'priority' => 5, 'q' => 'How can I volunteer with %s?', 'needs' => array( 'aw_volunteer_url' ), 'pattern' => '<org> volunteer', 'answer' => 'aw_faq_listing_volunteer' ),
			array( 'key' => 'donate', 'priority' => 6, 'q' => 'How can I donate to %s? Is it tax-deductible?', 'needs' => array( 'aw_donate_url', 'aw_upi', 'aw_reg_80g' ), 'pattern' => '<org> donate', 'answer' => 'aw_faq_listing_donate' ),
			array( 'key' => 'hours', 'priority' => 7, 'q' => 'What are the opening hours of %s?', 'needs' => array( 'aw_opening_hours' ), 'pattern' => '<org> timings', 'answer' => 'aw_faq_listing_hours' ),
			array( 'key' => 'adoption', 'priority' => 8, 'q' => 'Does %s offer adoption?', 'needs' => array( 'aw_adopt_url', 'Adoption Service' ), 'pattern' => '<org> adoption', 'answer' => 'aw_faq_listing_adoption' ),
			array( 'key' => 'registration', 'priority' => 9, 'q' => 'What is the registration number or NGO Darpan ID of %s?', 'needs' => array( 'aw_registration', 'aw_darpan_id' ), 'pattern' => '<org> registration', 'answer' => 'aw_faq_listing_registration' ),
			array( 'key' => 'animals', 'priority' => 10, 'q' => 'Which animals does %s help?', 'needs' => array( 'aw_animals' ), 'pattern' => '—', 'answer' => 'aw_faq_listing_animals' ),
		),
		'aw_legal'   => array(
			array( 'key' => 'pdf', 'priority' => 1, 'q' => 'Where can I download the %s PDF?', 'needs' => array( 'aw_file_id (PDF)', 'aw_source_url (.pdf)' ), 'pattern' => '<doc> pdf / pdf download', 'answer' => 'aw_faq_legal_pdf' ),
			array( 'key' => 'force', 'priority' => 2, 'q' => 'Is the %s in force?', 'needs' => array( 'aw_legal_force' ), 'pattern' => '<doc> latest / today', 'answer' => 'aw_faq_legal_force' ),
			array( 'key' => 'date', 'priority' => 3, 'q' => 'When was the %s %s?', 'needs' => array( 'aw_source_date', 'aw_legal_number (not needed for official releases and circulars)' ), 'pattern' => '<doc> gazette / notification', 'answer' => 'aw_faq_legal_date' ),
			array( 'key' => 'issuer', 'priority' => 4, 'q' => 'Who issued the %s?', 'needs' => array( 'aw_issuer (not for Acts and Rules: the stored issuer is whose copy it is)' ), 'pattern' => '—', 'answer' => 'aw_faq_legal_issuer' ),
			array( 'key' => 'hindi', 'priority' => 5, 'q' => 'Is the %s available in Hindi?', 'needs' => array( 'aw_file_hi_url' ), 'pattern' => '<doc> in hindi', 'answer' => 'aw_faq_legal_hindi' ),
			array( 'key' => 'read', 'priority' => 7, 'q' => 'Where can I read the %s?', 'needs' => array( 'aw_source_url (not a PDF)', 'aw_issuer', 'official release' ), 'pattern' => '<doc> press release', 'answer' => 'aw_faq_legal_read' ),
			array( 'key' => 'sections', 'priority' => 6, 'q' => 'What does section %2$s of the %1$s say?', 'needs' => array( 'aw_key_sections (Acts and Rules)' ), 'pattern' => 'pca act section 11 / punishment', 'answer' => 'aw_faq_legal_sections' ),
		),
	);
	return (array) apply_filters( 'aw_faq_registry', $r );
}

function aw_faq_listing_phone( $id, $name ) {
	$phones = array_values( array_filter( aw_listing_phones( $id ), fn( $p ) => '' !== aw_tel_url( $p['number'] ) ) );
	if ( ! $phones ) {
		return null;
	}
	$first = $phones[0];
	$wa    = '';
	foreach ( $phones as $p ) {
		if ( $p['whatsapp'] ) {
			$wa = $p['number'];
			break;
		}
	}
	$a = esc_html( $name ) . '’s phone number is ' . aw_faq_a( aw_tel_url( $first['number'] ), $first['number'] ) . ( $wa === $first['number'] ? ' (also on WhatsApp).' : '.' );
	if ( '' !== $wa && $wa !== $first['number'] ) {
		$a .= ' WhatsApp: ' . esc_html( $wa ) . '.';
	}
	$more = array();
	foreach ( array_slice( $phones, 1, 3 ) as $p ) {
		if ( $p['number'] !== $wa ) {
			$more[] = ( '' !== $p['label'] ? esc_html( $p['label'] ) . ': ' : '' ) . esc_html( $p['number'] );
		}
	}
	if ( $more ) {
		$a .= ' Other numbers: ' . implode( '; ', $more ) . '.';
	}
	return array( $a, 'phone' );
}

function aw_faq_listing_location( $id, $name ) {
	list( $state, $district ) = aw_listing_place( $id );
	$addr = aw_lf( $id, 'aw_address' );
	if ( '' !== $addr ) { // the address, then the district and state when it does not name them already
		foreach ( array( $district, $state ) as $part ) {
			$addr .= '' !== (string) $part && false === stripos( $addr, (string) $part ) ? ', ' . $part : '';
		}
	} elseif ( aw_lf( $id, 'aw_area' ) ) {
		$addr = aw_listing_location_text( aw_lf( $id, 'aw_area' ), array( $state, $district ), aw_lf( $id, 'aw_pincode' ) );
	} elseif ( '' !== (string) $district ) {
		$addr = implode( ', ', array_filter( array( $district, $state ) ) );
	}
	if ( '' === trim( (string) $addr ) ) {
		return null;
	}
	$maps = aw_lf( $id, 'aw_maps_url' );
	$a    = esc_html( $name ) . ' is at ' . esc_html( rtrim( $addr, '. ' ) ) . '. ' . ( $maps ? aw_faq_a( $maps, 'Get directions' ) : aw_faq_a( aw_faq_section_url( $id, 'location' ), 'See the location' ) ) . '.';
	return array( $a, 'location' );
}

function aw_faq_listing_services( $id, $name ) {
	$names = aw_listing_service_names( $id );
	$typed = false;
	if ( ! $names && aw_lf( $id, 'aw_services' ) ) {
		$names = array_map( 'trim', explode( ',', aw_lf( $id, 'aw_services' ) ) );
	}
	if ( ! $names ) {
		$names = aw_listing_type_services( $id );
		$typed = true;
	}
	$names = array_values( array_filter( array_map( 'aw_faq_plain', $names ), 'strlen' ) );
	if ( ! $names ) {
		return null;
	}
	$extra = max( 0, count( $names ) - 8 ); // a long list stays under 50 words
	$list  = aw_faq_and( array_map( 'aw_faq_lc', array_slice( $names, 0, 8 ) ) ) . ( $extra ? ' and ' . $extra . ' more' : '' );
	$a     = esc_html( $name ) . ' offers ' . esc_html( $list ) . ( $typed ? ' (based on type)' : '' ) . '. ' . aw_faq_a( aw_faq_section_url( $id, 'services' ), 'See its services' ) . '.';
	return array( $a, 'services', ! $typed ); // "based on type" is shown but does not count toward the 3 (owner, 20 Oct 2026)
}

function aw_faq_listing_awbi( $id, $name ) {
	$code = aw_lf( $id, 'aw_awbi_code' );
	if ( '' === $code ) {
		return null;
	}
	$valid = aw_lf( $id, 'aw_awbi_valid' );
	$day   = $valid && strtotime( $valid ) ? wp_date( 'Y-m-d', strtotime( $valid . ' 12:00:00' ) ) : '';
	$drop  = aw_lf( $id, 'aw_awbi_dropped' );
	$link  = aw_faq_a( aw_faq_section_url( $id, 'registrations' ), 'See its registrations' ) . '.';
	if ( '' !== $day && $day < wp_date( 'Y-m-d' ) ) {
		$a = 'The AWBI recognition of ' . esc_html( $name ) . ' (number ' . esc_html( $code ) . ') expired on ' . aw_date( $day ) . '. ' . $link;
	} elseif ( '' !== $drop && strtotime( $drop ) ) {
		$a = esc_html( $name ) . ' has AWBI number ' . esc_html( $code ) . ', but it is not on the AWBI list of ' . aw_date( $drop ) . '. ' . $link;
	} else {
		$a = 'Yes. ' . esc_html( $name ) . ' is recognised by the AWBI, number ' . esc_html( $code ) . ( '' !== $day ? ', valid until ' . aw_date( $day ) : '' ) . '. ' . $link;
	}
	return array( $a, 'awbi' );
}

function aw_faq_listing_volunteer( $id, $name ) {
	$url = aw_lf( $id, 'aw_volunteer_url' );
	return $url ? array( 'You can volunteer with ' . esc_html( $name ) . ' through its ' . aw_faq_a( $url, 'volunteer page' ) . '.', 'volunteer' ) : null;
}

function aw_faq_listing_donate( $id, $name ) {
	$url = aw_lf( $id, 'aw_donate_url' );
	$upi = aw_lf( $id, 'aw_upi' );
	if ( '' === $url && '' === $upi ) {
		return null;
	}
	$a  = $url ? 'You can donate to ' . esc_html( $name ) . ' through its ' . aw_faq_a( $url, 'donation page' ) . ( $upi ? ' or by UPI (' . esc_html( $upi ) . ')' : '' ) . '.'
		: 'You can donate to ' . esc_html( $name ) . ' by UPI: ' . esc_html( $upi ) . '. ' . aw_faq_a( aw_faq_section_url( $id, 'support' ), 'See how to support it' ) . '.';
	$g  = aw_lf( $id, 'aw_reg_80g' );
	$a .= $g ? ' It is registered under 80G (number ' . esc_html( $g ) . '), so donations can be tax-deductible.' : ' No 80G registration is on record.';
	return array( $a, 'donate' );
}

function aw_faq_listing_hours( $id, $name ) {
	$rows = aw_listing_hours( $id );
	return $rows ? array( esc_html( $name ) . ' is open ' . esc_html( aw_faq_hours_summary( $rows ) ) . '. ' . aw_faq_a( aw_faq_section_url( $id, 'location' ), 'See the hours by day' ) . '.', 'hours' ) : null;
}

function aw_faq_listing_adoption( $id, $name ) {
	$url = aw_lf( $id, 'aw_adopt_url' );
	if ( $url ) {
		return array( 'Yes. See the ' . aw_faq_a( $url, 'adoption page of ' . $name ) . '.', 'adoption' );
	}
	return has_term( 'adoption', 'post_tag', $id ) ? array( 'Yes. ' . esc_html( $name ) . ' lists adoption among its services. ' . aw_faq_a( aw_faq_section_url( $id, 'services' ), 'See its services' ) . '.', 'services' ) : null; // the services answer already says so: dropped as a duplicate
}

function aw_faq_listing_registration( $id, $name ) {
	$no     = aw_lf( $id, 'aw_registration' );
	$darpan = aw_lf( $id, 'aw_darpan_id' );
	if ( '' === $no && '' === $darpan ) {
		return null;
	}
	$type = aw_lf( $id, 'aw_reg_type' );
	$a    = '' !== $no
		? esc_html( $name ) . ( $type ? ' is registered as a ' . esc_html( aw_faq_lc( $type ) ) . ', number ' : '’s registration number is ' ) . esc_html( $no ) . ( $darpan ? '; its NGO Darpan ID is ' . esc_html( $darpan ) : '' ) . '.'
		: 'The NGO Darpan ID of ' . esc_html( $name ) . ' is ' . esc_html( $darpan ) . '.';
	return array( $a . ' ' . aw_faq_a( aw_faq_section_url( $id, 'registrations' ), 'See its registrations' ) . '.', 'registration' );
}

function aw_faq_listing_animals( $id, $name ) {
	$an = aw_lf( $id, 'aw_animals' );
	return $an ? array( esc_html( $name ) . ' helps ' . esc_html( aw_faq_and( array_map( 'aw_faq_lc', explode( ',', $an ) ) ) ) . '. ' . aw_faq_a( aw_faq_section_url( $id, 'services' ), 'See its services' ) . '.', 'animals' ) : null;
}

/* ---------- B. Library documents ---------- */

/** The Library type slug (act, rule, circular …) of a document. */
function aw_faq_legal_kind( $id ) {
	$k = function_exists( 'aw_legal_kinds' ) ? ( aw_legal_kinds( $id )[0] ?? null ) : null;
	return $k ? $k->slug : '';
}

/** The PDF of a document: our hosted copy, else the source when it is a PDF ('' for none). */
function aw_faq_legal_pdf_url( $id ) {
	$att = (int) get_post_meta( $id, 'aw_file_id', true );
	$url = $att && 'application/pdf' === get_post_mime_type( $att ) ? (string) wp_get_attachment_url( $att ) : '';
	$src = (string) get_post_meta( $id, 'aw_source_url', true );
	return '' !== $url ? $url : ( preg_match( '/\.pdf$/i', (string) wp_parse_url( $src, PHP_URL_PATH ) ) ? $src : '' );
}

function aw_faq_legal_pdf( $id, $doc ) {
	$url = aw_faq_legal_pdf_url( $id );
	if ( '' === $url ) {
		return null;
	}
	$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	return array( 'Download the official ' . esc_html( $doc ) . ' PDF ' . aw_faq_a( $url, 'here' ) . ( $iss ? ' (from ' . esc_html( $iss->name ) . ')' : '' ) . '.', 'pdf' );
}

function aw_faq_legal_force( $id, $doc ) {
	$f     = (string) get_post_meta( $id, 'aw_legal_force', true );
	$src   = (string) get_post_meta( $id, 'aw_source_url', true );
	$read  = $src ? ' ' . aw_faq_a( $src, 'Read the official text' ) . '.' : '';
	$draft = has_term( 'draft', 'post_tag', $id ) || preg_match( '/^Draft\b/i', $doc );
	switch ( $f ) {
		case 'InForce':
			return array( 'Yes. The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc ) . ' in force.' . $read, 'force' );
		case 'PartiallyInForce':
			return array( 'The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc ) . ' partly in force.' . $read, 'force' );
		case 'NotInForce':
			return array( 'No. The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc ) . ( $draft ? ( aw_faq_plural( $doc ) ? ' draft rules and are not in force.' : ' a draft and is not in force.' ) : ' not in force.' ) . $read, 'force' );
	}
	return null;
}

/** The verb for when a document came out: notified (laws), delivered (judgments), passed (orders), else issued. */
function aw_faq_legal_verb( $id ) {
	$k = aw_faq_legal_kind( $id );
	return in_array( $k, array( 'act', 'rule', 'notification' ), true ) ? 'notified' : ( 'judgment' === $k ? 'delivered' : ( 'order' === $k ? 'passed' : ( 'official-release' === $k ? 'released' : 'issued' ) ) );
}

function aw_faq_legal_date( $id, $doc ) {
	$d  = (string) get_post_meta( $id, 'aw_source_date', true );
	$no = trim( (string) get_post_meta( $id, 'aw_legal_number', true ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) || $d < '1900' ) {
		return null;
	}
	if ( '' === $no ) {
		// Releases and circulars carry no number (owner, 25 Oct 2026): the date alone, with who released or issued it.
		$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
		if ( ! $iss || ! in_array( aw_faq_legal_kind( $id ), array( 'official-release', 'circular' ), true ) ) {
			return null;
		}
		return array( esc_html( $iss->name ) . ' ' . aw_faq_legal_verb( $id ) . ' the ' . esc_html( $doc ) . ' on ' . aw_date( $d ) . '.', 'date' );
	}
	$src = (string) get_post_meta( $id, 'aw_source_url', true );
	$a   = 'The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc, true ) . ' ' . aw_faq_legal_verb( $id ) . ' on ' . aw_date( $d ) . ' as ' . ( $src ? aw_faq_a( $src, $no ) : esc_html( $no ) ) . '.';
	return array( $a, 'date' );
}

function aw_faq_legal_issuer( $id, $doc ) {
	$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	if ( ! $iss || in_array( aw_faq_legal_kind( $id ), array( 'act', 'rule', 'template' ), true ) ) {
		return null; // an Act's or Rule's stored issuer is whose copy it is, not the law-maker
	}
	$act = aw_faq_plain( get_post_meta( $id, 'aw_legal_act', true ) );
	$src = (string) get_post_meta( $id, 'aw_source_url', true );
	$a   = 'The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc, true ) . ' issued by ' . esc_html( $iss->name ) . ( '' !== $act ? ' under the ' . esc_html( preg_replace( '/^The\s+/u', '', $act ) ) : '' ) . '.' . ( $src ? ' ' . aw_faq_a( $src, 'See the original' ) . '.' : '' );
	return array( $a, 'issuer' );
}

/**
 * Where to read an official release that has no PDF (owner, 25 Oct 2026): the issuer's own page.
 *
 * @param int    $id  Document ID.
 * @param string $doc Document short name.
 * @return array|null [ answer html, fact ] or null.
 */
function aw_faq_legal_read( $id, $doc ) {
	$src = (string) get_post_meta( $id, 'aw_source_url', true );
	$iss = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
	if ( '' === $src || ! $iss || 'official-release' !== aw_faq_legal_kind( $id ) || '' !== aw_faq_legal_pdf_url( $id ) ) {
		return null;
	}
	return array( 'On the website of ' . esc_html( $iss->name ) . ': ' . aw_faq_a( $src, 'read the ' . esc_html( $doc ) ) . '.', 'read' );
}

function aw_faq_legal_hindi( $id, $doc ) {
	$url = trim( (string) get_post_meta( $id, 'aw_file_hi_url', true ) );
	return $url ? array( 'Yes. The Hindi version of the ' . esc_html( $doc ) . ' is ' . aw_faq_a( $url, 'here' ) . '.', 'hindi' ) : null;
}

/**
 * Key sections of an Act or Rules, as listed by an editor (never picked automatically): meta aw_key_sections, one per
 * line "number | one-line plain summary | link (optional)".
 *
 * @param int $id Document ID.
 * @return array[] [ [ n, summary, url ], … ]
 */
function aw_faq_key_sections( $id ) {
	if ( ! in_array( aw_faq_legal_kind( $id ), array( 'act', 'rule' ), true ) ) {
		return array();
	}
	$out = array();
	foreach ( preg_split( '/\R/', (string) get_post_meta( $id, 'aw_key_sections', true ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 2 && '' !== $p[0] && '' !== $p[1] ) {
			$out[] = array( $p[0], rtrim( $p[1], '. ' ), $p[2] ?? '' );
		}
	}
	return $out;
}

/** Section answers: several questions from one entry (one per listed section). */
function aw_faq_legal_sections( $id, $doc ) {
	$out = array();
	foreach ( aw_faq_key_sections( $id ) as $s ) {
		$url   = '' !== $s[2] ? $s[2] : ( aw_faq_legal_pdf_url( $id ) ? aw_faq_legal_pdf_url( $id ) : (string) get_post_meta( $id, 'aw_source_url', true ) );
		$out[] = array(
			'q'    => sprintf( 'What does section %2$s of the %1$s say?', $doc, $s[0] ),
			'a'    => 'Section ' . esc_html( $s[0] ) . ' of the ' . esc_html( $doc ) . ' provides ' . esc_html( aw_faq_lc( $s[1] ) ) . '.' . ( $url ? ' ' . aw_faq_a( $url, 'Read the full section' ) . '.' : '' ),
			'fact' => 'section-' . $s[0],
		);
	}
	return $out ? $out : null;
}

/* ---------- Topic FAQ items (terms and hub pages) ---------- */

/**
 * The FAQ items stored on a term or page, cleaned: [ [ q, a, sources[ [name, url] ], keyword, status, reviewed ], … ].
 *
 * @param string $kind 'term' or 'post'.
 * @param int    $id   Term or post ID.
 * @return array[]
 */
function aw_faq_items( $kind, $id ) {
	$raw = 'term' === $kind ? get_term_meta( $id, 'aw_faq', true ) : get_post_meta( $id, 'aw_faq', true );
	$raw = json_decode( (string) $raw, true );
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $row ) {
		$q = trim( wp_strip_all_tags( (string) ( $row['q'] ?? '' ) ) );
		$a = trim( wp_kses( (string) ( $row['a'] ?? '' ), AW_FAQ_ANSWER_HTML ) );
		if ( '' === $q || '' === $a ) {
			continue;
		}
		$src = array();
		foreach ( (array) ( $row['sources'] ?? array() ) as $s ) {
			$name = trim( wp_strip_all_tags( (string) ( is_array( $s ) ? ( $s['name'] ?? '' ) : $s ) ) );
			$url  = esc_url_raw( (string) ( is_array( $s ) ? ( $s['url'] ?? '' ) : '' ) );
			if ( '' !== $name || '' !== $url ) {
				$src[] = array( 'name' => $name, 'url' => $url );
			}
		}
		$out[] = array(
			'q'        => $q,
			'a'        => $a,
			'sources'  => $src,
			'keyword'  => trim( sanitize_text_field( (string) ( $row['keyword'] ?? '' ) ) ),
			'status'   => 'approved' === ( $row['status'] ?? '' ) ? 'approved' : 'draft',
			'reviewed' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['reviewed'] ?? '' ) ) ? $row['reviewed'] : '',
		);
	}
	return $out;
}

/**
 * Store a term's or page's FAQ items (the edit screens, Tools → FAQ and wp aw-faq import).
 *
 * @param string $kind 'term' or 'post'.
 * @param int    $id   Term or post ID.
 * @param array  $rows Items.
 */
function aw_faq_items_save( $kind, $id, array $rows ) {
	$clean = array();
	foreach ( $rows as $row ) {
		$q = trim( sanitize_text_field( (string) ( $row['q'] ?? '' ) ) );
		$a = trim( wp_kses( (string) ( $row['a'] ?? '' ), AW_FAQ_ANSWER_HTML ) );
		if ( '' === $q || '' === $a ) {
			continue;
		}
		$src = array();
		foreach ( (array) ( $row['sources'] ?? array() ) as $s ) {
			$name = trim( sanitize_text_field( (string) ( $s['name'] ?? '' ) ) );
			$url  = esc_url_raw( (string) ( $s['url'] ?? '' ) );
			if ( '' !== $name || '' !== $url ) {
				$src[] = array( 'name' => $name, 'url' => $url );
			}
		}
		$clean[] = array(
			'q'        => $q,
			'a'        => $a,
			'sources'  => $src,
			'keyword'  => trim( sanitize_text_field( (string) ( $row['keyword'] ?? '' ) ) ),
			'status'   => 'approved' === ( $row['status'] ?? '' ) ? 'approved' : 'draft',
			'reviewed' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['reviewed'] ?? '' ) ) ? $row['reviewed'] : '',
		);
	}
	$json = wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	if ( 'term' === $kind ) {
		$clean ? update_term_meta( $id, 'aw_faq', $json ) : delete_term_meta( $id, 'aw_faq' );
	} else {
		$clean ? update_post_meta( $id, 'aw_faq', $json ) : delete_post_meta( $id, 'aw_faq' );
	}
}

/** Whether a term may have a topic FAQ: an organisation type, a service or a category (never a format tag). */
function aw_faq_term_allowed( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return false;
	}
	if ( 'category' === $term->taxonomy ) {
		return (int) get_option( 'default_category' ) !== $term->term_id;
	}
	return 'post_tag' === $term->taxonomy && in_array( $term->slug, array_merge( aw_listing_type_slugs(), aw_service_slugs() ), true );
}

/** The hub page IDs with a topic FAQ: slug => ID. */
function aw_faq_hub_pages() {
	$out = array();
	foreach ( array_keys( AW_FAQ_HUBS ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			$out[ $slug ] = (int) $p->ID;
		}
	}
	return $out;
}

/** True when a hub is shown as itself: page 1, no place, type, search or filter in the address. */
function aw_faq_hub_plain() {
	if ( is_paged() || get_query_var( 'aw_place' ) || get_query_var( 'aw_dir_type' ) ) {
		return false;
	}
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! preg_match( '/^(utm_[a-z]+|gclid|fbclid|nocache|ref|v)$/', (string) $k ) ) {
			return false;
		}
	}
	return true;
}

/* ---------- The page's FAQ ---------- */

/**
 * A Resources guide: a published post that is not a research summary (research gets no FAQ, docs/faq.md).
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function aw_faq_is_guide( $post ) {
	return $post instanceof WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status && ! has_term( 'research', 'post_tag', $post );
}

/**
 * The FAQ context of a page: kind (listing | legal | term | hub), object ID, entity name, URL, the page node's and
 * the main entity's @id, the H2. Null where the page has no FAQ (News, Research, Opportunities, format tags, About,
 * Contact, policies, Home, the News and Resources pages …).
 *
 * @param WP_Post|WP_Term|null $obj Default: the queried object.
 * @return array|null
 */
function aw_faq_context( $obj = null ) {
	$obj = null === $obj ? get_queried_object() : $obj;
	if ( $obj instanceof WP_Post && in_array( $obj->post_type, array( 'aw_listing', 'aw_legal' ), true ) && 'publish' === $obj->post_status ) {
		$url   = get_permalink( $obj );
		$legal = 'aw_legal' === $obj->post_type;
		$kind  = $legal ? aw_faq_legal_kind( $obj->ID ) : '';
		return array(
			'kind'    => $legal ? 'legal' : 'listing',
			'id'      => $obj->ID,
			'type'    => $obj->post_type,
			'entity'  => $legal ? aw_faq_doc_short( $obj->ID ) : aw_faq_plain( get_the_title( $obj ) ),
			'full'    => $legal ? aw_faq_doc_name( $obj->ID ) : '',
			'url'     => $url,
			'page_id' => $url . '#webpage',
			'about'   => $url . ( $legal ? ( in_array( $kind, array( 'act', 'rule', 'notification' ), true ) ? '#legislation' : ( 'template' === $kind ? '#document' : '#article' ) ) : '#organization' ),
			'head'    => 'Questions about ' . ( $legal ? 'the ' . aw_faq_doc_short( $obj->ID ) : aw_faq_plain( get_the_title( $obj ) ) ),
		);
	}
	if ( $obj instanceof WP_Post && aw_faq_is_guide( $obj ) ) {
		// Resources guides (owner, 25 Oct 2026): their own written questions, stored on the guide (meta aw_faq), shown by
		// the item-faq block at the end of the content; the Rank Math FAQ block they used is converted on save.
		$url = get_permalink( $obj );
		return array( 'kind' => 'guide', 'id' => $obj->ID, 'type' => 'post', 'entity' => aw_faq_plain( get_the_title( $obj ) ), 'url' => $url, 'page_id' => $url . '#webpage', 'about' => $url . '#webpage', 'head' => 'Questions and answers' );
	}
	if ( $obj instanceof WP_Term && aw_faq_term_allowed( $obj ) ) {
		$url = (string) kits_term_url( $obj );
		return array( 'kind' => 'term', 'id' => $obj->term_id, 'type' => $obj->taxonomy, 'entity' => aw_faq_plain( $obj->name ), 'url' => $url, 'page_id' => $url . '#page', 'about' => $url . '#page', 'head' => 'Questions about ' . aw_faq_plain( single_term_title( '', false ) ? single_term_title( '', false ) : $obj->name ) );
	}
	if ( $obj instanceof WP_Post && 'page' === $obj->post_type && in_array( (int) $obj->ID, aw_faq_hub_pages(), true ) ) {
		$slug = (string) array_search( (int) $obj->ID, aw_faq_hub_pages(), true );
		$url  = get_permalink( $obj );
		$node = in_array( $slug, array( 'directory', 'opportunities' ), true ) ? '#page' : '#webpage'; // the list pages' CollectionPage (aw_hub_schema_nodes)
		return array( 'kind' => 'hub', 'id' => (int) $obj->ID, 'type' => 'page', 'entity' => AW_FAQ_HUBS[ $slug ], 'url' => $url, 'page_id' => $url . $node, 'about' => $url . $node, 'head' => 'Questions about ' . AW_FAQ_HUBS[ $slug ] );
	}
	return null;
}

/**
 * The questions a context offers before the cap: registry answers that have data, then approved topic items.
 *
 * @param array $ctx aw_faq_context().
 * @return array[] [ [ q, a, fact, priority, key ], … ]
 */
function aw_faq_candidates( $ctx ) {
	$out = array();
	foreach ( aw_faq_registry()[ $ctx['type'] ] ?? array() as $e ) {
		$r = call_user_func( $e['answer'], $ctx['id'], $ctx['entity'] );
		if ( ! $r ) {
			continue;
		}
		if ( isset( $r[0] ) && is_string( $r[0] ) ) { // one answer: [ html, fact ]
			$verb  = 'date' === $e['key'] ? aw_faq_legal_verb( $ctx['id'] ) : '';
			$q     = sprintf( $e['q'], $ctx['entity'], $verb );
			if ( 'legal' === $ctx['kind'] && aw_faq_plural( $ctx['entity'] ) ) { // "Are the ABC Rules 2023 in force?", "When were … notified?"
				$q = preg_replace( array( '/^Is the /', '/^When was the /' ), array( 'Are the ', 'When were the ' ), $q );
			}
			$out[] = array( 'q' => $q, 'a' => $r[0], 'fact' => $r[1], 'priority' => $e['priority'], 'key' => $e['key'], 'counts' => $r[2] ?? true, 'answer' => $e['answer'] );
			continue;
		}
		foreach ( $r as $i => $x ) { // several (key sections)
			$out[] = array( 'q' => $x['q'], 'a' => $x['a'], 'fact' => $x['fact'], 'priority' => $e['priority'] + $i / 100, 'key' => $e['key'], 'counts' => true, 'answer' => $e['answer'] );
		}
	}
	// Letter templates have no data fields: they take hand-written, owner-approved items like topics (owner, 25 Oct 2026).
	if ( in_array( $ctx['kind'], array( 'term', 'hub', 'guide' ), true ) || ( 'legal' === $ctx['kind'] && 'template' === aw_faq_legal_kind( $ctx['id'] ) ) ) {
		$i = 0;
		foreach ( aw_faq_items( 'term' === $ctx['kind'] ? 'term' : 'post', $ctx['id'] ) as $it ) {
			if ( 'approved' === $it['status'] ) {
				$out[] = array( 'q' => $it['q'], 'a' => $it['a'], 'fact' => 'topic:' . strtolower( $it['q'] ), 'priority' => 100 + $i++, 'key' => 'topic', 'counts' => true ); // in the owner's order
			}
		}
	}
	return $out;
}

/**
 * build_faq(page): the questions shown, in order, or null for no FAQ (fewer than 3, a holdout page, a later page of a
 * list). Cached for the request.
 *
 * @param array|null $ctx      aw_faq_context(); default the current page.
 * @param bool       $holdout  Respect the experiment (false: what the page would show, for reports).
 * @return array|null [ ctx, items ]
 */
function aw_faq_build( $ctx = null, $holdout = true ) {
	static $cache = array();
	$ctx = null === $ctx ? aw_faq_context() : $ctx;
	if ( ! $ctx ) {
		return null;
	}
	$key = $ctx['kind'] . ':' . $ctx['id'] . ':' . (int) $holdout;
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	if ( $holdout && in_array( $ctx['kind'], array( 'listing', 'legal' ), true ) && aw_faq_in_holdout( $ctx['id'] ) ) {
		return $cache[ $key ] = null;
	}
	$cands = aw_faq_candidates( $ctx );
	usort( $cands, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
	$keep = array();
	$seen = array();
	foreach ( $cands as $c ) { // dedupe by the fact an answer states (and by question): the more searched question keeps it
		$qk = 'q:' . strtolower( trim( preg_replace( '/\W+/u', ' ', $c['q'] ) ) );
		if ( isset( $seen[ 'f:' . $c['fact'] ] ) || isset( $seen[ $qk ] ) ) {
			continue;
		}
		$keep[]                    = $c;
		$seen[ 'f:' . $c['fact'] ] = true;
		$seen[ $qk ]               = true;
	}
	$faq = array_slice( $keep, 0, AW_FAQ_MAX );
	if ( count( array_filter( $faq, fn( $c ) => $c['counts'] ) ) < aw_faq_min( $ctx ) ) { // "based on type" answers do not count toward the 3
		return $cache[ $key ] = null;
	}
	if ( 'legal' === $ctx['kind'] && '' !== $ctx['full'] && $ctx['full'] !== $ctx['entity'] && ! empty( $faq[0]['answer'] ) ) { // the full official title, once, in the first answer (data answers only: a hand-written item has no answer builder)
		$r = call_user_func( $faq[0]['answer'], $ctx['id'], $ctx['full'] );
		if ( isset( $r[0] ) && is_string( $r[0] ) ) {
			$faq[0]['a'] = $r[0];
		} elseif ( is_array( $r ) ) {
			foreach ( $r as $x ) {
				$faq[0]['a'] = $x['fact'] === $faq[0]['fact'] ? $x['a'] : $faq[0]['a'];
			}
		}
	}
	return $cache[ $key ] = array( 'ctx' => $ctx, 'items' => $faq );
}

/**
 * The accordion: H2 "Questions about …" at h4 size; each question a button (aria-expanded) inside an H3; each answer
 * in the HTML (kit.js closes them after load; without JavaScript every answer shows).
 *
 * @param array|null $faq aw_faq_build().
 * @return string
 */
function aw_faq_html( $faq = null ) {
	static $html = array();
	$faq = null === $faq ? aw_faq_build() : $faq;
	if ( ! $faq ) {
		return '';
	}
	$key = $faq['ctx']['kind'] . ':' . $faq['ctx']['id'];
	if ( isset( $html[ $key ] ) ) {
		return $html[ $key ];
	}
	$body = '';
	foreach ( $faq['items'] as $i => $it ) {
		$n     = $i + 1;
		$body .= '<div class="kit-faq-item"><h3 class="kit-faq-q"><button type="button" class="kit-faq-btn" id="kit-faq-q' . $n . '" aria-expanded="true" aria-controls="kit-faq-a' . $n . '">' . esc_html( $it['q'] ) . '</button></h3>'
			. '<div class="kit-faq-a" id="kit-faq-a' . $n . '" role="region" aria-labelledby="kit-faq-q' . $n . '"><p>' . $it['a'] . '</p></div></div>'; // answers: escaped parts (registry) or kses-cleaned (topic items)
	}
	// wptexturize as the block template does to the whole page (" - " → " – ", "24x7" → "24×7"), so the text the schema is
	// parsed from is the text the page shows; texturizing it again later changes nothing.
	return $html[ $key ] = wptexturize( '<section class="kit-faq" id="faq" aria-labelledby="kit-faq-h" data-faq="' . esc_attr( $faq['ctx']['kind'] ) . '"><h2 class="kit-faq-head" id="kit-faq-h">' . esc_html( $faq['ctx']['head'] ) . '</h2>' . $body . '</section>' );
}

/**
 * The questions and answers as the page shows them, read back from the rendered HTML: [ [ q (text), a (HTML: a, p, b
 * only), a_text ], … ]. The schema and the tests use this, so they hold exactly what the page shows.
 *
 * @param string $html aw_faq_html().
 * @return array[]
 */
function aw_faq_parse( $html ) {
	if ( '' === trim( (string) $html ) ) {
		return array();
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xp  = new DOMXPath( $dom );
	$out = array();
	foreach ( $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " kit-faq-item ")]' ) as $item ) {
		$q = $xp->query( './/button[contains(@class, "kit-faq-btn")]', $item )->item( 0 );
		$a = $xp->query( './/div[contains(@class, "kit-faq-a")]', $item )->item( 0 );
		if ( ! $q || ! $a ) {
			continue;
		}
		$inner = '';
		foreach ( $a->childNodes as $c ) {
			$inner .= $dom->saveHTML( $c );
		}
		$out[] = array(
			'q'      => aw_faq_plain( $q->textContent ),
			'a'      => trim( preg_replace( '/\s+/u', ' ', wp_kses( str_replace( array( '<strong>', '</strong>' ), array( '<b>', '</b>' ), $inner ), array( 'a' => array( 'href' => true ), 'p' => array(), 'b' => array(), 'ul' => array(), 'li' => array() ) ) ) ),
			'a_text' => aw_faq_plain( $a->textContent ),
		);
	}
	return $out;
}

/**
 * The page's FAQPage node, parsed from the rendered accordion (null without one). Put in the page's @graph by
 * aw-seo-extras/schema.php.
 *
 * @return array|null
 */
function aw_faq_schema() {
	$faq = aw_faq_build();
	$qa  = $faq ? aw_faq_parse( aw_faq_html( $faq ) ) : array();
	if ( count( $qa ) < aw_faq_min( $faq['ctx'] ?? null ) ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => $faq['ctx']['url'] . '#faq',
		'url'        => $faq['ctx']['url'],
		'name'       => $faq['ctx']['head'],
		'inLanguage' => 'en-IN',
		'isPartOf'   => array( '@id' => $faq['ctx']['page_id'] ),
		'about'      => array( '@id' => $faq['ctx']['about'] ),
		'mainEntity' => array_map( fn( $x ) => array( '@type' => 'Question', 'name' => $x['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $x['a'] ) ), $qa ),
	);
}

/* ---------- Placement ---------- */

/** The item FAQ block (Library documents and listings; nothing anywhere else). */
function aw_block_item_faq( $a = array(), $id = 0 ) {
	if ( ! function_exists( 'aw_item_here' ) || ! aw_item_here( $id ) ) {
		return function_exists( 'aw_item_editor_preview' ) ? aw_item_editor_preview( 'FAQ: 3 to 6 questions from this page’s own fields (none when fewer than 3)' ) : '';
	}
	return aw_faq_html();
}

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['item-faq'] = array( 'Item: FAQ', 'aw_block_item_faq', array() );
		return $blocks;
	}
);

// Hubs: the FAQ follows the page's content (its list), page 1 only, never on a Directory place or a filtered view.
add_filter(
	'render_block_core/post-content',
	function ( $html ) {
		static $done = false;
		if ( $done || ! is_page() ) {
			return $html;
		}
		$ctx = aw_faq_context();
		if ( ! $ctx || 'hub' !== $ctx['kind'] || ! aw_faq_hub_plain() ) {
			return $html;
		}
		$done = true;
		$faq  = aw_faq_html();
		return '' !== $faq ? $html . '<div class="aw-hub-faq alignwide">' . $faq . '</div>' : $html;
	}
);

/* ---------- Analytics: which experiment group a page is in ---------- */

add_filter(
	'kits_page_context',
	function ( $ctx ) {
		$c = aw_faq_context();
		if ( $c && in_array( $c['kind'], array( 'listing', 'legal' ), true ) ) {
			$ctx['faq_group'] = aw_faq_build( $c ) ? 'faq' : ( aw_faq_build( $c, false ) ? 'holdout' : 'none' );
		}
		return $ctx;
	}
);

/* ---------- Editing topic items: the term screen, the hub pages ---------- */

/** The editor rows (used on the term screen and in the hub pages' box). */
function aw_faq_editor( array $rows ) {
	$n = max( 6, count( $rows ) + 1 );
	wp_nonce_field( 'aw_faq_items', 'aw_faq_items_nonce' );
	echo '<p class="description">3 to 6 questions people search. First sentence answers and names the subject; at most 50 words; one link (the source or a section of the site); dates as 4 Oct 2026. Only <strong>approved</strong> questions are shown (and go into the FAQ schema); fewer than 3 approved = no FAQ. Answers may use links, bold and italic. Sources: one per line, "Name | URL". Empty question = row removed.</p>';
	for ( $i = 0; $i < $n; $i++ ) {
		$r   = $rows[ $i ] ?? array( 'q' => '', 'a' => '', 'sources' => array(), 'keyword' => '', 'status' => 'draft', 'reviewed' => '' );
		$src = implode( "\n", array_map( fn( $s ) => trim( $s['name'] . ' | ' . $s['url'], ' |' ), $r['sources'] ) );
		$wc  = '' !== $r['a'] ? aw_faq_word_count( $r['a'] ) : 0;
		echo '<fieldset style="border:1px solid #c3c4c7;padding:8px 12px;margin:0 0 12px"><legend><strong>Question ' . (int) ( $i + 1 ) . '</strong>' . ( $wc > AW_FAQ_WORDS ? ' <span style="color:#b32d2e">(' . (int) $wc . ' words: over 50)</span>' : '' ) . '</legend>';
		echo '<p><input type="text" class="large-text" name="aw_faq[' . (int) $i . '][q]" value="' . esc_attr( $r['q'] ) . '" placeholder="Question" aria-label="Question ' . (int) ( $i + 1 ) . '"></p>';
		echo '<p><textarea class="large-text" rows="3" name="aw_faq[' . (int) $i . '][a]" placeholder="Answer" aria-label="Answer ' . (int) ( $i + 1 ) . '">' . esc_textarea( $r['a'] ) . '</textarea></p>';
		echo '<p><textarea class="large-text" rows="2" name="aw_faq[' . (int) $i . '][sources]" placeholder="Sources: Name | URL, one per line" aria-label="Sources ' . (int) ( $i + 1 ) . '">' . esc_textarea( $src ) . '</textarea></p>';
		echo '<p><label>Target keyword <input type="text" name="aw_faq[' . (int) $i . '][keyword]" value="' . esc_attr( $r['keyword'] ) . '"></label> ';
		echo '<label>Status <select name="aw_faq[' . (int) $i . '][status]"><option value="draft"' . selected( $r['status'], 'draft', false ) . '>Draft</option><option value="approved"' . selected( $r['status'], 'approved', false ) . '>Approved</option></select></label> ';
		echo '<label>Last reviewed <input type="date" name="aw_faq[' . (int) $i . '][reviewed]" value="' . esc_attr( $r['reviewed'] ) . '"></label></p></fieldset>';
	}
}

/** The editor's posted rows, as items (sources "Name | URL" lines → list). */
function aw_faq_editor_rows() {
	$rows = array();
	foreach ( (array) wp_unslash( $_POST['aw_faq'] ?? array() ) as $r ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- checked by the caller; cleaned in aw_faq_items_save()
		$src = array();
		foreach ( preg_split( '/\R/', (string) ( $r['sources'] ?? '' ) ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( '' !== $p[0] || '' !== ( $p[1] ?? '' ) ) {
				$src[] = 1 === count( $p ) && preg_match( '#^https?://#', $p[0] ) ? array( 'name' => '', 'url' => $p[0] ) : array( 'name' => $p[0], 'url' => $p[1] ?? '' );
			}
		}
		$r['sources'] = $src;
		$rows[]       = $r;
	}
	return $rows;
}

/** Whether the posted editor may be saved. */
function aw_faq_editor_ok( $cap ) {
	return isset( $_POST['aw_faq_items_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_faq_items_nonce'] ) ), 'aw_faq_items' ) && current_user_can( $cap );
}

foreach ( array( 'post_tag', 'category' ) as $aw_faq_tax ) {
	add_action(
		"{$aw_faq_tax}_edit_form_fields",
		function ( $term ) {
			if ( ! aw_faq_term_allowed( $term ) ) {
				return;
			}
			echo '<tr class="form-field"><th scope="row">FAQ</th><td>';
			aw_faq_editor( aw_faq_items( 'term', $term->term_id ) );
			echo '</td></tr>';
		}
	);
	add_action(
		"edited_{$aw_faq_tax}",
		function ( $term_id ) {
			if ( aw_faq_editor_ok( 'manage_categories' ) && aw_faq_term_allowed( get_term( $term_id ) ) ) {
				aw_faq_items_save( 'term', $term_id, aw_faq_editor_rows() );
			}
		}
	);
}
unset( $aw_faq_tax );

add_action(
	'add_meta_boxes_page',
	function ( $post ) {
		if ( in_array( (int) $post->ID, aw_faq_hub_pages(), true ) ) {
			add_meta_box( 'aw-faq', 'FAQ', fn( $p ) => aw_faq_editor( aw_faq_items( 'post', $p->ID ) ), 'page', 'normal', 'low' );
		}
	}
);
add_action(
	'save_post_page',
	function ( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) && in_array( (int) $post_id, aw_faq_hub_pages(), true ) && aw_faq_editor_ok( 'edit_post' ) ) {
			aw_faq_items_save( 'post', $post_id, aw_faq_editor_rows() );
		}
	}
);

/* ---------- Resources guides: the FAQ box, and Rank Math FAQ blocks converted on save ---------- */

add_action(
	'add_meta_boxes_post',
	function ( $post ) {
		if ( ! has_term( 'research', 'post_tag', $post ) ) {
			add_meta_box( 'aw-faq', 'FAQ', fn( $p ) => aw_faq_editor( aw_faq_items( 'post', $p->ID ) ), 'post', 'normal', 'low' );
		}
	}
);
add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! empty( $GLOBALS['aw_faq_converting'] ) ) {
			return; // the conversion's own re-save must not overwrite the questions it just moved
		}
		if ( aw_faq_editor_ok( 'edit_post' ) ) {
			aw_faq_items_save( 'post', $post_id, aw_faq_editor_rows() );
		}
		aw_faq_convert_rank_math( $post_id ); // a Rank Math FAQ block added in the editor or by a publish script
	},
	20
);

/**
 * Move a guide's Rank Math FAQ block into its own FAQ (owner, 25 Oct 2026: one FAQ system, ours). Questions and answers
 * are kept word for word and approved (they were already published); the block and the heading just before it leave
 * the content; the removed markup is kept in _aw_faq_rm_block for Undo. A guide that already has FAQ items keeps them
 * and gets the Rank Math questions added after them.
 *
 * @param int  $post_id Post ID.
 * @param bool $dry     Report only.
 * @return array|null [ questions moved, words over 50, answers with more than one link ], or null when there was none.
 */
function aw_faq_convert_rank_math( $post_id, $dry = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || ! has_block( 'rank-math/faq-block', $post ) ) {
		return null;
	}
	$blocks  = parse_blocks( $post->post_content );
	$keep    = array();
	$removed = array();
	$moved   = array();
	foreach ( $blocks as $i => $b ) {
		if ( 'rank-math/faq-block' === $b['blockName'] ) {
			foreach ( (array) ( $b['attrs']['questions'] ?? array() ) as $q ) {
				if ( isset( $q['visible'] ) && ! $q['visible'] ) {
					continue;
				}
				$moved[] = array( 'q' => wp_strip_all_tags( (string) ( $q['title'] ?? '' ) ), 'a' => (string) ( $q['content'] ?? '' ) );
			}
			$prev = end( $keep );
			if ( $prev && 'core/heading' === $prev['blockName'] && preg_match( '/question|faq/i', wp_strip_all_tags( (string) $prev['innerHTML'] ) ) ) {
				$removed[] = array_pop( $keep );
			}
			$removed[] = $b;
			continue;
		}
		$keep[] = $b;
	}
	$long  = count( array_filter( $moved, fn( $m ) => aw_faq_word_count( $m['a'] ) > AW_FAQ_WORDS ) );
	$links = count( array_filter( $moved, fn( $m ) => substr_count( $m['a'], '<a ' ) > 1 ) );
	if ( $dry ) {
		return array( count( $moved ), $long, $links );
	}
	$items  = aw_faq_items( 'post', $post_id );
	$before = (string) get_post_meta( $post_id, 'aw_faq', true ); // the FAQ as it was, for undo
	$have   = array_map( fn( $x ) => strtolower( $x['q'] ), $items );
	foreach ( $moved as $m ) {
		if ( '' !== $m['q'] && ! in_array( strtolower( $m['q'] ), $have, true ) ) {
			$items[] = array( 'q' => $m['q'], 'a' => $m['a'], 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => wp_date( 'Y-m-d' ) );
		}
	}
	aw_faq_items_save( 'post', $post_id, $items );
	// One backup per conversion, newest last: undo reverses only the latest one (block back, FAQ as it was before it).
	$log   = json_decode( (string) get_post_meta( $post_id, '_aw_faq_rm_block', true ), true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'at'     => wp_date( 'Y-m-d H:i' ),
		'blocks' => serialize_blocks( $removed ),
		'faq'    => $before,
	);
	update_post_meta( $post_id, '_aw_faq_rm_block', wp_slash( wp_json_encode( $log ) ) );
	$GLOBALS['aw_faq_converting'] = true;
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => wp_slash( serialize_blocks( $keep ) ),
		)
	);
	$GLOBALS['aw_faq_converting'] = false;
	clean_post_cache( $post_id );
	return array( count( $moved ), $long, $links );
}

/* ---------- Library documents: the fields the FAQ reads that had no home ---------- */

add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_posts' );
		register_post_meta( 'aw_legal', 'aw_short_name', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Short name, as people search it (PCA Act 1960); used in FAQ questions', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_file_hi_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Hindi version (link to the official Hindi text)', 'sanitize_callback' => 'esc_url_raw', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_legal_act', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Issued under (the parent Act, as the document states it)', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_key_sections', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Key sections (Acts and Rules): one per line, "number | one-line plain summary | link"', 'sanitize_callback' => 'sanitize_textarea_field', 'auth_callback' => $auth ) );
	}
);

add_action(
	'add_meta_boxes_aw_legal',
	function () {
		add_meta_box(
			'aw-faq-legal',
			'FAQ fields',
			function ( $post ) {
				wp_nonce_field( 'aw_faq_legal', 'aw_faq_legal_nonce' );
				echo '<p class="description">Questions on this page come from its own fields (PDF, in force, date and number, issuer). These add more when filled.</p>';
				echo '<p><label for="aw-short-name">Short name, as people search it (e.g. PCA Act 1960, ABC Rules 2023). Questions use it; empty = the focus keyphrase</label><br><input type="text" class="large-text" id="aw-short-name" name="aw_short_name" value="' . esc_attr( get_post_meta( $post->ID, 'aw_short_name', true ) ) . '"></p>';
				echo '<p><label for="aw-file-hi">Hindi version (link to the official Hindi text)</label><br><input type="url" class="large-text" id="aw-file-hi" name="aw_file_hi_url" value="' . esc_attr( get_post_meta( $post->ID, 'aw_file_hi_url', true ) ) . '"></p>';
				echo '<p><label for="aw-legal-act">Issued under (the parent Act, as the document states it)</label><br><input type="text" class="large-text" id="aw-legal-act" name="aw_legal_act" value="' . esc_attr( get_post_meta( $post->ID, 'aw_legal_act', true ) ) . '"></p>';
				echo '<p><label for="aw-key-sections">Key sections (Acts and Rules only): one per line, "number | one-line plain summary | link"</label><br><textarea class="large-text" rows="4" id="aw-key-sections" name="aw_key_sections" placeholder="11 | punishment for treating animals cruelly | https://…">' . esc_textarea( get_post_meta( $post->ID, 'aw_key_sections', true ) ) . '</textarea></p>';
			},
			'aw_legal',
			'normal',
			'low'
		);
	}
);
add_action(
	'save_post_aw_legal',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['aw_faq_legal_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_faq_legal_nonce'] ) ), 'aw_faq_legal' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( 'aw_short_name' => 'sanitize_text_field', 'aw_file_hi_url' => 'esc_url_raw', 'aw_legal_act' => 'sanitize_text_field', 'aw_key_sections' => 'sanitize_textarea_field' ) as $k => $clean ) {
			$v = call_user_func( $clean, wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'' !== trim( $v ) ? update_post_meta( $post_id, $k, $v ) : delete_post_meta( $post_id, $k );
		}
	}
);

/* ---------- AW SEO Plus → FAQ: drafts to approve, counts, the experiment ---------- */

/** Every term and hub page with FAQ items: [ [ kind, id, label, edit URL, view URL, items ], … ]. */
function aw_faq_topic_pages() {
	$out   = array();
	$terms = get_terms( array( 'taxonomy' => array( 'post_tag', 'category' ), 'hide_empty' => false, 'meta_key' => 'aw_faq' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		$out[] = array( 'term', $t->term_id, ( 'category' === $t->taxonomy ? 'Category: ' : ( in_array( $t->slug, aw_listing_type_slugs(), true ) ? 'Type: ' : 'Service: ' ) ) . html_entity_decode( $t->name ), get_edit_term_link( $t ), kits_term_url( $t ), aw_faq_items( 'term', $t->term_id ) );
	}
	foreach ( aw_faq_hub_pages() as $slug => $pid ) {
		$items = aw_faq_items( 'post', $pid );
		if ( $items ) {
			$out[] = array( 'post', $pid, 'Page: ' . get_the_title( $pid ), get_edit_post_link( $pid, 'raw' ), get_permalink( $pid ), $items );
		}
	}
	foreach ( get_posts( array( 'post_type' => 'aw_legal', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_faq' ) ) as $pid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$items = 'template' === aw_faq_legal_kind( $pid ) ? aw_faq_items( 'post', $pid ) : array();
		if ( $items ) {
			$out[] = array( 'post', $pid, 'Library template: ' . html_entity_decode( get_the_title( $pid ) ), get_edit_post_link( $pid, 'raw' ), get_permalink( $pid ), $items );
		}
	}
	return $out;
}

/**
 * Counts for the report: published listings and Library documents by FAQ group (faq / holdout / none) and size, terms
 * and hubs with an FAQ shown. Heavy (builds every listing): the admin screen caches it for an hour.
 *
 * @return array
 */
function aw_faq_counts() {
	$c = array( 'listing' => array(), 'legal' => array(), 'term' => array( 'shown' => 0, 'drafts' => 0 ), 'hub' => array( 'shown' => 0, 'drafts' => 0 ) );
	foreach ( array( 'aw_listing' => 'listing', 'aw_legal' => 'legal' ) as $pt => $k ) {
		$c[ $k ] = array( 'total' => 0, 'faq' => 0, 'holdout' => 0, 'none' => 0, 'sizes' => array(), 'questions' => array() );
		foreach ( get_posts( array( 'post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) {
			$ctx = aw_faq_context( get_post( $id ) );
			$all = $ctx ? aw_faq_build( $ctx, false ) : null;
			++$c[ $k ]['total'];
			if ( ! $all ) {
				++$c[ $k ]['none'];
				continue;
			}
			$g = aw_faq_in_holdout( $id ) ? 'holdout' : 'faq';
			++$c[ $k ][ $g ];
			if ( 'faq' === $g ) {
				$n                       = count( $all['items'] );
				$c[ $k ]['sizes'][ $n ] = ( $c[ $k ]['sizes'][ $n ] ?? 0 ) + 1;
				foreach ( $all['items'] as $it ) {
					$c[ $k ]['questions'][ $it['key'] ] = ( $c[ $k ]['questions'][ $it['key'] ] ?? 0 ) + 1;
				}
			}
		}
		ksort( $c[ $k ]['sizes'] );
	}
	// Resources (owner, 25 Oct 2026): guides carry their own Rank Math FAQ block; research summaries get none (spec).
	$c['guide'] = array( 'total' => 0, 'faq' => 0, 'research' => 0, 'without' => array() );
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'no_found_rows' => true ) ) as $p ) {
		if ( has_term( 'research', 'post_tag', $p ) ) {
			++$c['guide']['research'];
			continue;
		}
		++$c['guide']['total'];
		$gctx = aw_faq_context( $p );
		if ( ( $gctx && aw_faq_build( $gctx, false ) ) || has_block( 'rank-math/faq-block', $p ) ) { // ours, or a Rank Math block not yet converted
			++$c['guide']['faq'];
		} else {
			$c['guide']['without'][] = (int) $p->ID;
		}
	}
	foreach ( aw_faq_topic_pages() as $p ) {
		if ( 'post' === $p[0] && 'aw_legal' === get_post_type( $p[1] ) ) {
			continue; // Library templates are counted in the Library row
		}
		$k        = 'term' === $p[0] ? 'term' : 'hub';
		$approved = count( array_filter( $p[5], fn( $i ) => 'approved' === $i['status'] ) );
		$c[ $k ]['shown']  += $approved >= AW_FAQ_MIN ? 1 : 0;
		$c[ $k ]['drafts'] += count( $p[5] ) - $approved;
	}
	return $c;
}

add_action(
	'admin_menu',
	function () {
		// AW SEO Plus (owner, 25 Oct 2026): the plugin's own top-level menu, FAQ first, then Search Insights.
		add_menu_page( 'AW SEO Plus', 'AW SEO Plus', 'manage_categories', 'aw-faq', 'aw_faq_admin_page', 'dashicons-chart-line', 81 );
		add_submenu_page( 'aw-faq', 'FAQ', 'FAQ', 'manage_categories', 'aw-faq', 'aw_faq_admin_page' );
	}
);

add_action(
	'admin_post_aw_faq_approve',
	function () {
		check_admin_referer( 'aw_faq_approve' );
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( 'Not allowed.' );
		}
		$picked = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['pick'] ?? array() ) );
		$action = sanitize_key( wp_unslash( $_POST['do'] ?? 'approve' ) );
		$done   = 0;
		foreach ( aw_faq_topic_pages() as $p ) {
			$items   = $p[5];
			$changed = false;
			foreach ( $items as $i => $it ) {
				if ( in_array( $p[0] . ':' . $p[1] . ':' . $i, $picked, true ) ) {
					$items[ $i ]['status']   = 'approve' === $action ? 'approved' : 'draft';
					$items[ $i ]['reviewed'] = wp_date( 'Y-m-d' );
					$changed                 = true;
					++$done;
				}
			}
			if ( $changed ) {
				aw_faq_items_save( $p[0], $p[1], $items );
			}
		}
		delete_transient( 'aw_faq_counts' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'aw-faq', 'done' => $done ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

add_action(
	'admin_post_aw_faq_rollout',
	function () {
		check_admin_referer( 'aw_faq_rollout' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		$e       = aw_faq_experiment();
		$e['on'] = ! empty( $_POST['on'] );
		$e['changed'] = wp_date( 'Y-m-d' );
		update_option( 'aw_faq_experiment', $e, false );
		delete_transient( 'aw_faq_counts' );
		wp_safe_redirect( add_query_arg( 'page', 'aw-faq', admin_url( 'admin.php' ) ) );
		exit;
	}
);

/** AW SEO Plus → FAQ (was Tools → FAQ; the old address redirects). */
function aw_faq_admin_page() {
	$e    = aw_faq_experiment();
	$end  = wp_date( 'Y-m-d', strtotime( $e['start'] . ' +' . (int) $e['weeks'] . ' weeks' ) );
	$c    = get_transient( 'aw_faq_counts' );
	if ( ! is_array( $c ) || isset( $_GET['recount'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$c = aw_faq_counts();
		set_transient( 'aw_faq_counts', $c, HOUR_IN_SECONDS );
	}
	echo '<div class="wrap"><h1>FAQ</h1>';
	if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>' . (int) $_GET['done'] . ' questions updated.</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	echo '<h2>Pages with an FAQ</h2>';
	$cells = array();
	foreach ( array( 'listing' => 'Directory listings', 'legal' => 'Library documents' ) as $k => $label ) {
		$s       = implode( ', ', array_map( fn( $n, $v ) => $n . ': ' . number_format_i18n( $v ), array_keys( $c[ $k ]['sizes'] ), $c[ $k ]['sizes'] ) );
		$cells[] = array(
			'type'    => esc_html( $label ),
			'total'   => esc_html( number_format_i18n( $c[ $k ]['total'] ) ),
			'faq'     => esc_html( number_format_i18n( $c[ $k ]['faq'] ) ),
			'holdout' => esc_html( number_format_i18n( $c[ $k ]['holdout'] ) ),
			'none'    => esc_html( number_format_i18n( $c[ $k ]['none'] ) ),
			'sizes'   => esc_html( $s ),
		);
	}
	// No colspan in a list table: the topic and hub lines carry their text in the second column.
	$cells[] = array( 'type' => 'Types, services, categories', 'total' => (int) $c['term']['shown'] . ' shown; ' . (int) $c['term']['drafts'] . ' draft questions waiting' );
	$cells[] = array( 'type' => 'Directory, Volunteer, Claim, Opportunities', 'total' => (int) $c['hub']['shown'] . ' shown; ' . (int) $c['hub']['drafts'] . ' draft questions waiting' );
	if ( isset( $c['guide'] ) ) {
		$g       = $c['guide'];
		$links   = implode( ', ', array_map( fn( $id ) => '<a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>', array_slice( $g['without'], 0, 20 ) ) );
		$cells[] = array(
			'type'  => 'Resources guides',
			'total' => esc_html( number_format_i18n( $g['total'] ) ),
			'faq'   => esc_html( number_format_i18n( $g['faq'] ) ),
			'none'  => esc_html( number_format_i18n( count( $g['without'] ) ) ) . ( $links ? ': ' . $links : '' ),
			'sizes' => esc_html( number_format_i18n( $g['research'] ) . ' research summaries not counted (no FAQ on Research)' ),
		);
	}
	aw_seo_extras_simple_table(
		'faq-pages',
		array( 'type' => 'Type', 'total' => 'Published', 'faq' => 'With FAQ', 'holdout' => 'Holdout (no FAQ)', 'none' => 'Fewer than 3 questions (no FAQ)', 'sizes' => 'Questions per FAQ' ),
		$cells
	);
	echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'aw-faq', 'recount' => 1 ), admin_url( 'admin.php' ) ) ) . '">Count again</a></p>';

	echo '<h2>Experiment</h2><p>' . ( ! empty( $e['on'] ) ? 'Running' : 'Rolled out: every eligible page shows its FAQ' ) . '. ' . (int) $e['pct'] . '% of eligible listings and Library documents (random, stable by ID) show no FAQ, from ' . aw_date( $e['start'] ) . ' for ' . (int) $e['weeks'] . ' weeks (to ' . aw_date( $end ) . '). Compare in Search Console (queries with "contact number", "pdf", "in hindi", "address") and GA4 (sessions from chatgpt.com, perplexity.ai, gemini.google.com) by page: the page lists are <code>wp aw-faq holdout</code>; GA4 also gets <code>faq_group</code> (faq, holdout, none) in the page context.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_faq_rollout">' . wp_nonce_field( 'aw_faq_rollout', '_wpnonce', true, false ) . ( ! empty( $e['on'] ) ? '<button class="button">Roll out to 100% (end the holdout)</button>' : '<input type="hidden" name="on" value="1"><button class="button">Restart the holdout</button>' ) . '</form>';

	echo '<h2>Topic questions</h2><p>Drafts are not shown on the site. Tick the questions to approve (a page shows its FAQ once it has 3 approved). Edit wording on the page’s own screen.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_faq_approve">' . wp_nonce_field( 'aw_faq_approve', '_wpnonce', true, false );
	foreach ( aw_faq_topic_pages() as $p ) {
		$ap = count( array_filter( $p[5], fn( $i ) => 'approved' === $i['status'] ) );
		echo '<h3 style="margin-top:2em">' . esc_html( $p[2] ) . ' <small>(' . (int) $ap . ' of ' . count( $p[5] ) . ' approved' . ( $ap >= AW_FAQ_MIN ? ', shown' : ', not shown' ) . ') — <a href="' . esc_url( $p[3] ) . '">edit</a> · <a href="' . esc_url( $p[4] ) . '">view</a></small></h3>';
		$cells = array();
		foreach ( $p[5] as $i => $it ) {
			$wc      = aw_faq_word_count( $it['a'] );
			$src     = implode( '<br>', array_map( fn( $s ) => $s['url'] ? '<a href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $s['name'] ? $s['name'] : $s['url'] ) . '</a>' : esc_html( $s['name'] ), $it['sources'] ) );
			$cells[] = array(
				'cb' => '<input type="checkbox" name="pick[]" value="' . esc_attr( $p[0] . ':' . $p[1] . ':' . $i ) . '" aria-label="' . esc_attr( $it['q'] ) . '">',
				'q'  => '<strong>' . esc_html( $it['q'] ) . '</strong> <span class="description">' . esc_html( $it['status'] ) . ( $it['reviewed'] ? ' ' . esc_html( $it['reviewed'] ) : '' ) . ' · keyword: ' . esc_html( $it['keyword'] ) . ' · ' . (int) $wc . ' words' . ( $wc > AW_FAQ_WORDS ? ' (over 50)' : '' ) . '</span><br>' . wp_kses( $it['a'], AW_FAQ_ANSWER_HTML ) . '<br><small>Sources: ' . ( $src ? $src : '<em>none</em>' ) . '</small>',
			);
		}
		aw_seo_extras_simple_table( 'faq-questions', array( 'cb' => '<input type="checkbox">', 'q' => 'Question' ), $cells, array( 'primary' => 'q', 'empty' => 'No questions.' ) ); // inside the approve form: no nonce of its own
	}
	echo '<p><button class="button button-primary" name="do" value="approve">Approve ticked</button> <button class="button" name="do" value="draft">Back to draft</button></p></form></div>';
}

/* ---------- WP-CLI ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * FAQ tools: report (counts and samples), check (the spec's tests on every page), holdout (URLs by group, CSV),
	 * import <file> (topic drafts: {"post_tag:slug"|"category:slug"|"page:slug"|"library:slug" (letter templates): [ {q, a, sources, keyword} ] }).
	 */
	WP_CLI::add_command(
		'aw-faq',
		function ( $args, $assoc ) {
			$cmd = $args[0] ?? 'report';
			if ( 'convert-guides' === $cmd ) { // Rank Math FAQ blocks on guides → their own FAQ (owner, 25 Oct 2026)
				$dry = ! empty( $assoc['dry-run'] );
				$tot = array( 0, 0, 0, 0 );
				foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
					$r = aw_faq_convert_rank_math( $id, $dry );
					if ( null === $r ) {
						continue;
					}
					WP_CLI::line( sprintf( '%d %s: %d questions%s%s', $id, get_post_field( 'post_name', $id ), $r[0], $r[1] ? ", $r[1] over 50 words" : '', $r[2] ? ", $r[2] with more than one link" : '' ) . ( $r[0] > AW_FAQ_MAX ? ', MORE THAN ' . AW_FAQ_MAX . ': only the first ' . AW_FAQ_MAX . ' show' : '' ) );
					++$tot[0];
					$tot[1] += $r[0];
					$tot[2] += $r[1];
					$tot[3] += $r[2];
				}
				delete_transient( 'aw_faq_counts' );
				WP_CLI::success( ( $dry ? 'Would convert ' : 'Converted ' ) . "$tot[0] guides, $tot[1] questions ($tot[2] over 50 words, $tot[3] with more than one link: kept as written)." );
				return;
			}
			if ( 'undo-guide' === $cmd ) { // reverse a guide's latest conversion: its Rank Math block back, its FAQ as it was before
				$id   = (int) ( $args[1] ?? 0 );
				$log  = json_decode( (string) get_post_meta( $id, '_aw_faq_rm_block', true ), true );
				$last = is_array( $log ) && $log ? array_pop( $log ) : null;
				if ( ! $last ) {
					WP_CLI::error( "Nothing to undo for $id." );
				}
				$GLOBALS['aw_faq_converting'] = true;
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => wp_slash( get_post_field( 'post_content', $id ) . "\n\n" . $last['blocks'] ),
					)
				);
				$GLOBALS['aw_faq_converting'] = false;
				if ( '' === (string) $last['faq'] ) {
					delete_post_meta( $id, 'aw_faq' );
				} else {
					update_post_meta( $id, 'aw_faq', wp_slash( $last['faq'] ) );
				}
				if ( $log ) {
					update_post_meta( $id, '_aw_faq_rm_block', wp_slash( wp_json_encode( $log ) ) );
				} else {
					delete_post_meta( $id, '_aw_faq_rm_block' );
				}
				WP_CLI::success( "Reversed the conversion of {$last['at']} on $id: Rank Math FAQ block back at the end of the content (converted again on its next save), FAQ as it was before." );
				return;
			}
			if ( 'import' === $cmd ) {
				$data = json_decode( (string) file_get_contents( $args[1] ?? '' ), true );
				if ( ! is_array( $data ) ) {
					WP_CLI::error( 'Not a JSON file.' );
				}
				$n = 0;
				foreach ( $data as $key => $rows ) {
					if ( ! preg_match( '/^(post_tag|category|page|library):([a-z0-9-]+)$/', (string) $key, $m ) || ! is_array( $rows ) ) {
						continue;
					}
					if ( 'library' === $m[1] ) { // a Library letter template, by slug
						$p    = get_page_by_path( $m[2], OBJECT, 'aw_legal' );
						$id   = $p && 'template' === aw_faq_legal_kind( $p->ID ) ? (int) $p->ID : 0;
						$kind = 'post';
					} elseif ( 'page' === $m[1] ) {
						$p = get_page_by_path( $m[2] );
						$id = $p && isset( AW_FAQ_HUBS[ $m[2] ] ) ? (int) $p->ID : 0;
						$kind = 'post';
					} else {
						$t = get_term_by( 'slug', $m[2], $m[1] );
						$id = $t && aw_faq_term_allowed( $t ) ? $t->term_id : 0;
						$kind = 'term';
					}
					if ( ! $id ) {
						WP_CLI::warning( "skipped $key" );
						continue;
					}
					$items = aw_faq_items( $kind, $id );
					$have  = array_map( fn( $i ) => strtolower( $i['q'] ), $items );
					foreach ( $rows as $r ) {
						if ( ! in_array( strtolower( trim( (string) ( $r['q'] ?? '' ) ) ), $have, true ) ) {
							$items[] = array( 'q' => $r['q'] ?? '', 'a' => $r['a'] ?? '', 'sources' => $r['sources'] ?? array(), 'keyword' => $r['keyword'] ?? '', 'status' => 'draft', 'reviewed' => '' );
							++$n;
						}
					}
					aw_faq_items_save( $kind, $id, $items );
				}
				WP_CLI::success( "$n draft questions added." );
				return;
			}
			if ( 'holdout' === $cmd ) {
				echo "url,type,group,questions\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( get_posts( array( 'post_type' => array( 'aw_listing', 'aw_legal' ), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) {
					$all = aw_faq_build( aw_faq_context( get_post( $id ) ), false );
					if ( $all ) {
						echo esc_url_raw( get_permalink( $id ) ) . ',' . esc_html( get_post_type( $id ) ) . ',' . ( aw_faq_in_holdout( $id ) ? 'holdout' : 'faq' ) . ',' . count( $all['items'] ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}
				return;
			}
			if ( 'check' === $cmd ) {
				// Spec §9 on every listing, Library document, term and hub: 3–6 questions or no FAQ; schema text = visible text;
				// no duplicate question; ≤50 words; one link; holdout pages have none.
				$fail = 0;
				$seen = 0;
				$objs = array();
				foreach ( get_posts( array( 'post_type' => array( 'aw_listing', 'aw_legal' ), 'post_status' => 'publish', 'numberposts' => -1, 'no_found_rows' => true ) ) as $p ) {
					$objs[] = $p;
				}
				foreach ( get_terms( array( 'taxonomy' => array( 'post_tag', 'category' ), 'hide_empty' => false ) ) as $t ) {
					$objs[] = $t;
				}
				foreach ( aw_faq_hub_pages() as $pid ) {
					$objs[] = get_post( $pid );
				}
				$held   = 0;
				$kwmiss = 0;
				foreach ( $objs as $o ) {
					$ctx = aw_faq_context( $o );
					$faq = $ctx ? aw_faq_build( $ctx ) : null;
					$id  = $o instanceof WP_Term ? 'term ' . $o->taxonomy . ':' . $o->slug : $o->post_type . ' ' . $o->ID;
					if ( $ctx && in_array( $ctx['kind'], array( 'listing', 'legal' ), true ) && aw_faq_in_holdout( $ctx['id'] ) && aw_faq_build( $ctx, false ) ) {
						++$held;
						if ( null !== $faq ) {
							++$fail;
							WP_CLI::line( $id . ': holdout page has an FAQ' );
						}
						continue;
					}
					if ( ! $faq ) {
						continue;
					}
					++$seen;
					$qa   = aw_faq_parse( aw_faq_html( $faq ) );
					$errs = array();
					if ( count( $qa ) < aw_faq_min( $faq['ctx'] ?? null ) || count( $qa ) > AW_FAQ_MAX ) {
						$errs[] = count( $qa ) . ' questions';
					}
					if ( count( array_unique( array_map( 'strtolower', wp_list_pluck( $qa, 'q' ) ) ) ) !== count( $qa ) ) {
						$errs[] = 'duplicate question';
					}
					foreach ( $qa as $i => $x ) {
						if ( aw_faq_plain( $x['a'] ) !== $x['a_text'] ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' schema text differs';
						}
						if ( aw_faq_word_count( $x['a'] ) > AW_FAQ_WORDS ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' over 50 words';
						}
						if ( 1 !== substr_count( $x['a'], '<a ' ) ) {
							$errs[] = 'Q' . ( $i + 1 ) . ' has ' . substr_count( $x['a'], '<a ' ) . ' links';
						}
					}
					$kw = strtolower( trim( explode( ',', (string) ( $o instanceof WP_Term ? get_term_meta( $o->term_id, 'rank_math_focus_keyword', true ) : get_post_meta( $o->ID, 'rank_math_focus_keyword', true ) ) )[0] ) );
					$fold = fn( $t ) => preg_replace( '/\s+/u', ' ', str_replace( array( '–', '—', '’', '‘' ), array( '-', '-', "'", "'" ), mb_strtolower( $t ) ) ); // dashes and quotes as typed in a keyphrase
					if ( '' !== $kw && count( array_filter( wp_list_pluck( $qa, 'q' ), fn( $q ) => false !== strpos( $fold( $q ), $fold( $kw ) ) ) ) < 2 ) {
						++$kwmiss;
						if ( ! empty( $assoc['verbose'] ) ) {
							WP_CLI::line( $id . ': focus keyphrase "' . $kw . '" in fewer than 2 questions' );
						}
					}
					if ( $errs ) {
						++$fail;
						WP_CLI::line( $id . ': ' . implode( '; ', $errs ) );
					}
				}
				WP_CLI::line( "$kwmiss pages have their focus keyphrase in fewer than 2 questions (--verbose lists them)." );
				$fail ? WP_CLI::error( "$fail of $seen pages with an FAQ fail ($held holdout pages)." ) : WP_CLI::success( "$seen pages with an FAQ pass; $held holdout pages show none." );
				return;
			}
			// report
			$c = aw_faq_counts();
			WP_CLI::line( wp_json_encode( $c, JSON_PRETTY_PRINT ) );
		}
	);
}

// Old Tools addresses lead to the AW SEO Plus menu (animal-writes-core admin-ia.php redirects them).
add_filter( 'aw_admin_moved_pages', fn( $m ) => $m + array( 'aw-faq' => 'admin.php' ) );
