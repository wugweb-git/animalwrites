<?php
/**
 * Campaign tools for an objection or letter campaign (first used by Campaign 2.0, the draft lab animal rules 2026).
 * All wording lives in the campaign's own content, so it is edited in the block editor; the code adds behaviour only.
 *
 * Campaign settings (post meta on the aw_campaign item), per the Campaign Content Standard v1.0 (Notion, 17 Oct 2026):
 *   aw_campaign_open           Y-m-d  the first day for action (IST); before it the campaign is Pre (Upcoming)
 *   aw_campaign_close          Y-m-d  the last day for action (IST); from 00:00 IST the day after, it is Post (Closed)
 *   aw_campaign_doc            int    the Library item whose clauses the rule chips quote
 *   aw_campaign_outcome        Post only: pending|achieved|partial|not|inconclusive (pending until reviewed)
 *   aw_campaign_outcome_asof   Y-m-d  when the outcome was last reviewed (set to the closing day if empty)
 *   aw_campaign_state_override pre|active|post, with aw_campaign_override_note (the reason; required)
 *   aw_campaign_metric_min     the count is shown from this number (default 100)
 *   aw_campaign_metric_stale   days after the last letter before the count shows an "as of" date (default 1)
 *   aw_campaign_share_text     the line shared with the link (Share button, WhatsApp, X); default: the title
 *   aw_campaign_share_story    attachment ID: 1080x1920 image for Instagram/Facebook/WhatsApp stories (owner-approved)
 *   aw_campaign_share_post     attachment ID: 1080x1080 image for feed posts (owner-approved)
 *
 * Shortcodes:
 *   [aw_rule_chip rule="11-3" label="Rule 11(3)" allows="..."]  a chip that opens the clause, word for word
 *   [aw_letter_tool to="..." subject="..." ticked="1,2,6"]       letter builder; its grounds are the list with
 *                                                                class aw-letter-grounds just before it
 *   [aw_campaign_count min="100"]                                "N letters sent" once N reaches min
 *   [aw_camp_status]                                             the hero's status: action window, and the outcome
 *   [aw_sticky_cta href="#send" label="..."]                     phone-only bar with the days left
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

/** Lifecycle state from the dates (pre, active or post); a manual override with a reason wins. Not filtered. */
function aw_camp_state( $id ) {
	$over = (string) get_post_meta( $id, 'aw_campaign_state_override', true );
	if ( in_array( $over, array( 'pre', 'active', 'post' ), true ) && '' !== trim( (string) get_post_meta( $id, 'aw_campaign_override_note', true ) ) ) {
		return $over;
	}
	$open  = aw_camp_open_ts( $id );
	$close = aw_camp_close_ts( $id );
	if ( $open && time() < $open ) {
		return 'pre';
	}
	return $close && time() >= $close ? 'post' : 'active';
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
 * only until the moment it closes (LiteSpeed TTL), and a one-off cron event purges the whole cache at that moment. The
 * TTL is also capped at the next midnight in India, so the countdown's day count never stays a day behind in the cache.
 */
function aw_camp_cache_until( $id ) {
	$ts = aw_camp_open_ts( $id ) > time() ? aw_camp_open_ts( $id ) : aw_camp_close_ts( $id );
	if ( $ts && time() < $ts ) {
		$midnight = ( new DateTimeImmutable( 'tomorrow', new DateTimeZone( 'Asia/Kolkata' ) ) )->getTimestamp();
		do_action( 'litespeed_control_set_ttl', max( 60, min( $ts, $midnight ) - time() ) );
	}
}
function aw_camp_schedule_close( $id ) {
	$ts = aw_camp_close_ts( $id );
	wp_clear_scheduled_hook( 'aw_camp_closed', array( (int) $id ) );
	if ( $ts && time() < $ts && 'publish' === get_post_status( $id ) ) {
		wp_schedule_single_event( $ts + 5, 'aw_camp_closed', array( (int) $id ) );
	}
}
add_action( 'save_post_aw_campaign', fn( $id ) => aw_camp_schedule_close( $id ), 20 );
add_action( 'updated_post_meta', fn( $mid, $id, $key ) => 'aw_campaign_close' === $key && aw_camp_schedule_close( $id ), 10, 3 );
add_action(
	'aw_camp_closed',
	function ( $id = 0 ) {
		if ( $id && ! get_post_meta( $id, 'aw_campaign_outcome_asof', true ) ) { // outcome Pending, as of the closing day
			update_post_meta( $id, 'aw_campaign_outcome_asof', wp_date( 'Y-m-d', aw_camp_close_ts( $id ), new DateTimeZone( 'Asia/Kolkata' ) ) );
		}
		do_action( 'litespeed_purge_all' );
	}
);
// Campaigns published before the closing job existed were scheduled once; that step ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

/* ---------- Open / closed blocks ---------- */
add_filter(
	'render_block',
	function ( $html, $block ) {
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		if ( preg_match( '/(^|\s)aw-camp(\s|$)/', $cls ) && aw_camp_id() ) { // the page wrapper: is-closed lets CSS reorder sections
			aw_camp_cache_until( aw_camp_id() );
			return aw_camp_is_open( aw_camp_id() ) ? $html : preg_replace( '/^(\s*<[^>]*?class=")([^"]*)"/', '$1$2 is-closed"', $html, 1 ); // appended to the wrapper's own class list
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
		$out .= '<p class="aw-rule-chip-src">' . esc_html( get_the_title( $doc ) ) . ( $link ? ' <a href="' . esc_url( $link ) . '">Read the full text</a>' : '' ) . '</p>';
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
	<div class="aw-letter-inputs">
		<fieldset class="aw-letter-grounds-pick">
			<legend><span class="aw-letter-step">1</span>Choose your grounds <span class="aw-letter-picked" aria-live="polite"></span></legend>
			<div class="aw-letter-ground-list"></div>
		</fieldset>
		<div class="aw-letter-sign">
		<p class="aw-letter-head"><span class="aw-letter-step">2</span>Sign it</p>
		<p class="aw-letter-field"><label for="aw-letter-name">Your name <span aria-hidden="true">*</span></label>
			<input id="aw-letter-name" name="name" type="text" autocomplete="name" required maxlength="80"></p>
		<p class="aw-letter-field"><label for="aw-letter-city">Your city <span aria-hidden="true">*</span></label>
			<input id="aw-letter-city" name="city" type="text" autocomplete="address-level2" required maxlength="60"></p>
		<p class="aw-letter-field aw-letter-email"><label for="aw-letter-email">Your email <span aria-hidden="true">*</span></label>
			<input id="aw-letter-email" name="email" type="email" autocomplete="email" required maxlength="190"></p>
		<p class="aw-letter-field"><label for="aw-letter-own">Add a line in your own words <span class="aw-letter-hint">(optional, up to 300 characters)</span></label>
			<textarea id="aw-letter-own" name="own" maxlength="300" rows="3"></textarea></p>
		</div>
	</div>
	<div class="aw-letter-out">
		<p class="aw-letter-head"><span class="aw-letter-step">3</span>Check and send</p>
		<p class="aw-letter-meta"><span class="aw-letter-to"><?php echo aw_i( 'mail' ); ?><?php echo esc_html( $to ); ?></span><span><strong>Subject:</strong> <?php echo esc_html( $a['subject'] ); ?></span></p>
		<div class="aw-letter-preview" aria-live="polite" tabindex="0" aria-label="Your letter"></div>
		<p class="aw-letter-optin"><input type="checkbox" id="aw-letter-optin" name="optin" value="1" checked> <label for="aw-letter-optin"><?php echo esc_html( defined( 'AW_CL_OPTIN' ) ? AW_CL_OPTIN : '' ); ?></label></p>
		<p class="aw-letter-field"><label for="aw-letter-wa">WhatsApp number <span class="aw-letter-hint">(optional)</span></label>
			<input id="aw-letter-wa" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" maxlength="16"></p>
		<p class="aw-letter-optin"><input type="checkbox" id="aw-letter-wa-optin" name="wa_optin" value="1" checked> <label for="aw-letter-wa-optin">OK to contact me on WhatsApp.</label></p>
		<p class="aw-letter-error" role="alert" hidden>Add your name, city and email first.</p>
		<?php echo function_exists( 'kits_altcha_widget' ) ? str_replace( '<div class="kit-captcha', '<div data-placed class="kit-captcha', kits_altcha_widget( 'invisible' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in kits_altcha_widget; the sign-up's bot check (campaign.js) ?>
		<div class="aw-letter-routes">
			<button type="button" class="wp-element-button" data-route="gmail"><?php echo aw_i( 'brand-gmail' ); ?>Send with Gmail</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="mailto"><?php echo aw_i( 'mail' ); ?>Email app</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="copy"><?php echo aw_i( 'copy' ); ?>Copy letter</button>
		</div>
		<p class="aw-letter-copied" role="status"></p>
		<div class="aw-letter-confirm" hidden>
			<p><strong>Did you send it?</strong> Check your sent folder. We count your objection once you say it went.</p>
			<button type="button" class="wp-element-button" data-confirm="yes">Yes, I sent it</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-confirm="no">Not yet</button>
		</div>
		<div class="aw-letter-thanks" hidden tabindex="-1">
			<p class="aw-letter-thanks-head"><?php echo aw_i( 'check' ); ?><strong>Thank you. Your objection is on its way to CCSEA.</strong></p>
			<p>Keep the sent email: it is your record. CCSEA may reply to the address you sent from.</p>
			<p class="aw-letter-ref" hidden>Your reference: <strong></strong></p>
		</div>
	</div>
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
		$win   = aw_camp_window( $id );
		$names = array( 'upcoming' => 'Upcoming', 'open' => 'Open', 'last-day' => 'Last day', 'closed' => 'Closed' );
		$out   = '<p class="aw-camp-status"><span class="aw-camp-status-window is-' . esc_attr( $win ) . '">' . esc_html( $names[ $win ] ) . '</span>';
		if ( 'closed' === $win ) {
			list( $key, $label, $asof ) = aw_camp_outcome( $id );
			$out .= '<span class="aw-camp-status-outcome is-' . esc_attr( $key ) . '">Outcome: ' . esc_html( $label ) . '</span>';
			$out .= $asof ? '<span class="aw-camp-status-asof">As of ' . aw_date( $asof ) . '</span>' : '';
		}
		return $out . '</p>';
	}
);

/* ---------- Phone bar ---------- */
add_shortcode(
	'aw_sticky_cta',
	function ( $atts ) {
		$a  = shortcode_atts( array( 'href' => '#send', 'label' => 'Send your objection' ), $atts );
		$id = aw_camp_id();
		if ( ! $id || ! aw_camp_is_open( $id ) ) {
			return '';
		}
		$d = aw_camp_days_left( $id );
		return '<div class="aw-sticky-cta" data-target="' . esc_attr( $a['href'] ) . '"><a class="wp-element-button" href="' . esc_attr( $a['href'] ) . '">' . esc_html( $a['label'] ) . '</a><span class="aw-sticky-cta-days">' . esc_html( $d ? sprintf( _n( '%d day to go', '%d days to go', $d, 'animal-writes-core' ), $d ) : 'Last day today' ) . '</span></div>';
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

/** The home band's campaign that campaign-home.css was generated for (tools/campaign-home-css). */
const AW_CAMP_HOME_CSS_FOR = 'lab-animals-rules-2026';

/* ---------- Assets, ads ---------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		$id   = aw_camp_id( get_queried_object_id() );
		$band = is_front_page() && function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign(); // the home hero is the campaign's, open or closed (home.php)
		if ( ! ( is_singular( 'aw_campaign' ) && $id && get_post_meta( $id, 'aw_campaign_close', true ) ) && ! $band ) {
			return;
		}
		$v    = fn( $f ) => (string) filemtime( AW_CORE_DIR . $f );
		$slug = $band ? (string) get_post_field( 'post_name', AW_HOME_CAMPAIGN ) : '';
		if ( $band && ! is_singular( 'aw_campaign' ) && AW_CAMP_HOME_CSS_FOR === $slug && is_readable( AW_CORE_DIR . 'assets/css/campaign-home.css' ) ) {
			// Home: only the band's rules (campaign-home.css; the campaign hero uses them), inline in campaign.css's place, so nothing blocks the first paint
			// and the cascade order stays the same (owner, 22 Oct 2026). Any other campaign in the band: the full file.
			wp_register_style( 'aw-campaign', false, array(), $v( 'assets/css/campaign-home.css' ) );
			wp_enqueue_style( 'aw-campaign' );
			wp_add_inline_style( 'aw-campaign', (string) file_get_contents( AW_CORE_DIR . 'assets/css/campaign-home.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			wp_enqueue_style( 'aw-campaign', plugins_url( 'assets/css/campaign.css', AW_CORE_DIR . 'animal-writes-core.php' ), array(), $v( 'assets/css/campaign.css' ) );
		}
		wp_enqueue_script( 'aw-campaign', plugins_url( 'assets/js/campaign.js', AW_CORE_DIR . 'animal-writes-core.php' ), array(), $v( 'assets/js/campaign.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
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
			wp_add_inline_script( 'aw-campaign', 'window.awCampShare=' . wp_json_encode( array( 'url' => get_permalink( $id ), 'title' => get_the_title( $id ), 'text' => $txt ? $txt : get_the_title( $id ), 'story' => $img( 'aw_campaign_share_story' ), 'post' => $img( 'aw_campaign_share_post' ), 'slug' => get_post_field( 'post_name', $id ), 'og' => array( 'title' => $og_t, 'desc' => $og_d, 'image' => $og_i ? $og_i : $img( 'aw_campaign_share_post' ), 'host' => wp_parse_url( home_url(), PHP_URL_HOST ) ), 'close' => aw_date( get_post_meta( $id, 'aw_campaign_close', true ) ) ) ) . ';', 'before' );
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
		if ( ! is_singular( 'aw_campaign' ) ) {
			return;
		}
		$font = get_theme_file_uri( 'assets/fonts/inter/inter-latin.woff2' );
		echo '<link rel="preload" href="' . esc_url( $font ) . '" as="font" type="font/woff2" crossorigin media="(max-width: 1199px)">' . "\n";
		$heavy = aw_camp_inter_file( 800 ); // the heading's own file when the theme's Inter does not cover 800 (Global Styles keep it at 400 to 700)
		if ( $heavy && $heavy !== $font ) {
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
	echo '<p><label for="aw-camp-share-text"><strong>Share line</strong> (sent with the link)</label><input type="text" id="aw-camp-share-text" name="aw_campaign_share_text" value="' . esc_attr( $m( 'aw_campaign_share_text' ) ) . '" class="widefat"><br><label for="aw-camp-share-story">Story image ID (1080×1920)</label> <input type="number" id="aw-camp-share-story" name="aw_campaign_share_story" value="' . esc_attr( $m( 'aw_campaign_share_story' ) ) . '" class="small-text"><br><label for="aw-camp-share-post">Post image ID (1080×1080)</label> <input type="number" id="aw-camp-share-post" name="aw_campaign_share_post" value="' . esc_attr( $m( 'aw_campaign_share_post' ) ) . '" class="small-text"></p>';
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
		update_post_meta( $id, 'aw_campaign_share_story', (string) absint( $_POST['aw_campaign_share_story'] ?? 0 ) );
		update_post_meta( $id, 'aw_campaign_share_post', (string) absint( $_POST['aw_campaign_share_post'] ?? 0 ) );
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

/** A card for the current campaign: its picture, title, one or two lines, days left and Take part. Nothing when none is open. */
function aw_block_current_campaign( $a = array(), $id = 0 ) {
	$c = aw_current_campaign_id();
	if ( ! $c || ( is_singular( 'aw_campaign' ) && get_queried_object_id() === $c ) ) {
		return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="aw-block-preview">' . esc_html__( 'Current campaign (shows while a campaign is open)', 'animal-writes-core' ) . '</p>' : '';
	}
	$url  = get_permalink( $c );
	$days = aw_camp_days_left( $c );
	$img  = get_the_post_thumbnail( $c, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) );
	$hid  = 'aw-side-camp-' . $c;
	/* translators: %d: days. */
	$left = 'last-day' === aw_camp_window( $c ) ? __( 'Last day', 'animal-writes-core' ) : ( $days ? sprintf( _n( '%d day left', '%d days left', $days, 'animal-writes-core' ), $days ) : '' );
	return '<section class="kit-card aw-side-camp" aria-labelledby="' . esc_attr( $hid ) . '">'
		. ( $img ? '<div class="aw-side-camp-media">' . $img . '</div>' : '' )
		. '<div class="aw-side-camp-body"><p class="aw-side-camp-meta kit-meta-row"><span class="kit-meta-item">' . esc_html__( 'Current campaign', 'animal-writes-core' ) . '</span>' . ( '' !== $left ? '<span class="kit-meta-item">' . aw_i( 'clock' ) . esc_html( $left ) . '</span>' : '' ) . '</p>'
		. '<h2 class="aw-side-camp-title" id="' . esc_attr( $hid ) . '"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $c ) ) . '</a></h2>'
		. ( has_excerpt( $c ) ? '<p class="aw-side-camp-line">' . esc_html( wp_strip_all_tags( get_the_excerpt( $c ) ) ) . '</p>' : '' )
		. '<a class="kit-btn aw-side-camp-btn" href="' . esc_url( $url ) . '" aria-describedby="' . esc_attr( $hid ) . '">' . esc_html__( 'Take part', 'animal-writes-core' ) . '</a></div></section>';
}

/*
 * The lab rules page's Media & PR list ("On social media") shows people's own social media posts only (owner, 21 Oct 2026):
 * Instagram, Facebook, X, YouTube and the like (aw_press_group social or videos), never news articles.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $q ) {
		// The lab rules page only: the community dogs page keeps its own Media coverage list of articles (31 Oct 2026).
		if ( ! is_singular( 'aw_campaign' ) || 'aw_press' !== ( $q['post_type'] ?? '' ) || 'lab-animals-rules-2026' !== get_post_field( 'post_name', get_queried_object_id() ) ) {
			return $q;
		}
		$q['meta_query']   = isset( $q['meta_query'] ) && is_array( $q['meta_query'] ) ? $q['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$q['meta_query'][] = array( 'key' => 'aw_press_group', 'value' => array( 'social', 'videos' ), 'compare' => 'IN' );
		return $q;
	}
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
