<?php
/**
 * Phase 10 data: Resources (guides, how-tos, research) and Library (official documents, templates); pages archived
 * with redirects; Contact text; Media & PR page and list; opportunity review dates.
 * Run once after deploying the Phase 10 code: wp eval-file tools/restructure/phase10.php. Safe to run again.
 * Nothing is deleted: archived pages become drafts, moved items keep their slug.
 */

$log  = array();
$move = function ( $id, $to, $old_path ) use ( &$log ) {
	$p = get_post( $id );
	if ( ! $p ) {
		$log[] = "$id: missing";
		return false;
	}
	if ( $p->post_type !== $to ) {
		$clash = get_posts( array( 'post_type' => $to, 'name' => $p->post_name, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1, 'exclude' => array( $id ) ) );
		if ( $clash ) {
			$log[] = "$id: SLUG COLLISION with #{$clash[0]} in $to ({$p->post_name}) — not moved";
			return false;
		}
		set_post_type( $id, $to );
		clean_post_cache( $id );
	}
	if ( $old_path && trim( $old_path, '/' ) !== trim( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), '/' ) ) {
		aw_redirect_add( home_url( $old_path ), $id );
	}
	$log[] = "$id: $old_path -> " . wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
	return true;
};

// Templates -> Library (kind Template).
foreach ( array( 7606, 7605, 9, 10, 11, 12 ) as $id ) {
	$old = '/resources/' . get_post_field( 'post_name', $id ) . '/';
	if ( $move( $id, 'aw_legal', $old ) ) {
		wp_set_object_terms( $id, 'template', 'aw_legal_kind', false );
	}
}
// How-tos -> Resources (same address as before).
foreach ( array( 13, 14 ) as $id ) {
	if ( $move( $id, 'aw_guide', '/resources/' . get_post_field( 'post_name', $id ) . '/' ) ) {
		wp_set_object_terms( $id, 'how-to', 'aw_resource_type', false );
	}
}
// Research -> Resources (kind Research).
if ( $move( 125, 'aw_guide', '/research/' . get_post_field( 'post_name', 125 ) . '/' ) ) {
	wp_set_object_terms( 125, 'research', 'aw_resource_type', false );
}
// Guides: kind Guide (FAQs: kind FAQ).
foreach ( get_posts( array( 'post_type' => 'aw_guide', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	if ( ! wp_get_object_terms( $id, 'aw_resource_type', array( 'fields' => 'ids' ) ) ) {
		wp_set_object_terms( $id, false !== stripos( get_the_title( $id ), 'FAQ' ) ? 'faq' : 'guide', 'aw_resource_type', false );
	}
}

// Pages archived (draft) with one-hop redirects.
$archive = array( 'helplines' => '/', 'emergency' => '/', 'get-involved' => '/#volunteer', 'for-organisations' => '/contact/', 'editorial' => '/editorial-policy/' );
foreach ( $archive as $slug => $to ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page && 'publish' === $page->post_status ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'draft' ) );
		update_post_meta( $page->ID, 'aw_archived', 'Phase 10, 7 Oct 2026' );
	}
	aw_redirect_add( home_url( "/$slug/" ), $to );
	$log[] = "/$slug/ archived -> $to";
}
aw_redirect_add( home_url( '/legal-watch/acts-and-rules/' ), '/library/?kind=act' );
aw_redirect_add( home_url( '/library/acts-and-rules/' ), '/library/?kind=act' );

// Contact: the owner's text, then the form.
$contact = get_page_by_path( 'contact' );
if ( $contact && false === strpos( $contact->post_content, 'For organisations:' ) ) {
	$p    = fn( $html ) => "<!-- wp:paragraph -->\n<p>$html</p>\n<!-- /wp:paragraph -->\n";
	$body = $p( 'Write to us at <a href="mailto:contact@animalwrites.in">contact@animalwrites.in</a> or use the form below.' )
		. $p( '<strong>For organisations:</strong>' )
		. "<!-- wp:list -->\n<ul class=\"wp-block-list\">"
		. "<!-- wp:list-item -->\n<li><strong>Add or update a listing</strong> — send your organisation's name, district, services and contact details.</li>\n<!-- /wp:list-item -->\n"
		. "<!-- wp:list-item -->\n<li><strong>Get featured</strong> — ask about featured placement in the Directory.</li>\n<!-- /wp:list-item -->\n"
		. "<!-- wp:list-item -->\n<li><strong>Post an opportunity</strong> — send the role, location, deadline and how to apply.</li>\n<!-- /wp:list-item -->\n"
		. "<!-- wp:list-item -->\n<li><strong>Partner with us</strong> — CSR, sponsorship and advertising enquiries.</li>\n<!-- /wp:list-item -->\n"
		. "</ul>\n<!-- /wp:list -->\n"
		. $p( '<strong>Corrections:</strong> if something we published is wrong, tell us what and where.' )
		. "<!-- wp:shortcode -->\n[aw_contact_form]\n<!-- /wp:shortcode -->\n";
	wp_update_post( array( 'ID' => $contact->ID, 'post_content' => $body ) );
	$log[] = '/contact/: owner text + form (earlier version kept in revisions)';
}

