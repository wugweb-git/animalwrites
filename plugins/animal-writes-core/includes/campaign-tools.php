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
 *
 * Shortcodes:
 *   [aw_rule_chip rule="11-3" label="Rule 11(3)" allows="..."]  a chip that opens the clause, word for word
 *   [aw_letter_tool to="..." subject="..." ticked="1,2,6"]       letter builder; its grounds are the list with
 *                                                                class aw-letter-grounds just before it
 *   [aw_campaign_count min="100"]                                "N letters sent" once N reaches min
 *   [aw_camp_status]                                             the hero's status: action window, and the outcome
 *   [aw_sticky_cta href="#send" label="..."]                     phone-only bar with the days left
 *   [aw_campaign_band campaign="slug" theme="lab-rules"]       home band; shown only while the campaign is
 *                                                                published and open
 * Blocks with class aw-camp-open show only while the campaign is open (Active); aw-camp-closed only after it closes
 * (Post); aw-camp-pre only before it opens. aw-camp-if-count shows once the count reaches the threshold;
 * aw-camp-if-filled hides a section that has nothing in it (e.g. no partners).
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
 * The switch at closing time, cache-safe: while a campaign is open, its page (and the home page with its band) is cached
 * only until the moment it closes (LiteSpeed TTL), and a one-off cron event purges the whole cache at that moment.
 */
