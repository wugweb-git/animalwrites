<?php
/**
 * Animal Writes' FAQ data for AW SEO Plus (owner, 31 Oct 2026: the plugin's FAQ is a generic feature; the site's own
 * rules live here, through its filters). Nothing here runs unless AW SEO Plus calls its filters, except the Library
 * "Library item" box and its meta, which are Library fields.
 *
 * - aw_faq_registry: the questions built from a Directory listing's and a Library document's own fields (each entry a
 *   question template, the fields it needs, an answer builder, a priority in Search Insights demand order).
 * - aw_faq_context: the entity name (a Library document's short name), the schema node the FAQ is about
 *   (#organization, #legislation …), the list pages' CollectionPage node (#page).
 * - aw_faq_show_here: a Directory place or type view shows no page FAQ.
 *
 * The answer builders use the plugin's sentence helpers (aw_faq_a, aw_faq_plain, aw_faq_and, aw_faq_lc), which exist
 * whenever the plugin calls them. docs/faq.md describes the questions.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- The registry ---------- */

add_filter(
	'aw_faq_registry',
	function ( $r ) {
		$r['aw_listing'] = array(
			array( 'key' => 'phone', 'priority' => 1, 'q' => 'What is the contact number of %s?', 'needs' => array( 'aw_phones', 'aw_darpan_id (else hidden)' ), 'pattern' => '<org> contact number / helpline number', 'answer' => 'aw_faq_listing_phone', 'hidden' => fn( $id ) => ! aw_faq_listing_phone_allowed( $id ), 'hidden_why' => 'phone numbers are shown in FAQs only for organisations with an NGO Darpan ID' ),
			array( 'key' => 'location', 'priority' => 2, 'q' => 'Where is %s located?', 'needs' => array( 'aw_address', 'district' ), 'pattern' => '<org> <city> / address', 'answer' => 'aw_faq_listing_location' ),
			array( 'key' => 'services', 'priority' => 3, 'q' => 'What services does %s offer?', 'needs' => array( 'aw_services', 'type' ), 'pattern' => '<org> services / adoption / rescue', 'answer' => 'aw_faq_listing_services' ),
			array( 'key' => 'awbi', 'priority' => 4, 'q' => 'Is %s recognised by the Animal Welfare Board of India?', 'needs' => array( 'aw_awbi_code' ), 'pattern' => '<org> awbi', 'answer' => 'aw_faq_listing_awbi' ),
			array( 'key' => 'volunteer', 'priority' => 5, 'q' => 'How can I volunteer with %s?', 'needs' => array( 'aw_volunteer_url' ), 'pattern' => '<org> volunteer', 'answer' => 'aw_faq_listing_volunteer' ),
			array( 'key' => 'hours', 'priority' => 7, 'q' => 'What are the opening hours of %s?', 'needs' => array( 'aw_opening_hours' ), 'pattern' => '<org> timings', 'answer' => 'aw_faq_listing_hours' ),
			array( 'key' => 'adoption', 'priority' => 8, 'q' => 'Does %s offer adoption?', 'needs' => array( 'aw_adopt_url', 'Adoption Service' ), 'pattern' => '<org> adoption', 'answer' => 'aw_faq_listing_adoption' ),
			array( 'key' => 'registration', 'priority' => 9, 'q' => 'What is the registration number or NGO Darpan ID of %s?', 'needs' => array( 'aw_registration', 'aw_darpan_id' ), 'pattern' => '<org> registration', 'answer' => 'aw_faq_listing_registration' ),
			array( 'key' => 'animals', 'priority' => 10, 'q' => 'Which animals does %s help?', 'needs' => array( 'aw_animals' ), 'pattern' => '—', 'answer' => 'aw_faq_listing_animals' ),
			// After the questions above (owner, 1 Nov 2026), from fields the listing page shows.
			array( 'key' => 'type', 'priority' => 11, 'q' => 'What kind of organisation is %s?', 'needs' => array( 'listing type tag' ), 'pattern' => '<org> / <type> <district>', 'answer' => 'aw_faq_listing_type' ),
			array( 'key' => 'website', 'priority' => 12, 'q' => 'What is the website of %s?', 'needs' => array( 'aw_website (not marked unverified; not nofollow)' ), 'pattern' => '<org> website', 'answer' => 'aw_faq_listing_website' ),
			array( 'key' => 'email', 'priority' => 13, 'q' => 'What is the email address of %s?', 'needs' => array( 'aw_email (not marked unverified)' ), 'pattern' => '<org> email', 'answer' => 'aw_faq_listing_email' ),
			array( 'key' => 'nearby', 'priority' => 14, 'q' => 'Which other organisations are in %s?', 'needs' => array( 'district (else state)', '1 other listing there' ), 'pattern' => 'animal ngo <district>', 'answer' => 'aw_faq_listing_nearby', 'question' => 'aw_faq_listing_nearby_question' ),
		);
		$r['aw_legal']   = array(
			array( 'key' => 'pdf', 'priority' => 1, 'q' => 'Where can I download the %s PDF?', 'needs' => array( 'aw_file_id (PDF)', 'aw_source_url (.pdf)' ), 'pattern' => '<doc> pdf / pdf download', 'answer' => 'aw_faq_legal_pdf', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'force', 'priority' => 2, 'q' => 'Is the %s in force?', 'needs' => array( 'aw_legal_force' ), 'pattern' => '<doc> latest / today', 'answer' => 'aw_faq_legal_force', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'date', 'priority' => 3, 'q' => 'When was the %s %s?', 'needs' => array( 'aw_source_date', 'aw_legal_number (not needed for official releases and circulars)' ), 'pattern' => '<doc> gazette / notification', 'answer' => 'aw_faq_legal_date', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'issuer', 'priority' => 4, 'q' => 'Who issued the %s?', 'needs' => array( 'aw_issuer (not for Acts and Rules: the stored issuer is whose copy it is)' ), 'pattern' => '—', 'answer' => 'aw_faq_legal_issuer', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'read', 'priority' => 7, 'q' => 'Where can I read the %s?', 'needs' => array( 'aw_source_url (not a PDF)', 'aw_issuer', 'official release' ), 'pattern' => '<doc> press release', 'answer' => 'aw_faq_legal_read', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'sections', 'priority' => 6, 'q' => 'What does section %2$s of the %1$s say?', 'needs' => array( 'aw_key_sections (Acts and Rules)' ), 'pattern' => 'pca act section 11 / punishment', 'answer' => 'aw_faq_legal_sections' ),
		);
		// Resources research summaries (the "research" tag): from the study's own fields. Guides get none from here.
		$r['post']     = array(
			array( 'key' => 'study-authors', 'priority' => 1, 'q' => 'Who carried out %s?', 'needs' => array( 'aw_authors', 'aw_source_url', 'focus keyphrase' ), 'pattern' => '<topic> study <place>', 'answer' => 'aw_faq_study_authors', 'question' => 'aw_faq_study_question' ),
			array( 'key' => 'study-journal', 'priority' => 2, 'q' => 'Where was %s published?', 'needs' => array( 'aw_journal or aw_source_name', 'aw_source_date', 'aw_source_url' ), 'pattern' => '<topic> study journal', 'answer' => 'aw_faq_study_journal', 'question' => 'aw_faq_study_question' ),
			array( 'key' => 'study-about', 'priority' => 3, 'q' => 'What is %s about?', 'needs' => array( 'excerpt (first sentences, up to 35 words)', 'aw_source_url' ), 'pattern' => '<topic> study findings', 'answer' => 'aw_faq_study_about', 'question' => 'aw_faq_study_question' ),
			array( 'key' => 'study-law', 'priority' => 4, 'q' => 'Which law does %s discuss?', 'needs' => array( 'aw_entities laws (a Library document)' ), 'pattern' => '<law> study', 'answer' => 'aw_faq_study_law', 'question' => 'aw_faq_study_question' ),
		);
		// Tags: from the items tagged. None for a Directory listing type or a tag with written questions of its own
		// (those keep only theirs), nor for a tag with fewer than 4 items. Opportunities are left out (paused).
		$r['post_tag'] = array(
			array( 'key' => 'tag-news', 'priority' => 1, 'q' => 'What is the latest news on %s?', 'needs' => array( 'News items' ), 'pattern' => '<tag> news', 'answer' => 'aw_faq_tag_news', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-library', 'priority' => 1.1, 'q' => 'What is the latest %s in the Library?', 'needs' => array( 'Library documents' ), 'pattern' => 'latest <tag>', 'answer' => 'aw_faq_tag_library', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-resource', 'priority' => 1.2, 'q' => 'What is the newest %s on Animal Writes?', 'needs' => array( 'Resources' ), 'pattern' => '<tag>', 'answer' => 'aw_faq_tag_resource', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-directory', 'priority' => 2, 'q' => 'Which organisations offer %s in India?', 'needs' => array( '3 Directory listings with a state' ), 'pattern' => '<tag> near me', 'answer' => 'aw_faq_tag_directory', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-issuers', 'priority' => 3, 'q' => 'Who issues the %s in the Library?', 'needs' => array( '2 Library documents with an issuer (not Acts, Rules, templates)' ), 'pattern' => '<issuer> <tag>', 'answer' => 'aw_faq_tag_issuers', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-topics', 'priority' => 3.1, 'q' => 'Which topics come up most under %s?', 'needs' => array( '3 items (not listings) with a category' ), 'pattern' => '<tag> <topic>', 'answer' => 'aw_faq_tag_topics', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-districts', 'priority' => 4, 'q' => 'Which districts have the most %s listings?', 'needs' => array( '3 Directory listings with a district' ), 'pattern' => '<tag> <district>', 'answer' => 'aw_faq_tag_districts', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-journals', 'priority' => 5, 'q' => 'Which journals published the %s studies?', 'needs' => array( '3 Resources with aw_journal' ), 'pattern' => '<tag> journal', 'answer' => 'aw_faq_tag_journals', 'question' => 'aw_faq_tag_question' ),
			// After every question above, so a tag that already shows 6 keeps them.
			array( 'key' => 'tag-animals', 'priority' => 6, 'q' => 'Which animals do the %s organisations help?', 'needs' => array( '3 Directory listings with aw_animals' ), 'pattern' => '<tag> for <animal>', 'answer' => 'aw_faq_tag_animals', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-laws', 'priority' => 6.1, 'q' => 'Which laws do the %s explain?', 'needs' => array( '3 Resources or news items linking to a Library document' ), 'pattern' => '<law> <tag>', 'answer' => 'aw_faq_tag_laws', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-in-force', 'priority' => 6.2, 'q' => 'Which %s are in force?', 'needs' => array( '2 Library documents with aw_legal_force' ), 'pattern' => '<tag> in force', 'answer' => 'aw_faq_tag_in_force', 'question' => 'aw_faq_tag_question' ),
			array( 'key' => 'tag-list', 'priority' => 6.3, 'q' => 'Which templates does the Library have?', 'needs' => array( 'the template tag; 3 Library documents' ), 'pattern' => '<tag> list', 'answer' => 'aw_faq_tag_list', 'question' => 'aw_faq_tag_question' ),
		);
		return $r;
	}
);

/**
 * A Library question: the verb for the date question, and "Are the … Rules in force?" / "When were …" for plural names.
 *
 * @param string $tpl Question template.
 * @param int    $id  Document ID.
 * @param string $doc Document short name.
 * @param string $key Registry key.
 * @return string
 */
function aw_faq_legal_question( $tpl, $id, $doc, $key ) {
	$verb = 'date' === $key ? aw_faq_legal_verb( $id ) : '';
	if ( aw_faq_is_topic_name( $doc ) ) { // "the press release on …": "When was it issued?", "the PDF of the …"
		$verb = 'released' === $verb ? 'issued' : $verb;
		$tpl  = 'pdf' === $key ? 'Where can I download the PDF of the %s?' : $tpl;
	}
	$q = sprintf( $tpl, $doc, $verb );
	return aw_faq_plural( $doc ) ? preg_replace( array( '/^Is the /', '/^When was the /' ), array( 'Are the ', 'When were the ' ), $q ) : $q;
}

/* ---------- The context: names and schema nodes ---------- */

add_filter(
	'aw_faq_context',
	function ( $ctx, $obj ) {
		if ( ! is_array( $ctx ) ) {
			return $ctx;
		}
		if ( $obj instanceof WP_Post && 'aw_listing' === $obj->post_type ) {
			$ctx['kind']  = 'listing';
			$ctx['about'] = $ctx['url'] . '#organization';
		} elseif ( $obj instanceof WP_Post && 'aw_legal' === $obj->post_type ) {
			$kind          = aw_faq_legal_kind( $obj->ID );
			$ctx['kind']   = 'legal';
			$ctx['entity'] = aw_faq_doc_short( $obj->ID );
			$ctx['full']   = aw_faq_is_topic_name( $ctx['entity'] ) ? '' : aw_faq_doc_name( $obj->ID ); // the full official title, once, in the first answer; a release or circular named by its topic keeps the topic (its title is a headline)
			$ctx['about']  = $ctx['url'] . ( in_array( $kind, array( 'act', 'rule', 'notification' ), true ) ? '#legislation' : ( 'template' === $kind ? '#document' : '#article' ) );
			$ctx['head']   = 'Questions about the ' . $ctx['entity'];
		} elseif ( $obj instanceof WP_Post && 'page' === $obj->post_type && in_array( $obj->post_name, array( 'directory', 'opportunities' ), true ) ) {
			$ctx['page_id'] = $ctx['url'] . '#page'; // the list pages' CollectionPage (aw_hub_schema_nodes)
			$ctx['about']   = $ctx['page_id'];
		} elseif ( $obj instanceof WP_Term && in_array( $obj->taxonomy, array( 'post_tag', 'category' ), true ) ) {
			$ctx['page_id'] = $ctx['url'] . '#page'; // the term page's own CollectionPage
			$ctx['about']   = $ctx['page_id'];
		}
		return $ctx;
	},
	10,
	2
);

// A Directory place or type view (/directory/<state>/, /directory/type/…) is a list of its own: no page FAQ.
add_filter( 'aw_faq_show_here', fn( $show ) => $show && ! get_query_var( 'aw_place' ) && ! get_query_var( 'aw_dir_type' ) );

/* ---------- Document names ---------- */

/** A document title for a sentence: "The Transport of Animals Rules, 1978" → "Transport of Animals Rules, 1978". */
function aw_faq_doc_name( $id ) {
	return preg_replace( '/^The\s+/u', '', aw_faq_plain( get_the_title( $id ) ) );
}

/** Whether a document name is plural ("ABC Rules 2023", "… Amendment Rules, 2026"): "Are … in force?", "were notified". */
function aw_faq_plural( $name ) {
	if ( aw_faq_is_topic_name( $name ) ) {
		return false; // "the circular on ABC guidelines" is one circular
	}
	return (bool) preg_match( '/\b(Rules|Acts|Regulations|Guidelines|Orders|Judgments)\b/iu', (string) $name );
}

/** "is"/"are", "was"/"were" for a document name. */
function aw_faq_be( $name, $past = false ) {
	return aw_faq_plural( $name ) ? ( $past ? 'were' : 'are' ) : ( $past ? 'was' : 'is' );
}

/**
 * A document's short name, the way people search it (owner, 20 Oct 2026): the "Short name" field ("PCA Act 1960", "ABC
 * Rules 2023"), else the primary focus keyphrase (for an official release or a circular, "press release on <keyphrase>" /
 * "circular on <keyphrase>"), else the title. Questions use it; the first answer gives the full
 * official title once.
 */
function aw_faq_doc_short( $id ) {
	$s = aw_faq_plain( get_post_meta( $id, 'aw_short_name', true ) );
	if ( '' === $s ) {
		$s = trim( explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) )[0] );
		$k = '' !== $s ? aw_faq_legal_kind( $id ) : '';
		if ( ( 'official-release' === $k || 'circular' === $k ) && ! preg_match( '/\b(?:circulars?|releases?)\b/iu', $s ) ) { // a release's or circular's keyphrase is its topic, not its name ("awbi world animal day circular" is a name)
			$s = ( 'circular' === $k ? 'circular on ' : 'press release on ' ) . $s;
		}
	}
	return '' !== $s ? $s : aw_faq_doc_name( $id );
}

