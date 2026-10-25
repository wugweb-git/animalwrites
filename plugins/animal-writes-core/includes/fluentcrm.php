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
 * - Volunteers are contacts tagged "Volunteer", with their volunteer details in custom fields (group "Volunteer").
 *   FluentCRM is the one volunteers table (owner, 24 Oct 2026): sign-ups write here only; the site's volunteers table
 *   ({prefix}aw_volunteers) is a read-only backup, copied in once with `wp aw vol-to-crm` (dry run first).
 * - Replies: the Inbox → Reply screen (mail-reply.php) was removed, and with it the Reply link on email notes (audit
 *   item 17, 25 Oct 2026). Emails are answered from Mailweb's Inbox (Reply, Reply all, Forward).
 * Setup (sends nothing): `wp aw crm-setup`, `wp aw crm-import`, `wp aw crm-move` (fields, notes), `wp aw vol-to-crm`.
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
	$html = '<p>' . $html . '</p>' . ( '' !== $text ? wpautop( esc_html( $text ) ) : '<p><em>(no text)</em></p>' )
		. '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">Open in Inbox → Contact messages</a></p>';
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
// queue (mail-cap.php reads and removes X-AW-Priority); no List-Unsubscribe.
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

/** Volunteer consent, narrowest first: a later sign-up only ever widens it. */
const AW_CRM_VOL_CONSENT_RANK = array( 'campaign_1_0' => 1, 'volunteering' => 2, 'volunteering_and_updates' => 3 );

/**
 * A tag's ID, or 0 when there is no such tag (unlike aw_crm_tag_ids(), creates nothing: dry runs use it).
 *
 * @param string $slug Tag slug.
 * @return int
 */
function aw_crm_tag_id( $slug ) {
	static $ids = array();
	if ( empty( $ids[ $slug ] ) ) { // a missing tag (0) is looked up again: it may be created later in the request
		$tag          = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $slug )->first();
		$ids[ $slug ] = $tag ? (int) $tag->id : 0;
	}
	return $ids[ $slug ];
}

/**
 * True when the contact has the tag.
 *
 * @param object $c    FluentCRM contact.
 * @param string $slug Tag slug.
 * @return bool
 */
function aw_crm_has_tag( $c, $slug ) {
	$id = aw_crm_tag_id( $slug );
	foreach ( $c->tags as $t ) {
		if ( (int) $t->id === $id ) {
			return true;
		}
	}
	return false;
}

/**
 * A volunteer sign-up (the site's own form) onto their FluentCRM contact, the one place volunteers are kept (owner,
 * 24 Oct 2026). The contact must exist (aw_newsletter_join makes it); it gets the Volunteer tag and the fields. What
 * the person sent replaces what was there, as the table did for a 2.0 sign-up; consent only widens; the join date is
 * kept. Sends nothing.
 *
 * @param string $email  Email.
 * @param array  $fields vol_* custom fields (empty values are left out).
 * @return bool False when there is no contact.
 */
function aw_crm_volunteer_signup( $email, $fields ) {
	$c = aw_crm_on() ? FluentCrmApi( 'contacts' )->getContact( strtolower( trim( (string) $email ) ) ) : null;
	if ( ! $c ) {
		return false;
	}
	aw_crm_fields_setup();
	$have = (array) $c->custom_fields();
	$now  = current_time( 'mysql', true );
	if ( ( AW_CRM_VOL_CONSENT_RANK[ $fields['vol_consent'] ?? '' ] ?? 0 ) <= ( AW_CRM_VOL_CONSENT_RANK[ $have['vol_consent'] ?? '' ] ?? 0 ) ) {
		unset( $fields['vol_consent'], $fields['vol_consent_version'] ); // their earlier, wider consent stays
	} else {
		$fields['vol_consent_at'] = $now;
	}
	if ( '' === (string) ( $have['vol_joined'] ?? '' ) ) {
		$fields['vol_joined'] = substr( $now, 0, 10 );
	}
	$c->attachTags( aw_crm_tag_ids( array( 'volunteer' ) ) );
	$c->syncCustomFieldValues( array_filter( $fields, fn( $v ) => '' !== (string) $v ) + array( 'vol_status' => 'active' ), false );
	return true;
}