function aw_camp_cache_until( $id ) {
	$ts = aw_camp_open_ts( $id ) > time() ? aw_camp_open_ts( $id ) : aw_camp_close_ts( $id );
	if ( $ts && time() < $ts ) {
		do_action( 'litespeed_control_set_ttl', max( 60, $ts - time() ) );
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
add_action(
	'admin_init',
	function () { // campaigns published before this existed: schedule once
		if ( get_option( 'aw_camp_close_scheduled' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'aw_campaign', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $cid ) {
			aw_camp_schedule_close( $cid );
		}
		update_option( 'aw_camp_close_scheduled', 1, false );
	}
);

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
			return '<span class="aw-rule-chip aw-rule-chip--plain">' . esc_html( $label ) . '</span>';
		}
		++$n;
		$dlg  = 'aw-rule-' . $n;
		$link = 'publish' === get_post_status( $doc ) ? get_permalink( $doc ) . '#' . $anchor : (string) get_post_meta( $doc, 'aw_source_url', true );
		$btn  = '<button type="button" class="aw-rule-chip" aria-haspopup="dialog" aria-controls="' . esc_attr( $dlg ) . '" data-rule="' . esc_attr( $a['rule'] ) . '">' . esc_html( $label ) . '</button>';
		$out  = '<dialog class="aw-rule-chip-dialog" id="' . esc_attr( $dlg ) . '" aria-labelledby="' . esc_attr( $dlg ) . '-t">';
		$out .= '<p class="aw-rule-chip-head" id="' . esc_attr( $dlg ) . '-t"><strong>' . esc_html( $label ) . ': what it says</strong></p>';
		$out .= '<div class="aw-rule-chip-text">' . $clause . '</div>';
		if ( '' !== $a['allows'] ) {
			$out .= '<p class="aw-rule-chip-head"><strong>What it allows</strong></p><p>' . esc_html( $a['allows'] ) . '</p>';
		}
		$out .= '<p class="aw-rule-chip-src">' . esc_html( get_the_title( $doc ) ) . ( $link ? ' · <a href="' . esc_url( $link ) . '">Read the full text</a>' : '' ) . '</p>';
		$out .= '<button type="button" class="aw-rule-chip-close" data-close>Close</button></dialog>';
		aw_camp_dialogs( $out ); // printed at the end of the page: a dialog holds paragraphs, so it cannot sit inside one
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
			'to'      => $to,
			'subject' => $a['subject'],
			'ticked'  => array_map( 'intval', array_filter( explode( ',', $a['ticked'] ) ) ),
			'address' => $a['address'],
			'salute'  => $a['salute'],
			'intro'   => $a['intro'],
			'closing' => $a['closing'],
			'sign'    => $a['sign'],
			'confirm' => $id ? rest_url( 'aw/v1/campaign/' . $id . '/sent' ) : '',
			'letter'  => $id ? rest_url( 'aw/v1/campaign/' . $id . '/letter' ) : '', // anonymous statistics (campaign-letters.php)
			'optin'   => $id ? rest_url( 'aw/v1/campaign/' . $id . '/optin' ) : '',
			'source'  => $a['source'] ? $a['source'] : ( $id ? 'campaign:' . get_post_field( 'post_name', $id ) : '' ),
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
		<p class="aw-letter-field"><label for="aw-letter-own">Add a line in your own words <span class="aw-letter-hint">(optional, up to 300 characters)</span></label>
			<textarea id="aw-letter-own" name="own" maxlength="300" rows="3"></textarea></p>
		</div>
	</div>
	<div class="aw-letter-out">
		<p class="aw-letter-head"><span class="aw-letter-step">3</span>Check and send</p>
		<p class="aw-letter-meta"><span class="aw-letter-to"><?php echo aw_i( 'mail' ); ?><?php echo esc_html( $to ); ?></span><span><strong>Subject:</strong> <?php echo esc_html( $a['subject'] ); ?></span></p>
		<div class="aw-letter-preview" aria-live="polite" tabindex="0" aria-label="Your letter"></div>
		<p class="aw-letter-optin"><input type="checkbox" id="aw-letter-optin" name="optin" value="1"> <label for="aw-letter-optin"><?php echo esc_html( defined( 'AW_CL_OPTIN' ) ? AW_CL_OPTIN : '' ); ?></label></p>
		<p class="aw-letter-field aw-letter-email" hidden><label for="aw-letter-email">Your email <span aria-hidden="true">*</span></label>
			<input id="aw-letter-email" name="email" type="email" autocomplete="email" maxlength="190"></p>
		<p class="aw-letter-error" role="alert" hidden>Add your name and city first.</p>
		<div class="aw-letter-routes">
			<button type="button" class="wp-element-button" data-route="gmail"><?php echo aw_i( 'send' ); ?>Send with Gmail</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="outlook">Outlook</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="mailto">Email app</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="copy">Copy letter</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-route="copy-address">Copy address</button>
		</div>
		<p class="aw-letter-copied" role="status"></p>
		<div class="aw-letter-confirm" hidden>
			<p><strong>Did you send it?</strong></p>
			<button type="button" class="wp-element-button" data-confirm="yes">Yes, I sent it</button>
			<button type="button" class="wp-element-button aw-letter-alt" data-confirm="no">Not yet</button>
		</div>
		<div class="aw-letter-thanks" hidden tabindex="-1">
			<p><strong>Thank you.</strong> Ask one more person to send theirs.</p>
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
 * prints the campaign's partners, Directory listings named in aw_campaign_partners (IDs, comma-separated), as cards
 * linking to their profiles; with no partners it prints nothing, heading included.
 */
add_shortcode(
	'aw_campaign_partners',
	function ( $atts ) {
		$a   = shortcode_atts( array( 'title' => 'Partners' ), $atts );
		$id  = aw_camp_id();
		$ids = $id ? array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $id, 'aw_campaign_partners', true ) ) ) ) : array();
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
		$a    = shortcode_atts( array( 'campaign' => '', 'title' => '', 'text' => '', 'button' => 'Send your objection', 'link' => 'Read what the rules say', 'theme' => '' ), $atts );
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
		$out = '<section class="aw-camp aw-camp-band' . ( $a['theme'] ? ' aw-camp--' . sanitize_html_class( $a['theme'] ) : '' ) . '" aria-labelledby="aw-camp-band-t"' . $data . '>';
		$out .= do_shortcode( '[aw_countdown date="' . esc_attr( $day ) . '" label="Last day"]' );
		$out .= '<h2 id="aw-camp-band-t">' . esc_html( $a['title'] ? $a['title'] : get_the_title( $post ) ) . '</h2>';
		$out .= $a['text'] ? '<p>' . esc_html( $a['text'] ) . '</p>' : '';
		$out .= '<p class="aw-camp-band-actions"><a class="wp-element-button" data-track="home_campaign_click" href="' . esc_url( $url . '#send' ) . '">' . esc_html( $a['button'] ) . '</a> <a class="wp-element-button aw-camp-band-ghost" href="' . esc_url( $url . '#rules' ) . '" data-track="home_campaign_click">' . esc_html( $a['link'] ) . '</a></p>';
		return $out . '</section>';
	}
);

/* ---------- Assets, ads ---------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		$id   = aw_camp_id( get_queried_object_id() );
		$band = is_front_page() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw_campaign_band' );
		if ( ! ( is_singular( 'aw_campaign' ) && $id && get_post_meta( $id, 'aw_campaign_close', true ) ) && ! $band ) {
			return;
		}
		$v = fn( $f ) => (string) filemtime( AW_CORE_DIR . $f );
		wp_enqueue_style( 'aw-campaign', plugins_url( 'assets/css/campaign.css', AW_CORE_DIR . 'animal-writes-core.php' ), array(), $v( 'assets/css/campaign.css' ) );
		wp_enqueue_script( 'aw-campaign', plugins_url( 'assets/js/campaign.js', AW_CORE_DIR . 'animal-writes-core.php' ), array(), $v( 'assets/js/campaign.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

/* ---------- WP Admin: Campaign details box and list columns ---------- */
add_action(
	'add_meta_boxes_aw_campaign',
	fn() => add_meta_box( 'aw-campaign-details', 'Campaign details', 'aw_camp_details_box', 'aw_campaign', 'side', 'high' )
);