/** Whether a document name is a topic name from aw_faq_doc_short() ("press release on …", "circular on …"). */
function aw_faq_is_topic_name( $name ) {
	return (bool) preg_match( '/^(?:press release|circular) on /u', (string) $name );
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
 * Whether a listing's FAQ may give its phone and WhatsApp numbers (owner, 1 Nov 2026): only larger organisations, for
 * now those with an NGO Darpan ID that Darpan has not blacklisted. Elsewhere the question stays in the FAQ box, hidden.
 * The numbers stay on the listing page and in its schema either way.
 */
function aw_faq_listing_phone_allowed( $id ) {
	return '' !== aw_lf( $id, 'aw_darpan_id' ) && '' === aw_lf( $id, 'aw_darpan_blacklisted_on' ) && '' === aw_lf( $id, 'aw_darpan_blacklisted_by' );
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
		if ( aw_faq_listing_type( $id, $name ) && aw_faq_listing_type_fits( $id, $name ) ) {
			return null; // the type answer says what kind of organisation it is; services read off the type would repeat it
		}
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

/**
 * Whether the type question makes the FAQ when "services based on type" gives way to it: fewer than AW_FAQ_MAX
 * questions shown before it, counting the listing's other answers of a higher priority in the default order (hidden
 * and repeated facts left out, as the FAQ box does). Otherwise the services answer stays, so one of the two shows.
 */
function aw_faq_listing_type_fits( $id, $name ) {
	$reg  = (array) ( apply_filters( 'aw_faq_registry', array() )['aw_listing'] ?? array() );
	$prio = (float) ( array_column( $reg, 'priority', 'key' )['type'] ?? 0 );
	$seen = array();
	foreach ( $reg as $e ) {
		if ( in_array( $e['key'], array( 'services', 'type' ), true ) || $e['priority'] >= $prio ) {
			continue;
		}
		$h = $e['hidden'] ?? false;
		if ( is_callable( $h ) ? call_user_func( $h, $id, $name ) : (bool) $h ) {
			continue;
		}
		$r = call_user_func( $e['answer'], $id, $name );
		if ( $r && isset( $r[0] ) && is_string( $r[0] ) ) {
			$seen[ (string) $r[1] ] = true;
		}
	}
	return count( $seen ) < ( defined( 'AW_FAQ_MAX' ) ? AW_FAQ_MAX : 6 );
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

/**
 * How a listing type reads in a sentence: slug => [ singular with its article, plural ]. A type not here reads as its
 * tag name in lower case.
 */
const AW_FAQ_TYPE_WORDS = array(
	'abc-centre'                  => array( 'an ABC centre', 'ABC centres' ),
	'adoption-network'            => array( 'an adoption network', 'adoption networks' ),
	'animal-ambulance'            => array( 'an animal ambulance service', 'animal ambulance services' ),
	'animal-shelter'              => array( 'an animal shelter', 'animal shelters' ),
	'animal-welfare-organisation' => array( 'an animal welfare NGO', 'animal welfare NGOs' ),
	'bird-hospital'               => array( 'a bird hospital', 'bird hospitals' ),
	'emergency-rescue'            => array( 'an emergency rescue service', 'emergency rescue services' ),
	'feeder-network'              => array( 'a feeder network', 'feeder networks' ),
	'gaushala'                    => array( 'a gaushala', 'gaushalas' ),
	'legal-aid'                   => array( 'a legal aid organisation', 'legal aid organisations' ),
	'mobile-veterinary-unit'      => array( 'a mobile veterinary unit', 'mobile veterinary units' ),
	'rescue-group'                => array( 'a rescue group', 'rescue groups' ),
	'sanctuary'                   => array( 'an animal sanctuary', 'animal sanctuaries' ),
	'veterinary-clinic'           => array( 'a veterinary clinic', 'veterinary clinics' ),
	'veterinary-dispensary'       => array( 'a veterinary dispensary', 'veterinary dispensaries' ),
	'veterinary-hospital'         => array( 'a veterinary hospital', 'veterinary hospitals' ),
	'veterinary-office'           => array( 'a government veterinary office', 'government veterinary offices' ),
	'wildlife-rescue'             => array( 'a wildlife rescue group', 'wildlife rescue groups' ),
);

/** [ singular with its article, plural ] for a listing type tag. */
function aw_faq_type_words( $t ) {
	if ( isset( AW_FAQ_TYPE_WORDS[ $t->slug ] ) ) {
		return AW_FAQ_TYPE_WORDS[ $t->slug ];
	}
	$n = aw_faq_lc( aw_faq_plain( $t->name ) );
	return array( ( preg_match( '/^[aeiou]/i', $n ) ? 'an ' : 'a ' ) . $n, $n . 's' );
}

/**
 * Published listings of each type by district code (aw_place_counts() pairs) and by state code (counted on the state
 * code, so listings with a state and no district are in it, as on the state pages).
 *
 * @return array{district: array, state: array}
 */
function aw_faq_type_counts() {
	static $idx = null;
	if ( null !== $idx ) {
		return $idx;
	}
	$idx = array( 'district' => array(), 'state' => array() );
	foreach ( function_exists( 'aw_place_counts' ) ? (array) ( aw_place_counts()['pairs'] ?? array() ) : array() as $p ) {
		list( $code, $type, , $n ) = $p;
		$idx['district'][ $code ][ $type ] = $n;
	}
	$state = get_transient( 'aw_faq_state_type_counts' );
	if ( ! is_array( $state ) ) {
		global $wpdb;
		$state = array();
		$slugs = function_exists( 'aw_listing_type_slugs' ) ? (array) aw_listing_type_slugs() : array();
		$skip  = function_exists( 'kits_preview_only_ids' ) && kits_preview_only_ids() ? ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', kits_preview_only_ids() ) ) . ')' : ''; // preview-only items
		$types = "'" . implode( "','", array_map( 'esc_sql', $slugs ) ) . "'";
		$rows  = $slugs ? $wpdb->get_results( "SELECT sm.meta_value code, t.slug type, COUNT(DISTINCT p.ID) n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = 'aw_state_code' JOIN {$wpdb->term_relationships} rt ON rt.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = rt.term_taxonomy_id AND tt.taxonomy = 'post_tag' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ({$types}) WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'{$skip} GROUP BY sm.meta_value, t.slug", ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $r ) {
			$state[ (int) $r['code'] ][ $r['type'] ] = (int) $r['n'];
		}
		set_transient( 'aw_faq_state_type_counts', $state, HOUR_IN_SECONDS );
	}
	$idx['state'] = $state;
	return $idx;
}

add_action( 'save_post_aw_listing', fn() => delete_transient( 'aw_faq_state_type_counts' ) ); // fresh state counts after a listing is saved

/** Whether a listing value is shown marked unverified (kept next to another value). */
function aw_faq_unverified( $id, $value ) {
	return function_exists( 'aw_unverified_list' ) && in_array( aw_unverified_norm( $value ), aw_unverified_list( $id ), true );
}

function aw_faq_listing_type( $id, $name ) {
	$types = array_slice( aw_listing_types( $id ), 0, 3 ); // the type chips the page shows, in their order
	if ( ! $types ) {
		return null;
	}
	$words = array_map( 'aw_faq_type_words', $types );
	$place = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	list( $state, $district ) = aw_listing_place( $id );
	$where = implode( ', ', array_filter( array( $district, $state ) ) );
	$a     = esc_html( $name ) . ' is ' . esc_html( aw_faq_and( array_column( $words, 0 ) ) ) . ( '' !== $where ? ' in ' . esc_html( $where ) : '' ) . '. ';
	$slug  = $types[0]->slug;
	$plur  = $words[0][1];
	$cnt   = aw_faq_type_counts();
	$self  = 'publish' === get_post_status( $id ) ? 1 : 0; // the counts include the listing itself once published
	if ( $place && 'district' === $place->kind && ( $cnt['district'][ $place->code ][ $slug ] ?? 0 ) > $self ) { // others of its type in its district
		$link = aw_faq_a( aw_directory_url( $place->slug, $slug ), 'See other ' . $plur . ' in ' . $place->name );
	} elseif ( $place && ( $cnt['state'][ 'district' === $place->kind ? $place->state : $place->code ][ $slug ] ?? 0 ) > $self ) { // else in its state
		$st   = 'district' === $place->kind ? aw_place_state( $place->state ) : $place;
		$link = aw_faq_a( aw_directory_url( $st->slug, $slug ), 'See other ' . $plur . ' in ' . $st->name );
	} else {
		$link = aw_faq_a( kits_term_url( $types[0] ), 'See ' . $plur . ' in India' );
	}
	return array( $a . $link . '.', 'type' );
}

function aw_faq_listing_website( $id, $name ) {
	$url  = aw_lf( $id, 'aw_website' );
	$host = preg_replace( '/^www\./i', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host || aw_faq_unverified( $id, $url ) || false !== strpos( aw_listing_rel( $id ), 'nofollow' ) ) {
		return null; // an answer link carries no rel: a nofollow or sponsored listing keeps its website on the page only
	}
	return array( esc_html( $name ) . '’s website is ' . aw_faq_a( $url, $host ) . '.', 'website' );
}

function aw_faq_listing_email( $id, $name ) {
	$email = aw_lf( $id, 'aw_email' );
	if ( ! is_email( $email ) || aw_faq_unverified( $id, $email ) ) {
		return null;
	}
	return array( 'You can email ' . esc_html( $name ) . ' at ' . aw_faq_a( 'mailto:' . $email, $email ) . '.', 'email' );
}

/** The listing's district, else its state, and how many other published listings it has. */
function aw_faq_listing_nearby_place( $id ) {
	$place = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
	if ( ! $place ) {
		return null;
	}
	$self = 'publish' === get_post_status( $id ) ? 1 : 0;
	foreach ( array( $place, 'district' === $place->kind ? aw_place_state( $place->state ) : null ) as $p ) { // the district, else its state
		$others = $p ? (int) aw_place_count( $p ) - $self : 0;
		if ( $others > 0 ) {
			return array( $p, $others );
		}
	}
	return null;
}

function aw_faq_listing_nearby_question( $tpl, $id ) {
	$p = aw_faq_listing_nearby_place( $id );
	return sprintf( $tpl, $p ? $p[0]->name : '' );
}

function aw_faq_listing_nearby( $id ) {
	$p = aw_faq_listing_nearby_place( $id );
	if ( ! $p ) {
		return null;
	}
	list( $place, $others ) = $p;
	$st    = 'district' === $place->kind ? aw_place_state( $place->state ) : null;
	$where = $st ? $place->name . ', ' . $st->name : $place->name;
	$a = sprintf( 1 === $others ? 'The Directory lists %1$d other organisation in %2$s.' : 'The Directory lists %1$d other organisations in %2$s.', $others, esc_html( $where ) );
	return array( $a . ' ' . aw_faq_a( aw_directory_url( $place->slug ), 'See the organisations in ' . $place->name ) . '.', 'nearby', false ); // about other organisations, not this one: shown, not counted
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
	$what = aw_faq_is_topic_name( $doc ) ? 'PDF of the ' . esc_html( $doc ) : esc_html( $doc ) . ' PDF';
	return array( 'Download the official ' . $what . ' ' . aw_faq_a( $url, 'here' ) . ( $iss ? ' (from ' . esc_html( aw_faq_the( $iss->name ) ) . ')' : '' ) . '.', 'pdf' );
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
		$src  = (string) get_post_meta( $id, 'aw_source_url', true );
		$who  = ucfirst( esc_html( aw_faq_the( $iss->name ) ) );
		$what = aw_faq_is_topic_name( $doc ) ? ' issued it' : ' ' . aw_faq_legal_verb( $id ) . ' the ' . esc_html( $doc ); // not "released the press release on …"
		return array( $who . $what . ' on ' . aw_date( $d ) . '.' . ( $src ? ' ' . aw_faq_a( $src, 'See the original' ) . '.' : '' ), 'date' );
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
	$a   = 'The ' . esc_html( $doc ) . ' ' . aw_faq_be( $doc, true ) . ' issued by ' . esc_html( aw_faq_the( $iss->name ) ) . ( '' !== $act ? ' under the ' . esc_html( preg_replace( '/^The\s+/u', '', $act ) ) : '' ) . '.' . ( $src ? ' ' . aw_faq_a( $src, 'See the original' ) . '.' : '' );
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
	return array( 'On the website of ' . esc_html( aw_faq_the( $iss->name ) ) . ': ' . aw_faq_a( $src, 'read the ' . $doc ) . '.', 'read' ); // aw_faq_a() escapes its text
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
/* ---------- Library documents: the Library item fields, in the SCF "Document" box (document fields the FAQ also reads) ---------- */

add_action(
	'init',
	function () {
		$auth = fn() => current_user_can( 'edit_posts' );
		register_post_meta( 'aw_legal', 'aw_short_name', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Short name, as people search it (PCA Act 1960)', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		// The Hindi version link is no longer edited or asked about (owner, 1 Nov 2026); stored values are kept.
		register_post_meta( 'aw_legal', 'aw_file_hi_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Hindi version (link to the official Hindi text)', 'sanitize_callback' => 'esc_url_raw', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_legal_act', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Issued under (the parent Act, as the document states it)', 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth ) );
		register_post_meta( 'aw_legal', 'aw_key_sections', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'description' => 'Key sections (Acts and Rules): one per line, "number | one-line plain summary | link"', 'sanitize_callback' => 'sanitize_textarea_field', 'auth_callback' => $auth ) );
	}
);

/**
 * The Library item fields (short name, parent Act, key sections), shown inside the SCF "Document" box.
 *
 * @param WP_Post $post Library item.
 */
function aw_legal_item_fields( $post ) {
	wp_nonce_field( 'aw_faq_legal', 'aw_faq_legal_nonce' );
	echo '<p><label for="aw-short-name">Short name, as people search it (e.g. PCA Act 1960, ABC Rules 2023). Empty = the focus keyphrase</label><br><input type="text" class="large-text" id="aw-short-name" name="aw_short_name" value="' . esc_attr( get_post_meta( $post->ID, 'aw_short_name', true ) ) . '"></p>';
	echo '<p><label for="aw-legal-act">Issued under (the parent Act, as the document states it)</label><br><input type="text" class="large-text" id="aw-legal-act" name="aw_legal_act" value="' . esc_attr( get_post_meta( $post->ID, 'aw_legal_act', true ) ) . '"></p>';
	echo '<p><label for="aw-key-sections">Key sections (Acts and Rules only): one per line, "number | one-line plain summary | link"</label><br><textarea class="large-text" rows="4" id="aw-key-sections" name="aw_key_sections" placeholder="11 | punishment for treating animals cruelly | https://…">' . esc_textarea( get_post_meta( $post->ID, 'aw_key_sections', true ) ) . '</textarea></p>';
}

// SCF "Document" box (acf-json/group_aw_legal_document.json): the Library item fields and the issuer, each in its message field.
add_action(
	'acf/render_field/key=field_aw_legal_msg_item',
	function () {
		global $post;
		if ( $post instanceof WP_Post && 'aw_legal' === $post->post_type ) {
			aw_legal_item_fields( $post );
		}
	}
);
add_action(
	'acf/render_field/key=field_aw_legal_msg_issued_by',
	function () {
		global $post;
		if ( $post instanceof WP_Post && 'aw_legal' === $post->post_type && function_exists( 'aw_issuer_box' ) ) {
			aw_issuer_box( $post );
		}
	}
);
add_action(
	'save_post_aw_legal',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['aw_faq_legal_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['aw_faq_legal_nonce'] ) ), 'aw_faq_legal' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( 'aw_short_name' => 'sanitize_text_field', 'aw_legal_act' => 'sanitize_text_field', 'aw_key_sections' => 'sanitize_textarea_field' ) as $k => $clean ) {
			$v = call_user_func( $clean, wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$v = str_replace( "\r\n", "\n", $v ); // browsers post CRLF; stored lines end in \n, so a save without changes changes nothing
			'' !== trim( $v ) ? update_post_meta( $post_id, $k, $v ) : delete_post_meta( $post_id, $k );
		}
	}
);

/* ---------- C. Resources research summaries ---------- */

/** Whether a post is a research summary (a Resource tagged "research"). */
function aw_faq_is_study( $id ) {
	return 'post' === get_post_type( $id ) && has_term( 'research', 'post_tag', $id );
}

/**
 * A title or journal name for a sentence: quotes trimmed, an all-capitals name in title case ("INDIAN JOURNAL OF LEGAL
 * REVIEW" → "Indian Journal of Legal Review").
 */
function aw_faq_study_name( $s ) {
	$s = trim( aw_faq_plain( $s ), " \t\n\"'“”‘’" );
	if ( '' === $s || preg_match( '/\p{Ll}/u', $s ) ) {
		return $s;
	}
	$small = array( 'a', 'an', 'and', 'as', 'at', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with' );
	$acro  = array( 'ABC', 'APCRI', 'AWBI', 'BMC', 'CCSEA', 'CPCSEA', 'ICMR', 'IJLR', 'NGO', 'NGT', 'PCA', 'PFA', 'SPCA', 'WHO', 'WWF' ); // kept in capitals
	$words = explode( ' ', $s );
	foreach ( $words as $i => $w ) {
		$bare = preg_replace( '/[^\p{L}]/u', '', $w );
		if ( in_array( $bare, $acro, true ) || ( mb_strlen( $bare ) > 1 && ! preg_match( '/[AEIOUY]/u', $bare ) ) ) { // a listed acronym, or capitals with no vowel ("BMC")
			continue;
		}
		$w           = mb_strtolower( $w );
		$words[ $i ] = $i && in_array( $w, $small, true ) ? $w : mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_substr( $w, 1 );
	}
	return preg_replace_callback( '/\(([^)]+)\)/u', fn( $m ) => '(' . mb_strtoupper( $m[1] ) . ')', implode( ' ', $words ) ); // "(IJLR)" stays an acronym
}

/**
 * The study the questions name: "the <focus keyphrase> study", plus its place ("the rabies knowledge study in Lucknow"),
 * the district when it has one. '' without a focus keyphrase.
 */
function aw_faq_study_subject( $id ) {
	$kw = trim( explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) )[0] );
	if ( '' === $kw ) {
		return '';
	}
	$ent   = json_decode( (string) get_post_meta( $id, 'aw_entities', true ), true );
	$title = aw_faq_plain( get_the_title( $id ) );
	$best  = array( -1, '' ); // the place the title names, else the first district, else the first state
	foreach ( (array) ( $ent['places'] ?? array() ) as $p ) {
		$name  = aw_faq_plain( $p['name'] ?? '' );
		$score = '' === $name ? -1 : ( false !== mb_stripos( $title, $name ) ? 2 : 0 ) + ( empty( $p['district'] ) ? 0 : 1 );
		if ( $score > $best[0] ) {
			$best = array( $score, $name );
		}
	}
	$place = $best[1];
	$kw = preg_replace( '/\s+stud(y|ies)$/iu', '', $kw );
	return 'the ' . $kw . ' study' . ( '' !== $place && false === mb_stripos( $kw, $place ) ? ' in ' . $place : '' );
}

/** A research summary's question: the template with the study's name. */
function aw_faq_study_question( $tpl, $id, $entity, $key ) {
	$q = sprintf( $tpl, aw_faq_study_subject( $id ) );
	return 'study-law' === $key && count( aw_faq_study_laws( $id ) ) > 1 ? str_replace( 'Which law does', 'Which laws does', $q ) : $q;
}

/** The study's link (its DOI or source), or '' — every research answer links to it, so none is built without it. */
function aw_faq_study_url( $id ) {
	return aw_faq_is_study( $id ) && '' !== aw_faq_study_subject( $id ) ? (string) get_post_meta( $id, 'aw_source_url', true ) : '';
}

function aw_faq_study_authors( $id ) {
	$url     = aw_faq_study_url( $id );
	$authors = get_post_meta( $id, 'aw_authors', true );
	$authors = array_values( array_filter( array_map( 'aw_faq_plain', is_array( $authors ) ? $authors : explode( ';', (string) $authors ) ), 'strlen' ) );
	if ( '' === $url || ! $authors ) {
		return null;
	}
	$who   = count( $authors ) > 3 ? implode( ', ', array_slice( $authors, 0, 3 ) ) . ' and others' : aw_faq_and( $authors );
	$title = aw_faq_study_name( get_post_meta( $id, 'aw_study_title', true ) );
	$named = ', titled “' . esc_html( $title ) . '”';
	$a     = esc_html( $who ) . ' carried out the study' . ( '' !== $title && aw_faq_word_count( $who . $named ) <= 40 ? $named : '' ) . '. ' . aw_faq_a( $url, 'Read the study' ) . '.';
	return array( $a, 'study-authors' );
}

function aw_faq_study_journal( $id ) {
	$url = aw_faq_study_url( $id );
	$jn  = aw_faq_study_name( get_post_meta( $id, 'aw_journal', true ) );
	$jn  = '' !== $jn ? $jn : aw_faq_study_name( get_post_meta( $id, 'aw_source_name', true ) );
	$d   = (string) get_post_meta( $id, 'aw_source_date', true );
	if ( '' === $url || '' === $jn ) {
		return null;
	}
	$a = 'The study was published in ' . esc_html( $jn ) . ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && $d > '1900' ? ' on ' . aw_date( $d ) : '' ) . '. ' . aw_faq_a( $url, 'See it in the journal' ) . '.';
	return array( $a, 'study-journal' );
}

