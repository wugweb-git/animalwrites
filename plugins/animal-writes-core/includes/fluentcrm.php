<?php
/**
 * FluentCRM (owner, 16 Oct 2026): one list, "Newsletter"; volunteers are newsletter subscribers.
 *
 * - The footer sign-up (Fluent Forms), the forms' newsletter box and every volunteer sign-up add the person to the
 *   Newsletter list as "subscribed" and to the site's own list ({prefix}aw_subscribers, active). FluentCRM sends no
 *   email for this: the only email is the sign-up confirmation Fluent Forms already sends.
 * - The two lists stay in step on unsubscribes: FluentCRM's unsubscribe marks the site's row unsubscribed, and the
 *   site's unsubscribe link unsubscribes the FluentCRM contact.
 * - Campaigns go out through wp_mail (the site's mailbox, kits-core) under the daily cap in mail-cap.php.
 * - "Remove my data" deletes the FluentCRM contact, and with it its notes, custom fields and emails.
 *
 * FluentCRM is where people are kept (owner, 17 Oct 2026):
 * - Every incoming email (mail-in.php) and every Contact form message (pages.php) finds or creates the sender's
 *   contact and adds a note: subject, text, the address or form it came to, when it was sent. A contact made this way
 *   is "transactional" (never sent the newsletter); an existing contact keeps its status. Tags "Emailed us" and
 *   "Contact form". The Inbox item (Contact messages) is still made, for spotting new mail. Notes are kept as long as
 *   the contact messages are (retention.php: nothing is deleted on a timer); "Remove my data" removes both.
 * - Volunteers are contacts tagged "Volunteer", with their volunteer details in custom fields (group "Volunteer",
 *   mirroring the volunteers table, which stays as the backup).
 * - Replies (owner, 17 Oct 2026): only to incoming emails, one email at a time. FluentCRM free has no "Send Email" on
 *   the profile, so each email's note carries a Reply link to Inbox → Reply (mail-reply.php), as do the email's Inbox
 *   row and screen. The reply goes out through wp_mail (the site's mailbox) under the daily cap, first in the queue
 *   (mail-cap.php), Reply-To the address that email was sent to, so the answer comes back through the mail webhook as
 *   a new note; the reply itself is logged as a note "Reply sent: <subject>". Form messages get no Reply.
 * Setup (sends nothing): `wp aw crm-setup`, `wp aw crm-import`, `wp aw crm-move` (fields, volunteers, notes).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CRM_LIST = 'newsletter';

function aw_crm_on() {
	return function_exists( 'FluentCrmApi' );
}

/** The Newsletter list ID (created on first use). */
function aw_crm_list_id() {
	if ( ! aw_crm_on() ) {
		return 0;
	}
	$list = FluentCrmApi( 'lists' )->getInstance()->where( 'slug', AW_CRM_LIST )->first();
	if ( ! $list ) {
		$list = FluentCrmApi( 'lists' )->getInstance()->create( array( 'title' => 'Newsletter', 'slug' => AW_CRM_LIST ) );
	}
	return $list ? (int) $list->id : 0;
}

/** Tag titles shown in FluentCRM (slug => title); any other tag is titled by its slug. */
const AW_CRM_TAG_TITLES = array(
	'volunteer'           => 'Volunteer',
	'emailed-us'          => 'Emailed us',
	'contact-form'        => 'Contact form',
	// Added 21 Oct 2026 (owner) next to the existing ones; newsletter ("Joined via site form"), volunteer-1.0 and
	// campaign-<slug> are kept as they are. The owner sets org-approved, vol-active and vol-paused by hand.
	'claimant'            => 'Claimant',
	'org-pending'         => 'Organisation: pending',
	'org-approved'        => 'Organisation: approved',
	'vol-active'          => 'Volunteer: active',
	'vol-paused'          => 'Volunteer: paused',
	'topic-rescues'       => 'Topic: rescues and cruelty cases',
	'topic-campaigns'     => 'Topic: campaigns',
	'topic-laws'          => 'Topic: laws and court orders',
	'topic-opportunities' => 'Topic: opportunities',
);

/** Tag IDs for slugs (created if missing). */
function aw_crm_tag_ids( $slugs ) {
	$ids = array();
	foreach ( (array) $slugs as $slug ) {
		$tag = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $slug )->first();
		$tag = $tag ? $tag : FluentCrmApi( 'tags' )->getInstance()->create( array( 'title' => AW_CRM_TAG_TITLES[ $slug ] ?? $slug, 'slug' => $slug ) );
		$ids[] = (int) $tag->id;
	}
	return $ids;
}

