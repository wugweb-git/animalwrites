<?php
/**
 * Campaign tools for an objection or letter campaign (first used by Campaign 2.0, the draft lab animal rules 2026).
 * All wording lives in the campaign's own content, so it is edited in the block editor; the code adds behaviour only.
 *
 * Campaign settings (post meta on the aw_campaign item), per the Campaign Content Standard v1.0 (Notion, 17 Oct 2026):
 *   aw_campaign_open           Y-m-d  the first day for action (IST); before it the campaign is Pre (Upcoming)
 *   aw_campaign_close          Y-m-d  the last day for action (IST), shown in the countdown; it does not close the campaign
 *   aw_campaign_doc            int    the Library item whose clauses the rule chips quote
 *   aw_campaign_outcome        Post only: pending|achieved|partial|not|inconclusive (pending until reviewed)
 *   aw_campaign_outcome_asof   Y-m-d  when the outcome was last reviewed (set to the closing day if empty)
 *   aw_campaign_state_override pre|active|post, with aw_campaign_override_note (the reason; required). A campaign
 *                              closes only when the owner sets Post here; nothing closes it by date (owner, 2 Nov 2026)
 *   aw_campaign_metric_min     the count is shown from this number (default 100)
 *   aw_campaign_metric_stale   days after the last letter before the count shows an "as of" date (default 1)
 *   aw_campaign_share_text     the line shared with the link (Share button, WhatsApp, X); default: the title
 *   aw_campaign_share_image    attachment ID: the one share picture, 1080x1080 (1:1), for every network: X, Facebook,
 *                              Instagram, LinkedIn, WhatsApp, Threads and stories, so one picture is made once and reused
 *                              (owner, 13 Nov 2026); when set, it replaces the story and post pictures below
 *   aw_campaign_share_story    attachment ID: 1080x1920 image for Instagram/Facebook/WhatsApp stories (older campaigns)
 *   aw_campaign_share_post     attachment ID: 1080x1350 (4:5) image for feed posts (older campaigns)
 *   aw_campaign_share_hashtags hashtags for the X link only: comma-separated, without # (owner, 2 Nov 2026)
 *   aw_campaign_short          the short link's slug: /lab redirects to the campaign (campaign-short.php)
 *   aw_campaign_card_title     the side card's title, short (the campaign title when empty; owner, 13 Nov 2026)
 *
 * Shortcodes:
 *   [aw_rule_chip rule="11-3" label="Rule 11(3)" allows="..."]  a chip that opens the clause, word for word
 *   [aw_letter_tool to="..." subject="..." ticked="1,2,6"]       letter builder; its grounds are the list with
 *                                                                class aw-letter-grounds just before it
 *   [aw_campaign_count min="100"]                                "N letters sent" once N reaches min
 *   [aw_camp_status]                                             the hero's status: action window, and the outcome
 *   [aw_sticky_cta href="#send" label="..."]                     phone-only bar with the days left; days="0" leaves them out; after_label="..." shows after the close day;
 *                                                                pre_label="..." (pre_href, default #updates) shows it in Pre too
 *   [aw_campaign_band campaign="slug" theme="lab-rules"]       home band; shown only while the campaign is
 *                                                                published and open; label="…" a pill above the
 *                                                                title, art="ID" art_phone="ID" the artwork behind it
 * Blocks with class aw-camp-open show only while the campaign is open (Active); aw-camp-closed only after it closes
 * (Post); aw-camp-pre only before it opens. aw-camp-if-count shows once the count reaches the threshold;
 * aw-camp-if-filled hides a section that has nothing in it (e.g. no partners); aw-camp-if-items hides a list section with no items.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether a campaign is the Lab rules one (lab-animals-rules-2026), whose page is kept as it was on the morning of 2 Nov 2026 (owner). */
function aw_camp_is_lab_page( $id ) {
	return $id && 'lab-animals-rules-2026' === get_post_field( 'post_name', $id );
}

/** The campaign whose page is being shown (or the one given), or 0. */
function aw_camp_id( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_the_ID();
	return $id && 'aw_campaign' === get_post_type( $id ) ? $id : 0;
}

/** Unix time when the campaign closes (00:00 IST the day after its last day), or 0 when it has no date. */
function aw_camp_close_ts( $id ) {
	$day = (string) get_post_meta( $id, 'aw_campaign_close', true );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? strtotime( $day . ' 00:00:00 Asia/Kolkata' ) + DAY_IN_SECONDS : 0;
}

/** Unix time when the campaign opens (00:00 IST on its first day), or 0 when it has no open date. */
function aw_camp_open_ts( $id ) {
	$day = (string) get_post_meta( $id, 'aw_campaign_open', true );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? strtotime( $day . ' 00:00:00 Asia/Kolkata' ) : 0;
}

/** Lifecycle state: pre before the open date, then active until the owner sets the override to post. Not filtered. */
function aw_camp_state( $id ) {
	$over = (string) get_post_meta( $id, 'aw_campaign_state_override', true );
	if ( in_array( $over, array( 'pre', 'active', 'post' ), true ) && '' !== trim( (string) get_post_meta( $id, 'aw_campaign_override_note', true ) ) ) {
		return $over;
	}
	$open = aw_camp_open_ts( $id );
	return $open && time() < $open ? 'pre' : 'active'; // the close date never closes it (owner, 2 Nov 2026)
}

/** Whether the campaign is open (Active; no dates counts as open). Filterable for checking the closed state. */
function aw_camp_is_open( $id ) {
	return (bool) apply_filters( 'aw_camp_is_open', 'active' === aw_camp_state( $id ), $id );
}

/** The state the page shows: active, pre or post (the preview filter included). */
function aw_camp_shown_state( $id ) {
	if ( aw_camp_is_open( $id ) ) {
		return 'active';
	}
	return 'pre' === aw_camp_state( $id ) ? 'pre' : 'post';
}

/** Action window: upcoming, open, last-day (the close date itself, IST) or closed. */
function aw_camp_window( $id ) {
	$state = aw_camp_shown_state( $id );
	if ( 'active' !== $state ) {
		return 'pre' === $state ? 'upcoming' : 'closed';
	}
	$day = (string) get_post_meta( $id, 'aw_campaign_close', true );
	return $day && wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Kolkata' ) ) === $day ? 'last-day' : 'open';
}

/** Outcome (Post): [key, label, as-of Y-m-d]. Pending until the owner classifies it; as of the closing day by default. */
function aw_camp_outcome( $id ) {
	$labels = array( 'pending' => 'Pending', 'achieved' => 'Achieved', 'partial' => 'Partially achieved', 'not' => 'Not achieved', 'inconclusive' => 'Inconclusive' );
	$key    = (string) get_post_meta( $id, 'aw_campaign_outcome', true );
	$key    = isset( $labels[ $key ] ) ? $key : 'pending';
	$asof   = (string) get_post_meta( $id, 'aw_campaign_outcome_asof', true );
	if ( ! $asof && aw_camp_close_ts( $id ) ) {
		$asof = wp_date( 'Y-m-d', aw_camp_close_ts( $id ), new DateTimeZone( 'Asia/Kolkata' ) );
	}
	return array( $key, $labels[ $key ], $asof );
}

/** Days to go, counted as [aw_countdown] counts them (to the start of the last day), so both say the same number. */
function aw_camp_days_left( $id ) {
	$ts = aw_camp_close_ts( $id );
	return $ts ? max( 0, (int) ceil( ( $ts - DAY_IN_SECONDS - time() ) / DAY_IN_SECONDS ) ) : 0;
}

/*
 * Preview the other state (admins and editors only): ?aw_camp_preview=closed or =open on a campaign page or the home
 * page. Logged-in views are not cached, so visitors never see it.
 */