function aw_faq_study_about( $id ) {
	$url = aw_faq_study_url( $id );
	$txt = aw_faq_plain( get_post_field( 'post_excerpt', $id ) );
	if ( '' === $url || '' === $txt ) {
		return null;
	}
	$out = '';
	foreach ( preg_split( '/(?<=[.!?])\s+(?=\p{Lu})/u', $txt ) as $sent ) { // whole sentences, up to 35 words
		if ( str_word_count( trim( $out . ' ' . $sent ) ) > 35 ) {
			break;
		}
		$out = trim( $out . ' ' . $sent );
	}
	if ( '' === $out ) {
		return null;
	}
	return array( esc_html( $out ) . ' ' . aw_faq_a( $url, 'Read the full study' ) . '.', 'study-about' );
}

/** The published Library documents a study names (aw_entities laws). */
function aw_faq_study_laws( $id ) {
	$ent = json_decode( (string) get_post_meta( $id, 'aw_entities', true ), true );
	$out = array();
	foreach ( (array) ( $ent['laws'] ?? array() ) as $l ) {
		$lid = (int) ( $l['id'] ?? 0 );
		if ( $lid && 'aw_legal' === get_post_type( $lid ) && 'publish' === get_post_status( $lid ) ) {
			$out[ $lid ] = aw_faq_doc_name( $lid );
		}
	}
	return $out;
}

