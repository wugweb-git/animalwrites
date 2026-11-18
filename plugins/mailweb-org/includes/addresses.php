<?php
/**
 * Email addresses: the one parser for every input (audit item 20, owner 25 Oct 2026). It replaces three that could
 * disagree: the compose form's "To / Cc / Bcc" text (split on commas, so "Name <a@b>" was lost), the webhook's and
 * API's headers, and the API's address lists.
 *
 * No WordPress hooks here, so tools/ci/mailweb_addresses_test.php can load it on its own.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain addresses, lower case, valid (is_email), each once, in the order given. An address must start the text or
 * follow a space, bracket, quote or separator, so "a!b@c.in" is dropped rather than read as "b@c.in".
 *
 * Takes any of: a typed list ("a@b.in, c@d.in; e@f.in" or one per line), a header ("Name <a@b.in>, \"Last, First\"
 * <c@d.in>"), one API address ({name, address} or {email}), or a list of any of these.
 *
 * @param mixed $v Input.
 * @return string[]
 */
function mailweb_addresses( $v ) {
	$out = array();
	foreach ( is_array( $v ) && ! isset( $v['address'] ) && ! isset( $v['email'] ) ? $v : array( $v ) as $x ) {
		$s = is_array( $x ) ? (string) ( $x['address'] ?? $x['email'] ?? '' ) : (string) $x;
		if ( ! preg_match_all( "/(?<![^\\s<>,;:\"'(\\[])[A-Z0-9._%+'\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}/i", $s, $m ) ) {
			continue;
		}
		foreach ( $m[0] as $a ) {
			$a = strtolower( trim( $a, "'." ) );
			if ( is_email( $a ) ) {
				$out[] = $a;
			}
		}
	}
	return array_values( array_unique( $out ) );
}
