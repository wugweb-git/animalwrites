/* Animal Writes interactions specific to Animal Writes. Generic behaviours live in Kits (kit.js). */
(function () {
	'use strict';
	/* Directory search: District stays off until a State is chosen, then lists that state's districts; it resets
	   when the State changes. Without JavaScript the server-rendered list works as is. */
	document.querySelectorAll('.aw-dir-search, .aw-form-ff form').forEach(function (f) {
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
			fetch('/wp-admin/admin-ajax.php?action=aw_pin&pin=' + v, { credentials: 'same-origin' }) // Fluent Forms forms have no action
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

	/* Logo (claim, Fluent Forms): a picked file is checked, then sent at once to the private folder; the form carries
	   its token. A wrong file shows the field's own message. */
	document.querySelectorAll('input[type=file][data-aw-upload]').forEach(function (inp) {
		var box = inp.closest('.ff-el-input--content'), tok = box && box.querySelector('input[name=logo_token]'), msg = document.getElementById(inp.id + '-error');
		inp.addEventListener('change', function () {
			var file = inp.files && inp.files[0];
			if (tok) tok.value = '';
			if (msg) msg.textContent = '';
			if (!file) return;
			if (!/^image\/(png|jpeg|webp)$/.test(file.type) || file.size > +(inp.dataset.maxBytes || Infinity)) { if (msg) msg.textContent = inp.dataset.msgFile; inp.value = ''; return; }
			var fd = new FormData(); fd.append('logo', file);
			inp.setAttribute('aria-busy', 'true');
			fetch(inp.dataset.awUpload, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (r) { inp.removeAttribute('aria-busy'); if (r.success) { tok.value = r.data.token; } else { if (msg) msg.textContent = r.data; inp.value = ''; } })
				.catch(function () { inp.removeAttribute('aria-busy'); if (msg) msg.textContent = inp.dataset.msgFile; inp.value = ''; });
		});
	});

	/* Fluent Forms: when the spam check was not accepted, it starts again (a solved check works once). */
	if (window.jQuery) jQuery(document).on('fluentform_submission_failed', function (e, d) {
		var err = d && d.response && d.response.responseJSON && d.response.responseJSON.errors, f = d && d.form && d.form[0];
		var w = f && f.querySelector('altcha-widget');
		if (w && err && err.altcha && typeof w.reset === 'function') w.reset();
	});

	/* Contact: the message placeholder follows the chosen reason. */
	document.querySelectorAll('.aw-contact-form').forEach(function (f) {
		var sel = f.querySelector('[name=about]'), msg = f.querySelector('[name=message]');
		if (!sel || !msg) return;
		var map = {}; try { map = JSON.parse(f.dataset.placeholders || '{}'); } catch (x) {} // reason => placeholder, on the form's wrapper
		sel.addEventListener('change', function () { msg.placeholder = map[sel.value] || ''; });
		// The rows on the page (List your organisation, Post an opportunity …) choose their reason here and move to the form.
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

/* Home "Opportunities near you" (owner, 16 Oct 2026). A state chosen in a Directory search ("Find help near you") is
   kept in this browser only; on the home page the list then shows opportunities in that state (newest fill the rest).
   No state chosen, nothing found, or no JavaScript: the server's newest ones stay. */
(function () {
	'use strict';
	var KEY = 'aw_state';
	function get() { try { return localStorage.getItem(KEY) || ''; } catch (e) { return ''; } }
	function set(v) { try { if (v) localStorage.setItem(KEY, v); else localStorage.removeItem(KEY); } catch (x) {} }
	// Chosen in a Directory search (home "Find help near you", Directory hub) ...
	document.addEventListener('change', function (e) {
		var t = e.target, f = t.form;
		if (t.matches && t.matches('select[name=state]') && f && /\/directory\/?$/.test(f.getAttribute('action') || '')) set(t.value);
	});
	// ... or by opening a state's (or district's) Directory page: /directory/{state}/.
	var m = location.pathname.match(/^\/directory\/([a-z0-9-]+)\//);
	if (m && ['org', 'page', 'feed'].indexOf(m[1]) < 0) set(m[1]);
	var list = document.querySelector('.aw-ed-opps .wp-block-post-template');
	var state = get();
	if (!list || !state || !window.fetch) return;
	fetch('/wp-json/aw/v1/opps-near?state=' + encodeURIComponent(state), { credentials: 'omit' })
		.then(function (r) { return r.ok ? r.json() : null; })
		.then(function (d) {
			if (d && !d.state) { set(''); return; } // not a state after all
			if (!d || !d.local || !d.html) return;
			var box = document.createElement('div');
			box.innerHTML = d.html;
			var ul = box.querySelector('.wp-block-post-template');
			if (ul) list.replaceWith(ul);
		})
		.catch(function () {});
})();

/* Directory search on phones: the filter icon inside the search bar opens and closes the filters (21 Oct 2026). */
(function () {
	var f = document.querySelector('form.aw-dir-search');
	if (!f) return;
	var b = f.querySelector('.aw-dir-filter-toggle'), d = f.querySelector('details.aw-dir-more');
	if (!b || !d) return;
	function sync() { b.setAttribute('aria-expanded', d.open ? 'true' : 'false'); }
	b.addEventListener('click', function () { d.open = !d.open; });
	d.addEventListener('toggle', sync);
	sync();
})();