function aw_faq_study_law( $id ) {
	$laws = aw_faq_study_laws( $id );
	if ( '' === aw_faq_study_url( $id ) || ! $laws ) {
		return null;
	}
	$names = array_map( fn( $n ) => 'the ' . esc_html( $n ), array_values( $laws ) );
	$a     = 'It discusses ' . aw_faq_and( $names ) . '. ' . aw_faq_a( (string) get_permalink( array_key_first( $laws ) ), 'Read the ' . aw_faq_doc_short( array_key_first( $laws ) ) . ' in the Library' ) . '.';
	return array( $a, 'study-law' );
}

/* ---------- D. Content tags ---------- */

/**
 * The published items with a tag, by type, newest first: news, Library, Resources, listings. Null for a Directory
 * listing type, for a tag with written questions of its own (it keeps only those) and for a tag with fewer than 4 items.
 *
 * @param int $term_id Tag ID.
 * @return array|null type => IDs
 */
function aw_faq_tag_items( $term_id ) {
	static $memo = array();
	if ( array_key_exists( $term_id, $memo ) ) {
		return $memo[ $term_id ];
	}
	$t     = get_term( $term_id, 'post_tag' );
	$types = function_exists( 'aw_listing_type_slugs' ) ? (array) aw_listing_type_slugs() : array();
	$own   = $t instanceof WP_Term ? array_filter( aw_faq_items( 'term', $term_id ), fn( $x ) => '' === ( $x['auto'] ?? '' ) && 'approved' === ( $x['status'] ?? '' ) ) : array();
	if ( ! $t instanceof WP_Term || in_array( $t->slug, $types, true ) || count( $own ) >= AW_FAQ_MIN ) { // a written FAQ of its own: kept as it is
		return $memo[ $term_id ] = null;
	}
	$out = get_transient( 'aw_faq_tag_' . $term_id ); // the IDs, for an hour: a tag page would otherwise query every item on each view
	if ( ! is_array( $out ) ) {
		$out = array();
		foreach ( array( 'aw_news', 'aw_legal', 'post', 'aw_listing' ) as $pt ) {
			$ids = get_posts( array( 'post_type' => $pt, 'post_status' => 'publish', 'tag_id' => $term_id, 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => true ) );
			if ( $ids && 'aw_listing' !== $pt ) {
				update_meta_cache( 'post', $ids );
				usort( $ids, fn( $a, $b ) => aw_item_date_ts( $b ) <=> aw_item_date_ts( $a ) );
			}
			if ( $ids ) {
				$out[ $pt ] = array_map( 'intval', $ids );
			}
		}
		set_transient( 'aw_faq_tag_' . $term_id, $out, HOUR_IN_SECONDS );
	}
	foreach ( $out as $pt => $ids ) { // posts, meta and categories in one query each, not one per item
		update_meta_cache( 'post', $ids );
		if ( 'aw_listing' !== $pt ) {
			_prime_post_caches( $ids, false, false ); // titles, content and dates for the answers
			update_object_term_cache( $ids, $pt );
		}
	}
	return $memo[ $term_id ] = array_sum( array_map( 'count', $out ) ) >= 4 ? $out : null;
}