/**
 * Add or update a contact on the Newsletter list as subscribed (someone who unsubscribed, bounced or complained keeps
 * that status). Sends nothing.
 *
 * @param string $email Email.
 * @param string $name  Full name (optional).
 * @param array  $extra FluentCRM fields (phone, address_line_1, city, state, postal_code, country, source).
 * @param array  $tags  Tag slugs.
 * @return int Contact ID, or 0.
 */
function aw_crm_subscribe( $email, $name = '', $extra = array(), $tags = array() ) {
	$email = strtolower( trim( (string) $email ) );
	$list  = aw_crm_list_id();
	if ( ! $list || ! is_email( $email ) ) {
		return 0;
	}
	$parts = preg_split( '/\s+/', trim( (string) $name ), 2 );
	$data  = array_filter( $extra ) + array(
		'email'  => $email,
		'status' => 'subscribed',
		'lists'  => array( $list ),
	);
	if ( '' !== ( $parts[0] ?? '' ) ) {
		$data['first_name'] = $parts[0];
		$data['last_name']  = $parts[1] ?? '';
	}
	$have = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( $have && in_array( $have->status, array( 'unsubscribed', 'bounced', 'complained' ), true ) ) {
		$data['status'] = $have->status;
	}
	if ( $tags ) {
		$data['tags'] = aw_crm_tag_ids( $tags );
	}
	$c = FluentCrmApi( 'contacts' )->createOrUpdate( $data );
	return $c ? (int) $c->id : 0;
}

/** A volunteer's details as FluentCRM fields (state and district by place slug). */
function aw_crm_volunteer_fields( $state_slug, $district_slug, $city, $pincode, $phone ) {
	$st = $state_slug ? aw_place_by_slug( $state_slug ) : null;
	$di = $district_slug ? aw_place_by_slug( $district_slug ) : null;
	return array(
		'phone'          => (string) $phone,
		'address_line_1' => $di ? $di->name . ' district' : '',
		'city'           => (string) $city,
		'state'          => $st ? $st->name : '',
		'postal_code'    => (string) $pincode,
		'country'        => 'IN',
	);
}

/** One sign-up onto both lists: the site's (active) and FluentCRM's Newsletter (subscribed). */
function aw_newsletter_join( $email, $name, $source, $consent, $extra = array(), $tags = array() ) {
	$res = function_exists( 'aw_nl_activate' ) ? aw_nl_activate( $email, $name, $source, $consent ) : false;
	if ( aw_crm_on() ) {
		aw_crm_subscribe( $email, $name, $extra + array( 'source' => $source ), $tags );
	}
	return $res;
}

/* ---------- Messages: a note on the sender's contact ---------- */

/**
 * The sender's contact, found or made. A new contact is "transactional" (no newsletter); an existing one keeps its
 * status. Our own addresses and mail-system senders get none.
 *
 * @param string $email  Email.
 * @param string $name   Name (optional).
 * @param array  $tags   Tag slugs.
 * @param string $source Where the contact came from.
 * @return object|null FluentCRM contact.
 */
function aw_crm_contact_for( $email, $name, $tags, $source ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! aw_crm_on() || ! is_email( $email ) || str_ends_with( $email, '@animalwrites.in' ) || preg_match( '/^(mailer-daemon|postmaster|no-?reply)@/i', $email ) ) {
		return null;
	}
	$c = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( ! $c ) {
		$data  = array( 'email' => $email, 'status' => 'transactional', 'source' => $source );
		$name  = false !== strpos( (string) $name, '@' ) ? '' : trim( (string) $name, " \t\"'" );
		$parts = preg_split( '/\s+/', $name, 2 );
		if ( '' !== ( $parts[0] ?? '' ) ) {
			$data['first_name'] = $parts[0];
			$data['last_name']  = $parts[1] ?? '';
		}
		$c = FluentCrmApi( 'contacts' )->createOrUpdate( $data );
	}
	if ( $c && $tags ) {
		$c->attachTags( aw_crm_tag_ids( $tags ) );
	}
	return $c ? $c : null;
}

/**
 * Add (or bring up to date) the note for one Contact messages item: an incoming email or a Contact form message.
 * The note's ID is kept on the item, so running it again never makes a second note. A removal request makes none.
 *
 * @param int $id aw_message post ID.
 * @return int Note ID, or 0.
 */
