/* Lab rules campaign page only (lab-animals-rules-2026): campaign.js kept as it was on the morning of 2 Nov 2026 (owner); campaign-tools.php loads it in campaign.js's place. */
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

	/* The same event, sent once the tap has painted (Core Web Vitals, INP): the analytics handlers no longer sit inside the
	   tap's own frame. Only for taps that stay on the page; a send or a link keeps tracking at once, before the page leaves. */
	function trackLater(name, params) {
		var go = function () { track(name, params); };
		if (typeof requestAnimationFrame === 'function') requestAnimationFrame(function () { setTimeout(go, 0); });
		else setTimeout(go, 0);
	}

	/* Rule chips: the clause in a dialog. Each dialog waits in a <template> (campaign-tools.php) and joins the page on the
	   chip's first tap, so the page's tree stays small. */
	document.querySelectorAll('.aw-rule-chip[aria-controls]').forEach(function (btn) {
		var id = btn.getAttribute('aria-controls'), dlg = null;
		function dialog() {
			if (dlg) return dlg;
			var d = document.getElementById(id);
			if (!d) {
				var tpl = document.getElementById(id + '-tpl');
				if (!tpl || !tpl.content || !tpl.content.firstElementChild) return null;
				d = tpl.content.firstElementChild;
				document.body.appendChild(d);
				tpl.remove();
			}
			if (typeof d.showModal !== 'function') return null;
			d.addEventListener('click', function (e) {
				if (e.target === d || e.target.hasAttribute('data-close')) d.close();
			});
			dlg = d;
			return d;
		}
		btn.addEventListener('click', function () {
			var d = dialog();
			if (!d) return;
			d.showModal();
			trackLater('rule_card_open', { rule: btn.dataset.rule || '' });
		});
	});

	/* Share row: the copy-link button (links marked data-kit-share are counted by kit.js); newsletter sign-ups made on a
	   campaign page, once the form plugin reports success. Source: the share row's data-source (e.g. campaign2). */
	var shareRow = document.querySelector('.aw-camp-share');
	var source = shareRow && shareRow.dataset.source || 'campaign';
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('button[data-kit-share]');
		if (b) trackLater('share_click', { network: b.dataset.kitShare });
	});
	/* Share: the site's one share bar (kits_share_html, kit.js), as on every page (owner, 13 Nov 2026: the share card is
	   the same across the product). Phones: Share (the phone's own sheet) and Copy link; tablets: Share, then the
	   networks; computers: the networks. The page shares itself; its picture is the link preview's. */
	var sh = window.awCampShare || {};
	/* Animal Writes' own accounts, as in the site footer (parts/footer.html). */
	function followLinks() {
		return [['https://x.com/animal_writes_', 'X'], ['https://www.instagram.com/animal_writes_/', 'Instagram'], ['https://www.threads.com/@animal_writes_', 'Threads'], ['https://www.linkedin.com/company/animalwrites/', 'LinkedIn'], ['https://www.facebook.com/profile.php?id=61596537146561', 'Facebook']]
			.map(function (l) { return '<a href="' + l[0] + '" rel="noopener">' + l[1] + '</a>'; }).join(', ') + '.';
	}
	if (sh.short) sh.url = sh.short; /* the campaign's short link (/lab): what Share and Copy link hand out; the page's canonical stays the full address */

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
			var row = document.createElement('label'); // a choice card: the theme's checkbox (kit-checkbox), the whole card toggles
			row.className = 'kit-checkbox aw-letter-ground';
			row.htmlFor = id;
			var box = document.createElement('input');
			box.type = 'checkbox';
			box.id = id;
			box.value = String(n);
			box.checked = (cfg.ticked || []).indexOf(n) !== -1;
			var label = document.createElement('span');
			label.innerHTML = li.innerHTML;
			row.appendChild(box);
			row.appendChild(label);
			holder.appendChild(row);
			grounds.push({ box: box, text: li.textContent.replace(/\s+/g, ' ').trim() });
			box.addEventListener('change', function () { render(); trackLater('ground_toggle', { ground: n, on: box.checked }); });
		});
		source.hidden = true; // the list stays in the page for readers without JavaScript
		// All 14 grounds show at once (owner, 10 Nov 2026: "all 14 rules visible").
	}
	var pickedEl = form.querySelector('.aw-letter-picked');
	function picked() {
		if (!pickedEl) return;
		var n = grounds.filter(function (g) { return g.box.checked; }).length;
		pickedEl.textContent = n + ' of ' + grounds.length + ' chosen';
	}
	picked();

	/* Phones: the letter shows its opening lines with a fade, and opens in full with one button under it, in place of a
	   small box that scrolls inside the page (campaign-lab.css .aw-letter-whole). From 768 the button is hidden and the
	   box is as before. Screen readers get the whole letter either way. */
	if (preview) {
		preview.id = preview.id || 'aw-letter-preview';
		var whole = document.createElement('button');
		whole.type = 'button';
		whole.className = 'kit-btn kit-btn--secondary kit-btn--sm aw-letter-whole';
		whole.setAttribute('aria-controls', preview.id);
		whole.setAttribute('aria-expanded', 'false');
		whole.textContent = 'Show the whole letter';
		preview.classList.add('is-folded');
		preview.parentNode.insertBefore(whole, preview.nextSibling);
		whole.addEventListener('click', function () {
			var open = preview.classList.toggle('is-folded') === false;
			whole.setAttribute('aria-expanded', String(open));
			whole.textContent = open ? 'Show less' : 'Show the whole letter';
			if (!open && preview.getBoundingClientRect().top < 0) preview.scrollIntoView({ block: 'start' }); // back to the letter's top when it folds from below
		});
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

	/* The preview is rebuilt once per frame, not on every key press or tick (Interaction to Next Paint). */
	var renderQueued = false;
	function renderNow() { renderQueued = false; preview.textContent = letter(); picked(); }
	function render() {
		if (renderQueued) return;
		renderQueued = true;
		(window.requestAnimationFrame || setTimeout)(renderNow);
	}

	var opened = false, openedAt = 0;
	form.addEventListener('focusin', function () { if (!opened) { opened = true; openedAt = Date.now(); trackLater('letter_open'); } });
	form.addEventListener('input', render);
	form.addEventListener('submit', function (e) { e.preventDefault(); });
	renderNow();

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

	/* Sign-up at send (campaign-letters.php): name, city and email are required; every letter sent records the sender
	   (owner, 18 Oct 2026), and the box (ticked by default) also joins them to the newsletter and the volunteers. */
	var optin = form.elements.optin;
	function wantsUpdates() { return !!(optin && optin.checked); }
	function emailOk() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email')); }

	/* A field's state, as the theme's forms show it (kit.js): aria-invalid and the message on the field's own error line,
	   is-valid once it holds a good value. */
	function mark(el, text) {
		el.setAttribute('aria-invalid', text ? 'true' : 'false');
		el.classList.toggle('is-valid', !text && el.value.trim() !== '');
		var line = document.getElementById(el.id + '-error');
		if (line) line.textContent = text;
	}

	/* Error state: names what is missing, marks each field, and focuses the first one. */
	function ready() {
		var miss = [];
		[['name', 'your name'], ['city', 'your city'], ['email', 'a working email']].forEach(function (f) {
			var bad = f[0] === 'email' ? !emailOk() : !val(f[0]);
			var el = form.elements[f[0]];
			if (el) mark(el, bad ? 'Add ' + f[1] + ' to send your objection.' : ''); // the same words as the message by the send buttons
			if (bad) miss.push(f);
		});
		var ok = !miss.length;
		errorEl.textContent = ok ? '' : 'Add ' + miss.map(function (f) { return f[1]; }).join(miss.length > 2 ? ', ' : ' and ').replace(/, ([^,]*)$/, ' and $1') + ' to send your objection.';
		errorEl.hidden = ok;
		if (!ok) form.elements[miss[0][0]].focus();
		return ok;
	}
	['name', 'city', 'email'].forEach(function (n) { // the mark clears as soon as the field is filled
		var el = form.elements[n];
		if (!el) return;
		var good = function () { return n === 'email' ? emailOk() : !!val(n); };
		el.addEventListener('input', function () { if (el.getAttribute('aria-invalid') === 'true' && good()) mark(el, ''); });
		el.addEventListener('blur', function () { if (el.getAttribute('aria-invalid') !== 'true') el.classList.toggle('is-valid', good()); }); // success state once filled; errors only after a send
	});

	/* keepalive: the browser finishes the call after the page leaves (Gmail or the mail app opening). One retry after
	   2 s and one after 6 s when a call fails; the server keeps one row and one reference per send_id. */
	function post(url, data, tries, asForm) {
		if (!url || !window.fetch) return Promise.reject();
		tries = tries === undefined ? 2 : tries;
		return fetch(url, { method: 'POST', credentials: 'omit', keepalive: true, headers: { 'Content-Type': asForm ? 'application/x-www-form-urlencoded' : 'application/json' }, body: asForm ? data.toString() : JSON.stringify(data) })
			.then(function (r) { if (!r.ok && r.status >= 500) throw r; return r; })
			.catch(function (err) {
				if (tries <= 0) throw err;
				return new Promise(function (ok) { setTimeout(ok, tries === 2 ? 2000 : 6000); }).then(function () { return post(url, data, tries - 1, asForm); });
			})
			.then(function (r) { if (!r.ok) throw r; return r; });
	}
	function sendId() {
		var a = new Uint8Array(8);
		if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(a);
		else for (var i = 0; i < 8; i++) a[i] = Math.floor(Math.random() * 256);
		return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
	}
	function chosen() { return grounds.filter(function (g) { return g.box.checked; }).map(function (g) { return g.box.value; }).join(','); }
	function device() { return window.matchMedia('(max-width: 767px)').matches ? 'phone' : (window.matchMedia('(max-width: 1199px)').matches ? 'tablet' : 'desktop'); }
	var counted = false, joined = false, sid = '';
	/* One anonymous row per letter (city, grounds, route, device, date, the two boxes as 1/0; no name, email or IP), and
	   the sign-up (FluentCRM), whose reference (AW-L-<n>) is shown in the thank-you. The sign-up carries the row's fields
	   and the same send_id too, so the row is saved when either call arrives. Called before the page leaves. */
	function recordSend(route) {
		var join = wantsUpdates(), wa = form.elements.wa_optin, waOn = wa && wa.checked ? '1' : '0';
		var row = { city: val('city'), grounds: chosen(), route: route, device: device(), nl_optin: join ? '1' : '0', wa_optin: waOn };
		if (!sid) sid = sendId();
		row.send_id = sid;
		if (!counted) {
			counted = true;
			post(cfg.letter, row).catch(function () { counted = false; });
		}
		if (!joined) {
			joined = true;
			signUp(row, join, waOn)
				.then(function (ref) {
					var box = form.querySelector('.aw-letter-ref');
					if (box && ref) { box.querySelector('strong').textContent = ref; box.hidden = false; }
					track('newsletter_optin', { source: cfg.source || '' });
				})
				.catch(function () { joined = false; botAnswer = ''; }); // the next send tries again, with a fresh bot check
		}
	}

	/* The sign-up's bot check (audit item 4, owner 25 Oct 2026): the site's own ALTCHA widget (kits-core altcha.php),
	   placed hidden in the letter tool. It is solved while the visitor fills the form in, so the answer is ready when
	   Gmail or the mail app takes the page away. One answer covers the letter's send_id on the server. */
	var bot = form.querySelector('altcha-widget'), botAnswer = '', botWait = null;
	if (bot) bot.addEventListener('statechange', function (e) { var d = e.detail || {}; botAnswer = d.state === 'verified' && d.payload ? d.payload : ''; });
	function botCheck() {
		if (botAnswer) return Promise.resolve(botAnswer);
		if (botWait) return botWait;
		if (!bot || !window.customElements) return Promise.resolve('');
		botWait = new Promise(function (done) {
			var tries = 0, stop = setTimeout(function () { finish(''); }, 20000);
			function finish(p) { clearTimeout(stop); bot.removeEventListener('statechange', on); done(p); }
			function on(e) {
				var d = e.detail || {};
				if (d.state === 'verified' && d.payload) finish(d.payload);
				else if (d.state === 'error') finish('');
			}
			bot.addEventListener('statechange', on);
			/* The widget mounts just after its script defines it: try again shortly until its verify() is there. */
			function start() {
				if (typeof bot.verify === 'function') {
					try { if (typeof bot.reset === 'function') bot.reset(); bot.verify(); return; } catch (e) { /* not mounted yet */ }
				}
				if (++tries < 25) setTimeout(start, 200);
				else finish('');
			}
			customElements.whenDefined('altcha-widget').then(function () { setTimeout(start, 0); });
		}).then(function (p) { botWait = null; return p; });
		return botWait;
	}
	form.addEventListener('focusin', function () { botCheck(); }); // the widget's script loads on the same first touch (kits-core)

	/* The sign-up: the Fluent Forms Campaign form (campaign-form.php) when it is set up, else the older endpoint. The
	   server copies every send as it arrives, before any check (wp_aw_form_raw). Resolves with the reference. */
	function signUp(row, join, waOn) {
		return botCheck().then(function (altcha) { return signUpAs(row, join, waOn, altcha); });
	}
	function signUpAs(row, join, waOn, altcha) {
		var person = { email: val('email'), name: val('name'), city: val('city'), own: val('own'), whatsapp: val('whatsapp') };
		function older() { return post(cfg.optin, Object.assign({ consent: join ? '1' : '0', altcha: altcha }, person, row)).then(function (r) { return r.json(); }).then(function (d) { return d && d.ref; }); }
		if (!cfg.ff || !cfg.ajax) return older();
		var f = new URLSearchParams();
		f.append('altcha', altcha);
		Object.keys(person).forEach(function (k) { f.append(k, person[k]); });
		if (join) f.append('nl_optin[]', '1');
		if (waOn === '1') f.append('wa_optin[]', '1');
		['grounds', 'route', 'device', 'send_id'].forEach(function (k) { f.append(k, row[k]); });
		f.append('campaign_id', cfg.campaign_id);
		f.append('consent_version', cfg.consent || '');
		var v = typeof window.awVisitData === 'function' ? window.awVisitData(openedAt) : {};
		Object.keys(v).forEach(function (k) { f.append(k, v[k]); });
		var body = new URLSearchParams({ action: 'fluentform_submit', form_id: String(cfg.ff), data: f.toString() });
		return post(cfg.ajax, body, undefined, true).then(function (r) { return r.json(); }).then(function (d) {
			var res = d && (d.result || (d.data && d.data.result)), m = /data-aw-ref="([^"]+)"/.exec((res && res.message) || '');
			if (!m) throw new Error('no reference');
			return m[1];
		}).catch(older); // Fluent Forms refused or failed: the older sign-up (same send_id, so one row and one reference)
	}

	/* On phones, Gmail opens in the installed app; Gmail on the web is the fallback when the app is not there. */
	function gmailApp(to, subject, body, web) {
		var ua = navigator.userAgent || '', e = encodeURIComponent;
		if (/Android/i.test(ua)) {
			window.location.href = 'intent:' + to + '?subject=' + e(subject) + '&body=' + e(body) +
				'#Intent;scheme=mailto;package=com.google.android.gm;S.browser_fallback_url=' + e(web) + ';end';
			return true;
		}
		if (/iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
			var left = false, stop = function () { left = true; };
			window.addEventListener('pagehide', stop, { once: true });
			window.addEventListener('blur', stop, { once: true });
			document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); }, { once: true });
			setTimeout(function () { if (!left && !document.hidden) window.location.href = web; }, 2000);
			window.location.href = 'googlegmail://co?to=' + e(to) + '&subject=' + e(subject) + '&body=' + e(body);
			return true;
		}
		return false;
	}

	/* After a copy: the note names the address, with its own small copy button (Copy address is not a send button). */
	function copiedNote(msg) {
		copied.textContent = msg + ' ';
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'aw-letter-copy-addr';
		b.textContent = 'Copy address';
		b.addEventListener('click', function () {
			copy(cfg.to);
			b.textContent = 'Address copied';
		});
		copied.appendChild(b);
	}

	/* The share box sits under the send buttons all the time (owner, 21 Oct 2026: sharing matters): a message and the
	   site's share bar. After a send its heading turns to asking for three more letters. */
	function shareBlock(holder) {
		if (!holder || holder.dataset.awShareBlock) return;
		holder.dataset.awShareBlock = '1';
		holder.classList.add('aw-share-block');
		/* The share bar's links carry the campaign's share message with the short link (the server's bar has the title). */
		var u = encodeURIComponent(sh.url), t = encodeURIComponent(sh.text || sh.title || '');
		var nets = { x: 'https://x.com/intent/post?text=' + t + '&url=' + u + (sh.hashtags ? '&hashtags=' + encodeURIComponent(sh.hashtags) : ''), whatsapp: 'https://wa.me/?text=' + t + '%20' + u, email: 'mailto:?subject=' + encodeURIComponent(sh.title || '') + '&body=' + t + '%20' + u };
		holder.querySelectorAll('.kit-share a[data-kit-share]').forEach(function (a) { if (nets[a.dataset.kitShare] && sh.text) a.href = nets[a.dataset.kitShare]; });
	}
	var sendShare = null, shareHead = null;
	function showSendShare(sent) {
		if (!sh.url) return;
		if (!sendShare) {
			/* The page's own Share section (owner, 21 Oct 2026) holds the heading and message; else the box carries them. */
			var holder = document.querySelector('[data-aw-share]');
			if (holder && holder.querySelector('.kit-share')) { // the site's one share bar, from the server (owner, 2 Nov 2026: the listing share widget)
				sendShare = holder;
				holder.classList.add('is-in-section');
				shareBlock(holder);
			}
		}
		if (!sendShare) {
			sendShare = document.createElement(holder ? 'div' : 'section');
			sendShare.className = 'aw-letter-share-box' + (holder ? ' is-in-section' : '');
			if (!holder) sendShare.setAttribute('aria-labelledby', 'aw-letter-share-title');
			sendShare.innerHTML = '<div class="aw-letter-share-copy">'
				+ (holder ? '' : '<h3 class="aw-letter-share-head" id="aw-letter-share-title"></h3>'
				+ (sh.closed
					? '<p class="aw-letter-share-lede">The final rules are not out yet. We will post the decision here first. Follow Animal Writes: ' + followLinks() + '</p>'
					: '<p class="aw-letter-share-lede">One letter is one voice. Send this page to three people who care about animals, so each of them can send their own objection' + (sh.close ? ' before ' + sh.close : '') + '.</p>'))
				+ '<p class="aw-letter-share"></p></div>';
			shareHead = sendShare.querySelector('.aw-letter-share-head');
			if (holder) holder.appendChild(sendShare);
			else confirmBox.parentNode.insertBefore(sendShare, confirmBox);
			var row = sendShare.querySelector('.aw-letter-share');
			var bar = document.querySelector('.kit-share');
			if (bar) { row.appendChild(bar.cloneNode(true)); shareBlock(row); } else row.remove();
		}
		if (shareHead) shareHead.textContent = sh.closed ? 'Follow us for the decision.' : (sent ? 'Sent? Now get three more letters in.' : 'Get three more letters in.');
		if (sent && sendShare.classList.contains('is-in-section') && !form.querySelector('.aw-letter-next')) { // a pointer from the letter tool to the Share section
			var nx = document.createElement('p');
			nx.className = 'aw-letter-next';
			nx.innerHTML = sh.closed // after the close day: follow us for the decision (owner, 13 Nov 2026); the Share section is already on the page
				? '<strong>Sent? Follow us for the decision.</strong> ' + followLinks()
				: '<a href="#share"><strong>Sent? Now get three more letters in.</strong> Share this page ' + '<span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a>';
			thanks.parentNode.insertBefore(nx, thanks.nextSibling); // under the thank-you
		}
	}
	showSendShare(false);

	form.querySelectorAll('[data-route]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var route = btn.dataset.route;
			if (!ready()) return;
			var body = letter();
			var e = encodeURIComponent;
			var url = '';
			if (route === 'gmail') url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + e(cfg.to) + '&su=' + e(cfg.subject) + '&body=' + e(body);
			if (route === 'outlook') url = 'https://outlook.office.com/mail/deeplink/compose?to=' + e(cfg.to) + '&subject=' + e(cfg.subject) + '&body=' + e(body);
			if (route === 'mailto') {
				if (body.length > 1800) { // long links fail in some mail apps: copy instead
					route = 'copy';
					copiedNote('Your letter is long for an email app link, so it was copied. Paste it into a new email to ' + cfg.to + '.');
				} else {
					url = 'mailto:' + cfg.to + '?subject=' + e(cfg.subject) + '&body=' + e(body);
				}
			}
			if (route === 'copy') {
				copy(body);
				if (!copied.textContent || btn.dataset.route === 'copy') copiedNote('Letter copied. Paste it into a new email to ' + cfg.to + '.');
			}
			recordSend(route); // before Gmail or the mail app takes the page away
			if (url && route === 'gmail' && gmailApp(cfg.to, cfg.subject, body, url)) url = '';
			if (url) {
				if (route === 'mailto') window.location.href = url;
				else window.open(url, '_blank', 'noopener');
			}
			track('objection_sent', { route: route });
			confirmBox.hidden = false;
			showSendShare(true);
			justSent = true;
			try { localStorage.setItem(sentKey, String(Date.now())); } catch (e) { /* private mode */ }
		});
	});

	// Phones often reload the page on the way back from the Gmail or mail app: bring the share box back, and into view.
	var sentKey = 'aw-letter-routed-' + (sh.slug || '');
	var justSent = false;
	var sentAt = 0;
	try { sentAt = parseInt(localStorage.getItem(sentKey), 10) || 0; } catch (e) { /* private mode */ }
	if (sentAt) {
		showSendShare(true);
		if (sendShare && !location.hash && Date.now() - sentAt < 30 * 60 * 1000) shareTarget().scrollIntoView({ block: 'start' });
	}
	document.addEventListener('visibilitychange', function () {
		if (document.hidden || !justSent || !sendShare) return;
		justSent = false;
		shareTarget().scrollIntoView({ block: 'start', behavior: 'smooth' });
	});
	function shareTarget() { return document.getElementById('share') || sendShare; }

	form.querySelectorAll('[data-confirm]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (btn.dataset.confirm !== 'yes') { // not sent yet: say so, keep the letter
				confirmBox.hidden = true;
				copiedNote('No problem. Your letter is still here: send it when you are ready.');
				return;
			}
			track('objection_confirmed');
			confirmBox.hidden = true;
			thanks.hidden = false;
			thanks.focus();
			try { sessionStorage.setItem('aw-letter-sent', '1'); } catch (e) { /* private mode */ }
			document.dispatchEvent(new CustomEvent('aw:letter-sent'));
			if (cfg.confirm && window.fetch) fetch(cfg.confirm, { method: 'POST', credentials: 'omit', keepalive: true }).catch(function () {});
		});
	});
})();

