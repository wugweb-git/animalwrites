<?php
/**
 * Deploy check (audit item 20, owner 25 Oct 2026): Mailweb's one address parser (plugins/mailweb/includes/addresses.php)
 * on both kinds of input it replaced: the compose form's typed To / Cc / Bcc text, and the webhook's and API's headers
 * and address lists. Runs without WordPress; is_email() below follows WordPress core's checks (no filters).
 * Usage: php tools/ci/mailweb_addresses_test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

/** WordPress core's is_email() checks (wp-includes/formatting.php), without its filters. */
function is_email( $email ) {
	if ( strlen( $email ) < 6 || false === strpos( $email, '@', 1 ) ) {
		return false;
	}
	list( $local, $domain ) = explode( '@', $email, 2 );
	if ( ! preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) || preg_match( '/\.{2,}/', $domain ) || trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) {
		return false;
	}
	$subs = explode( '.', $domain );
	if ( 2 > count( $subs ) ) {
		return false;
	}
	foreach ( $subs as $sub ) {
		if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub || ! preg_match( '/^[a-z0-9-]+$/i', $sub ) ) {
			return false;
		}
	}
	return $email;
}

require dirname( __DIR__, 2 ) . '/plugins/mailweb/includes/addresses.php';

$cases = array(
	// The compose form (was mailweb_parse_addrs): typed lists.
	'form: commas'                 => array( 'a@example.in, b@example.org', array( 'a@example.in', 'b@example.org' ) ),
	'form: semicolons and spaces'  => array( "A@Example.in;b@example.org  c@example.com\nd@example.net", array( 'a@example.in', 'b@example.org', 'c@example.com', 'd@example.net' ) ),
	'form: repeats once'           => array( 'a@example.in, A@example.in', array( 'a@example.in' ) ),
	'form: invalid dropped'        => array( 'not-an-address, @x.in, a@b, ok@example.in', array( 'ok@example.in' ) ),
	'form: name and angle'         => array( 'Priya Shah <priya@example.in>, b@example.org', array( 'priya@example.in', 'b@example.org' ) ),
	'form: apostrophe local part'  => array( "o'brien@example.ie", array( "o'brien@example.ie" ) ),
	'form: trailing full stop'     => array( 'write to a@example.in.', array( 'a@example.in' ) ),
	'form: empty'                  => array( '', array() ),
	'form: no partial address'     => array( 'a!b@example.in, ok@example.in', array( 'ok@example.in' ) ),
	// Webhook and API (was mailweb_addresses in mail-in.php and mailweb_addr_list in inbox.php): headers and lists.
	'header: quoted name w/ comma' => array( '"Shah, Priya" <Priya@Example.in>, Team <team@example.org>', array( 'priya@example.in', 'team@example.org' ) ),
	'api: one {name, address}'     => array( array( 'name' => 'Priya', 'address' => 'Priya@Example.in' ), array( 'priya@example.in' ) ),
	'api: one {email}'             => array( array( 'email' => 'a@example.in' ), array( 'a@example.in' ) ),
	'api: list of objects'         => array( array( array( 'name' => 'A', 'address' => 'a@example.in' ), array( 'address' => 'b@example.org' ), array( 'name' => 'No address' ) ), array( 'a@example.in', 'b@example.org' ) ),
	'api: list of strings'         => array( array( 'a@example.in', 'B <b@example.org>', 'junk' ), array( 'a@example.in', 'b@example.org' ) ),
	'api: empty list'              => array( array(), array() ),
	'api: null'                    => array( null, array() ),
);

$fail = 0;
foreach ( $cases as $name => $c ) {
	$got = mailweb_addresses( $c[0] );
	if ( $got !== $c[1] ) {
		$fail++;
		fwrite( STDERR, "FAIL $name: got " . json_encode( $got ) . ', want ' . json_encode( $c[1] ) . "\n" );
	}
}
echo ( count( $cases ) - $fail ) . ' of ' . count( $cases ) . " address cases pass\n";
exit( $fail ? 1 : 0 );