function aw_crm_message_note( $id ) {
	$id = (int) $id;
	if ( ! aw_crm_on() || 'aw_message' !== get_post_type( $id ) ) {
		return 0;
	}
	$m     = fn( $k ) => trim( (string) get_post_meta( $id, 'aw_m_' . $k, true ) );
	$email = 'email' === $m( 'about' );
	if ( 'remove-data' === $m( 'about' ) ) {
		return 0;
	}
	$c = aw_crm_contact_for( $m( 'email' ), $m( 'name' ), array( $email ? 'emailed-us' : 'contact-form' ), $email ? 'email' : 'contact-form' );
	if ( ! $c ) {
		return 0;
	}
	$post  = get_post( $id );
	$when  = mysql2date( 'j M Y, g:i a', $post->post_date );
	$about = defined( 'AW_CONTACT_ABOUT' ) ? ( AW_CONTACT_ABOUT[ $m( 'about' ) ] ?? $m( 'about' ) ) : $m( 'about' );
	if ( $email ) {
		$title = $post->post_title; // "Email: subject"
		$head  = array( 'Sent to' => aw_crm_message_to( $id ), 'Mailbox' => $m( 'mailbox' ), 'Sent' => $when, 'Attachments' => str_replace( "\n", ', ', $m( 'attachments' ) ) );
	} else {
		$title = 'Contact form: ' . $about;
		$head  = array( 'Form' => 'Contact (' . $about . ')', 'Organisation' => $m( 'org' ), 'Sent from' => $m( 'source' ), 'Sent' => $when );
	}
	$html = '';
	foreach ( array_filter( $head ) as $k => $v ) {
		$html .= '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( $v ) . '<br>';
	}
	$text = trim( (string) $post->post_content );
	$reply = $email && function_exists( 'aw_reply_url' ) ? '<a href="' . esc_url( aw_reply_url( $id ) ) . '">Reply to this email</a> · ' : ''; // emails only (mail-reply.php)
	$html  = '<p>' . $html . '</p>' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '<p><em>(no text)</em></p>' )
		. '<p>' . $reply . '<a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">Open in Inbox → Contact messages</a></p>';
	$note = (int) get_post_meta( $id, 'aw_m_crm_note', true );
	$row  = $note ? \FluentCrm\App\Models\SubscriberNote::where( 'id', $note )->first() : null;
	if ( $row ) {
		$row->title       = $title;
		$row->description = $html;
		$row->save();
		return $note;
	}
	$row = \FluentCrm\App\Models\SubscriberNote::create(
		array(
			'subscriber_id' => (int) $c->id,
			'type'          => $email ? 'email' : 'note',
			'title'         => $title,
			'description'   => $html,
			'created_at'    => $post->post_date,
		)
	);
	if ( $row ) {
		update_post_meta( $id, 'aw_m_crm_note', (int) $row->id );
	}
	return $row ? (int) $row->id : 0;
}

/** The address an item was written to: an incoming email's own To (one of ours), else its mailbox; the form: contact@. */
function aw_crm_message_to( $id ) {
	if ( 'email' !== get_post_meta( $id, 'aw_m_about', true ) ) {
		return 'contact@animalwrites.in';
	}
	$to = strtolower( trim( (string) get_post_meta( $id, 'aw_m_to', true ) ) );
	$mb = strtolower( trim( (string) get_post_meta( $id, 'aw_m_mailbox', true ) ) );
	return str_ends_with( $to, '@animalwrites.in' ) ? $to : ( $mb ? $mb : 'contact@animalwrites.in' );
}

/** Reply-To for a reply to this person: the address they last wrote to (email or form), else contact@. */
function aw_crm_reply_to( $email ) {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'aw_m_email' WHERE p.post_type = 'aw_message' AND LOWER(TRIM(m.meta_value)) = %s ORDER BY p.post_date DESC, p.ID DESC LIMIT 1", strtolower( trim( (string) $email ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $id ? aw_crm_message_to( $id ) : 'contact@animalwrites.in';
}

/* ---------- FluentCRM Pro's one-off email (profile → Emails → Send Email; not in FluentCRM free) ---------- */

// Only if FluentCRM Pro is ever added: its one-off email gets Reply-To = where they last wrote; first in the site's
// queue (mail-cap.php reads and removes X-AW-Priority); no List-Unsubscribe. Replies today use mail-reply.php.
add_filter(
	'fluent_crm/email_headers',
	function ( $headers, $data, $subscriber, $email_model ) {
		$camp = $email_model && $email_model->campaign ? $email_model->campaign : null;
		if ( ! $camp || 'custom_email_campaign' !== $camp->type ) {
			return $headers;
		}
		$headers   = array_values( array_filter( (array) $headers, fn( $h ) => ! preg_match( '/^(Reply-To|List-Unsubscribe(-Post)?)\s*:/i', (string) $h ) ) );
		$headers[] = 'Reply-To: Animal Writes <' . aw_crm_reply_to( $subscriber ? $subscriber->email : '' ) . '>';
		$headers[] = 'X-AW-Priority: 0';
		return $headers;
	},
	20,
	4
);