/** A tag's name in a sentence: each capitalised word in lower case, acronyms kept ("Treatment and Vet Care" → "treatment and vet care", "Sterilisation (ABC)" → "sterilisation (ABC)"). */
function aw_faq_tag_lc( $name ) {
	return preg_replace_callback( '/\b\p{Lu}\p{Ll}+\b/u', fn( $m ) => mb_strtolower( $m[0] ), aw_faq_plain( $name ) );
}

/** The tag's name in a question ("Official release" → "official release"). */
function aw_faq_tag_question( $tpl, $id, $entity, $key ) {
	$name = aw_faq_tag_lc( $entity );
	if ( in_array( $key, array( 'tag-issuers', 'tag-laws', 'tag-in-force', 'tag-campaign' ), true ) ) { // "Who issues the circulars …", "Which rules are in force?"; tag-campaign is kept for the deferred campaign question (aw_faq_tag_campaign)
		$name = aw_faq_tag_plural( $name, $key, aw_faq_tag_slug( $id ) ) ?? $name; // null: the answer is null too, so the question is not shown
	}
	return sprintf( $tpl, $name );
}

/**
 * A tag's name in the plural ("rule" → "rules", "policy" → "policies", "research" → "research summaries"; "news" stays).
 * For a question that only fits some tags, null for the others: "Which … are in force?" for laws that can be in force
 * (Acts, Rules, Notifications), "Which laws do the … explain?" for Resource types (guides, research summaries).
 *
 * @param string $name Tag name in a sentence.
 * @param string $key  Registry key ('' for any tag).
 * @param string $slug Tag slug, with $key.
 * @return string|null
 */
