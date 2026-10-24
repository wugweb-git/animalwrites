<?php
/**
 * Check list tab and the monthly live Google check (plan §5.4). Nothing here searches Google from the server: the
 * owner runs the check in their own browser with a bookmarklet on google.com.
 *
 * - The list (option aw_si_checklist, keyword record IDs) starts from the keyword map's P1/P2, brand terms, the
 *   current quick wins and the 130 queries of the 21 Oct rankings; the owner adds and removes keywords.
 * - "Start live check" makes a one-time token (2 hours) and shows the bookmarklet. On google.com the bookmarklet opens
 *   a small Search Insights window (this site, logged in), which hands it the keywords not yet checked this month. The
 *   bookmarklet loads each search (gl=in, hl=en, num=20, pws=0) in a hidden frame on google.com, reads our position
 *   in the top 20, the top 3 domains and "People also ask", and posts the result back to the window, which saves it.
 *   It waits 8–15 seconds between searches and stops on Google's "unusual traffic" page; the next click resumes.
 * - Results are stored in obs as src live, period month, d = the day of the check: position (value, txt "#n" or
 *   ">20"), ours (how many of our links in the top 20; 2 or more on a brand search = sitelinks), top3, paa, url.
 *   Nothing is sent to GA4.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_LIVE_TOKEN = 'aw_si_live_token';
const AW_SI_LIVE_TTL   = 2 * HOUR_IN_SECONDS;

add_filter(
	'aw_si_tabs',
	function ( $tabs ) {
		$tabs['checklist'] = __( 'Check list', 'aw-seo-extras' );
		return $tabs;
	},
	6
);

/** First day of the current month (Y-m-d): a keyword with a live result from this date on is checked this month. */
function aw_si_live_month(): string {
	return wp_date( 'Y-m-01' );
}

/**
 * The check list's keyword record IDs. Built from the rules the first time.
 *
 * @return int[]
 */