// A reply FluentCRM could not hand over at once (its sender was busy) is retried with the site's mail queue.
add_action(
	'mailweb_flush',
	function () {
		global $wpdb;
		if ( ! aw_crm_on() ) {
			return;
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT e.subscriber_id FROM {$wpdb->prefix}fc_campaign_emails e JOIN {$wpdb->prefix}fc_campaigns c ON c.id = e.campaign_id WHERE c.type = 'custom_email_campaign' AND e.status IN ('pending','scheduled') AND e.scheduled_at <= %s LIMIT 20", current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $sid ) {
			$c = \FluentCrm\App\Models\Subscriber::find( (int) $sid );
			if ( $c ) {
				do_action( 'fluentcrm_process_contact_jobs', $c );
			}
		}
	},
	5
);

/* ---------- Volunteers: tag "Volunteer" and their details in custom fields ---------- */

/** Custom fields (group "Volunteer"): slug => array( label, type ). Mirrors the volunteers table (volunteers-db.php). */
const AW_CRM_VOL_FIELDS = array(
	'vol_help'            => array( 'Volunteer: how they would like to help', 'text' ),
	'vol_state'           => array( 'Volunteer: state', 'text' ),
	'vol_district'        => array( 'Volunteer: district', 'text' ),
	'vol_city'            => array( 'Volunteer: city (as given)', 'text' ),
	'vol_pincode'         => array( 'Volunteer: PIN code', 'text' ),
	'vol_phone'           => array( 'Volunteer: phone', 'text' ),
	'vol_page'            => array( 'Volunteer: signed up on (page)', 'text' ),
	'vol_source'          => array( 'Volunteer: source', 'text' ),
	'vol_consent'         => array( 'Volunteer: consent', 'text' ),
	'vol_consent_version' => array( 'Volunteer: consent wording', 'text' ),
	'vol_consent_at'      => array( 'Volunteer: consent given (UTC)', 'text' ),
	'vol_status'          => array( 'Volunteer: status', 'text' ),
	'vol_joined'          => array( 'Volunteer: joined', 'date' ),
	// Filled by the Volunteer form's FluentCRM link (owner, 21 Oct 2026; /forms/5-volunteer-sign-up.json).
	'vol_availability'    => array( 'Volunteer: availability', 'text' ),
	'vol_travel_range'    => array( 'Volunteer: travel range', 'text' ),
	'vol_skills'          => array( 'Volunteer: skills', 'text' ),
	'vol_languages'       => array( 'Volunteer: languages', 'text' ),
	'vol_whatsapp'        => array( 'Volunteer: WhatsApp', 'text' ),
	'vol_wa_optin'        => array( 'Volunteer: OK on WhatsApp (1 = yes, empty = no)', 'text' ),
	'vol_organisation'    => array( 'Volunteer: organisation (as given)', 'text' ),
	'vol_org_show'        => array( 'Volunteer: show my name on the organisation\'s page (1 = yes, empty = no)', 'text' ),
	'vol_org_listing'     => array( 'Volunteer: organisation\'s listing ID (set by the owner when the name does not match)', 'text' ),
);

/** Custom fields (group "Forms"): the newsletter box on the last form a contact sent (owner, 21 Oct 2026). */
const AW_CRM_FORM_FIELDS = array(
	'nl_box_last'      => array( 'Newsletter box (last form): 1 or 0', 'text' ),
	'nl_box_form'      => array( 'Newsletter box (last form): which form', 'text' ),
	'nl_box_consent'   => array( 'Newsletter box (last form): consent wording', 'text' ),
	'campaign_ref'     => array( 'Campaign letter: reference', 'text' ),
	'campaign_last'    => array( 'Campaign letter: last campaign', 'text' ),
	'campaign_grounds' => array( 'Campaign letter: grounds chosen', 'text' ),
	'campaign_route'   => array( 'Campaign letter: sent with (gmail, outlook, mailto, copy)', 'text' ),
	'campaign_date'    => array( 'Campaign letter: date sent', 'text' ),
	'campaign_line'    => array( 'Campaign letter: line in their own words', 'text' ),
	'whatsapp'         => array( 'WhatsApp number (Contact form)', 'text' ),
	'wa_optin'         => array( 'OK on WhatsApp (Contact form; 1 = yes, 0 = no)', 'text' ),
);

/* ---------- The volunteer update link: /update/?t=<signed token> (owner, 21 Oct 2026) ---------- */

/** Days an update link works. */
const AW_UPDATE_LINK_DAYS = 30;

/**
 * A contact's update link: signed with the site's own secret (HMAC), for that one contact and their email, 30 days.
 * No name or email in the address (GA4 and Clarity record addresses). In FluentCRM emails: {{aw.update_link}}.
 *
 * @param object $c FluentCRM contact.
 */