/**
 * Volunteers as FluentCRM keeps them: contacts tagged Volunteer, except those whose Volunteer status is "deleted".
 * One query; the readers that used the volunteers table use this (owner, 24 Oct 2026).
 *
 * @return array[] email, name, status (the contact's), vol_status, vol_source, joined (Y-m-d), created_at.
 */
function aw_crm_volunteers() {
	global $wpdb;
	if ( ! aw_crm_on() ) {
		return array();
	}
	$p   = $wpdb->prefix;
	$tag = aw_crm_tag_id( 'volunteer' );
	$sql = "SELECT s.email, TRIM(CONCAT(s.first_name, ' ', s.last_name)) AS name, s.status, s.created_at,
		COALESCE(st.value, '') AS vol_status, COALESCE(so.value, '') AS vol_source, COALESCE(jo.value, '') AS joined
		FROM {$p}fc_subscribers s
		JOIN {$p}fc_subscriber_pivot t ON t.subscriber_id = s.id AND t.object_id = %d AND t.object_type = %s
		LEFT JOIN {$p}fc_subscriber_meta st ON st.subscriber_id = s.id AND st.object_type = 'custom_field' AND st.`key` = 'vol_status'
		LEFT JOIN {$p}fc_subscriber_meta so ON so.subscriber_id = s.id AND so.object_type = 'custom_field' AND so.`key` = 'vol_source'
		LEFT JOIN {$p}fc_subscriber_meta jo ON jo.subscriber_id = s.id AND jo.object_type = 'custom_field' AND jo.`key` = 'vol_joined'
		WHERE COALESCE(st.value, '') <> 'deleted' ORDER BY s.created_at DESC";
	$out = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $tag, 'FluentCrm\App\Models\Tag' ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$out[ strtolower( $r['email'] ) ] ??= $r; // one row per contact, whatever extra meta rows join in
	}
	return array_values( $out );
}

/* ---------- The volunteers table → FluentCRM, once (owner, 24 Oct 2026): fill gaps, never overwrite ---------- */

/**
 * A volunteers-table row as the Volunteer custom fields.
 *
 * @param array $r Table row.
 * @return array vol_* => value.
 */
function aw_crm_vol_row_fields( $r ) {
	static $places = null;
	if ( null === $places ) {
		$places = array();
		foreach ( aw_lgd()['ids'] as $t ) {
			$places[ $t->term_id ] = $t;
		}
	}
	$st    = $places[ (int) $r['state_id'] ] ?? null;
	$di    = $places[ (int) $r['district_id'] ] ?? null;
	$roles = array_map( 'trim', explode( ',', (string) $r['role'] ) );
	return array(
		'vol_help'            => 'v2_form' === $r['source'] && defined( 'AW_VOL_ROLES' ) ? implode( '; ', array_map( fn( $k ) => AW_VOL_ROLES[ $k ] ?? $k, array_filter( $roles ) ) ) : (string) $r['role'],
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
	);
}

/**
 * Copy one volunteers-table row into FluentCRM without overwriting anything. A person with no contact gets one,
 * tagged Volunteer, with the status their row allows (active: transactional, so no newsletter; unsubscribed:
 * unsubscribed). A row marked deleted is skipped, contact or not. On an existing contact only empty fields are filled,
 * the tag is added when missing and the status is never changed. Sends nothing.
 *
 * @param array $r     Table row.
 * @param bool  $apply False: only work out what would change.
 * @return array action (create | fill | none | skip), contact, status (an existing contact's), tag (bool), fields
 *               (filled), differ (set on both sides and different: kept as FluentCRM has it).
 */
