<?php
/**
 * FluentCRM (owner, 16 Oct 2026): one list, "Newsletter"; volunteers are newsletter subscribers.
 *
 * - The footer sign-up, the forms' newsletter box and every volunteer sign-up (all Fluent Forms, each with its own
 *   FluentCRM link) add the person to the Newsletter list as "subscribed". FluentCRM sends no email for this. The
 *   site's own list ({prefix}aw_subscribers) is a backup nothing adds to any more (newsletter.php).
 * - The two lists stay in step on unsubscribes: FluentCRM's unsubscribe marks the site's row unsubscribed (below), and
 *   the site's unsubscribe link unsubscribes the FluentCRM contact (newsletter.php).
 * - Campaigns go out through wp_mail (the site's mailbox, kits-core) under the daily cap in mail-cap.php.
 *
 * FluentCRM is where people are kept (owner, 17 Oct 2026):
 * - Every Contact form entry finds or creates the sender's contact and adds a note (aw_crm_entry_note, called from
 *   forms-fluentforms.php). A contact made this way is "transactional" (never sent the newsletter); an existing contact
 *   keeps its status. Tag "Contact form".
 * - Volunteers are contacts tagged "Volunteer", with their volunteer details in custom fields (group "Volunteer").
 *   FluentCRM is the one volunteers table (owner, 24 Oct 2026): sign-ups write here only; the site's volunteers table
 *   ({prefix}aw_volunteers) is a read-only backup, copied in once.
 * - Replies: the Inbox → Reply screen (mail-reply.php) was removed, and with it the Reply link on email notes (audit
 *   item 17, 25 Oct 2026). Emails are answered from Mailweb's Inbox (Reply, Reply all, Forward).
 * Setup (sends nothing): `wp aw crm-setup`. The one-time `wp aw crm-import`, `wp aw crm-move` and `wp aw vol-to-crm`
 * ran and were removed with the site's own forms (owner, 25 Oct 2026; see git history).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CRM_LIST = 'newsletter';

/**
 * Block list (owner, 1 Nov 2026): addresses deleted from FluentCRM as junk that must never come back as contacts.
 * SHA-256 of the lowercased, trimmed address, so the code holds no copy of it. Checked by aw_crm_subscribe(),
 * aw_crm_contact_for(), Mailweb's inbox sync and the Fluent Forms → FluentCRM feed (below).
 * Add a hash with: php -r 'echo hash("sha256","name@example.com");'
 */
const AW_CRM_BLOCKED_EMAILS = array(
	'8b600ae0de9fda16c7aea7c0daf03ee9ef9d54c8b7861a9da0f140555a206d15',
	'c5d30eda4914993eaabded86416e212400abdc3a0f15eb05b7d85ec0defbc445',
	'1f88974f397859a9d3d4030c7cb7a87dde909e4960014743ad4cf518b9911dcd',
	'907d7bf93d60cbe6565458bb0fe7e8fac60dffe3561e27cce320947f1a13ef8b',
	'1d2da3c794ec0b0c4e2017b9ff3ea4ddacb88793fd3f89bd78b537141f29a715',
	'831cb478c3fff5a97b54cf9419ba3862f304a25458288949db3c9cfed3f711a0',
	'f4b83dfa3e0820c4332493dd1230f0a162a98cced8150d5c1c58f94db0fc2055',
);

/** Whether an email address is on the block list. */
function aw_crm_blocked( $email ) {
	$email = strtolower( trim( (string) $email ) );
	return '' !== $email && in_array( hash( 'sha256', $email ), (array) apply_filters( 'aw_crm_blocked_emails', AW_CRM_BLOCKED_EMAILS ), true );
}

add_filter(
	'mailweb_crm_skip_email',
	function ( $skip, $email ) {
		return $skip || aw_crm_blocked( $email );
	},
	10,
	2
);

/*
 * Fluent Forms → FluentCRM feeds create contacts inside FluentCRM, which has no hook before it creates one. For a
 * blocked address the feed's handler (priority 10) is taken off for that one run and put back straight after.
 */