// Media & PR: page and the 1.0 coverage list.
if ( ! get_option( 'aw_press' ) ) {
	update_option(
		'aw_press',
		array(
			array( 'outlet' => 'INDIA TV NEWS', 'date' => '2025-11-29', 'headline' => 'Death sentence to animals: Thousands write to Supreme Court', 'url' => 'https://www.indiatvnews.com/news/india/death-sentence-to-animals-thousands-write-to-supreme-court-over-november-7-order-on-stray-dogs-2025-11-29-1019576', 'logo' => 0 ),
			array( 'outlet' => 'OUTLOOK INDIA', 'date' => '', 'headline' => 'Nationwide Letter Campaign Urges SC To Strengthen Animal Rights', 'url' => 'https://www.outlookindia.com/national/nationwide-letter-campaign-urges-supreme-court-to-strengthen-animal-rights', 'logo' => 0 ),
			array( 'outlet' => 'DECCAN HERALD', 'date' => '', 'headline' => 'Nationwide campaign urges citizens to write to SC for animal welfare', 'url' => 'https://www.deccanherald.com/india/nationwide-campaign-urges-citizens-to-write-to-sc-for-animal-welfare-over-10000-letters-sent-3814221', 'logo' => 0 ),
			array( 'outlet' => 'TIMES OF INDIA', 'date' => '', 'headline' => 'Animal activists to participate in letter campaign', 'url' => 'https://timesofindia.indiatimes.com/city/mangaluru/animal-activists-to-participate-in-letter-campaign/articleshow/125618323.cms', 'logo' => 0 ),
			array( 'outlet' => 'THE NEW INDIAN EXPRESS', 'date' => '2025-11-30', 'headline' => 'Animal lovers spam Supreme Court with letter petitions', 'url' => 'https://www.newindianexpress.com/cities/delhi/2025/Nov/30/animal-lovers-spam-supreme-court-with-letter-petitions-urge-rollback-of-stray-dog-removal-order', 'logo' => 0 ),
			array( 'outlet' => 'INDIA TV', 'date' => '2025-11-29', 'headline' => 'Over 1,00,000 people filed letter petition in Supreme Court', 'url' => 'https://www.indiatv.in/india/national/over-100000-people-filed-letter-petition-in-supreme-court-appealing-not-to-remove-stray-dogs-from-streets-2025-11-29-1179585', 'logo' => 0 ),
			array( 'outlet' => 'DT NEXT', 'date' => '', 'headline' => 'Over 1.5L from TN write to SC seeking recall of dog removal order', 'url' => 'https://www.dtnext.in/news/tamilnadu/over-15l-from-tn-write-to-sc-seeking-recall-of-dog-removal-order-855059', 'logo' => 0 ),
		),
		false
	);
}
if ( ! get_page_by_path( 'media' ) ) {
	$id    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'media', 'post_title' => 'Media & PR', 'post_content' => "<!-- wp:paragraph -->\n<p>See how our movement is making headlines across the nation</p>\n<!-- /wp:paragraph -->\n<!-- wp:shortcode -->\n[aw_media_list]\n<!-- /wp:shortcode -->\n" ) );
	$log[] = "/media/: created (#$id)";
}

// Opportunities with no deadline: review date 30 days after posting.
foreach ( get_posts( array( 'post_type' => 'aw_opportunity', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	aw_opportunity_set_review( $id );
}
$log[] = 'opportunities: review dates set where there is no deadline';

delete_transient( 'aw_stats' );
flush_rewrite_rules( false );
foreach ( $log as $l ) {
	WP_CLI::log( $l );
}
