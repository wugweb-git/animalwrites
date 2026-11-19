/* Kits Core: form behaviour (forms.php, form-kit.php: every .kit-form, ALTCHA, expandable and multi-step forms).
   Moved from the Kits theme's kit.js on 18 Nov 2026; loads before it. */
(function () {
	'use strict';

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
	// Says what is wrong with a picked file (size, type), never one message for every cause.
	function mb(n) { return (Math.round(n / 104857.6) / 10) + ' MB'; }
	function fileProblem(f, max) {
		if (/\.(heic|heif)$/i.test(f.name) || /^image\/hei[cf]/.test(f.type)) return 'This is a HEIC file. Use PNG, JPG or WebP.';
		if (!/^image\/(png|jpeg|webp)$/.test(f.type)) return 'This file type is not accepted. Use PNG, JPG or WebP.';
		if (f.size > max) return 'This file is ' + mb(f.size) + '. The limit is ' + Math.round(max / 1048576) + ' MB.';
		return '';
	}
	window.kitsFileProblem = fileProblem;
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
			var why = fileProblem(el.files[0], +(d.maxBytes || Infinity));
			if (why) el.setCustomValidity(why);
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
	document.querySelectorAll('form.kit-form').forEach(function (form) {
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
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'X-Kits-Fetch': '1' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					busy(btn, false);
					// The page's form key expired (a cached page open a long time): get a fresh one and send once more.
					if (data.code === 'expired' && !form.dataset.retried) {
						form.dataset.retried = '1';
						var act = form.querySelector('input[name=action]');
						return fetch(form.getAttribute('action').replace('admin-post.php', 'admin-ajax.php') + '?action=kits_form_nonce&form=' + encodeURIComponent(act ? act.value : ''), { credentials: 'same-origin' })
							.then(function (r) { return r.json(); })
							.then(function (k) { var n = form.querySelector('input[name=_kits_nonce]'); if (n && k.nonce) { n.value = k.nonce; form.requestSubmit(); } else status(form, data.notice); });
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

	/* Steps from a form plugin's section breaks (claim form, owner 31 Oct 2026: Fluent Forms free has no steps). A
	   .kit-step-break starts a step (its title names the step, its description is the step's intro); a .kit-fold-break
	   starts a group inside the step that folds open on tap. The fields move into the same .kit-steps markup
	   kits_form_steps() prints, so the step code below runs them; the submit button goes into the last step. Without
	   JavaScript none of this runs: the form is one long page with the section titles. */
	document.querySelectorAll('form').forEach(function (form) {
		var breaks = form.querySelectorAll('.kit-step-break');
		if (breaks.length < 2) return;
		var parent = breaks[0].parentNode, n = breaks.length, i = 0, step = null, fold = null;
		if (Array.prototype.some.call(breaks, function (b) { return b.parentNode !== parent; })) return; // fields not side by side: leave the form as it is
		var box = document.createElement('div');
		box.className = 'kit-steps'; box.setAttribute('data-steps', String(n));
		parent.insertBefore(box, breaks[0]);
		var text = function (el, sel) { var t = el.querySelector(sel); return t ? t.textContent.trim() : ''; };
		var node = box.nextSibling, submit = null;
		while (node) {
			var next = node.nextSibling;
			if (node.nodeType === 1 && node.classList.contains('kit-step-break')) {
				i++;
				step = document.createElement('fieldset');
				step.className = 'kit-step'; step.setAttribute('data-step', String(i));
				var lg = document.createElement('legend');
				lg.innerHTML = '<span class="kit-step-count"></span> <span class="kit-step-title"></span>';
				lg.firstChild.textContent = 'Step ' + i + ' of ' + n;
				lg.lastChild.textContent = text(node, '.ff-el-section-title');
				step.appendChild(lg);
				var intro = text(node, '.ff-section_break_desk');
				if (intro) { var p = document.createElement('p'); p.className = 'kit-step-intro'; p.textContent = intro; step.appendChild(p); }
				box.appendChild(step); fold = null; parent.removeChild(node);
			} else if (node.nodeType === 1 && node.classList.contains('kit-fold-break') && step) {
				fold = document.createElement('details');
				fold.className = 'wp-block-details kit-fold-group';
				fold.open = true; // no folds (owner, 13 Nov 2026): the group shows open
				var sm = document.createElement('summary'); sm.textContent = text(node, '.ff-el-section-title'); fold.appendChild(sm);
				var fd = text(node, '.ff-section_break_desk');
				if (fd) { var fp = document.createElement('p'); fp.className = 'kit-field-hint'; fp.textContent = fd; fold.appendChild(fp); }
				step.appendChild(fold); parent.removeChild(node);
			} else if (node.nodeType === 1 && node.classList.contains('ff_submit_btn_wrapper')) {
				submit = node; break;
			} else if (step) {
				(fold || step).appendChild(node);
			}
			node = next;
		}
		var all = box.querySelectorAll('.kit-step');
		Array.prototype.forEach.call(all, function (s, k) {
			var nav = document.createElement('div'); nav.className = 'kit-step-nav';
			if (k < all.length - 1) nav.innerHTML = '<button type="button" class="kit-btn" data-step-next>Next</button>';
			else if (submit) nav.appendChild(submit);
			if (k > 0) { var b = document.createElement('button'); b.type = 'button'; b.className = 'kit-btn kit-btn--secondary'; b.setAttribute('data-step-back', ''); b.textContent = 'Back'; nav.appendChild(b); }
			s.appendChild(nav);
		});
	});

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
				wrong.forEach(function (el) { var d = el.closest('details'); if (d) d.open = true; }); // a folded group with a wrong field opens
				wrong[0].focus();
				box.dispatchEvent(new CustomEvent('kit:form-error', { bubbles: true, detail: { where: 'step ' + (cur + 1), fields: wrong.length } })); // analytics
				return;
			}
			go(cur + 1);
		});
		// A form plugin (Fluent Forms) that finds errors on send: say what is wrong (a list on the last step and on the step
		// shown), then show the first step that has an error. Each item opens its step and focuses the field.
		function problems() {
			var out = [];
			steps.forEach(function (st, n) {
				var groups = [];
				Array.prototype.forEach.call(st.querySelectorAll('.ff-el-group.ff-el-is-error, [aria-invalid=true]'), function (e) { var g = e.classList.contains('ff-el-group') ? e : e.closest('.ff-el-group'); if (g && groups.indexOf(g) < 0) groups.push(g); });
				groups.forEach(function (g) {
					var l = g.querySelector('.ff-el-input--label label, label'), m = g.querySelector('.error, .kit-field-error');
					var f = g.querySelector('input:not([type=hidden]), select, textarea') || g.querySelector('input');
					var label = l ? l.textContent.replace(/\(optional\)/, '').replace(/\*/g, '').trim() : 'A field';
					out.push({ step: n, label: label, text: m ? m.textContent.trim() : '', field: f });
				});
			});
			return out;
		}
		function summary(list) {
			steps.forEach(function (st) { var old = st.querySelector('.kit-error-summary'); if (old) old.remove(); });
			if (!list.length) return;
			var first = list[0].step, last = steps.length - 1, where = {};
			list.forEach(function (it) { where[it.step] = 1; });
			var many = Object.keys(where).length > 1;
			[last, first].filter(function (v, i, a) { return a.indexOf(v) === i; }).forEach(function (n) {
				var wrap = document.createElement('div'), ul = document.createElement('ul'), head = document.createElement('p');
				wrap.className = 'kit-error-summary'; wrap.setAttribute('role', 'alert'); wrap.tabIndex = -1;
				head.className = 'kit-error-summary-title';
				head.textContent = list.length + (list.length === 1 ? ' thing' : ' things') + ' to fix' + (many ? ':' : ' in step ' + (first + 1) + ':');
				list.forEach(function (it) {
					var li = document.createElement('li'), a = document.createElement('a');
					a.href = '#'; a.textContent = (many ? 'Step ' + (it.step + 1) + ': ' : '') + it.label + (it.text ? ' (' + it.text.replace(/\.$/, '') + ')' : '');
					a.addEventListener('click', function (e) { e.preventDefault(); if (it.step !== cur) go(it.step); if (it.field) { var d = it.field.closest('details'); if (d) d.open = true; it.field.focus(); } });
					li.appendChild(a); ul.appendChild(li);
				});
				wrap.appendChild(head); wrap.appendChild(ul);
				var lg = steps[n].querySelector('legend');
				steps[n].insertBefore(wrap, lg ? lg.nextSibling : steps[n].firstChild);
			});
		}
		// A fixed field no longer needs the list: it goes as soon as anything in the form changes.
		['input', 'change'].forEach(function (ev) { box.closest('form').addEventListener(ev, function () { summary([]); }); });
		if (window.jQuery) jQuery(box.closest('form')).on('fluentform_validation_failed fluentform_submission_failed', function () {
			setTimeout(function () {
				var list = problems(), i = steps.findIndex(function (s) { return s.querySelector('.ff-el-is-error, [aria-invalid=true]'); });
				box.querySelectorAll('details').forEach(function (d) { if (d.querySelector('.ff-el-is-error, [aria-invalid=true]')) d.open = true; });
				summary(list);
				if (i > -1 && i !== cur) go(i);
				var sm = steps[i > -1 ? i : cur].querySelector('.kit-error-summary');
				if (sm) sm.focus();
			}, 0);
		});
	});
})();
