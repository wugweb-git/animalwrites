<?php
/**
 * Links seed (owner, 31 Oct 2026): the links known before Bing Webmaster was set up, added once to wp_aw_si_links.
 * - Search Console → Links, read 26 Oct 2026 (Claude outputs/who-links-to-animalwrites-2026-10-26.md: news and websites,
 *   Telegram, Reddit, X, LinkedIn; the Instagram tables are mentions and tags, not links, so they are left out). Where
 *   the read names no linked page, the home page is used and the note says so.
 * - The OpenRush backlink index, read 25 Oct 2026.
 * Run on the schema upgrade, on opening the Links tab, or with `wp aw-si links seed`. Marked done (option
 * aw_si_links_seeded) only when every row was written; a repeat only moves dates, never types or hidden flags.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The seed rows. */
function aw_si_links_seed_data(): array {
	$home = 'https://animalwrites.in/';
	$lab  = 'https://animalwrites.in/campaigns/lab-animals-rules-2026/';
	$gsc  = 'Search Console Links, read 26 Oct 2026';
	$or   = 'OpenRush backlink index, read 25 Oct 2026';
	$nopg = 'Linked page not named in the read; home page used.';
	$rows = array();
	$g    = function ( string $from, string $to, string $type, string $anchor = '', string $note = '', int $links = 1 ) use ( &$rows, $gsc ) {
		$rows[] = array(
			'from_url'   => $from,
			'linked_url' => $to,
			'anchor'     => $anchor,
			'links'      => $links,
			'first_seen' => '2026-10-26',
			'last_seen'  => '2026-10-26',
			'source'     => $gsc,
			'type'       => $type,
			'note'       => $note,
		);
	};
	// News and websites (confirmed on the live page).
	$g( 'https://www.hindustantimes.com/entertainment/bollywood/kalki-koechlin-abhirami-nafisa-ali-urge-fans-to-send-objection-emails-to-government-over-new-draft-on-animal-testing-in-labs-101792400061102.html', $lab, 'press', 'According to Animal Writes', 'Under "What does the GSR 864(E) entail?"' );
	$g( 'https://www.freepressjournal.in/mumbai/nationwide-campaign-over-50000-citizens-write-to-supreme-court-seeking-recall-of-dog-removal-order', $home, 'press', '', 'Where we appear: "uploaded on the animalwrites.in website"' );
	$g( 'https://www.dtnext.in/news/tamilnadu/over-15l-from-tn-write-to-sc-seeking-recall-of-dog-removal-order-855059', $home, 'press', '', 'Where we appear: "upload their receipts here https://animalwrites.in/"' );
	$g( 'https://dirkvanlaere.com/article/bollywood-stars-speak-out-urgent-action-needed-to-protect-animals-from-new-draft', $lab, 'press', '', 'Where we appear: "According to reports from Animal Writes"' );
	$g( 'https://www.cjmemorialtrust.org/post/animalwrites-in-pawsandpost-the-countdown-to-a-collective-voice-nov-29', $home, 'organisation', '', 'The whole post is about us (#PawsAndPost).' );
	// Telegram.
	$g( 'https://t.me/s/PeepalFarm', $home, 'social', '', 'Peepal Farm channel, 19 posts.', 19 );
	// Reddit.
	$g( 'https://www.reddit.com/r/Indiedogs/comments/1p9vl6m/animal_writes/', $home, 'social' );
	$g( 'https://www.reddit.com/r/IndianPets/comments/1xc2uq7/urgent_our_animals_are_in_danger_see_description/', $lab, 'social', '', 'Links to the draft lab rules.' );
	$g( 'https://www.reddit.com/r/TwentiesIndia/comments/1xc31mo/urgent_our_animals_are_in_danger_see_description/', $lab, 'social', '', 'Links to the draft lab rules.' );
	$g( 'https://www.reddit.com/r/indiadiscussion/comments/1xc85cz/indias_new_draft_rules_on_animal_experiments_put/', $lab, 'social' );
	// X.
	$g( 'https://x.com/ShrijeetNair06/status/1994374563996590570', $home, 'social', '', $nopg );
	$g( 'https://x.com/ms_medusssa/status/1993944788102730064', $home, 'social', '', $nopg );
	$g( 'https://x.com/dilthi_gujarati/status/2112537577022779604', $lab, 'social' );
	foreach ( array( 'Standforstrays', 'nish7x', 'sangitaiyer108' ) as $x ) {
		$g( 'https://x.com/' . $x, $home, 'social', '', 'Profile links to us. ' . $nopg );
	}
	// LinkedIn.
	$g( 'https://in.linkedin.com/company/kahu-advocacy-foundation', $home, 'social', '', $nopg );
	$g( 'https://in.linkedin.com/in/abodh-aras-57039b185', $home, 'social', '', 'Possibly Aras Foundation (listing 18414 has a pending claim). ' . $nopg );
	$g( 'https://in.linkedin.com/in/jc-sharma-74270526', $home, 'social', '', $nopg );
	$g( 'https://in.linkedin.com/in/saileshghelani', $home, 'social', '', $nopg );

	// OpenRush: one row per domain, with its number of links.
	$o = function ( string $domain, string $to, string $type, string $anchor, string $first, string $note = '', int $links = 1 ) use ( &$rows, $or ) {
		$rows[] = array(
			'domain'     => $domain,
			'linked_url' => $to,
			'anchor'     => $anchor,
			'links'      => $links,
			'first_seen' => $first,
			'last_seen'  => '2026-10-25',
			'source'     => $or,
			'type'       => $type,
			'note'       => $note,
		);
	};
	$o( 'xitter.cf', $lab, 'social', '', '2026-10-24', 'X mirror, #SayNoToAnimalExperimentationBill.' );
	$o( 'dogexpress.in', $home, 'directory', "Charlie's Animal Rescue Centre", '2026-10-22', 'nofollow' );
	$copies = array(
		'kucukiskender.com'           => 1,
		'horizondatacom.com'          => 1,
		'upnovadigiagency.com'        => 1,
		'stworzswojprzestrzen.com'    => 1,
		'manhattanbrassquintet.org'   => 4,
		'bstpo.org'                   => 4,
		'grandmondialcasinoapp.com'   => 1,
		'happywadeing.com'            => 1,
		'mymusicandlyrics.com'        => 1,
		'vyatka.org'                  => 1,
		'homeservicemarketingpros.com' => 1,
		'blckpress.org'               => 1,
		'korekom.org'                 => 1,
		'naakorndesign.com'           => 1,
		'wordofgodinstitute.org'      => 1,
	);
	foreach ( $copies as $d => $n ) {
		$o( $d, $lab, 'copy', 'Animal Writes', '2026-10-19', 'Copy of the celebrity GSR 864(E) article (seen 19–24 Oct).', $n );
	}
	$spam = array( 'clifflisting.com' => 2, 'dbike-us.com' => 1, 'techbumppy.com' => 1, 'thcradar.com' => 1, 'shanzytechs.site' => 1, 'sahammurah.com' => 1, 'bureatag.com' => 1, 'bestnz-poker-casinoslot.com' => 1, 'onlyhealthydeals.com' => 1, 'archive-hu.com' => 1, 'bestwebs123.site' => 1, 'thedocmag.com' => 1, 'dupurgeniefr.com' => 1, 'kernelpanicpodcast.com' => 1 );
	foreach ( $spam as $d => $n ) {
		$o( $d, $home, 'spam', 'Buy Backlinks…', '2026-10-02', 'Link farm (seen 2–23 Oct).', $n );
	}
	return $rows;
}

/**
 * Add the seed rows once.
 *
 * @param bool $force Run again even when marked done or after Delete data (a repeat only moves dates).
 * @return array Counts, or an empty array when already seeded.
 */
function aw_si_links_seed( bool $force = false ): array {
	// After "Delete Search Insights data" (aw_si_off is set) the built-in links stay deleted; only --force brings them back.
	if ( ! $force && ( get_option( 'aw_si_links_seeded' ) || get_option( 'aw_si_off' ) ) ) {
		return array();
	}
	$count = array(
		'added'   => 0,
		'updated' => 0,
		'failed'  => 0,
	);
	foreach ( aw_si_links_seed_data() as $row ) {
		$n = aw_si_link_put( $row );
		if ( 1 === $n ) {
			++$count['added'];
		} elseif ( $n >= 0 ) {
			++$count['updated'];
		} else {
			++$count['failed'];
		}
	}
	if ( ! $count['failed'] ) {
		update_option( 'aw_si_links_seeded', time(), false );
	}
	return $count;
}
