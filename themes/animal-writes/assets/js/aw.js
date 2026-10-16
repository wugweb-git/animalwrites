/* Animal Writes interactions specific to Animal Writes. Generic behaviours live in Kits (kit.js). */
(function () {
	'use strict';
	/* Directory search: District stays off until a State is chosen, then lists that state's districts; it resets
	   when the State changes. Without JavaScript the server-rendered list works as is. */
	document.querySelectorAll('.aw-dir-search, form.kit-form').forEach(function (f) {
		var st = f.querySelector('[name=state]'), di = f.querySelector('[name=district]');
		if (!st || !di) return;
		var first = di.options[0];
		function sync(reset) {
			var v = st.value;
			di.querySelectorAll('optgroup').forEach(function (g) {
				var show = !!v && g.dataset.state === v;
				g.hidden = !show; g.disabled = !show;
			});
			if (reset) di.value = '';
			di.disabled = !v;
			first.textContent = v ? first.dataset.all : first.dataset.none;
		}
		st.addEventListener('change', function () { sync(true); });
		sync(false);
	});


	/* PIN code (forms with State and District): six digits fill State and District from India Post, matched to the
	   LGD list; if the PIN is not found, the visitor chooses them. */
	document.querySelectorAll('input[data-pin-lookup]').forEach(function (pin) {
		var f = pin.form, st = f && f.querySelector('select[name=state]'), di = f && f.querySelector('select[name=district]');
		if (!st) return;
		var msg = document.getElementById(pin.id + '-error'), last = '';
		pin.addEventListener('input', function () {
			var v = pin.value.replace(/\D/g, '');
			if (v.length !== 6 || v === last) return;
			last = v;
			fetch(f.getAttribute('action').replace('admin-post.php', 'admin-ajax.php') + '?action=aw_pin&pin=' + v, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) {
					if (!r.success) { if (msg) msg.textContent = r.data; return; }
					if (msg) msg.textContent = '';
					st.value = r.data.state_slug; st.dispatchEvent(new Event('change', { bubbles: true }));
					if (di && r.data.district_slug) { di.value = r.data.district_slug; di.dispatchEvent(new Event('change', { bubbles: true })); }
				})
				.catch(function () { if (msg) msg.textContent = 'Couldn\'t find this PIN. Choose your state and district instead.'; });
		});
	});

	/* Contact: the message placeholder follows the chosen reason. */
	document.querySelectorAll('.aw-contact-form').forEach(function (f) {
		var sel = f.querySelector('[name=about]'), msg = f.querySelector('[name=message]');
		if (!sel || !msg) return;
		sel.addEventListener('change', function () { msg.placeholder = sel.selectedOptions[0].dataset.placeholder || ''; });
		// The rows on the page (List your organisation, Get featured …) choose their reason here and move to the form.
		document.querySelectorAll('a.aw-contact-pick[data-about]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				if (!sel.querySelector('option[value="' + a.dataset.about + '"]')) return;
				e.preventDefault();
				sel.value = a.dataset.about; sel.dispatchEvent(new Event('change', { bubbles: true }));
				history.replaceState(null, '', a.href);
				f.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
				sel.focus({ preventScroll: true });
			});
		});
	});
})();

/* Card pictures: fill the 16:9 frame only when the picture's own shape is within about 15% of it; otherwise it is shown
   whole (CSS default: contain). Decided from each picture's real size once it has loaded (owner, 14 Oct 2026). */
( function () {
	var FRAME = 16 / 9, SEL = ':is(.kit-card-media, .wp-block-post-featured-image, .kit-story-media, .aw-task-media, .aw-opp-media, .aw-res-media) img, img.aw-case-cover';
	function fit( img ) {
		if ( ! img.naturalWidth || ! img.naturalHeight ) { return; }
		var r = img.naturalWidth / img.naturalHeight;
		img.classList.toggle( 'is-fill', Math.abs( r - FRAME ) / FRAME <= 0.15 );
	}
	function scan( root ) {
		( root || document ).querySelectorAll( SEL ).forEach( function ( img ) {
			if ( img.complete ) { fit( img ); } else { img.addEventListener( 'load', function () { fit( img ); }, { once: true } ); }
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', function () { scan(); } ); } else { scan(); }
	new MutationObserver( function ( list ) { list.forEach( function ( m ) { m.addedNodes.forEach( function ( n ) { if ( n.nodeType === 1 ) { scan( n ); } } ); } ); } ).observe( document.documentElement, { childList: true, subtree: true } );
} )();

/* Home "Opportunities near you" (owner, 16 Oct 2026). A state chosen in a Directory search ("Find help near you") is
   kept in this browser only; on the home page the list then shows opportunities in that state (newest fill the rest).
   No state chosen, nothing found, or no JavaScript: the server's newest ones stay. */
(function () {
	'use strict';
	var KEY = 'aw_state';
	function get() { try { return localStorage.getItem(KEY) || ''; } catch (e) { return ''; } }
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!t.matches || !t.matches('form.aw-dir-search select[name=state]')) return;
		try { if (t.value) localStorage.setItem(KEY, t.value); else localStorage.removeItem(KEY); } catch (x) {}
	});
	var list = document.querySelector('.aw-ed-opps .wp-block-post-template');
	var state = get();
	if (!list || !state || !window.fetch) return;
	fetch('/wp-json/aw/v1/opps-near?state=' + encodeURIComponent(state), { credentials: 'omit' })
		.then(function (r) { return r.ok ? r.json() : null; })
		.then(function (d) {
			if (!d || !d.local || !d.html) return;
			var box = document.createElement('div');
			box.innerHTML = d.html;
			var ul = box.querySelector('.wp-block-post-template');
			if (ul) list.replaceWith(ul);
		})
		.catch(function () {});
})();
