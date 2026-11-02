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
		var st = sum && sum.closest('.kit-step');
		if (!sum || (st && +st.getAttribute('data-step') !== e.detail.step)) return; // kit:step fires before the step is shown
		var dl = sum.querySelector('dl'), seen = {};
		dl.textContent = '';
		form.querySelectorAll('[name^="l_"], input[type=hidden][name="logo_token"], input[type=hidden][name="photo_tokens"]').forEach(function (el) {
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

	/* Volunteer form, "Same as my phone" (owner, 31 Oct 2026): the WhatsApp number follows the phone number while ticked
	   (the server copies it too). The change event lets Fluent Forms show "OK to contact me on WhatsApp". */
	document.querySelectorAll('input[name="wa_same[]"]').forEach(function (box) {
		var f = box.form, ph = f && f.querySelector('input[name=phone]'), wa = f && f.querySelector('input[name=whatsapp]');
		if (!ph || !wa) return;
		function sync() {
			if (box.checked) wa.value = ph.value;
			wa.readOnly = box.checked;
			wa.dispatchEvent(new Event('input', { bubbles: true })); wa.dispatchEvent(new Event('change', { bubbles: true }));
			if (window.jQuery) jQuery(wa).trigger('change');
		}
		box.addEventListener('change', sync);
		ph.addEventListener('input', function () { if (box.checked) sync(); });
	});

	/* Volunteer form, Organisation from the Directory (owner, 31 Oct 2026): typing three letters lists matching listings
	   ("name · district, state"); picking one saves its ID in org_listing and its name in the box. "My organisation isn't
	   listed" keeps the typed name. A combobox: arrow keys, Enter, Escape; the choice is announced. */
	document.querySelectorAll('input[data-aw-org-search]').forEach(function (inp) {
		var f = inp.form, idf = f && f.querySelector('input[name=org_listing]');
		if (!idf) return;
		var host = inp.parentNode, list = document.createElement('ul'), live = document.createElement('span');
		list.id = (inp.id || 'aw-org') + '-list'; list.className = 'aw-org-list'; list.setAttribute('role', 'listbox'); list.hidden = true;
		live.className = 'screen-reader-text'; live.setAttribute('role', 'status');
		inp.setAttribute('role', 'combobox'); inp.setAttribute('aria-autocomplete', 'list'); inp.setAttribute('aria-expanded', 'false'); inp.setAttribute('aria-controls', list.id);
		host.classList.add('aw-org-host'); host.appendChild(list); host.appendChild(live);
		var picked = idf.value ? inp.value : '', items = [], cur = -1, timer;
		function close() { list.hidden = true; inp.setAttribute('aria-expanded', 'false'); inp.removeAttribute('aria-activedescendant'); cur = -1; }
		function choose(i) {
			var it = items[i];
			if (it && it.id) { inp.value = it.name; idf.value = String(it.id); picked = it.name; live.textContent = it.name + ' chosen'; }
			else { idf.value = ''; picked = ''; live.textContent = ''; }
			close();
		}
		function mark() {
			Array.prototype.forEach.call(list.children, function (li, i) { li.setAttribute('aria-selected', i === cur ? 'true' : 'false'); });
			if (cur > -1) { inp.setAttribute('aria-activedescendant', list.id + '-' + cur); list.children[cur].scrollIntoView({ block: 'nearest' }); }
		}
		function render() {
			list.textContent = '';
			items.forEach(function (it, i) {
				var li = document.createElement('li');
				li.id = list.id + '-' + i; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
				li.className = 'aw-org-opt' + (it.id ? '' : ' aw-org-none');
				li.textContent = it.name;
				if (it.place) { var s = document.createElement('span'); s.className = 'aw-org-place'; s.textContent = ' · ' + it.place; li.appendChild(s); }
				li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
				list.appendChild(li);
			});
			list.hidden = !items.length; inp.setAttribute('aria-expanded', items.length ? 'true' : 'false');
		}
		inp.addEventListener('input', function () {
			if (inp.value !== picked) { idf.value = ''; picked = ''; }
			clearTimeout(timer);
			var q = inp.value.trim();
			if (q.length < 3) { items = []; render(); return; }
			timer = setTimeout(function () {
				fetch(inp.dataset.awOrgSearch + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (r) { items = (r && r.success ? r.data : []).concat([{ id: 0, name: 'My organisation isn\'t listed' }]); cur = -1; render(); })
					.catch(function () {});
			}, 250);
		});
		inp.addEventListener('keydown', function (e) {
			if (list.hidden) return;
			if (e.key === 'ArrowDown') { cur = Math.min(cur + 1, items.length - 1); mark(); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { cur = Math.max(cur - 1, 0); mark(); e.preventDefault(); }
			else if (e.key === 'Enter' && cur > -1) { choose(cur); e.preventDefault(); }
			else if (e.key === 'Escape') { close(); }
		});
		inp.addEventListener('blur', function () { setTimeout(close, 150); });
	});

	/* Every Fluent Forms form: when the send itself fails (no connection, a server error: no field errors came back), a
	   plain line under the button (owner, 31 Oct 2026; wording for the owner's yes). */
	if (window.jQuery) jQuery(document).on('fluentform_submission_failed', function (e, d) {
		var res = d && d.response, f = d && d.form && d.form[0];
		if (!f || (res && res.responseJSON && res.responseJSON.errors)) return;
		var box = f.querySelector('.aw-ff-send-failed');
		if (!box) { box = document.createElement('p'); box.className = 'aw-ff-send-failed kit-field-message kit-field-error'; box.setAttribute('role', 'alert'); (f.querySelector('.ff_submit_btn_wrapper') || f).appendChild(box); }
		box.textContent = 'Your message didn\'t send. Check your connection and try again.';
		f.querySelectorAll('.ff-errors-in-stack').forEach(function (s) { s.hidden = true; }); // one message, not Fluent Forms' generic one as well
	});
	if (window.jQuery) jQuery(document).on('fluentform_submission_success', function (e, d) { var b = d && d.form && d.form[0] && d.form[0].querySelector('.aw-ff-send-failed'); if (b) b.remove(); });

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

/* Need help now: the bento tiles fade up one after another (112 first) when the grid scrolls into view. Tiles already
   on screen when the page loads are left as they are; nothing moves under prefers-reduced-motion or without GSAP. */
(function () {
	var grid = document.querySelector('.aw-bento');
	if (!grid || !window.gsap || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	if (grid.getBoundingClientRect().top < window.innerHeight) return;
	var tiles = grid.querySelectorAll('.aw-bento-tile');
	window.gsap.set(tiles, { opacity: 0, y: 16 });
	var io = new IntersectionObserver(function (entries) {
		var e = entries[0];
		if (!e.isIntersecting) {
			if (e.boundingClientRect.bottom < 0) { io.disconnect(); window.gsap.set(tiles, { clearProps: 'opacity,transform' }); } // jumped past: show it, no animation
			return;
		}
		io.disconnect();
		window.gsap.to(tiles, { opacity: 1, y: 0, duration: 0.45, ease: 'power2.out', stagger: 0.06, clearProps: 'opacity,transform' });
	}, { rootMargin: '0px 0px -10% 0px' });
	io.observe(grid);
	window.addEventListener('beforeprint', function () { io.disconnect(); window.gsap.set(tiles, { clearProps: 'opacity,transform' }); });
})();

/* Rows that scroll sideways (WCAG 2.1.1, 4.1.2): any element in the page's main area that scrolls sideways (overflow-x
   auto or scroll, and wider than its box), such as the card swipe rows (design-system.md rule 12), the Community dogs
   story's figures and dates (Home phones, aw.css) and a table in an article (kit.css .wp-block-table), takes keyboard
   focus, so arrow keys scroll it, and is a region named by words already on the page: the story's heading; the table's
   caption, else the nearest heading above it; any other row, the nearest h2-h3 above it in its section, else the nearest
   h2-h6 above it. A row that fits is left alone. Skipped: rows that already have a tabindex or a name, the campaign
   pages' rows (.aw-camp, campaign.js), the header and footer, and form fields. */
(function () {
	var main = document.querySelector('main') || document.querySelector('.wp-block-post-content');
	var fixed = [], n = 0;
	function idOf(el) {
		if (!el.id) {
			do { n++; } while (document.getElementById('aw-row-name-' + n));
			el.id = 'aw-row-name-' + n;
		}
		return el.id;
	}
	function above(el, root, sel) { // the nearest heading (sel) before el, inside root
		for (var node = el; node && node !== root; node = node.parentElement) {
			for (var p = node.previousElementSibling; p; p = p.previousElementSibling) {
				if (p.matches(sel)) return p;
				var hs = p.querySelectorAll(sel);
				if (hs.length) return hs[hs.length - 1];
			}
		}
		return null;
	}
	function nameOf(el) {
		var sec = el.parentElement && el.parentElement.closest('section, .kit-section, .wp-block-group');
		return (sec && above(el, sec, 'h2, h3')) || above(el, main, 'h2, h3, h4, h5, h6');
	}
	document.querySelectorAll('.aw-ed-story :is(.aw-ed-story-figures, .aw-ed-story-dates)').forEach(function (r) {
		fixed.push({ el: r, name: r.closest('.aw-ed-story').querySelector('h2') });
	});
	document.querySelectorAll('.wp-block-post-content figure.wp-block-table').forEach(function (r) {
		if (r.closest('.aw-camp--diwali')) return;
		fixed.push({ el: r, name: r.querySelector('figcaption, caption') || above(r, r.closest('.wp-block-post-content'), 'h2, h3, h4, h5, h6') });
	});
	if (!main && !fixed.length) return;
	function own(r) { // a tabindex or name that this script did not set
		return !r.hasAttribute('data-aw-row') && (r.hasAttribute('tabindex') || r.hasAttribute('aria-label') || r.hasAttribute('aria-labelledby'));
	}
	function set(r, name, on) {
		if (on) {
			r.setAttribute('data-aw-row', '');
			r.setAttribute('tabindex', '0');
			r.setAttribute('role', 'region');
			if (name) r.setAttribute('aria-labelledby', idOf(name));
		} else if (r.hasAttribute('data-aw-row')) {
			r.removeAttribute('data-aw-row');
			r.removeAttribute('tabindex');
			r.removeAttribute('role');
			r.removeAttribute('aria-labelledby');
		}
	}
	function wide(r) { return r.scrollWidth > r.clientWidth + 1; }
	function mark() {
		var done = [];
		fixed.forEach(function (row) {
			done.push(row.el);
			if (!own(row.el)) set(row.el, row.name, wide(row.el));
		});
		if (!main) return;
		Array.prototype.forEach.call(main.querySelectorAll('*'), function (r) {
			if (done.indexOf(r) > -1) return;
			var on = r.clientWidth > 0 && wide(r) && /^(auto|scroll)$/.test(getComputedStyle(r).overflowX) &&
				!r.matches('input, textarea, select, [contenteditable]') && !r.closest('.aw-camp, header, footer');
			if (on && own(r)) return;
			set(r, on ? nameOf(r) : null, on);
		});
	}
	// After load (images change widths), so the width reads don't add to the page's start-up work.
	if (document.readyState === 'complete') {
		mark();
	} else {
		window.addEventListener('load', mark);
	}
	var t;
	window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(mark, 150); }, { passive: true });
})();