function aw_update_link( $c ) {
	$exp = time() + AW_UPDATE_LINK_DAYS * DAY_IN_SECONDS;
	$sig = substr( hash_hmac( 'sha256', (int) $c->id . '|' . $exp . '|' . strtolower( (string) $c->email ), wp_salt( 'auth' ) ), 0, 32 );
	return add_query_arg( 't', (int) $c->id . '.' . $exp . '.' . $sig, home_url( '/update/' ) );
}

/**
 * The contact an update link is for.
 *
 * @param string $token The t value.
 * @return object|string FluentCRM contact, or 'expired' | 'invalid'.
 */
function aw_update_link_contact( $token ) {
	if ( ! aw_crm_on() || ! preg_match( '/^(\d+)\.(\d+)\.([a-f0-9]{32})$/', (string) $token, $m ) ) {
		return 'invalid';
	}
	$c   = \FluentCrm\App\Models\Subscriber::find( (int) $m[1] );
	$sig = $c ? substr( hash_hmac( 'sha256', (int) $m[1] . '|' . (int) $m[2] . '|' . strtolower( (string) $c->email ), wp_salt( 'auth' ) ), 0, 32 ) : '';
	if ( ! $c || ! hash_equals( $sig, $m[3] ) ) {
		return 'invalid';
	}
	return (int) $m[2] < time() ? 'expired' : $c;
}

// {{aw.update_link}} in FluentCRM emails (a fresh 30-day link for each contact when the email is made).
add_action(
	'init',
	function () {
		if ( aw_crm_on() ) {
			FluentCrmApi( 'extender' )->addSmartCode(
				'aw',
				'Animal Writes',
				array( 'update_link' => 'Volunteer update link (30 days)' ),
				fn( $code, $key, $default, $subscriber ) => 'update_link' === $key && $subscriber ? aw_update_link( $subscriber ) : $default,
				array( 'level' => 'credential' ) // like the unsubscribe link: only in an email to that contact
			);
		}
	}
);

/** Add the volunteer fields and their group to FluentCRM's custom fields (keeps any others). */
function aw_crm_fields_setup() {
	$fields = (array) fluentcrm_get_option( 'contact_custom_fields', array() );
	$have   = wp_list_pluck( $fields, 'slug' );
	$added  = 0;
	foreach ( array( 'Volunteer' => AW_CRM_VOL_FIELDS, 'Forms' => AW_CRM_FORM_FIELDS ) as $group => $list ) {
		foreach ( $list as $slug => $f ) {
			if ( ! in_array( $slug, $have, true ) ) {
				$fields[] = array( 'slug' => $slug, 'label' => $f[0], 'type' => $f[1], 'group' => $group );
				++$added;
			}
		}
	}
	if ( $added ) {
		fluentcrm_update_option( 'contact_custom_fields', array_values( $fields ) );
	}
	$groups = (array) ( fluentcrm_get_option( 'contact_field_groups' ) ? fluentcrm_get_option( 'contact_field_groups' ) : array( array( 'slug' => 'default', 'title' => 'Custom Profile Data' ) ) );
	$titles = wp_list_pluck( $groups, 'title' );
	foreach ( array( 'volunteer' => 'Volunteer', 'forms' => 'Forms' ) as $gslug => $gtitle ) {
		if ( ! in_array( $gtitle, $titles, true ) ) {
			$groups[] = array( 'slug' => $gslug, 'title' => $gtitle );
		}
	}
	if ( count( $groups ) !== count( $titles ) ) {
		fluentcrm_update_option( 'contact_field_groups', $groups );
	}
	return $added;
}

/**
 * Copy one volunteer (by email) from the volunteers table onto their FluentCRM contact: tag Volunteer and the custom
 * fields. An active volunteer with no contact yet is added as on sign-up (Newsletter list, subscribed). Sends nothing.
 *
 * @param string $email Email.
 * @return string 'updated' | 'created' | 'skipped'
 */
