/* Animal Writes interactions. No dependencies. Respects prefers-reduced-motion. */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var doc = document.documentElement;

	/* 1. One orchestrated moment: homepage hero animates on the first view of a session only. */
	try {
		if (!reduce && document.body.classList.contains('home') && !sessionStorage.getItem('aw_seen')) {
			doc.classList.add('aw-motion');
			sessionStorage.setItem('aw_seen', '1');
		}
	} catch (e) {}

	/* 2. Sticky help bar: hide while scrolling down, show on scroll up or near the top. */
	var bar = document.querySelector('.aw-sticky-help');
	if (bar) {
		var lastY = window.scrollY, ticking = false;
		window.addEventListener('scroll', function () {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(function () {
				var y = window.scrollY;
				bar.classList.toggle('is-hidden', y > lastY && y > 240);
				lastY = y;
				ticking = false;
			});
		}, { passive: true });
		bar.addEventListener('focusin', function () { bar.classList.remove('is-hidden'); });
	}

	/* 3. Carousels: position dots that also work as controls. */
	document.querySelectorAll('.aw-cards .wp-block-post-template').forEach(function (track) {
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
			Array.prototype.forEach.call(dots.children, function (b, i) { b.setAttribute('aria-current', i === best ? 'true' : 'false'); });
		}
		track.addEventListener('scroll', function () { requestAnimationFrame(mark); }, { passive: true });
		mark();
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
		if (el.dataset.error && !v.valid) return el.dataset.error;
		if (v.valueMissing) return el.type === 'checkbox' ? 'Please tick this box.' : MSG.valueMissing;
		if (v.typeMismatch) return MSG.typeMismatch[el.type] || MSG.patternMismatch;
		for (var k in MSG) { if (v[k] && typeof MSG[k] === 'string') return MSG[k]; }
		return el.validationMessage;
	}
	function check(el, show) {
		if (!el.willValidate || el.type === 'hidden' || el.closest('.aw-hp')) return true;
		var ok = el.checkValidity();
		if (show || el.getAttribute('aria-invalid') === 'true') {
			el.setAttribute('aria-invalid', ok ? 'false' : 'true');
			el.classList.toggle('is-valid', ok && el.value !== '');
			errorEl(el).textContent = ok ? '' : message(el);
		}
		return ok;
	}
	document.querySelectorAll('form.aw-form').forEach(function (form) {
		form.setAttribute('novalidate', '');
		form.addEventListener('focusout', function (e) {
			if (e.target.matches('input, select, textarea') && e.target.value !== '') check(e.target, true);
		});
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