function aw_crm_volunteer_copy( $r, $apply ) {
	$email = strtolower( trim( (string) $r['email'] ) );
	$plan  = array( 'action' => 'none', 'contact' => 0, 'tag' => false, 'fields' => array(), 'differ' => array() );
	if ( ! is_email( $email ) || 'deleted' === $r['status'] ) { // a deleted row gives FluentCRM nothing, contact or not
		$plan['action'] = 'skip';
		return $plan;
	}
	$want  = array_filter( aw_crm_vol_row_fields( $r ), fn( $v ) => '' !== trim( (string) $v ) );
	$parts = preg_split( '/\s+/', trim( (string) $r['name'] ), 2 );
	$core  = array_filter( array( 'first_name' => (string) ( $parts[0] ?? '' ), 'last_name' => (string) ( $parts[1] ?? '' ), 'phone' => (string) $r['phone'] ), fn( $v ) => '' !== trim( $v ) );
	$c     = FluentCrmApi( 'contacts' )->getContact( $email );
	if ( ! $c ) {
		$plan = array( 'action' => 'create', 'contact' => 0, 'tag' => true, 'fields' => array_keys( $core + $want ), 'differ' => array() );
		if ( $apply ) {
			$c = FluentCrmApi( 'contacts' )->createOrUpdate( $core + array( 'email' => $email, 'status' => 'unsubscribed' === $r['status'] ? 'unsubscribed' : 'transactional', 'source' => (string) $r['source'] ) );
			if ( ! $c ) {
				$plan['action'] = 'skip';
				return $plan;
			}
			$plan['contact'] = (int) $c->id;
			$c->attachTags( aw_crm_tag_ids( array( 'volunteer' ) ) );
			$c->syncCustomFieldValues( $want, false );
		}
		return $plan;
	}
	$plan['contact'] = (int) $c->id;
	$plan['status']  = (string) $c->status;
	$plan['tag']     = ! aw_crm_has_tag( $c, 'volunteer' );
	$have            = (array) $c->custom_fields();
	$same            = fn( $a, $b ) => 0 === strcasecmp( trim( (string) $a ), trim( (string) $b ) );
	$fill_core       = array();
	if ( isset( $core['first_name'] ) && '' === trim( (string) $c->first_name ) && '' === trim( (string) $c->last_name ) ) {
		$fill_core = array_intersect_key( $core, array_flip( array( 'first_name', 'last_name' ) ) ); // the name only as a whole
	} elseif ( isset( $core['first_name'] ) && ! $same( trim( $c->first_name . ' ' . $c->last_name ), $r['name'] ) ) {
		$plan['differ'][] = 'name';
	}
	if ( isset( $core['phone'] ) ) {
		if ( '' === trim( (string) $c->phone ) ) {
			$fill_core['phone'] = $core['phone'];
		} elseif ( ! $same( $c->phone, $core['phone'] ) ) {
			$plan['differ'][] = 'phone';
		}
	}
	$fill = array();
	foreach ( $want as $k => $v ) {
		if ( '' === trim( (string) ( $have[ $k ] ?? '' ) ) ) {
			$fill[ $k ] = $v;
		} elseif ( ! $same( $have[ $k ], $v ) ) {
			$plan['differ'][] = $k;
		}
	}
	$plan['fields'] = array_keys( $fill_core + $fill );
	if ( $plan['tag'] || $plan['fields'] ) {
		$plan['action'] = 'fill';
	}
	if ( $apply && 'fill' === $plan['action'] ) {
		if ( $plan['tag'] ) {
			$c->attachTags( aw_crm_tag_ids( array( 'volunteer' ) ) );
		}
		if ( $fill ) {
			$c->syncCustomFieldValues( $fill, false );
		}
		if ( $fill_core ) {
			foreach ( $fill_core as $k => $v ) {
				$c->{$k} = $v;
			}
			$c->save();
		}
	}
	return $plan;
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

	// wp aw crm-import: the site's sign-ups onto both lists as subscribed / active.
	WP_CLI::add_command(
		'aw crm-import',
		function () {
			global $wpdb;
			add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind during the import
			$n = array( 'signups' => 0, 'failed' => 0 );
			// Volunteers: no longer from here (owner, 24 Oct 2026). FluentCRM is the volunteers table; the table's rows go in
			// once with `wp aw vol-to-crm`, which never subscribes anyone.
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
	// wp aw crm-move (owner, 17 Oct 2026): every contact message and incoming email as a note on its sender's contact.
	// Safe to run again (no second notes). Sends nothing; counts only.
	WP_CLI::add_command(
		'aw crm-move',
		function () {
			global $wpdb;
			if ( ! aw_crm_on() ) {
				WP_CLI::error( 'FluentCRM is not active.' );
			}
			add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind
			$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fc_subscribers" ); // phpcs:ignore
			$n      = array( 'fields_added' => aw_crm_fields_setup(), 'notes_new' => 0, 'notes_updated' => 0, 'messages_skipped' => 0 ); // volunteers: wp aw vol-to-crm
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

	// wp aw vol-to-crm [--apply] [--ignore-automations] (owner, 24 Oct 2026): the volunteers table's people and fields
	// into FluentCRM, filling gaps only (aw_crm_volunteer_copy). Without --apply nothing changes: it prints the diff
	// (matched by lowercased email) and the plan. Counts only, never a person's details. --apply sends nothing and logs
	// every change (row and contact IDs, what was added, no values) to aw-private/vol-to-crm-<time>.jsonl.
	WP_CLI::add_command(
		'aw vol-to-crm',
		function ( $args, $assoc ) {
			global $wpdb;
			if ( ! aw_crm_on() ) {
				WP_CLI::error( 'FluentCRM is not active.' );
			}
			$apply   = ! empty( $assoc['apply'] );
			$p       = $wpdb->prefix;
			$funnels = (array) $wpdb->get_col( "SELECT trigger_name FROM {$p}fc_funnels WHERE status = 'published'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table prefix only
			if ( $apply && $funnels && empty( $assoc['ignore-automations'] ) ) {
				WP_CLI::error( count( $funnels ) . ' published FluentCRM automation(s) (' . implode( ', ', array_unique( $funnels ) ) . ') could email someone on a new tag or contact. Pause them first, or pass --ignore-automations.' );
			}
			$log = '';
			if ( $apply ) {
				add_filter( 'pre_wp_mail', '__return_false', 1 ); // no email of any kind
				aw_crm_fields_setup();
				$log = aw_private_dir() . '/vol-to-crm-' . gmdate( 'Ymd-His' ) . '.jsonl';
			}
			$tagged = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT LOWER(TRIM(s.email)) FROM {$p}fc_subscribers s JOIN {$p}fc_subscriber_pivot t ON t.subscriber_id = s.id AND t.object_type = %s AND t.object_id = %d", 'FluentCrm\App\Models\Tag', aw_crm_tag_id( 'volunteer' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table prefix only
			$rows   = (array) $wpdb->get_results( 'SELECT * FROM ' . aw_vol_table() . ' ORDER BY id', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- no input
			$add    = function ( &$a, $k ) {
				$a[ $k ] = ( $a[ $k ] ?? 0 ) + 1;
			};
			$n      = array(
				'table_rows'             => count( $rows ),
				'table_status'           => array(),
				'fluentcrm_tagged'       => count( $tagged ),
				'in_both'                => 0,
				'in_both_not_tagged'     => 0,
				'only_table'             => array(),
				'skipped'                => array(),
				'only_fluentcrm'         => 0,
				'matched_contact_status' => array(),
				'fields_differ_kept'     => array(),
				'plan'                   => array(),
				'tag_to_add'             => 0,
				'fields_to_fill'         => array(),
				'automations_published'  => count( $funnels ),
			);
			$emails = array();
			foreach ( $rows as $r ) {
				$emails[ strtolower( trim( (string) $r['email'] ) ) ] = true;
				$add( $n['table_status'], (string) $r['status'] );
				$plan = aw_crm_volunteer_copy( $r, $apply );
				$add( $n['plan'], $plan['action'] );
				if ( 'skip' === $plan['action'] ) { // marked deleted, or no valid email: not copied, not matched
					$add( $n['skipped'], (string) $r['status'] );
				} elseif ( 'create' === $plan['action'] ) {
					$add( $n['only_table'], (string) $r['status'] );
				} else {
					++$n[ $plan['tag'] ? 'in_both_not_tagged' : 'in_both' ];
					$add( $n['matched_contact_status'], $plan['status'] );
				}
				$n['tag_to_add'] += (int) $plan['tag'];
				foreach ( $plan['fields'] as $k ) {
					$add( $n['fields_to_fill'], $k );
				}
				foreach ( $plan['differ'] as $k ) {
					$add( $n['fields_differ_kept'], $k );
				}
				if ( $apply && in_array( $plan['action'], array( 'create', 'fill' ), true ) ) {
					file_put_contents( $log, wp_json_encode( array( 'at' => gmdate( 'c' ), 'row' => (int) $r['id'], 'contact' => $plan['contact'], 'action' => $plan['action'], 'tag_added' => $plan['tag'], 'fields_filled' => $plan['fields'] ) ) . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			}
			$n['only_fluentcrm'] = count( array_diff_key( array_flip( $tagged ), $emails ) );
			WP_CLI::log( wp_json_encode( $n, JSON_PRETTY_PRINT ) );
			WP_CLI::success( $apply ? 'Applied. Log: ' . $log : 'Dry run: nothing changed. Run again with --apply to copy.' );
		}
	);
}