function aw_faq_tag_plural( $name, $key = '', $slug = '' ) {
	$only = array(
		'tag-in-force' => array( 'act', 'rule', 'notification' ),
		'tag-laws'     => array( 'guide', 'research' ),
	);
	if ( isset( $only[ $key ] ) && ! in_array( $slug, $only[ $key ], true ) ) {
		return null;
	}
	if ( 'research' === $name ) {
		return 'research summaries';
	}
	return preg_match( '/[^aeiou]y$/u', $name ) ? mb_substr( $name, 0, -1 ) . 'ies' : ( preg_match( '/s$/u', $name ) ? $name : $name . 's' );
}

/** A tag's slug ('' for none). */
function aw_faq_tag_slug( $term_id ) {
	$t = get_term( $term_id, 'post_tag' );
	return $t instanceof WP_Term ? $t->slug : '';
}

/**
 * "… the A, the B and the C. <link>" with as many names as fit in 50 words (at most 3, at least 1), dropped from the end.
 *
 * @param callable $build fn( string[] $names ) => answer html.
 * @param string[] $names Names, most important first (escaped).
 * @return string Answer html.
 */
function aw_faq_fit_names( callable $build, array $names ) {
	$names = array_slice( array_values( $names ), 0, 3 );
	$a     = $build( $names );
	while ( count( $names ) > 1 && aw_faq_word_count( $a ) > 50 ) {
		array_pop( $names );
		$a = $build( $names );
	}
	return $a;
}

/** An issuer's name after "from": "the Supreme Court", "the Press Information Bureau". */
function aw_faq_the( $name ) {
	$name = aw_faq_plain( $name ); // term names are stored with entities ("Bar &amp; Bench"); callers escape
	return preg_match( '/^the\s/iu', $name ) ? $name : 'the ' . $name;
}

/** "A (12), B (8) and C (5)": the three largest counts. */
function aw_faq_tag_top( array $counts ) {
	arsort( $counts );
	$out = array();
	foreach ( array_slice( $counts, 0, 3, true ) as $name => $n ) {
		$out[] = esc_html( aw_faq_plain( (string) $name ) ) . ' (' . number_format_i18n( $n ) . ')'; // term names are stored with entities
	}
	return aw_faq_and( $out );
}

/** An item's title, quoted, with its date: "“Title” (4 Oct 2026)". */
function aw_faq_tag_item_text( $id ) {
	$ts = aw_item_date_ts( $id );
	return '“' . esc_html( aw_faq_plain( get_the_title( $id ) ) ) . '”' . ( $ts ? ' (' . aw_date( $ts ) . ')' : '' );
}

function aw_faq_tag_news( $term_id ) {
	$ids = aw_faq_tag_items( $term_id )['aw_news'] ?? array();
	if ( ! $ids ) {
		return null;
	}
	$src = aw_faq_plain( get_post_meta( $ids[0], 'aw_source_name', true ) );
	$a   = 'The latest is ' . aw_faq_tag_item_text( $ids[0] ) . ( '' !== $src ? ', from ' . esc_html( $src ) : '' ) . '. ' . aw_faq_a( (string) get_permalink( $ids[0] ), 'Read it' ) . '.';
	return array( $a, 'tag-news' );
}

function aw_faq_tag_library( $term_id ) {
	$ids = aw_faq_tag_items( $term_id )['aw_legal'] ?? array();
	if ( ! $ids ) {
		return null;
	}
	$iss = function_exists( 'aw_item_issuer' ) && ! in_array( aw_faq_legal_kind( $ids[0] ), array( 'act', 'rule', 'template' ), true ) ? aw_item_issuer( $ids[0] ) : null;
	$a   = 'The latest is ' . aw_faq_tag_item_text( $ids[0] ) . ( $iss ? ', from ' . esc_html( aw_faq_the( $iss->name ) ) : '' ) . '. ' . aw_faq_a( (string) get_permalink( $ids[0] ), 'Read it' ) . '.';
	return array( $a, 'tag-library' );
}

function aw_faq_tag_resource( $term_id ) {
	$ids = aw_faq_tag_items( $term_id )['post'] ?? array();
	if ( ! $ids ) {
		return null;
	}
	$a = 'The newest is ' . aw_faq_tag_item_text( $ids[0] ) . '. ' . aw_faq_a( (string) get_permalink( $ids[0] ), 'Read it' ) . '.';
	return array( $a, 'tag-resource' );
}

/** Listing counts by state (or district) name, and each name's place, for the listings with the tag. */
function aw_faq_tag_places( $term_id, $district ) {
	$counts = array();
	$place  = array();
	foreach ( aw_faq_tag_items( $term_id )['aw_listing'] ?? array() as $id ) {
		$p = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null;
		if ( ! $p ) {
			continue;
		}
		if ( $district && 'district' !== $p->kind ) {
			continue;
		}
		$s = $district ? $p : ( 'district' === $p->kind ? aw_place_state( $p->state ) : $p );
		if ( ! $s ) {
			continue;
		}
		$counts[ $s->slug ] = ( $counts[ $s->slug ] ?? 0 ) + 1;
		$place[ $s->slug ]  = $s;
	}
	$names = array_count_values( array_map( fn( $x ) => $x->name, $place ) );
	$named = array();
	foreach ( $counts as $slug => $n ) { // by name; a district that shares its name with another gets its state ("Aurangabad, Bihar")
		$x       = $place[ $slug ];
		$st      = $district && $names[ $x->name ] > 1 ? aw_place_state( $x->state ) : null;
		$label   = aw_faq_plain( $x->name . ( $st ? ', ' . $st->name : '' ) ); // stored with entities; aw_faq_a() escapes
		$named[ $label ] = $n;
		$place[ $label ] = $x;
	}
	return array( $named, $place );
}

