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
	/* Share: the phone's own share sheet (Instagram, WhatsApp, Telegram, Snapchat...) plus the story and post images,
	   which go straight to Instagram or Facebook stories through the same sheet; on a computer the images download. */
	var sh = window.awCampShare || {};
	if (sh.short) sh.url = sh.short; /* the campaign's short link (/lab): what Share and Copy link hand out; the page's canonical stays the full address */
	var canShare = !!navigator.share;
	/* The pictures are fetched once, ahead of the tap where possible: a phone only opens its share sheet straight from a tap. */
	var files = {};
	function getFile(kind) {
		var url = kind === 'og' ? (sh.og && sh.og.image) : sh[kind];
		if (!url) return Promise.resolve(null);
		if (!files[kind]) files[kind] = fetch(url).then(function (r) { return r.blob(); }).then(function (blob) {
			var ext = url.slice(url.lastIndexOf('.') + 1).toLowerCase();
			var type = /^image\//.test(blob.type) ? blob.type : ({ png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp' })[ext] || 'image/jpeg';
			return new File([blob], (sh.slug || 'campaign') + '-' + kind + '.' + ext, { type: type }); // apps only take an image they can recognise
		}).catch(function () { return null; });
		return files[kind];
	}
	function canShareFile(file) { return !!(file && navigator.canShare && navigator.canShare({ files: [file] })); }
	/* The story and post pictures are not shown on the page (owner, 2 Nov 2026: they are templates for a share). The
	   picture for a share: the story on a phone, the post elsewhere; the other one, then the link preview image, if unset. */
	function picKind() {
		var phone = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
		return [phone ? 'story' : 'post', phone ? 'post' : 'story', 'og'].filter(function (k) { return k === 'og' ? sh.og && sh.og.image : sh[k]; })[0] || '';
	}
	/* One share with the picture, the message and the link (WhatsApp, Telegram and Facebook keep all three; Instagram
	   keeps the picture, so the link is on the clipboard for its Link sticker); the link alone where files can't go. */
	function shareAll() {
		if (navigator.clipboard) navigator.clipboard.writeText(sh.url).catch(function () {});
		getFile(picKind()).then(function (file) {
			var data = canShareFile(file) ? { files: [file], title: sh.title, text: sh.text + ' ' + sh.url } : { title: sh.title, text: sh.text, url: sh.url };
			return navigator.share(data);
		}).catch(function () {});
	}
	/* The share row in the letter tool (owner, 19 Oct 2026; not the page's own share row): on a phone the share sheet
	   with picture, message and link, plus the story and post images; on a computer WhatsApp, X, Facebook, LinkedIn,
	   Copy link and the images to download. */
	function shareKit(where) {
		function add(el, label, ico, network) {
			el.className = 'aw-camp-share-btn';
			el.dataset.kitShare = network;
			el.innerHTML = '<span class="kit-ico kit-ico--' + ico + '" aria-hidden="true"></span>';
			el.appendChild(document.createTextNode(label));
			where.appendChild(el);
			return el;
		}
		function btn(label, ico, network, fn) {
			var b = document.createElement('button');
			b.type = 'button';
			add(b, label, ico, network).addEventListener('click', fn);
			return b;
		}
		function link(label, ico, network, href) {
			var a = document.createElement('a');
			a.href = href;
			a.target = '_blank';
			a.rel = 'noopener';
			add(a, label, ico, network);
		}
		var u = encodeURIComponent(sh.url), t = encodeURIComponent(sh.text);
		if (canShare) btn('Share', 'share', 'native', shareAll).classList.add('is-primary');
		else {
			link('WhatsApp', 'brand-whatsapp', 'whatsapp', 'https://wa.me/?text=' + t + '%20' + u);
			link('X', 'x-brand', 'x', 'https://twitter.com/intent/tweet?text=' + t + '&url=' + u);
			link('Facebook', 'facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + u);
			link('LinkedIn', 'linkedin', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url=' + u);
			var c = btn('Copy link', 'link', 'copy_link', function () {
				if (navigator.clipboard) navigator.clipboard.writeText(sh.url).catch(function () {});
				c.lastChild.nodeValue = 'Link copied';
			});
		}
		if (canShare && 'IntersectionObserver' in window) { // fetch the picture once the row is near, so the tap opens the sheet at once
			var io = new IntersectionObserver(function (en) {
				if (!en[0].isIntersecting) return;
				io.disconnect();
				getFile(picKind());
			}, { rootMargin: '400px' });
			io.observe(where);
		}
	}

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
	}
	var pickedEl = form.querySelector('.aw-letter-picked');
	function picked() {
		if (!pickedEl) return;
		var n = grounds.filter(function (g) { return g.box.checked; }).length;
		pickedEl.textContent = n + ' of ' + grounds.length + ' chosen';
	}
	picked();

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

	/* The share box sits under the send buttons all the time (owner, 21 Oct 2026: sharing matters): a message, the share
	   buttons (no link preview or pictures shown: 2 Nov 2026). After a send its heading turns to asking for three more letters. */
	/* Share block template (owner, 2 Nov 2026; the same on every campaign): in the Share section's card, one main
	   "Share this page" button (the phone's share sheet with the picture, the message and the short link; where a browser
	   has no share sheet it copies the link and says so), the site's share bar as icons, and the campaign's ready-made
	   story and post pictures: a tap shares that picture with the message and link, or saves it and copies the link. */
	function sharePicture(kind, done) {
		if (navigator.clipboard) navigator.clipboard.writeText(sh.url).catch(function () {});
		done.textContent = canShare ? 'Link copied. Paste it in a Link sticker.' : 'Image saved. The link is copied too: paste it with your post.';
		getFile(kind).then(function (file) {
			if (!file) return;
			if (canShareFile(file)) return navigator.share({ files: [file], title: sh.title, text: sh.text + ' ' + sh.url }).catch(function () {});
			var a = document.createElement('a');
			a.href = URL.createObjectURL(file);
			a.download = file.name;
			document.body.appendChild(a);
			a.click();
			a.remove();
		});
	}
	function shareBlock(holder) {
		if (!holder || holder.dataset.awShareBlock) return;
		holder.dataset.awShareBlock = '1';
		holder.classList.add('aw-share-block');
		var wrap = document.createElement('p');
		wrap.className = 'aw-share-cta';
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'wp-element-button kit-btn kit-btn--brand aw-share-cta-btn';
		b.dataset.kitShare = canShare ? 'native' : 'copy_link';
		b.innerHTML = '<span class="kit-ico kit-ico--' + (canShare ? 'share' : 'link') + '" aria-hidden="true"></span>';
		b.appendChild(document.createTextNode('Share this page'));
		var done = document.createElement('span');
		done.className = 'aw-share-cta-done';
		done.setAttribute('role', 'status');
		done.setAttribute('aria-live', 'polite');
		wrap.appendChild(b);
		wrap.appendChild(done);
		holder.insertBefore(wrap, holder.firstChild);
		var timer;
		b.addEventListener('click', function () {
			if (canShare) { shareAll(holder); return; }
			if (navigator.clipboard) navigator.clipboard.writeText(sh.url).catch(function () {});
			done.textContent = 'Link copied';
			clearTimeout(timer);
			timer = setTimeout(function () { done.textContent = ''; }, 4000);
		});
		var pics = [['story', 'Instagram or Facebook story', 'Story, 9:16'], ['post', 'Instagram or Facebook post', 'Post, 4:5']].filter(function (k) { return sh[k[0]]; });
		if (pics.length) {
			var box = document.createElement('div');
			box.className = 'aw-share-pics';
			var list = document.createElement('div');
			list.className = 'aw-share-pics-list';
			pics.forEach(function (k) {
				var p = document.createElement('button');
				p.type = 'button';
				p.className = 'aw-share-pic aw-share-pic--' + k[0];
				p.dataset.kitShare = k[0] + '_image';
				var img = document.createElement('img');
				img.src = sh[k[0]];
				img.alt = '';
				img.loading = 'lazy';
				img.decoding = 'async';
				p.appendChild(img);
				p.setAttribute('aria-label', (canShare ? 'Share the ' : 'Save the ') + k[1] + ' picture');
				p.title = p.getAttribute('aria-label');
				p.addEventListener('click', function () { sharePicture(k[0], done); });
				list.appendChild(p);
			});
			box.appendChild(list);
			holder.appendChild(box);
		}
		if (canShare && 'IntersectionObserver' in window) { // fetch the pictures once the block is near, so a tap opens the sheet at once
			var io = new IntersectionObserver(function (en) {
				if (!en[0].isIntersecting) return;
				io.disconnect();
				getFile(picKind());
				pics.forEach(function (k) { getFile(k[0]); });
			}, { rootMargin: '400px' });
			io.observe(holder);
		}
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
				+ '<p class="aw-letter-share-lede">One letter is one voice. Send this page to three people who care about animals, so each of them can send their own objection' + (sh.close ? ' before ' + sh.close : '') + '.</p>')
				+ '<p class="aw-letter-share"></p></div>';
			shareHead = sendShare.querySelector('.aw-letter-share-head');
			if (holder) holder.appendChild(sendShare);
			else confirmBox.parentNode.insertBefore(sendShare, confirmBox);
			var row = sendShare.querySelector('.aw-letter-share');
			shareKit(row);
		}
		if (shareHead) shareHead.textContent = sent ? 'Sent? Now get three more letters in.' : 'Get three more letters in.';
		if (sent && sendShare.classList.contains('is-in-section') && !form.querySelector('.aw-letter-next')) { // a pointer from the letter tool to the Share section
			var nx = document.createElement('p');
			nx.className = 'aw-letter-next';
			nx.innerHTML = '<a href="#share"><strong>Sent? Now get three more letters in.</strong> Share this page ' + '<span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a>';
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

/* What the draft does (campaign-lab.css, bento): the four supporting tiles fold (the lead and the full-width last tile stay open). Each tile's own H3 moves into a summary and
   its text into the same details, so the tile shows icon, heading and rule chips and the text opens on tap. No words are
   added; the rule chips stay outside the fold. Without JavaScript nothing changes and every text stays open. */
(function () {
	if (!('open' in document.createElement('details'))) return;
	document.querySelectorAll('.aw-camp--lab-rules .aw-camp-grid > .aw-camp-card:not(:first-child):not(:last-child)').forEach(function (card) {
		var h = card.querySelector(':scope > h3');
		var text = card.querySelectorAll(':scope > p:not(.aw-camp-card-ico):not(.aw-camp-card-chips)');
		if (!h || !text.length) return;
		var fold = document.createElement('details');
		fold.className = 'aw-camp-card-fold';
		var sum = document.createElement('summary');
		card.insertBefore(fold, h);
		sum.appendChild(h);
		fold.appendChild(sum);
		text.forEach(function (p) { fold.appendChild(p); });
	});
})();

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
