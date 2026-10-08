/* Kits by Wugweb interactions. No dependencies. Respects prefers-reduced-motion.
 * Each behaviour answers to .kit-* and to the older .aw-* names. */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var doc = document.documentElement;

	/* 1. One orchestrated moment: homepage hero animates on the first view of a session only. */
	try {
		if (!reduce && document.body.classList.contains('home') && !sessionStorage.getItem('aw_seen')) {
			doc.classList.add('aw-motion', 'kit-motion');
			sessionStorage.setItem('aw_seen', '1');
		}
	} catch (e) {}

	/* 2. Sticky help bar: hide while scrolling down, show on scroll up or near the top. */
	var bar = document.querySelector('.kit-sticky-help, .kit-sticky-help');
	if (bar) {
		var lastY = window.scrollY;
		window.addEventListener('scroll', function () {
			var y = window.scrollY;
			if (Math.abs(y - lastY) < 8) return; // ignore jitter
			bar.classList.toggle('is-hidden', y > lastY && y > 240);
			lastY = y;
		}, { passive: true });
		bar.addEventListener('focusin', function () { bar.classList.remove('is-hidden'); });
	}

	/* 3. Carousels: position dots that also work as controls. */
	document.querySelectorAll('.kit-cards .wp-block-post-template, .kit-cards .wp-block-post-template').forEach(function (track) {
		var items = track.children;
		if (items.length < 2) return;
		var dots = document.createElement('div');
		dots.className = 'kit-carousel-dots';
		dots.setAttribute('role', 'group');
		dots.setAttribute('aria-label', 'Choose a card');
		Array.prototype.forEach.call(items, function (item, i) {
			var b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('aria-label', 'Card ' + (i + 1) + ' of ' + items.length);
			b.addEventListener('click', function () {
				setCurrent(i);
				track.scrollTo({ left: item.offsetLeft - track.offsetLeft - parseFloat(getComputedStyle(track).paddingLeft), behavior: reduce ? 'auto' : 'smooth' });
			});
			dots.appendChild(b);
		});
		track.parentNode.insertBefore(dots, track.nextSibling);
		function mark() {
			var best = 0, bestDist = Infinity, left = track.scrollLeft;
			Array.prototype.forEach.call(items, function (item, i) {
				var d = Math.abs(item.offsetLeft - track.offsetLeft - left);
				if (d < bestDist) { bestDist = d; best = i; }
			});
			setCurrent(best);
		}
		function setCurrent(n) {
			Array.prototype.forEach.call(dots.children, function (b, i) { b.setAttribute('aria-current', i === n ? 'true' : 'false'); });
		}
		var settle;
		track.addEventListener('scroll', function () { clearTimeout(settle); settle = setTimeout(mark, 90); }, { passive: true });
		mark();
	});

	/* Copy link (share bar). */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.kit-share-copy, .aw-share-copy');
		if (!b || !navigator.clipboard) return;
		var label = b.textContent;
		navigator.clipboard.writeText(b.dataset.url).then(function () {
			b.textContent = b.dataset.done;
			setTimeout(function () { b.textContent = label; }, 2000);
		});
	});

	/* 4. Shared form behaviour for every .kit-form (Phase 11 F.1).
	   Validate on submit; after the first submit, check a field again when it loses focus, and clear its message as
	   soon as it is valid while typing. Focus the first invalid field; a summary only when there is more than one.
	   Send with fetch() (the server answers in JSON and the form keeps what was typed); without JavaScript the normal
	   POST gives the same messages. Messages come from data-msg-* on each field, set by the server. */
	var GENERIC = 'Couldn\'t send. Your details are still here — try again.';
	function errorEl(el) {
		var id = (el.id || el.name.replace(/\W/g, '')) + '-error';
		var e = document.getElementById(id);
		if (!e) {
			e = document.createElement('span');
			e.className = 'kit-field-message kit-field-error';
			e.id = id;
			e.setAttribute('aria-live', 'polite');
			(el.closest('.kit-field-control') || el.closest('.kit-check') || el).insertAdjacentElement('afterend', e);
		}
		var by = (el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
		if (by.indexOf(id) < 0) { by.push(id); el.setAttribute('aria-describedby', by.join(' ')); }
		return e;
	}
	function message(el) {
		var v = el.validity, d = el.dataset;
		if (v.customError) return el.validationMessage;
		if (v.valueMissing) return d.msgRequired || (el.type === 'email' ? 'Enter your email address' : el.type === 'checkbox' ? 'Please tick this box.' : 'This is required.');
		if (v.typeMismatch && el.type === 'email') return d.msgEmail || 'Enter a full email address, like you@example.com';
		if (v.typeMismatch && el.type === 'url') return d.msgUrl || 'Enter a link starting with https://.';
		if (v.tooLong) return d.msgMax || 'This is too long.';
		if (v.patternMismatch) return d.msgPattern || 'Check the format.';
		return el.validationMessage;
	}
	function show(el, text) {
		el.setAttribute('aria-invalid', text ? 'true' : 'false');
		el.classList.toggle('is-valid', !text && el.value !== '' && el.type !== 'checkbox');
		errorEl(el).textContent = text || '';
	}
	// Rules beyond HTML: 10-digit phones (+91 or a leading 0 allowed), web addresses get https://, files ≤ size.
	function custom(el) {
		var d = el.dataset, v = el.value.trim();
		el.setCustomValidity('');
		if (d.rule === 'phone' && v) {
			var n = v.replace(/\D/g, '');
			if (n.length === 12 && n.indexOf('91') === 0) n = n.slice(2); else if (n.length === 11 && n[0] === '0') n = n.slice(1);
			if (n.length !== 10) el.setCustomValidity(d.msgPhone || 'Enter a 10-digit phone number, like 98765 43210');
		}
		if (d.rule === 'url' && v && !/^https?:\/\//i.test(v)) { el.value = 'https://' + v; }
		if (el.type === 'file' && el.files && el.files[0]) {
			var ok = /^image\/(png|jpeg|webp)$/.test(el.files[0].type) && el.files[0].size <= +(d.maxBytes || Infinity);
			if (!ok) el.setCustomValidity(d.msgFile || 'Use a PNG, JPG or WebP under 2 MB.');
		}
	}
	function check(el, display) {
		if (!el.willValidate || el.type === 'hidden' || el.closest('.kit-hp') || !el.name) return true;
		if (el.type === 'email') el.value = el.value.trim();
		custom(el);
		var ok = el.checkValidity();
		if (display) show(el, ok ? '' : message(el));
		return ok;
	}
	function fields(form) { return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function (el) { return !el.closest('.kit-check-group'); }); }
	// Checkbox groups (fieldset.kit-check-group[data-min]): at least N ticked.
	function groupOk(g, display) {
		var n = g.querySelectorAll('input[type=checkbox]:checked').length, ok = n >= +(g.dataset.min || 0);
		if (display) {
			var m = g.querySelector('.kit-field-message');
			if (m) m.textContent = ok ? '' : (g.dataset.msgRequired || 'Choose at least one.');
			g.classList.toggle('is-invalid', !ok);
			g.querySelectorAll('input').forEach(function (i) { i.setAttribute('aria-invalid', ok ? 'false' : 'true'); });
		}
		return ok;
	}
	function groups(form) { return Array.prototype.slice.call(form.querySelectorAll('fieldset.kit-check-group[data-min]')); }
	function status(form, text, ok) {
		var st = form.querySelector('.kit-form-status');
		if (!st) return;
		st.classList.toggle('is-success', !!ok);
		st.textContent = text || '';
		if (text) st.focus();
	}
	function summary(form, bad) {
		var old = form.querySelector('.kit-form-summary');
		if (old) old.remove();
		if (bad.length < 2) return;
		var box = document.createElement('div');
		box.className = 'kit-form-summary';
		box.setAttribute('role', 'alert');
		box.innerHTML = '<strong>Please fix these ' + bad.length + ' before sending:</strong><ul></ul>';
		bad.forEach(function (el) {
			var li = document.createElement('li'), a = document.createElement('a');
			a.href = '#' + el.id; a.textContent = errorEl(el).textContent;
			a.addEventListener('click', function (ev) { ev.preventDefault(); el.focus(); });
			li.appendChild(a); box.querySelector('ul').appendChild(li);
		});
		var st = form.querySelector('.kit-form-status');
		(st || form.firstChild).insertAdjacentElement(st ? 'afterend' : 'beforebegin', box);
	}
	function busy(btn, on) {
		if (!btn) return;
		btn.classList.toggle('is-loading', on);
		if (on) btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy');
		btn.disabled = on;
	}
	function done(form, data) {
		var box = document.createElement('div');
		box.className = 'kit-form-done';
		box.setAttribute('role', 'status');
		box.tabIndex = -1;
		var h = document.createElement('p'); h.className = 'aw-form-done-title'; h.textContent = data.title || ''; box.appendChild(h);
		if (data.text) { var t = document.createElement('p'); t.textContent = data.text; box.appendChild(t); }
		if (data.again) {
			var again = document.createElement('button');
			again.type = 'button'; again.className = 'kit-btn kit-btn--secondary'; again.textContent = data.again;
			again.addEventListener('click', function () { form.reset(); fields(form).forEach(function (el) { show(el, ''); el.classList.remove('is-valid'); }); box.replaceWith(form); var f = form.querySelector('input:not([type=hidden]), select, textarea'); if (f) f.focus(); });
			box.appendChild(again);
		}
		form.replaceWith(box);
		box.focus();
	}
	document.querySelectorAll('form.kit-form, form.kit-form').forEach(function (form) {
		form.setAttribute('novalidate', '');
		var submitted = false;
		var served = form.querySelector('.kit-form-summary') || form.querySelector('.kit-form-status:not(:empty)') || form.querySelector('[aria-invalid=true]');
		if (served) { submitted = true; served.focus(); } // errors returned by the server (no-JS path)
		form.addEventListener('focusout', function (e) {
			if (submitted && e.target.matches('input, select, textarea')) check(e.target, true);
		});
		form.addEventListener('input', function (e) {
			var el = e.target;
			if (el.getAttribute('aria-invalid') === 'true' && el.checkValidity()) show(el, ''); // clear as soon as it is valid
		});
		form.addEventListener('change', function (e) {
			if (submitted && (e.target.type === 'checkbox' || e.target.tagName === 'SELECT')) check(e.target, true);
			var g = e.target.closest('.kit-check-group');
			if (g && submitted) groupOk(g, true);
		});
		// Keep what was typed in this tab until the form is sent (a reload or an expired page does not lose it).
		var KEY = 'kit-form:' + location.pathname + ':' + (form.id || form.getAttribute('action'));
		function save() {
			try {
				var o = {};
				form.querySelectorAll('input, select, textarea').forEach(function (el) {
					if (!el.name || el.type === 'hidden' || el.type === 'file' || el.closest('.kit-hp')) return;
					if (el.type === 'checkbox') { if (el.checked) (o[el.name] = o[el.name] || []).push(el.value); } else o[el.name] = el.value;
				});
				sessionStorage.setItem(KEY, JSON.stringify(o));
			} catch (x) {}
		}
		try {
			var kept = JSON.parse(sessionStorage.getItem(KEY) || 'null');
			if (kept && !fields(form).some(function (el) { return el.type !== 'hidden' && el.type !== 'checkbox' && el.value && !el.closest('.kit-hp'); })) {
				Object.keys(kept).forEach(function (name) {
					form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
						if (el.type === 'checkbox') el.checked = [].concat(kept[name]).indexOf(el.value) > -1; else if (el.type !== 'file') el.value = kept[name];
					});
				});
			}
		} catch (x) {}
		form.addEventListener('input', save);
		form.addEventListener('change', save);
		// Characters used on long text (shown from data-counter characters on).
		form.querySelectorAll('textarea[data-counter][maxlength]').forEach(function (ta) {
			var c = document.createElement('span');
			c.className = 'kit-field-hint kit-counter'; c.setAttribute('aria-live', 'polite');
			(ta.closest('.kit-field-control') || ta).insertAdjacentElement('afterend', c);
			function upd() { var n = ta.value.length; c.textContent = n >= +ta.dataset.counter ? n.toLocaleString('en-IN') + ' of ' + (+ta.maxLength).toLocaleString('en-IN') + ' characters' : ''; }
			ta.addEventListener('input', upd); upd();
		});
		form.addEventListener('submit', function (e) {
			submitted = true;
			status(form, '');
			var bad = fields(form).filter(function (el) { return !check(el, true); });
			groups(form).forEach(function (g) { if (!groupOk(g, true)) bad.push(g.querySelector('input')); });
			var custom = form.dispatchEvent(new CustomEvent('aw:validate', { cancelable: true, detail: { errors: bad } }));
			summary(form, bad);
			if (bad.length || !custom) { e.preventDefault(); if (bad[0]) bad[0].focus(); return; }
			var btn = form.querySelector('button[type=submit]');
			if (btn && btn.classList.contains('is-loading')) { e.preventDefault(); return; }
			// ALTCHA in automatic mode checks on send and then sends the form again: let it run first.
			var auto = form.querySelector('altcha-widget[auto=onsubmit]'), solved = form.querySelector('input[name=altcha]');
			if (auto && !(solved && solved.value)) {
				if (!window.customElements || !customElements.get('altcha-widget')) {
					e.preventDefault();
					var first = form.querySelector('input:not([type=hidden])');
					if (first) first.dispatchEvent(new Event('focusin', { bubbles: true })); // loads the widget
					if (window.customElements) customElements.whenDefined('altcha-widget').then(function () { setTimeout(function () { form.requestSubmit(); }, 50); });
				}
				return; // the widget stops this send, checks, and sends again
			}
			if (!window.fetch || !window.FormData || form.enctype === 'multipart/form-data' && form.querySelector('input[type=file]')) { busy(btn, true); return; } // normal POST
			e.preventDefault();
			busy(btn, true);
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'X-AW-Fetch': '1' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					busy(btn, false);
					// The page's form key expired (a cached page open a long time): get a fresh one and send once more.
					if (data.code === 'expired' && !form.dataset.retried) {
						form.dataset.retried = '1';
						var act = form.querySelector('input[name=action]');
						return fetch(form.getAttribute('action').replace('admin-post.php', 'admin-ajax.php') + '?action=kits_form_nonce&form=' + encodeURIComponent(act ? act.value : ''), { credentials: 'same-origin' })
							.then(function (r) { return r.json(); })
							.then(function (k) { var n = form.querySelector('input[name=_aw_nonce]'); if (n && k.nonce) { n.value = k.nonce; form.requestSubmit(); } else status(form, data.notice); });
					}
					if (data.ok) { try { sessionStorage.removeItem(KEY); } catch (x) {} done(form, data); return; }
					var list = [];
					Object.keys(data.errors || {}).forEach(function (name) {
						var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
						if (el) { show(el, data.errors[name]); list.push(el); }
					});
					summary(form, list);
					if (data.notice) status(form, data.notice); else if (list[0]) list[0].focus();
				})
				.catch(function () { busy(btn, false); status(form, form.dataset.genericError || GENERIC); });
		});
	});
})();