/**
 * The Directory link for a tag's listings: the Directory filtered by the tag when it is a service (?tag=), else the page
 * of the state with the most of them. '' when there is neither.
 *
 * @param int    $term_id Tag ID.
 * @param string $name    Tag name.
 * @param array  $counts  aw_faq_tag_places() counts by state, sorted largest first.
 * @param array  $place   aw_faq_tag_places() places by name.
 * @return string Link HTML.
 */
function aw_faq_tag_directory_link( $term_id, $name, array $counts, array $place ) {
	$t = get_term( $term_id, 'post_tag' );
	if ( ! $t instanceof WP_Term ) {
		return '';
	}
	if ( function_exists( 'aw_service_slugs' ) && in_array( $t->slug, (array) aw_service_slugs(), true ) ) {
		return aw_faq_a( add_query_arg( 'tag', $t->slug, home_url( 'directory/' ) ), 'Find ' . aw_faq_tag_lc( $name ) . ' in the Directory' );
	}
	$top = (string) array_key_first( $counts );
	return '' !== $top && function_exists( 'aw_directory_url' ) ? aw_faq_a( aw_directory_url( $place[ $top ]->slug ), 'Browse the Directory in ' . $top ) : '';
}

function aw_faq_tag_directory( $term_id, $name ) {
	list( $counts, $place ) = aw_faq_tag_places( $term_id, false );
	if ( array_sum( $counts ) < 3 ) {
		return null;
	}
	arsort( $counts );
	$link = aw_faq_tag_directory_link( $term_id, $name, $counts, $place );
	if ( '' === $link ) {
		return null;
	}
	$n = count( aw_faq_tag_items( $term_id )['aw_listing'] );
	$a = 'The Directory lists ' . number_format_i18n( $n ) . ' organisations offering ' . esc_html( aw_faq_tag_lc( $name ) ) . ', most of them in ' . aw_faq_tag_top( $counts ) . '. ' . $link . '.';
	return array( $a, 'tag-directory' );
}

/** Which animals the tag's listings help (aw_animals, comma separated; "others" left out). */
function aw_faq_tag_animals( $term_id, $name ) {
	$counts = array();
	$n      = 0;
	foreach ( aw_faq_tag_items( $term_id )['aw_listing'] ?? array() as $id ) {
		$an = array_unique( array_filter( array_map( fn( $x ) => mb_strtolower( trim( $x ) ), explode( ',', aw_lf( $id, 'aw_animals' ) ) ), fn( $x ) => '' !== $x && ! in_array( $x, array( 'other', 'others' ), true ) ) );
		if ( ! $an ) {
			continue;
		}
		++$n;
		foreach ( $an as $x ) {
			$counts[ $x ] = ( $counts[ $x ] ?? 0 ) + 1;
		}
	}
	if ( $n < 3 ) {
		return null;
	}
	list( $states, $place ) = aw_faq_tag_places( $term_id, false );
	arsort( $states );
	$link = aw_faq_tag_directory_link( $term_id, $name, $states, $place );
	if ( '' === $link ) {
		return null;
	}
	$a = 'Of ' . number_format_i18n( $n ) . ' listings that name the animals they help, most help ' . aw_faq_tag_top( $counts ) . '. ' . $link . '.';
	return array( $a, 'tag-animals' );
}

/**
 * The Library documents an item links to: links in its text to this site's /library/ addresses that resolve to a
 * document, and its aw_entities laws with an ID. Whether each is still published is checked by the caller.
 *
 * @param int $id Item ID.
 * @return int[] Document IDs.
 */
function aw_faq_item_laws( $id ) {
	static $memo = array(); // normalised /library/ path => document ID (0 for none), for the request
	$out  = array();
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$base = rtrim( $home, '/' ) . '/library/';
	$host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', (string) get_post_field( 'post_content', $id ), $m ) ) {
		foreach ( array_unique( $m[1] ) as $url ) {
			$u    = wp_parse_url( html_entity_decode( $url ) );
			$path = is_array( $u ) ? strtolower( (string) ( $u['path'] ?? '' ) ) : '';
			if ( '' === $path || ( isset( $u['host'] ) && preg_replace( '/^www\./', '', strtolower( $u['host'] ) ) !== $host ) || 0 !== strpos( $path, $base ) || strlen( rtrim( $path, '/' ) ) < strlen( $base ) ) {
				continue; // only this site's Library documents (absolute, with or without www, or root-relative), not the Library page itself
			}
			$key = rtrim( $path, '/' ) . '/'; // one key for "…/x", "…/x/", "https://www.…/x/#y" and "/library/x?z"
			if ( ! isset( $memo[ $key ] ) ) {
				$memo[ $key ] = (int) url_to_postid( home_url( substr( $key, strlen( rtrim( $home, '/' ) ) ) ) );
			}
			$out[] = $memo[ $key ];
		}
	}
	$ent = json_decode( (string) get_post_meta( $id, 'aw_entities', true ), true );
	foreach ( (array) ( $ent['laws'] ?? array() ) as $l ) {
		$out[] = (int) ( $l['id'] ?? 0 );
	}
	return array_values( array_filter( array_unique( array_map( 'intval', $out ) ), fn( $d ) => $d && $d !== (int) $id ) );
}

/**
 * Which Library documents the tag's Resources and news items link to most. Only for a Resource type tag (guide,
 * research). The item => documents map is kept for an hour beside the tag's item IDs (transient aw_faq_tag_laws_<id>).
 */
function aw_faq_tag_laws( $term_id, $name ) {
	$plural = aw_faq_tag_plural( aw_faq_tag_lc( $name ), 'tag-laws', aw_faq_tag_slug( $term_id ) );
	$items  = null !== $plural ? aw_faq_tag_items( $term_id ) : null;
	if ( ! $items || ! empty( $items['aw_listing'] ) ) {
		return null; // "the rescue works": a service's name is not a kind of item
	}
	$ids = array_merge( $items['post'] ?? array(), $items['aw_news'] ?? array() );
	$map  = get_transient( 'aw_faq_tag_laws_' . $term_id );
	$map  = is_array( $map ) ? $map : array();
	$miss = array_diff( $ids, array_keys( $map ) ); // all of them on a first view; items tagged since, after that
	foreach ( $miss as $id ) {
		$map[ $id ] = aw_faq_item_laws( $id );
	}
	if ( $miss ) {
		set_transient( 'aw_faq_tag_laws_' . $term_id, $map, HOUR_IN_SECONDS );
	}
	$all = array_values( array_unique( array_merge( array(), ...array_values( array_map( 'array_values', $map ) ) ) ) );
	if ( $all ) {
		_prime_post_caches( $all, true, true ); // the documents' status, meta and types in a few queries
	}
	$live   = array_flip( array_filter( $all, fn( $d ) => 'aw_legal' === get_post_type( $d ) && 'publish' === get_post_status( $d ) ) );
	$counts = array();
	$first  = array(); // short name => document ID
	$n      = 0;
	$with   = 0;
	foreach ( $ids as $id ) {
		++$n;
		$docs  = array_filter( $map[ $id ] ?? array(), fn( $d ) => isset( $live[ $d ] ) );
		$with += $docs ? 1 : 0;
		foreach ( $docs as $d ) {
			$s            = aw_faq_doc_short( $d );
			$counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
			$first[ $s ]  = $first[ $s ] ?? $d;
		}
	}
	if ( $with < 3 ) {
		return null;
	}
	arsort( $counts );
	$top   = (string) array_key_first( $counts );
	$link  = aw_faq_a( (string) get_permalink( $first[ $top ] ), 'Read the ' . $top . ' in the Library' );
	$lead  = number_format_i18n( $with ) . ' of the ' . number_format_i18n( $n ) . ' ' . esc_html( $plural ) . ' link to Library documents, most often the ';
	$names = array();
	foreach ( array_slice( $counts, 0, 3, true ) as $s => $c ) {
		$names[] = esc_html( (string) $s ) . ' (' . number_format_i18n( $c ) . ')';
	}
	$a = aw_faq_fit_names( fn( $x ) => $lead . aw_faq_and( $x ) . '. ' . $link . '.', $names );
	return array( $a, 'tag-laws' );
}

