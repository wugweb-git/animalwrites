<?php
/**
 * MailWeb tests that need no WordPress (owner rule: no local WordPress or MySQL), for the new screens:
 *
 * 1. Save only what changed (includes/mailweb-settings-save.php): an unchanged Save writes nothing, whether the option
 *    is absent (defaults shown) or stored; a changed field is written, cleaned the way today's forms clean it.
 * 2. Each clean-up keeps today's rules: the limit's clamps, the alias lines, "Send from it", profiles, the FluentCRM
 *    feed (no mailbox ticked is [ '-' ]) and the SMTP port.
 * 3. The Dashboard tiles (includes/mailweb-dashboard.php): every tile has its fields, a pause shows as "Not working",
 *    the forms tile is hidden without a forms source, and the day's log row has the compared numbers.
 *
 * Usage: php plugins/mailweb/tests/run.php
 *
 * @package mailweb
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.DateTime.RestrictedFunctions, Squiz.Commenting.FunctionComment.MissingParamTag, Universal.Files.SeparateFunctionsFromOO -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['mw_options'] = array();
$GLOBALS['mw_writes']  = 0;

/** Stub: options in memory; the fallback when absent, as WordPress. */
function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['mw_options'] ) ? $GLOBALS['mw_options'][ $name ] : $fallback;
}

/** Stub: counts writes. */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	++$GLOBALS['mw_writes'];
	$GLOBALS['mw_options'][ $name ] = $value;
	return true;
}

/** Stub: hooks are not run here. */
function add_action() {}

/** Stub: no translation. */
function __( $s ) {
	return $s;
}

/** Stub: singular or plural. */
function _n( $one, $many, $n ) {
	return 1 === (int) $n ? $one : $many;
}

/** Stub: plain thousands. */
function number_format_i18n( $n ) {
	return number_format( (float) $n );
}

/** Stub: UTC dates. */
function wp_date( $format, $t = null ) {
	return gmdate( $format, null === $t ? time() : (int) $t );
}

/** WordPress's absint. */
function absint( $n ) {
	return abs( (int) $n );
}

/** WordPress's sanitize_key. */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/** Close to WordPress's sanitize_text_field: tags out, whitespace folded. */
function sanitize_text_field( $str ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}

/** Close to WordPress's sanitize_textarea_field: tags out, line breaks kept. */
function sanitize_textarea_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

/** WordPress core's is_email() checks, without filters (as tools/ci/mailweb_addresses_test.php). */
function is_email( $email ) {
	if ( strlen( $email ) < 6 || false === strpos( $email, '@', 1 ) ) {
		return false;
	}
	list( $local, $domain ) = explode( '@', $email, 2 );
	if ( ! preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) || ! preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $domain ) ) {
		return false;
	}
	return $email;
}

/** Close to sanitize_email: an address or ''. */
function sanitize_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	return is_email( $email ) ? $email : '';
}

/** Close to wp_kses_post: scripts out. */
function wp_kses_post( $html ) {
	return preg_replace( '@<script[^>]*>.*?</script>@si', '', (string) $html );
}

require dirname( __DIR__ ) . '/includes/mailweb-settings-save.php';
require dirname( __DIR__ ) . '/includes/mailweb-dashboard.php';

$fails = 0;
/** Report one check. */
function check( $label, $ok ) {
	global $fails;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
}

/** Reset the stored options. */
function reset_options( array $o = array() ) {
	$GLOBALS['mw_options'] = $o;
	$GLOBALS['mw_writes']  = 0;
}

// 1. The daily limit.
reset_options();
check(
	'limit: unchanged Save with the option absent writes nothing',
	! mailweb_save_changed(
		'mailweb_cap',
		mailweb_cap_current(),
		array(
			'budget'  => 90,
			'reserve' => 10,
		),
		'mailweb_cap_clean'
	) && 0 === $GLOBALS['mw_writes'] && ! array_key_exists(
		'mailweb_cap',
		$GLOBALS['mw_options']
	)
);
check( 'limit: an empty Save writes nothing', ! mailweb_save_changed( 'mailweb_cap', mailweb_cap_current(), array(), 'mailweb_cap_clean' ) );
reset_options(
	array(
		'mailweb_cap' => array(
			'budget'  => 90,
			'reserve' => 10,
		),
	)
);
check(
	'limit: unchanged Save of stored values writes nothing',
	! mailweb_save_changed(
		'mailweb_cap',
		mailweb_cap_current(),
		array(
			'budget'  => '90',
			'reserve' => '10',
		),
		'mailweb_cap_clean'
	) && 0 === $GLOBALS['mw_writes']
);
check(
	'limit: a changed budget is written, reserve kept',
	mailweb_save_changed( 'mailweb_cap', mailweb_cap_current(), array( 'budget' => 80 ), 'mailweb_cap_clean' ) && array(
		'budget'  => 80,
		'reserve' => 10,
	) === get_option( 'mailweb_cap' )
);
check(
	'limit: budget clamped 1–10,000 and reserve a whole number, as today',
	array(
		'budget'  => 10000,
		'reserve' => 3,
	) === mailweb_cap_clean(
		array(
			'budget'  => 99999,
			'reserve' => -3,
		)
	) && 1 === mailweb_cap_clean( array( 'budget' => 0 ) )['budget']
);
check( 'limit: fields not in the form are ignored', ! mailweb_save_changed( 'mailweb_cap', mailweb_cap_current(), array( 'other' => 5 ), 'mailweb_cap_clean' ) );