function aw_si_checklist(): array {
	$ids = get_option( 'aw_si_checklist', null );
	if ( ! is_array( $ids ) ) {
		$ids = aw_si_checklist_defaults();
		update_option( 'aw_si_checklist', $ids, false );
	}
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * The starting list: keyword map P1/P2, brand terms, quick wins, and the 21 Oct rankings queries (live results in the
 * seed). At most 300 keywords.
 *
 * @return int[]
 */
function aw_si_checklist_defaults(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k   = aw_si_table( 'keywords' );
	$o   = aw_si_table( 'obs' );
	$eff = "IF(priority_override <> '', priority_override, priority)";
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names and constants only.
	$ids = array_merge(
		(array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$k} WHERE roles & %d AND {$eff} IN ('P1', 'P2') AND entity = 0", AW_SI_ROLE_TRACKED ) ),
		(array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$k} WHERE roles & %d", AW_SI_ROLE_BRAND ) ),
		(array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$k} WHERE pos28 BETWEEN 8 AND 20 AND impr28 >= %d AND entity = 0 ORDER BY impr28 DESC LIMIT 100", AW_SI_QUICK_MIN ) ),
		(array) $wpdb->get_col( "SELECT DISTINCT kw_id FROM {$o} WHERE src = 'live' AND metric = 'position' AND kw_id > 0" )
	);
	// phpcs:enable
	return array_slice( array_values( array_unique( array_map( 'intval', $ids ) ) ), 0, 300 );
}

/**
 * Keywords of the list still to check this month, as [id, query].
 *
 * @return array<int, array{id: int, q: string}>
 */
function aw_si_live_pending(): array {
	global $wpdb;
	$ids = aw_si_checklist();
	if ( ! $ids || ! aw_si_ready() ) {
		return array();
	}
	$k    = aw_si_table( 'keywords' );
	$o    = aw_si_table( 'obs' );
	$in   = implode( ',', $ids );
	$done = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT kw_id FROM {$o} WHERE src = 'live' AND metric = 'position' AND period = 'month' AND d >= %s AND kw_id IN ({$in})", aw_si_live_month() ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	$out  = array();
	foreach ( (array) $wpdb->get_results( "SELECT id, label FROM {$k} WHERE id IN ({$in}) ORDER BY id" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
		if ( ! isset( $done[ (int) $r->id ] ) && false === strpos( $r->label, '{' ) ) { // Directory patterns such as {district} are not searchable.
			$out[] = array(
				'id' => (int) $r->id,
				'q'  => (string) $r->label,
			);
		}
	}
	return $out;
}

/** Make a new live-check token (valid 2 hours) and return it. Only its hash is stored. */
function aw_si_live_token_new(): string {
	$token = wp_generate_password( 32, false );
	update_option(
		AW_SI_LIVE_TOKEN,
		array(
			'hash'    => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
			'expires' => time() + AW_SI_LIVE_TTL,
			'user'    => get_current_user_id(),
		),
		false
	);
	return $token;
}

/**
 * Whether a token is the current, unexpired one, for the user who made it.
 *
 * @param string $token Token from the request.
 */
function aw_si_live_token_ok( string $token ): bool {
	$t = get_option( AW_SI_LIVE_TOKEN );
	return is_array( $t ) && '' !== $token && time() < (int) $t['expires'] && get_current_user_id() === (int) $t['user']
		&& hash_equals( (string) $t['hash'], hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) );
}

/**
 * The bookmarklet for a token. It runs on google.com in the owner's browser.
 *
 * @param string $token Live-check token.
 */
function aw_si_bookmarklet( string $token ): string {
	$win  = add_query_arg(
		array(
			'page'  => AW_SI_SLUG,
			'tab'   => 'checklist',
			'live'  => 1,
			'token' => $token,
		),
		admin_url( 'tools.php' )
	);
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', $host );
	// phpcs:disable Generic.Strings.UnnecessaryStringConcat -- readable JavaScript.
	$js = '(()=>{if(!/\.google\./.test(location.hostname)){alert("Open google.com first, then click this again.");return;}'
		. 'const W=' . wp_json_encode( $win ) . ',H=' . wp_json_encode( $host ) . ',O=new URL(W).origin;'
		. 'const w=window.open(W,"awsilive","width=560,height=680");if(!w){alert("Allow pop-ups for Google, then click again.");return;}'
		. 'let q=[],stop=false;'
		. 'const sleep=t=>new Promise(r=>setTimeout(r,t));'
		. 'const one=s=>new Promise(res=>{const f=document.createElement("iframe");f.style.cssText="position:fixed;left:-10000px;top:0;width:1280px;height:2400px";'
		. 'f.src="/search?q="+encodeURIComponent(s)+"&gl=in&hl=en&num=20&pws=0";'
		. 'f.onload=()=>setTimeout(()=>{let r;try{const d=f.contentDocument,u=f.contentWindow.location.href;'
		. 'if(u.includes("/sorry/")||/unusual traffic/i.test(d.body.innerText)){r={captcha:true};}else{'
		. 'const L=[...d.querySelectorAll("#search a h3")].map(h=>h.closest("a")).filter(a=>a&&/^https?:/.test(a.href)).map(a=>a.href).filter(h=>!/(^|\.)google\./.test(new URL(h).hostname));'
		. 'const D=L.map(h=>new URL(h).hostname.replace(/^www\./,""));let p=null,n=0,url="";'
		. 'D.forEach((h,j)=>{if(h===H||h.endsWith("."+H)){n++;if(p===null){p=j+1;url=L[j];}}});'
		. 'r={pos:p!==null&&p<=20?p:null,ours:n,url:url,top3:[...new Set(D)].slice(0,3),paa:[...d.querySelectorAll("[data-q]")].map(x=>x.getAttribute("data-q")).filter(Boolean).slice(0,6)};}'
		. '}catch(e){r={error:String(e)};}f.remove();res(r);},1500);document.body.appendChild(f);});'
		. 'const run=async()=>{for(let i=0;i<q.length&&!stop;i++){const r=await one(q[i].q);'
		. 'if(r.captcha){w.postMessage({type:"aw-si-captcha"},O);return;}'
		. 'w.postMessage({type:"aw-si-result",id:q[i].id,r:r},O);if(i<q.length-1)await sleep(8000+Math.random()*7000);}'
		. 'if(!stop)w.postMessage({type:"aw-si-done"},O);};'
		. 'window.addEventListener("message",e=>{if(e.origin!==O)return;const d=e.data||{};'
		. 'if(d.type==="aw-si-list"&&!q.length){q=d.queries||[];run();}if(d.type==="aw-si-stop")stop=true;});'
		. '})();';
	// phpcs:enable
	return 'javascript:' . rawurlencode( $js );
}

/**
 * Save one live result (from the window the bookmarklet opened). Logged-in owner + nonce + live token.
 */
function aw_si_live_save(): void {
	check_ajax_referer( 'aw_si_live', 'nonce' );
	if ( ! current_user_can( aw_si_cap() ) || ! aw_si_live_token_ok( isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '' ) ) {
		wp_send_json_error( array( 'message' => __( 'The live-check token has expired. Start a new live check.', 'aw-seo-extras' ) ), 403 );
	}
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id || ! in_array( $id, aw_si_checklist(), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Not on the check list.', 'aw-seo-extras' ) ), 400 );
	}
	$pos   = isset( $_POST['pos'] ) && '' !== $_POST['pos'] ? absint( $_POST['pos'] ) : 0;
	$pos   = $pos >= 1 && $pos <= 20 ? $pos : 0;
	$ours  = isset( $_POST['ours'] ) ? min( 20, absint( $_POST['ours'] ) ) : 0;
	$top3  = isset( $_POST['top3'] ) ? array_slice( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['top3'] ) ), 0, 3 ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per item.
	$paa   = isset( $_POST['paa'] ) ? array_slice( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['paa'] ) ), 0, 6 ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per item.
	$url   = isset( $_POST['url'] ) ? aw_si_path( esc_url_raw( wp_unslash( $_POST['url'] ) ) ) : '';
	$month = wp_date( 'Y-m-d' ); // The day of the check, so it is newer than any earlier check (the seed's is 22 Oct).
	$ok    = aw_si_obs_put(
		$id,
		'',
		'live',
		'position',
		'month',
		$month,
		array(
			'value' => $pos ? (float) $pos : null,
			'txt'   => $pos ? '#' . $pos : '>20',
		)
	);
	$ok    = $ok && aw_si_obs_put( $id, '', 'live', 'ours', 'month', $month, array( 'value' => (float) $ours ) );
	$ok    = $ok && aw_si_obs_put( $id, '', 'live', 'top3', 'month', $month, array( 'txt' => implode( ', ', $top3 ) ) );
	$ok    = $ok && aw_si_obs_put( $id, '', 'live', 'paa', 'month', $month, array( 'txt' => implode( ' | ', $paa ) ) );
	if ( '' !== $url ) {
		$ok = $ok && aw_si_obs_put( $id, '', 'live', 'url', 'month', $month, array( 'txt' => $url ) );
	}
	if ( ! $ok ) {
		wp_send_json_error( array( 'message' => __( 'The result could not be saved.', 'aw-seo-extras' ) ), 500 );
	}
	aw_si_run_ok(
		'live',
		array(
			'rows'  => count( aw_si_checklist() ) - count( aw_si_live_pending() ),
			'up_to' => wp_date( 'Y-m-d' ),
		)
	);
	wp_send_json_success( array( 'pending' => count( aw_si_live_pending() ) ) );
}
add_action( 'wp_ajax_aw_si_live_save', 'aw_si_live_save' );