function aw_crm_volunteer_sync( $email ) {
	global $wpdb;
	static $places = null, $fields_ok = false;
	if ( ! aw_crm_on() || ! function_exists( 'aw_vol_table' ) ) {
		return 'skipped';
	}
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . aw_vol_table() . ' WHERE email = %s', strtolower( trim( (string) $email ) ) ), ARRAY_A ); // phpcs:ignore
	if ( ! $r ) {
		return 'skipped';
	}
	if ( ! $fields_ok ) {
		aw_crm_fields_setup();
		$fields_ok = true;
	}
	if ( null === $places ) {
		$places = array();
		foreach ( aw_lgd()['ids'] as $t ) {
			$places[ $t->term_id ] = $t;
		}
	}
	$st  = $places[ (int) $r['state_id'] ] ?? null;
	$di  = $places[ (int) $r['district_id'] ] ?? null;
	$c   = FluentCrmApi( 'contacts' )->getContact( $r['email'] );
	$res = 'updated';
	if ( ! $c ) {
		if ( 'active' !== $r['status'] ) {
			return 'skipped';
		}
		$c   = aw_crm_contact_for( $r['email'], $r['name'], array( 'volunteer' ), (string) $r['source'] ); // a contact, not a subscriber: the newsletter needs its own tick (owner, 20 Oct 2026)
		$res = 'created';
		if ( ! $c ) {
			return 'skipped';
		}
	}
	$roles = array_map( 'trim', explode( ',', (string) $r['role'] ) );
	$help  = 'v2_form' === $r['source'] && defined( 'AW_VOL_ROLES' ) ? implode( '; ', array_map( fn( $k ) => AW_VOL_ROLES[ $k ] ?? $k, array_filter( $roles ) ) ) : (string) $r['role'];
	$c->attachTags( aw_crm_tag_ids( array( 'volunteer' ) ) );
	$c->syncCustomFieldValues(
		array(
			'vol_help'            => $help,
			'vol_state'           => $st ? $st->name : '',
			'vol_district'        => $di ? $di->name : '',
			'vol_city'            => (string) $r['city_text'],
			'vol_pincode'         => (string) $r['pincode'],
			'vol_phone'           => (string) $r['phone'],
			'vol_page'            => (string) $r['page_source'],
			'vol_source'          => 'v1_campaign' === $r['source'] ? '1.0 campaign' : '2.0 form',
			'vol_consent'         => (string) $r['consent_scope'],
			'vol_consent_version' => (string) $r['consent_text_version'],
			'vol_consent_at'      => (string) $r['consent_at'],
			'vol_status'          => (string) $r['status'],
			'vol_joined'          => substr( (string) $r['created_at'], 0, 10 ),
		),
		false
	);
	return $res;
}

/* ---------- Forms: what a Fluent Forms → FluentCRM link cannot do (owner, 21 Oct 2026) ---------- */

/**
 * Record the newsletter box of the form a person just sent on their contact: 1 or 0, the form, the consent wording.
 * Fluent Forms saves nothing for an unticked box (the entry's empty value is the 0) and a link with a condition does
 * not run at all when it is unticked, so this is the one place a 0 reaches FluentCRM.
 *
 * @param object $c       FluentCRM contact.
 * @param bool   $ticked  The box.
 * @param string $form    Form name.
 * @param string $consent Consent wording version.
 */
function aw_crm_nl_box( $c, $ticked, $form, $consent ) {
	if ( $c ) {
		aw_crm_fields_setup();
		$c->syncCustomFieldValues( array( 'nl_box_last' => $ticked ? '1' : '0', 'nl_box_form' => (string) $form, 'nl_box_consent' => '' !== (string) $consent ? (string) $consent : 'none' ), false );
	}
}

/**
 * A Contact form entry as a note on the sender's contact (title "Contact form: <reason> (AW-<serial>)"): the reason,
 * the reason's own fields, the message, the WhatsApp number and tick, the page, and a link to the entry.
 *
 * @param object $c      FluentCRM contact.
 * @param int    $entry  Fluent Forms entry ID.
 * @param array  $data   The entry's values.
 * @param int    $form   Form ID.
 * @param string $ref    Reference (AW-<serial>).
 */
function aw_crm_entry_note( $c, $entry, $data, $form, $ref ) {
	if ( ! $c ) {
		return 0;
	}
	$about = defined( 'AW_CONTACT_ABOUT' ) ? ( AW_CONTACT_ABOUT[ (string) ( $data['about'] ?? '' ) ] ?? (string) ( $data['about'] ?? '' ) ) : (string) ( $data['about'] ?? '' );
	$labels = array( 'org_name' => 'Organisation', 'org' => 'Organisation', 'org_district' => 'District', 'org_phone' => 'Organisation phone', 'org_services' => 'Services', 'org_website' => 'Website', 'opp_title' => 'Opportunity', 'opp_type' => 'Type', 'opp_ongoing' => 'Ongoing', 'opp_start' => 'Starts', 'opp_end' => 'Ends', 'opp_link' => 'Link', 'whatsapp' => 'Phone or WhatsApp', 'wa_optin' => 'OK on WhatsApp', 'v_page_title' => 'Sent from' );
	$html   = '<strong>Reference:</strong> ' . esc_html( $ref ) . '<br><strong>Reason:</strong> ' . esc_html( $about ) . '<br>';
	foreach ( $labels as $k => $label ) {
		$v = $data[ $k ] ?? '';
		$v = is_array( $v ) ? implode( ', ', array_map( fn( $x ) => AW_SERVICES[ $x ] ?? (string) $x, $v ) ) : (string) $v;
		if ( 'wa_optin' === $k && '' !== (string) ( $data['whatsapp'] ?? '' ) ) {
			$v = '' !== $v ? '1 (yes)' : '0 (no)';
		}
		if ( '' !== trim( $v ) ) {
			$html .= '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $v ) . '<br>';
		}
	}
	$text  = trim( (string) ( $data['message'] ?? '' ) );
	$html  = '<p>' . $html . '</p>' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '' )
		. '<p><a href="' . esc_url( admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . (int) $form . '#/entries/' . (int) $entry ) ) . '">Open the entry in Fluent Forms</a></p>';
	$row = \FluentCrm\App\Models\SubscriberNote::create( array( 'subscriber_id' => (int) $c->id, 'type' => 'note', 'title' => 'Contact form: ' . $about . ( '' !== $ref ? ' (' . $ref . ')' : '' ), 'description' => $html, 'created_at' => current_time( 'mysql' ) ) );
	return $row ? (int) $row->id : 0;
}