// 2. Aliases and mailboxes.
$map = array(
	'hello@example.in'   => 'support@example.in',
	'contact@example.in' => 'support@example.in',
);
check( 'aliases: lines and map round trip', mailweb_aliases_clean( mailweb_aliases_text( $map ) ) === $map );
check( 'aliases: bad lines left out, case folded', array( 'a@example.in' => 'b@example.in' ) === mailweb_aliases_clean( "A@Example.in = B@example.in\nnot an alias\nx@y = z@example.in" ) );
reset_options( array( 'mailweb_aliases' => $map ) );
$cur = array( 'v' => mailweb_aliases_text( $map ) );
check( 'aliases: unchanged Save writes nothing', ! mailweb_save_changed( 'mailweb_aliases', $cur, $cur, fn( $v ) => mailweb_aliases_clean( (string) $v['v'] ) ) );
check(
	'mailboxes: every mailbox stored, ticked or not',
	array(
		'support'      => array( 'send' => true ),
		'partnerships' => array( 'send' => false ),
	) === mailweb_mailboxes_clean( array( 'support' => 1 ), array( 'support', 'partnerships' ) )
);
reset_options();
$now = array(
	'support'      => true,
	'partnerships' => false,
);
$cl  = fn( $v ) => mailweb_mailboxes_clean( $v, array( 'support', 'partnerships' ) );
check( 'mailboxes: unchanged Save with the option absent writes nothing', ! mailweb_save_changed( 'mailweb_mailboxes', $now, $now, $cl ) && 0 === $GLOBALS['mw_writes'] );
check( 'mailboxes: unknown mailbox keys are ignored', ! mailweb_save_changed( 'mailweb_mailboxes', $now, array( 'other' => true ), $cl ) );
check( 'mailboxes: a ticked box is written', mailweb_save_changed( 'mailweb_mailboxes', $now, array( 'partnerships' => true ), $cl ) && true === get_option( 'mailweb_mailboxes' )['partnerships']['send'] );

// 3. Profiles, FluentCRM, SMTP.
$p = mailweb_profile_clean(
	array(
		'name'      => ' Support <b>team</b> ',
		'signature' => 'Hi<script>x()</script> <b>there</b>',
		'picture'   => '12',
	)
);
check(
	'profile: cleaned as today',
	array(
		'name'      => 'Support team',
		'signature' => 'Hi <b>there</b>',
		'picture'   => 12,
	) === $p
);
check( 'profile: clean is stable (an unchanged Save compares equal)', mailweb_profile_clean( $p ) === $p );
check( 'fluentcrm: no mailbox ticked is kept as [ "-" ]', array( '-' ) === mailweb_crm_clean( array( 'boxes' => array() ), array( 'support' ) )['boxes'] );
check( 'fluentcrm: only known mailboxes', array( 'support' ) === mailweb_crm_clean( array( 'boxes' => array( 'support', 'evil' ) ), array( 'support' ) )['boxes'] );
reset_options();
$crm = array(
	'on'    => true,
	'tag'   => 'Emailed us',
	'boxes' => array( 'support' ),
);
$cc  = fn( $v ) => mailweb_crm_clean( $v, array( 'support' ) );
check( 'fluentcrm: unchanged Save with the option absent writes nothing', ! mailweb_save_changed( 'mailweb_crm', $crm, $crm, $cc ) && 0 === $GLOBALS['mw_writes'] );
check( 'fluentcrm: switching off is written', mailweb_save_changed( 'mailweb_crm', $crm, array( 'on' => false ), $cc ) && false === get_option( 'mailweb_crm' )['on'] );
$smtp = array(
	'user' => 'noreply@example.in',
	'name' => 'Animal Writes',
	'host' => 'smtp.hostinger.com',
	'port' => 465,
);
check( 'smtp: unchanged fields compare equal (port as number or text)', mailweb_smtp_clean( $smtp ) === mailweb_smtp_clean( array_merge( $smtp, array( 'port' => '465' ) ) ) );
check( 'smtp: no port is stored as empty, as today', '' === mailweb_smtp_clean( array( 'port' => '0' ) )['port'] );
check( 'smtp: the password is never part of the cleaned fields', ! array_key_exists( 'pass', mailweb_smtp_clean( $smtp + array( 'pass' => 'secret' ) ) ) );