/* What the draft does: no tile folds (owner, 13 Nov 2026: no expand and collapse on any page); every tile shows its text. */

/* What the draft does: the tiles fade in once, when the grid first comes on screen (campaign-lab.css is-reveal / is-in).
   Skipped with reduced motion or without IntersectionObserver; a timer shows them anyway if the observer never fires. */
(function () {
	var grid = document.querySelector('.aw-camp--lab-rules .aw-camp-grid');
	if (!grid || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	if (grid.getBoundingClientRect().top < window.innerHeight) return;
	Array.prototype.forEach.call(grid.children, function (c, i) { c.style.setProperty('--i', i); });
	grid.classList.add('is-reveal');
	var show = function () { grid.classList.add('is-in'); io.disconnect(); setTimeout(function () { grid.classList.remove('is-reveal', 'is-in'); }, 1000); };
	var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) show(); }, { rootMargin: '0px 0px -10% 0px' });
	io.observe(grid);
	setTimeout(show, 4000);
})();

/* Pain scale (campaign-lab.css): the meters charge once, step after step, when the ladder first comes on screen (skipped
   with reduced motion, without IntersectionObserver, or when the page opens on the ladder). On phones the rail fills as
   the reader scrolls: a step lights once its bar passes 60% of the screen height, and the line to the next step fills
   with it. Scroll reads are batched to one per frame and run only while the ladder is near the screen. */
