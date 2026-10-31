<?php
/**
 * Animal Writes' FAQ data for AW SEO Plus (owner, 31 Oct 2026: the plugin's FAQ is a generic feature; the site's own
 * rules live here, through its filters). Nothing here runs unless AW SEO Plus calls its filters, except the Library
 * "FAQ fields" box and its meta, which are Library fields.
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
			array( 'key' => 'phone', 'priority' => 1, 'q' => 'What is the contact number of %s?', 'needs' => array( 'aw_phones' ), 'pattern' => '<org> contact number / helpline number', 'answer' => 'aw_faq_listing_phone' ),
			array( 'key' => 'location', 'priority' => 2, 'q' => 'Where is %s located?', 'needs' => array( 'aw_address', 'district' ), 'pattern' => '<org> <city> / address', 'answer' => 'aw_faq_listing_location' ),
			array( 'key' => 'services', 'priority' => 3, 'q' => 'What services does %s offer?', 'needs' => array( 'aw_services', 'type' ), 'pattern' => '<org> services / adoption / rescue', 'answer' => 'aw_faq_listing_services' ),
			array( 'key' => 'awbi', 'priority' => 4, 'q' => 'Is %s recognised by the Animal Welfare Board of India?', 'needs' => array( 'aw_awbi_code' ), 'pattern' => '<org> awbi', 'answer' => 'aw_faq_listing_awbi' ),
			array( 'key' => 'volunteer', 'priority' => 5, 'q' => 'How can I volunteer with %s?', 'needs' => array( 'aw_volunteer_url' ), 'pattern' => '<org> volunteer', 'answer' => 'aw_faq_listing_volunteer' ),
			array( 'key' => 'hours', 'priority' => 7, 'q' => 'What are the opening hours of %s?', 'needs' => array( 'aw_opening_hours' ), 'pattern' => '<org> timings', 'answer' => 'aw_faq_listing_hours' ),
			array( 'key' => 'adoption', 'priority' => 8, 'q' => 'Does %s offer adoption?', 'needs' => array( 'aw_adopt_url', 'Adoption Service' ), 'pattern' => '<org> adoption', 'answer' => 'aw_faq_listing_adoption' ),
			array( 'key' => 'registration', 'priority' => 9, 'q' => 'What is the registration number or NGO Darpan ID of %s?', 'needs' => array( 'aw_registration', 'aw_darpan_id' ), 'pattern' => '<org> registration', 'answer' => 'aw_faq_listing_registration' ),
			array( 'key' => 'animals', 'priority' => 10, 'q' => 'Which animals does %s help?', 'needs' => array( 'aw_animals' ), 'pattern' => '—', 'answer' => 'aw_faq_listing_animals' ),
		);
		$r['aw_legal']   = array(
			array( 'key' => 'pdf', 'priority' => 1, 'q' => 'Where can I download the %s PDF?', 'needs' => array( 'aw_file_id (PDF)', 'aw_source_url (.pdf)' ), 'pattern' => '<doc> pdf / pdf download', 'answer' => 'aw_faq_legal_pdf', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'force', 'priority' => 2, 'q' => 'Is the %s in force?', 'needs' => array( 'aw_legal_force' ), 'pattern' => '<doc> latest / today', 'answer' => 'aw_faq_legal_force', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'date', 'priority' => 3, 'q' => 'When was the %s %s?', 'needs' => array( 'aw_source_date', 'aw_legal_number (not needed for official releases and circulars)' ), 'pattern' => '<doc> gazette / notification', 'answer' => 'aw_faq_legal_date', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'issuer', 'priority' => 4, 'q' => 'Who issued the %s?', 'needs' => array( 'aw_issuer (not for Acts and Rules: the stored issuer is whose copy it is)' ), 'pattern' => '—', 'answer' => 'aw_faq_legal_issuer', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'hindi', 'priority' => 5, 'q' => 'Is the %s available in Hindi?', 'needs' => array( 'aw_file_hi_url' ), 'pattern' => '<doc> in hindi', 'answer' => 'aw_faq_legal_hindi', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'read', 'priority' => 7, 'q' => 'Where can I read the %s?', 'needs' => array( 'aw_source_url (not a PDF)', 'aw_issuer', 'official release' ), 'pattern' => '<doc> press release', 'answer' => 'aw_faq_legal_read', 'question' => 'aw_faq_legal_question' ),
			array( 'key' => 'sections', 'priority' => 6, 'q' => 'What does section %2$s of the %1$s say?', 'needs' => array( 'aw_key_sections (Acts and Rules)' ), 'pattern' => 'pca act section 11 / punishment', 'answer' => 'aw_faq_legal_sections' ),
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
	$q = sprintf( $tpl, $doc, 'date' === $key ? aw_faq_legal_verb( $id ) : '' );
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
			$ctx['full']   = aw_faq_doc_name( $obj->ID ); // the full official title, once, in the first answer
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
