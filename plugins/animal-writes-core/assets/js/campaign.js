/* Campaign tools (campaign-tools.php): rule chips, letter tool, phone bar, home band. Events go through kitsTrack (kit.js). */
(function () {
	function track(name, params) {
		if (typeof window.kitsTrack === 'function') window.kitsTrack(name, params || {});
		else (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: name }, params || {}));
	}

	/* Rule chips: the clause in a dialog. */
	document.querySelectorAll('.aw-rule-chip[aria-controls]').forEach(function (btn) {
		var dlg = document.getElementById(btn.getAttribute('aria-controls'));
		if (!dlg || typeof dlg.showModal !== 'function') return;
		btn.addEventListener('click', function () {
			dlg.showModal();
			track('rule_card_open', { rule: btn.dataset.rule || '' });
		});
		dlg.addEventListener('click', function (e) {
			if (e.target === dlg || e.target.hasAttribute('data-close')) dlg.close();
		});
	});

	/* Home band. */
	document.querySelectorAll('[data-track]').forEach(function (a) {
		a.addEventListener('click', function () { track(a.dataset.track, { link_text: a.textContent.trim() }); });
	});

	/* Letter tool. */
	var form = document.querySelector('.aw-letter');
	if (!form) return;
	var cfg = {};
	try { cfg = JSON.parse(form.dataset.letter || '{}'); } catch (e) { return; }
	var source = document.querySelector('.aw-letter-grounds');
	var holder = form.querySelector('.aw-letter-ground-list');
	var preview = form.querySelector('.aw-letter-preview');
	var errorEl = form.querySelector('.aw-letter-error');
	var copied = form.querySelector('.aw-letter-copied');
	var confirmBox = form.querySelector('.aw-letter-confirm');
	var thanks = form.querySelector('.aw-letter-thanks');
	var grounds = [];

	if (source) {
		source.querySelectorAll('li').forEach(function (li, i) {
			var n = i + 1;
			var id = 'aw-ground-' + n;
			var row = document.createElement('p');
			row.className = 'aw-letter-ground';
			var box = document.createElement('input');
			box.type = 'checkbox';
			box.id = id;
			box.value = String(n);
			box.checked = (cfg.ticked || []).indexOf(n) !== -1;
			var label = document.createElement('label');
			label.htmlFor = id;
			label.innerHTML = li.innerHTML;
			row.appendChild(box);
			row.appendChild(label);
			holder.appendChild(row);
			grounds.push({ box: box, text: li.textContent.replace(/\s+/g, ' ').trim() });
			box.addEventListener('change', function () { track('ground_toggle', { ground: n, on: box.checked }); render(); });
		});
		source.hidden = true; // the list stays in the page for readers without JavaScript
	}

	function val(name) { return (form.elements[name].value || '').replace(/\s+/g, ' ').trim(); }

	function letter() {
		var picked = grounds.filter(function (g) { return g.box.checked; }).map(function (g, i) { return (i + 1) + '. ' + g.text; });
		var parts = [];
		if (cfg.address) parts.push('To: ' + cfg.address);
		parts.push('Subject: ' + cfg.subject, '', cfg.salute);
		if (cfg.intro) parts.push('', cfg.intro);
		if (picked.length) parts.push('', picked.join('\n\n'));
		if (val('own')) parts.push('', val('own'));
		if (cfg.closing) parts.push('', cfg.closing);
		parts.push('', cfg.sign, (val('name') || '[Your name]') + ', ' + (val('city') || '[Your city]'));
		return parts.join('\n');
	}

	function render() { preview.textContent = letter(); }

	var opened = false;
	form.addEventListener('focusin', function () { if (!opened) { opened = true; track('letter_open'); } });
	form.addEventListener('input', render);
	form.addEventListener('submit', function (e) { e.preventDefault(); });
	render();
	track('campaign_view');

	function copy(text) {
		function fallback() {
			var t = document.createElement('textarea');
			t.value = text;
			t.setAttribute('readonly', '');
			t.style.position = 'absolute';
			t.style.left = '-9999px';
			document.body.appendChild(t);
			t.select();
			try { document.execCommand('copy'); } catch (e) { /* the preview stays selectable */ }
			t.remove();
		}
		if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).catch(fallback);
		else fallback();
	}

	function ready() {
		var ok = val('name') && val('city');
		errorEl.hidden = !!ok;
		if (!ok) (val('name') ? form.elements.city : form.elements.name).focus();
		return ok;
	}

	form.querySelectorAll('[data-route]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var route = btn.dataset.route;
			if (route === 'copy-address') {
				copy(cfg.to);
				copied.textContent = 'Address copied: ' + cfg.to;
				return;
			}
			if (!ready()) return;
			var body = letter();
			var e = encodeURIComponent;
			var url = '';
			if (route === 'gmail') url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + e(cfg.to) + '&su=' + e(cfg.subject) + '&body=' + e(body);
			if (route === 'outlook') url = 'https://outlook.office.com/mail/deeplink/compose?to=' + e(cfg.to) + '&subject=' + e(cfg.subject) + '&body=' + e(body);
			if (route === 'mailto') {
				if (body.length > 1800) { // long links fail in some mail apps: copy instead
					route = 'copy';
					copied.textContent = 'Your letter is long for an email app link, so it was copied. Paste it into a new email to ' + cfg.to + '.';
				} else {
					url = 'mailto:' + cfg.to + '?subject=' + e(cfg.subject) + '&body=' + e(body);
				}
			}
			if (route === 'copy') {
				copy(body);
				if (!copied.textContent || btn.dataset.route === 'copy') copied.textContent = 'Letter copied. Paste it into a new email to ' + cfg.to + '.';
			}
			if (url) {
				if (route === 'mailto') window.location.href = url;
				else window.open(url, '_blank', 'noopener');
			}
			track('objection_sent', { route: route });
			confirmBox.hidden = false;
		});
	});

	form.querySelectorAll('[data-confirm]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (btn.dataset.confirm !== 'yes') { confirmBox.hidden = true; return; }
			track('objection_confirmed');
			confirmBox.hidden = true;
			thanks.hidden = false;
			thanks.focus();
			try { sessionStorage.setItem('aw-letter-sent', '1'); } catch (e) { /* private mode */ }
			document.dispatchEvent(new CustomEvent('aw:letter-sent'));
			if (cfg.confirm && window.fetch) fetch(cfg.confirm, { method: 'POST', credentials: 'omit' }).catch(function () {});
		});
	});
})();

/* Phone bar: hidden while the letter tool is on screen, and after a letter is confirmed. */
(function () {
	var bar = document.querySelector('.aw-sticky-cta');
	if (!bar) return;
	var target = document.querySelector(bar.dataset.target);
	var sent = false;
	try { sent = sessionStorage.getItem('aw-letter-sent') === '1'; } catch (e) { /* private mode */ }
	function hide(h) { bar.classList.toggle('is-hidden', h); }
	hide(sent);
	document.addEventListener('aw:letter-sent', function () { sent = true; hide(true); });
	if (target && 'IntersectionObserver' in window) {
		new IntersectionObserver(function (entries) { hide(sent || entries[0].isIntersecting); }).observe(target);
	}
})();
