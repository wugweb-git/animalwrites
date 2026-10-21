<?php
/**
 * Every section labelled (shell spec): each full-width or wide group in the page becomes a <section> with a kebab-case
 * id (unique on the page) and an accessible name — aria-labelledby its first heading when that heading always renders
 * outside a list, otherwise aria-label. Headings inside lists (cards in a query loop) never carry ids.
 * A site can name sections without a visible heading with the kits_section_labels filter (class => [id, label]).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A unique id on this page. */
function kits_section_id( $base ) {
	static $used = array();
	$id = sanitize_title( $base );
	$id = $id ? $id : 'section';
	$n  = $id;
	for ( $i = 2; isset( $used[ $n ] ); $i++ ) {
		$n = $id . '-' . $i;
	}
	$used[ $n ] = true;
	return $n;
}

/**
 * The text of the heading the processor stands on, and its id (one is added from the text when it has none).
 * Attributes are read and written with WordPress's HTML API (WP_HTML_Tag_Processor), never with regular expressions.
 *
 * @param WP_HTML_Tag_Processor $p    Processor on a heading opener.
 * @param string                $base Id base when the heading has no id ('' = its text).
 * @return array{0: string, 1: string} text, id.
 */
function kits_section_heading( $p, $base = '' ) {
	$tag = $p->get_tag();
	$p->set_bookmark( 'kits-h' );
	$hid  = $p->get_attribute( 'id' );
	$text = '';
	while ( $p->next_token() ) {
		if ( '#tag' === $p->get_token_type() && $tag === $p->get_tag() && $p->is_tag_closer() ) {
			break;
		}
		if ( '#text' === $p->get_token_type() ) {
			$text .= $p->get_modifiable_text();
		}
	}
	$text = trim( wp_strip_all_tags( $text ) );
	if ( ! is_string( $hid ) || '' === $hid ) {
		$p->seek( 'kits-h' );
		$hid = kits_section_id( ( $base ? $base : $text ) . '-title' );
		$p->set_attribute( 'id', $hid );
	}
	$p->release_bookmark( 'kits-h' );
	return array( $text, $hid );
}

add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		$align = $block['attrs']['align'] ?? '';
		if ( is_admin() || ! in_array( $align, array( 'full', 'wide' ), true ) ) {
			return $html;
		}
		$p = new WP_HTML_Tag_Processor( $html );
		if ( ! $p->next_tag() || 'DIV' !== $p->get_tag() ) {
			return $html; // page header bands keep <header>
		}
		if ( false !== strpos( (string) $p->get_attribute( 'class' ), 'kit-site-' ) || null !== $p->get_attribute( 'aria-label' ) ) {
			return $html; // site header and footer are landmarks already
		}
		$p->set_bookmark( 'kits-open' );
		$id    = '';
		$label = '';
		foreach ( (array) apply_filters( 'kits_section_labels', array() ) as $class => $def ) {
			if ( $p->has_class( $class ) ) {
				list( $id, $label ) = $def;
				break;
			}
		}
		// The first h1/h2 that comes before any list item in this section names it (a breadcrumb <nav> before it does not count).
		$hid    = '';
		$in_nav = false;
		while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			if ( 'NAV' === $p->get_tag() ) {
				$in_nav = ! $p->is_tag_closer();
				continue;
			}
			if ( $in_nav || $p->is_tag_closer() ) {
				continue;
			}
			if ( 'LI' === $p->get_tag() ) {
				break;
			}
			if ( in_array( $p->get_tag(), array( 'H1', 'H2' ), true ) ) {
				list( $text, $hid ) = kits_section_heading( $p, $id );
				$id                 = $id ? $id : $text;
				break;
			}
		}
		if ( ! $hid && ! $label ) {
			return $html; // nothing to name it by: leave as a plain group
		}
		$p->seek( 'kits-open' );
		$hid ? $p->set_attribute( 'aria-labelledby', $hid ) : $p->set_attribute( 'aria-label', $label );
		if ( null === $p->get_attribute( 'id' ) ) {
			$p->set_attribute( 'id', kits_section_id( $id ) );
		}
		$p->release_bookmark( 'kits-open' );
		$html = $p->get_updated_html();
		// The HTML API cannot rename a tag: the group's own outer <div> … </div> becomes <section> … </section>.
		$html = (string) preg_replace( '/^(\s*)<div\b/', '$1<section', $html, 1 );
		return (string) preg_replace( '/<\/div>(\s*)$/', '</section>$1', $html, 1 );
	},
	20,
	2
);

