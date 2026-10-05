<?php
/**
 * Campaign kit: countdown, proof-of-posting upload (private), live counts.
 *
 * Shortcodes:
 *   [aw_countdown date="2026-11-29" label="Posting day"]
 *   [aw_receipt_form campaign="slug"]
 *   [aw_count campaign="slug" what="letters|people|cities|volunteers"]
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'aw_receipt',
			array(
				'labels'          => array( 'name' => 'Campaign receipts', 'singular_name' => 'Receipt', 'menu_name' => 'Receipts' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title' ),
				'capability_type' => array( 'aw_volunteer', 'aw_volunteers' ), // Same admin-only capabilities as volunteers.
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			)
		);
	}
);

/** Private folder for receipt photos (blocked from the web). */
function aw_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'aw-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	return $dir;
}

/* ---------- Countdown ---------- */
add_shortcode(
	'aw_countdown',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'date' => '', 'label' => '' ), $atts );
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['date'] ) ? $a['date'] : '';
		if ( ! $date ) {
			return '';
		}
		$ts   = strtotime( $date . ' 00:00:00 Asia/Kolkata' );
		$days = (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS );
		$when = wp_date( 'j F Y', $ts );
		$lbl  = $a['label'] ? esc_html( $a['label'] ) . ': ' : '';
		if ( $days > 1 ) {
			$text = sprintf( '<span class="aw-cd-num">%d</span> <span class="aw-cd-unit">days to go</span>', $days );
		} elseif ( 1 === $days ) {
			$text = '<span class="aw-cd-num">1</span> <span class="aw-cd-unit">day to go</span>';
		} elseif ( 0 === $days ) {
			$text = '<span class="aw-cd-num">Today</span>';
		} else {
			$text = '<span class="aw-cd-unit">Took place on</span>';
		}
		return '<p class="aw-countdown" data-date="' . esc_attr( $date ) . '">' . $text . ' <span class="aw-cd-when">' . $lbl . esc_html( $when ) . '</span></p>';
	}
);

