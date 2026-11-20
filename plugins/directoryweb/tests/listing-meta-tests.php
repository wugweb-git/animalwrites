<?php
/**
 * The listing data model (includes/listing-meta.php) and Core's wrappers (the site layer's content/meta.php),
 * without WordPress: every key registered once, the rules, the phone and hours lists. Run by the Deploy check.
 *
 * Usage: php plugins/directoryweb/tests/listing-meta-tests.php
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' !== PHP_SAPI ) {
		exit;
	}
	define( 'ABSPATH', __DIR__ . '/' );
}

// phpcs:disable -- a command-line test runner with stand-ins for WordPress functions.


$meta = array();
$reg  = array();
$wrong   = array();
$takedown = array();
function add_action( ...$a ) {}
function apply_filters( $h, $v ) { global $takedown; return 'directoryweb_never_publish_phones' === $h ? array_merge( (array) $v, $takedown ) : $v; }
function _doing_it_wrong( $f ) { global $wrong; $wrong[] = $f; }
function esc_html( $v ) { return (string) $v; }
function esc_url_raw( $v ) { return 'url:' . $v; }
function sanitize_email( $v ) { return 'email:' . $v; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( (string) $v ); }
function absint( $v ) { return abs( (int) $v ); }
function current_user_can() { return true; }
function aw_post_types() { return array_fill_keys( array( 'aw_news', 'aw_legal', 'post', 'aw_listing', 'aw_opportunity', 'aw_campaign' ), 1 ); }
function aw_normalise_phone( $r ) { return preg_replace( '/\D+/', '', (string) $r ); }
function get_post_meta( $id, $k ) { global $meta; return $meta[ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { global $meta; $meta[ $id ][ $k ] = $v; }
function delete_post_meta( $id, $k ) { global $meta; unset( $meta[ $id ][ $k ] ); }
function register_post_meta( $t, $k, $a ) { global $reg; $reg[] = $t . '|' . $k . '|' . $a['type']; }

require dirname( __DIR__ ) . '/includes/listing-meta.php';
require dirname( __DIR__ ) . '/includes/listing-terms.php'; // directoryweb_normalise_phone()
require dirname( __DIR__, 3 ) . '/themes/kits-by-wugweb/assets/site/content/meta.php';

$fails = 0;
$count = 0;
function check( $ok, $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL: ' . $name . "\n";
	}
}

// Schema: 48 listing keys, all aw_ on aw_listing; Core's whole schema still lists them; aw_website on both types.
$dw = directoryweb_meta_schema();
check( 48 === count( $dw ), '48 listing keys' );
check( array() === array_filter( array_keys( $dw ), fn( $k ) => 0 !== strpos( $k, 'aw_' ) ), 'every key keeps aw_' );
check( array() === array_diff_key( $dw, aw_meta_schema() ), 'Core schema lists every listing key' );
$web = aw_meta_schema()['aw_website'][1];
sort( $web );
check( array( 'aw_listing', 'aw_opportunity' ) === $web, 'aw_website on listings and Opportunities' );

// Registration: each (type, key) once, listing keys by DirectoryWeb, Opportunity website by Core.
aw_register_meta();
$core = $reg;
directoryweb_register_meta();
check( count( $reg ) === count( array_unique( $reg ) ), 'no key registered twice' );
check( in_array( 'aw_opportunity|aw_website|string', $core, true ) && ! in_array( 'aw_listing|aw_website|string', $core, true ), 'Core registers the Opportunity website only' );
check( in_array( 'aw_listing|aw_website|string', $reg, true ) && in_array( 'aw_listing|aw_verified|boolean', $reg, true ), 'DirectoryWeb registers the listing keys' );

// Rules: Core's sanitiser hands listing keys to DirectoryWeb.
check( '' === aw_sanitize_meta( '2026-13', 'aw_verified_on' ) && '2026-01-02' === aw_sanitize_meta( '2026-01-02', 'aw_verified_on' ), 'listing date' );
check( '1,2,3,4,5,6' === aw_sanitize_meta( '1,2,0,3,4,5,6,7', 'aw_gallery' ), 'gallery: six IDs' );
check( '' === aw_sanitize_meta( '1799', 'aw_founded' ) && '1999' === aw_sanitize_meta( ' 1999 ', 'aw_founded' ), 'founded year' );
check( 'email:a@b.c' === aw_sanitize_meta( 'a@b.c', 'aw_email' ) && 'url:x' === aw_sanitize_meta( 'x', 'aw_website' ), 'email and url' );
check( '' === aw_sanitize_meta( 'abc', 'aw_lat' ) && '' === aw_sanitize_meta( '4.5', 'aw_g_reviews' ), 'numbers' );
check( '2026-01-02' === aw_sanitize_meta( '2026-01-02', 'aw_deadline' ) && 'FULL_TIME' === aw_sanitize_meta( 'full time', 'aw_employment_type' ), 'Core keys keep Core rules' );

// Lists: storage in SCF's repeater format, read back in order, through Core's old names.
aw_set_listing_list( 7, 'aw_phones', array( array( 'number' => '98765 43210', 'label' => 'Office', 'whatsapp' => true ) ) );
check( 1 === get_post_meta( 7, 'aw_phones' ) && 'field_aw_listing_phones_number' === get_post_meta( 7, '_aw_phones_0_number' ), 'phones stored as SCF stores them' );
aw_listing_put_phone( 7, '011 2222 3333', '' );
check( '011 2222 3333' === aw_listing_first_phone( 7 ) && '011 2222 3333' === directoryweb_listing_first_phone( 7 ), 'put phone replaces the first number' );
aw_set_listing_list( 7, 'aw_opening_hours', array( array( 'day' => 'Sunday', 'closed' => 1 ), array( 'day' => 'Monday', 'open' => '09:00', 'close' => '17:00' ) ) );
$h = aw_listing_hours( 7 );
check( 'Monday' === $h[0]['day'] && '09:00' === $h[0]['open'] && $h[1]['closed'], 'hours in weekday order' );
$spec = aw_hours_rows_spec( $h );
check( 2 === count( $spec ) && '00:00' === $spec[1]['opens'], 'closed day as 00:00' );
aw_set_listing_list( 7, 'aw_phones', array() );
check( 0 === get_post_meta( 7, 'aw_phones' ) && '' === get_post_meta( 7, 'aw_phones_0_number' ), 'rows past the count are removed' );
directoryweb_set_listing_list( 7, 'aw_unknown', array( array( 'x' => 1 ) ) );
check( '' === get_post_meta( 7, 'aw_unknown' ) && in_array( 'directoryweb_set_listing_list', $wrong, true ), 'unknown list is not written, and says so' );

// Never-publish: the takedown hash kept, and a listed number is never stored or shown.
check( 1 === count( DIRECTORYWEB_NEVER_PUBLISH_PHONES ), 'takedown hash kept' );
check( ! aw_phone_never_publish( '98765 43210' ), 'an ordinary number is shown' );
$takedown[] = hash( 'sha256', '919876543210' ); // a number taken down through the filter
check( aw_phone_never_publish( '+91 98765-43210' ), 'a listed number is caught in any format' );
aw_set_listing_list( 8, 'aw_phones', array( array( 'number' => '98765 43210' ), array( 'number' => '011 2222 3333' ) ) );
check( 1 === get_post_meta( 8, 'aw_phones' ) && '011 2222 3333' === get_post_meta( 8, 'aw_phones_0_number' ), 'a listed number is never stored' );
update_post_meta( 8, 'aw_phones', 2 );
update_post_meta( 8, 'aw_phones_1_number', '9876543210' ); // written past the check (an old row)
check( 1 === count( aw_listing_phones( 8 ) ), 'a listed number is never shown' );
check( AW_WEEKDAYS === DIRECTORYWEB_WEEKDAYS, 'weekdays the same' );

echo $count . ' checks, ' . $fails . " failed\n";
exit( $fails ? 1 : 0 );