/**
 * A campaign letter's sender into FluentCRM with FluentCRM's own API (owner, 21 Oct 2026), as the Volunteer form's link
 * would: Newsletter list (subscribed; someone who unsubscribed keeps that), tags newsletter, volunteer and
 * campaign-<slug> whatever the box says, the WhatsApp number and its tick, the newsletter box and the reference.
 *
 * @return int Contact ID, or 0.
 */
function aw_crm_campaign_signer( $email, $name, $city, $slug, $box, $whatsapp, $wa_optin, $ref, $consent ) {
	if ( ! aw_crm_on() ) {
		return 0;
	}
	$id = aw_crm_subscribe( $email, $name, array( 'city' => $city, 'source' => 'site:campaign:' . $slug ), array( 'newsletter', 'volunteer', 'campaign-' . $slug ) );
	$c  = $id ? \FluentCrm\App\Models\Subscriber::find( $id ) : null;
	if ( ! $c ) {
		return 0;
	}
	aw_crm_nl_box( $c, $box, 'Campaign letter', $consent );
	// the WhatsApp box as 1/0 every time, like the newsletter box (array_filter would drop the 0)
	$c->syncCustomFieldValues( array_filter( array( 'vol_city' => (string) $city, 'vol_page' => 'campaign:' . $slug, 'vol_source' => 'campaign letter', 'vol_whatsapp' => (string) $whatsapp, 'campaign_ref' => (string) $ref ) ) + array( 'vol_wa_optin' => $wa_optin ? '1' : '0' ), false );
	return $id;
}

/* ---------- Unsubscribes stay in step ---------- */