add_filter(
	'aw_camp_is_open',
	function ( $open ) {
		$p = sanitize_key( wp_unslash( $_GET['aw_camp_preview'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $p, array( 'open', 'closed' ), true ) && current_user_can( 'edit_posts' ) ? 'open' === $p : $open;
	},
	20
);

/*
 * The switch at closing time, cache-safe: while a campaign is open, its page (and the home page with its hero) is cached
 * only until its close date (LiteSpeed TTL). The
 * TTL is also capped at the next midnight in India, so the countdown's day count never stays a day behind in the cache.
 */
function aw_camp_cache_until( $id ) {
	$ts = aw_camp_open_ts( $id ) > time() ? aw_camp_open_ts( $id ) : aw_camp_close_ts( $id );
	if ( $ts && time() < $ts ) {
		$midnight = ( new DateTimeImmutable( 'tomorrow', new DateTimeZone( 'Asia/Kolkata' ) ) )->getTimestamp();
		do_action( 'litespeed_control_set_ttl', max( 60, min( $ts, $midnight ) - time() ) );
	}
}
// No closing job: a campaign closes only when the owner sets it to Post (owner, 2 Nov 2026). Jobs scheduled before
// that are removed once, so none fires at a close date.
add_action(
	'init',
	function () {
		if ( ! get_option( 'aw_camp_close_jobs_removed' ) ) {
			wp_unschedule_hook( 'aw_camp_closed' );
			update_option( 'aw_camp_close_jobs_removed', 1, false );
		}
	}
);
// India Writes for Its Community Dogs closed on 29 Nov 2025 and was moved to Post on 17 Oct 2026 (Campaign Content
// Standard v1.0, appendix), by its close date only. Since the close date stopped closing campaigns (2 Nov 2026) it read
// as open: no outcome in its hero, and the sidebar's Current campaign card offered "Take part". Its Post state is set
// once, as the override the owner's rule asks for; a later change in WordPress Admin is kept.
add_action(
	'init',
	function () {
		if ( get_option( 'aw_camp_dogs_post_set' ) ) {
			return;
		}
		$p = get_page_by_path( 'india-writes-for-its-community-dogs', OBJECT, 'aw_campaign' );
		if ( $p && '' === (string) get_post_meta( $p->ID, 'aw_campaign_state_override', true ) ) {
			update_post_meta( $p->ID, 'aw_campaign_state_override', 'post' );
			update_post_meta( $p->ID, 'aw_campaign_override_note', 'Closed 29 Nov 2025; in Post since 17 Oct 2026 (Campaign Content Standard v1.0, appendix).' );
		}
		update_option( 'aw_camp_dogs_post_set', 1, false );
	}
);

/* ---------- Open / closed blocks ---------- */
add_filter(
	'render_block',
	function ( $html, $block ) {
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		if ( preg_match( '/(^|\s)aw-camp(\s|$)/', $cls ) && aw_camp_id() ) { // the page wrapper: is-closed (Post) or is-pre lets CSS reorder sections
			aw_camp_cache_until( aw_camp_id() );
			$cls = array( 'pre' => 'is-pre', 'post' => 'is-closed' )[ aw_camp_shown_state( aw_camp_id() ) ] ?? ''; // is-closed only after closing; a campaign not open yet is is-pre
			return '' === $cls ? $html : preg_replace( '/^(\s*<[^>]*?class=")([^"]*)"/', '$1$2 ' . $cls . '"', $html, 1 ); // appended to the wrapper's own class list
		}
		$id = aw_camp_id();
		if ( ! $id ) {
			return $html;
		}
		if ( preg_match( '/\baw-camp-if-count\b/', $cls ) ) { // a metric section: only once the count is shown
			$min = (int) get_post_meta( $id, 'aw_campaign_metric_min', true );
			if ( (int) get_post_meta( $id, 'aw_campaign_sent', true ) < ( $min ? $min : 100 ) ) {
				return '';
			}
		}
		if ( preg_match( '/\baw-camp-if-filled\b/', $cls ) && '' === trim( wp_strip_all_tags( do_shortcode( $html ) ) ) && false === strpos( $html, '<img' ) ) { // shortcodes run after blocks: test what they print
			return '';
		}
		if ( preg_match( '/\baw-camp-if-items\b/', $cls ) && false === strpos( $html, 'wp-block-post ' ) ) { // a list section with no items yet (e.g. no press coverage)
			return '';
		}
		if ( ! preg_match( '/\baw-camp-(open|closed|pre)\b/', $cls, $m ) ) {
			return $html;
		}
		$want = array( 'open' => 'active', 'closed' => 'post', 'pre' => 'pre' );
		return aw_camp_shown_state( $id ) === $want[ $m[1] ] ? $html : '';
	},
	10,
	2
);

/* ---------- Rule chips ---------- */

/** Collects chip dialogs (with $html) or returns them all (without). */
function aw_camp_dialogs( $html = null ) {
	static $all = array();
	if ( null !== $html ) {
		$all[] = $html;
	}
	return $all;
}
add_action( 'wp_footer', fn() => print( implode( '', aw_camp_dialogs() ) ) ); // phpcs:ignore -- built from escaped parts above

/**
 * The clause with this anchor in a Library item, as HTML: the block itself, plus its lettered clauses (for a sub-rule)
 * or the rest of the rule up to the next heading (for a whole rule). Empty when not found.
 *
 * @param int    $doc    Library post ID.
 * @param string $anchor Anchor id, e.g. rule-11-3.
 * @return string
 */
function aw_camp_clause( $doc, $anchor ) {
	$key  = 'aw_clause_' . $doc . '_' . md5( $anchor . get_post_modified_time( 'U', true, $doc ) );
	$hit  = wp_cache_get( $key, 'aw' );
	if ( false !== $hit ) {
		return $hit;
	}
	$out    = '';
	$blocks = array_values( array_filter( parse_blocks( (string) get_post_field( 'post_content', $doc ) ), fn( $b ) => ! empty( $b['blockName'] ) ) );
	foreach ( $blocks as $i => $b ) {
		if ( ( $b['attrs']['anchor'] ?? '' ) !== $anchor ) {
			continue;
		}
		$is_head = 'core/heading' === $b['blockName'];
		$out     = $is_head ? '' : $b['innerHTML'];
		$n       = 0;
		for ( $j = $i + 1; $j < count( $blocks ) && $n < 12; $j++, $n++ ) {
			$next   = $blocks[ $j ];
			$clause = false !== strpos( (string) ( $next['attrs']['className'] ?? '' ), 'aw-clause' );
			if ( 'core/heading' === $next['blockName'] || ( ! $is_head && ! $clause ) ) {
				break;
			}
			$out .= $next['innerHTML'];
		}
		break;
	}
	$out = wp_kses( preg_replace( '/\sid="[^"]*"/', '', $out ), array( 'p' => array( 'class' => true ), 'strong' => array(), 'em' => array() ) );
	wp_cache_set( $key, $out, 'aw', HOUR_IN_SECONDS );
	return $out;
}

add_shortcode(
	'aw_rule_chip',
	function ( $atts ) {
		static $n = 0;
		$a      = shortcode_atts( array( 'rule' => '', 'label' => '', 'allows' => '', 'doc' => '' ), $atts );
		$camp   = aw_camp_id();
		$doc    = $a['doc'] ? (int) $a['doc'] : ( $camp ? (int) get_post_meta( $camp, 'aw_campaign_doc', true ) : 0 );
		$anchor = preg_match( '/^[a-z0-9-]+$/', $a['rule'] ) ? ( 0 === strpos( $a['rule'], 'annexure' ) ? $a['rule'] : 'rule-' . $a['rule'] ) : '';
		$label  = $a['label'] ? $a['label'] : 'Rule ' . $a['rule'];
		$clause = $doc && $anchor ? aw_camp_clause( $doc, $anchor ) : '';
		if ( '' === $clause ) {
			return '<span class="kit-tag aw-rule-chip aw-rule-chip--plain">' . esc_html( $label ) . '</span>';
		}
		++$n;
		$dlg  = 'aw-rule-' . $n;
		$link = 'publish' === get_post_status( $doc ) ? get_permalink( $doc ) . '#' . $anchor : (string) get_post_meta( $doc, 'aw_source_url', true );
		$btn  = '<button type="button" class="kit-chip aw-rule-chip" aria-haspopup="dialog" aria-controls="' . esc_attr( $dlg ) . '" data-rule="' . esc_attr( $a['rule'] ) . '">' . esc_html( $label ) . '</button>';
		$out  = '<dialog class="aw-rule-chip-dialog" id="' . esc_attr( $dlg ) . '" aria-labelledby="' . esc_attr( $dlg ) . '-t">';
		$out .= '<p class="aw-rule-chip-head" id="' . esc_attr( $dlg ) . '-t"><strong>' . esc_html( $label ) . ': what it says</strong></p>';
		$out .= '<div class="aw-rule-chip-text">' . $clause . '</div>';
		if ( '' !== $a['allows'] ) {
			$out .= '<p class="aw-rule-chip-head"><strong>What it allows</strong></p><p>' . esc_html( $a['allows'] ) . '</p>';
		}
		$out .= '<p class="aw-rule-chip-src">' . esc_html( get_the_title( $doc ) ) . ( $link ? ' <a href="' . esc_url( $link ) . '" aria-label="' . esc_attr( 'Read the full text of ' . $label ) . '">Read the full text</a>' : '' ) . '</p>';
		$out .= '<button type="button" class="aw-rule-chip-close" data-close>Close</button></dialog>';
		// Printed at the end of the page (a dialog holds paragraphs, so it cannot sit inside one), inside a template: the
		// page's own tree stays small, and campaign.js puts the dialog in the page on the chip's first tap.
		aw_camp_dialogs( '<template id="' . esc_attr( $dlg ) . '-tpl">' . $out . '</template>' );
		return $btn;
	}
);

/* ---------- Letter tool ---------- */
add_shortcode(
	'aw_letter_tool',
	function ( $atts ) {
		$a  = shortcode_atts(
			array(
				'to'       => '',
				'subject'  => '',
				'ticked'   => '',
				'salute'   => 'Sir/Madam,',
				'intro'    => '',
				'closing'  => '',
				'sign'     => 'Yours faithfully,',
				'address'  => '',
				'source'   => '', // newsletter_optin's source (analytics); default campaign:<slug>
			),
			$atts
		);
		$id = aw_camp_id();
		if ( $id && ! aw_camp_is_open( $id ) ) {
			return '';
		}
		$to = sanitize_email( $a['to'] );
		if ( ! $to ) {
			return '';
		}
		$data = array(
			'to'          => $to,
			'subject'     => $a['subject'],
			'ticked'      => array_map( 'intval', array_filter( explode( ',', $a['ticked'] ) ) ),
			'address'     => $a['address'],
			'salute'      => $a['salute'],
			'intro'       => $a['intro'],
			'closing'     => $a['closing'],
			'sign'        => $a['sign'],
			'confirm'     => $id ? rest_url( 'aw/v1/campaign/' . $id . '/sent' ) : '',
			'letter'      => $id ? rest_url( 'aw/v1/campaign/' . $id . '/letter' ) : '', // anonymous statistics (campaign-letters.php)
			'optin'       => $id ? rest_url( 'aw/v1/campaign/' . $id . '/optin' ) : '', // the older sign-up, used when the Campaign form is not set up
			'ff'          => $id && function_exists( 'aw_cf_id' ) ? aw_cf_id() : 0, // the Fluent Forms Campaign form (campaign-form.php)
			'ajax'        => admin_url( 'admin-ajax.php' ),
			'campaign_id' => $id,
			'consent'     => defined( 'AW_CL_CONSENT' ) ? AW_CL_CONSENT : '',
			'source'      => $a['source'] ? $a['source'] : ( $id ? 'campaign:' . get_post_field( 'post_name', $id ) : '' ),
		);
		ob_start();
		?>
<form class="aw-letter" data-letter="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" novalidate>
<?php if ( aw_camp_is_lab_page( $id ) ) : // the Lab rules page keeps its letter tool on one page, as on the morning of 2 Nov 2026 (owner) ?>
	<?php
	// Fields in the theme's form markup (kits-core forms.php aw_field()): kit-field, label, kit-field-control with the
	// field icon and the error line {id}-error. Each placeholder repeats its label in plain words (owner, 2 Nov 2026).
	?>
	<div class="aw-letter-inputs">
		<fieldset class="aw-letter-grounds-pick">
			<legend><span class="aw-letter-step">1</span>Choose your grounds <span class="aw-letter-picked" aria-live="polite"></span></legend>
			<div class="aw-letter-ground-list"></div>
		</fieldset>
		<div class="aw-letter-sign">
		<p class="aw-letter-head"><span class="aw-letter-step">2</span>Sign it</p>
		<div class="kit-field kit-field--text aw-letter-field"><label for="aw-letter-name">Your name <span aria-hidden="true">*</span></label>
			<div class="kit-field-control"><span class="kit-ico kit-ico--name kit-field-icon" aria-hidden="true"></span><input id="aw-letter-name" aria-describedby="aw-letter-name-error" data-msg-required="Add your name to send your objection." name="name" type="text" autocomplete="name" enterkeyhint="next" required maxlength="80" placeholder="Your name"></div>
			<span class="kit-field-message kit-field-error" id="aw-letter-name-error" aria-live="polite"></span></div>
		<div class="kit-field kit-field--text aw-letter-field"><label for="aw-letter-city">Your city <span aria-hidden="true">*</span></label>
			<div class="kit-field-control"><span class="kit-ico kit-ico--map-pin kit-field-icon" aria-hidden="true"></span><input id="aw-letter-city" aria-describedby="aw-letter-city-error" data-msg-required="Add your city to send your objection." name="city" type="text" autocomplete="address-level2" enterkeyhint="next" required maxlength="60" placeholder="Your city"></div>
			<span class="kit-field-message kit-field-error" id="aw-letter-city-error" aria-live="polite"></span></div>
		<div class="kit-field kit-field--email aw-letter-field aw-letter-email"><label for="aw-letter-email">Your email <span aria-hidden="true">*</span></label>
			<div class="kit-field-control"><span class="kit-ico kit-ico--mail kit-field-icon" aria-hidden="true"></span><input id="aw-letter-email" aria-describedby="aw-letter-email-error" data-msg-required="Add a working email to send your objection." name="email" type="email" inputmode="email" autocomplete="email" enterkeyhint="next" required maxlength="190" placeholder="Your email" data-msg-email="Add a working email to send your objection."></div>
			<span class="kit-field-message kit-field-error" id="aw-letter-email-error" aria-live="polite"></span></div>
		<div class="kit-field kit-field--textarea aw-letter-field"><label for="aw-letter-own">Add a line in your own words <span class="kit-optional aw-letter-hint">(optional, up to 300 characters)</span></label>
			<div class="kit-field-control"><span class="kit-ico kit-ico--file kit-field-icon" aria-hidden="true"></span><textarea id="aw-letter-own" name="own" maxlength="300" rows="3" placeholder="Add a line in your own words"></textarea></div></div>
		</div>
	</div>
	<div class="aw-letter-out">
		<p class="aw-letter-head"><span class="aw-letter-step">3</span>Check and send</p>
		<p class="aw-letter-meta"><span class="aw-letter-to"><?php echo aw_i( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?><?php echo esc_html( $to ); ?></span><span><strong>Subject:</strong> <?php echo esc_html( $a['subject'] ); ?></span></p>
		<div class="aw-letter-preview" aria-live="polite" tabindex="0" aria-label="Your letter"></div>
		<div class="kit-field kit-field--checkbox aw-letter-optin"><label class="kit-checkbox kit-check" for="aw-letter-optin"><input type="checkbox" id="aw-letter-optin" name="optin" value="1" checked> <span><?php echo esc_html( defined( 'AW_CL_OPTIN' ) ? AW_CL_OPTIN : '' ); ?></span></label></div>
		<div class="kit-field kit-field--tel aw-letter-field"><label for="aw-letter-wa">Mobile <span class="kit-optional aw-letter-hint">(optional)</span></label>
			<div class="kit-field-control"><span class="kit-ico kit-ico--phone kit-field-icon" aria-hidden="true"></span><input id="aw-letter-wa" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" enterkeyhint="done" maxlength="16" placeholder="10 digits"></div></div>
		<div class="kit-field kit-field--checkbox aw-letter-optin"><label class="kit-checkbox kit-check" for="aw-letter-wa-optin"><input type="checkbox" id="aw-letter-wa-optin" name="wa_optin" value="1" checked> <span>On WhatsApp</span></label></div>
		<p class="kit-form-status aw-letter-error" role="alert" hidden>Add your name, city and email first.</p>
		<?php echo function_exists( 'kits_altcha_widget' ) ? str_replace( '<div class="kit-captcha', '<div data-placed class="kit-captcha', kits_altcha_widget( 'invisible' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in kits_altcha_widget; the sign-up's bot check (campaign.js) ?>
		<div class="aw-letter-routes">
			<button type="button" class="wp-element-button kit-btn" data-route="gmail"><?php echo aw_i( 'brand-gmail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Send with Gmail</button>
			<button type="button" class="wp-element-button kit-btn kit-btn--secondary aw-letter-alt" data-route="mailto"><?php echo aw_i( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Email app</button>
			<button type="button" class="wp-element-button kit-btn kit-btn--secondary aw-letter-alt" data-route="copy"><?php echo aw_i( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Copy letter</button>
		</div>
		<p class="aw-letter-copied" role="status"></p>
		<div class="aw-letter-confirm" hidden>
			<p><strong>Did you send it?</strong> Check your sent folder. We count your objection once you say it went.</p>
			<button type="button" class="wp-element-button kit-btn" data-confirm="yes">Yes, I sent it</button>
			<button type="button" class="wp-element-button kit-btn kit-btn--secondary aw-letter-alt" data-confirm="no">Not yet</button>
		</div>
		<div class="kit-form-done aw-letter-thanks" hidden tabindex="-1">
			<p class="aw-letter-thanks-head"><?php echo aw_i( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?><strong>Thank you. Your objection is on its way to CCSEA.</strong></p>
			<p>Keep the sent email: it is your record. CCSEA may reply to the address you sent from.</p>
			<p class="aw-letter-ref" hidden>Your reference: <strong></strong></p>
		</div>
	</div>
<?php else : ?>
<div class="kit-steps aw-letter-steps" data-steps="3">
	<fieldset class="kit-step aw-letter-grounds-pick" data-step="1">
		<legend><span class="kit-step-count">Step 1 of 3</span><span class="aw-letter-step" aria-hidden="true">1</span><span class="kit-step-title">Choose your grounds</span> <span class="aw-letter-picked" aria-live="polite"></span></legend>
		<div class="aw-letter-ground-list"></div>
		<div class="kit-step-nav"><button type="button" class="kit-btn" data-step-next>Next</button></div>
	</fieldset>
	<fieldset class="kit-step aw-letter-sign" data-step="2">
		<legend class="aw-letter-head"><span class="kit-step-count">Step 2 of 3</span><span class="aw-letter-step" aria-hidden="true">2</span><span class="kit-step-title">Sign it</span></legend>
		<div class="aw-letter-sign-fields">
			<p class="aw-letter-field"><label for="aw-letter-name">Your name <span aria-hidden="true">*</span></label>
				<input id="aw-letter-name" aria-describedby="aw-letter-name-error" data-msg-required="Add your name to send your objection." name="name" type="text" autocomplete="name" enterkeyhint="next" required maxlength="80"><span class="kit-field-message kit-field-error" id="aw-letter-name-error" aria-live="polite"></span></p>
			<p class="aw-letter-field"><label for="aw-letter-city">Your city <span aria-hidden="true">*</span></label>
				<input id="aw-letter-city" aria-describedby="aw-letter-city-error" data-msg-required="Add your city to send your objection." name="city" type="text" autocomplete="address-level2" enterkeyhint="next" required maxlength="60"><span class="kit-field-message kit-field-error" id="aw-letter-city-error" aria-live="polite"></span></p>
			<p class="aw-letter-field aw-letter-email"><label for="aw-letter-email">Your email <span aria-hidden="true">*</span></label>
				<input id="aw-letter-email" aria-describedby="aw-letter-email-error" data-msg-required="Add a working email to send your objection." name="email" type="email" autocomplete="email" enterkeyhint="next" required maxlength="190"><span class="kit-field-message kit-field-error" id="aw-letter-email-error" aria-live="polite"></span></p>
			<p class="aw-letter-field"><label for="aw-letter-own">Add a line in your own words <span class="aw-letter-hint">(optional, up to 300 characters)</span></label>
				<textarea id="aw-letter-own" name="own" maxlength="300" rows="3"></textarea></p>
		</div>
		<div class="aw-letter-preview" tabindex="0" aria-label="Your letter"></div>
		<div class="kit-step-nav"><button type="button" class="kit-btn" data-step-next>Next</button><button type="button" class="kit-btn kit-btn--secondary" data-step-back>Back</button></div>
	</fieldset>
	<fieldset class="kit-step aw-letter-out" data-step="3">
		<legend class="aw-letter-head"><span class="kit-step-count">Step 3 of 3</span><span class="aw-letter-step" aria-hidden="true">3</span><span class="kit-step-title">Check and send</span></legend>
		<p class="aw-letter-meta"><span class="aw-letter-to"><?php echo aw_i( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?><?php echo esc_html( $to ); ?></span><span><strong>Subject:</strong> <?php echo esc_html( $a['subject'] ); ?></span></p>
		<p class="aw-letter-optin"><input type="checkbox" id="aw-letter-optin" name="optin" value="1" checked> <label for="aw-letter-optin"><?php echo esc_html( defined( 'AW_CL_OPTIN' ) ? AW_CL_OPTIN : '' ); ?></label></p>
		<p class="aw-letter-field"><label for="aw-letter-wa">Mobile <span class="aw-letter-hint">(optional)</span></label>
			<input id="aw-letter-wa" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" maxlength="16" placeholder="10 digits"></p>
		<p class="aw-letter-optin"><input type="checkbox" id="aw-letter-wa-optin" name="wa_optin" value="1" checked> <label for="aw-letter-wa-optin">On WhatsApp</label></p>
		<p class="aw-letter-error" role="alert" hidden>Add your name, city and email first.</p>
		<?php echo function_exists( 'kits_altcha_widget' ) ? str_replace( '<div class="kit-captcha', '<div data-placed class="kit-captcha', kits_altcha_widget( 'invisible' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in kits_altcha_widget; the sign-up's bot check (campaign.js) ?>
		<div class="aw-letter-routes">
			<button type="button" class="wp-element-button" data-route="gmail"><?php echo aw_i( 'brand-gmail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Send with Gmail</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="mailto"><?php echo aw_i( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Email app</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="copy"><?php echo aw_i( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?>Copy letter</button>
		</div>
		<p class="aw-letter-copied" role="status"></p>
		<div class="aw-letter-confirm" hidden>
			<p><strong>Did you send it?</strong> Check your sent folder. We count your objection once you say it went.</p>
			<button type="button" class="wp-element-button" data-confirm="yes">Yes, I sent it</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-confirm="no">Not yet</button>
		</div>
		<div class="aw-letter-thanks" hidden tabindex="-1">
			<p class="aw-letter-thanks-head"><?php echo aw_i( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_i() returns a trusted inline SVG icon.
?><strong>Thank you. Your objection is on its way to CCSEA.</strong></p>
			<p>Keep the sent email: it is your record. CCSEA may reply to the address you sent from.</p>
			<p class="aw-letter-ref" hidden>Your reference: <strong></strong></p>
		</div>
		<div class="kit-step-nav"><button type="button" class="kit-btn kit-btn--secondary" data-step-back>Back</button></div>
	</fieldset>
</div>
<?php endif; ?>
</form>
		<?php
		return (string) ob_get_clean();
	}
);

/* ---------- Count of letters confirmed (no personal data) ---------- */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/campaign/(?P<id>\d+)/sent',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $req ) {
					$id = aw_camp_id( (int) $req['id'] );
					if ( ! $id || 'publish' !== get_post_status( $id ) || ! aw_camp_is_open( $id ) ) {
						return new WP_Error( 'aw_closed', 'Not open', array( 'status' => 404 ) );
					}
					$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore
					$key = 'aw_cs_' . $id . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt() ), 0, 20 ); // one a minute per address
					if ( get_transient( $key ) ) {
						return array( 'count' => (int) get_post_meta( $id, 'aw_campaign_sent', true ) );
					}
					set_transient( $key, 1, MINUTE_IN_SECONDS );
					$n = (int) get_post_meta( $id, 'aw_campaign_sent', true ) + 1;
					update_post_meta( $id, 'aw_campaign_sent', $n );
					update_post_meta( $id, 'aw_campaign_sent_at', time() );
					return array( 'count' => $n );
				},
			)
		);
	}
);

add_shortcode(
	'aw_campaign_count',
	function ( $atts ) {
		$id  = aw_camp_id();
		$min = $id ? (int) get_post_meta( $id, 'aw_campaign_metric_min', true ) : 0;
		$a   = shortcode_atts( array( 'min' => $min ? $min : 100, 'label' => 'objections sent' ), $atts );
		$n   = $id ? (int) get_post_meta( $id, 'aw_campaign_sent', true ) : 0;
		if ( $n < (int) $a['min'] ) {
			return '';
		}
		$at    = $id ? (int) get_post_meta( $id, 'aw_campaign_sent_at', true ) : 0; // the last "Yes, I sent it"
		$stale = $id ? (int) get_post_meta( $id, 'aw_campaign_metric_stale', true ) : 0;
		$asof  = $at && time() - $at > ( $stale ? $stale : 1 ) * DAY_IN_SECONDS ? ' <span class="aw-camp-count-asof">as of ' . aw_date( $at ) . '</span>' : '';
		return '<p class="aw-camp-count"><strong>' . esc_html( number_format_i18n( $n ) ) . '</strong> ' . esc_html( $a['label'] ) . $asof . '</p>';
	}
);

/*
 * ---------- Campaign partners ----------
 * Campaigns can have partners (owner, 17 Oct 2026; the site itself has none). [aw_campaign_partners title="Partners"]
 * prints the campaign's partners, Directory listings named in aw_campaign_partners (IDs, comma-separated) plus any
 * older aw_participant rows (aw_campaign_partner_ids(), model.php), as cards linking to their profiles; with no
 * partners it prints nothing, heading included.
 */
add_shortcode(
	'aw_campaign_partners',
	function ( $atts ) {
		$a   = shortcode_atts( array( 'title' => 'Partners' ), $atts );
		$id  = aw_camp_id();
		$ids = $id ? aw_campaign_partner_ids( $id ) : array();
		$out = '';
		foreach ( $ids as $pid ) {
			if ( 'aw_listing' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$logo = get_the_post_thumbnail( $pid, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) );
			$out .= '<li><a href="' . esc_url( get_permalink( $pid ) ) . '">' . ( $logo ? $logo : aw_i( 'directory' ) ) . '<span>' . esc_html( get_the_title( $pid ) ) . '</span></a></li>';
		}
		return $out ? '<div class="aw-camp-partners"><h2>' . esc_html( $a['title'] ) . '</h2><ul>' . $out . '</ul></div>' : '';
	}
);

/*
 * ---------- Status (hero) ----------
 * The action window is the main label; after closing, the outcome sits beside it as its own label with its "as of"
 * date (Campaign Content Standard A3). The two are never joined into one string.
 */
add_shortcode(
	'aw_camp_status',
	function () {
		$id = aw_camp_id();
		if ( ! $id ) {
			return '';
		}
		// No status pills on campaigns, ever (owner, 2 Nov 2026): no Upcoming, Open, Last day or Closed. A closed campaign
		// keeps its outcome line, as plain text.
		if ( 'closed' !== aw_camp_window( $id ) ) {
			return '';
		}
		list( , $label, $asof ) = aw_camp_outcome( $id );
		if ( '' === (string) $label ) {
			return '';
		}
		return '<p class="aw-camp-status">Outcome: ' . esc_html( $label ) . ( $asof ? '. As of ' . aw_date( $asof ) : '' ) . '</p>';
	}
);

/* ---------- Phone bar ---------- */
add_shortcode(
	'aw_sticky_cta',
	function ( $atts ) {
		$a  = shortcode_atts( array( 'href' => '#send', 'label' => 'Send your objection', 'days' => '1', 'pre_href' => '#updates', 'pre_label' => '', 'after_label' => '' ), $atts );
		$id = aw_camp_id();
		if ( $id && '' !== $a['pre_label'] && 'pre' === aw_camp_shown_state( $id ) ) { // Pre: optional "get notified" bar, no day count (Content Standard E)
			return '<div class="aw-sticky-cta" data-target="' . esc_attr( $a['pre_href'] ) . '"><a class="wp-element-button" href="' . esc_attr( $a['pre_href'] ) . '">' . esc_html( $a['pre_label'] ) . '</a></div>';
		}
		if ( ! $id || ! aw_camp_is_open( $id ) ) {
			return '';
		}
		$d     = aw_camp_days_left( $id );
		$close = aw_camp_close_ts( $id );
		if ( '' !== $a['after_label'] && $close && time() >= $close ) { // after the close day: the owner's wording, e.g. "Closed 5 Nov · decision awaited"
			$note = $a['after_label'];
		} else {
			$note = $d ? sprintf( _n( '%d day to go', '%d days to go', $d, 'animal-writes-core' ), $d ) : 'Last day today';
		}
		$days = '0' === (string) $a['days'] ? '' : '<span class="aw-sticky-cta-days">' . esc_html( $note ) . '</span>';
		return '<div class="aw-sticky-cta" data-target="' . esc_attr( $a['href'] ) . '"><a class="wp-element-button" href="' . esc_attr( $a['href'] ) . '">' . esc_html( $a['label'] ) . '</a>' . $days . '</div>';
	}
);

/* ---------- Home band ---------- */
add_shortcode(
	'aw_campaign_band',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'campaign' => '', 'title' => '', 'text' => '', 'button' => 'Send your objection', 'link' => 'Read what the rules say', 'theme' => '', 'label' => '', 'art' => '', 'art_phone' => '' ), $atts );
		$post = $a['campaign'] ? get_page_by_path( sanitize_title( $a['campaign'] ), OBJECT, 'aw_campaign' ) : null;
		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}
		$url  = get_permalink( $post );
		$day  = (string) get_post_meta( $post->ID, 'aw_campaign_close', true );
		$data = ' data-campaign-slug="' . esc_attr( $post->post_name ) . '" data-campaign-title="' . esc_attr( get_the_title( $post ) ) . '"'; // campaign_action promo_click (aw-analytics.js)
		if ( 'pre' === aw_camp_shown_state( $post->ID ) ) { // not open yet: no band (optional in Pre; not set up)
			return '';
		}
		if ( ! aw_camp_is_open( $post->ID ) ) { // after closing: a quieter "Latest campaign" card in the same place
			$out  = '<section class="aw-camp aw-camp-band aw-camp-band--closed' . ( $a['theme'] ? ' aw-camp--' . sanitize_html_class( $a['theme'] ) : '' ) . '" aria-labelledby="aw-camp-band-t"' . $data . '>';
			$out .= '<p class="aw-camp-band-kicker">Latest campaign</p>';
			$out .= '<h2 id="aw-camp-band-t">' . esc_html( $a['title'] ? $a['title'] : get_the_title( $post ) ) . '</h2>';
			$out .= '<p>' . ( $day ? 'Objections closed on ' . aw_date( $day ) . '. ' : '' ) . 'See what happens next and read the rules.</p>';
			$out .= '<p class="aw-camp-band-actions"><a class="wp-element-button" data-track="home_campaign_click" href="' . esc_url( $url . '#next' ) . '">What happens next</a></p>';
			return $out . '</section>';
		}
		aw_use_component_style( 'aw-camp-band-aw' ); // the open band's focus colour
		aw_camp_cache_until( $post->ID );
		// The objection window as one mark per day: days gone dimmed, today raised, the days left bright.
		$open_ts  = aw_camp_open_ts( $post->ID );
		$close_ts = aw_camp_close_ts( $post->ID );
		$window   = do_shortcode( '[aw_countdown date="' . esc_attr( $day ) . '" label="Last day"]' );
		if ( $open_ts && $close_ts > $open_ts ) {
			$total   = (int) round( ( $close_ts - $open_ts ) / DAY_IN_SECONDS );
			$today   = (int) floor( ( time() - $open_ts ) / DAY_IN_SECONDS );
			$window .= '<ol class="aw-camp-band-days" style="--days:' . $total . '" aria-hidden="true">';
			for ( $i = 0; $i < $total; $i++ ) {
				$window .= '<li' . ( $i < $today ? ' class="is-past"' : ( $i === $today ? ' class="is-today"' : '' ) ) . '></li>';
			}
			$window .= '</ol><p class="aw-camp-band-range kit-meta-row"><span class="kit-meta-item">Opened ' . aw_date( $open_ts ) . '</span><span class="kit-meta-item">Last day ' . aw_date( $day ) . '</span></p>';
		}
		// The artwork is the band's background; the text sits on its plain left side (owner, 19 Oct 2026). Phones get
		// their own crop of it.
		$art   = (int) $a['art'];
		$pic   = '';
		if ( $art && wp_attachment_is_image( $art ) ) {
			$phone = (int) $a['art_phone'];
			$pic   = '<picture class="aw-camp-band-art">'
				. ( $phone ? '<source media="(max-width: 767px)" srcset="' . esc_attr( (string) wp_get_attachment_image_srcset( $phone, 'full' ) ) . '" sizes="100vw" width="' . (int) ( wp_get_attachment_metadata( $phone )['width'] ?? 0 ) . '" height="' . (int) ( wp_get_attachment_metadata( $phone )['height'] ?? 0 ) . '">' : '' ) // its own shape on phones
				. wp_get_attachment_image( $art, 'full', false, array( 'alt' => '', 'sizes' => '100vw', 'loading' => 'lazy', 'decoding' => 'async' ) )
				. '</picture>';
		}
		$out  = '<section class="aw-camp aw-camp-band' . ( $pic ? ' has-art' : '' ) . ( $a['theme'] ? ' aw-camp--' . sanitize_html_class( $a['theme'] ) : '' ) . '" aria-labelledby="aw-camp-band-t"' . $data . '><div class="aw-camp-band-inner"><div class="aw-camp-band-text">';
		$out .= $a['label'] ? '<p class="aw-camp-band-pill">' . esc_html( $a['label'] ) . '</p>' : '';
		$out .= '<h2 id="aw-camp-band-t" class="aw-camp-band-title">' . esc_html( $a['title'] ? $a['title'] : get_the_title( $post ) ) . '</h2>';
		$out .= $a['text'] ? '<p>' . esc_html( $a['text'] ) . '</p>' : '';
		$out .= $window;
		$out .= '<p class="aw-camp-band-actions"><a class="wp-element-button" data-track="home_campaign_click" href="' . esc_url( $url . '#send' ) . '">' . esc_html( $a['button'] ) . '</a> <a class="wp-element-button aw-camp-band-ghost" href="' . esc_url( $url . '#rules' ) . '" data-track="home_campaign_click">' . esc_html( $a['link'] ) . '</a></p>';
		// The artwork comes after the text: it is pinned to the band's bottom below 1200, so placed first it moved down while the
		// browser was still reading the text after the first paint (CLS 0.1 at 768, 24 Oct 2026). Same look: z-index orders them.
		$out .= '</div></div>' . $pic;
		return $out . '</section>';
	}
);

/* ---------- Assets, ads ---------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		$id   = aw_camp_id( get_queried_object_id() );
		$band = is_front_page() && function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign(); // the home hero is the campaign's, open or closed (home.php)
		$page = is_singular( 'aw_campaign' ) && $id && get_post_meta( $id, 'aw_campaign_close', true );
		$sc   = ! is_front_page() && is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw_campaign_band' ); // a band in a page's own content
		$v    = fn( $f ) => (string) filemtime( AW_CORE_DIR . $f );
		$url  = fn( $f ) => plugins_url( 'assets/css/' . $f, AW_CORE_DIR . 'animal-writes-core.php' );
		if ( $sc ) { // the band's own files, after the page's stylesheet
			wp_enqueue_style( 'aw-camp-band', $url( 'aw-camp-band.css' ), array_merge( array( 'aw-camp-tokens' ), $page ? array( 'aw-campaign' ) : array() ), $v( 'assets/css/aw-camp-band.css' ) );
			wp_register_style( 'aw-camp-tokens', $url( 'aw-camp-tokens.css' ), array(), $v( 'assets/css/aw-camp-tokens.css' ) );
		}
		if ( ! $page && ! $band ) {
			return;
		}
		$lab  = is_singular( 'aw_campaign' ) && aw_camp_is_lab_page( $id ); // the Lab rules page: its own copy of both files, as on the morning of 2 Nov 2026 (owner)
		$css  = $lab ? 'campaign-lab.css' : 'campaign.css';
		$js   = $lab ? 'campaign-lab.js' : 'campaign.js';
		if ( $band ) {
			// Home with a campaign hero: the tokens and the band's rules only, inline in campaign.css's place, so nothing
			// blocks the first paint and the cascade order stays the same, before Kits and aw.css (owner, 22 Oct 2026).
			foreach ( array( 'aw-camp-tokens' => array(), 'aw-camp-band' => array( 'aw-camp-tokens' ) ) as $h => $deps ) {
				wp_register_style( $h, false, $deps, $v( 'assets/css/' . $h . '.css' ) );
				wp_enqueue_style( $h );
				$rules = (string) file_get_contents( AW_CORE_DIR . 'assets/css/' . $h . '.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				wp_add_inline_style( $h, trim( (string) preg_replace( '/\s*\n\s*/', "\n", (string) preg_replace( '#/\*.*?\*/#s', '', $rules ) ) ) ); // without the comments and indents (the files hold no strings or url()s)
			}
		} else {
			$deps = array();
			if ( ! $lab ) { // the campaign tokens, just before campaign.css (the Lab page's frozen copy carries its own)
				wp_register_style( 'aw-camp-tokens', $url( 'aw-camp-tokens.css' ), array(), $v( 'assets/css/aw-camp-tokens.css' ) );
				$deps = array( 'aw-camp-tokens' );
			}
			wp_enqueue_style( 'aw-campaign', $url( $css ), $deps, $v( 'assets/css/' . $css ) );
		}
		wp_enqueue_script( 'aw-campaign', plugins_url( 'assets/js/' . $js, AW_CORE_DIR . 'animal-writes-core.php' ), array(), $v( 'assets/js/' . $js ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( is_singular( 'aw_campaign' ) ) { // the Share button and the story/post images (campaign.js)
			$img = fn( $k ) => ( $a = (int) get_post_meta( $id, $k, true ) ) ? (string) wp_get_attachment_url( $a ) : '';
			$txt = (string) get_post_meta( $id, 'aw_campaign_share_text', true );
			// The link preview in the letter share box shows what Facebook, WhatsApp and X show: Rank Math's social title, description and image
			$rm   = function ( $k ) use ( $id ) {
				$v = (string) get_post_meta( $id, $k, true );
				if ( $v && class_exists( '\RankMath\Helper' ) ) {
					$v = \RankMath\Helper::replace_vars( $v, get_post( $id ) );
				}
				return trim( wp_strip_all_tags( $v ) );
			};
			$og_t = $rm( 'rank_math_facebook_title' );
			$og_t = $og_t ? $og_t : ( $rm( 'rank_math_title' ) ? $rm( 'rank_math_title' ) : get_the_title( $id ) );
			$og_d = $rm( 'rank_math_facebook_description' );
			$og_d = $og_d ? $og_d : ( $rm( 'rank_math_description' ) ? $rm( 'rank_math_description' ) : wp_strip_all_tags( get_the_excerpt( $id ) ) );
			$og_i = $img( 'rank_math_facebook_image_id' );
			wp_add_inline_script( 'aw-campaign', 'window.awCampShare=' . wp_json_encode( array( 'url' => get_permalink( $id ), 'short' => aw_camp_short_url( $id ), 'title' => get_the_title( $id ), 'text' => $txt ? $txt : get_the_title( $id ), 'hashtags' => aw_camp_x_hashtags( get_post_meta( $id, 'aw_campaign_share_hashtags', true ) ), 'image' => $img( 'aw_campaign_share_image' ), 'story' => $img( 'aw_campaign_share_story' ), 'post' => $img( 'aw_campaign_share_post' ), 'slug' => get_post_field( 'post_name', $id ), 'og' => array( 'title' => $og_t, 'desc' => $og_d, 'image' => $og_i ? $og_i : (string) get_the_post_thumbnail_url( $id, 'full' ), 'host' => wp_parse_url( home_url(), PHP_URL_HOST ) ), 'close' => aw_date( get_post_meta( $id, 'aw_campaign_close', true ) ), 'closed' => aw_camp_close_ts( $id ) && time() >= aw_camp_close_ts( $id ) ) ) . ';', 'before' );
		}
	}
);

/*
 * A campaign page's largest element on a phone is its hero heading, set in Inter at 800 (Lighthouse mobile, 1 Nov 2026),
 * so phones and tablets fetch Inter alongside the stylesheets instead of after them (the theme preloads it from 1200px).
 * The heading paints sooner, and the poster below it no longer moves when Inter replaces the fallback.
 */
add_action(
	'wp_head',
	function () {
		$mode = function_exists( 'kits_option' ) ? kits_option( 'font_preload' ) : 'wide'; // Kits → Theme Options → Utility → Performance → Font preload.
		if ( ! is_singular( 'aw_campaign' ) || 'off' === $mode ) {
			return;
		}
		$font = function_exists( 'kits_body_font_file' ) ? kits_body_font_file() : '';
		$font = '' !== $font ? $font : get_theme_file_uri( 'assets/fonts/inter/inter-latin.woff2' );
		if ( 'all' !== $mode ) { // "all": the theme already preloads it for every width.
			echo '<link rel="preload" href="' . esc_url( $font ) . '" as="font" type="font/woff2" crossorigin media="(max-width: 1199px)">' . "\n";
		}
		$heavy = aw_camp_inter_file( 800 ); // the heading's own file when the theme's Inter does not cover 800 (Global Styles keep it at 400 to 700)
		$light = false !== strpos( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw-camp--community-dogs' ); // its headings are 700 (campaign-record.css)
		if ( $heavy && $heavy !== $font && ! $light ) {
			echo '<link rel="preload" href="' . esc_url( $heavy ) . '" as="font" type="font/woff2" crossorigin media="(max-width: 1199px)">' . "\n";
		}
	},
	2
);

/**
 * The file the browser uses for upright Inter at this weight, from the font faces as WordPress resolves them: the
 * theme's own file when its declared range covers the weight, else the Font Library's file for that weight. '' when
 * none is found.
 *
 * @param int $weight Font weight, e.g. 800.
 * @return string
 */
function aw_camp_inter_file( $weight ) {
	if ( ! class_exists( 'WP_Font_Face_Resolver' ) ) {
		return '';
	}
	$theme = '';
	$lib   = '';
	foreach ( WP_Font_Face_Resolver::get_fonts_from_theme_json() as $faces ) {
		foreach ( (array) $faces as $f ) {
			if ( 'inter' !== strtolower( trim( (string) ( $f['font-family'] ?? '' ), '"\' ' ) ) || 'normal' !== ( $f['font-style'] ?? 'normal' ) ) {
				continue;
			}
			$src = '';
			foreach ( (array) ( $f['src'] ?? array() ) as $u ) {
				if ( preg_match( '/\.woff2(\?|$)/', (string) $u ) ) {
					$src = (string) $u;
					break;
				}
			}
			$w = array_map( 'intval', preg_split( '/\s+/', trim( (string) ( $f['font-weight'] ?? '400' ) ) ) );
			if ( '' === $src || $weight < min( $w ) || $weight > max( $w ) ) {
				continue;
			}
			if ( false !== strpos( $src, '/uploads/fonts/' ) ) {
				$lib = $lib ? $lib : $src;
			} else {
				$theme = $theme ? $theme : $src;
			}
		}
	}
	return $theme ? $theme : $lib;
}

/* ---------- WP Admin: the campaign boxes and list columns ---------- */
/*
 * Five SCF field groups (acf-json/group_aw_campaign_*.json) are the campaign's boxes in the main column, after Event
 * (group_aw_campaign). Each holds one message field; the box's markup is printed under that field. The inputs are
 * plain post fields, saved by the save_post_aw_campaign handlers below and in campaign-record.php.
 */
const AW_CAMP_BOXES = array(
	'field_aw_campaign_dates_box'    => 'aw_camp_dates_box',
	'field_aw_campaign_document_box' => 'aw_camp_document_box',
	'field_aw_campaign_orgs_box'     => 'aw_camp_orgs_box',
	'field_aw_campaign_outcome_box'  => 'aw_camp_outcome_box',
	'field_aw_campaign_share_box'    => 'aw_camp_share_box',
);
foreach ( AW_CAMP_BOXES as $aw_camp_key => $aw_camp_fn ) {
	add_action(
		'acf/render_field/key=' . $aw_camp_key,
		function () use ( $aw_camp_fn ) {
			$post = get_post();
			if ( $post && 'aw_campaign' === $post->post_type ) {
				$aw_camp_fn( $post );
			}
		}
	);
}
unset( $aw_camp_key, $aw_camp_fn );

/**
 * Are all five campaign groups on? The save below writes every field, so it may only run when every box is on the
 * screen: a group switched off in SCF would otherwise save its fields as empty or default.
 */
function aw_camp_boxes_all_on() {
	if ( ! function_exists( 'acf_get_field_group' ) ) {
		return false;
	}
	foreach ( array( 'dates', 'document', 'orgs', 'outcome', 'share' ) as $g ) {
		$group = acf_get_field_group( 'group_aw_campaign_' . $g );
		if ( ! $group || empty( $group['active'] ) ) {
			return false;
		}
	}
	return true;
}

/** Dates and state: now, opens on, closes on, the override and its reason. Holds the nonce of the save below. */
function aw_camp_dates_box( $post ) {
	if ( aw_camp_boxes_all_on() ) { // without the nonce nothing is saved, so no field is emptied
		wp_nonce_field( 'aw_camp_details', 'aw_camp_details_nonce' );
	}
	$m     = fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
	$state = array( 'pre' => 'Pre (upcoming)', 'active' => 'Active (open)', 'post' => 'Post (closed)' );
	$win   = array( 'upcoming' => 'Upcoming', 'open' => 'Open', 'last-day' => 'Last day', 'closed' => 'Closed' );
	echo '<p><strong>Now:</strong> ' . esc_html( $state[ aw_camp_state( $post->ID ) ] . ', ' . $win[ aw_camp_window( $post->ID ) ] ) . '</p>';
	echo '<p><label for="aw-camp-open"><strong>Opens on</strong> (first day)</label><br><input type="date" id="aw-camp-open" name="aw_campaign_open" value="' . esc_attr( $m( 'aw_campaign_open' ) ) . '"></p>';
	$close = (string) get_post_meta( $post->ID, 'aw_campaign_close', true );
	echo '<p><label for="aw-camp-close"><strong>Closes on</strong> (last day)</label><br><input type="date" id="aw-camp-close" name="aw_campaign_close" value="' . esc_attr( $close ) . '"></p>';
	echo '<p><label for="aw-camp-override"><strong>Override the state</strong></label><br><select id="aw-camp-override" name="aw_campaign_state_override"><option value="">No, follow the dates</option>';
	foreach ( $state as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $m( 'aw_campaign_state_override' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select><br><label for="aw-camp-override-note">Reason (required for an override)</label><input type="text" id="aw-camp-override-note" name="aw_campaign_override_note" value="' . esc_attr( $m( 'aw_campaign_override_note' ) ) . '" class="widefat"></p>';
}

/** Document: the campaign document (Library item ID) and its edit link. */
function aw_camp_document_box( $post ) {
	$doc = (string) get_post_meta( $post->ID, 'aw_campaign_doc', true );
	echo '<p><label for="aw-camp-doc"><strong>Campaign document</strong> (Library item ID)</label><br><input type="number" id="aw-camp-doc" name="aw_campaign_doc" value="' . esc_attr( $doc ) . '" class="small-text">' . ( $doc ? ' <a href="' . esc_url( get_edit_post_link( (int) $doc ) ) . '">' . esc_html( get_the_title( (int) $doc ) ) . '</a>' : '' ) . '</p>';
}

/** Organisations: partners and supporters. */
function aw_camp_orgs_box( $post ) {
	$parts = (string) get_post_meta( $post->ID, 'aw_campaign_partners', true );
	$sups  = (string) get_post_meta( $post->ID, 'aw_campaign_supporters', true );
	echo '<p><label for="aw-camp-partners"><strong>Partners</strong> (Directory listing IDs, comma-separated)</label><br><input type="text" id="aw-camp-partners" name="aw_campaign_partners" value="' . esc_attr( $parts ) . '" class="widefat"></p>';
	echo '<p><label for="aw-camp-supporters"><strong>Supporters</strong> (Directory listing IDs, comma-separated; "Supported by" on the page)</label><br><input type="text" id="aw-camp-supporters" name="aw_campaign_supporters" value="' . esc_attr( $sups ) . '" class="widefat"></p>';
}

/** Outcome: the outcome and its date, then the real-world impact (campaign-record.php). */
function aw_camp_outcome_box( $post ) {
	$m = fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
	echo '<p><label for="aw-camp-outcome"><strong>Outcome</strong> (after closing)</label><br><select id="aw-camp-outcome" name="aw_campaign_outcome">';
	foreach ( array( 'pending' => 'Pending', 'achieved' => 'Achieved', 'partial' => 'Partially achieved', 'not' => 'Not achieved', 'inconclusive' => 'Inconclusive' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $m( 'aw_campaign_outcome' ) ? $m( 'aw_campaign_outcome' ) : 'pending', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select> <label class="screen-reader-text" for="aw-camp-asof">As of</label><input type="date" id="aw-camp-asof" name="aw_campaign_outcome_asof" value="' . esc_attr( $m( 'aw_campaign_outcome_asof' ) ) . '"><br><span class="description">As of: the day the outcome was last reviewed. Don\'t infer success from activity.</span></p>';
	aw_camp_record_box( $post );
}

/** Letters and share: when the count shows, the share line and images, letters sent (read-only) and how the tools work. */
function aw_camp_share_box( $post ) {
	$m    = fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
	$sent = (int) get_post_meta( $post->ID, 'aw_campaign_sent', true );
	echo '<p><label for="aw-camp-min"><strong>Show the count from</strong></label> <input type="number" min="0" id="aw-camp-min" name="aw_campaign_metric_min" value="' . esc_attr( $m( 'aw_campaign_metric_min' ) ? $m( 'aw_campaign_metric_min' ) : '100' ) . '" class="small-text"><br><label for="aw-camp-stale">"As of" date after</label> <input type="number" min="1" id="aw-camp-stale" name="aw_campaign_metric_stale" value="' . esc_attr( $m( 'aw_campaign_metric_stale' ) ? $m( 'aw_campaign_metric_stale' ) : '1' ) . '" class="small-text"> days without a new letter</p>';
	echo '<p><label for="aw-camp-share-text"><strong>Share line</strong> (sent with the link)</label><input type="text" id="aw-camp-share-text" name="aw_campaign_share_text" value="' . esc_attr( $m( 'aw_campaign_share_text' ) ) . '" class="widefat"><br><label for="aw-camp-share-hashtags">Hashtags for X</label><input type="text" id="aw-camp-share-hashtags" name="aw_campaign_share_hashtags" value="' . esc_attr( $m( 'aw_campaign_share_hashtags' ) ) . '" class="widefat"><br><label for="aw-camp-share-image"><strong>Share picture ID (1080×1080)</strong>, one picture for X, Facebook, Instagram, LinkedIn, WhatsApp and stories</label> <input type="number" id="aw-camp-share-image" name="aw_campaign_share_image" value="' . esc_attr( $m( 'aw_campaign_share_image' ) ) . '" class="small-text"><br><span class="description">When set, it replaces the story and post pictures below (older campaigns).</span><br><label for="aw-camp-share-story">Story image ID (1080×1920)</label> <input type="number" id="aw-camp-share-story" name="aw_campaign_share_story" value="' . esc_attr( $m( 'aw_campaign_share_story' ) ) . '" class="small-text"><br><label for="aw-camp-share-post">Post image ID (1080×1350)</label> <input type="number" id="aw-camp-share-post" name="aw_campaign_share_post" value="' . esc_attr( $m( 'aw_campaign_share_post' ) ) . '" class="small-text"></p>';
	$short = aw_camp_short_url( $post->ID );
	echo '<p><label for="aw-camp-short"><strong>Short link</strong></label><br><code>' . esc_html( trailingslashit( home_url() ) ) . '</code><input type="text" id="aw-camp-short" name="aw_campaign_short" value="' . esc_attr( $m( 'aw_campaign_short' ) ) . '" class="regular-text" maxlength="40"><br><span class="description">Letters, digits and hyphens. Redirects to this campaign while it is published; Share and Copy link use it.' . ( $short ? ' <a href="' . esc_url( $short ) . '">' . esc_html( $short ) . '</a>' : '' ) . '</span></p>';
	echo '<p><label for="aw-camp-card-title"><strong>Side card title</strong></label><input type="text" id="aw-camp-card-title" name="aw_campaign_card_title" value="' . esc_attr( $m( 'aw_campaign_card_title' ) ) . '" class="widefat" maxlength="60"><br><span class="description">The Current campaign card on News, guides and Library pages. Empty: the campaign title.</span></p>';
	echo '<p><strong>Letters sent:</strong> ' . esc_html( number_format_i18n( $sent ) ) . '<br><span class="description">"Yes, I sent it" clicks, at most one a minute per visitor. Shown on the page from 100.</span></p>';
	echo '<details><summary>How this campaign works</summary><ul style="list-style:disc;margin-left:1.2em">'
		. '<li><code>[aw_letter_tool]</code>: the letter builder; its grounds are the list with class <code>aw-letter-grounds</code> just before it.</li>'
		. '<li><code>[aw_rule_chip rule="11-3"]</code>: a button that opens that clause of the campaign document.</li>'
		. '<li><code>[aw_campaign_count]</code>, <code>[aw_camp_status]</code>, <code>[aw_sticky_cta]</code>, <code>[aw_campaign_partners]</code>.</li>'
		. '<li>Sections follow the Campaign Content Standard v1.0 (Notion): each is a group whose anchor is its slug (hero, context, why-it-matters, how-to-help, impact, support, faq, news, library, press, partners, next-steps, updates).</li>'
		. '<li><code>[aw_campaign_band campaign="slug"]</code> on the home page: the band while open, "Latest campaign" after.</li>'
		. '<li>Blocks with class <code>aw-camp-open</code> show until the close date; <code>aw-camp-closed</code> after it; <code>aw-camp-pre</code> before the open date. <code>aw-camp-if-count</code> shows once the count is shown; <code>aw-camp-if-filled</code> hides an empty section.</li>'
		. '<li>Preview the other state (logged in): add <code>?aw_camp_preview=closed</code> or <code>=open</code> to the page URL.</li>'
		. '</ul></details>';
}
add_action(
	'save_post_aw_campaign',
	function ( $id ) {
		if ( ! isset( $_POST['aw_camp_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['aw_camp_details_nonce'] ), 'aw_camp_details' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) ) {
			return;
		}
		$close = sanitize_text_field( wp_unslash( $_POST['aw_campaign_close'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_close', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $close ) ? $close : '' );
		update_post_meta( $id, 'aw_campaign_doc', (string) absint( $_POST['aw_campaign_doc'] ?? 0 ) );
		$open = sanitize_text_field( wp_unslash( $_POST['aw_campaign_open'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_open', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $open ) ? $open : '' );
		$out = sanitize_key( wp_unslash( $_POST['aw_campaign_outcome'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_outcome', in_array( $out, array( 'pending', 'achieved', 'partial', 'not', 'inconclusive' ), true ) ? $out : 'pending' );
		$asof = sanitize_text_field( wp_unslash( $_POST['aw_campaign_outcome_asof'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_outcome_asof', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $asof ) ? $asof : '' );
		$note = sanitize_text_field( wp_unslash( $_POST['aw_campaign_override_note'] ?? '' ) );
		$over = sanitize_key( wp_unslash( $_POST['aw_campaign_state_override'] ?? '' ) );
		update_post_meta( $id, 'aw_campaign_state_override', $note && in_array( $over, array( 'pre', 'active', 'post' ), true ) ? $over : '' ); // no reason, no override
		update_post_meta( $id, 'aw_campaign_override_note', $note );
		update_post_meta( $id, 'aw_campaign_metric_min', (string) absint( $_POST['aw_campaign_metric_min'] ?? 100 ) );
		update_post_meta( $id, 'aw_campaign_metric_stale', (string) max( 1, absint( $_POST['aw_campaign_metric_stale'] ?? 1 ) ) );
		update_post_meta( $id, 'aw_campaign_share_text', sanitize_text_field( wp_unslash( $_POST['aw_campaign_share_text'] ?? '' ) ) );
		update_post_meta( $id, 'aw_campaign_share_hashtags', aw_camp_x_hashtags( wp_unslash( $_POST['aw_campaign_share_hashtags'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- aw_camp_x_hashtags() keeps letters, digits and _ only
		update_post_meta( $id, 'aw_campaign_share_image', (string) absint( $_POST['aw_campaign_share_image'] ?? 0 ) );
		update_post_meta( $id, 'aw_campaign_share_story', (string) absint( $_POST['aw_campaign_share_story'] ?? 0 ) );
		update_post_meta( $id, 'aw_campaign_share_post', (string) absint( $_POST['aw_campaign_share_post'] ?? 0 ) );
		update_post_meta( $id, 'aw_campaign_card_title', mb_substr( sanitize_text_field( wp_unslash( $_POST['aw_campaign_card_title'] ?? '' ) ), 0, 60 ) );
		update_post_meta( $id, 'aw_campaign_partners', implode( ',', array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_campaign_partners'] ?? '' ) ) ) ) ) ) );
		update_post_meta( $id, 'aw_campaign_supporters', implode( ',', array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_campaign_supporters'] ?? '' ) ) ) ) ) ) );
	},
	10
);
add_filter(
	'manage_aw_campaign_posts_columns',
	fn( $c ) => array_merge( $c, array( 'aw_camp_close' => 'Closes on', 'aw_camp_sent' => 'Letters sent' ) )
);
add_action(
	'manage_aw_campaign_posts_custom_column',
	function ( $col, $id ) {
		if ( 'aw_camp_close' === $col ) {
			$d = (string) get_post_meta( $id, 'aw_campaign_close', true );
			echo $d ? esc_html( wp_date( AW_DATE_FORMAT, strtotime( $d . ' 12:00' ) ) ) . ( aw_camp_is_open( $id ) ? '' : ' (closed)' ) : '—';
		} elseif ( 'aw_camp_sent' === $col ) {
			echo esc_html( number_format_i18n( (int) get_post_meta( $id, 'aw_campaign_sent', true ) ) );
		}
	},
	10,
	2
);

/* ---------- animal-writes/current-campaign: the sidebar's campaign card (owner, 20 Oct 2026) ---------- */

/** The open campaign closing soonest (0 when none is open). */
function aw_current_campaign_id() {
	$best = 0;
	$at   = PHP_INT_MAX;
	foreach ( get_posts( array( 'post_type' => 'aw_campaign', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids' ) ) as $id ) {
		if ( ! aw_camp_is_open( $id ) ) {
			continue;
		}
		$close = aw_camp_close_ts( $id ) ? aw_camp_close_ts( $id ) : PHP_INT_MAX - 1;
		if ( $close < $at ) {
			$best = (int) $id;
			$at   = $close;
		}
	}
	return $best;
}

/**
 * A card for the current campaign: its picture, title, one or two lines, days left and Take part. Nothing when none is open.
 * After the close date (the campaign still open, waiting for a decision) it says "Decision awaited" and links to what
 * happens next and to the newsletter sign-up on the page (owner, 13 Nov 2026).
 */
function aw_block_current_campaign( $a = array(), $id = 0 ) {
	// sm, the campaign's small card (owner, 13 Nov 2026: every campaign in four sizes, lg, md, sm and share). The block's
	// "campaign" attribute (a campaign's slug, e.g. diwali) shows that campaign; without it, the open campaign closing soonest.
	$slug = sanitize_title( (string) ( $a['campaign'] ?? '' ) );
	$pick = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'aw_campaign' ) : null;
	$c    = '' !== $slug ? ( $pick && 'publish' === $pick->post_status ? (int) $pick->ID : 0 ) : aw_current_campaign_id();
	if ( ! $c || ( is_singular( 'aw_campaign' ) && get_queried_object_id() === $c ) ) {
		return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="aw-block-preview">' . esc_html__( 'Current campaign (shows while a campaign is open)', 'animal-writes-core' ) . '</p>' : '';
	}
	aw_use_component_style( 'aw-single' ); // aw-side-camp
	// The card in the campaign's own colours: its aw-camp--{name} tokens (aw-camp-tokens.css), read from the page's wrapper.
	$theme = preg_match( '/\baw-camp--([a-z0-9-]+)/', (string) get_post_field( 'post_content', $c ), $tm ) ? sanitize_html_class( $tm[1] ) : '';
	if ( '' !== $theme ) {
		wp_enqueue_style( 'aw-camp-tokens', plugins_url( 'assets/css/aw-camp-tokens.css', AW_CORE_DIR . 'animal-writes-core.php' ), array(), (string) filemtime( AW_CORE_DIR . 'assets/css/aw-camp-tokens.css' ) );
	}
	$url   = get_permalink( $c );
	$days  = aw_camp_days_left( $c );
	$close = aw_camp_close_ts( $c );
	$wait  = $close && time() >= $close;
	$img   = get_the_post_thumbnail( $c, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) );
	$hid   = 'aw-side-camp-' . $c;
	$title = trim( (string) get_post_meta( $c, 'aw_campaign_card_title', true ) );
	$title = '' !== $title ? $title : get_the_title( $c );
	/* translators: %d: days. */
	$left = $days ? sprintf( _n( '%d day left', '%d days left', $days, 'animal-writes-core' ), $days ) : ''; // the day count only, no "Last day" status (owner, 2 Nov 2026)
	if ( $wait ) {
		$meta = '<span class="kit-meta-item">' . esc_html__( 'Current campaign', 'animal-writes-core' ) . '</span><span class="aw-side-camp-chip">' . esc_html__( 'Decision awaited', 'animal-writes-core' ) . '</span>';
		$btns = '<div class="aw-side-camp-btns"><a class="kit-btn aw-side-camp-btn" href="' . esc_url( $url . '#context' ) . '">' . esc_html__( 'What happens next', 'animal-writes-core' ) . '</a>'
			. '<a class="kit-btn kit-btn--secondary aw-side-camp-btn" href="' . esc_url( $url . '#newsletter' ) . '">' . esc_html__( 'Get the decision first', 'animal-writes-core' ) . '</a></div>';
	} else {
		$meta = '<span class="kit-meta-item">' . esc_html__( 'Current campaign', 'animal-writes-core' ) . '</span>' . ( '' !== $left ? '<span class="kit-meta-item">' . aw_i( 'clock' ) . esc_html( $left ) . '</span>' : '' );
		$btns = '<a class="kit-btn aw-side-camp-btn" href="' . esc_url( $url ) . '" aria-describedby="' . esc_attr( $hid ) . '">' . esc_html__( 'Take part', 'animal-writes-core' ) . '</a>';
	}
	$class = 'kit-card aw-side-camp' . ( '' !== $theme ? ' aw-camp aw-camp--' . $theme : '' ) . ( $wait ? ' aw-side-camp--wait' : '' );
	return '<section class="' . esc_attr( $class ) . '" aria-labelledby="' . esc_attr( $hid ) . '">'
		. ( $img ? '<div class="aw-side-camp-media">' . $img . '</div>' : '' )
		. '<div class="aw-side-camp-body"><p class="aw-side-camp-meta kit-meta-row">' . $meta . '</p>'
		. '<h2 class="aw-side-camp-title" id="' . esc_attr( $hid ) . '"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h2>'
		. ( has_excerpt( $c ) ? '<p class="aw-side-camp-line">' . esc_html( wp_strip_all_tags( get_the_excerpt( $c ) ) ) . '</p>' : '' )
		. $btns . '</div></section>';
}

/*
 * The lab rules page's Media & PR list ("On social media") shows people's own social media posts only (owner, 21 Oct 2026):
 * Instagram, Facebook, X, YouTube and the like (aw_press_group social or videos), never news articles.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $q, $block = null ) {
		// The post type the block asks for: WordPress drops a type with no public pages (aw_press) from the query vars,
		// and systems.php (priority 10) only puts it back, so the block's own setting is read here (after it, at 15).
		$type = $block instanceof WP_Block ? (string) ( $block->context['query']['postType'] ?? '' ) : (string) ( $q['post_type'] ?? '' );
		// The lab rules page only: the community dogs page keeps its own Media coverage list of articles (31 Oct 2026).
		if ( ! is_singular( 'aw_campaign' ) || 'aw_press' !== $type || 'lab-animals-rules-2026' !== get_post_field( 'post_name', get_queried_object_id() ) ) {
			return $q;
		}
		$q['meta_query']   = isset( $q['meta_query'] ) && is_array( $q['meta_query'] ) ? $q['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$q['meta_query'][] = array( 'key' => 'aw_press_group', 'value' => array( 'social', 'videos' ), 'compare' => 'IN' );
		return $q;
	},
	15,
	2
);

/*
 * Campaign pages' "In the news" lists the campaign's News and its Media & PR news articles together (owner, 24 Oct
 * 2026), newest first; social posts and videos stay in "What's happening". After systems.php (10) has applied the
 * Campaign link filter.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $q ) {
		if ( ! is_singular( 'aw_campaign' ) || 'aw_news' !== ( $q['post_type'] ?? '' ) || empty( $q['tax_query'] ) ) {
			return $q;
		}
		$linked = false;
		foreach ( (array) $q['tax_query'] as $t ) {
			$linked = $linked || ( is_array( $t ) && AW_CAMPAIGN_LINK === ( $t['taxonomy'] ?? '' ) );
		}
		if ( ! $linked ) {
			return $q;
		}
		$q['post_type']    = array( 'aw_news', 'aw_press' );
		$q['post__not_in'] = array_merge( (array) ( $q['post__not_in'] ?? array() ), aw_camp_press_dupes( $q['tax_query'] ) );
		$q['meta_query']   = isset( $q['meta_query'] ) && is_array( $q['meta_query'] ) ? $q['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$q['meta_query'][] = array(
			'relation' => 'OR',
			array( 'key' => 'aw_press_group', 'compare' => 'NOT EXISTS' ), // News
			array( 'key' => 'aw_press_group', 'value' => 'articles' ),
		);
		return $q;
	},
	20
);

/**
 * Media & PR articles that are the same story as a News item of the campaign (same link or same title): left out of
 * "In the news" so a story is listed once, as News.
 */
function aw_camp_press_dupes( $tax_query ) {
	$base  = array( 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids', 'tax_query' => $tax_query, 'suppress_filters' => true ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$norm  = fn( $t ) => trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( html_entity_decode( (string) $t, ENT_QUOTES ) ) ) );
	$news  = get_posts( array( 'post_type' => 'aw_news' ) + $base );
	$urls  = array();
	$names = array();
	foreach ( $news as $id ) {
		$urls[ untrailingslashit( (string) get_post_meta( $id, 'aw_source_url', true ) ) ] = true;
		$names[ $norm( get_the_title( $id ) ) ]                                           = true;
	}
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'aw_press' ) + $base ) as $id ) {
		if ( isset( $urls[ untrailingslashit( (string) get_post_meta( $id, 'aw_press_url', true ) ) ] ) || isset( $names[ $norm( get_the_title( $id ) ) ] ) ) {
			$out[] = $id;
		}
	}
	return $out ? $out : array();
}

/** A press card's title and image link to the coverage, in a new tab (the item has no page of its own). */
foreach ( array( 'core/post-title', 'core/post-featured-image' ) as $aw_blk ) {
	add_filter(
		'render_block_' . $aw_blk,
		function ( $html, $block, $instance ) {
			$id = (int) ( $instance->context['postId'] ?? 0 );
			if ( ! $id || 'aw_press' !== get_post_type( $id ) || str_contains( $html, '<a ' ) || empty( $block['attrs']['isLink'] ) ) {
				return $html;
			}
			$url = (string) get_post_meta( $id, 'aw_press_url', true );
			if ( ! $url ) {
				return $html;
			}
			$a = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">';
			// Title: link the heading's text. Image: link the figure's image.
			return preg_replace( array( '#(<h[1-6][^>]*>)(.*?)(</h[1-6]>)#s', '#(<figure[^>]*>)(.*?)(</figure>)#s' ), '$1' . $a . '$2</a>$3', $html, 1 );
		},
		99, // after the fallback image (stock-photo and logo rules) is filled in
		3
	);
}

/** A Media & PR item has no page of its own: its link is the coverage itself (cards in "In the news"). */
add_filter(
	'post_type_link',
	function ( $link, $post ) {
		if ( 'aw_press' === $post->post_type ) {
			$url = (string) get_post_meta( $post->ID, 'aw_press_url', true );
			return $url ? $url : $link;
		}
		return $link;
	},
	10,
	2
);

/**
 * A campaign's hashtags for the X link: comma-separated, each without # and only letters, digits and _ (what X takes).
 *
 * @param mixed $raw Stored or posted value.
 * @return string
 */
function aw_camp_x_hashtags( $raw ) {
	$tags = array_filter( array_map( fn( $t ) => preg_replace( '/[^A-Za-z0-9_]/', '', (string) $t ), explode( ',', (string) $raw ) ), 'strlen' );
	return implode( ',', array_values( array_unique( $tags ) ) );
}

// One share kit per page (owner, 13 Nov 2026): every share carries #AnimalWrites first (brand hashtag first, 4 Nov);
// a campaign page adds its own share line and hashtags. The page shares itself; its picture is the link preview's (owner,
// 13 Nov 2026: "share function on the page is only about sharing page (campaign hero)").
add_filter(
	'kits_share_args',
	function ( $args ) {
		$tags = array( 'AnimalWrites' );
		if ( is_singular( 'aw_campaign' ) ) {
			$id   = get_queried_object_id();
			$line = (string) get_post_meta( $id, 'aw_campaign_share_text', true );
			$tags = array_merge( $tags, explode( ',', aw_camp_x_hashtags( get_post_meta( $id, 'aw_campaign_share_hashtags', true ) ) ) );
			if ( '' === (string) $args['text'] && '' !== $line ) {
				$args['text'] = $line;
			}
		}
		$args['hashtags'] = array_merge( $tags, (array) $args['hashtags'] );
		return $args;
	}
);

/**
 * The "What's happening" timeline (ol.aw-camp-dates) knows which dates have passed (owner, 13 Nov 2026): each item whose
 * date ("30 Sep 2026") is before today gets is-done, the first one still to come gets is-next; aw-camp-marks.css draws
 * the nodes from them. Items whose date can't be read keep their classes. No text changes.
 *
 * @param string $html The rendered content.
 * @return string
 */
function aw_camp_dates_state( $html ) {
	if ( false === strpos( $html, 'aw-camp-dates' ) ) {
		return $html;
	}
	$today = current_time( 'Y-m-d' );
	return (string) preg_replace_callback(
		'#(<ol\b[^>]*class="[^"]*\baw-camp-dates\b[^"]*"[^>]*>)(.*?)(</ol>)#s',
		function ( $list ) use ( $today ) {
			$next = false;
			$body = preg_replace_callback(
				'#<li\b([^>]*)>(.*?)</li>#s',
				function ( $li ) use ( $today, &$next ) {
					// The date: the <time datetime> inside the item's date (aw_date), else the date's own words.
					if ( preg_match( '#<time\b[^>]*\bdatetime="([0-9]{4}-[0-9]{2}-[0-9]{2})#', $li[2], $when ) ) {
						$time = strtotime( $when[1] );
					} elseif ( preg_match( '#class="[^"]*\baw-camp-dates-when\b[^"]*"[^>]*>(.*?)</span>\s*<span[^>]*\baw-camp-dates-what\b#s', $li[2], $when ) ) {
						$time = strtotime( trim( wp_strip_all_tags( html_entity_decode( $when[1], ENT_QUOTES ) ) ) );
					} else {
						return $li[0];
					}
					if ( false === $time ) {
						return $li[0];
					}
					$add = gmdate( 'Y-m-d', $time ) < $today ? 'is-done' : '';
					if ( '' === $add && ! $next ) {
						$add  = 'is-next';
						$next = true;
					}
					if ( '' === $add ) {
						return $li[0];
					}
					$attrs = preg_match( '#\bclass="#', $li[1] ) ? preg_replace( '#\bclass="#', 'class="' . $add . ' ', $li[1], 1 ) : $li[1] . ' class="' . $add . '"';
					return '<li' . $attrs . '>' . $li[2] . '</li>';
				},
				$list[2]
			);
			return $list[1] . $body . $list[3];
		},
		$html
	);
}
add_filter( 'the_content', fn( $html ) => is_singular( 'aw_campaign' ) ? aw_camp_dates_state( $html ) : $html, 98 );

// The newsletter band on a campaign page takes the campaign's colours (owner, 13 Nov 2026): the campaign's own class
// (aw-camp--<slug>, the first one in the page's content) on the band, so its --camp-* tokens apply there; aw.css maps
// them. Everywhere else the band keeps the brand colours.
add_filter(
	'render_block_core/template-part',
	function ( $html, $block ) {
		if ( 'newsletter' !== ( $block['attrs']['slug'] ?? '' ) || ! is_singular( 'aw_campaign' ) ) {
			return $html;
		}
		if ( ! preg_match( '/\baw-camp--([a-z0-9-]+)/', (string) get_post_field( 'post_content', get_queried_object_id() ), $m ) ) {
			return $html;
		}
		return (string) preg_replace( '/\baw-nl-band\b/', 'aw-nl-band aw-nl-band--camp aw-camp--' . sanitize_html_class( $m[1] ), $html, 1 );
	},
	10,
	2
);

/**
 * Our own social accounts for "Follow us" on page Share sections (owner, 13 Nov 2026), the same as the footer's. Never
 * the old @animalwrites_india or /AnimalWritesIndia (not ours). Filter aw_follow_accounts.
 *
 * @return array<int,array{0:string,1:string,2:string}> [label, url, icon]
 */
function aw_follow_accounts() {
	return (array) apply_filters(
		'aw_follow_accounts',
		array(
			array( 'Instagram', 'https://www.instagram.com/animal_writes_/', 'instagram' ),
			array( 'Facebook', 'https://www.facebook.com/profile.php?id=61596537146561', 'facebook' ),
			array( 'X', 'https://x.com/animal_writes_', 'x-brand' ),
			array( 'Threads', 'https://www.threads.com/@animal_writes_', 'threads' ),
			array( 'LinkedIn', 'https://www.linkedin.com/company/animalwrites/', 'linkedin' ),
		)
	);
}

// A campaign's Share section: the site's one share bar from the server, the one on listings and posts (owner, 2 Nov
// 2026), on every campaign, with or without a letter tool.
add_filter(
	'the_content',
	function ( $html ) {
		if ( ! is_singular( 'aw_campaign' ) || ! function_exists( 'aw_share_html' ) || false === strpos( $html, 'data-aw-share' ) ) {
			return $html;
		}
		$short = aw_camp_short_url( get_the_ID() ); // the short link, when the campaign has one (campaign-short.php)
		$bar   = $short && function_exists( 'kits_share_html' ) ? kits_share_html( $short, html_entity_decode( get_the_title(), ENT_QUOTES ), null, (string) get_post_field( 'post_name', get_the_ID() ), array( 'follow' => aw_follow_accounts() ) ) : aw_share_html( get_the_ID() );
		if ( ! $short && function_exists( 'kits_follow_html' ) ) {
			$bar .= kits_follow_html( aw_follow_accounts() ); // Follow us under the bar (owner, 13 Nov 2026)
		}
		return preg_replace_callback(
			'#(<div class="aw-camp-share-kit"[^>]*\sdata-aw-share[^>]*>)\s*(</div>)#',
			function ( $m ) use ( $bar ) {
				return $m[1] . $bar . $m[2];
			},
			$html,
			1
		);
	},
	99
);

/**
 * The Supreme Court stray dogs campaign page: the state tracker's cells marked (aw_sc_tracker_marks); the court orders and state
 * tracker tables keep their table roles, which some browsers drop once the rows are shown as cards on phones
 * (campaign.css). The news row, which scrolls sideways, can take keyboard focus and is named by the heading just before
 * it (which gets an id when it has none); campaign.js takes the focus off it while it fits (phones: stacked).
 *
 * @param string $html The rendered content.
 * @return string
 */
function aw_sc_rows_a11y( $html ) {
	if ( false === strpos( $html, 'aw-camp--sc-stray-dogs' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$html  = aw_sc_tracker_marks( $html );
	$p     = new WP_HTML_Tag_Processor( $html );
	$head  = '';
	$n     = 0;
	$table = false; // inside one of the two tables
	$roles = array( 'TABLE' => 'table', 'THEAD' => 'rowgroup', 'TBODY' => 'rowgroup', 'TR' => 'row', 'TH' => 'columnheader', 'TD' => 'cell' );
	while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $p->get_tag();
		if ( $p->is_tag_closer() ) {
			$table = 'TABLE' === $tag ? false : $table;
			continue;
		}
		if ( 'H2' === $tag || 'H3' === $tag ) {
			$id = (string) $p->get_attribute( 'id' );
			if ( '' === $id ) {
				$id = 'aw-sc-h-' . ( ++$n );
				$p->set_attribute( 'id', $id );
			}
			$head = $id;
		} elseif ( 'FIGURE' === $tag && ( $p->has_class( 'aw-camp-timeline' ) || $p->has_class( 'aw-camp-tracker' ) ) ) {
			$table = true;
		} elseif ( $table && isset( $roles[ $tag ] ) ) {
			$p->set_attribute( 'role', $roles[ $tag ] );
		} elseif ( 'DIV' === $tag && $p->has_class( 'aw-camp-row' ) && '' !== $head ) {
			$p->set_attribute( 'data-aw-sc-row', '' );
			$p->set_attribute( 'tabindex', '0' ); // the news row: focusable, so the arrow keys scroll it (Layout rules 11)
			$p->set_attribute( 'aria-labelledby', $head );
		}
	}
	return $p->get_updated_html();
}

/**
 * The state tracker's cells marked for the card and table styles (campaign.css, "Where your state stands"); nothing is
 * folded or reworded. In each order cell (the third) the date it opens with gets aw-sc-lead (the date pill) and a
 * closing <strong> ("Due: ..." or "Next: ...") gets aw-sc-due (its own line); a state cell that lists several states
 * gets aw-sc-states (set as text, not as a name).
 *
 * @param string $html The rendered content.
 * @return string
 */
function aw_sc_tracker_marks( $html ) {
	// One class added to an opening tag, with or without a class attribute.
	$add  = function ( $tag, $name ) {
		return preg_match( '#\sclass="#', $tag ) ? (string) preg_replace( '#\sclass="#', ' class="' . $name . ' ', $tag, 1 ) : (string) preg_replace( '#^<(\w+)#', '<$1 class="' . $name . '"', $tag, 1 );
	};
	$cell = function ( $tr ) use ( $add ) {
		$n = 0;
		return preg_replace_callback(
			'#(<td\b[^>]*>)(.*?)(</td>)#s',
			function ( $td ) use ( &$n, $add ) {
				++$n;
				$open = $td[1];
				$body = $td[2];
				if ( 1 === $n && false !== strpos( wp_strip_all_tags( $body ), ',' ) ) {
					$open = $add( $open, 'aw-sc-states' );
				} elseif ( 3 === $n ) {
					$body = (string) preg_replace( '#^(\s*)<span class="kit-date aw-date"#', '$1<span class="kit-date aw-date aw-sc-lead"', $body, 1 );
					$body = (string) preg_replace( '#<strong>((?:(?!<strong\b).)*)</strong>(\s*)$#s', '<strong class="aw-sc-due">$1</strong>$2', $body, 1 );
				}
				return $open . $body . $td[3];
			},
			$tr[0]
		);
	};
	return (string) preg_replace_callback(
		'#(<figure\b[^>]*\baw-camp-tracker\b[^>]*>)(.*?)(</figure>)#s',
		function ( $f ) use ( $cell ) {
			$rows = fn( $tb ) => preg_replace_callback( '#<tr\b[^>]*>.*?</tr>#s', $cell, $tb[0] );
			return $f[1] . (string) preg_replace_callback( '#<tbody\b.*?</tbody>#s', $rows, $f[2] ) . $f[3];
		},
		$html
	);
}

add_filter(
	'the_content',
	function ( $html ) {
		return is_singular( 'aw_campaign' ) ? aw_sc_rows_a11y( $html ) : $html;
	},
	100
);