/* Expandable forms (kits_disclosure, S5.6): the label turns to "Close"; #id opens one and puts focus in its first field;
   in a group only one is open. Multi-step forms (kits_form_steps, S5.7): one step at a time, Next checks the step. */
(function () {
	function first(el) { var f = el.querySelector('.kit-disclosure-body input:not([type=hidden]):not([tabindex="-1"]), .kit-disclosure-body select, .kit-disclosure-body textarea'); if (f) f.focus(); }
	var all = Array.prototype.slice.call(document.querySelectorAll('details.kit-disclosure'));
	all.forEach(function (d) {
		var s = d.querySelector('summary');
		s.setAttribute('aria-expanded', d.open ? 'true' : 'false');
		d.addEventListener('toggle', function () {
			s.setAttribute('aria-expanded', d.open ? 'true' : 'false');
			if (!d.open) return;
			if (d.dataset.group) all.forEach(function (o) { if (o !== d && o.dataset.group === d.dataset.group) o.open = false; });
			first(d);
		});
	});
	function fromHash() {
		var id = location.hash.slice(1), d = id && document.getElementById(id);
		if (d && d.matches('details.kit-disclosure')) { d.open = true; d.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); first(d); }
	}
	fromHash();
	window.addEventListener('hashchange', fromHash);

	document.querySelectorAll('.kit-steps').forEach(function (box) {
		var steps = Array.prototype.slice.call(box.querySelectorAll('.kit-step'));
		if (steps.length < 2) return;
		box.classList.add('is-stepped');
		var bad = steps.findIndex(function (s) { return s.querySelector('[aria-invalid=true]'); });
		var cur = bad > -1 ? bad : 0;
		function go(i) { cur = i; steps.forEach(function (s, n) { s.hidden = n !== i; }); var lg = steps[i].querySelector('legend'); if (lg) { lg.tabIndex = -1; lg.focus(); } }
		steps.forEach(function (s, n) { s.hidden = n !== cur; });
		box.addEventListener('click', function (e) {
			var next = e.target.closest('[data-step-next]'), back = e.target.closest('[data-step-back]');
			if (back) { go(cur - 1); return; }
			if (!next) return;
			var wrong = Array.prototype.slice.call(steps[cur].querySelectorAll('input, select, textarea')).filter(function (el) {
				if (!el.willValidate || el.type === 'hidden' || !el.name || el.closest('.kit-hp')) return false;
				return !el.checkValidity();
			});
			if (wrong.length) {
				wrong.forEach(function (el) {
					el.setAttribute('aria-invalid', 'true');
					var m = document.getElementById(el.id + '-error');
					if (m) m.textContent = el.validity.valueMissing ? (el.dataset.msgRequired || 'Enter this') : (el.dataset.msgEmail || el.dataset.msgPattern || el.validationMessage);
				});
				wrong[0].focus();
				return;
			}
			go(cur + 1);
		});
	});
})();