add_action(
	'aw_si_load_tab_checklist',
	function () {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Keywords per page', 'aw-seo-extras' ),
				'default' => 50,
				'option'  => 'aw_si_checklist_per_page',
			)
		);
		require_once __DIR__ . '/class-aw-si-checklist-table.php';
		aw_si_checklist_handle();
	}
);

add_filter(
	'set_screen_option_aw_si_checklist_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

/** Changes from the Check list tab: add keywords, remove (row or bulk), rebuild from the rules, start a live check. */
function aw_si_checklist_handle(): void {
	$action = '';
	foreach ( array( 'aw_si_cl', 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked below before any change.
		if ( in_array( $v, array( 'remove', 'add', 'rebuild', 'start' ), true ) ) {
			$action = $v;
			break;
		}
	}
	if ( ! $action ) {
		return;
	}
	check_admin_referer( 'bulk-checklist' );
	if ( ! current_user_can( aw_si_cap() ) ) {
		wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
	}
	$list = aw_si_checklist();
	$msg  = '';
	if ( 'remove' === $action ) {
		$ids  = array_map( 'intval', (array) wp_unslash( $_REQUEST['kw'] ?? array() ) );
		$list = array_values( array_diff( $list, $ids ) );
		$msg  = 'removed';
	} elseif ( 'add' === $action ) {
		$lines = preg_split( '/[\r\n]+/', isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '' );
		$ids   = aw_si_kw_ids( array_filter( array_map( 'trim', (array) $lines ) ), AW_SI_ROLE_TRACKED );
		$list  = array_values( array_unique( array_merge( $list, array_map( 'intval', $ids ) ) ) );
		$msg   = 'added';
	} elseif ( 'rebuild' === $action ) {
		$list = aw_si_checklist_defaults();
		$msg  = 'rebuilt';
	} elseif ( 'start' === $action ) {
		set_transient( 'aw_si_live_new_' . get_current_user_id(), aw_si_live_token_new(), 10 * MINUTE_IN_SECONDS );
		$msg = 'started';
	}
	update_option( 'aw_si_checklist', array_slice( $list, 0, 500 ), false );
	wp_safe_redirect( add_query_arg( 'cl', $msg, aw_si_url( 'checklist' ) ) );
	exit;
}

/** The small window the bookmarklet opens: hands over the keywords to check, saves each result, shows progress. */
function aw_si_live_window(): void {
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- the live token is checked here.
	if ( ! aw_si_live_token_ok( $token ) ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'This live-check link has expired or is not yours. Start a new live check on the Check list tab.', 'aw-seo-extras' ) . '</p></div>';
		return;
	}
	$pending = aw_si_live_pending();
	echo '<h2>' . esc_html__( 'Live Google check', 'aw-seo-extras' ) . '</h2>';
	/* translators: %d: keywords left to check this month. */
	echo '<p id="aw-si-live-status">' . esc_html( sprintf( __( '%d keywords to check this month. Keep this window and the Google tab open.', 'aw-seo-extras' ), count( $pending ) ) ) . '</p>';
	echo '<p><button type="button" class="button" id="aw-si-live-stop">' . esc_html__( 'Stop', 'aw-seo-extras' ) . '</button></p>';
	echo '<ol id="aw-si-live-log"></ol>';
	$cfg = array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'aw_si_live' ),
		'token'   => $token,
		'queries' => $pending,
		'google'  => (array) apply_filters( 'aw_si_live_origins', array( 'https://www.google.com', 'https://www.google.co.in' ) ),
		'text'    => array(
			/* translators: %d: keywords left. */
			'left'    => __( '%d left.', 'aw-seo-extras' ),
			'done'    => __( 'All done for this month. You can close this window.', 'aw-seo-extras' ),
			'captcha' => __( 'Google asked for a check ("unusual traffic"), so the run stopped. Solve it or wait a few hours, then click the bookmarklet again: it carries on where it stopped.', 'aw-seo-extras' ),
			'stopped' => __( 'Stopped. Click the bookmarklet again to carry on.', 'aw-seo-extras' ),
			'nopos'   => __( 'not in the top 20', 'aw-seo-extras' ),
			'failed'  => __( 'not saved', 'aw-seo-extras' ),
		),
	);
	wp_print_inline_script_tag(
		'(function(c){var left=c.queries.length,log=document.getElementById("aw-si-live-log"),st=document.getElementById("aw-si-live-status");'
		. 'function say(t){st.textContent=t;}'
		. 'function line(t){var li=document.createElement("li");li.textContent=t;log.insertBefore(li,log.firstChild);}'
		. 'function send(m){if(!window.opener)return;c.google.forEach(function(o){try{window.opener.postMessage(m,o);}catch(e){}});}'
		. 'var names={};c.queries.forEach(function(q){names[q.id]=q.q;});'
		. 'if(!left){say(c.text.done);}else{send({type:"aw-si-list",queries:c.queries});}'
		. 'document.getElementById("aw-si-live-stop").addEventListener("click",function(){send({type:"aw-si-stop"});say(c.text.stopped);});'
		. 'window.addEventListener("message",function(e){if(c.google.indexOf(e.origin)<0)return;var d=e.data||{};'
		. 'if(d.type==="aw-si-captcha"){say(c.text.captcha);return;}'
		. 'if(d.type==="aw-si-done"){say(c.text.done);return;}'
		. 'if(d.type!=="aw-si-result"||!names[d.id])return;var r=d.r||{};'
		. 'var b=new URLSearchParams();b.append("action","aw_si_live_save");b.append("nonce",c.nonce);b.append("token",c.token);b.append("id",d.id);'
		. 'b.append("pos",r.pos||"");b.append("ours",r.ours||0);b.append("url",r.url||"");'
		. '(r.top3||[]).forEach(function(x){b.append("top3[]",x);});(r.paa||[]).forEach(function(x){b.append("paa[]",x);});'
		. 'fetch(c.ajax,{method:"POST",credentials:"same-origin",body:b}).then(function(x){return x.json();}).then(function(j){'
		. 'left--;line(names[d.id]+": "+(r.pos?"#"+r.pos:c.text.nopos)+(j&&j.success?"":" ("+c.text.failed+")"));say(c.text.left.replace("%d",left));})'
		. '.catch(function(){line(names[d.id]+": "+c.text.failed);});});'
		. '})(' . wp_json_encode( $cfg ) . ');'
	);
}

