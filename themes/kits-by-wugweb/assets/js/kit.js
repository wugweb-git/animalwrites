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
		var html = b.innerHTML; // icon + label
		navigator.clipboard.writeText(b.dataset.url).then(function () {
			b.textContent = b.dataset.done;
			setTimeout(function () { b.innerHTML = html; }, 2000);
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
		form.dispatchEvent(new CustomEvent('kit:form-done', { bubbles: true })); // analytics (tracking module below)
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
		var KEY = 'kit-form:' + location.pathname + ':' + (form.id || form.getAttribute('action')) + (form.dataset.key ? ':' + form.dataset.key : ''); // data-key: one form page for many items
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
			if (bad.length || !custom) { e.preventDefault(); form.dispatchEvent(new CustomEvent('kit:form-error', { bubbles: true, detail: { where: 'browser', fields: bad.length } })); if (bad[0]) bad[0].focus(); return; }
			if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return; // a GET form (a search, like the Claim finder) opens its results page
			try { sessionStorage.setItem('kit-sent', '1'); } catch (x) {} // analytics: the thank-you page counts a send only after this
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
					if (data.ok && data.redirect) { try { sessionStorage.removeItem(KEY); } catch (x) {} busy(btn, true); location.assign(data.redirect); return; } // the thank-you page
					if (data.ok) { try { sessionStorage.removeItem(KEY); } catch (x) {} done(form, data); return; }
					var list = [];
					Object.keys(data.errors || {}).forEach(function (name) {
						var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
						if (el) { show(el, data.errors[name]); list.push(el); }
					});
					summary(form, list);
					form.dispatchEvent(new CustomEvent('kit:form-error', { bubbles: true, detail: { where: 'server', fields: list.length } }));
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
		// A progress header naming every step, the current one marked (visual; each step's legend still says "Step n of m").
		var bar = document.createElement('ol');
		bar.className = 'kit-steps-progress';
		bar.setAttribute('aria-hidden', 'true');
		steps.forEach(function (s, n) {
			var lg = s.querySelector('legend'), li = document.createElement('li');
			var name = lg ? (lg.querySelector('.kit-step-title') || lg).textContent.replace(/^\s*Step \d+ of \d+\s*/i, '').trim() : 'Step ' + (n + 1);
			li.innerHTML = '<span class="kit-steps-num">' + (n + 1) + '</span><span class="kit-steps-name"></span>';
			li.lastChild.textContent = name;
			bar.appendChild(li);
		});
		box.insertBefore(bar, steps[0]);
		function mark() { Array.prototype.forEach.call(bar.children, function (li, n) { li.classList.toggle('is-current', n === cur); li.classList.toggle('is-done', n < cur); }); }
		function go(i) { cur = i; mark(); box.dispatchEvent(new CustomEvent('kit:step', { bubbles: true, detail: { step: i + 1 } })); steps.forEach(function (s, n) { s.hidden = n !== i; }); var lg = steps[i].querySelector('legend'); if (lg) { lg.tabIndex = -1; lg.focus(); } }
		steps.forEach(function (s, n) { s.hidden = n !== cur; });
		mark();
		setTimeout(function () { box.dispatchEvent(new CustomEvent('kit:step', { bubbles: true, detail: { step: cur + 1 } })); }, 0); // analytics: the first step shown
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
					var text = el.validity.valueMissing ? (el.dataset.msgRequired || 'Enter this') : (el.dataset.msgEmail || el.dataset.msgPattern || el.validationMessage);
					var m = document.getElementById(el.id + '-error');
					var ff = !m && el.closest('.ff-el-input--content'); // a form plugin's field (Fluent Forms): its own error line
					if (ff) {
						m = ff.querySelector('div.error');
						if (!m) { m = document.createElement('div'); m.className = 'error text-danger'; m.setAttribute('role', 'alert'); ff.appendChild(m); }
						el.closest('.ff-el-group').classList.add('ff-el-is-error');
					}
					if (m) m.textContent = text;
				});
				wrong[0].focus();
				box.dispatchEvent(new CustomEvent('kit:form-error', { bubbles: true, detail: { where: 'step ' + (cur + 1), fields: wrong.length } })); // analytics
				return;
			}
			go(cur + 1);
		});
		// A form plugin (Fluent Forms) that finds errors on send: show the first step that has one.
		if (window.jQuery) jQuery(box.closest('form')).on('fluentform_validation_failed fluentform_submission_failed', function () {
			setTimeout(function () {
				var i = steps.findIndex(function (s) { return s.querySelector('.ff-el-is-error, [aria-invalid=true]'); });
				if (i > -1 && i !== cur) go(i);
			}, 0);
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
		if ((window.kitsPage || {}).gtag && typeof window.gtag === 'function') window.gtag('event', 'menu_click', data); // GA4 through Site Kit, until GA4 runs inside GTM
	}, { capture: true });
})();