/* Menu clicks: one analytics event per click on a header, menu or footer link (GA4 through gtag, or GTM's dataLayer). */
(function () {
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var zone = a.closest('[data-menu]');
		var area = zone ? zone.getAttribute('data-menu')
			: a.closest('.kit-menu-secondary') ? 'secondary'
			: a.closest('.wp-block-navigation') && a.closest('header, .kit-site-header, .kit-site-header') ? 'primary' : '';
		if (!area) return;
		var data = { menu_area: area, link_text: (a.textContent || a.getAttribute('aria-label') || '').trim().slice(0, 100), link_url: a.getAttribute('href') };
		(window.dataLayer = window.dataLayer || []).push(Object.assign({ event: 'menu_click' }, data)); // for GTM
		if (typeof window.gtag === 'function') window.gtag('event', 'menu_click', data); // GA4 through Site Kit
	}, { capture: true });
})();

/* Header search: the icon opens a search field in place (it stays a link to the search page without JavaScript). */
(function () {
	document.querySelectorAll('[data-search-toggle]').forEach(function (btn) {
		var form = document.getElementById(btn.getAttribute('aria-controls'));
		if (!form) return;
		var input = form.querySelector('input');
		function set(open) {
			form.hidden = !open;
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) input.focus();
		}
		btn.addEventListener('click', function (e) { e.preventDefault(); set(form.hidden); });
		form.addEventListener('keydown', function (e) { if (e.key === 'Escape') { set(false); btn.focus(); } });
	});
})();