/* ---------- Live counts (real submissions only) ---------- */
function aw_campaign_counts( $campaign ) {
	$key = 'aw_counts_' . md5( $campaign );
	$c   = get_transient( $key );
	if ( false !== $c ) {
		return $c;
	}
	$ids     = get_posts( array( 'post_type' => 'aw_receipt', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_r_campaign', 'meta_value' => $campaign ) );
	$letters = 0;
	$people  = array();
	$cities  = array();
	foreach ( $ids as $id ) {
		$letters += max( 1, (int) get_post_meta( $id, 'aw_r_letters', true ) );
		$people[ strtolower( (string) get_post_meta( $id, 'aw_r_email', true ) ) ] = 1;
		$city = strtolower( trim( (string) get_post_meta( $id, 'aw_r_city', true ) ) );
		if ( $city ) {
			$cities[ $city . '|' . get_post_meta( $id, 'aw_r_state', true ) ] = 1;
		}
	}
	$c = array(
		'letters'    => $letters,
		'people'     => count( $people ),
		'cities'     => count( $cities ),
		'volunteers' => (int) wp_count_posts( 'aw_volunteer' )->private,
	);
	set_transient( $key, $c, 5 * MINUTE_IN_SECONDS );
	return $c;
}

add_shortcode(
	'aw_count',
	function ( $atts ) {
		$a      = shortcode_atts( array( 'campaign' => '', 'what' => 'letters', 'label' => '' ), $atts );
		$counts = aw_campaign_counts( sanitize_title( $a['campaign'] ) );
		$labels = array( 'letters' => 'letters posted', 'people' => 'people took part', 'cities' => 'cities', 'volunteers' => 'volunteers signed up' );
		$what   = isset( $counts[ $a['what'] ] ) ? $a['what'] : 'letters';
		return '<span class="aw-count"><strong>' . esc_html( number_format_i18n( $counts[ $what ] ) ) . '</strong> ' . esc_html( $a['label'] ? $a['label'] : $labels[ $what ] ) . '</span>';
	}
);

/* ---------- Proof-of-posting upload ---------- */
const AW_R_MAX_FILES = 5;
const AW_R_MAX_LINKS = 3;
const AW_R_MAX_BYTES = 10485760; // 10 MB per file, before optimisation.
const AW_R_LABELS    = array( 'receipt' => 'Speed Post receipt', 'envelope' => 'Envelope', 'letter' => 'Letter', 'other' => 'Other' );

add_shortcode(
	'aw_receipt_form',
	function ( $atts ) {
		$a        = shortcode_atts( array( 'campaign' => '' ), $atts );
		$campaign = sanitize_title( $a['campaign'] );
		if ( isset( $_GET['posted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return '<div class="aw-form-done" role="status"><h3>Thank you. Your letter is counted.</h3><p>Share the campaign so more people post theirs.</p></div>';
		}
		$error  = isset( $_GET['receipt_error'] ) ? sanitize_key( $_GET['receipt_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msgs   = array(
			'missing' => 'Please add your name, a valid email, your state, and at least one photo or link.',
			'file'    => 'Each file must be a JPG, PNG, WebP, HEIC image or a PDF, up to 10 MB, and at most 5 files.',
			'limit'   => 'Too many uploads from this connection. Please try again later.',
			'invalid' => 'Something went wrong. Please try again.',
		);
		$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
		$opts   = '';
		foreach ( AW_R_LABELS as $k => $l ) {
			$opts .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		ob_start();
		?>
		<form class="aw-form aw-receipt" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-max-files="<?php echo (int) AW_R_MAX_FILES; ?>" data-max-bytes="<?php echo (int) AW_R_MAX_BYTES; ?>">
			<?php if ( $error && isset( $msgs[ $error ] ) ) : ?><p class="aw-form-error" role="alert"><?php echo esc_html( $msgs[ $error ] ); ?></p><?php endif; ?>
			<input type="hidden" name="action" value="aw_receipt">
			<input type="hidden" name="campaign" value="<?php echo esc_attr( $campaign ); ?>">
			<?php wp_nonce_field( 'aw_receipt', 'aw_r_nonce' ); ?>
			<input type="hidden" name="aw_t" value="<?php echo esc_attr( time() ); ?>">
			<p class="aw-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="aw_website" tabindex="-1" autocomplete="off"></label></p>

			<fieldset class="aw-uploads">
				<legend>Photos of your receipt, envelope or letter</legend>
				<p class="aw-note">Up to 5 files: JPG, PNG, WebP, HEIC or PDF, 10 MB each. Photos are made smaller before sending. Screenshots work too: choose them, or paste with Ctrl/Cmd+V.</p>
				<div class="aw-upload-buttons">
					<label class="aw-upload-btn">Take photo<input type="file" accept="image/*" capture="environment" class="aw-pick" hidden></label>
					<label class="aw-upload-btn aw-upload-btn-alt">Choose files<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" multiple class="aw-pick" hidden></label>
				</div>
				<input type="file" name="photos[]" multiple class="aw-real-files" hidden>
				<ul class="aw-file-list" aria-live="polite"></ul>
				<template class="aw-label-options"><?php echo $opts; // phpcs:ignore ?></template>
				<noscript><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></noscript>
			</fieldset>

			<fieldset class="aw-links">
				<legend>Or add links (optional)</legend>
				<p class="aw-note">A link to a photo or PDF, for example from Google Drive or Google Photos. Up to 3.</p>
				<?php for ( $i = 0; $i < AW_R_MAX_LINKS; $i++ ) : ?>
					<div class="aw-link-row"><input type="url" name="links[]" placeholder="https://" inputmode="url" aria-label="Link <?php echo (int) $i + 1; ?>"><select name="link_labels[]" aria-label="What is link <?php echo (int) $i + 1; ?>?"><?php echo $opts; // phpcs:ignore ?></select></div>
				<?php endfor; ?>
			</fieldset>

			<p><label for="aw-r-letters">How many letters did you post?</label><input id="aw-r-letters" name="letters" type="number" min="1" max="500" value="1" inputmode="numeric"></p>
			<p><label for="aw-r-name">Your name</label><input id="aw-r-name" name="name" required autocomplete="name" maxlength="120"></p>
			<p><label for="aw-r-email">Email</label><input id="aw-r-email" name="email" type="email" required autocomplete="email" maxlength="160"></p>
			<p><label for="aw-r-state">State or union territory</label><select id="aw-r-state" name="state" required><option value="">Choose a state</option>
				<?php foreach ( $states as $s ) : ?><option value="<?php echo esc_attr( $s->slug ); ?>"><?php echo esc_html( $s->name ); ?></option><?php endforeach; ?>
			</select></p>
			<p><label for="aw-r-city">City or town</label><input id="aw-r-city" name="city" maxlength="80" autocomplete="address-level2"></p>
			<p class="aw-note">Your photos are kept private and only used to count letters. They are never published, and location data is removed.</p>
			<p><button type="submit" class="wp-element-button">Count my letter</button></p>
		</form>
		<?php
		return ob_get_clean();
	}
);

/* Client side: camera/gallery/paste, labels, image optimisation (max 1600px JPEG). */
add_action(
	'wp_footer',
	function () {
		if ( ! is_singular() || false === strpos( (string) get_post_field( 'post_content', get_queried_object_id() ), 'aw_receipt_form' ) ) {
			return;
		}
		?>
<script>
(function(){
document.querySelectorAll('form.aw-receipt').forEach(function(form){
  var MAX=+form.dataset.maxFiles||5, MAXB=+form.dataset.maxBytes||10485760, items=[];
  var list=form.querySelector('.aw-file-list'), real=form.querySelector('.aw-real-files');
  var opts=form.querySelector('.aw-label-options').innerHTML;
  var OK=/^(image\/(jpeg|png|webp|heic|heif)|application\/pdf)$/;
  function shrink(file){
    if(!/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.resolve(file);
    return new Promise(function(res){
      var img=new Image(), url=URL.createObjectURL(file);
      img.onload=function(){
        var s=Math.min(1,1600/Math.max(img.width,img.height)), c=document.createElement('canvas');
        c.width=Math.round(img.width*s); c.height=Math.round(img.height*s);
        c.getContext('2d').drawImage(img,0,0,c.width,c.height); URL.revokeObjectURL(url);
        c.toBlob(function(b){ res(b&&b.size<file.size?new File([b],file.name.replace(/\.\w+$/,'')+'.jpg',{type:'image/jpeg'}):file); },'image/jpeg',0.82);
      };
      img.onerror=function(){URL.revokeObjectURL(url);res(file);};
      img.src=url;
    });
  }
  function render(){
    list.innerHTML='';
    items.forEach(function(it,i){
      var li=document.createElement('li');
      var kb=Math.round(it.file.size/1024);
      li.innerHTML=(it.file.type.indexOf('image/')===0&&it.file.type!=='image/heic'?'<img alt="" src="'+URL.createObjectURL(it.file)+'">':'<span class="aw-file-icon">PDF</span>')+
        '<span class="aw-file-name"></span><select name="labels[]" aria-label="What is this file?">'+opts+'</select><button type="button" class="aw-file-remove" aria-label="Remove">×</button>';
      li.querySelector('.aw-file-name').textContent=it.file.name+' ('+kb+' KB)';
      var sel=li.querySelector('select'); sel.value=it.label; sel.onchange=function(){it.label=sel.value;};
      li.querySelector('button').onclick=function(){items.splice(i,1);render();};
      list.appendChild(li);
    });
    var dt=new DataTransfer(); items.forEach(function(it){dt.items.add(it.file);}); real.files=dt.files;
  }
  function add(files){
    Array.prototype.forEach.call(files,function(f){
      if(items.length>=MAX){alert('You can add up to '+MAX+' files.');return;}
      if(!OK.test(f.type)){alert(f.name+': use a photo (JPG, PNG, WebP, HEIC) or a PDF.');return;}
      if(f.size>MAXB){alert(f.name+' is larger than 10 MB.');return;}
      var it={file:f,label:items.length?'other':'receipt'}; items.push(it);
      shrink(f).then(function(small){it.file=small;render();});
    });
    render();
  }
  form.querySelectorAll('.aw-pick').forEach(function(inp){inp.addEventListener('change',function(){add(inp.files);inp.value='';});});
  form.addEventListener('paste',function(e){ if(e.clipboardData&&e.clipboardData.files.length){add(e.clipboardData.files);e.preventDefault();} });
  document.addEventListener('paste',function(e){ if(e.target.closest&&e.target.closest('form')!==form&&e.clipboardData&&e.clipboardData.files.length&&form.getBoundingClientRect().top<innerHeight){add(e.clipboardData.files);} });
});
})();
</script>
		<?php
	}
);

/**
 * Store one file privately; images are re-encoded (max 1600px), which also strips EXIF/GPS.
 *
 * @param string $tmp  Temp path.
 * @param string $name Original name.
 * @return string Stored file name or ''.
 */
function aw_store_receipt_file( $tmp, $name ) {
	$types = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'heif' => 'image/heif', 'pdf' => 'application/pdf' );
	$check = wp_check_filetype_and_ext( $tmp, $name, $types );
	if ( empty( $check['type'] ) || filesize( $tmp ) > AW_R_MAX_BYTES ) {
		return '';
	}
	$dir  = aw_private_dir();
	$base = wp_generate_password( 20, false );
	if ( 'application/pdf' === $check['type'] ) {
		$fh = fopen( $tmp, 'rb' ); // phpcs:ignore
		$sig = $fh ? fread( $fh, 5 ) : ''; // phpcs:ignore
		if ( $fh ) { fclose( $fh ); } // phpcs:ignore
		if ( '%PDF-' !== $sig ) {
			return '';
		}
		return copy( $tmp, "$dir/$base.pdf" ) ? "$base.pdf" : '';
	}
	$editor = wp_get_image_editor( $tmp );
	if ( ! is_wp_error( $editor ) ) {
		$editor->resize( 1600, 1600, false );
		$editor->set_quality( 82 );
		$saved = $editor->save( "$dir/$base.jpg", 'image/jpeg' );
		if ( ! is_wp_error( $saved ) ) {
			return "$base.jpg";
		}
	}
	// Server cannot decode (e.g. HEIC without Imagick): keep the original, still private.
	return copy( $tmp, "$dir/$base.{$check['ext']}" ) ? "$base.{$check['ext']}" : '';
}

function aw_receipt_submit() {
	$back = wp_get_referer() ? remove_query_arg( array( 'posted', 'receipt_error' ), wp_get_referer() ) : home_url( '/' );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'receipt_error', $code, $back ) . '#count-my-letter' );
		exit;
	};
	if ( ! isset( $_POST['aw_r_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_r_nonce'] ) ), 'aw_receipt' ) ) {
		$fail( 'invalid' );
	}
	if ( ! empty( $_POST['aw_website'] ) || ( time() - (int) ( $_POST['aw_t'] ?? 0 ) ) < 3 ) {
		wp_safe_redirect( add_query_arg( 'posted', 1, $back ) . '#count-my-letter' );
		exit;
	}
	$ip_key = 'aw_rc_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 10 ) {
		$fail( 'limit' );
	}
	$campaign = sanitize_title( wp_unslash( $_POST['campaign'] ?? '' ) );
	$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$state    = get_term_by( 'slug', sanitize_title( wp_unslash( $_POST['state'] ?? '' ) ), 'aw_city' );
	$city     = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
	$letters  = min( 500, max( 1, (int) ( $_POST['letters'] ?? 1 ) ) );
	$labels   = array_map( 'sanitize_key', (array) wp_unslash( $_POST['labels'] ?? array() ) );
	if ( '' === $name || ! is_email( $email ) || ! $state ) {
		$fail( 'missing' );
	}
	$stored = array();
	// Uploaded files.
	$f = $_FILES['photos'] ?? null; // phpcs:ignore
	if ( $f && is_array( $f['name'] ) ) {
		$n = count( array_filter( $f['name'] ) );
		if ( $n > AW_R_MAX_FILES ) {
			$fail( 'file' );
		}
		foreach ( $f['name'] as $i => $orig ) {
			if ( '' === $orig || UPLOAD_ERR_OK !== $f['error'][ $i ] ) {
				continue;
			}
			$file = aw_store_receipt_file( $f['tmp_name'][ $i ], $orig );
			if ( ! $file ) {
				$fail( 'file' );
			}
			$label    = isset( AW_R_LABELS[ $labels[ $i ] ?? '' ] ) ? $labels[ $i ] : 'other';
			$stored[] = array( 'file' => $file, 'label' => $label, 'from' => 'upload' );
		}
	}
	// Links: direct images/PDFs are fetched (safely); anything else is kept as a reference.
	$links       = array_slice( (array) wp_unslash( $_POST['links'] ?? array() ), 0, AW_R_MAX_LINKS );
	$link_labels = array_map( 'sanitize_key', (array) wp_unslash( $_POST['link_labels'] ?? array() ) );
	foreach ( $links as $i => $link ) {
		$link = esc_url_raw( trim( (string) $link ), array( 'https' ) );
		if ( ! $link ) {
			continue;
		}
		$label = isset( AW_R_LABELS[ $link_labels[ $i ] ?? '' ] ) ? $link_labels[ $i ] : 'other';
		$entry = array( 'link' => $link, 'label' => $label, 'from' => 'link' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( $link, 20 ); // Uses wp_safe_remote_get: blocks local/private addresses.
		if ( ! is_wp_error( $tmp ) ) {
			$file = filesize( $tmp ) <= AW_R_MAX_BYTES ? aw_store_receipt_file( $tmp, basename( (string) wp_parse_url( $link, PHP_URL_PATH ) ) ?: 'link' ) : '';
			wp_delete_file( $tmp );
			if ( $file ) {
				$entry['file'] = $file;
			}
		}
		$stored[] = $entry;
		if ( count( $stored ) >= AW_R_MAX_FILES + AW_R_MAX_LINKS ) {
			break;
		}
	}
	if ( ! $stored ) {
		$fail( 'missing' );
	}
	$id = wp_insert_post( array( 'post_type' => 'aw_receipt', 'post_status' => 'private', 'post_title' => $name . ' (' . $state->name . ')' ), true );
	if ( is_wp_error( $id ) ) {
		$fail( 'invalid' );
	}
	foreach ( array( 'aw_r_campaign' => $campaign, 'aw_r_name' => $name, 'aw_r_email' => $email, 'aw_r_state' => $state->slug, 'aw_r_city' => $city, 'aw_r_letters' => $letters, 'aw_r_files' => $stored ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	delete_transient( 'aw_counts_' . md5( $campaign ) );
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'posted', 1, $back ) . '#count-my-letter' );
	exit;
}
add_action( 'admin_post_nopriv_aw_receipt', 'aw_receipt_submit' );
add_action( 'admin_post_aw_receipt', 'aw_receipt_submit' );

/* Admin: view a private photo (administrators only). */
add_action(
	'admin_post_aw_receipt_photo',
	function () {
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$n  = (int) ( $_GET['n'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( 'edit_aw_volunteers' ) || ! $id || 'aw_receipt' !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$files = (array) get_post_meta( $id, 'aw_r_files', true );
		$path  = aw_private_dir() . '/' . basename( (string) ( $files[ $n ]['file'] ?? '' ) );
		if ( ! is_file( $path ) ) {
			wp_die( 'Missing.', 404 );
		}
		header( 'Content-Type: ' . wp_check_filetype( $path )['type'] );
		header( 'Cache-Control: private, no-store' );
		readfile( $path ); // phpcs:ignore
		exit;
	}
);

add_filter( 'manage_aw_receipt_posts_columns', fn( $c ) => array( 'cb' => $c['cb'], 'title' => 'Name (state)', 'aw_campaign' => 'Campaign', 'aw_city' => 'City', 'aw_letters' => 'Letters', 'aw_photo' => 'Files', 'date' => 'Uploaded' ) );
add_action(
	'manage_aw_receipt_posts_custom_column',
	function ( $col, $id ) {
		$map = array( 'aw_campaign' => 'aw_r_campaign', 'aw_city' => 'aw_r_city', 'aw_letters' => 'aw_r_letters' );
		if ( isset( $map[ $col ] ) ) {
			echo esc_html( (string) get_post_meta( $id, $map[ $col ], true ) );
		} elseif ( 'aw_photo' === $col ) {
			$out = array();
			foreach ( (array) get_post_meta( $id, 'aw_r_files', true ) as $n => $f ) {
				$label = AW_R_LABELS[ $f['label'] ?? 'other' ] ?? 'File';
				if ( ! empty( $f['file'] ) ) {
					$out[] = '<a href="' . esc_url( admin_url( 'admin-post.php?action=aw_receipt_photo&id=' . $id . '&n=' . $n ) ) . '" target="_blank">' . esc_html( $label ) . '</a>';
				} elseif ( ! empty( $f['link'] ) ) {
					$out[] = '<a href="' . esc_url( $f['link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . ' (link)</a>';
				}
			}
			echo implode( '<br>', $out ); // phpcs:ignore
		}
	},
	10,
	2
);

// Deleting a receipt removes its private photo too.
add_action(
	'before_delete_post',
	function ( $id ) {
		if ( 'aw_receipt' === get_post_type( $id ) ) {
			foreach ( (array) get_post_meta( $id, 'aw_r_files', true ) as $f ) {
				$f = basename( (string) ( $f['file'] ?? '' ) );
				if ( $f && is_file( aw_private_dir() . '/' . $f ) ) {
					wp_delete_file( aw_private_dir() . '/' . $f );
				}
			}
			delete_transient( 'aw_counts_' . md5( (string) get_post_meta( $id, 'aw_r_campaign', true ) ) );
		}
	}
);
