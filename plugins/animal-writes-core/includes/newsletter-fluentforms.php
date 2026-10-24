<?php
/**
 * Newsletter sign-up in Fluent Forms (owner, 16 Oct 2026).
 *
 * - [aw_newsletter] (footer, the "Newsletter box" pattern, campaign pages) shows the Fluent Forms form "Newsletter"
 *   when Fluent Forms is active; otherwise the site's own form in newsletter.php, which stays installed.
 * - Each sign-up goes to FluentCRM's Newsletter list (subscribed, tag newsletter) by the form's own FluentCRM link.
 * - Fluent Forms sends one confirmation email per sign-up through wp_mail (the site's mailbox, kits-core), as it
 *   happens. newsletter.php sends nothing for these. The email carries the unsubscribe link.
 * - "Remove my data" also removes the person's Fluent Forms entries.
 * Setup: `wp aw nl-form` (creates the form once), `wp aw nl-import-volunteers` (sends nothing).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NL_FF_OPTION = 'aw_nl_ff_form';

/** The Fluent Forms form ID, or 0 when Fluent Forms is off or the form is missing. */
function aw_nl_ff_id() {
	$id = (int) get_option( AW_NL_FF_OPTION );
	return $id && defined( 'FLUENTFORM' ) ? $id : 0;
}

/**
 * Add someone to the subscriber list as active, or update them if they are there (one row per email).
 *
 * @return string|false 'added', 'updated' or false.
 */
function aw_nl_activate( $email, $name, $source, $consent ) {
	global $wpdb;
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	$row = aw_nl_get( 'email', $email );
	if ( $row ) {
		$upd = array( 'status' => 'active', 'updated_at' => $now );
		if ( '' !== trim( (string) $name ) ) {
			$upd['name'] = $name;
		}
		if ( 'active' !== $row['status'] ) {
			$upd['consent_text_version'] = $consent;
			$upd['consent_at']           = $now;
		}
		$wpdb->update( aw_nl_table(), $upd, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return 'updated';
	}
	$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		aw_nl_table(),
		array( 'email' => $email, 'name' => (string) $name, 'source' => $source, 'status' => 'active', 'token' => bin2hex( random_bytes( 16 ) ), 'consent_text_version' => $consent, 'consent_at' => $now, 'created_at' => $now, 'updated_at' => $now )
	);
	return $ok ? 'added' : false;
}

/* ---------- The form ---------- */

/** [aw_newsletter]: the Fluent Forms form, in the same box (data-kit-form names it for analytics). */
function aw_newsletter_ff_form() {
	$id = aw_nl_ff_id();
	if ( ! $id ) {
		return aw_newsletter_form();
	}
	return '<div class="aw-newsletter-form" data-kit-form="newsletter">' . do_shortcode( '[fluentform id="' . $id . '"]' ) . '</div>';
}
add_action(
	'init',
	function () {
		remove_shortcode( 'aw_newsletter' );
		add_shortcode( 'aw_newsletter', 'aw_newsletter_ff_form' );
	},
	20
);

// Each sign-up is a FluentCRM contact through the form's own Fluent Forms → FluentCRM link (everyone → Newsletter list,
// tag newsletter; /forms/3-newsletter.json). The site's subscriber table is no longer written by the form (owner, 21 Oct
// 2026: FluentCRM keeps people); its rows stay as the backup.

// The confirmation email: plain (no Fluent Forms template or credit) and with this person's unsubscribe link.
add_filter( 'fluentform/send_plain_html_email', fn( $plain, $form ) => (int) $form->id === aw_nl_ff_id() ? true : $plain, 10, 2 );
add_filter(
	'fluentform/email_body',
	function ( $body, $notification, $data, $form ) {
		if ( (int) $form->id !== aw_nl_ff_id() ) {
			return $body;
		}
		$row = aw_nl_get( 'email', strtolower( trim( (string) ( $data['email'] ?? '' ) ) ) );
		return $row ? str_replace( '%%aw_unsubscribe%%', '<a href="' . esc_url( aw_nl_link( 'unsubscribe', $row['token'] ) ) . '">' . esc_html( aw_nl_link( 'unsubscribe', $row['token'] ) ) . '</a>', $body ) : $body;
	},
	10,
	4
);