/** Which of the tag's Library documents are in force (aw_legal_force, as aw_faq_legal_force reads it). Only for the act, rule and notification tags. */
function aw_faq_tag_in_force( $term_id, $name = '' ) {
	if ( null === aw_faq_tag_plural( aw_faq_tag_lc( $name ), 'tag-in-force', aw_faq_tag_slug( $term_id ) ) ) {
		return null;
	}
	$known = 0;
	$in    = array();
	foreach ( aw_faq_tag_items( $term_id )['aw_legal'] ?? array() as $id ) { // newest first
		$f = (string) get_post_meta( $id, 'aw_legal_force', true );
		if ( in_array( $f, array( 'InForce', 'PartiallyInForce', 'NotInForce' ), true ) ) {
			++$known;
			if ( 'InForce' === $f ) {
				$in[] = $id;
			}
		}
	}
	if ( $known < 2 || ! $in ) {
		return null;
	}
	$link = ' ' . aw_faq_a( home_url( 'library/' ), 'Browse the Library' ) . '.';
	$of   = number_format_i18n( count( $in ) ) . ' of the ' . number_format_i18n( $known );
	if ( 1 === count( $in ) ) {
		return array( $of . ' is in force: the ' . esc_html( aw_faq_doc_short( $in[0] ) ) . '.' . $link, 'tag-in-force' );
	}
	$names = array_map( fn( $d ) => 'the ' . esc_html( aw_faq_doc_short( $d ) ), $in );
	$a     = aw_faq_fit_names( fn( $x ) => $of . ' are in force' . ( count( $x ) < count( $in ) ? ', among them ' : ': ' ) . aw_faq_and( $x ) . '.' . $link, $names ); // "among them" only when not all are named
	return array( $a, 'tag-in-force' );
}

/** The tag's Library documents, named after the newest (tag-library names it). Only for the template tag (owner, 1 Nov 2026). */
function aw_faq_tag_list( $term_id ) {
	if ( 'template' !== aw_faq_tag_slug( $term_id ) ) {
		return null;
	}
	$ids = aw_faq_tag_items( $term_id )['aw_legal'] ?? array(); // newest first
	if ( count( $ids ) < 3 ) {
		return null;
	}
	$names = array_map( fn( $d ) => '“' . esc_html( aw_faq_plain( get_the_title( $d ) ) ) . '”', array_slice( $ids, 1 ) );
	$link  = ' ' . aw_faq_a( home_url( 'library/' ), 'Browse the Library' ) . '.';
	$a     = aw_faq_fit_names( fn( $x ) => number_format_i18n( count( $ids ) ) . ', among them ' . aw_faq_and( $x ) . '.' . $link, $names );
	return array( $a, 'tag-list' );
}

// Not in the registry until the campaigns it names are open again (owner, 1 Nov 2026).
/** The campaign most of the tag's Library documents are linked to (aw_campaign_link; its term meta aw_campaign_page). */
function aw_faq_tag_campaign( $term_id, $name ) {
	$ids    = aw_faq_tag_items( $term_id )['aw_legal'] ?? array();
	$counts = array();
	foreach ( $ids as $id ) {
		foreach ( function_exists( 'aw_campaign_links' ) ? aw_campaign_links( $id ) : array() as $t ) {
			$counts[ $t->term_id ] = ( $counts[ $t->term_id ] ?? 0 ) + 1;
		}
	}
	if ( ! $counts ) {
		return null;
	}
	arsort( $counts );
	$tid  = (int) array_key_first( $counts );
	$page = (int) get_term_meta( $tid, 'aw_campaign_page', true );
	if ( $counts[ $tid ] < 2 || ! $page || 'publish' !== get_post_status( $page ) ) {
		return null;
	}
	$a = number_format_i18n( $counts[ $tid ] ) . ' of the ' . number_format_i18n( count( $ids ) ) . ' ' . esc_html( aw_faq_tag_plural( aw_faq_tag_lc( $name ) ) ) . ' are for the ' . esc_html( aw_faq_plain( get_the_title( $page ) ) ) . ' campaign. ' . aw_faq_a( (string) get_permalink( $page ), 'See the campaign' ) . '.';
	return array( $a, 'tag-campaign' );
}

function aw_faq_tag_districts( $term_id ) {
	list( $counts, $place ) = aw_faq_tag_places( $term_id, true );
	if ( array_sum( $counts ) < 3 || count( $counts ) < 2 || ! function_exists( 'aw_directory_url' ) ) {
		return null;
	}
	arsort( $counts );
	$top = (string) array_key_first( $counts );
	$a   = aw_faq_tag_top( $counts ) . ', of ' . number_format_i18n( array_sum( $counts ) ) . ' listings with a district. ' . aw_faq_a( aw_directory_url( $place[ $top ]->slug ), 'See ' . $top ) . '.';
	return array( $a, 'tag-districts' );
}

function aw_faq_tag_issuers( $term_id ) {
	$counts = array();
	foreach ( aw_faq_tag_items( $term_id )['aw_legal'] ?? array() as $id ) {
		$iss = function_exists( 'aw_item_issuer' ) && ! in_array( aw_faq_legal_kind( $id ), array( 'act', 'rule', 'template' ), true ) ? aw_item_issuer( $id ) : null; // an Act's or Rule's stored issuer is whose copy it is
		if ( $iss ) {
			$counts[ aw_faq_the( $iss->name ) ] = ( $counts[ aw_faq_the( $iss->name ) ] ?? 0 ) + 1;
		}
	}
	if ( array_sum( $counts ) < 2 ) {
		return null;
	}
	$n = number_format_i18n( array_sum( $counts ) );
	$a = ( 1 === count( $counts ) ? 'All ' . $n . ' come from ' . esc_html( (string) array_key_first( $counts ) ) : 'Most come from ' . aw_faq_tag_top( $counts ) . ', of ' . $n . ' with a known issuer' ) . '. ' . aw_faq_a( home_url( 'library/' ), 'Browse the Library' ) . '.';
	return array( $a, 'tag-issuers' );
}

function aw_faq_tag_topics( $term_id ) {
	$items  = aw_faq_tag_items( $term_id );
	$counts = array();
	$terms  = array();
	$n      = 0;
	foreach ( array( 'aw_news', 'aw_legal', 'post' ) as $pt ) {
		foreach ( $items[ $pt ] ?? array() as $id ) {
			++$n;
			$cats = get_the_terms( $id, 'category' );
			foreach ( is_array( $cats ) ? $cats : array() as $c ) {
				if ( 'uncategorized' !== $c->slug ) {
					$cn            = aw_faq_plain( $c->name ); // stored with entities; aw_faq_a() escapes
					$counts[ $cn ] = ( $counts[ $cn ] ?? 0 ) + 1;
					$terms[ $cn ]  = $c;
				}
			}
		}
	}
	if ( array_sum( $counts ) < 3 ) {
		return null;
	}
	arsort( $counts );
	$top = (string) array_key_first( $counts );
	$url = function_exists( 'kits_term_url' ) ? kits_term_url( $terms[ $top ] ) : get_term_link( $terms[ $top ] );
	$a   = aw_faq_tag_top( $counts ) . ', across ' . number_format_i18n( $n ) . ' items. ' . aw_faq_a( (string) $url, 'See ' . $top ) . '.';
	return array( $a, 'tag-topics' );
}

function aw_faq_tag_journals( $term_id ) {
	$counts = array();
	foreach ( aw_faq_tag_items( $term_id )['post'] ?? array() as $id ) {
		$jn = aw_faq_study_name( get_post_meta( $id, 'aw_journal', true ) );
		if ( '' !== $jn ) {
			$counts[ $jn ] = ( $counts[ $jn ] ?? 0 ) + 1;
		}
	}
	if ( array_sum( $counts ) < 3 ) {
		return null;
	}
	$a = aw_faq_tag_top( $counts ) . ', of ' . number_format_i18n( array_sum( $counts ) ) . ' studies with a journal. ' . aw_faq_a( home_url( 'resources/' ), 'Browse Resources' ) . '.';
	return array( $a, 'tag-journals' );
}
