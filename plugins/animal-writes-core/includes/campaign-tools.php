<?php
/**
 * Campaign tools for an objection or letter campaign (first used by Campaign 2.0, the draft lab animal rules 2026).
 * All wording lives in the campaign's own content, so it is edited in the block editor; the code adds behaviour only.
 *
 * Campaign settings (post meta on the aw_campaign item):
 *   aw_campaign_close  Y-m-d  the last day for action (IST); the day after, the page shows its "closed" state
 *   aw_campaign_doc    int    the Library item whose clauses the rule chips quote
 *
 * Shortcodes:
 *   [aw_rule_chip rule="11-3" label="Rule 11(3)" allows="..."]  a chip that opens the clause, word for word
 *   [aw_letter_tool to="..." subject="..." ticked="1,2,6"]       letter builder; its grounds are the list with
 *                                                                class aw-letter-grounds just before it
 *   [aw_campaign_count min="100"]                                "N letters sent" once N reaches min
 *   [aw_sticky_cta href="#send" label="..."]                     phone-only bar with the days left
 *   [aw_campaign_band campaign="slug" theme="lab-rules"]       home band; shown only while the campaign is
 *                                                                published and open
 * Blocks with class aw-camp-open show only while the campaign is open; aw-camp-closed only after it closes.
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

/** Whether the campaign is open (no close date counts as open). Filterable for checking the closed state. */
function aw_camp_is_open( $id ) {
	$ts = aw_camp_close_ts( $id );
	return (bool) apply_filters( 'aw_camp_is_open', ! $ts || time() < $ts, $id );
}

/** Whole days left, counting today (5 Nov when it is 16 Oct: 21). */
function aw_camp_days_left( $id ) {
	$ts = aw_camp_close_ts( $id );
	return $ts ? max( 0, (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS ) ) : 0;
}

/* ---------- Open / closed blocks ---------- */
add_filter(
	'render_block',
	function ( $html, $block ) {
		$cls = (string) ( $block['attrs']['className'] ?? '' );
		if ( ! preg_match( '/\baw-camp-(open|closed)\b/', $cls, $m ) ) {
			return $html;
		}
		$id = aw_camp_id();
		if ( ! $id ) {
			return $html;
		}
		$open = aw_camp_is_open( $id );
		return ( 'open' === $m[1] ) === $open ? $html : '';
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
		);
		ob_start();
		?>
<form class="aw-letter" data-letter="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" novalidate>
	<div class="aw-letter-inputs">
		<fieldset class="aw-letter-grounds-pick">
			<legend>Choose your grounds</legend>
			<div class="aw-letter-ground-list"></div>
		</fieldset>
		<p class="aw-letter-field"><label for="aw-letter-name">Your name <span aria-hidden="true">*</span></label>
			<input id="aw-letter-name" name="name" type="text" autocomplete="name" required maxlength="80"></p>
		<p class="aw-letter-field"><label for="aw-letter-city">Your city <span aria-hidden="true">*</span></label>
			<input id="aw-letter-city" name="city" type="text" autocomplete="address-level2" required maxlength="60"></p>
		<p class="aw-letter-field"><label for="aw-letter-own">Add a line in your own words <span class="aw-letter-hint">(optional, up to 300 characters)</span></label>
			<textarea id="aw-letter-own" name="own" maxlength="300" rows="3"></textarea></p>
	</div>
	<div class="aw-letter-out">
		<p class="aw-letter-meta"><strong>To:</strong> <?php echo esc_html( $to ); ?><br><strong>Subject:</strong> <?php echo esc_html( $a['subject'] ); ?></p>
		<div class="aw-letter-preview" aria-live="polite" tabindex="0" aria-label="Your letter"></div>
		<p class="aw-letter-error" role="alert" hidden>Add your name and city first.</p>
		<div class="aw-letter-routes">
			<button type="button" class="wp-element-button" data-route="gmail">Send with Gmail</button>
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
					return array( 'count' => $n );
				},
			)
		);
	}
);

add_shortcode(
	'aw_campaign_count',
	function ( $atts ) {
		$a  = shortcode_atts( array( 'min' => 100, 'label' => 'objections sent' ), $atts );
		$id = aw_camp_id();
		$n  = $id ? (int) get_post_meta( $id, 'aw_campaign_sent', true ) : 0;
		return $n >= (int) $a['min'] ? '<p class="aw-camp-count"><strong>' . esc_html( number_format_i18n( $n ) ) . '</strong> ' . esc_html( $a['label'] ) . '</p>' : '';
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
		return '<div class="aw-sticky-cta" data-target="' . esc_attr( $a['href'] ) . '"><a class="wp-element-button" href="' . esc_attr( $a['href'] ) . '">' . esc_html( $a['label'] ) . '</a><span class="aw-sticky-cta-days">' . esc_html( sprintf( _n( '%d day left', '%d days left', $d, 'animal-writes-core' ), $d ) ) . '</span></div>';
	}
);

/* ---------- Home band ---------- */
add_shortcode(
	'aw_campaign_band',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'campaign' => '', 'title' => '', 'text' => '', 'button' => 'Send your objection', 'link' => 'Read what the rules say', 'theme' => '' ), $atts );
		$post = $a['campaign'] ? get_page_by_path( sanitize_title( $a['campaign'] ), OBJECT, 'aw_campaign' ) : null;
		if ( ! $post || 'publish' !== $post->post_status || ! aw_camp_is_open( $post->ID ) ) {
			return '';
		}
		$url = get_permalink( $post );
		$day = (string) get_post_meta( $post->ID, 'aw_campaign_close', true );
		$out = '<section class="aw-camp aw-camp-band' . ( $a['theme'] ? ' aw-camp--' . sanitize_html_class( $a['theme'] ) : '' ) . '" aria-labelledby="aw-camp-band-t">';
		$out .= do_shortcode( '[aw_countdown date="' . esc_attr( $day ) . '" label="Last day"]' );
		$out .= '<h2 id="aw-camp-band-t">' . esc_html( $a['title'] ? $a['title'] : get_the_title( $post ) ) . '</h2>';
		$out .= $a['text'] ? '<p>' . esc_html( $a['text'] ) . '</p>' : '';
		$out .= '<p class="aw-camp-band-actions"><a class="wp-element-button" data-track="home_campaign_click" href="' . esc_url( $url . '#send' ) . '">' . esc_html( $a['button'] ) . '</a> <a href="' . esc_url( $url . '#rules' ) . '" data-track="home_campaign_click">' . esc_html( $a['link'] ) . '</a></p>';
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

// No ads on a page with a letter tool (a form page with one job).
add_filter( 'kits_ads_skip', fn( $skip ) => $skip || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw_letter_tool' ) ) );