/* ---------- "Remove my data": Fluent Forms entries ---------- */

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['aw-fluentforms'] = array(
			'eraser_friendly_name' => 'Form entries (Fluent Forms)',
			'callback'             => function ( $email ) {
				global $wpdb;
				$ids = array();
				if ( defined( 'FLUENTFORM' ) ) {
					$email = strtolower( trim( (string) $email ) );
					$like  = '%' . $wpdb->esc_like( '"' . $email . '"' ) . '%';
					$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, form_id FROM {$wpdb->prefix}fluentform_submissions WHERE LOWER(response) LIKE %s OR id IN (SELECT submission_id FROM {$wpdb->prefix}fluentform_entry_details WHERE LOWER(field_value) = %s)", $like, $email ) ); // phpcs:ignore
					$by    = array();
					foreach ( $rows as $r ) {
						$by[ (int) $r->form_id ][] = (int) $r->id;
						$ids[]                     = (int) $r->id;
					}
					foreach ( $by as $form_id => $sub_ids ) { // Fluent Forms removes entries one form at a time
						\FluentForm\App\Models\Submission::remove( $sub_ids, $form_id );
					}
				}
				return array( 'items_removed' => (bool) $ids, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);

/* ---------- Setup commands ---------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// wp aw nl-form: create the Fluent Forms form "Newsletter" once (and its confirmation email).
	WP_CLI::add_command(
		'aw nl-form',
		function () {
			global $wpdb;
			if ( ! defined( 'FLUENTFORM' ) ) {
				WP_CLI::error( 'Fluent Forms is not active.' );
			}
			$have = (int) get_option( AW_NL_FF_OPTION );
			if ( $have && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE id = %d", $have ) ) ) { // phpcs:ignore
				WP_CLI::success( "Form already set up: {$have}" );
				return;
			}
			$req = fn( $msg ) => array( 'value' => true, 'message' => $msg );
			$off = array( 'type' => 'any', 'status' => false, 'conditions' => array( array( 'field' => '', 'value' => '', 'operator' => '' ) ) );
			$fields = array(
				'fields'       => array(
					array(
						'index'      => 1,
						'element'    => 'input_email',
						'attributes' => array( 'type' => 'email', 'name' => 'email', 'value' => '', 'id' => '', 'class' => '', 'placeholder' => 'you@example.com', 'autocomplete' => 'email' ),
						'settings'   => array( 'container_class' => '', 'label' => 'Email', 'label_placement' => '', 'help_message' => '', 'admin_field_label' => 'Email', 'validation_rules' => array( 'required' => $req( 'Enter your email address' ), 'email' => $req( 'Enter a full email address, like you@example.com' ) ), 'conditional_logics' => array(), 'is_unique' => 'no', 'unique_validation_message' => '' ),
						'editor_options' => array( 'title' => 'Email Address', 'icon_class' => 'ff-edit-email', 'template' => 'inputText' ),
						'uniqElKey'  => 'el_aw_nl_email',
					),
					array(
						'index'      => 2,
						'element'    => 'input_text',
						'attributes' => array( 'type' => 'text', 'name' => 'name', 'value' => '', 'id' => '', 'class' => '', 'placeholder' => 'Asha', 'maxlength' => 80, 'autocomplete' => 'name' ),
						'settings'   => array( 'container_class' => '', 'label' => 'Your name <span class="kit-optional">(optional)</span>', 'label_placement' => '', 'admin_field_label' => 'Name', 'help_message' => '', 'validation_rules' => array( 'required' => array( 'value' => false, 'message' => '' ) ), 'conditional_logics' => array() ),
						'editor_options' => array( 'title' => 'Simple Text', 'icon_class' => 'ff-edit-text', 'template' => 'inputText' ),
						'uniqElKey'  => 'el_aw_nl_name',
					),
					array(
						'index'      => 3,
						'element'    => 'custom_html',
						'attributes' => array(),
						'settings'   => array( 'html_codes' => '<p>' . esc_html( AW_NL_OPTIN_LABEL ) . '</p>', 'conditional_logics' => array(), 'container_class' => '' ),
						'editor_options' => array( 'title' => 'Custom HTML', 'icon_class' => 'ff-edit-html', 'template' => 'customHTML' ),
						'uniqElKey'  => 'el_aw_nl_consent',
					),
				),
				'submitButton' => array(
					'uniqElKey'      => 'el_aw_nl_submit',
					'element'        => 'button',
					'attributes'     => array( 'type' => 'submit', 'class' => 'kit-btn' ),
					'settings'       => array( 'align' => 'left', 'button_style' => '', 'container_class' => '', 'help_message' => '', 'background_color' => '', 'button_size' => 'md', 'color' => '', 'button_ui' => array( 'type' => 'default', 'text' => 'Subscribe', 'img_url' => '' ) ),
					'editor_options' => array( 'title' => 'Submit Button' ),
				),
			);
			$now = current_time( 'mysql' );
			$wpdb->insert( "{$wpdb->prefix}fluentform_forms", array( 'title' => 'Newsletter', 'status' => 'published', 'form_fields' => wp_json_encode( $fields ), 'has_payment' => 0, 'type' => 'form', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				WP_CLI::error( 'Could not create the form.' );
			}
			$settings = array(
				'confirmation' => array( 'redirectTo' => 'samePage', 'messageToShow' => '<p class="aw-form-done-title">You\'re subscribed</p><p>We\'ve sent a confirmation to {inputs.email}.</p>', 'customPage' => null, 'samePageFormBehavior' => 'hide_form', 'customUrl' => null ),
				'restrictions' => array( 'denyEmptySubmission' => array( 'enabled' => true, 'message' => 'Enter your email address' ) ),
				'layout'       => array( 'labelPlacement' => 'top', 'helpMessagePlacement' => 'with_label', 'errorMessagePlacement' => 'inline', 'asteriskPlacement' => 'none' ),
			);
			$mail = array(
				'name'         => 'Confirmation to the subscriber',
				'sendTo'       => array( 'type' => 'field', 'email' => '', 'field' => 'email', 'routing' => array() ),
				'fromName'     => '',
				'fromEmail'    => '',
				'replyTo'      => '',
				'bcc'          => '',
				'subject'      => 'Your subscription to Animal Writes',
				'message'      => '<p>Thank you for signing up. You will get Animal Writes updates at this address.</p><p>If you did not ask for this, use the link below and you will not hear from us.</p><p>--<br>To stop these emails: %%aw_unsubscribe%%</p>',
				'conditionals' => array( 'status' => false, 'type' => 'all', 'conditions' => array() ),
				'enabled'      => true,
				'email_template' => '',
			);
			$meta = array( 'formSettings' => $settings, 'notifications' => $mail, '_primary_email_field' => 'email', '_ff_selected_style' => 'ffs_inherit_theme', 'step_data_persistency_status' => 'no' );
			foreach ( $meta as $k => $v ) {
				$wpdb->insert( "{$wpdb->prefix}fluentform_form_meta", array( 'form_id' => $id, 'meta_key' => $k, 'value' => is_string( $v ) ? $v : wp_json_encode( $v ) ) ); // phpcs:ignore
			}
			update_option( AW_NL_FF_OPTION, $id, false );
			WP_CLI::success( "Form created: {$id}" );
		}
	);

	// wp aw nl-import-volunteers: the volunteers onto the subscriber list as active (owner, 16 Oct 2026). Sends nothing.
	// Reads FluentCRM, the volunteers table since 24 Oct 2026: active volunteers (a Fluent Forms sign-up has no status
	// field yet: active) whose contact has not left the newsletter.
	WP_CLI::add_command(
		'aw nl-import-volunteers',
		function () {
			add_filter( 'pre_wp_mail', '__return_false', 999 );
			$n = array( 'added' => 0, 'updated' => 0, 'failed' => 0 );
			foreach ( aw_crm_volunteers() as $r ) {
				if ( ! in_array( $r['vol_status'], array( '', 'active' ), true ) || in_array( $r['status'], array( 'unsubscribed', 'bounced', 'complained' ), true ) ) {
					continue;
				}
				$res = aw_nl_activate( $r['email'], $r['name'], '1.0 campaign' === $r['vol_source'] ? 'v1_campaign' : 'v2_form', 'owner-2026-10-16-volunteers' );
				$n[ $res ? $res : 'failed' ]++;
			}
			WP_CLI::success( wp_json_encode( $n ) );
		}
	);
}
