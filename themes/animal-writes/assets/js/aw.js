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

	/* Private uploads (claim logo, photos, proof; Fluent Forms): picked files are checked, then sent at once to the
	   private folder; the form carries their tokens (several, comma-separated, for photos). A wrong file shows the
	   field's own message. */
	document.querySelectorAll('input[type=file][data-aw-upload]').forEach(function (inp) {
		var box = inp.closest('.ff-el-input--content'), tok = box && box.querySelector('input[type=hidden]'), msg = document.getElementById(inp.id + '-error');
		if (!tok) return;
		function fail(t) { inp.removeAttribute('aria-busy'); tok.value = ''; if (msg) msg.textContent = t || inp.dataset.msgFile; inp.value = ''; }
		inp.addEventListener('change', function () {
			var files = Array.prototype.slice.call(inp.files || []), max = +(inp.dataset.maxFiles || 1);
			tok.value = '';
			if (msg) msg.textContent = '';
			if (!files.length) return;
			if (files.length > max) { fail(inp.dataset.msgFiles); return; }
			if (files.some(function (f) { return !/^image\/(png|jpeg|webp)$/.test(f.type) || f.size > +(inp.dataset.maxBytes || Infinity); })) { fail(); return; }
			inp.setAttribute('aria-busy', 'true');
			var tokens = [];
			files.reduce(function (p, file) { // one at a time, in order
				return p.then(function () {
					var fd = new FormData(); fd.append('logo', file);
					return fetch(inp.dataset.awUpload, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
						.then(function (r) { if (!r.success) throw new Error(r.data); tokens.push(r.data.token); });
				});
			}, Promise.resolve())
				.then(function () { inp.removeAttribute('aria-busy'); tok.value = tokens.join(','); })
				.catch(function (e) { fail(e && e.message && e.message !== '[object Object]' ? e.message : ''); });
		});
	});

	/* Claim form, last step: "Summary of changes" lists only the listing fields the claimant changed (owner, 31 Oct
	   2026), from each field's pre-filled value; read-only. */
	document.addEventListener('kit:step', function (e) {
		var form = e.target.closest && e.target.closest('form'), sum = form && form.querySelector('[data-aw-claim-summary]');
		if (!sum || sum.closest('.kit-step[hidden]')) return;
		var dl = sum.querySelector('dl'), seen = {};
		dl.textContent = '';
		form.querySelectorAll('[name^="l_"], input[type=hidden][name$="_token"], input[type=hidden][name="photo_tokens"]').forEach(function (el) {
			var name = el.name.replace(/\[\]$/, ''), group = el.closest('.ff-el-group'), changed, value;
			if (seen[name] || !group) return;
			seen[name] = true;
			var label = group.querySelector('label, .ff-el-input--label') ? (group.querySelector('.ff-el-input--label label') || group.querySelector('label')).textContent.replace(/\(optional\)/, '').trim() : name;
			if (el.type === 'checkbox') {
				var boxes = form.querySelectorAll('[name="' + el.name + '"]');
				changed = Array.prototype.some.call(boxes, function (b) { return b.checked !== b.defaultChecked; });
				value = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).map(function (b) { return b.closest('label') ? b.closest('label').textContent.trim() : b.value; }).join(', ') || '(none)';
			} else if (el.type === 'hidden') {
				changed = el.value !== '';
				value = el.name === 'photo_tokens' ? el.value.split(',').length + ' new' : 'new file';
				var lab = el.closest('.ff-el-input--content') && el.closest('.ff-el-group').querySelector('label');
				label = lab ? lab.textContent.trim() : label;
			} else {
				changed = el.value.trim() !== el.defaultValue.trim();
				value = el.value.trim() || '(removed)';
			}
			if (!changed) return;
			var dt = document.createElement('dt'), dd = document.createElement('dd');
			dt.textContent = label; dd.textContent = value;
			dl.appendChild(dt); dl.appendChild(dd);
		});
		if (!dl.children.length) { var none = document.createElement('dd'); none.textContent = 'No changes to the listing\'s details.'; dl.appendChild(none); }
		sum.hidden = false;
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