/** The box: dates, status, campaign document, partners, letters sent (read-only) and how the tools work. */
function aw_camp_details_box( $post ) {
	wp_nonce_field( 'aw_camp_details', 'aw_camp_details_nonce' );
	$m     = fn( $k ) => (string) get_post_meta( $post->ID, $k, true );
	$state = array( 'pre' => 'Pre (upcoming)', 'active' => 'Active (open)', 'post' => 'Post (closed)' );
	$win   = array( 'upcoming' => 'Upcoming', 'open' => 'Open', 'last-day' => 'Last day', 'closed' => 'Closed' );
	echo '<p><strong>Now:</strong> ' . esc_html( $state[ aw_camp_state( $post->ID ) ] . ', ' . $win[ aw_camp_window( $post->ID ) ] ) . '</p>';
	echo '<p><label for="aw-camp-open"><strong>Opens on</strong> (first day)</label><br><input type="date" id="aw-camp-open" name="aw_campaign_open" value="' . esc_attr( $m( 'aw_campaign_open' ) ) . '"></p>';
	$close = (string) get_post_meta( $post->ID, 'aw_campaign_close', true );
	$doc   = (string) get_post_meta( $post->ID, 'aw_campaign_doc', true );
	$parts = (string) get_post_meta( $post->ID, 'aw_campaign_partners', true );
	$sent  = (int) get_post_meta( $post->ID, 'aw_campaign_sent', true );
	echo '<p><label for="aw-camp-close"><strong>Closes on</strong> (last day)</label><br><input type="date" id="aw-camp-close" name="aw_campaign_close" value="' . esc_attr( $close ) . '"></p>';
	echo '<p><label for="aw-camp-doc"><strong>Campaign document</strong> (Library item ID)</label><br><input type="number" id="aw-camp-doc" name="aw_campaign_doc" value="' . esc_attr( $doc ) . '" class="small-text">' . ( $doc ? ' <a href="' . esc_url( get_edit_post_link( (int) $doc ) ) . '">' . esc_html( get_the_title( (int) $doc ) ) . '</a>' : '' ) . '</p>';
	echo '<p><label for="aw-camp-partners"><strong>Partners</strong> (Directory listing IDs, comma-separated)</label><br><input type="text" id="aw-camp-partners" name="aw_campaign_partners" value="' . esc_attr( $parts ) . '" class="widefat"></p>';
	echo '<p><label for="aw-camp-outcome"><strong>Outcome</strong> (after closing)</label><br><select id="aw-camp-outcome" name="aw_campaign_outcome">';
	foreach ( array( 'pending' => 'Pending', 'achieved' => 'Achieved', 'partial' => 'Partially achieved', 'not' => 'Not achieved', 'inconclusive' => 'Inconclusive' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $m( 'aw_campaign_outcome' ) ? $m( 'aw_campaign_outcome' ) : 'pending', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select> <label class="screen-reader-text" for="aw-camp-asof">As of</label><input type="date" id="aw-camp-asof" name="aw_campaign_outcome_asof" value="' . esc_attr( $m( 'aw_campaign_outcome_asof' ) ) . '"><br><span class="description">As of: the day the outcome was last reviewed. Don\'t infer success from activity.</span></p>';
	echo '<p><label for="aw-camp-override"><strong>Override the state</strong></label><br><select id="aw-camp-override" name="aw_campaign_state_override"><option value="">No, follow the dates</option>';
	foreach ( $state as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $m( 'aw_campaign_state_override' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select><br><label for="aw-camp-override-note">Reason (required for an override)</label><input type="text" id="aw-camp-override-note" name="aw_campaign_override_note" value="' . esc_attr( $m( 'aw_campaign_override_note' ) ) . '" class="widefat"></p>';
	echo '<p><label for="aw-camp-min"><strong>Show the count from</strong></label> <input type="number" min="0" id="aw-camp-min" name="aw_campaign_metric_min" value="' . esc_attr( $m( 'aw_campaign_metric_min' ) ? $m( 'aw_campaign_metric_min' ) : '100' ) . '" class="small-text"><br><label for="aw-camp-stale">"As of" date after</label> <input type="number" min="1" id="aw-camp-stale" name="aw_campaign_metric_stale" value="' . esc_attr( $m( 'aw_campaign_metric_stale' ) ? $m( 'aw_campaign_metric_stale' ) : '1' ) . '" class="small-text"> days without a new letter</p>';
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
		update_post_meta( $id, 'aw_campaign_partners', implode( ',', array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['aw_campaign_partners'] ?? '' ) ) ) ) ) ) );
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

// No ads on a page with a letter tool (a form page with one job).
add_filter( 'kits_ads_skip', fn( $skip ) => $skip || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw_letter_tool' ) ) );
