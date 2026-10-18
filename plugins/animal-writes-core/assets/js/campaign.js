/* Campaign tools (campaign-tools.php): rule chips, letter tool, phone bar, home band. Events go through kitsTrack (kit.js).
   Standard campaign events (owner, 17 Oct 2026): every action here is also campaign_action (action = the older event's
   name, with campaign_slug and campaign_title), through awCampaignTrack (aw-analytics.js). The older events keep firing
   (objection_sent and objection_confirmed are key events). campaign_view: aw-analytics.js, on every campaign page. */
(function () {
	var page = window.kitsPage || {};
	function track(name, params) {
		if (typeof window.kitsTrack === 'function') window.kitsTrack(name, params || {});
		else (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: name }, params || {}));
		// the same action as campaign_action, on a campaign page (the home band: aw-analytics.js promo_click)
		if (page.campaign_slug && name !== 'home_campaign_click' && typeof window.awCampaignTrack === 'function') {
			var p = Object.assign({}, params || {});
			if ('on' in p) p.on = String(p.on);
			if ('ground' in p) p.ground = String(p.ground);
			window.awCampaignTrack(name === 'share_click' ? 'share' : name, p);
		}
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

	/* Share row: the copy-link button (links marked data-kit-share are counted by kit.js); newsletter sign-ups made on a
	   campaign page, once the form plugin reports success. Source: the share row's data-source (e.g. campaign2). */
	var shareRow = document.querySelector('.aw-camp-share');
	var source = shareRow && shareRow.dataset.source || 'campaign';
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('button[data-kit-share]');
		if (b) track('share_click', { network: b.dataset.kitShare });
	});
	document.addEventListener('fluentform_submission_success', function (e) {
		var f = e.detail && e.detail.form;
		f = f && f.jquery ? f[0] : f;
		if (page.campaign_slug && f && f.querySelector && f.querySelector('input[type=email]')) track('newsletter_optin', { source: source }); // campaign pages only (not the home band's page)
	});
	document.addEventListener('kit:form-done', function (e) {
		if (page.campaign_slug && e.target && e.target.querySelector && e.target.querySelector('input[type=email]') && !e.target.closest('.aw-letter')) track('newsletter_optin', { source: source });
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
	var pickedEl = form.querySelector('.aw-letter-picked');
	function picked() {
		if (!pickedEl) return;
		var n = grounds.filter(function (g) { return g.box.checked; }).length;
		pickedEl.textContent = n + ' of ' + grounds.length + ' chosen';
	}
	picked();
	grounds.forEach(function (g) { g.box.addEventListener('change', picked); });

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

	/* Opt-in at send (campaign-letters.php): ticked, the email is asked for and required. */
	var optin = form.elements.optin;
	var emailRow = form.querySelector('.aw-letter-email');
	if (optin && emailRow) {
		emailRow.hidden = !optin.checked; // ticked by default (owner, 18 Oct 2026): the email field shows from the start
		optin.addEventListener('change', function () { emailRow.hidden = !optin.checked; if (optin.checked) form.elements.email.focus(); });
	}
	function wantsUpdates() { return !!(optin && optin.checked); }
	function emailOk() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email')); }

	function ready() {
		var named = val('name') && val('city');
		var ok = named && (!wantsUpdates() || emailOk());
		errorEl.textContent = named ? 'Add your email to get updates, or untick the box.' : 'Add your name and city first.';
		errorEl.hidden = !!ok;
		if (!ok) (!val('name') ? form.elements.name : !val('city') ? form.elements.city : form.elements.email).focus();
		return ok;
	}

	function post(url, data) {
		if (!url || !window.fetch) return Promise.reject();
		return fetch(url, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
			.then(function (r) { if (!r.ok) throw r; return r; });
	}
	function chosen() { return grounds.filter(function (g) { return g.box.checked; }).map(function (g) { return g.box.value; }).join(','); }
	function device() { return window.matchMedia('(max-width: 767px)').matches ? 'phone' : (window.matchMedia('(max-width: 1199px)').matches ? 'tablet' : 'desktop'); }
	var counted = false, joined = false;
	/* One anonymous row per letter (city, grounds, route, device, date; no name, email or IP), and the sign-up if ticked. */
	function recordSend(route) {
		if (!counted) {
			counted = true;
			post(cfg.letter, { city: val('city'), grounds: chosen(), route: route, device: device() }).catch(function () {});
		}
		if (wantsUpdates() && !joined) {
			joined = true;
			post(cfg.optin, { email: val('email'), name: val('name'), city: val('city'), grounds: chosen(), consent: '1' })
				.then(function () { track('newsletter_optin', { source: cfg.source || '' }); })
				.catch(function () { joined = false; });
		}
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
			recordSend(route);
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

/* Phone bar: shows once the hero has scrolled away; hidden while the letter tool or the footer is on screen, and for the
   rest of the session after a letter is confirmed. */
(function () {
	var bar = document.querySelector('.aw-sticky-cta');
	if (!bar) return;
	var target = document.querySelector(bar.dataset.target);
	var hero = document.querySelector('.aw-camp-hero');
	var foot = document.querySelector('.wp-site-blocks > footer, footer.wp-block-template-part');
	var sent = false, onSend = false, onHero = !!hero, onFoot = false;
	try { sent = sessionStorage.getItem('aw-letter-sent') === '1'; } catch (e) { /* private mode */ }
	function update() {
		var h = sent || onSend || onHero || onFoot;
		bar.classList.toggle('is-hidden', h);
		bar.inert = h; // off screen: not reachable by keyboard either
	}
	update();
	document.addEventListener('aw:letter-sent', function () { sent = true; update(); });
	if ('IntersectionObserver' in window) {
		if (target) new IntersectionObserver(function (en) { onSend = en[0].isIntersecting; update(); }).observe(target);
		if (hero) new IntersectionObserver(function (en) { onHero = en[0].isIntersecting; update(); }).observe(hero);
		if (foot) new IntersectionObserver(function (en) { onFoot = en[0].isIntersecting; update(); }).observe(foot);
	} else {
		onHero = false;
		update();
	}
})();
