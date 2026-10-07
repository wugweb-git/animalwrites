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

	/* 4. Shared form validation for every .aw-form: inline errors, summary, loading state. */
	var MSG = {
		valueMissing: 'This is required.',
		typeMismatch: { email: 'Enter an email address like name@example.com.', url: 'Enter a link starting with https://.' },
		patternMismatch: 'Check the format.',
		tooLong: 'This is too long.',
		rangeUnderflow: 'Enter a larger number.',
		rangeOverflow: 'Enter a smaller number.',
		badInput: 'Enter a number.'
	};
	function fieldLabel(el) {
		var l = el.id && document.querySelector('label[for="' + el.id + '"]');
		if (l) { l = l.cloneNode(true); l.querySelectorAll('.aw-optional').forEach(function (o) { o.remove(); }); }
		return (l ? l.textContent : el.getAttribute('aria-label') || el.name || 'Field').trim();
	}
	function errorEl(el) {
		var id = (el.id || el.name.replace(/\W/g, '')) + '-error';
		var e = document.getElementById(id);
		if (!e) {
			e = document.createElement('span');
			e.className = 'aw-field-error';
			e.id = id;
			e.setAttribute('aria-live', 'polite');
			(el.closest('.aw-check') || el).insertAdjacentElement('afterend', e);
		}
		var by = (el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
		if (by.indexOf(id) < 0) { by.push(id); el.setAttribute('aria-describedby', by.join(' ')); }
		return e;
	}
	function message(el) {
		var v = el.validity;
		if (v.customError) return el.validationMessage;
		if (el.dataset.error && !v.valid) return el.dataset.error;
		if (v.valueMissing) return el.type === 'checkbox' ? 'Please tick this box.' : MSG.valueMissing;
		if (v.typeMismatch) return MSG.typeMismatch[el.type] || MSG.patternMismatch;
		for (var k in MSG) { if (v[k] && typeof MSG[k] === 'string') return MSG[k]; }
		return el.validationMessage;
	}
	function check(el, show) {
		if (!el.willValidate || el.type === 'hidden' || el.closest('.aw-hp')) return true;
		if (el.type === 'file' && el.dataset.maxBytes) {
			var big = Array.prototype.some.call(el.files, function (f) { return f.size > +el.dataset.maxBytes; });
			el.setCustomValidity(big ? 'The file is larger than ' + Math.round(el.dataset.maxBytes / 1048576) + ' MB.' : '');
		}
		var ok = el.checkValidity();
		if (show || el.getAttribute('aria-invalid') === 'true') {
			el.setAttribute('aria-invalid', ok ? 'false' : 'true');
			el.classList.toggle('is-valid', ok && el.value !== '');
			errorEl(el).textContent = ok ? '' : message(el);
		}
		return ok;
	}
	document.querySelectorAll('form.kit-form, form.aw-form').forEach(function (form) {
		form.setAttribute('novalidate', '');
		var served = form.querySelector('.aw-form-summary');
		if (served) served.focus(); // Errors returned by the server (no-JS path or server-only rules).
		form.addEventListener('focusout', function (e) {
			if (e.target.matches('input, select, textarea') && e.target.value !== '') check(e.target, true);
		});
		form.addEventListener('change', function (e) { if (e.target.type === 'file') check(e.target, true); });
		form.addEventListener('input', function (e) {
			if (e.target.getAttribute('aria-invalid') === 'true') check(e.target, true);
		});
		form.addEventListener('submit', function (e) {
			var bad = [];
			form.querySelectorAll('input, select, textarea').forEach(function (el) { if (!check(el, true)) bad.push(el); });
			var custom = form.dispatchEvent(new CustomEvent('aw:validate', { cancelable: true, detail: { errors: bad } }));
			var summary = form.querySelector('.aw-form-summary');
			if (bad.length || !custom) {
				e.preventDefault();
				if (!summary) {
					summary = document.createElement('div');
					summary.className = 'aw-form-summary';
					summary.setAttribute('role', 'alert');
					summary.tabIndex = -1;
					form.insertBefore(summary, form.firstChild);
				}
				summary.innerHTML = '<strong>Please fix ' + (bad.length === 1 ? 'this' : 'these ' + bad.length) + ' before sending:</strong><ul></ul>';
				var ul = summary.querySelector('ul');
				bad.forEach(function (el) {
					var li = document.createElement('li'), a = document.createElement('a');
					a.href = '#' + el.id;
					a.textContent = fieldLabel(el) + ': ' + message(el);
					a.addEventListener('click', function (ev) { ev.preventDefault(); el.focus(); });
					li.appendChild(a); ul.appendChild(li);
				});
				summary.focus();
				return;
			}
			if (summary) summary.remove();
			var btn = form.querySelector('button[type=submit]');
			if (btn) {
				if (btn.classList.contains('is-loading')) { e.preventDefault(); return; }
				btn.classList.add('is-loading');
				btn.setAttribute('aria-busy', 'true');
			}
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
