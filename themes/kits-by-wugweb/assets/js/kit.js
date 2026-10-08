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
	var bar = document.querySelector('.kit-sticky-help, .aw-sticky-help');
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
	document.querySelectorAll('.kit-cards .wp-block-post-template, .aw-cards .wp-block-post-template').forEach(function (track) {
		var items = track.children;
		if (items.length < 2) return;
		var dots = document.createElement('div');
		dots.className = 'aw-carousel-dots';
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

	/* 4. Shared form behaviour for every .aw-form (Phase 11 F.1).
	   Validate on submit; after the first submit, check a field again when it loses focus, and clear its message as
	   soon as it is valid while typing. Focus the first invalid field; a summary only when there is more than one.
	   Send with fetch() (the server answers in JSON and the form keeps what was typed); without JavaScript the normal
	   POST gives the same messages. Messages come from data-msg-* on each field, set by the server. */
	var GENERIC = 'Something went wrong. Your details are still here — try again.';
	function errorEl(el) {
		var id = (el.id || el.name.replace(/\W/g, '')) + '-error';
		var e = document.getElementById(id);
		if (!e) {
			e = document.createElement('span');
			e.className = 'kit-field-message aw-field-error';
			e.id = id;
			e.setAttribute('aria-live', 'polite');
			(el.closest('.kit-field-control') || el.closest('.aw-check') || el).insertAdjacentElement('afterend', e);
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
	function check(el, display) {
		if (!el.willValidate || el.type === 'hidden' || el.closest('.aw-hp') || !el.name) return true;
		if (el.type === 'email') el.value = el.value.trim();
		var ok = el.checkValidity();
		if (display) show(el, ok ? '' : message(el));
		return ok;
	}
	function fields(form) { return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')); }
	function status(form, text, ok) {
		var st = form.querySelector('.kit-form-status');
		if (!st) return;
		st.classList.toggle('is-success', !!ok);
		st.textContent = text || '';
		if (text) st.focus();
	}
	function summary(form, bad) {
		var old = form.querySelector('.aw-form-summary');
		if (old) old.remove();
		if (bad.length < 2) return;
		var box = document.createElement('div');
		box.className = 'aw-form-summary';
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
		box.className = 'aw-form-done kit-form-done';
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
	document.querySelectorAll('form.kit-form, form.aw-form').forEach(function (form) {
		form.setAttribute('novalidate', '');
		var submitted = false;
		var served = form.querySelector('.aw-form-summary') || form.querySelector('.kit-form-status:not(:empty)') || form.querySelector('[aria-invalid=true]');
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
		});
		form.addEventListener('submit', function (e) {
			submitted = true;
			status(form, '');
			var bad = fields(form).filter(function (el) { return !check(el, true); });
			var custom = form.dispatchEvent(new CustomEvent('aw:validate', { cancelable: true, detail: { errors: bad } }));
			summary(form, bad);
			if (bad.length || !custom) { e.preventDefault(); if (bad[0]) bad[0].focus(); return; }
			var btn = form.querySelector('button[type=submit]');
			if (btn && btn.classList.contains('is-loading')) { e.preventDefault(); return; }
			if (!window.fetch || !window.FormData || form.enctype === 'multipart/form-data' && form.querySelector('input[type=file]')) { busy(btn, true); return; } // normal POST
			e.preventDefault();
			busy(btn, true);
			fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-AW-Fetch': '1' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					busy(btn, false);
					if (data.ok) { done(form, data); return; }
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

/* Menu clicks: one analytics event per click on a header, menu or footer link (GA4 through gtag, or GTM's dataLayer). */
(function () {
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var zone = a.closest('[data-menu]');
		var area = zone ? zone.getAttribute('data-menu')
			: a.closest('.kit-menu-secondary') ? 'secondary'
			: a.closest('.wp-block-navigation') && a.closest('header, .aw-site-header, .kit-site-header') ? 'primary' : '';
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
				var first = sheet.querySelector('.kit-filters a, .kit-filters button');
				if (first) first.focus();
			}
		});
		sheet.addEventListener('keydown', function (e) {
			if (!sheet.classList.contains('is-sheet') || !sheet.open) return;
			if (e.key === 'Escape') { sheet.open = false; summary.focus(); return; }
			if (e.key !== 'Tab') return;
			var f = Array.prototype.filter.call(sheet.querySelectorAll('.kit-filters a, .kit-filters button'), function (el) { return el.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
		var close = sheet.querySelector('[data-kit-sheet-close]');
		if (close) close.addEventListener('click', function () { sheet.open = false; summary.focus(); });
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