/* Filters (kits_filter_bar): below 640px the groups fold into a "Filters" sheet (focus kept inside, Esc closes);
   groups with many options show the first eight until "Show more". */
(function () {
	var phone = window.matchMedia('(max-width: 639px)');
	document.querySelectorAll('.kit-filter-sheet').forEach(function (sheet) {
		var summary = sheet.querySelector('summary');
		function layout() {
			sheet.classList.toggle('is-sheet', phone.matches);
			if (phone.matches) sheet.removeAttribute('open'); else sheet.setAttribute('open', '');
		}
		layout();
		phone.addEventListener('change', layout);
		sheet.addEventListener('toggle', function () {
			if (!sheet.classList.contains('is-sheet')) return;
			if (sheet.open) {
				var first = sheet.querySelector('.kit-filters summary, .kit-filters a, .kit-filters button, .kit-filters input');
				if (first) first.focus();
			}
		});
		sheet.addEventListener('keydown', function (e) {
			if (!sheet.classList.contains('is-sheet') || !sheet.open) return;
			if (e.key === 'Escape') { sheet.open = false; summary.focus(); return; }
			if (e.key !== 'Tab') return;
			var f = Array.prototype.filter.call(sheet.querySelectorAll('.kit-filters summary, .kit-filters a, .kit-filters button, .kit-filters input'), function (el) { return el.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
	});
	document.querySelectorAll('.kit-filter-more').forEach(function (btn) {
		var group = document.getElementById(btn.getAttribute('aria-controls'));
		btn.addEventListener('click', function () {
			var open = group.classList.toggle('is-expanded');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			btn.textContent = open ? btn.getAttribute('data-less') : btn.getAttribute('data-more');
		});
	});
})();

/* External links: mark text links to other sites with an arrow (CSS) and a screen-reader note. Icon, image and logo links are left as they are. */
(function () {
	function mark(root) {
		root.querySelectorAll('a[href^="http"]:not(.kit-link-ext)').forEach(function (a) {
			if (a.host === location.host || a.querySelector('img, svg, .kit-ico, .kit-ico') || !a.textContent.trim() || a.closest('.wp-block-navigation, .kit-ad')) {
				return;
			}
			a.classList.add('kit-link-ext');
			var sr = document.createElement('span');
			sr.className = 'screen-reader-text';
			sr.textContent = ' (external site)';
			a.appendChild(sr);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { mark(document); });
	} else {
		mark(document);
	}
})();

/* Folded sections (data-kit-fold): open on wide screens, folded below lg (1200px); open without JavaScript. */
(function () {
	var mq = window.matchMedia('(max-width: 1199px)');
	function apply() {
		document.querySelectorAll('details[data-kit-fold]').forEach(function (d) { d.open = !mq.matches; });
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', apply);
	} else {
		apply();
	}
	if (mq.addEventListener) {
		mq.addEventListener('change', apply);
	}
})();

/* Dropdowns (kits_filter_dropdown, kits_sort_dropdown): one open at a time; a click outside or Esc closes (focus back
   on the button); arrow keys move through the options. Ticking a filter applies it at once, except in the phone sheet,
   where "Apply" sends them together. Several values of one filter go as topic=a,b. */
(function () {
	var dds = Array.prototype.slice.call(document.querySelectorAll('details[data-kit-dd]'));
	if (!dds.length) return;
	function closeAll(except) { dds.forEach(function (d) { if (d !== except) d.open = false; }); }
	dds.forEach(function (d) {
		var btn = d.querySelector('summary');
		d.addEventListener('toggle', function () { if (d.open && !d.closest('.is-sheet')) closeAll(d); });
		d.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && d.open) { e.stopPropagation(); d.open = false; btn.focus(); return; }
			if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
			var items = Array.prototype.slice.call(d.querySelectorAll('.kit-dd-panel input, .kit-dd-panel a'));
			if (!items.length) return;
			e.preventDefault();
			if (!d.open) { d.open = true; items[0].focus(); return; }
			var i = items.indexOf(document.activeElement);
			items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
		});
	});
	document.addEventListener('click', function (e) { if (!e.target.closest('details[data-kit-dd]')) closeAll(null); });

	function go(form) {
		var params = new URLSearchParams(), lists = {};
		Array.prototype.forEach.call(form.elements, function (el) {
			if (!el.name || el.disabled) return;
			if (el.type === 'checkbox') {
				if (!el.checked) return;
				var n = el.name.replace(/\[\]$/, '');
				(lists[n] = lists[n] || []).push(el.value);
			} else if (el.type === 'hidden') {
				params.set(el.name, el.value);
			}
		});
		Object.keys(lists).forEach(function (n) { params.set(n, lists[n].join(',')); });
		var status = form.parentNode.querySelector('[data-kit-filter-status]');
		if (status) status.textContent = 'Loading results';
		var q = params.toString();
		window.location.href = form.getAttribute('action').split('?')[0] + (q ? '?' + q : '');
	}
	document.querySelectorAll('form[data-kit-filterbar]').forEach(function (form) {
		form.addEventListener('submit', function (e) { e.preventDefault(); go(form); });
		form.addEventListener('change', function (e) {
			if (e.target.classList.contains('kit-dd-check') && !e.target.closest('.is-sheet')) go(form);
		});
	});
})();