add_action(
	'fluentcrm_subscriber_status_to_unsubscribed',
	function ( $subscriber ) {
		global $wpdb;
		$wpdb->update( aw_nl_table(), array( 'status' => 'unsubscribed', 'updated_at' => current_time( 'mysql', true ) ), array( 'email' => strtolower( (string) $subscriber->email ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
);

// The site's own unsubscribe link (in the sign-up confirmation) also unsubscribes the FluentCRM contact.
add_action(
	'template_redirect',
	function () {
		if ( 'unsubscribe' !== sanitize_key( $_GET['aw_nl'] ?? '' ) || ! aw_crm_on() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$row   = 32 === strlen( $token ) ? aw_nl_get( 'token', $token ) : null;
		$c     = $row ? FluentCrmApi( 'contacts' )->getContact( $row['email'] ) : null;
		if ( $c && 'unsubscribed' !== $c->status ) {
			$c->updateStatus( 'unsubscribed' );
		}
	},
	5 // before newsletter.php's handler redirects
);

/* ---------- "Remove my data": the FluentCRM contact goes too ---------- */

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-fluentcrm'] = array(
			'eraser_friendly_name' => 'FluentCRM contact (newsletter, volunteer details, notes, emails)', // FluentCRM's delete removes its notes, fields and emails too
			'callback'             => function ( $email ) {
				$c = aw_crm_on() ? FluentCrmApi( 'contacts' )->getContact( strtolower( trim( (string) $email ) ) ) : null;
				if ( $c ) {
					\FluentCrm\App\Services\Helper::deleteContacts( array( (int) $c->id ) );
				}
				return array( 'items_removed' => (bool) $c, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);

/* ---------- Setup commands (send nothing) ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw crm-setup: sender, slowest sending speed, the Newsletter list and the volunteer-1.0 tag.
	WP_CLI::add_command(
		'aw crm-setup',
		function () {
			if ( ! aw_crm_on() ) {
				WP_CLI::error( 'FluentCRM is not active.' );
			}
			$all = (array) get_option( 'fluentcrm-global-settings', array() );
			$em  = \FluentCrm\App\Services\Helper::getGlobalEmailSettings();
			$em['from_name']         = 'Animal Writes';
			$em['from_email']        = function_exists( 'mailweb_smtp_settings' ) ? mailweb_smtp_settings()['user'] : get_option( 'admin_email' );
			$em['emails_per_second'] = 4; // the lowest FluentCRM accepts; the filter in mail-cap.php lowers it to 1
			$all['email_settings']   = $em;
			$biz                     = (array) ( $all['business_settings'] ?? array() );
			$biz['business_name']    = 'Animal Writes';
			$all['business_settings'] = $biz;
			update_option( 'fluentcrm-global-settings', $all );
			foreach ( AW_CRM_TAG_TITLES as $slug => $title ) { // the tags' visible names
				$tag = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $slug )->first();
				if ( $tag && $tag->title !== $title ) {
					$tag->title = $title;
					$tag->save();
				}
			}
			aw_crm_tag_ids( array_keys( AW_CRM_TAG_TITLES ) );
			WP_CLI::success( 'List ' . aw_crm_list_id() . ', tag ' . implode( ',', aw_crm_tag_ids( array( 'volunteer-1.0' ) ) ) . ', volunteer fields added ' . aw_crm_fields_setup() );
		}
	);

	// wp aw crm-import: the volunteers and the site's sign-ups onto both lists as subscribed / active.
	WP_CLI::add_command(
		'aw crm-import',
		function () {
			global $wpdb;
			add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind during the import
			$n     = array( 'volunteers' => 0, 'signups' => 0, 'failed' => 0 );
			$names = array();
			foreach ( aw_lgd()['ids'] as $t ) {
				$names[ $t->term_id ] = $t;
			}
			foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . aw_vol_table() . " WHERE status = 'active'", ARRAY_A ) as $r ) { // phpcs:ignore
				$st = $names[ (int) $r['state_id'] ] ?? null;
				$di = $names[ (int) $r['district_id'] ] ?? null;
				$f  = aw_crm_volunteer_fields( $st ? $st->slug : '', $di ? $di->slug : '', $r['city_text'], $r['pincode'], $r['phone'] );
				$id = aw_crm_subscribe( $r['email'], $r['name'], $f + array( 'source' => $r['source'] ), array( 'v1_campaign' === $r['source'] ? 'volunteer-1.0' : 'volunteer' ) );
				$id ? $n['volunteers']++ : $n['failed']++;
			}
			foreach ( (array) $wpdb->get_results( 'SELECT email, name, source, status FROM ' . aw_nl_table() . " WHERE status IN ('pending','invited','active')", ARRAY_A ) as $r ) { // phpcs:ignore
				if ( 'active' !== $r['status'] ) { // owner, 16 Oct 2026: the earlier sign-ups count as subscribed
					aw_nl_activate( $r['email'], $r['name'], $r['source'], 'owner-2026-10-16-signups' );
				}
				if ( 'v1_campaign' === $r['source'] ) {
					continue; // already added as a volunteer
				}
				$id = aw_crm_subscribe( $r['email'], $r['name'], array( 'source' => $r['source'] ), array( 'newsletter' ) );
				$id ? $n['signups']++ : $n['failed']++;
			}
			WP_CLI::success( wp_json_encode( $n ) );
		}
	);
	// wp aw crm-move (owner, 17 Oct 2026): volunteers onto their contacts (tag + fields), and every contact message and
	// incoming email as a note on its sender's contact. Safe to run again (no second notes). Sends nothing; counts only.
	WP_CLI::add_command(
		'aw crm-move',
		function () {
			global $wpdb;
			if ( ! aw_crm_on() ) {
				WP_CLI::error( 'FluentCRM is not active.' );
			}
			add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind
			$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fc_subscribers" ); // phpcs:ignore
			$n      = array( 'fields_added' => aw_crm_fields_setup(), 'volunteers' => array(), 'notes_new' => 0, 'notes_updated' => 0, 'messages_skipped' => 0 );
			foreach ( (array) $wpdb->get_col( 'SELECT email FROM ' . aw_vol_table() ) as $email ) { // phpcs:ignore
				$r                     = aw_crm_volunteer_sync( $email );
				$n['volunteers'][ $r ] = ( $n['volunteers'][ $r ] ?? 0 ) + 1;
			}
			foreach ( get_posts( array( 'post_type' => 'aw_message', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC' ) ) as $id ) {
				$had  = (int) get_post_meta( $id, 'aw_m_crm_note', true );
				$note = aw_crm_message_note( $id );
				if ( ! $note ) {
					++$n['messages_skipped'];
				} else {
					++$n[ $had ? 'notes_updated' : 'notes_new' ];
				}
			}
			$n['contacts_created'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fc_subscribers" ) - $before; // phpcs:ignore
			WP_CLI::success( wp_json_encode( $n ) );
		}
	);
}