add_action(
	'fluentform/integration_notify_fluentcrm_feeds',
	function ( $feed, $form_data ) {
		$email = (string) ( $feed['processedValues']['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			$email = (string) ( is_array( $form_data ) ? ( $form_data[ $email ] ?? '' ) : '' );
		}
		if ( ! aw_crm_blocked( $email ) ) {
			return;
		}
		global $wp_filter;
		$hook = 'fluentform/integration_notify_fluentcrm_feeds';
		$cbs  = isset( $wp_filter[ $hook ] ) ? (array) ( $wp_filter[ $hook ]->callbacks[10] ?? array() ) : array();
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( ! is_array( $fn ) || ! is_object( $fn[0] ) || 'notify' !== $fn[1] || ! str_starts_with( get_class( $fn[0] ), 'FluentCrm\\' ) ) {
				continue;
			}
			remove_action( $hook, $fn, 10 );
			$restore = function () use ( $hook, $fn, &$restore ) {
				add_action( $hook, $fn, 10, 4 );
				remove_action( $hook, $restore, 11 );
			};
			add_action( $hook, $restore, 11, 0 );
		}
	},
	9,
	2
);

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
	'campaign-own-line'   => 'Campaign: own line', // signed a campaign letter with their own line (owner, 26 Oct 2026: a filter to reply to each)
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
	if ( ! $list || ! is_email( $email ) || aw_crm_blocked( $email ) ) {
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

/* ---------- Unticked: on the list all the same (owner, 31 Oct 2026) ---------- */

/**
 * A sender who left "Email me updates" unticked: on the Newsletter list all the same, with everything the form's
 * FluentCRM link would have saved (the link itself runs only when the box is ticked). The box is recorded as 1/0
 * (aw_crm_nl_box()); no form changes a contact's status (owner, 1 Nov 2026).
 *
 * @param object $c    FluentCRM contact (aw_crm_contact_for()).
 * @param int    $form Fluent Forms form ID.
 * @param array  $data The submission.
 */
function aw_crm_unticked( $c, $form, $data ) {
	global $wpdb;
	$list = aw_crm_list_id();
	if ( $list ) {
		$c->attachLists( array( $list ) );
	}
	$skip  = aw_crm_tag_ids( array( 'newsletter' ) );
	$feeds = (array) $wpdb->get_col( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = 'fluentcrm_feeds'", (int) $form ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$value = function ( $tpl ) use ( $data ) { // {inputs.name} → the sent value (a list joined with commas)
		return trim( (string) preg_replace_callback( '/\{inputs\.([a-z0-9_]+)\}/i', fn( $m ) => is_array( $data[ $m[1] ] ?? '' ) ? implode( ', ', array_map( 'strval', $data[ $m[1] ] ) ) : (string) ( $data[ $m[1] ] ?? '' ), (string) $tpl ) );
	};
	foreach ( $feeds as $json ) {
		$f    = (array) json_decode( (string) $json, true );
		$cond = (array) ( $f['conditionals']['conditions'] ?? array() );
		if ( ! array_filter( $cond, fn( $x ) => 'nl_optin' === ( $x['field'] ?? '' ) ) ) {
			continue; // only the link that runs for a ticked box
		}
		$fields = array();
		foreach ( (array) ( $f['other_fields'] ?? array() ) as $o ) {
			$v = $value( $o['item_value'] ?? '' );
			if ( '' !== (string) ( $o['label'] ?? '' ) && '' !== $v ) {
				$fields[ (string) $o['label'] ] = $v;
			}
		}
		if ( isset( $fields['phone'] ) ) { // the contact's own phone field, not a custom one
			if ( '' === (string) $c->phone ) {
				$c->phone = $fields['phone'];
				$c->save();
			}
			unset( $fields['phone'] );
		}
		if ( $fields ) {
			$c->syncCustomFieldValues( $fields, false );
		}
		$tags = array_values( array_diff( array_map( 'intval', (array) ( $f['tag_ids'] ?? array() ) ), $skip ) );
		if ( $tags ) {
			$c->attachTags( $tags );
		}
	}
}

/**
 * A claim on the claimant's contact (owner, 31 Oct 2026: capture all info): the listing, role, phone, WhatsApp, "Show my
 * name", the reference and a link to the entry.
 */
function aw_crm_claim_note( $c, $entry, $data, $form, $ref ) {
	if ( ! $c ) {
		return 0;
	}
	$lid  = absint( $data['listing'] ?? 0 );
	$rows = array(
		'Reference'            => $ref,
		'Listing'              => $lid ? get_the_title( $lid ) . ' (' . $lid . ')' : '',
		'Role'                 => defined( 'AW_CLAIM_ROLES' ) ? ( AW_CLAIM_ROLES[ (string) ( $data['role'] ?? '' ) ] ?? (string) ( $data['role'] ?? '' ) ) : (string) ( $data['role'] ?? '' ),
		'Phone'                => (string) ( $data['phone'] ?? '' ),
		'WhatsApp'             => (string) ( $data['whatsapp'] ?? '' ),
		'OK on WhatsApp'       => '' !== (string) ( $data['whatsapp'] ?? '' ) ? ( aw_ff_ticked( $data, 'wa_optin' ) ? '1 (yes)' : '0 (no)' ) : '',
		'Show name on listing' => 'yes' === (string) ( $data['show_on_listing'] ?? '' ) ? 'Yes' : 'No',
		'Email me updates'     => aw_ff_ticked( $data, 'nl_optin' ) ? '1 (yes)' : '0 (no)',
	);
	$html = '';
	foreach ( $rows as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			$html .= '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( (string) $v ) . '<br>';
		}
	}
	$notes = trim( (string) ( $data['notes'] ?? '' ) );
	$html  = '<p>' . $html . '</p>' . ( '' !== $notes ? wpautop( esc_html( $notes ) ) : '' )
		. '<p><a href="' . esc_url( admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . (int) $form . '#/entries/' . (int) $entry ) ) . '">Open the entry in Fluent Forms</a></p>';
	$row = \FluentCrm\App\Models\SubscriberNote::create( array( 'subscriber_id' => (int) $c->id, 'type' => 'note', 'title' => 'Claim: ' . ( $lid ? get_the_title( $lid ) : 'listing' ) . ( '' !== $ref ? ' (' . $ref . ')' : '' ), 'description' => $html, 'created_at' => current_time( 'mysql' ) ) );
	return $row ? (int) $row->id : 0;
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
	if ( ! aw_crm_on() || ! is_email( $email ) || aw_crm_blocked( $email ) || str_ends_with( $email, '@animalwrites.in' ) || preg_match( '/^(mailer-daemon|postmaster|no-?reply)@/i', $email ) ) {
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

/** Reply-To for an email to a person: always support@, the address for all mail with people (owner, 31 Oct 2026). */
function aw_crm_reply_to( $email = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- kept for callers
	return 'support@animalwrites.in';
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
	'vol_org_role'        => array( 'Organisation: role (from an approved claim)', 'text' ),
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

/* ---------- Dashboard panels (owner, 1 Nov 2026) ---------- */

// Getting Started: FluentCRM shows the checklist only while its dashboard data carries onboarding steps.
add_filter(
	'fluent_crm/dashboard_data',
	function ( $data ) {
		if ( is_array( $data ) ) {
			$data['onboarding'] = null;
		}
		return $data;
	}
);

// The greeting header, Quick Links and the "Do more with FluentCRM Pro" card have no filter, so they are hidden with
// a few lines of CSS on FluentCRM's own stylesheet, on FluentCRM's admin page only. The Pro card is the one linking to
// FluentCRM's Pro page; the integration "Recommendation" card links there too, so it goes as well.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'toplevel_page_fluentcrm-admin' !== $hook || ! aw_crm_on() || ! wp_style_is( 'fluentcrm_app_global', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_style(
			'fluentcrm_app_global',
			'.fluentcrm_admin_dashboard .fcrm_dashboard_user,.fluentcrm_admin_dashboard .fcrm_quick_links_wrap,'
			. '.fluentcrm_admin_dashboard .fcrm_base_card:has(a[href*="utm_campaign=pro"]){display:none}'
		);
	},
	20
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
}