(function () {
	var ladder = document.querySelector('.aw-camp--lab-rules .aw-camp-ladder');
	if (!ladder) return;
	var tiers = ladder.querySelectorAll('.aw-camp-tier');
	var hasIO = 'IntersectionObserver' in window;
	var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	if (hasIO && !still && ladder.getBoundingClientRect().top > window.innerHeight) {
		Array.prototype.forEach.call(tiers, function (t, i) { t.style.setProperty('--i', i); });
		ladder.classList.add('is-reveal');
		var done = false;
		var show = function () {
			if (done) return;
			done = true;
			ladder.classList.add('is-in');
			io.disconnect();
			setTimeout(function () { ladder.classList.remove('is-reveal', 'is-in'); }, 1600);
		};
		var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) show(); }, { rootMargin: '0px 0px -20% 0px' });
		io.observe(ladder);
		setTimeout(show, 8000);
	}

	if (!hasIO || tiers.length < 2) return;
	var phone = window.matchMedia('(max-width: 767px)');
	var near = false, live = false, frame = 0;
	function paint() {
		frame = 0;
		var line = window.innerHeight * 0.6;
		var tops = Array.prototype.map.call(tiers, function (t) { return t.getBoundingClientRect().top; });
		Array.prototype.forEach.call(tiers, function (t, i) {
			t.classList.toggle('is-on', tops[i] < line);
			if (i < tiers.length - 1) {
				var seg = Math.min(1, Math.max(0, (line - tops[i]) / (tops[i + 1] - tops[i])));
				t.style.setProperty('--aw-seg', (seg * 100).toFixed(1) + '%');
			}
		});
	}
	function onScroll() { if (near && !frame) frame = requestAnimationFrame(paint); }
	new IntersectionObserver(function (e) { near = e[0].isIntersecting; onScroll(); }, { rootMargin: '50% 0px' }).observe(ladder);
	function setup() {
		if (phone.matches === live) return;
		live = phone.matches;
		ladder.classList.toggle('is-live', live);
		if (live) {
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll, { passive: true });
			paint();
		} else {
			window.removeEventListener('scroll', onScroll);
			window.removeEventListener('resize', onScroll);
			Array.prototype.forEach.call(tiers, function (t) { t.classList.remove('is-on'); t.style.removeProperty('--aw-seg'); });
		}
	}
	setup();
	if (phone.addEventListener) phone.addEventListener('change', setup);
	else if (phone.addListener) phone.addListener(setup);
})();

/* Phone bar: shows once the hero has scrolled away; hidden while the letter tool, the newsletter band or the footer is
   on screen, and for the rest of the session after a letter is confirmed. */
(function () {
	var bar = document.querySelector('.aw-sticky-cta');
	if (!bar) return;
	var target = document.querySelector(bar.dataset.target);
	var hero = document.querySelector('.aw-camp-hero');
	var feet = document.querySelectorAll('.aw-nl-band, .wp-site-blocks > footer, footer.wp-block-template-part'); // the band is its own part just above the footer
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
		if (feet.length) {
			var inView = [];
			var fo = new IntersectionObserver(function (en) {
				en.forEach(function (e) { var i = inView.indexOf(e.target); if (e.isIntersecting && i < 0) inView.push(e.target); else if (!e.isIntersecting && i > -1) inView.splice(i, 1); });
				onFoot = inView.length > 0;
				update();
			});
			Array.prototype.forEach.call(feet, function (f) { fo.observe(f); });
		}
	} else {
		onHero = false;
		update();
	}
})();