/**
 * Hand-written <section> elements (shortcodes, blocks) get the same treatment: an id, and a name from their first
 * heading when they have none. Runs on every block's output; sections that already have both are left alone.
 */
add_filter(
	'render_block',
	function ( $html ) {
		if ( is_admin() || false === strpos( $html, '<section' ) ) {
			return $html;
		}
		$p = new WP_HTML_Tag_Processor( $html );
		$n = 0;
		while ( $p->next_tag( array( 'tag_name' => 'SECTION' ) ) ) {
			$id = $p->get_attribute( 'id' );
			$lb = $p->get_attribute( 'aria-labelledby' );
			$al = $p->get_attribute( 'aria-label' );
			if ( is_string( $id ) && $id && ( $lb || $al ) ) {
				continue;
			}
			$mark = 'kits-s' . ( ++$n );
			$p->set_bookmark( $mark );
			$name = '';
			$hid  = '';
			if ( is_string( $lb ) && $lb ) {
				$name = preg_replace( '/-(title|h|heading)$/', '', $lb );
				$name = $name === $lb ? $lb . '-section' : $name; // a heading id with no suffix: never give the section the heading's own id
			} elseif ( is_string( $al ) && $al ) {
				$name = $al;
			} else {
				// Its first h2–h4, up to the next section (opening or closing).
				while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
					if ( 'SECTION' === $p->get_tag() ) {
						break;
					}
					if ( ! $p->is_tag_closer() && in_array( $p->get_tag(), array( 'H2', 'H3', 'H4' ), true ) ) {
						list( $name, $hid ) = kits_section_heading( $p );
						break;
					}
				}
			}
			$p->seek( $mark );
			if ( '' !== $name ) {
				if ( $hid ) {
					$p->set_attribute( 'aria-labelledby', $hid );
				}
				if ( ! is_string( $id ) || '' === $id ) {
					$p->set_attribute( 'id', kits_section_id( $name ) );
				}
			}
			$p->release_bookmark( $mark );
		}
		return $p->get_updated_html();
	},
	99
);


// The share plugin's heading comes straight after the page title: h2, so no level is skipped.
add_filter( 'scriptlesssocialsharing_heading_element', fn() => 'h2' );

/**
 * End of an article: its tags bottom left (crawlable text links) and the share buttons bottom right, one row.
 * Printed by [kits_endbar] at the end of the single templates (news has no post content, so not on the_content).
 */
function kits_endbar( $id = 0 ) {
	$id    = $id ? (int) $id : get_the_ID();
	$tax   = (string) apply_filters( 'kits_endbar_taxonomy', 'category', get_post_type( $id ) );
	$tags  = get_the_term_list( $id, $tax, '<div class="kit-endbar-tags wp-block-post-terms taxonomy-' . esc_attr( $tax ) . '">', '', '</div>' );
	$tags  = is_wp_error( $tags ) ? '' : (string) $tags;
	$share = function_exists( 'aw_share_html' ) && in_array( get_post_type( $id ), (array) apply_filters( 'kits_endbar_share_types', array() ), true ) ? aw_share_html( $id ) : ''; // the Kits share bar (share.php): X, Facebook, LinkedIn, WhatsApp, Email, Copy link
	$before = (string) apply_filters( 'kits_endbar_before', '' ); // anything a site prints before the end bar
	$after  = (string) apply_filters( 'kits_endbar_after', '' ); // or after it
	if ( '' === $tags . $share ) {
		return $before . $after;
	}
	return $before . '<div class="kit-endbar' . ( '' === $tags ? ' kit-endbar--share-only' : '' ) . '">' . $tags . ( $share ? '<div class="kit-endbar-share">' . $share . '</div>' : '' ) . '</div>' . $after;
}
add_shortcode( 'kits_endbar', fn() => is_singular() ? kits_endbar( get_queried_object_id() ) : '' );