// 4. Dashboard tiles and the day's log row.
$live  = array(
	'paused'    => false,
	'lastError' => array(),
	'smtp'      => array(
		'user' => 'noreply@example.in',
		'host' => 'smtp.hostinger.com',
		'port' => '465',
		'pass' => 'set',
	),
	'replies'   => 2,
	'perHour'   => 30,
	'budget'    => 90,
	'urls'      => array(
		'inbox' => 'i',
		'forms' => 'f',
	),
);
$c     = array(
	'at'     => time(),
	'boxes'  => array(
		'support' => array(
			'address' => 'support@example.in',
			'aliases' => array(),
			'ok'      => true,
			'unread'  => 3,
			'stale'   => false,
			'staleAt' => 0,
			'newest'  => array(),
		),
	),
	'forms'  => null,
	'sent24' => 12,
	'room1'  => 78,
	'room3'  => 68,
	'queued' => array( 0, 1, 0, 4 ),
	'failed' => 1,
	'held'   => 2,
	'perDay' => array( gmdate( 'Y-m-d' ) => 5 ),
);
$tiles = mailweb_dashboard_tiles( $c, $live );
$keys  = array_column( $tiles, 'key' );
check( 'tiles: the seven tiles of the plan, in order', array( 'unread', 'forms', 'sent24', 'waiting', 'failed', 'sending', 'replies' ) === $keys );
$ok = true;
foreach ( $tiles as $t ) {
	$ok = $ok && isset( $t['label'], $t['value'], $t['sub'], $t['tone'] ) && array_key_exists( 'href', $t );
}
check( 'tiles: each has label, value, sub, href and tone', $ok );
$by = array_column( $tiles, null, 'key' );
check( 'tiles: unread is the sum of the mailboxes', '3' === $by['unread']['value'] && 'support@ 3' === $by['unread']['sub'] );
check( 'tiles: sent in 24 hours shows the limit', '12 of 90' === $by['sent24']['value'] );
check( 'tiles: waiting adds the priorities', 5 === $by['waiting']['raw'] );
check( 'tiles: held emails need attention', 3 === $by['failed']['raw'] && 'warn' === $by['failed']['tone'] );
check( 'tiles: no forms source hides the forms tile', true === $by['forms']['hide'] );
check( 'tiles: running, through the SMTP mailbox', 'Running' === $by['sending']['value'] && '' === $by['sending']['tone'] );
$live['paused'] = array(
	'at'    => time(),
	'error' => 'SMTP Error: Could not authenticate.',
);
check( 'tiles: a pause shows as not working', 'error' === array_column( mailweb_dashboard_tiles( $c, $live ), null, 'key' )['sending']['tone'] );
$c['boxes']['support']['ok'] = false;
check( 'tiles: a mailbox not reachable needs attention', 'warn' === array_column( mailweb_dashboard_tiles( $c, $live ), null, 'key' )['unread']['tone'] );
check( 'tiles: while the first count runs, every tile still has its fields', 7 === count( mailweb_dashboard_tiles( array(), $live ) ) && false === mailweb_dashboard_tiles( array(), $live )[1]['hide'] );
$n = mailweb_dashboard_numbers( $c );
check(
	'log row: the compared numbers',
	array(
		'unread'  => 3,
		'sent24'  => 12,
		'waiting' => 5,
		'failed'  => 3,
		'sent'    => 5,
	) === $n
);

// Writes from a settings file (Kits import and undo) are cleaned like the screens' fields.
if ( ! function_exists( 'mailweb_all_boxes' ) ) {
	/** Two mailboxes. */
	function mailweb_all_boxes() {
		return array(
			'support'      => 'support@example.com',
			'partnerships' => 'partnerships@example.com',
		);
	}
}
$clean = array(
	'budget'  => 80,
	'reserve' => 10,
);
check(
	'import: the limit is made whole numbers',
	array(
		'budget'  => 5,
		'reserve' => 0,
	) === mailweb_option_clean(
		array(
			'budget'  => -5,
			'reserve' => 'x',
		),
		'mailweb_cap'
	)
);
check( 'import: a clean limit is kept', mailweb_option_clean( $clean, 'mailweb_cap' ) === $clean );
check( 'import: a signature loses its script', 'Hi' === mailweb_option_clean( array( 'support@example.com' => array( 'signature' => 'Hi<script>x</script>' ) ), 'mailweb_profiles' )['support@example.com']['signature'] );
check(
	'import: unknown mailboxes are dropped, absent ones stay unset',
	array( 'support' => array( 'send' => false ) ) === mailweb_option_clean(
		array(
			'support' => array( 'send' => 0 ),
			'other'   => array( 'send' => 1 ),
		),
		'mailweb_mailboxes'
	)
);
check(
	'import: an empty feed list still means every mailbox',
	array() === mailweb_option_clean(
		array(
			'on'    => 1,
			'boxes' => array(),
		),
		'mailweb_crm'
	)['boxes']
);
check( 'import: feed boxes of another site mean none', array( '-' ) === mailweb_option_clean( array( 'boxes' => array( 'elsewhere' ) ), 'mailweb_crm' )['boxes'] );
check(
	'import: aliases keep address pairs only',
	array( 'hello@example.com' => 'support@example.com' ) === mailweb_option_clean(
		array(
			'hello@example.com' => 'support@example.com',
			'bad'               => 'x',
		),
		'mailweb_aliases'
	)
);

echo $fails ? "\n$fails failed\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