/* Header search (every width): the icon opens a field across the whole header row; the menu and contact step aside.
   Esc, the close button or a click outside closes it and puts focus back on the icon. Without JavaScript the icon is a
   link to the search page. */
(function () {
	document.querySelectorAll('[data-search-toggle]').forEach(function (btn) {
		var form = document.getElementById(btn.getAttribute('aria-controls'));
		var header = btn.closest('.kit-site-header');
		if (!form || !header) return;
		var input = form.querySelector('input');
		var close = form.querySelector('.kit-hsearch-close');
		if (!close) {
			close = document.createElement('button');
			close.type = 'button';
			close.className = 'kit-hsearch-close';
			close.setAttribute('aria-label', 'Close search');
			close.innerHTML = '<span class="kit-ico kit-ico--x" aria-hidden="true"></span>';
			form.appendChild(close);
		}
		function set(open, back) {
			form.hidden = !open;
			header.classList.toggle('is-searching', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) input.focus(); else if (back) btn.focus();
		}
		btn.addEventListener('click', function (e) { e.preventDefault(); set(form.hidden); });
		close.addEventListener('click', function () { set(false, true); });
		form.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false, true); });
		document.addEventListener('click', function (e) { if (!form.hidden && !form.contains(e.target) && e.target !== btn && !btn.contains(e.target)) set(false); });
	});
})();

/* Sticky nav: the announcement bar scrolls away, the nav stays at the top (its height is measured, as it can wrap). */
(function () {
	var bar = document.querySelector('.kit-announce');
	function measure() { document.documentElement.style.setProperty('--kit-announce-h', (bar ? bar.offsetHeight : 0) + 'px'); }
	measure();
	window.addEventListener('resize', measure, { passive: true });
})();

/* Mobile menu: the page behind does not move (position: fixed, scroll restored — iOS ignores overflow: hidden), and the
   close button sits exactly where the open button was. */
(function () {
	var html = document.documentElement, y = 0;
	var openBtn = document.querySelector('.kit-site-header .wp-block-navigation__responsive-container-open');
	new MutationObserver(function () {
		var open = html.classList.contains('has-modal-open');
		if (open && !html.classList.contains('kit-scroll-locked')) {
			y = window.scrollY;
			if (openBtn) {
				var r = openBtn.getBoundingClientRect();
				html.style.setProperty('--kit-toggle-top', r.top + 'px');
				html.style.setProperty('--kit-toggle-left', r.left + 'px');
			}
			document.body.style.top = -y + 'px';
			html.classList.add('kit-scroll-locked');
		} else if (!open && html.classList.contains('kit-scroll-locked')) {
			html.classList.remove('kit-scroll-locked');
			document.body.style.top = '';
			window.scrollTo(0, y);
		}
	}).observe(html, { attributes: true, attributeFilter: ['class'] });
})();

/* Page scroll flow: main lists its sections in order; each top-level section gets data-scroll-index 1…n. */
(function () {
	var main = document.getElementById('main');
	if (!main) return;
	var root = main.querySelector(':scope > .wp-block-post-content') || main;
	var ids = [];
	Array.prototype.forEach.call(root.querySelectorAll(':scope > section, :scope > .alignfull, :scope > header, :scope > .wp-block-group'), function (s, i) {
		s.setAttribute('data-scroll-index', i + 1);
		if (s.id) ids.push(s.id);
	});
	main.setAttribute('data-scroll-flow', ids.join(' '));
})();

/* Filters (kits_filter_bar): below 768px the groups fold into a "Filters" sheet (focus kept inside, Esc closes);
   groups with many options show the first eight until "Show more". */