add_action(
	'aw_si_tab_checklist',
	function () {
		if ( isset( $_GET['live'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the live token is checked in aw_si_live_window().
			aw_si_live_window();
			return;
		}
		$msgs = array(
			'removed' => __( 'Removed from the check list.', 'aw-seo-extras' ),
			'added'   => __( 'Added to the check list.', 'aw-seo-extras' ),
			'rebuilt' => __( 'Check list rebuilt from the rules (P1/P2, brand, quick wins, the 21 Oct rankings).', 'aw-seo-extras' ),
		);
		$cl   = isset( $_GET['cl'] ) ? sanitize_key( wp_unslash( $_GET['cl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		if ( isset( $msgs[ $cl ] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html( $msgs[ $cl ] ) . '</p></div>';
		}
		global $wpdb;
		$pending  = count( aw_si_live_pending() );
		$ids      = aw_si_checklist();
		$patterns = $ids && aw_si_ready() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aw_si_table( 'keywords' ) . " WHERE label LIKE '%{%' AND id IN (" . implode( ',', $ids ) . ')' ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- integers and a table name.
		$total    = count( $ids ) - $patterns;
		echo '<h2>' . esc_html__( 'Monthly live check', 'aw-seo-extras' ) . '</h2>';
		/* translators: 1: keywords checked this month, 2: searchable keywords on the list. */
		$line = sprintf( __( '%1$d of %2$d keywords checked this month.', 'aw-seo-extras' ), $total - $pending, $total );
		if ( $patterns ) {
			/* translators: %d: Directory patterns such as "animal rescue {district}". */
			$line .= ' ' . sprintf( _n( '%d Directory pattern ({district}) is on the list but is not searched.', '%d Directory patterns ({district}) are on the list but are not searched.', $patterns, 'aw-seo-extras' ), $patterns );
		}
		echo '<p>' . esc_html( $line ) . '</p>';
		$token = get_transient( 'aw_si_live_new_' . get_current_user_id() );
		if ( $token && aw_si_live_token_ok( (string) $token ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Drag this button to your bookmarks bar (it works for 2 hours), open google.com in this browser and click it. A small window opens here and the check runs in the Google tab, 8–15 seconds per keyword. Nothing is sent to GA4.', 'aw-seo-extras' ) . '</p>';
			// The javascript: link is the bookmarklet itself; esc_url() would strip it, so it is built from rawurlencode() only.
			echo '<p><a class="button button-primary" href="' . esc_attr( aw_si_bookmarklet( (string) $token ) ) . '">' . esc_html__( 'AW live check', 'aw-seo-extras' ) . '</a></p></div>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'bulk-checklist' );
		echo '<p><button type="submit" name="aw_si_cl" value="start" class="button button-primary">' . esc_html__( 'Start live check', 'aw-seo-extras' ) . '</button> ';
		echo '<button type="submit" name="aw_si_cl" value="rebuild" class="button">' . esc_html__( 'Rebuild the list from the rules', 'aw-seo-extras' ) . '</button></p>';
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="aw-si-add">' . esc_html__( 'Add keywords', 'aw-seo-extras' ) . '</label></th><td>';
		echo '<textarea id="aw-si-add" name="keywords" rows="3" class="large-text" placeholder="' . esc_attr__( 'One keyword per line', 'aw-seo-extras' ) . '"></textarea>';
		echo '<p><button type="submit" name="aw_si_cl" value="add" class="button">' . esc_html__( 'Add to the list', 'aw-seo-extras' ) . '</button></p></td></tr></tbody></table>';
		echo '</form>';
		$table = new AW_SI_Checklist_Table();
		$table->prepare_items();
		echo '<form method="post"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="checklist">';
		$table->search_box( __( 'Search the list', 'aw-seo-extras' ), 'aw-si-cl' );
		$table->display();
		echo '</form>';
	}
);