(function () {
	var phone = window.matchMedia('(max-width: 767px)');
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

/* External links: every link to another site that has words gets an arrow (CSS) and a screen-reader note; icon-only links,
   buttons (they carry their own icon) and links that already show an arrow are left as they are. */
(function () {
	function mark(root) {
		root.querySelectorAll('a[href^="http"]:not(.kit-link-ext)').forEach(function (a) {
			if (a.host === location.host || !a.textContent.trim() || a.querySelector('.kit-ico--arrow-up-right') || a.closest('.wp-block-navigation, .kit-ad, .kit-share, .kit-socials') || a.matches('.kit-btn, .wp-block-button__link')) {
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

/* Folded sections (data-kit-fold): open on wide screens, folded at or below the width given (default 1199px), unless
   data-kit-keep-open (e.g. filters in use); open without JavaScript. */
(function () {
	document.querySelectorAll('details[data-kit-fold]').forEach(function (d) {
		var mq = window.matchMedia('(max-width: ' + (parseInt(d.getAttribute('data-kit-fold'), 10) || 1199) + 'px)');
		function apply() { d.open = !mq.matches || d.hasAttribute('data-kit-keep-open'); }
		apply();
		if (mq.addEventListener) mq.addEventListener('change', apply);
	});
})();

/* Filter bar: ticking a filter applies it at once, except in the phone sheet, where "Apply" sends them together.
   Several values of one filter go as category=a,b. (Opening and closing the dropdowns: kits/dropdown, Interactivity API.) */
(function () {
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
		form.dispatchEvent(new CustomEvent('kit:filter', { bubbles: true, detail: { params: q } }));
		window.location.href = form.getAttribute('action').split('?')[0] + (q ? '?' + q : '');
	}
	document.querySelectorAll('form[data-kit-filterbar]').forEach(function (form) {
		form.addEventListener('submit', function (e) { e.preventDefault(); go(form); });
		form.addEventListener('change', function (e) {
			if (e.target.classList.contains('kit-dd-check') && !e.target.closest('.is-sheet, .kit-dd--menu')) go(form); // the Filters menu waits for Apply
		});
	});
})();

/* Motion: scroll-in (transform and opacity only; nothing under prefers-reduced-motion).
   The first two groups (page header or hero, first content section) are shown at once, never faded in: they hold the
   page's largest text or picture, and hiding them until this script ran delayed LCP by 1-2 s on phones (owner, 20 Oct
   2026). Later sections rise in as they scroll into view. Elements can opt in with data-reveal. */
(function () {
	if (!window.gsap || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	var main = document.getElementById('main');
	if (!main || main.classList.contains('kit-static')) return; // a template can opt out: its sections are always visible
	var root = main.querySelector(':scope > .wp-block-post-content') || main;
	var sections = Array.prototype.slice.call(root.querySelectorAll(':scope > section, :scope > header, :scope > .alignfull, [data-reveal]'));
	var ease = 'back.out(1.2)'; // a light spring
	sections.forEach(function (s, i) {
		if (i < 2) return; // above the fold: shown as rendered
		if (!('IntersectionObserver' in window)) return;
		s.style.opacity = '0';
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) {
					io.disconnect();
					window.gsap.fromTo(s, { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: 0.6, ease: ease, clearProps: 'transform,opacity' });
				} else if (e.boundingClientRect.bottom < 0) { // jumped past (anchor link, fast scroll): show it, no animation
					io.disconnect();
					s.style.opacity = '';
				}
			});
		}, { rootMargin: '0px' });
		io.observe(s);
	});
	// Print shows every section, whether or not it has scrolled into view.
	window.addEventListener('beforeprint', function () {
		sections.forEach(function (s) { window.gsap.killTweensOf(s); s.style.opacity = ''; s.style.transform = ''; });
	});
})();


/* Header hides while you scroll down and comes back as soon as you scroll up (owner, 9 Oct 2026). It stays while the
   menu or the search is open, near the top of the page, or when focus is inside it. */
(function () {
	var bar = document.querySelector('header.wp-block-template-part');
	if (!bar || !bar.querySelector('.kit-site-header')) return;
	var last = window.scrollY, ticking = false;
	function busy() {
		return document.documentElement.classList.contains('has-modal-open') || bar.querySelector('.is-searching, .is-menu-open') || bar.contains(document.activeElement) && document.activeElement.matches(':focus-visible'); // keyboard users keep it
	}
	function update() {
		var y = window.scrollY, h = bar.offsetHeight;
		if (y <= h || busy()) bar.classList.remove('kit-header-hidden');
		else if (y > last + 4) bar.classList.add('kit-header-hidden');
		else if (y < last - 4) bar.classList.remove('kit-header-hidden');
		last = y; ticking = false;
	}
	window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
	bar.addEventListener('focusin', function (e) { if (e.target.matches(':focus-visible')) bar.classList.remove('kit-header-hidden'); });
	// Back/forward cache: a page restored after a menu tap keeps focus on the menu button and can show its focus ring
	// with no keyboard use; drop that stale focus when the page is shown again.
	window.addEventListener('pageshow', function (e) { var a = document.activeElement; if (e.persisted && a && bar.contains(a)) a.blur(); });
})();

/* Analytics events (owner, 9 Oct 2026). Also button_click on every button. Every event goes to the dataLayer (GTM). While GA4 is still placed directly by
   Site Kit (window.kitsPage.gtag), the same event also goes to gtag, so GA4 receives it once either way.
   Events: form_submit_success, form_error, search, pin_search, filter_apply, sort_change, outbound_click, call_click,
   whatsapp_click, email_click, pdf_download, share_click, claim_step, card_click, 404_view, scroll_depth.
   17 Oct 2026: share intent links count as share_click (never whatsapp_click); whatsapp_click only for a chat with a
   number; call/whatsapp/email clicks on a page about one listing carry listing_id and listing_name; card_click counts
   positions among cards only and carries card_id, card_name. Ad slots: the module after this one. */
(function () {
	var page = window.kitsPage || {};
	// params.ecommerce (GA4 items events: view_item, view_item_list, select_item): the previous one is cleared first, as GA4
	// in GTM reads the latest ecommerce object; gtag gets it flattened.
	function track(name, params) {
		params = params || {};
		params.page_type = page.page_type || '';
		var dl = window.dataLayer = window.dataLayer || [];
		if (params.ecommerce) dl.push({ ecommerce: null });
		dl.push(Object.assign({ event: name }, params));
		if (page.gtag && typeof window.gtag === 'function') {
			var g = Object.assign({}, params);
			if (g.ecommerce) { Object.assign(g, g.ecommerce); delete g.ecommerce; }
			window.gtag('event', name, g);
		}
	}
	window.kitsTrack = track;
	function formName(form) {
		form = form && form.closest && form.closest('form') || form;
		var named = form && form.closest && form.closest('[data-kit-form]'); // a form from a form plugin, named by its wrapper
		if (named) return named.dataset.kitForm;
		var a = form && form.querySelector('input[name=action]');
		var v = a ? a.value.replace(/^aw_/, '') : (form && form.id || '');
		return { newsletter: 'newsletter', volunteer: 'volunteer', claim: 'claim', report: 'report', contact: 'contact', remove_data: 'remove_data' }[v] || v;
	}
	document.addEventListener('kit:form-done', function (e) { track('form_submit_success', { form: formName(e.target) }); });
	document.addEventListener('kit:form-error', function (e) { track('form_error', { form: formName(e.target), where: e.detail.where, fields: e.detail.fields }); });
	// Fluent Forms (AJAX): its own success and failure events, on document, with the form in detail.form
	document.addEventListener('fluentform_submission_success', function (e) {
		var res = e.detail && e.detail.response && e.detail.response.data && e.detail.response.data.result;
		if (res && res.redirectUrl && res.redirectUrl.indexOf('/thank-you/') > -1) { try { sessionStorage.setItem('kit-sent', '1'); return; } catch (x) {} } // the thank-you page counts it, as for the site's own forms
		track('form_submit_success', { form: formName(e.detail && e.detail.form) });
	});
	document.addEventListener('fluentform_submission_failed', function (e) { track('form_error', { form: formName(e.detail && e.detail.form), where: 'server', fields: '' }); });
	// Fluent Forms' own check in the browser (a jQuery event): the fields it stopped on
	if (window.jQuery) jQuery(document).on('fluentform_validation_failed', function (e, d) { var f = d && d.form && d.form[0]; track('form_error', { form: formName(f), where: 'browser', fields: d && d.response ? Object.keys(d.response).length : '' }); });
	document.addEventListener('kit:step', function (e) { if (e.target.closest('.aw-claim-form, #claim')) track('claim_step', { step: e.detail.step }); });
	// filter_apply: one event per filter in use (filter, value), plus the whole query (filters) and the hub
	function filters(params, hub) {
		var all = params.toString().slice(0, 200), n = 0;
		params.forEach(function (v, k) {
			if (!v || /^(aw_filter|sort|paged|action|_wpnonce)$/.test(k)) return;
			n++;
			track('filter_apply', { filter: k === 'q' ? 'search' : k, value: v.slice(0, 100), filters: all, hub: hub });
		});
		if (!n) track('filter_apply', { filter: '(none)', value: '', filters: all, hub: hub });
	}
	document.addEventListener('kit:filter', function (e) { filters(new URLSearchParams(e.detail.params), page.section || ''); });

	// The thank-you page ([data-kit-thanks="<form>"]): every form sent from this site that goes there counts here
	// (once per visit to that URL; kit:form-done above counts only forms that show their message in place).
	var qs = new URLSearchParams(location.search);
	var thanks = document.querySelector('[data-kit-thanks]');
	if (thanks && thanks.dataset.kitThanks) {
		var seen = true; // a thank-you URL opened without sending a form here (bookmark, shared link, crawler) is not a send
		try {
			seen = sessionStorage.getItem('kit-sent') !== '1' || sessionStorage.getItem('kit-thanks:' + location.search) === '1';
			sessionStorage.removeItem('kit-sent');
			sessionStorage.setItem('kit-thanks:' + location.search, '1');
			Object.keys(sessionStorage).forEach(function (k) { if (k.indexOf('kit-form:') === 0) sessionStorage.removeItem(k); }); // sent: forget what was typed
		} catch (x) {}
		if (!seen) track('form_submit_success', { form: thanks.dataset.kitThanks });
	}

	// Search results and the PIN code search
	if (page.page_type === 'search' && qs.get('q')) {
		var c = document.querySelector('.aw-search-count');
		track('search', { term: qs.get('q').slice(0, 100), results: c ? parseInt(c.textContent.replace(/[^\d]/g, ''), 10) || 0 : 0 });
	}
	document.addEventListener('submit', function (e) {
		var q = e.target.querySelector && e.target.querySelector('input[name=q]');
		if (q && /^\s*[1-9]\d{5}\s*$/.test(q.value)) track('pin_search', { pin: q.value.trim() });
		if (e.target.matches && e.target.matches('.aw-dir-search, .aw-ed-search')) filters(new URLSearchParams(new FormData(e.target)), 'directory'); // the Directory search, also the homepage's
	}, true);

	// Every button (and link styled as a button): button_click with its label (GTM, owner 9 Oct 2026)
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('button, [role=button], .kit-btn, .wp-block-button__link, .wp-element-button, summary.kit-btn, input[type=submit]');
		if (!b || b.closest('.wp-block-post-template li a:not(.kit-btn)')) return;
		var label = (b.getAttribute('aria-label') || b.textContent || b.value || '').replace(/\s+/g, ' ').trim().slice(0, 80);
		track('button_click', { label: label, href: (b.getAttribute('href') || '').slice(0, 200), area: b.closest('header') ? 'header' : b.closest('footer') ? 'footer' : b.closest('form') ? 'form' : 'page' });
	}, true);

	// The network of a share intent URL (WhatsApp/Telegram text share, X, Facebook, LinkedIn, Pinterest, Reddit), or ''.
	function shareIntent(href) {
		if (/(^|\/\/|\.)wa\.me\/\?text=/i.test(href) || /whatsapp\.com\/send\?(?!(.*&)?phone=\d)/i.test(href) || /^whatsapp:\/\/send/i.test(href)) return 'whatsapp';
		if (/(twitter|x)\.com\/intent\//i.test(href)) return 'x';
		if (/facebook\.com\/sharer/i.test(href)) return 'facebook';
		if (/linkedin\.com\/(sharing|shareArticle)/i.test(href)) return 'linkedin';
		if (/t\.me\/share/i.test(href)) return 'telegram';
		if (/pinterest\.[a-z.]+\/pin\/create/i.test(href)) return 'pinterest';
		if (/reddit\.com\/submit/i.test(href)) return 'reddit';
		return '';
	}

	// Clicks
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a) return;
		var href = a.getAttribute('href') || '';
		if (a.closest('.kit-dd--sort, .kit-segment') || a.classList.contains('kit-dd-opt') && a.closest('[data-kit-sort], .kit-sort')) { track('sort_change', { value: a.textContent.trim().slice(0, 60) }); return; }
		// share buttons first: their WhatsApp and email buttons are shares, not contact clicks
		var share = a.closest('.scriptlesssocialsharing__buttons, [data-kit-share]'); // a link marked data-kit-share="network" is a share too
		if (share) { track('share_click', { network: a.dataset.kitShare || (a.className.match(/\b(whatsapp|facebook|twitter|linkedin|email|pinterest|reddit|telegram)\b/) || [, 'other'])[1] }); return; }
		// a share link outside a share row (a "share on WhatsApp/X/Facebook/LinkedIn" intent URL) is a share too, not a contact
		var intent = shareIntent(href);
		if (intent) { track('share_click', { network: intent }); return; }
		// contact clicks on an item page about one organisation (a Directory profile) carry it: listing_id, listing_name
		var who = page.listing_id && a.closest('main') && !a.closest('.wp-block-post-template') ? { listing_id: page.listing_id, listing_name: page.listing_name || '' } : {};
		if (/^tel:/i.test(href)) { track('call_click', Object.assign({ number: href.slice(4) }, who)); return; }
		// WhatsApp contact = a chat with a number (wa.me/<number>, api.whatsapp.com/send?phone=); a group invite
		// (chat.whatsapp.com) or a channel is not a contact and counts as an outbound click below.
		if (/(^|\/\/|\.)wa\.me\/\+?\d/i.test(href) || /whatsapp\.com\/send\?(.*&)?phone=\d/i.test(href)) { track('whatsapp_click', who); return; }
		if (/^mailto:/i.test(href)) { track('email_click', who); return; }
		if (/\.pdf(\?|#|$)/i.test(href) || a.hasAttribute('download')) { track('pdf_download', { item: document.title.split(' – ')[0].slice(0, 100), issuer: page.issuer || '' }); }
		var li = a.closest('.wp-block-post-template > li');
		if (li && !li.classList.contains('kit-ad-item')) {
			// position among the cards only (ad cards in the list are not counted); the card's post ID and title
			var cards = Array.prototype.filter.call(li.parentNode.children, function (c) { return !c.classList.contains('kit-ad-item'); });
			var title = li.querySelector('.wp-block-post-title, h2, h3');
			track('card_click', { list: page.section || page.page_type || '', position: cards.indexOf(li) + 1, card_id: ((li.className.match(/\bpost-(\d+)\b/) || [])[1] || ''), card_name: title ? title.textContent.replace(/\s+/g, ' ').trim().slice(0, 100) : '' });
		}
		try {
			var u = new URL(href, location.href);
			if (/^https?:$/.test(u.protocol) && u.host !== location.host) track('outbound_click', { domain: u.host.replace(/^www\./, '') });
		} catch (x) {}
	}, true);

	// 404
	if (document.body.classList.contains('error404')) track('404_view', { path: location.pathname.slice(0, 200) });

	// Scroll depth: 25, 50, 75, 90 % (once each)
	var marks = [25, 50, 75, 90], hit = {}, tick = false;
	window.addEventListener('scroll', function () {
		if (tick) return; tick = true;
		requestAnimationFrame(function () {
			tick = false;
			var h = document.documentElement.scrollHeight - window.innerHeight;
			if (h < 200) return;
			var pct = window.scrollY / h * 100;
			marks.forEach(function (m) { if (pct >= m && !hit[m]) { hit[m] = 1; track('scroll_depth', { percent: m }); } });
		});
	}, { passive: true });
})();

/* Ad slots (.kit-ad, kits-core ads.php), owner's analytics audit 17 Oct 2026. Per slot with code, once per page view:
   ad_slot_status (ad_slot, ad_status, position): filled / unfilled as AdSense reports it on its <ins data-ad-status>;
   not_loaded when the AdSense script never ran (blocked) and no_response when it ran but gave no answer within 15 s;
   code for another network's code (fill unknown). ad_slot_view: a filled slot at least half on screen for 1 second.
   Clicks on an ad cannot be counted: the ad is a frame from Google's domain. Empty placeholders are skipped. */
(function () {
	var slots = document.querySelectorAll('aside.kit-ad:not(.kit-ad--empty)');
	if (!slots.length) return;
	function track(name, params) { if (typeof window.kitsTrack === 'function') window.kitsTrack(name, params); }
	Array.prototype.forEach.call(slots, function (el, i) {
		var name = (el.className.match(/\baw-ad-([a-z0-9-]+)/) || [])[1] || 'unknown';
		var ins = el.querySelector('ins.adsbygoogle');
		var status = '', seen = false, viewed = false;
		function view() {
			if (viewed || !seen || (status !== 'filled' && status !== 'code')) return;
			viewed = true;
			track('ad_slot_view', { ad_slot: name, ad_status: status, position: i + 1 });
		}
		function report(s) {
			if (status) return;
			status = s;
			track('ad_slot_status', { ad_slot: name, ad_status: s, position: i + 1 });
			view();
		}
		if (!ins) {
			report('code');
		} else {
			var check = function () { var s = ins.getAttribute('data-ad-status'); if (s) report(s); };
			if (window.MutationObserver) new MutationObserver(check).observe(ins, { attributes: true, attributeFilter: ['data-ad-status'] });
			check();
			var wait = function () { setTimeout(function () { if (!status) report(window.adsbygoogle && window.adsbygoogle.loaded ? 'no_response' : 'not_loaded'); }, 15000); };
			var lz = el.getAttribute('data-kit-lazy');
			if (lz === 'fold') {
				status = 'fold'; /* never requested on a phone: nothing to report */
			} else if (lz === 'wait' && window.MutationObserver) {
				/* lazy slot (kits-core): the 15 s clock starts when its request goes out, not at page load */
				var lmo = new MutationObserver(function () {
					var v = el.getAttribute('data-kit-lazy');
					if (v === 'fold') { status = 'fold'; lmo.disconnect(); }
					else if (v !== 'wait') { lmo.disconnect(); wait(); }
				});
				lmo.observe(el, { attributes: true, attributeFilter: ['data-kit-lazy'] });
			} else if (document.readyState === 'complete') wait(); else window.addEventListener('load', wait, { once: true });
		}
		if ('IntersectionObserver' in window) {
			var timer;
			var io = new IntersectionObserver(function (en) {
				if (en[0].intersectionRatio >= 0.5) {
					if (!timer) timer = setTimeout(function () { seen = true; io.disconnect(); view(); }, 1000);
				} else { clearTimeout(timer); timer = null; }
			}, { threshold: [0, 0.5] });
			io.observe(el);
		}
	});
})();

/* Item page sidebar: sticky under the header at lg only when it is shorter than the screen (a taller one scrolls
   with the page, so nothing in it is out of reach). */
(function () {
	var side = document.querySelector('.kit-with-sidebar > .kit-sidebar');
	if (!side) return;
	function check() {
		var top = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--kit-header-height')) || 75;
		side.classList.toggle('is-sticky', side.offsetHeight > 0 && side.offsetHeight + top + 48 < window.innerHeight);
	}
	check();
	window.addEventListener('resize', check, { passive: true });
	if (window.ResizeObserver) new ResizeObserver(check).observe(side);
})();

/* Rank Math FAQ: each question opens and closes its answer, like the Details FAQ. The question stays a heading (the
   button sits inside it), the FAQ schema is untouched, and without JavaScript every answer is shown. */
(function () {
	document.querySelectorAll('.rank-math-list-item').forEach(function (item, i) {
		var q = item.querySelector('.rank-math-question'), a = item.querySelector('.rank-math-answer');
		if (!q || !a || q.querySelector('button')) return;
		a.id = a.id || 'kit-faq-a-' + i;
		var b = document.createElement('button');
		b.type = 'button';
		b.setAttribute('aria-expanded', 'false');
		b.setAttribute('aria-controls', a.id);
		while (q.firstChild) b.appendChild(q.firstChild);
		q.appendChild(b);
		a.hidden = true;
		b.addEventListener('click', function () {
			var open = b.getAttribute('aria-expanded') !== 'true';
			b.setAttribute('aria-expanded', open ? 'true' : 'false');
			a.hidden = !open;
		});
	});
})();
